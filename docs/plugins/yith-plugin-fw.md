# YITH Plugin Framework (`plugin-fw`)

- slug: n/a — a **framework bundled inside every YITH plugin**, not a plugin of its own
- version analysed: `4.7.8`
- source: `/vault/backups/wordpress/plugins/yith-woocommerce-wishlist/yith-woocommerce-wishlist,4.18.0.zip`,
  cross-checked against `yith-woocommerce-eu-vat-premium` installed on `devx`
- licensing: bundled with both free and premium YITH plugins
- Freemius bundled: no

## Analysis

Analysed on 4 Sep 2026 by Claude Code (Claude Opus 5).

YITH ship a shared framework, `plugin-fw`, inside every one of their plugins. Two
promotional RSS dashboard widgets live in that framework rather than in any
individual plugin, so **one rule covers every YITH plugin a site will ever have**,
free or premium, present or future.

That was confirmed rather than assumed: two unrelated YITH plugins — the free
`yith-woocommerce-wishlist` 4.18.0 from the vault and the premium
`yith-woocommerce-eu-vat-premium` installed on `devx` — bundle byte-identical
`plugin-fw` 4.7.8, registering the same two widget IDs at the same line numbers.

Both widgets call `fetch_feed()` against `yithemes.com` when the dashboard renders.
This is the case the project's dashboard rules exist for: not merely clutter, but an
outbound HTTP request on every dashboard load for a panel nobody reads.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 3 found, all operational — see *Deliberately left alone* |
| Vendor opt-out filters | **`yith_plugin_fw_show_dashboard_widgets`** (`includes/class-yith-dashboard.php:145`) |
| Vendor opt-out constants | None |
| Dashboard widgets | 2 — `yith_dashboard_products_news`, `yith_dashboard_blog_news` (`class-yith-dashboard.php:35-36`) |
| Outbound calls from widgets | Yes — `fetch_feed()` to two `yithemes.com` endpoints |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| YITH Latest Updates | `yith_dashboard_products_news` | suppress | Product news RSS; fetches `https://yithemes.com/latest-updates/feeds/` on render |
| Latest news from YITH Blog | `yith_dashboard_blog_news` | suppress | Vendor blog RSS; fetches `https://yithemes.com/feed/` on render |
| `YITH_System_Status::activate_system_notice` | `admin_notices` p15 | keep | Only renders when `$system_info['errors']` is set — a system requirements failure |
| `yith_plugin_fw_print_deactivation_message` | `admin_notices` | keep | Reports which plugins were just deactivated, on `plugins.php` |
| `YIT_Plugin_Panel::print_panel_tabs_in_wp_pages` | `all_admin_notices` | keep | Renders YITH's own settings tabs, not a notice |

### The vendor provides a switch

`class-yith-dashboard.php:145`:

```php
if ( apply_filters( 'yith_plugin_fw_show_dashboard_widgets', true ) ) {
    add_action( 'wp_dashboard_setup', 'YITH_Dashboard::dashboard_widget_setup' );
    add_action( 'admin_enqueue_scripts', 'YITH_Dashboard::enqueue_scripts', 20 );
}
```

This is mechanism 1 and it beats the `remove_meta_box()` approach on three counts:

1. The widgets are never registered, rather than registered and then removed
2. It also suppresses the `admin_enqueue_scripts` registration in the same block,
   so a script and a stylesheet stop being enqueued on **every** admin page
3. It is the vendor's own sanctioned switch, so it survives a widget being renamed

**Timing is safe.** The filter is evaluated at file-include time, inside the
`if ( ! class_exists( 'YITH_Dashboard' ) )` wrapper that opens the file, reached when
a YITH plugin requires `plugin-fw/yit-plugin.php`. Plugins and themes both load after
mu-plugins, so a filter registered at our file scope is always in place first.

## Deliberately left alone

**`YITH_System_Status::activate_system_notice`**
(`includes/class-yith-system-status.php:98`, priority 15) — returns early unless
`$system_info['errors']` is populated. It is a system-requirements failure warning,
which is squarely on the never-suppress list.

**`yith_plugin_fw_print_deactivation_message`** (`yit-plugin.php:298`) — renders only
on `plugins.php` and only when `yith_deactivated_plugins` is present in the query
string, reporting what was just deactivated. Operational feedback about an action the
administrator has this moment taken.

**`YIT_Plugin_Panel::print_panel_tabs_in_wp_pages`** (`class-yit-plugin-panel.php:242`) —
registered on `all_admin_notices`, but it prints YITH's settings-panel tabs, not a
notice. It is part of the vendor's own interface, which is out of scope by
construction.

## Mechanism

- tier: 1 (vendor opt-out hook)
- phase: file scope
- vendor registers at: file-include time of `plugin-fw/includes/class-yith-dashboard.php`,
  required from `plugin-fw/yit-plugin.php:36` when any YITH plugin loads
- instance reachable via: N/A

### Superseded approach

Versions 0.1.0 through 0.1.2 removed these two widgets with `remove_meta_box()` on
`wp_dashboard_setup` (mechanism 3). That worked, but it was found before the vendor
filter was. Replaced in 1.0.0.

The reason it was missed is worth recording: the analysis checklist's opt-out-filter
search matched on `notice|promo|upsell|nag|review|deal|banner|sale|discount|tracking|
opt_in|announcement`, and `yith_plugin_fw_show_dashboard_widgets` contains none of
those words. The checklist has since been widened to include `widget` and `dashboard`.

## Drift check

Re-check when a new `plugin-fw` version appears inside any YITH plugin:

1. `includes/class-yith-dashboard.php` — does `yith_plugin_fw_show_dashboard_widgets`
   still gate the `wp_dashboard_setup` registration?
2. Are there new `wp_add_dashboard_widget()` calls outside that `if` block?
3. Is `plugin-fw` still shared verbatim across YITH plugins? If YITH ever fork it
   per-plugin, one rule stops covering the whole vendor.

To find the framework version in any YITH plugin:
`grep -m1 'Version:' <plugin>/plugin-fw/init.php`

## Additions to `headwall-nag-cleanup.php`: YITH dashboard widgets

```php
// YITH plugin framework (plugin-fw) 4.7.8, bundled in every YITH plugin.
// Suppresses both yithemes.com RSS dashboard widgets and the script/style
// enqueue that accompanies them.
add_filter( 'yith_plugin_fw_show_dashboard_widgets', '__return_false' );
```
