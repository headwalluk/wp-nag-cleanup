# Challan – PDF Invoice & Packing Slip for WooCommerce

- slug: `webappick-pdf-invoice-for-woocommerce`
- version analysed: `3.7.90`
- source: **not in the vault** (19 Oct 2026). Read from a live install on a client site,
  copied into `work/webappick-pdf-invoice-for-woocommerce-3.7.90/`
- licensing: freemium. A separate premium `webappick-pdf-invoice-for-woocommerce-pro`
  exists; the free plugin deactivates it on activation
- Freemius bundled: no. It bundles WebAppick's own service library instead (below)
- vendor: WebAppick
- requires: WooCommerce (`Requires Plugins: woocommerce`)

## Analysis

Analysed on 19 Oct 2026 by Claude Code (Claude Opus 5).

Paul reported admin nags from a client site and suspected a shared WebAppick framework.
There is one: **`libs/WebAppick/AppServices/`** (`Client`, `Insights`, `Promotions`), the
"WebAppick Service Library". WebAppick Product Feed 6.5.84 bundles the same library. Each
product renames its namespace: `Challan\AppServices` here, `CTXFeed\AppServices` in Product
Feed. So a class-matched rule covers one product, not the whole range. The dashboard widget
ID, on the other hand, really is shared (below).

What reaches the admin:

- **Usage-tracking opt-in** (`Insights::admin_notice`). *"Want to help make Challan Free
  even more awesome? Allow Challan Free to collect non-sensitive diagnostic data…"* with
  *Allow* / *No thanks*. Every admin screen, `manage_options` only, until answered. It is
  hidden from loopback clients (`__is_local_server()`: `REMOTE_ADDR` 127.0.0.1 or ::1,
  filterable through `WebAppick_is_local`). **That matters on the bench:** a curl to a
  `localhost` vhost never sees it
- **Translation-volunteer request** (`Challan_Notifications::woo_invoice_translation_request`).
  *"We are looking for people to translate this plugin…"* with *Translate Here*. Every
  admin screen. Dismissing it re-arms it 30 days later (`woo_invoice_translation_notice_next_show_time`)
- **ZATCA Pro banner** (`…::woo_invoice_free_saudi_zatca_notice`). A banner image linking to
  the Pro product page (`utm_medium=free_vs_pro`). Registered only when `global $locale` is
  `ar` or `ary` **at plugin load**. Core does not set that global until something calls
  `get_locale()`, which normally happens after plugins load. So the banner only appears when
  an earlier-loading plugin has already called it. A site set to Arabic without that does
  not see it (bench-confirmed, below)
- **GST Pro banner** (`…::woo_invoice_free_gst_notice`). The same kind of banner, linking to
  the same Pro page. Registered when `woocommerce_default_country` is `IN:*` or
  `get_locale()` is `hi_IN`. Dismiss sets `woo_invoice_free_gst_notice = shown_gst_banner`
- **"Latest News from WebAppick Blog" dashboard widget**
  (`aaaa_webappick_latest_news_dashboard_widget`, `side`, `high`). Five webappick.com posts
  and a "Get Your Challan Pro" banner. The render callback calls
  `wp_remote_get( 'https://webappick.com/wp-json/wp/v2/posts?per_page=5&…' )` on **every
  dashboard load, uncached**

`includes/notices/admin-notice-for-review.php` ends `new Challan_Notifications();` and keeps
nothing. It runs at plugin load, from `includes/notices.php`. `Woo_Invoice_WebAppickAPI` is
built from an anonymous `init` closure in `woo_invoice_run()`. It holds `Insights` in a
**protected** property, and `Insights::init_common()` adds `admin_notice` then.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 10 live. `Challan_Notifications`: translation, ZATCA, GST. Two more (`woo_invoice_free_promotion_notice_hw2025`, `woo_invoice_review_notice`) are commented out. `Insights::admin_notice`. `Promotions::__show_promos` (dead, below). Named functions: `challan_admin_notice_woocommerce_is_not_installed__error`, `…_wp_content_dir_is_not_writeable__error`, `…_invoice_dir_is_not_writeable__error`, `…_downloading_font_background_process_in_progress__info`, `challan_font_dir_notice`. The multiline `add_action(` pass found nothing more. `admin_print_footer_scripts`: `Promotions::__get_promo_scripts` and five anonymous dismiss-script closures, each added from inside a notice callback |
| Vendor opt-out filters | `challan_promotions_source_url` only. No filter gates the notices or the widget. `WebAppick_is_local` is a loopback test, not an opt-out |
| Vendor opt-out constants | None |
| Dashboard widgets | 1, `aaaa_webappick_latest_news_dashboard_widget`. Registered with `add_meta_box()`, **not** `wp_add_dashboard_widget()`, so the checklist grep for the latter misses it. Found through pass (e), in `includes/widget.php` |
| Outbound calls from widgets | `https://webappick.com/wp-json/wp/v2/posts?per_page=5&_fields=parent,title,link,id,modified,excerpt`, on every render, uncached. Elsewhere: Insights' weekly tracking send (opt-in only) and `icanhazip.com`; `Promotions` (dead); the docs page's webappick.com fetch (vendor screen) |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Usage-tracking opt-in | `admin_notices` → `Challan\AppServices\Insights::admin_notice` | suppress | Usage-tracking opt-in prompt |
| Translation-volunteer request | `admin_notices` → `Challan_Notifications::woo_invoice_translation_request` | suppress | Solicitation. Nothing about the site |
| ZATCA Pro banner | `admin_notices` → `Challan_Notifications::woo_invoice_free_saudi_zatca_notice` | suppress | Image ad for Pro |
| GST Pro banner | `admin_notices` → `Challan_Notifications::woo_invoice_free_gst_notice` | suppress | Image ad for Pro |
| WebAppick blog widget | `aaaa_webappick_latest_news_dashboard_widget` | suppress | Vendor blog and Pro banner, uncached outbound fetch. No site data |
| Remote promotions feed | `admin_notices` → `Promotions::__show_promos` | no rule needed | Dead in 3.7.90 (below) |
| WooCommerce not installed / not active | `challan_admin_notice_woocommerce_is_not_installed__error` | keep | Missing dependency |
| `wp-content` / invoice dir / uploads not writable | `…_wp_content_dir_is_not_writeable__error`, `…_invoice_dir_is_not_writeable__error`, `challan_font_dir_notice` | keep | Real filesystem fault that stops invoices being written |
| Font download in progress | `challan_admin_notice_downloading_font_background_process_in_progress__info` | keep | Operational status |

## Deliberately left alone

- **The five operational notices.** All are named functions in the global namespace. The
  reader matches object instances only (`is_instance_callback()` rejects anything that is
  not an `[ object, method ]` pair), so it cannot touch them
- **`Promotions`, no rule.** 3.7.90 sets its source from
  `apply_filters( 'challan_promotions_source_url', '' )`, and `init()` returns early on an
  empty source. The vendor's comment says the old Bitbucket feed 404s. Nothing is fetched or
  shown. If a later release sets a default source again, revisit: the content would be
  vendor-controlled remote HTML (`wp_kses_post`), so it could be anything, including
  something operational. Do not pre-emptively return `''` from the filter: it already is
  `''`, and that would only hide a change worth noticing
- **Insights' deactivation-reason modal** (`admin_footer` → `deactivate_scripts`, plugins
  screen only). It is a feedback form on deactivation, not the notice area. Same call as for
  other vendors' deactivation surveys
- **Plugin row links, the Free vs Pro page, the docs page and settings tabs.** Vendor's own
  screens, out of scope by construction
- **`pre_option_` answers to the vendor's dismissal options, not used.** Each notice has
  one: `{slug}_tracking_notice = hide`, `woo_invoice_free_saudi_zatca_notice =
  showen_zatca_banner` (sic), `woo_invoice_free_gst_notice = shown_gst_banner`, and
  `woo_invoice_translation_notice_next_show_time` (a timestamp compared with `time()`).
  Answering them would need three answer methods of different types. ZATCA's misspelled
  sentinel is the sort of thing a vendor fixes silently. And the Insights option is keyed
  on `Client::getSlug()`, which is the plugin's **folder name**, so it breaks if the plugin
  is installed under another folder. The class-and-method match has none of those
  failure modes and writes nothing
- **`Woo_Invoice_WebAppickAPI::get_instance()`, not used for Insights.** The singleton is
  reachable, but `$insights` is `protected`. The public `$promotion` is the `Promotions`
  object, not `Insights`. `Insights::hide_notice()` exists but only works before `init()`,
  which runs in the vendor's own constructor
- **Product Feed's Insights notice** (`CTXFeed\AppServices\Insights`). Not claimed. Product
  Feed has not been analysed. Its copy of the library differs (line numbers, an extra
  `delete_option` at uninstall), so read it rather than adding the class by analogy

## Mechanism

- tier: 2 (targeted unhook) through `remove_discarded_instance_callback()`, the sanctioned
  `$wp_filter` reader, for the four notices, in `unhook_challan_promos()`. 3 (dashboard
  widget) for `aaaa_webappick_latest_news_dashboard_widget`: an entry in
  `PROMOTIONAL_DASHBOARD_WIDGETS`, context `side`
- phase: `admin_init` @ `self::LATE_PRIORITY` (999), from `unhook_vendor_notices()`. Widget
  on `wp_dashboard_setup` @ 999. The vendor adds it at priority **1**
- vendor registers at: `Challan_Notifications::__construct()` at plugin load, priority 10.
  `Insights::init_common()` during `init` @ 10 (`woo_invoice_run()`'s anonymous closure).
  Both are before `admin_init`
- instance reachable via: **nothing** for `Challan_Notifications` (`new …;` discarded).
  `Insights` sits in a protected property of `Woo_Invoice_WebAppickAPI::get_instance()`

**Shared widget ID.** WebAppick Product Feed 6.5.84 registers the same
`aaaa_webappick_latest_news_dashboard_widget`, `side`, `high`. It does so in both
`V5/Common/Widget.php` and `includes/widget.php`, and its render is the same uncached
webappick.com blog fetch with nothing site-specific. So the one entry removes it there too.
That is a deliberate side effect, read from source and not bench-tested. The entry's
comment records it.

The ZATCA and GST rules log "not registered" on every admin request of a site outside
those locales, when debug is on. That is expected, not drift: the vendor only registers
them for those locales (Simple Custom Post Order has the same caveat).

## Drift check

```bash
PLUGIN_PATH=work/webappick-pdf-invoice-for-woocommerce-3.7.90
command grep -n "new Challan_Notifications\|add_action( 'admin_notices'" "${PLUGIN_PATH}/includes/notices/admin-notice-for-review.php"
command grep -n "^namespace\|function admin_notice\|add_action( 'admin_notices'" "${PLUGIN_PATH}/libs/WebAppick/AppServices/Insights.php"
command grep -n "challan_promotions_source_url" "${PLUGIN_PATH}/includes/class-woo-invoice-webappick-api.php"
command grep -rn "aaaa_webappick_latest_news_dashboard_widget" "${PLUGIN_PATH}/includes/widget.php"
```

- Namespace changes from `Challan\AppServices`: the Insights line finds nothing and logs
  `Challan\AppServices\Insights::admin_notice not registered`. Only when the class exists,
  so absence of the class is silent. A rename to a new namespace is therefore **not**
  logged. Re-run the grep above on every new release
- `challan_promotions_source_url` gets a non-empty default: re-read `Promotions` (see above)
- The two commented-out notices (`woo_invoice_review_notice`, the `hw2025` promotion) come
  back: they are promotional, add them to `unhook_challan_promos()`
- Once the slug lands in the vault, re-point `source:` above at the zip

## Verification

### All four notices and the widget — **Confirmed** (bench, HTTP)

Bench: `bench1.local` (resolves to the LAN address, so `REMOTE_ADDR` is not loopback and
Insights renders), WooCommerce 11.2.1 from the vault, Challan 3.7.90 copied from the live
site, 19 Oct 2026. quick-2fa inactive. Authenticated HTTP with a minted admin cookie, with
the activation redirect absorbed first and the screen asserted. Before = bench1's
installed 1.40.0. After = working copy 1.43.0, with `HEADWALL_NAG_CLEANUP_DEBUG` from a
temporary mu-plugin.

Gates opened: `woocommerce_default_country = IN:MH` (GST). `WPLANG = ar`, written to the
database directly, because `sanitize_option()` rejects a locale with no installed language
pack. Even then ZATCA did **not** register: a `WP_ADMIN` harness listed only translation
and GST on `admin_notices`, because `global $locale` is unset at Challan's load. Adding a
one-line bench mu-plugin calling `get_locale()` (standing in for any earlier plugin that
loads a textdomain at file scope) registered it.

| Check | Before | After |
|---|---|---|
| Dashboard asserted `id="dashboard-widgets"` / Plugins asserted `id="the-list"` | 1 / 1 | 1 / 1 |
| Translation `data-which="translate"`, dashboard / plugins | **1 / 1** | **0 / 0** |
| ZATCA `data-which="zatca_close"`, dashboard / plugins | **1 / 1** | **0 / 0** |
| GST `data-which="gst_close"`, dashboard / plugins | **1 / 1** | **0 / 0** |
| Insights `class="webappick-pdf-invoice-for-woocommerce-insights-data-we-collect"`, dashboard / plugins | **1 / 1** | **0 / 0** |
| Dismiss scripts (`woo_invoice_hide_notice`), dashboard / plugins | 3 / 3 | 0 / 0 |
| Widget `id="aaaa_webappick_latest_news_dashboard_widget"` | **1** | **0** |
| Debug log | — | `Removed Challan_Notifications::woo_invoice_translation_request from admin_notices priority 10.`, the same for `…saudi_zatca_notice` and `…gst_notice`, `Removed Challan\AppServices\Insights::admin_notice …`, and `Removed dashboard widget "aaaa_webappick_latest_news_dashboard_widget" …` |

### Negative checks

| Check | Result |
|---|---|
| Site Health widget `id="dashboard_site_health"` | 1 before, **1 after** |
| Remaining `notice` matches on Plugins after | 2, both core JS templates (`{{ data.className }} notice`) |
| Vendor state after: `{slug}_tracking_notice`, `{slug}_allow_tracking`, the ZATCA, GST and translation options, `woo_invoice_translation_notice_dismissed` user meta | All still absent. Nothing written |
| Operational notices (WooCommerce missing, directories not writable) | **Source-verified only.** Not triggerable on the bench without breaking WooCommerce or the uploads directory. They are named functions, which the reader cannot match |

Bench afterwards: WooCommerce removed with its own uninstaller, Challan uninstalled, and
residue options deleted against a snapshot. Tables, cron and roles match the snapshot.
Known leftovers: `uploads/wc-logs/` (owned by the web user), and three pre-existing
`woocommerce_*` options that WooCommerce's uninstaller deleted. bench1 now runs 1.43.0.

## Additions to `headwall-nag-cleanup.php`: `unhook_challan_promos()` and one widget entry

```php
// unhook_vendor_notices()
$this->unhook_challan_promos();

public function unhook_challan_promos() : void {
	$this->remove_discarded_instance_callback( 'admin_notices', 'Challan_Notifications', 'woo_invoice_translation_request', 'webappick-pdf-invoice-for-woocommerce' );
	$this->remove_discarded_instance_callback( 'admin_notices', 'Challan_Notifications', 'woo_invoice_free_saudi_zatca_notice', 'webappick-pdf-invoice-for-woocommerce' );
	$this->remove_discarded_instance_callback( 'admin_notices', 'Challan_Notifications', 'woo_invoice_free_gst_notice', 'webappick-pdf-invoice-for-woocommerce' );
	$this->remove_discarded_instance_callback( 'admin_notices', 'Challan\\AppServices\\Insights', 'admin_notice', 'webappick-pdf-invoice-for-woocommerce' );
}

// PROMOTIONAL_DASHBOARD_WIDGETS
[
	// Same id in WebAppick Product Feed 6.5.84 (V5/Common/Widget.php, includes/widget.php).
	'widget_id' => 'aaaa_webappick_latest_news_dashboard_widget',
	'context'   => 'side',
	'vendor'    => 'Challan (webappick-pdf-invoice-for-woocommerce) 3.7.90',
	'reason'    => 'WebAppick blog and Challan Pro banner; uncached webappick.com fetch on render',
],
```
