# Magical Addons For Elementor

- slug: `magical-addons-for-elementor`
- version analysed: `1.5.0` (also read: `1.4.6`, the only other release in the vault)
- source: `/vault/backups/wordpress/plugins/magical-addons-for-elementor/magical-addons-for-elementor,1.5.0.zip`
- licensing: freemium (Magical Addons Pro and Magical Posts Display Pro are separate plugins)
- Freemius bundled: no
- vendor: WP Theme Space (`wpthemespace.com`)

## Analysis

Analysed on 18 Sep 2026 by Claude Code (Claude Opus 5).

Paul reported it from a live client site: a large sales notice on every admin screen,
*"Magical Theme Builder is Live! Get Magical Addons Pro + Magical Posts Display Pro for Just
$29"*, with struck-through pricing, a "50% OFF" tag and a feature list.

One rule, covering two callbacks: the 1.5.0 sales notice, and the 1.4.6 review request
from the same class. The plugin's dependency and version notices are left alone.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 1.5.0: 5. `madAdminInfo::display_sales_notice`, three in `includes/basic/mg-admin-notice.php` (Elementor missing, Elementor too old, PHP too old), `theme-builder/module.php` `render_dependency_notice`. One promotional. 1.4.6 adds `madAdminInfo::display_review_notice` and `admin_notice_gsap_feature` |
| Multiline `add_action(` form | None |
| `in_admin_header` / `admin_print_footer_scripts` | None, and no `wp-pointer` |
| Vendor opt-out filters | **None**. The only `apply_filters` hit is `mgtb/widgets` |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls from widgets | None. The one `wp_remote_post` is the Mailchimp form widget's front-end subscribe handler |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Pro bundle sales notice (`.mg-sales-notice`) | `admin_notices` → `madAdminInfo::display_sales_notice` | **suppress** | Priced upsell for two Pro plugins. Shown unless *both* Pro plugins are active. Dismissal lasts 25 days, then it comes back |
| Review request (1.4.6 only) | `admin_notices` → `madAdminInfo::display_review_notice` | **suppress** | *"Enjoying Magical Addons? … Leave a Review"*, after 7 days |
| Elementor missing / too old, PHP too old | `admin_notices` → `mg-admin-notice.php` instance methods | keep | Dependency and version warnings. The plugin does not load without them |
| Theme builder dependency notice | `admin_notices` → `render_dependency_notice` | keep | Dependency warning |
| GSAP feature notice (1.4.6 only) | `admin_notices` → `admin_notice_gsap_feature` | keep | See below |

## Deliberately left alone

### `admin_notice_gsap_feature` (1.4.6 only)

A feature announcement for scroll animations *in the free plugin itself*, shown on the
dashboard and Elementor screens until dismissed. It names no price and no Pro product.
It is not a review request, a cross-sell or an upsell, so it is **ambiguous, and no rule**.
1.5.0 removed it anyway.

### `mgaddons_admin_scripts` — the notice's asset enqueue

`madAdminInfo::mgaddons_admin_scripts` enqueues `mg-admin-info.js`, which only handles the
sales notice's dismiss button. It also enqueues `mg-admin-info.css`, which carries rules
for other admin markup too (`.mgadin-hero`, `.mge-info-*`, `.eye-notice`,
`.magical-review-notice`). Dequeuing it could restyle the vendor's own pages, so the
enqueue stays. The cost is one small, inert script and stylesheet per admin page.

### The vendor's own admin page

`mgAdmin_Info_Items` (`includes/admin/admin-page.php`) builds the plugin's own settings
screen and its Pro links. **Out of scope by construction.**

## Mechanism

- tier: 2 (targeted unhook). Static callbacks, so named directly with no `$wp_filter` read
- phase: `admin_init` at `self::LATE_PRIORITY`, in `unhook_vendor_notices()`
- vendor registers at: the main plugin's loader `include_once`s
  `includes/admin/helper/admin-info.php`, whose last line is `madAdminInfo::init();`. That
  adds `admin_notices` → `[ __CLASS__, 'display_sales_notice' ]` at priority 10 while the
  plugin loads, long before `admin_init`
- instance reachable via: N/A. `[ 'madAdminInfo', 'display_sales_notice' ]`

**Trap: case.** The class is declared `madAdminInfo` (lowercase `m`, capital `A` and `I`).
`__CLASS__` returns the declared spelling, and `_wp_filter_build_unique_id()` keys a static
callback by literal `class::method` string. A differently-cased name removes nothing and
looks like success. This is the Easy FancyBox trap again.

`has_action()` gates each removal and its log line, so the 1.4.6-only
`display_review_notice` is silent on 1.5.0 rather than logging a false removal.

## Drift check

- `includes/admin/helper/admin-info.php` — class still `madAdminInfo`, still static, still
  `add_action( 'admin_notices', [ __CLASS__, 'display_sales_notice' ] )`. If a later release
  adds a new promo method to this class (a seasonal one is likely, given the pattern), it
  needs its own entry in `$promo_callbacks`
- If the class becomes an instance, the rule stops matching silently. Re-check on every
  new vault version

```bash
unzip -p /vault/backups/wordpress/plugins/magical-addons-for-elementor/magical-addons-for-elementor,<version>.zip \
  magical-addons-for-elementor/includes/admin/helper/admin-info.php | command grep -n "^class\|add_action"
```

## Verification

Bench: `bench2.local`, Elementor 4.2.4 active, Magical Addons **1.5.0** unzipped from the
vault, 18 Sep 2026. Before = 1.30.0 (HEAD), after = working copy,
`HEADWALL_NAG_CLEANUP_DEBUG` on, three warm-up requests after the deploy.

No time gate on the sales notice. It shows straight away unless both Pro plugins are
active, or the current user dismissed it within the last 25 days
(`mg_bundle_sales_notice_dismissed` user meta).

### Sales notice — **Confirmed** (bench)

| Check (dashboard and plugins.php) | Before | After |
|---|---|---|
| `mg-sales-notice` | **1** | **0** |
| `mg_bundle_dismiss` (the dismiss link) | **1** | **0** |
| Debug log | — | `magical-addons-for-elementor: Removed madAdminInfo::display_sales_notice from admin_notices.` |

### Review request (1.4.6) — **Source-verified only**

Not on the bench. The vault's 1.4.6 registers it as `[ __CLASS__, 'display_review_notice' ]`
on `admin_notices`, from the same `madAdminInfo::init()`, so it is the same shape as the
confirmed rule.

### Negative checks

| Check | Result |
|---|---|
| Screens asserted: `id="dashboard-widgets"`, `id="the-list"` | yes |
| Other `class="notice…"` elements on plugins.php | unchanged; the diff is only the sales notice and its dismiss button |
| `mgaddons-admin-info` enqueue | still present (4 before, 4 after), as intended |
| `mg_bundle_sales_notice_dismissed` user meta | absent, so no dismissal written |
| PHP fatals / warnings / parse errors | **0** |

The Elementor-missing notice was not exercised: it needs Elementor deactivated. It is a
separate instance-method callback that no rule names.

### Live — **Confirmed**, 18 Sep 2026

Paul deployed 1.31.0 to the live client site where the sales notice was reported and confirmed
it gone the same day.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_vendor_notices():
$this->unhook_magical_addons_promos();

public function unhook_magical_addons_promos() : void {
	$promo_callbacks = [
		[ 'madAdminInfo', 'display_sales_notice' ],
		[ 'madAdminInfo', 'display_review_notice' ],
	];

	foreach ( $promo_callbacks as $promo_callback ) {
		if ( false === has_action( 'admin_notices', $promo_callback ) ) {
			// Not installed, or this version does not register it.
		} else {
			remove_action( 'admin_notices', $promo_callback );
			$this->log( 'magical-addons-for-elementor', sprintf( 'Removed %s::%s from admin_notices.', $promo_callback[0], $promo_callback[1] ) );
		}
	}
}
```
