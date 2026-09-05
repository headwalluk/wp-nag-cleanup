# Premium Addons for Elementor

- slug: `premium-addons-for-elementor`
- version analysed: `4.11.102`
- source: `/vault/backups/wordpress/plugins/premium-addons-for-elementor/premium-addons-for-elementor,4.11.102.zip`
- licensing: freemium (free on wordpress.org, Premium Addons PRO sold at premiumaddons.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a live
client site — an upsell notice carrying the `pa-new-feature-notice` CSS class.

4 fleet sites. **One rule added**: the "Premium Addons News" dashboard widget, which
fetches the vendor's API on every dashboard render. The `pa-new-feature-notice` notices
Paul saw are **not** removed, and the reason is worth recording — they share a single
callback with an operational dependency check.

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
| Review request | `admin_notices` → `show_review_notice()` | keep — **mixed callback** | Review begging, but see below |
| "New feature" / Angie notices | `admin_notices` → `get_angie_notice()` | keep — **mixed callback** | `pa-new-feature-notice`, the class Paul reported |
| Connect AI notice | `admin_notices` → `get_connect_ai_notice()` | keep — **mixed callback** | `pa-connect-ai-notice`, also promotional |

## Deliberately left alone

### The `pa-new-feature-notice` upsells share a callback with the dependency check

This is the notice Paul actually reported, so the reason it survives matters.

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

Mixed output, and the collateral is a dependency notice. No rule.

The vendor's own gate, `Helper_Functions::check_hide_notifications()`, is not usable
either: it returns true only when Premium Addons **PRO** is installed *and* white
labelling is switched on. It is a Pro feature, not an opt-out available to us — and note
it sits *after* the review notice, so even Pro users still get that one.

### If this needs revisiting

The clean fix would be for the vendor to split the dispatcher. Failing that, the only
route would be a `$wp_filter`-style intervention, which cannot help here — the problem is
not reaching the callback but that the callback does too much. A rule remains impossible
while `required_plugins_check()` shares it.

## Mechanism

- tier: 3 (dashboard widget removal)
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
- `admin/includes/admin-notices.php` — `admin_notices()`. **If `required_plugins_check()`
  is ever moved to its own callback, the upsell notices become a clean mechanism 2
  target** and this document's main finding can be revisited
- Any new `apply_filters` around the notices, which would make this a mechanism 1 rule

## Verification

Tested on `bench2.local` (WP 7.1) with Premium Addons 4.11.102 active, A/B with the rule
enabled and disabled, over authenticated admin requests:

| Check | Rule off | Rule on |
|---|---|---|
| `id="pa-stories"` on the dashboard | **1** | **0** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 3

```php
[
    'widget_id' => 'pa-stories',
    'context'   => 'column3',
    'vendor'    => 'Premium Addons for Elementor 4.11.102',
    'reason'    => 'Premium Addons News; fetches premiumaddons.com on render',
],
```
