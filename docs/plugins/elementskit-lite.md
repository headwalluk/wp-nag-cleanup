# ElementsKit Lite (Wpmet)

- slug: `elementskit-lite` (also `elementskit` — the Pro edition, 1 fleet site)
- version analysed: `4.0.2`
- source: `/vault/backups/wordpress/plugins/elementskit-lite/elementskit-lite,4.0.2.zip`
- licensing: freemium (free on wordpress.org, ElementsKit Pro sold at wpmet.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a
large client site.

**10 fleet sites.** Wpmet ship a set of shared libraries under `libs/`, and four of them
exist solely to put promotional content in the admin. **Five rules added**: one dashboard
widget and four `admin_head` callbacks.

The plugin's own version and dependency notices are created directly in
`elementskit-lite.php` rather than through those libs, so they are untouched — which is
what makes this a clean separation rather than a mixed-output problem.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 2: `Install_Notice::render` and the shared `Oxaim\Libs\Notice::get_notice` framework |
| Vendor opt-out filters | **None** for notices. `libs/` exposes only `elementskit/admin/onboard_steps/list`, `elementskit/admin/settings_sections/list`, `elementskit/widgets/status/update`, `wpmet_onboard_status_map` |
| Vendor opt-out constants | None |
| Dashboard widgets | **1**: `wpmet-stories`, "Wpmet Stories", `wp_dashboard_setup` priority 111 |
| Outbound calls from widgets | `https://api.wpmet.com/public/stories/` |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| "Wpmet Stories" widget | `wp_dashboard_setup` p111 → `wpmet-stories` | **suppress** | Vendor news, fetched from `api.wpmet.com` on render |
| Rating request | `admin_head` → `Wpmet\Libs\Rating::fire` | **suppress** | Review nag |
| Go Pro notice | `admin_head` → `ElementsKit_Lite\Libs\Pro_Label\Init::show_go_pro_notice` | **suppress** | *"To get more amazing features … please get the [Pro]"* → `wpmet.com/elementskit-pricing` |
| Remote banner | `admin_head` → `Wpmet\Libs\Banner::display_content` | **suppress** | Remotely-driven banner, same shape as WP Swings |
| EmailKit cross-sell | `admin_head` → `Wpmet\Libs\Emailkit::emailkit_admin_head` | **suppress** | Cross-sells another Wpmet product |
| Unsupported Elementor version | `Oxaim\Libs\Notice` | keep | **Dependency notice** |
| Unsupported PHP version | `Oxaim\Libs\Notice` | keep | **PHP version warning** |
| Auto-install / email prompt | `admin_notices` → `Install_Notice::render` | keep | Scoped to `page=elementskit` and the Get Help page — the vendor's own screens |

## Deliberately left alone

### The two version notices, and why they are safe

`Oxaim\Libs\Notice` is a shared notice framework used by everything. Critically,
`Notice::instance()` does **`self::$instance = new self();`** — it overwrites the static
each time and returns a fresh object, so it is *not* a keyed registry. Every notice on
`admin_notices` is the same class with the same `get_notice` method, and they cannot be
told apart by class and method alone.

That rules out targeting the framework directly, and it is why these rules attack the
**producers** instead. Each promotional lib builds its notice from its own `admin_head`
callback with its own class, so removing those four leaves `unsupported-elementor-version`
and `unsupported-php-version` — created directly in `elementskit-lite.php:369` and `:389`
— completely untouched.

### The auto-install prompt

`Install_Notice` collects an email address, but `should_render()` ends with:

```php
return in_array( $current_page, array( 'elementskit', 'elementskit-lite_get_help' ), true );
```

Vendor's own screens, so out of scope by construction — the same conclusion reached for
Yoast, ACF and Autoptimize.

### The admin menu "Go Pro" link

An orange `wpmet.com/elementskit-pricing` entry in the admin menu survives, along with its
localized script data. It is promotional but it is the **admin menu**, not the notice area
or the dashboard. Same call as WPB Product Slider's menu styling.

### Credit where due

Wpmet ship a **user-facing consent toggle**, `ekit_user_consent_for_banner`, which gates
the banner, the stories widget and the install prompt. That is better behaviour than most
vendors audited. It is a stored setting rather than a filter, so using it would mean
writing to the database — which this project does not do. The rules achieve the same
visible result per request, with nothing left behind.

## Mechanism

Five rules across two mechanisms.

### Dashboard widget — tier 3

- phase: `wp_dashboard_setup`, `self::LATE_PRIORITY`. The vendor registers at priority
  **111**, so ours runs after
- context: `normal` (three-argument `wp_add_dashboard_widget`)
- the widget also **reorders `$wp_meta_boxes` to force itself to the top**, under the
  comment `// Move our widget to top.` — the third vendor found doing this, after WP Desk
  and Elementor

### Four promotional notices — tier 2, via the shared `$wp_filter` reader

- phase: **`current_screen`**, `self::LATE_PRIORITY`, not `admin_init`. `Pro_Label` adds
  its `admin_head` hook from a `current_screen` handler, so it does not exist at
  `admin_init`
- instance reachable via: **nothing.** `plugin.php` discards all of them —
  `\Wpmet\Libs\Banner::instance( … )` and `\Wpmet\Libs\Rating::instance( … )` are chained
  and dropped, `new Libs\Pro_Label\Init();` and `new \Wpmet\Libs\Emailkit();` are bare
- this is the **third use** of `remove_discarded_instance_callback()`. It qualifies: no
  filter, no constant, no singleton accessor, and the widget half is handled by
  mechanism 3. Each of the four is a **distinct class**, so `instanceof` separates them
  cleanly — no property inspection, no content matching

## Drift check

Re-check when a new version appears in the vault:

- `libs/stories/stories.php` — widget id `wpmet-stories` and the `normal` context
- `libs/rating/rating.php`, `libs/banner/banner.php`, `libs/emailkit/emailkit.php`,
  `libs/pro-label/init.php` — the four class and method names. A rename makes the rule a
  silent no-op, which the debug log reports as "not registered"
- `elementskit-lite.php:369` / `:389` — the two version notices must keep being created
  directly rather than moving into a lib
- If Wpmet promote `ekit_user_consent_for_banner` from a setting to a filter, **withdraw
  all five rules** and use the filter instead

## Verification

Tested on `bench2.local` (WP 7.1) with ElementsKit Lite 4.0.2 active, A/B with the rules
enabled and disabled, over authenticated admin requests:

| Check | Rules off | Rules on |
|---|---|---|
| `id="wpmet-stories"` on the dashboard | **1** | **0** |
| `wpmet.com` occurrences | 11 | **3** (admin menu link only) |
| Notice divs on the dashboard | 22 | **15** |
| PHP fatals | 0 | 0 |

`Rating::fire` and `Banner::display_content` were observed being removed. **`Pro_Label`
and `Emailkit` were not registered on the bench** and their removal is structural rather
than observed:

- `Pro_Label::hook_current_screen` requires the screen to be one of `nav-menus`,
  `toplevel_page_elementskit`, `edit-elementskit_template` or `dashboard`, **and**
  `gmdate( 'd', time() - $activation_stamp ) > 10` — more than ten days since
  activation. A freshly installed bench cannot satisfy that
- `Emailkit` is gated by separate conditions at `plugin.php:316`

Both are logged as "not registered … no action taken", which is the intended drift
signal. On a client site where ElementsKit has been installed for more than ten days the
Go Pro notice *is* registered, and our removal runs on `current_screen` priority 999 —
after `Pro_Label`'s own `current_screen` priority 10 — so it is caught.

## Additions to `headwall-nag-cleanup.php`: 5 rules

```php
[
	'widget_id' => 'wpmet-stories',
	'context'   => 'normal',
	'vendor'    => 'ElementsKit Lite 4.0.2',
	'reason'    => 'Wpmet Stories; fetches wpmet.com on render',
],
```

```php
public function unhook_elementskit_promos() : void {
	$promo_callbacks = [
		[ '\\Wpmet\\Libs\\Rating', 'fire' ],
		[ '\\Wpmet\\Libs\\Banner', 'display_content' ],
		[ '\\Wpmet\\Libs\\Emailkit', 'emailkit_admin_head' ],
		[ '\\ElementsKit_Lite\\Libs\\Pro_Label\\Init', 'show_go_pro_notice' ],
	];

	foreach ( $promo_callbacks as $promo_callback ) {
		$this->remove_discarded_instance_callback(
			'admin_head',
			$promo_callback[0],
			$promo_callback[1],
			'elementskit'
		);
	}
}
```
