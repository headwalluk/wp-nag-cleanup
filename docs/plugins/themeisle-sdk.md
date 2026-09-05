# ThemeIsle SDK — shared across Menu Icons, WPCF7 Redirect and others

- slug: `themeisle-sdk` (a Composer package, `vendor/codeinwp/themeisle-sdk`)
- version analysed: as bundled in Menu Icons `0.13.24`
- source: `/vault/backups/wordpress/plugins/menu-icons/menu-icons,0.13.24.zip`
- licensing: the SDK is free; it is bundled by both free and paid ThemeIsle products
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a
live client site.

ThemeIsle ship a shared SDK inside their plugins. One of its modules adds a dashboard
widget titled **"WordPress Guides/Tutorials"** which fetches two external RSS feeds on
every dashboard render and lists other ThemeIsle products to install.

The vendor provides a clean opt-out filter, so this is a one-line mechanism 1 rule that
covers every plugin bundling the SDK.

### Which fleet plugins bundle it

Found by opening the newest vault release of all 1,129 distinct fleet slugs and testing
for a `themeisle-sdk/` path:

| Plugin | Sites |
|---|---|
| `menu-icons` | 8 |
| `wpcf7-redirect` | 7 |
| `robin-image-optimizer` | 1 |

16 sites today, and any ThemeIsle plugin installed in future is covered automatically —
the same leverage as the YITH, WP Desk and Brainstorm Force rules.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | None from the dashboard widget module |
| Vendor opt-out filters | **`themeisle_sdk_hide_dashboard_widget`**, plus a per-product `{slug}_load_dashboard_widget` |
| Vendor opt-out constants | None |
| Dashboard widgets | **1**: widget ID `themeisle`, on both `wp_dashboard_setup` and `wp_network_dashboard_setup` |
| Outbound calls from widgets | `https://themeisle.com/blog/feed`, `https://wpshout.com/feed`, plus `api.wordpress.org` queries for the author's other plugins and themes |
| Freemius | Not bundled |

## Findings

| Item | Hook / ID | Verdict | Reason |
|---|---|---|---|
| "WordPress Guides/Tutorials" widget | `wp_dashboard_setup` → widget ID `themeisle` | **suppress** | Vendor blog feed plus "Popular plugin / Install" cross-sells. No site state |

The widget title is confirmed in the SDK's own labels: `Loader.php:92` sets
`'title' => 'WordPress Guides/Tutorials'`, matching what was seen on the client site.

Its body renders two external feeds, then a `themeisle_sdk_recommend_plugin_or_theme`
block listing other ThemeIsle products with **Install** links and a "Powered by" footer.
It is a vendor news and catalogue widget in its entirety — there is no operational
content mixed in, so no collateral to weigh.

## Deliberately left alone

Nothing else in the SDK is touched. The other modules — `Licenser`, `Translations`,
`Rollback` — handle licence validation, translation updates and version rollback. All
operational, all left alone, and all make their own outbound calls which this rule does
not affect.

The per-product filter `{$product_slug}_load_dashboard_widget` was rejected in favour of
the generic one: it would need a rule per ThemeIsle plugin, whereas
`themeisle_sdk_hide_dashboard_widget` covers the SDK once.

## Mechanism

- tier: 1 (vendor hook)
- phase: file scope
- vendor registers at: `Dashboard_Widget::load()`, called by the SDK loader when the
  bundling plugin loads. The filter is read at the very top of `load()` and returns
  before the widget is registered — so `wp_dashboard_setup` never gains the action and
  **the feeds are never fetched**
- instance reachable via: N/A

```php
public function load( $product ) {
    if ( apply_filters( 'themeisle_sdk_hide_dashboard_widget', false ) ) {
        return;
    }
    ...
```

The SDK lives inside a plugin's `vendor/` directory, so it cannot load before regular
plugins. A file-scope filter from an mu-plugin is always in place first.

Note the widget also guards itself with
`if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['themeisle'] ) ) { return; }`,
so on a site with several ThemeIsle plugins only one registers it. That is exactly the
mutex problem that made `remove_meta_box()` the wrong choice for WP Desk, and another
reason to prefer the filter here.

## Drift check

Re-check when a new version appears in the vault:

- `vendor/codeinwp/themeisle-sdk/src/Modules/Dashboard_widget.php` — the
  `themeisle_sdk_hide_dashboard_widget` guard at the top of `load()`
- `src/Loader.php` — the `dashboard_widget` labels, if the widget is renamed
- Whether any ThemeIsle plugin newly appearing on the fleet bundles the SDK

## Verification

Tested on `bench2.local` (WP 7.1) with Menu Icons 0.13.24 active, over authenticated
admin requests, A/B with the rule enabled and disabled:

| Check | Rule off | Rule on |
|---|---|---|
| `id="themeisle"` on the dashboard | **1** | **0** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 1

```php
// ThemeIsle SDK, bundled in Menu Icons, WPCF7 Redirect and others.
// Menu Icons 0.13.24. docs/plugins/themeisle-sdk.md
add_filter( 'themeisle_sdk_hide_dashboard_widget', '__return_true' );
```
