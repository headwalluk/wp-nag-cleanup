# Premium Addons for Elementor

- slug: `premium-addons-for-elementor`
- version analysed: `4.11.102`
- source: `/vault/backups/wordpress/plugins/premium-addons-for-elementor/premium-addons-for-elementor,4.11.102.zip`
- licensing: freemium (free on wordpress.org, Premium Addons PRO sold at premiumaddons.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a live
client site — an upsell notice carrying the `pa-new-feature-notice` CSS class.

4 fleet sites. **Two rules added.** The "Premium Addons News" dashboard widget is
removed, and the three promotional notices are removed by **swapping the vendor's notice
dispatcher for its dependency check alone**.

The second rule was written on a later pass. The first pass concluded no rule was
possible, because one callback prints both the Elementor dependency notice and three
promos. That was wrong: both halves are public methods, so the dispatcher can be replaced
with the operational half rather than suppressed wholesale. The rejected reasoning is kept
below, because the technique generalises.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | **1** — `Admin_Notices::admin_notices`, a dispatcher printing several notices |
| Vendor opt-out filters | None relevant. Only `premium_addons/angie/is_pa_widget` and a WooCommerce filter |
| Vendor opt-out constants | None usable — see `check_hide_notifications()` below |
| Dashboard widgets | **1**: `pa-stories`, "Premium Addons News", context `column3`, registered on `wp_dashboard_setup` priority 111 |
| Outbound calls from widgets | `https://premiumaddons.com/wp-json/stories/v2/get`, cached in a transient |
| Freemius | Not bundled |

## Findings

| Item | Hook / ID | Verdict | Reason |
|---|---|---|---|
| "Premium Addons News" widget | `wp_dashboard_setup` → `pa-stories` | **suppress** | Vendor news feed plus an outbound API call on render. No site state |
| Elementor dependency check | `admin_notices` → `required_plugins_check()` | keep | *Install Elementor* prompt — a missing dependency notice |
| Review request | `admin_notices` → `show_review_notice()` | **suppress** | Review begging (`pa_review_notice`) |
| "New feature" / Angie notices | `admin_notices` → `get_angie_notice()` | **suppress** | `pa-new-feature-notice` (`pa-angie-not`) |
| Connect AI notice | `admin_notices` → `get_connect_ai_notice()` | **suppress** | *"Connect ChatGPT/Claude … Check it Out!"* (`pa-connect-ai-not`) |

## Deliberately left alone

### Why the first pass concluded no rule was possible

Kept because the mistake is instructive.

Premium Addons registers exactly one `admin_notices` callback, which dispatches
everything in sequence:

```php
public function admin_notices() {
    if ( wp_doing_ajax() ) { return; }

    $this->required_plugins_check();          // operational

    $review_state = self::get_notice_state( self::REVIEW_OPTION );
    if ( '1' !== $review_state && (int) $review_state < time() ) {
        $this->show_review_notice();          // review nag
    }

    if ( Helper_Functions::check_hide_notifications() ) { return; }

    if ( defined( 'ANGIE_VERSION' ) ) { $this->get_angie_notice(); }
    $this->get_connect_ai_notice();           // upsells, pa-new-feature-notice
}
```

`required_plugins_check()` runs **first and unconditionally**. It prints an *install
Elementor* prompt with a nonced install URL when Elementor is missing — a dependency
notice, on the never-suppress list. Unhooking `admin_notices` to remove the upsells takes
that with it, on a plugin whose entire function depends on Elementor being present.

The first pass stopped there: mixed output, collateral is a dependency notice, no rule.

That conclusion held only if the callback is atomic. It is not.
`required_plugins_check()` is declared **`public`** (line 196), as is `admin_notices()`
(line 132). So the dispatcher can be removed and the dependency check re-added on its own:

```php
remove_action( 'admin_notices', [ $notices, 'admin_notices' ] );
add_action( 'admin_notices', [ $notices, 'required_plugins_check' ] );
```

The three promotional methods — `show_review_notice()`, `get_angie_notice()`,
`get_connect_ai_notice()` — are private and only ever called from the dispatcher, so
removing it means they cannot run. Nothing is dismissed on the site owner's behalf and no
option is written.

**The lesson generalises**: when a callback is mixed, check the visibility of its parts
before declaring it atomic. A public operational method can be re-hooked.

### Why the option-writing alternative was rejected

The three notices are gated on options `pa_review_notice`, `pa-angie-not` and
`pa-connect-ai-not`, each set to `'1'` when the user dismisses. Writing those three
options would suppress the promos and leave the dependency notice untouched, and was
seriously considered — it would have needed a second, opt-in file to keep this plugin's
read-only guarantee intact.

Rejected once the dispatcher swap was found. It writes permanent per-site residue that
removing this plugin would not undo, it records a dismissal the site owner never made, and
it would need doing again for every vendor that gates on an option. The hook-level route
achieves the same visible result, per request, with nothing left behind.

### The vendor's own gate is not usable

`Helper_Functions::check_hide_notifications()` returns true only when Premium Addons
**PRO** is installed *and* white labelling is switched on. It is a Pro feature, not an
opt-out available to us — and it sits *after* the review notice, so even Pro users get
that one.

## Mechanism

Two rules.

### Notices — tier 2, dispatcher swap

- phase: `admin_init`, priority 999. Premium Addons registers on `admin_notices` from
  `Admin_Notices::__construct`, reached via `Admin_Helper` during plugin load, so ours
  runs comfortably later and before `admin_notices` fires
- instance reachable via: **`\PremiumAddons\Admin\Includes\Admin_Notices::get_instance()`**
  — a public static singleton accessor
- the swap is guarded: if `required_plugins_check()` cannot be found, **nothing is
  removed**. Losing an operational dependency notice would be worse than leaving three
  promos in place
- `required_plugins_check` is re-added at default priority 10, preserving the original
  ordering relative to other plugins' notices

### Dashboard widget — tier 3
- phase: `wp_dashboard_setup`, priority 999
- vendor registers at: `Admin_Notices::show_story_widget` on `wp_dashboard_setup`
  priority 111, so our 999 runs after it and `remove_meta_box()` finds the widget
- instance reachable via: N/A for mechanism 3
- context: **`column3`**, not the default `normal` — `wp_add_dashboard_widget()` is called
  with `$context = 'column3'` and `$priority = 'core'`, and `remove_meta_box()` must match

Removing the meta box means the render callback never runs, so the
`premiumaddons.com/wp-json/stories/v2/get` request is never made.

## Drift check

Re-check when a new version appears in the vault:

- `admin/includes/admin-notices.php` — `show_story_widget()`. If the widget ID or the
  `column3` context changes, the rule silently stops matching
- `admin/includes/admin-notices.php` — **`required_plugins_check()` must stay `public`
  and must stay the whole of the operational output of `admin_notices()`.** If the
  dispatcher gains further operational notices, the swap would silently drop them. This is
  the single most important line to re-read on a new version
- Any new `apply_filters` around the notices, which would make this a mechanism 1 rule

## Verification

Tested on `bench2.local` (WP 7.1) with Premium Addons 4.11.102 active, A/B with the rule
enabled and disabled, over authenticated admin requests:

| Check | Rules off | Rules on |
|---|---|---|
| `id="pa-stories"` on the dashboard | **1** | **0** |
| Elementor dependency notice on `plugins.php` | **1** | **1** — preserved |
| `pa-connect-ai-notice` on `plugins.php` | **1** | **0** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

The review and Angie notices could not be observed on the bench — the first is time-gated
and the second requires `ANGIE_VERSION` to be defined by a companion plugin. Their removal
is structural rather than observed: both are private methods called only from the
dispatcher, which no longer runs.

## Additions to `headwall-nag-cleanup.php`: 2 rules

```php
public function unhook_premium_addons_promos() : void {
	$notices_class = '\\PremiumAddons\\Admin\\Includes\\Admin_Notices';

	if ( ! class_exists( $notices_class ) || ! method_exists( $notices_class, 'get_instance' ) ) {
		return;
	}

	$notices = $notices_class::get_instance();

	if ( ! is_object( $notices ) || ! method_exists( $notices, 'required_plugins_check' ) ) {
		// Without the dependency check to put back, removing the dispatcher would
		// lose an operational notice. Leave the promos rather than risk that.
		$this->log( 'premium-addons', 'required_plugins_check not reachable; no action taken.' );
	} else {
		remove_action( 'admin_notices', [ $notices, 'admin_notices' ] );
		add_action( 'admin_notices', [ $notices, 'required_plugins_check' ] );
		$this->log( 'premium-addons', 'Swapped admin_notices dispatcher for required_plugins_check.' );
	}
}
```

Plus the mechanism 3 widget entry:

```php
[
    'widget_id' => 'pa-stories',
    'context'   => 'column3',
    'vendor'    => 'Premium Addons for Elementor 4.11.102',
    'reason'    => 'Premium Addons News; fetches premiumaddons.com on render',
],
```
