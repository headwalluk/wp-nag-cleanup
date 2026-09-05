# CookieYes (Cookie Law Info)

- slug: `cookie-law-info`
- version analysed: `3.5.5`
- source: `/vault/backups/wordpress/plugins/cookie-law-info/cookie-law-info,3.5.5.zip`
- licensing: freemium (free on wordpress.org, CookieYes Web App sold as SaaS)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from nags Paul reported on live
client sites.

40 fleet sites. Unlike the large vendors audited earlier the same day — Yoast, ACF,
Autoptimize — CookieYes puts promotional output on **core WordPress screens**, not just
its own. The review request and a WebToffee cross-promotion banner both appear in the
global admin notice area, which is squarely in scope.

**Two rules added**, both mechanism 1. Three further promotional-looking surfaces are
left alone, and the reasons are recorded.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 6 |
| Vendor opt-out filters | **`cky_is_module_active_{module_id}`** — a per-module switch read in the base class constructor. Also `cli_show_cookie_bar_only_on_selected_pages`, `wt_cli_ckyes_account_widget`, `wt_cli_force_show_old_cookie_categories` (unrelated) |
| Vendor opt-out constants | **`CYA11Y_ACCESSYES_BANNER_DISPLAYED`** — a cross-plugin mutex for the WebToffee promo banner |
| Dashboard widgets | 1: `cookieyes_dashboard_widget`. **Mixed output — left alone** |
| Outbound calls from widgets | The widget calls the CookieYes API only when the site is connected |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Review request | `admin_notices` → `Review_Feedback::add_notice` | **suppress** | Links to `wordpress.org/support/plugin/cookie-law-info/reviews`. Renders on `plugins.php` — a core screen |
| AccessiYes cross-promotion | `admin_notices` → `Wbte_Accessibility_Banner::show_banner_notice` | **suppress** | *"Make your site inclusive with AccessiYes accessibility widget"* — cross-selling a different WebToffee product. Renders on the dashboard |
| Migration notice | `admin_notices` → `migration_notice` | keep | **Data migration prompt. Never suppressed** |
| Update banner | `admin_notices` 1 → `render_update_banner` | keep | Reports plugin state after an update |
| Legacy review request | `admin_notices` → legacy `show_banner` | keep (moot) | Superseded by the `review_feedback` module; the legacy class is not the live path |
| Connect banner | `admin_notices` → `Connect_Banner::show_banner` | keep — **ambiguous** | See below |
| `cookieyes_dashboard_widget` | `wp_dashboard_setup` | keep — **mixed** | See below |

## Deliberately left alone

### The connect banner is ambiguous, so it stays

`Connect_Banner::show_banner` renders on `plugins.php`:

> **Unlock advanced features for seamless compliance** — Connect to CookieYes Web App

It is a prompt to sign up for a paid SaaS, which reads as promotional. But CookieYes's
entire purpose is consent compliance, and connecting is what enables the cookie scanner
that keeps the banner's categories accurate. A site owner running a stale, unscanned
cookie list has a real compliance problem, and this notice is the plugin's only route to
telling them so.

That is close enough to operational that the ambiguity rule applies: **when a rule is
ambiguous, it does not go in.** It would be trivial to add later —
`cky_is_module_active_connect_banner` — if the judgement changes.

### The dashboard widget is mixed output

`cookieyes_dashboard_widget` renders differently depending on state. Connected, it shows
consent-rate trends — the site's own data, in the same category as Yoast's Posts
Overview. Not connected, it shows *"Get cookie consent insights in your Dashboard! …
Connect to CookieYes Web App"* — a sign-up CTA.

One switch (`cky_is_module_active_dashboard_widget`) governs both. Suppressing it would
remove genuine consent analytics from every connected site to hide a CTA on unconnected
ones. Mixed output, so no rule.

### `affiliate_banner` is listed but absent

`Admin::$modules` includes `affiliate_banner`, but no
`lite/admin/modules/affiliate_banner/` directory exists in 3.5.5. `load_modules()` guards
with `class_exists()`, so the module never loads. A rule would target something that does
not run — the EmbedPress mistake. Worth re-checking if the directory reappears.

### `uninstall_feedback` is out of scope

A deactivation survey, shown as a modal during plugin deactivation rather than in the
notice area. Not this project's territory.

## Mechanism

Two rules, both tier 1, both at file scope.

### Review request — `cky_is_module_active_review_feedback`

CookieYes loads every module through a base class whose constructor gates on a filter:

```php
public function __construct( $module_id ) {
    $this->module_id = $module_id;
    if ( true === $this->is_active() ) {
        $this->init();
    }
}

public function is_active() {
    return apply_filters( "cky_is_module_active_{$this->get_module_id()}", true );
}
```

`Review_Feedback::init()` is what calls `add_action( 'admin_notices', … )`, so returning
false means the hook is never registered at all — cleaner than unhooking. The module id
is `review_feedback`, from `Admin::$modules`.

Note the constructor tests `true === $this->is_active()`, so `__return_false` works and
any truthy-but-not-true value would not.

This also removes the module's `admin_footer_text` review link and its
`admin_print_footer_scripts` handler, which are part of the same nag.

### WebToffee cross-promotion — `CYA11Y_ACCESSYES_BANNER_DISPLAYED`

The banner ships as a shared WebToffee package. Its bootstrap documents the constant as
a first-loader mutex:

```php
// Already bootstrapped by another plugin in this request (e.g. WT Smart Coupon).
if ( defined( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED' ) || class_exists( 'Wbte_Accessibility_Banner' ) ) {
    return;
}
define( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED', true );
require_once __DIR__ . '/class-wbte-accessibility-banner.php';
```

An mu-plugin loads before every regular plugin, so defining the constant first means the
banner's own file is never even required — in CookieYes and in **any other WebToffee
plugin using the same package**, which the file header names as including WT Smart
Coupons.

Defined with `defined( … ) || define( … )` so it is never redefined.

The vendor also offers an option, `cya11y_hide_accessyes_cta_banner`. Not used: setting
it would be a database write, which this project does not do.

## Drift check

Re-check when a new version appears in the vault:

- `lite/includes/class-modules.php` — the `cky_is_module_active_*` filter in the
  constructor, and the `true ===` comparison
- `lite/admin/class-admin.php` — the `$modules` array. If `review_feedback` is renamed
  the rule silently no-ops; if `affiliate_banner` gains a real directory, audit it
- `lite/admin/cross-promotion-banners/class-wbte-cross-promotion-banners.php` — the
  `CYA11Y_ACCESSYES_BANNER_DISPLAYED` mutex
- `Connect_Banner` and `cookieyes_dashboard_widget` — if either stops being ambiguous or
  mixed, revisit

## Verification

Tested on `bench2.local` (WP 7.1) with CookieYes 3.5.5 active, over authenticated admin
requests, A/B with the rules enabled and disabled:

| Check | Rules off | Rules on |
|---|---|---|
| Review nag on `plugins.php` | **1** | **0** |
| AccessiYes banner on dashboard | **1** | **0** |
| Connect banner on `plugins.php` (left alone) | present | **still present** |
| CookieYes plugin row on `plugins.php` | present | present |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

The connect banner surviving is the important line: it shows the rules are targeted
rather than a blanket suppression of the vendor.

## Additions to `headwall-nag-cleanup.php`: 2 rules, both mechanism 1

```php
// CookieYes 3.5.5 review request module. docs/plugins/cookie-law-info.md
add_filter( 'cky_is_module_active_review_feedback', '__return_false' );

// WebToffee cross-promotion banner, shared across their range. The vendor
// uses this constant as a first-loader mutex, so defining it here skips the
// banner everywhere. CookieYes 3.5.5. docs/plugins/cookie-law-info.md
defined( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED' ) || define( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED', true );
```
