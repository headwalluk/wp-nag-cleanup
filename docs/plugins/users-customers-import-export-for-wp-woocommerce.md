# Export and Import Users and Customers (WebToffee)

- slug: `users-customers-import-export-for-wp-woocommerce`
- version analysed: `2.7.8`
- source: `/vault/backups/wordpress/plugins/users-customers-import-export-for-wp-woocommerce/users-customers-import-export-for-wp-woocommerce,2.7.8.zip`
- licensing: freemium (free on wordpress.org; Pro add-on and Import Export Suite sold by WebToffee)
- Freemius bundled: no

Siblings read for the shared mechanisms, not separately analysed: `order-import-export-for-woocommerce` 2.8.1 and
`product-import-export-for-woo` 2.7.0, the latest of each in the vault.

## Analysis

Analysed on 24 Nov 2026 by Claude Code (Claude Opus 5.5), from a nag Paul reported on a client
site: a *"Did You Know? There's a faster way to manage orders…"* banner with a *"Check out plugin →"*
button, on the WooCommerce Orders screen.

This is one of WebToffee's three "basic" import/export plugins (users, order, product). They ship
the same review-request class under three names, and a set of shared banner files. The banners
coordinate through **request globals and define-guards used as cross-plugin mutexes**, so that only
one sibling's copy renders. Those mutexes are the opt-out: claiming them before the vendor does
skips the banner in every sibling.

**Ten mechanism 1 rules (two globals, one filter, seven constants), covering thirteen surfaces.**
Six are in the notice area. Seven are outside it: the WooCommerce Analytics injections, three
edit-screen meta boxes and the Coupons-list pitch. The server-security notice and the
WooCommerce-version notice stay.

The off-notice-area constants are shared with other WebToffee plugins, so their uses were read in
`wt-smart-coupons-for-woocommerce` 2.3.4, `print-invoices-packing-slip-labels-for-woocommerce` 5.0.4 and
`wt-woocommerce-related-products` 1.8.0 as well, before any of them was defined. See *Shared constants*.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 8 single-line: review request ×3 (`show_banner_cta`, `show_wc_pages_banner`, the `current_screen`-registered `show_banner` / `show_banner_in_type_specific_pages`), `Wbte_Iew_History_Banner::render_banner`, `Wt_Iew_Bfcm_Twenty_Twenty_Six::show_banner` @ 999, `Wt_Non_Apache_Info::show_banner`, `Wt_Import_Export_For_Woo_User_Basic_Common_Helper::no_minimum_base_version`. Multiline pass: none. `admin_print_footer_scripts`: banner scripts only, plus `wt_users_customers_imex_plugin_screen_update_js` on the Plugins screen |
| Vendor opt-out filters | **`wt_bfcm_banner_screens`**, the BFCM banner's entire screen list. Nothing else |
| Vendor opt-out constants | **`WT_IEW_HISTORY_BANNER_LOADED`**, `WT_IEW_ANALYTICS_PRODUCTS_BANNER_LOADED`, `WT_IEW_ANALYTICS_ORDERS_BANNER_LOADED`, `WBTE_SMART_COUPONS_ANALYTICS_BANNER`, `WT_PRODUCT_IMPORT_EXPORT_DISPLAY_BANNER`, `WT_PDF_INVOICE_PLUGIN_DISPLAY_BANNER`, `WT_SMART_COUPON_DISPLAY_BANNER`, `WBTE_BFCM_SC_COUPONS_PAGE`, `WT_IEW_ADMIN_SETTINGS_PAGE_DISPLAYED`, `WT_IEW_SCHEDULE_JOB_BANNER_DISPLAYED`, `WT_IEW_HELP_WIDGET_DISPLAYED`. All first-loader mutexes |
| Vendor opt-out globals (found reading the callbacks) | **`$wt_iew_review_banner_shown`**, **`$wt_iew_wc_pages_banner_shown`** |
| Dashboard widgets | None |
| Outbound calls from widgets | None. `wp_remote_post` to `feedback.webtoffee.com` from the deactivation survey, and from the request-feature form on its own screen, both user-initiated |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| "Did You Know?" cross-sell, WooCommerce Orders / Products / Users | `admin_notices` → `User_import_export_Review_Request::show_wc_pages_banner` | **suppress** | The reported nag. Sells the Order / Product / User Import Export plugins on WooCommerce's own list screens (`woocommerce_page_wc-orders`, `edit-product`, `users`). Its "Maybe later" button only sets a 30-day cookie |
| "Did You Know?" cross-sell, WooCommerce Reports | `admin_notices` → `::show_banner_cta` | **suppress** | Same pitch, per Reports tab (`orders`, `customers`, `stock`, `subscriptions`) |
| Review request, common screens | `current_screen` → `admin_notices` → `::show_banner` | **suppress** | *"…please leave us a quick 5-star review"*. Renders on `dashboard`, `plugins`, `woocommerce_page_wc-admin` and the plugin's own screens |
| Review request, type-specific screens | `current_screen` → `admin_notices` @ 5 → `::show_banner_in_type_specific_pages` | **suppress** | Same banner, on Orders, Coupons, Subscriptions, Products, Users and Reports |
| Black Friday 2026 banner | `admin_notices` @ 999 and `wt_iew_bfcm_inline_render` → `Wt_Iew_Bfcm_Twenty_Twenty_Six::show_banner` | **suppress** | *"Your Last Chance to Avail 30% OFF on WebToffee Plugins"*. Seasonal sale, 11 Nov to 4 Dec 2026 (IST), on the plugin's own screens |
| Import Export Suite cross-sell | `admin_notices` → `Wbte_Iew_History_Banner::render_banner` | **suppress** | *"Upgrade to Import Export Suite…"*, on the History screen. Since 2.7.4 |
| Import Export Suite / Premium upsell, Analytics → Orders | `admin_footer` → `Wbte_Iew_Analytics_Orders_Banner::maybe_inject_banner` | **suppress** | *"Your store's grown past basic imports."* or *"Go Beyond Basic Order Imports Exports"*, rotated 30 days apart. JS-injected because the Analytics screens are a React app |
| Import Export Suite / Premium upsell, Analytics → Products | `admin_footer` → `Wbte_Iew_Analytics_Products_Banner::maybe_inject_banner` | **suppress** | *"Still Managing Data in Pieces?"* or *"Go beyond basic product imports/exports."* |
| Smart Coupons cross-sell, Analytics → Revenue | `admin_init` → `Wbte_Smart_Coupons_Analytics_Banner::get_instance` → `admin_footer` → `::sc_analytics_inject_script` | **suppress** | *"Did you know?"* Smart Coupons pitch. Printed on every `wc-admin` screen; its JS shows it on Revenue |
| PDF Invoices meta box, order edit | `add_meta_boxes` → `Wt_Invoice_Cta_Banner::add_meta_box`, ID `wbte_pdf_invoice_pro` | **suppress** | Side box selling the PDF Invoices add-on |
| Smart Coupons meta box, coupon edit | `add_meta_boxes` → `Wt_Smart_Coupon_Cta_Banner::add_meta_box`, ID `wbte_coupon_import_export_pro` | **suppress** | Side box selling Smart Coupons Pro |
| Product Import Export meta box, product edit | `add_meta_boxes` → `Wt_P_IEW_Cta_Banner::add_meta_box`, ID `wbte_product_import_export_pro` | **suppress** | Side box selling Product Import Export. Only once the store has 50 products |
| Smart Coupons "Did you know?", Coupons list | `admin_head-edit.php` → `Wt_Iew_Bfcm_Twenty_Twenty_Six::show_smart_coupon_BFCM_sbanner` | **suppress** | *"WebToffee Smart Coupons lets you create advanced BOGO offers…"*, inserted beside the page-title button |
| Server-security notice | `admin_notices` → `Wt_Non_Apache_Info::show_banner` | **keep** | See below |
| Minimum WooCommerce version | `admin_notices` → `Wt_Import_Export_For_Woo_User_Basic_Common_Helper::no_minimum_base_version` | **keep** | Version warning. Never suppressed |
| Plugins-screen update script | `admin_print_footer_scripts` → `wt_users_customers_imex_plugin_screen_update_js` | keep | Update-related UI on the Plugins screen, not a promotion |

## Deliberately left alone

### The server-security notice is operational

`Wt_Non_Apache_Info` shows on the plugin's own screens when the server is neither Apache nor
LiteSpeed. It says: *"The User Import Export plugin uploads the imported file into
wp-content/webtoffee_import folder. Please ensure that public access restrictions are set in your
server for this folder."* On nginx it adds the `location` block to deny it. Uploaded customer CSVs
in a public folder are a data-exposure problem, so the notice is true and actionable. Confirmed still rendering after the
rule (see Verification).

### No-op branches of the review gate

`check_condition()`, `handle_dissmissed()` and the rotation helpers write options
(`wt_iew_basic_dismiss_count` and the like) on some paths. With the global already true,
`check_condition()` returns false on its first line, so none of those writes run. Nothing to leave
alone, but it matters that the rule does not write vendor state. Checked on the bench.

### Inline upsell lines on the plugin's own screens

The *"💡 Did You Know?"* lines inside the import and export step views
(`_import_post_type_page.php`, `_export_advanced_page.php` and the rest) are on the vendor's own
screens, inside its own UI. Out of scope by construction.

### Smart Coupons' own coupon-activity banners

`WBTE_BFCM_SC_COUPONS_PAGE` is also read by Smart Coupons 2.3.4, in
`Wbte_Cta_Banner::sc_pro_coupons_page_banner()`, for its own *"Did you know"* Pro pitch. That is the
**last** entry in `coupons_page_banners()`' chain (`no_recent_coupon_banner`, `order_milestone_banner`,
`loyality_discount_banner`, `no_bogo_created_banner`, then the Pro pitch). So defining the constant
stops the Pro pitch and nothing else. The four earlier banners comment on the store's own coupon
activity. Smart Coupons has not been analysed, so they are not ruled on here.

## Shared constants

Each constant is a first-loader mutex: the vendor checks it is undefined, renders, then defines it.
Every `define()` of all seven, in every WebToffee plugin read, sits behind a `defined()` check
(defines = checks, counted per constant), so defining one first never triggers a redefinition
warning. Each was read in every plugin that uses it:

| Constant | Read in | Gates |
|---|---|---|
| `WT_IEW_ANALYTICS_PRODUCTS_BANNER_LOADED`, `WT_IEW_ANALYTICS_ORDERS_BANNER_LOADED` | Users, Order, Product basic | `require_once` of the Analytics banner class in `Wbte_Cross_Promotion_Banners` |
| `WBTE_SMART_COUPONS_ANALYTICS_BANNER` | Users, Order, Product basic | `sc_analytics_should_display_banner()` |
| `WT_PDF_INVOICE_PLUGIN_DISPLAY_BANNER` | Users, Order, Product basic, Smart Coupons, PDF Invoices, Related Products | The `wt_pdf_invoice_pro` / `wbte_pdf_invoice_pro` meta box only |
| `WT_SMART_COUPON_DISPLAY_BANNER` | the same six | The Smart Coupons upgrade meta box only (`wbte-sc-upgrade-to-pro` in Smart Coupons itself) |
| `WT_PRODUCT_IMPORT_EXPORT_DISPLAY_BANNER` | the same six | The Product Import Export meta box only |
| `WBTE_BFCM_SC_COUPONS_PAGE` | Users, Order, Product basic, Smart Coupons | The Coupons-list Pro pitch (see above) |

Not covered: `WT_PRODUCT_IMPORT_EXPORT_DISPLAY_BANNER_DEFAULT_PAGE`, a separate banner in the Order
and Product basic plugins only. It is not in this plugin, so it was not ruled on.

## Mechanism

- tier: 1 (vendor mutexes: two request globals, one filter, seven constants)
- phase: file scope, in `register_vendor_optouts()`. The constant must be defined before the vendor's
  loader runs at plugin load. The globals are read later, at `current_screen` and `admin_notices`,
  so they also work if this file is loaded late
- vendor registers at:
  - `User_import_export_Review_Request` is constructed when the main plugin file loads
    (`include_once 'user_import_export_review_request.php'`, which ends `new User_import_export_Review_Request();`).
    `show_banner_cta` and `show_wc_pages_banner` go on `admin_notices` in the constructor. The review
    banner is wired from `register_review_banner_hooks_for_screen()` on `current_screen` @ 10
  - `Wt_Iew_Bfcm_Twenty_Twenty_Six` and `Wbte_Cross_Promotion_Banners` are required from
    `includes/class-wt-import-export-for-woo.php`
- instance reachable via: N/A. The review class instance is discarded, but no reader is needed

How each gate works, from 2.7.8 source:

- `$wt_iew_review_banner_shown === true` makes `check_condition()` return false (common review
  banner), makes `register_review_banner_hooks_for_screen()` skip the type-specific branch, and is
  checked first in `show_banner_in_type_specific_pages()`, `show_banner_cta()` and
  `show_wc_pages_banner()`
- `$wt_iew_wc_pages_banner_shown` is checked first in `show_wc_pages_banner()`. It is redundant on
  2.7.3+, but it is that banner's own gate, and it is the one in 2.7.0 to 2.7.2
- In all three siblings, both globals are read only in their review-request class and assigned only
  `true`. Nothing unsets them
- `wt_bfcm_banner_screens`: `is_show_banner()` does `in_array( $screen_id, (array) apply_filters( 'wt_bfcm_banner_screens', array() ) )`,
  and the vendor adds its screens at priority 10. Present in every vault version from 2.6.7. The PDF
  invoice plugin's BFCM banner uses `wtpdf_bfcm_banner_screens` and is not covered
- `WT_IEW_HISTORY_BANNER_LOADED`: `Wbte_Cross_Promotion_Banners::__construct()` requires
  `class-wbte-iew-history-banner.php` only `if ( ! defined( … ) )`, and defines it itself, so
  defining it first is safe from a redefinition warning

Not used:

- **The `$wp_filter` reader** on `User_import_export_Review_Request`. It would work, but it needs
  four calls across two phases. It would also log a drift line on every screen where the review gate
  is closed, and each sibling would need its own class name. The mutex covers all three siblings with
  no reader
- **`pre_option_{prefix}_review_request`**. The common banner reads all three siblings' states
  (`wt_u_iew_basic_`, `wt_o_iew_basic_`, `wt_p_iew_basic_`). A closed state (3/4) leads into a
  60-day rotation check rather than ending the prompt, so this would need three answers and a bespoke
  method
- **`pre_option_wt_iew_hide_did_you_know_wc_*_banner_2026`**. Year-suffixed, so it would break at the
  next campaign. It also does not cover the Reports banner

**Limit:** on 2.6.x to 2.7.2, `show_banner_cta()` (Reports) does not check the review global, so the
Reports cross-sell survives on those versions. It is gated from 2.7.3.

## Drift check

- `user_import_export_review_request.php`: grep `wt_iew_review_banner_shown` and
  `wt_iew_wc_pages_banner_shown`. Each banner method should still check one of them before
  printing. If a new banner method appears without that check, it is uncovered. A renamed global
  means every surface comes back at once
- `admin/banner/class-wt-bfcm-*.php`: each year's campaign is a new file (`twenty-twenty-six`). Check
  that the new one still reads `wt_bfcm_banner_screens`
- `admin/cross-promotion-banners/class-wbte-cross-promotion-banners.php`: the history banner should still
  be behind `WT_IEW_HISTORY_BANNER_LOADED`
- Re-grep the order and product siblings for the same identifiers. They are the same code but ship on
  their own schedules
- For the seven shared constants, recount `define(` against `defined(` per constant across every
  WebToffee plugin in the vault. An unguarded `define()` would turn this rule into a PHP warning on
  every admin request, and a constant read for anything other than a banner would make it unsafe

## Verification

Harness on `bench1.local`, 24 Nov 2026: WooCommerce 11.3.1 and this plugin 2.7.8 from the vault,
`wp --exec='define( "WP_ADMIN", true ); …' eval-file`. quick-2fa is active on bench1, so this is
**harness, not an HTTP capture**. The harness sets an administrator, fires `admin_init`, calls
`set_current_screen()` (which fires the vendor's own `current_screen` registration), then captures
`admin_notices` and `all_admin_notices`. 1.51.2 (the bundled copy) was the "before", and 1.52.0 copied
over it was the "after". The bundled file was restored byte-for-byte afterwards (sha256 checked).

Gates opened: `wt_u_iew_basic_start_date` backdated 10 days, for the type-specific review banner.
One published product, to stop WooCommerce's empty-store redirect on `edit-product`. The BFCM
window was genuinely open on the day.

Structural probes, before → after:

| Surface | Screen | Probe | Before | After |
|---|---|---|---|---|
| "Did You Know?" Orders | `woocommerce_page_wc-orders` | `id="wt-iew-cta-banner"` | 1 | 0 |
| "Did You Know?" Products | `edit-product` | `id="wt-iew-cta-banner"` | 1 | 0 |
| Review request (type-specific) | `users` | `wbtf-review-banner__box` | 1 | 0 |
| "Did You Know?" Reports | `woocommerce_page_wc-reports`, tab `orders` | `id="cta-banner"` | 1 | 0 |
| BFCM 2026 | `toplevel_page_wt_import_export_for_woo_basic_export` | `wt-iew-bfcm-banner-2026` | 1 | 0 |
| Suite cross-sell | `…_page_wt_import_export_for_woo_basic_history` | `id="wt-iew-history-cta-banner"` | 1 | 0 |
| **Server-security notice (negative check)** | export and history screens | `_show_server_info notice-warning` | 1 | **1** |

Per rule:

- `$wt_iew_review_banner_shown` / `$wt_iew_wc_pages_banner_shown`: **confirmed (harness)** for the
  Orders, Products and Reports cross-sells and the type-specific review banner. The common review banner
  (dashboard, Plugins) is **source-verified only**: its gate needs `wt_iew_action_history` job rows,
  and the bench had none. It is behind the same first-line check in `check_condition()`
- `wt_bfcm_banner_screens`: **confirmed (harness)**
- `WT_IEW_HISTORY_BANNER_LOADED`: **confirmed (harness)**
- **Off-notice-area surfaces, second run the same day**, same bench and method. Second harness:
  `admin_footer` with `$_GET['path']` set, `add_meta_boxes` then a `$wp_meta_boxes[ $screen ]['side']['low']`
  lookup, and `admin_head-edit.php`. 50 generated products, to open the Product Import Export box's
  gate. The footer markup is JSON-encoded into a script, so it is probed by bare ID, not `id="…"`
  (the quoted form reads 0 whether the banner is there or not). Before → after: Analytics Orders
  `wt-iew-analytics-orders-cta-banner` 2 → 0, Analytics Products `wt-iew-analytics-products-cta-banner`
  2 → 0, Revenue `wbte_sc_analytics_banner` 2 → 0, meta boxes `wbte_pdf_invoice_pro`,
  `wbte_coupon_import_export_pro` and `wbte_product_import_export_pro` 1 → 0 each, Coupons list
  `data-wbte-sc-promotion-banner-id="sc_cpns_page"` 1 → 0. All seven constants **confirmed (harness)**.
  No constant-redefinition warnings across the runs. The first-round probes were re-run on the final
  build: all 0, server-security notice still 1
- Nothing written: after the run there was no `wt_*_iew_basic_review_request`, no
  `wt_iew_hide_did_you_know_*` and no `wt_iew_bfcm_twenty_twenty_six_banner_state` option

## Additions to `headwall-nag-cleanup.php`: ten mechanism 1 rules, in `register_vendor_optouts()`

```php
$GLOBALS['wt_iew_review_banner_shown']   = true;
$GLOBALS['wt_iew_wc_pages_banner_shown'] = true;

add_filter( 'wt_bfcm_banner_screens', '__return_empty_array', self::LATE_PRIORITY );

defined( 'WT_IEW_HISTORY_BANNER_LOADED' ) || define( 'WT_IEW_HISTORY_BANNER_LOADED', true );

defined( 'WT_IEW_ANALYTICS_PRODUCTS_BANNER_LOADED' ) || define( 'WT_IEW_ANALYTICS_PRODUCTS_BANNER_LOADED', true );
defined( 'WT_IEW_ANALYTICS_ORDERS_BANNER_LOADED' ) || define( 'WT_IEW_ANALYTICS_ORDERS_BANNER_LOADED', true );
defined( 'WBTE_SMART_COUPONS_ANALYTICS_BANNER' ) || define( 'WBTE_SMART_COUPONS_ANALYTICS_BANNER', true );
defined( 'WT_PDF_INVOICE_PLUGIN_DISPLAY_BANNER' ) || define( 'WT_PDF_INVOICE_PLUGIN_DISPLAY_BANNER', true );
defined( 'WT_SMART_COUPON_DISPLAY_BANNER' ) || define( 'WT_SMART_COUPON_DISPLAY_BANNER', true );
defined( 'WT_PRODUCT_IMPORT_EXPORT_DISPLAY_BANNER' ) || define( 'WT_PRODUCT_IMPORT_EXPORT_DISPLAY_BANNER', true );
defined( 'WBTE_BFCM_SC_COUPONS_PAGE' ) || define( 'WBTE_BFCM_SC_COUPONS_PAGE', true );
```
