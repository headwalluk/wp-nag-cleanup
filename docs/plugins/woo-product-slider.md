# Product Slider for WooCommerce (ShapedPlugin)

- slug: `woo-product-slider`
- version analysed: `2.8.13`
- source: `/vault/backups/wordpress/plugins/woo-product-slider/woo-product-slider,2.8.13.zip`
- licensing: freemium (Woo Product Slider Pro is a separate premium plugin)
- Freemius bundled: no
- **Not to be confused with** `wpb-woocommerce-product-slider`, a different vendor with its
  own document and its own rule

## Analysis

Analysed on 11 Sep 2026 by Claude Code (Claude Opus 5).

ShapedPlugin's product slider carries **three** promotional surfaces and they are all
suppressed. Paul found the review nag on a client site; reading the plugin turned up a
seasonal offer banner that is the more intrusive of the two, because unlike the review nag
it is not screen-gated — it renders on **every** admin page for any user with
`manage_options` whenever one of its hard-coded date windows is open.

Everything operational is left alone: the WooCommerce-missing dependency notice, and the
two "install our other plugin" cross-sells, which are confined to the vendor's own screens.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 5 (a sixth is commented out in the vendor's source). 3 promotional, 1 dependency, 2 cross-sells on vendor screens |
| Vendor opt-out filters | **None.** No filter gates any notice in this plugin |
| Vendor opt-out constants | `SHAPEDPLIUGIN_OFFER_BANNER_LOADED` exists but is a **load mutex, not a switch** — see below |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Seasonal offer banner | `admin_notices` → `ShapedPlugin_Offer_Banner::render_offer_banner` | **suppress** | Full-width image advert linking to `wooproductslider.io/pricing`. Not screen-gated — every admin page |
| 5-star review request, `sp-wps-review-notice` | `admin_notices` → `Dashboard_Notice::display_admin_notice` | **suppress** | *"Enjoying Product Slider for Woocommerce? … Please take a moment to leave a review"* |
| Admin footer rating text | `admin_footer_text` 1 → `Dashboard_Notice::admin_footer` | **suppress** | *"Please rate us ★★★★★ WordPress.org"*, replacing core's footer text |
| WooCommerce not active | `admin_notices` → `WooProductSlider::error_admin_notice` | **keep** | Missing dependency, with an install link. Registered only when WooCommerce is inactive |
| Gallery Slider cross-sell | `admin_notices` → `WooProductSlider::woo_gallery_slider_admin_notice` | **keep** | Cross-sell, but gated to `sp_wps_shortcodes` screens — see below |
| Smart Swatches cross-sell | `admin_notices` → `WooProductSlider::smart_swatches_install_admin_notice` | **keep** | As above |
| Footer version string | `update_footer` 11 → `Dashboard_Notice::admin_footer_version` | **keep** | Prints `Woo Product Slider 2.8.13` on the vendor's own screens. A true fact about the site, no link, no upsell |
| Review-notice dismiss endpoint | `wp_ajax_sp-wps-never-show-review-notice` | **keep** | The vendor's own dismiss path. Removing it would strand anyone who still sees the notice |

## Deliberately left alone

### `SHAPEDPLIUGIN_OFFER_BANNER_LOADED` is a mutex, not a switch — and it bounds this rule

`main.php` wraps the banner's construction:

```php
if ( ! defined( 'SHAPEDPLIUGIN_OFFER_BANNER_LOADED' ) ) {
    define( 'SHAPEDPLIUGIN_OFFER_BANNER_LOADED', true );
    ShapedPlugin\WooProductSlider\Admin\Notices\ShapedPlugin_Offer_Banner::instance();
}
```

(The misspelling is the vendor's.) It reads like an opt-out constant and it is not one — it
is a first-loader mutex so that a site running several ShapedPlugin products shows the
banner once rather than once per plugin. Pre-defining it in `wp-config.php` would suppress
the banner, but that is a load-order side effect rather than a supported switch, and it is
the same abuse rejected for `cf_white_label_options` in `docs/plugins/cartflows.md`.

**It also bounds the rule, and this is the limitation to know about.** Each ShapedPlugin
product ships its own copy of the class under its own namespace
(`ShapedPlugin\WooProductSlider\...`, `ShapedPlugin\<Product>\...`). Whichever plugin loads
first wins the mutex and owns the live banner. This rule names the `WooProductSlider`
namespace, so **on a site where a sibling ShapedPlugin product loaded first, the banner
survives.** Covering the rest means one entry per sibling namespace, and none of the other
ShapedPlugin slugs has been analysed yet — so that is left until one turns up on the fleet.

### `instance()` exists and is deliberately not used

`ShapedPlugin_Offer_Banner` has a real singleton accessor, and `CLAUDE.md` says to prefer a
singleton over the `$wp_filter` reader. That is **not** done here, and the reason is the
mutex above.

`class_exists()` triggers the autoloader, which loads the class without constructing it. If
a sibling plugin won the mutex, this plugin's copy was **never constructed** — so calling
`instance()` would construct it then, and its constructor is what calls `add_action()`. The
rule would *add* the banner to a site that did not have it.

The reader has no such hazard: it only ever matches an object already on the hook. This is
the first case in the project where a singleton is available and the reader is still
correct, so it is written up rather than left as a surprise.

### The two cross-sells

`woo_gallery_slider_admin_notice` and `smart_swatches_install_admin_notice` promote other
ShapedPlugin products, which is squarely on the suppress list. Both are kept, because both
bail unless `get_current_screen()->post_type === 'sp_wps_shortcodes'` — the plugin's own
shortcode-manager screen. They never reach the dashboard, the plugins list, or any screen a
site owner passes through in normal work.

That is the same line drawn for CartFlows' NPS survey and WPForms' `promote_wpforms`:
vendor promotion confined to vendor screens is out of scope by construction. Consistency
matters more here than the marginal win, and both are dismissible.

### `admin_footer_version`

Replaces the WordPress version string in the admin footer with `Woo Product Slider 2.8.13`
on the vendor's own post type screens. Branded and mildly annoying, but it states a true
fact about the site, carries no link and sells nothing. Not a nag.

## Mechanism

- tier: 2 (targeted unhook, via the sanctioned `$wp_filter` reader), three callbacks
- phase: `admin_init` at `self::LATE_PRIORITY`
- vendor registers at: plugin-load time. `main.php` calls `sp_woo_product_slider()` at file
  scope, which constructs `ShapedPlugin_Offer_Banner` (behind the mutex) and
  `WooProductSlider::instance()`, whose constructor runs `new Admin()`, whose constructor
  runs `new Dashboard_Notice()`. Everything is on its hook well before `admin_init`
- instance reachable via: **no** for `Dashboard_Notice` — `Admin.php:33` is
  `new Dashboard_Notice();` with the return discarded. **Technically yes** for
  `ShapedPlugin_Offer_Banner`, but using it is unsafe; see above

`LATE_PRIORITY` is correct here rather than `EARLY_PRIORITY`: both targets sit on
`admin_notices`, which fires long after `admin_init`, so there is no race. The
`EARLY_PRIORITY` pass exists only for producers that run on `admin_init` itself.

## Drift check

- `src/Admin/Notices/ShapedPlugin_Offer_Banner.php` — `get_active_offers()`. The offers are
  a **hard-coded array of date windows**, so each release ships a new set. New windows do
  not break the rule (it removes the callback, not the offer), but they are what makes the
  banner visible, and they are the reason a bench check needs the windows widened
- `src/Admin/Notices/Dashboard_Notice.php` — the four `add_action`/`add_filter` lines in the
  constructor
- `src/Admin/Admin.php:33` — if `new Dashboard_Notice()` is ever assigned or registered,
  **drop the reader and name the instance**
- `src/Includes/WooProductSlider.php` — `init_actions()`. If a cross-sell is ever
  un-gated from `sp_wps_shortcodes`, it becomes a suppress candidate. Note
  `wqv_install_admin_notice` is currently commented out in the vendor's source and would be
  a fourth cross-sell if re-enabled
- **Other ShapedPlugin slugs.** `woo-product-bundle`,
  `woo-product-carousel-slider-and-grid-ultimate` and others are in the vault unanalysed.
  If any appears on the fleet, check whether it ships the same `ShapedPlugin_Offer_Banner`
  and extend the rule with its namespace

## Verification

Bench: `bench2.local`, WP 7.1, WooCommerce 11.1.0, Product Slider for WooCommerce 2.8.13,
over authenticated admin HTTP requests. Three warm requests between each deploy and capture,
because opcache served a stale mu-plugin during the WPForms work and produced a false
negative.

**Gates that had to be opened.** Neither nag shows on a fresh install, and one of them
cannot show at all in 2.8.13:

| Gate | Where | Bench action |
|---|---|---|
| 3 days since the notice was first scheduled | `sp_woo_product_slider_review_notice_dismiss` option (the first admin request creates it and returns) | backdated 30 days |
| An offer window must be open | `get_active_offers()`, **hard-coded** | see below |
| Offer not already dismissed | `shapedplugin_offer_banner_dismissed_black_friday_2025` | confirmed unset |

**The offer banner is currently dead code in the shipped release.** Both hard-coded windows
— Black Friday 2025 (18 Nov – 14 Dec 2025) and New Year 2026 (26 Dec 2025 – 14 Jan 2026) —
closed before this analysis. On today's date 2.8.13 renders no banner at all.

To prove the rule rather than source-verify it, the **bench copy** of
`ShapedPlugin_Offer_Banner.php` had its Black Friday window widened to 2026-01-01 →
2027-01-01, the capture taken, and the file then **restored byte-for-byte** from a copy
taken before the edit (`diff` clean, confirmed after the run). The vault was not touched.
Only the two date constants were changed; the hook registration and the callback under test
are the shipped code.

### All three callbacks — **Confirmed**

| Check | Before | After |
|---|---|---|
| `sp-wps-review-notice` in dashboard HTML | 3 | **0** |
| `shapedplugin-offer-banner` in dashboard HTML | 4 | **0** |
| `bfcm-offer-banner` (the advert image) | 1 | **0** |
| `Dashboard_Notice::display_admin_notice` on `admin_notices` | `true` | **`false`** |
| `ShapedPlugin_Offer_Banner::render_offer_banner` on `admin_notices` | `true` | **`false`** |
| `Dashboard_Notice::admin_footer` on `admin_footer_text` | `true` | **`false`** |

Debug log records all three removals by fully-qualified class name.

### Negative checks

| Check | Result |
|---|---|
| `WooProductSlider::woo_gallery_slider_admin_notice` still hooked | **yes** — deliberately kept |
| `WooProductSlider::smart_swatches_install_admin_notice` still hooked | **yes** — deliberately kept |
| `Dashboard_Notice::admin_footer_version` still on `update_footer` | **yes** — deliberately kept |
| Dashboard, `edit.php?post_type=sp_wps_shortcodes`, WC Status | all 200, screens asserted |
| Other vendors' rules still clean on the same bench | CartFlows, Astra theme and WPForms all still 0 |
| Front page | 200 |
| PHP fatals / parse errors in `error.log` | **0** |

`error_admin_notice` could not be exercised — it registers only when WooCommerce is
inactive, and WooCommerce is required for this plugin to be useful. It is a separate
callback on a separate object and no rule here names it.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2, three callbacks

```php
// In unhook_vendor_notices():
$this->unhook_shapedplugin_promos();

public function unhook_shapedplugin_promos() : void {
    $notice_class = 'ShapedPlugin\\WooProductSlider\\Admin\\Notices\\Dashboard_Notice';
    $banner_class = 'ShapedPlugin\\WooProductSlider\\Admin\\Notices\\ShapedPlugin_Offer_Banner';

    $this->remove_discarded_instance_callback( 'admin_notices', $notice_class, 'display_admin_notice', 'woo-product-slider' );
    $this->remove_discarded_instance_callback( 'admin_footer_text', $notice_class, 'admin_footer', 'woo-product-slider' );
    $this->remove_discarded_instance_callback( 'admin_notices', $banner_class, 'render_offer_banner', 'woo-product-slider' );
}
```
