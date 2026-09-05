# QuadLayers — shared Composer packages

- slug: covers `insta-gallery`, `autocomplete-woocommerce-orders`, `quadmenu`,
  `search-exclude`, `woocommerce-checkout-manager`
- version analysed: Insta Gallery (Social Feed Gallery) `5.0.8`
- source: `/vault/backups/wordpress/plugins/insta-gallery/insta-gallery,5.0.8.zip`
- licensing: freemium (free on wordpress.org, Pro sold at quadlayers.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a
client site.

QuadLayers split their admin surfaces into **six separate Composer packages** under
`jetpack_vendor/quadlayers/`, with self-describing names. That turns out to be unusually
convenient: the promotional and the operational notices live in *different packages*, so
removing one cannot touch the other.

**Two rules added**, covering **five fleet plugins across 8 sites**.

### The packages, and what each is for

| Package | Purpose | Verdict |
|---|---|---|
| `wp-notice-plugin-promote` | Review nag and cross-install promos | **suppress** |
| `wp-dashboard-widget-news` | "QuadLayers News" dashboard widget | **suppress** |
| `wp-notice-plugin-required` | *"not working because you need to activate…"* | **keep — dependency notice** |
| `wp-plugin-install-tab` | Adds a tab to Plugins → Add New | keep — plugin installer, not the notice area |
| `wp-plugin-suggestions` | Plugin suggestions | keep — same reason |
| `wp-plugin-table-links` | Links in the plugin list row | keep — out of scope |

### The framework win

All four other QuadLayers plugins on the fleet bundle the same packages (19 package files
each, verified by listing their newest vault releases). The wiring files in
`vendor_packages/` guard with `class_exists()`, so whichever plugin loads first owns the
package — **one removal covers the whole range**, and any QuadLayers plugin installed in
future.

| Plugin | Sites |
|---|---|
| `autocomplete-woocommerce-orders` | 3 |
| `insta-gallery` | 2 |
| `quadmenu` | 1 |
| `search-exclude` | 1 |
| `woocommerce-checkout-manager` | 1 |

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 3: promote, promote's dev-mode data wipe, and required |
| Vendor opt-out filters | **None.** The only two `apply_filters` in the packages are core's `install_plugins_tabs` / `install_plugins_nonmenu_tabs`, which QuadLayers *use* to add their tab |
| Vendor opt-out constants | None |
| Dashboard widgets | **1**: `wp-dashboard-widget-news`, "QuadLayers News", registered at priority **-10** |
| Outbound calls from widgets | A vendor feed, plus `quadlayers.com/shop` links |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Review nag and cross-sells | `admin_notices` → `WP_Notice_Plugin_Promote\Load::admin_notices` | **suppress** | *"Enjoying Social Feed Gallery?"* plus cross-install promos for their TikTok Feed plugin, `utm_campaign=cross_sell` |
| "QuadLayers News" widget | `wp_dashboard_setup` −10 → `wp-dashboard-widget-news` | **suppress** | *"Hi! We are Quadlayers!"* with a shop button |
| Missing dependency | `admin_notices` → `WP_Notice_Plugin_Required\Load::admin_notices` | **keep** | *"The %1$s is not working because you need to activate/install the %2$s plugin"* |
| Dev-mode data wipe | `admin_notices` → `Load::remove_all_data` | keep (moot) | Only registered when `$this->developer_mode` is true |

## Deliberately left alone

**The dependency notice is in its own package.** `wp-notice-plugin-required` is a separate
Composer package with its own `Load` class and its own `admin_notices` callback. Removing
the promote package's callback cannot affect it — no mixed output, no collateral, nothing
to weigh. This is the cleanest separation found in any vendor audited.

**The plugin-installer surfaces.** `wp-plugin-install-tab` adds a QuadLayers tab to
Plugins → Add New via core's `install_plugins_tabs` filter, and `wp-plugin-suggestions`
suggests plugins. Both are promotional and both are on a core screen, but they are the
**plugin installer**, not the admin notice area or the dashboard. Same call as WPB's admin
menu styling and ElementsKit's Go Pro menu entry.

**`wp-plugin-table-links`** adds links to the plugin list row. Out of scope.

**`remove_all_data`** is gated on `$this->developer_mode` and never fires on a normal
install. No rule needed, recorded so it is not mistaken for a live surface later.

## Mechanism

### Review and cross-sell notice — tier 2, via the shared `$wp_filter` reader

- phase: `admin_init`, `self::LATE_PRIORITY`. The constructor adds the `admin_notices`
  hook at plugin load, so it exists well before
- instance reachable via: **nothing.** `vendor_packages/wp-notice-plugin-promote.php:65`
  is `new \QuadLayers\WP_Notice_Plugin_Promote\Load( … )` with the return value discarded
- **fourth use** of `remove_discarded_instance_callback()`. It qualifies: no filter, no
  constant, no singleton accessor on this class, and it is not a dashboard widget

### "QuadLayers News" widget — tier 3

- phase: `wp_dashboard_setup`, `self::LATE_PRIORITY`. The vendor registers at **−10**, so
  ours runs comfortably after
- context: `normal` (three-argument `wp_add_dashboard_widget`)
- Note the widget class *does* expose `Load::instance()`, so mechanism 2 was available —
  mechanism 3 was chosen because it is the earlier mechanism for a dashboard widget and
  needs no instance at all

## Drift check

Re-check when a new version appears in the vault:

- `jetpack_vendor/quadlayers/wp-notice-plugin-promote/src/Load.php` — the class name and
  the `admin_notices` method. A rename makes the rule a silent no-op, logged as "not
  registered"
- `jetpack_vendor/quadlayers/wp-dashboard-widget-news/src/Load.php` — widget id
  `wp-dashboard-widget-news` and the `normal` context
- **`wp-notice-plugin-required` must stay a separate package.** If QuadLayers ever merge
  the promotional and dependency notices into one class, this becomes a mixed-output
  problem and the rule must be withdrawn
- If a sixth QuadLayers plugin appears on the fleet, no new rule is needed — but confirm
  it bundles the same packages

## Verification

Tested on `bench2.local` (WP 7.1) with Insta Gallery 5.0.8 active, A/B with the rules
enabled and disabled, over authenticated admin requests:

| Check | Rules off | Rules on |
|---|---|---|
| `id="wp-dashboard-widget-news"` | **1** | **0** |
| `quadlayers.com` occurrences | **5** | **0** |
| "Enjoying Social Feed Gallery?" | **1** | **0** |
| Notice divs on the dashboard | 19 | **15** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

## Additions to `headwall-nag-cleanup.php`: 2 rules

```php
[
	'widget_id' => 'wp-dashboard-widget-news',
	'context'   => 'normal',
	'vendor'    => 'QuadLayers wp-dashboard-widget-news, Insta Gallery 5.0.8',
	'reason'    => 'QuadLayers News; vendor feed and shop links',
],
```

```php
public function unhook_quadlayers_promote_notice() : void {
	$this->remove_discarded_instance_callback(
		'admin_notices',
		'\\QuadLayers\\WP_Notice_Plugin_Promote\\Load',
		'admin_notices',
		'quadlayers'
	);
}
```
