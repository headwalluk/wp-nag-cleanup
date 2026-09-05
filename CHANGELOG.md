# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.19.0] — 2026-09-05

### Added

- **Converter for Media's review request, PRO upsell and Black Friday sale notices**
  removed — **82 fleet sites**, the widest reach of any single-plugin rule in the project

### The notices could not be told apart, so a different handle was used

Converter for Media has the cleanest notice architecture audited so far, and that is
exactly what made it hard. Six notices, one class each, all rendered through a single
`NoticeIntegrator::load_notice` — built as six anonymous temporaries, so there is no
singleton, no registry and no property holding them. Class-plus-method, all
`remove_discarded_instance_callback()` matches on, cannot separate a Black Friday coupon
from *"your subscription has expired"*.

The handle is elsewhere. Each integrator also registers its dismiss handler on
`wp_ajax_<notice option>`, and those six option names are distinct — so
`wp_ajax_webpc_notice_thanks` holds exactly one callback, and its object is the integrator
wrapping the review nag and nothing else. From there its own `load_notice` entry is named
directly.

The dismiss handlers themselves are left in place; removing them would break the dismiss
button on the notices that remain.

### Three notices deliberately kept

- **`TokenInactiveNotice`** — expired subscription or exhausted conversion quota. Squarely
  on the never-suppress list, and the reason this rule had to be precise rather than
  convenient. Verified still rendering, on the same request that dropped the review nag
- **`CloudflareNotice`** — cache-purge steps, and on the vendor's own settings screen
- **`WelcomeNotice`** — the one that shows on *every* admin screen until dismissed, and so
  the likeliest source of the original report. It stays: a fresh install converts nothing
  until Bulk Optimization runs, so *"click Start Bulk Optimization"* is a true and
  actionable statement about the site. Recorded in full in the audit doc, including the
  `mattplugins.com` logo it loads

No vendor opt-out exists — 33 `apply_filters` calls in `src/` and not one near a notice —
and there is no dashboard widget.

### Changed

- `remove_discarded_instance_callback()` split: the `$wp_filter` walk moved into a new
  private `find_instance_callback()`, which returns the callback and its priority. **Still
  exactly one place in the file reads `$wp_filter`** — the split exists because this rule
  needs the *instance* to reach a sibling callback, not just the entry to delete

## [1.18.0] — 2026-09-05

### Added

- **WPCode's review request, its admin-footer review link, the "Pro Tip" upsell and the
  library connect promo** removed — **19 fleet sites**, the widest reach of any
  single-plugin rule so far behind Elementor

### A new phase — `EARLY_PRIORITY`

First vendor whose promotional notices are **built from `admin_init` callbacks at the
default priority**. `WPCode_Review::review_request()` calls `$this->review()` directly,
and `WPCode_Features_Notices::maybe_show_notices()` adds its `admin_notices` hook — both
at `admin_init` 10.

`LATE_PRIORITY` (999) is **too late**: the producer has already run and queued its notice.
So a new `unhook_early_vendor_notices()` pass runs at `admin_init` **priority 1**, before
they fire.

`EARLY_PRIORITY` is only for targets on `admin_init` itself. Everything else stays late.
Recorded in `CLAUDE.md`.

### Producers removed, framework kept

`WPCode_Notice::display` renders whatever `WPCode_Notice::add()` has queued, at
`admin_notices` 999000, and its callers include ordinary operational feedback from the
snippet generator. Removing the renderer would be blanket suppression of a general
framework — the pattern rejected for Astra's `astra-notices`. Removing the **producers**
achieves the same result with no collateral, and was verified: the framework is still
hooked at p999000 with the rules active.

### Deliberately not done

- **`wpcode_safe_mode_notice`** reports that WPCode is in safe mode and snippets are not
  executing — exactly the "why is my site behaving oddly" notice this project exists to
  make visible
- **`wpcode_lite_notice`** is a Pro/Lite conflict notice
- **The WPConsent cross-promo and Lite top-bar notices** render from `wpcode_admin_page`
  hooks, inside WPCode's own screens

### Verified

Probe at `admin_init` 500 — after the early pass, before the late one:

| Check | Rules off | Rules on |
|---|---|---|
| `WPCode_Review::review_request` | HOOKED p10 | **gone** |
| `WPCode_Review::admin_footer` | HOOKED p1 | **gone** |
| `WPCode_Features_Notices::maybe_show_notices` | HOOKED p10 | **gone** |
| `wpcode_maybe_add_library_connect_notice` | HOOKED | **gone** |
| `WPCode_Notice::display` (must survive) | HOOKED p999000 | **HOOKED p999000** |

## [1.17.0] — 2026-09-05

### Added

- **Easy FancyBox's review request removed** (`easyFancyBox_Admin::show_review_request`),
  which also carries the plugin's email opt-in

### A trap worth recording — static callback removal is case-sensitive

The class is declared `class easyFancyBox_Admin`, with a **lowercase `e`**. PHP class
names are case-insensitive, so `class_exists( 'EasyFancyBox_Admin' )` returns true and a
mis-cased rule looks fine. It is not:
`_wp_filter_build_unique_id()` keys a static array callback by literal string
concatenation —

```php
} elseif ( is_string( $callback[0] ) ) {
    return $callback[0] . '::' . $callback[1];   // wp-includes/plugin.php
}
```

— so the wrong case produces a different key and `remove_action()` removes nothing,
silently. Demonstrated on the bench: with the callback registered, `has_action()` returned
`false` for the mis-cased form and a priority for the correct one.

`CLAUDE.md` gains the rule: **copy static class names from the source, case included.**

### Deliberately not done

**`easyFancyBox_Admin::admin_notice` is a version-compatibility warning**, despite the
generic name. It renders only when the Pro plugin is installed below `$compat_pro_min` —
a genuine "your two halves are incompatible" notice. Suppressing it would leave a site
with a broken lightbox and no explanation. Verified still hooked.

### Vendor scope

No shared framework. The other `easy-*` plugins on the fleet are unrelated vendors, and
three of them are Headwall's own.

### Verified

| Check | Rule off | Rule on |
|---|---|---|
| `easyFancyBox_Admin::show_review_request` (correct case) | **HOOKED** | **gone** |
| `EasyFancyBox_Admin::show_review_request` (wrong case) | gone | gone |
| `easyFancyBox_Admin::admin_notice` (must survive) | present | **present** |

## [1.16.0] — 2026-09-05

### Added

- **HT Mega's "HasThemes Stories" and Happy Addons' "HappyAddons News & Updates"
  dashboard widgets** removed, plus Happy Addons' **review nag** and its **Appsero
  tracking opt-in**

  Different vendors despite both being Elementor addon packs — HasThemes and
  HappyMonster — so no shared framework between them.

### Five vendors now force their widget to the top

Both widgets register and then reorder `$wp_meta_boxes['dashboard']['normal']['core']` to
put themselves first. That makes five: WP Desk, Elementor, Wpmet, HasThemes and
HappyMonster. Worth treating as a genre rather than a quirk.

### Appsero, reached through the plugin's own singleton

`Base::instance() -> appsero -> insights`, every step public and every step guarded with
`isset()` / `is_object()`. **Covers Happy Addons only**: Appsero is bundled by three fleet
plugins but each creates its own `Client`, and the SDK's only filters (`appsero_endpoint`,
`appsero_is_local`, `appsero_custom_deactivation_reasons`) do not gate the notice.

### Deliberately not done

- **HT Mega's "fast asset mode" notice is operational**, despite the class name
  `HTMega_Performance_Upgrade_Notice`. It offers to stop loading HT Mega's CSS/JS on pages
  that do not need them — a real performance setting. Verified still hooked
- **HT Mega's diagnostic-data opt-in is a closure** on `admin_notices`. A closure has
  neither class nor method, so the `$wp_filter` reader cannot identify it and
  `remove_action()` has nothing to name. Matching a closure's bound scope would be a far
  bigger escalation than the reader represents, so it was not attempted
- **HT Mega's `Dynamic_Notice` framework is unfed** — nothing calls `set_notice()`, so it
  renders nothing, like EmbedPress's promo framework
- **Happy Addons' `Classes\Notice` targets a campaign window of 18–30 March 2025**,
  hardcoded and long closed. A rule for it would target a hook that cannot fire. The doc
  records the one-line addition to make if a future version opens a new window
- **`Attention_Seeker::seek_attention`** loops over `get_attentions()`, which returns `[]`

### Verified

Structurally. A content-based A/B was attempted and **discarded as invalid** — activating
Happy Addons redirects to its own dashboard, so the captures were of different screens,
the same trap hit with Essential Blocks.

| Check | Rules off | Rules on |
|---|---|---|
| `hasthemes-dashboard-stories` in `$wp_meta_boxes` | PRESENT | **gone** |
| `happy_addons_news_update` in `$wp_meta_boxes` | PRESENT | **gone** |
| HT Mega fast-asset-mode notice | present | **present** |

The review nag and Appsero opt-in **could not be exercised**: `Review` registers only 10
days after install, and Appsero's `Insights` is created lazily. Both rules are guarded so
a missing target is a silent no-op, and both were confirmed not to fire spuriously.

## [1.15.0] — 2026-09-05

### Added

- **Essential Blocks' campaign notice bank removed** — its bundled
  `PriyoMukul\WPNotice` bank holds four notices and **all four are promotional**: a
  seasonal *"Summer Savings … up to $150 OFF"* upsell, an *"Essential Blocks PRO"* early
  bird, a review request and a usage-tracking opt-in

  `review` and `opt_in` are independently on the suppress list, so every notice in the
  bank qualifies even judged individually.

### No `$wp_filter` exception needed — worth recording why

The obvious read is that this needs the reader: `Admin::notices()` does
`$notices = new Notices( … )` into a **discarded local**. But the object actually on
`admin_notices` is not that one — it is the `CacheBank` singleton the library creates
internally, and `CacheBank::get_instance()` is public.

So this is ordinary mechanism 2. **Check what is really on the hook before reaching for
the exception**, which is now the third habit recorded in `CLAUDE.md` alongside checking
method visibility and looking for a singleton.

`CacheBank::scripts` is removed from `admin_footer` alongside, since it exists only to
drive the notices.

### Deliberately not done

- **The Facebook token expiry notice** is a separate callback on the same hook and
  reports a real credential problem — a connected feed about to stop updating. Verified
  still hooked with the rule active
- **`promotion_message_on_admin_screen`** only registers when the current screen is
  `toplevel_page_essential-blocks` — the vendor's own page

### Vendor scope

WPDeveloper also publish Essential Addons for Elementor and EmbedPress, both already
audited. **Neither bundles `PriyoMukul\WPNotice`**, so this rule covers Essential Blocks
alone and the existing `eael/disable_promotions` rule is unaffected.

### Verified

Structurally, not by rendered output — none of the four notices is eligible on a freshly
installed bench (`summer_campaign2026` expired 25 June 2026; the others start 1, 2 and 7
days after registration):

| Check | Rule off | Rule on |
|---|---|---|
| `CacheBank::notices` on `admin_notices` | hooked | **gone** |
| `CacheBank::scripts` on `admin_footer` | hooked | **gone** |
| `Facebook::render_expiry_notice` | present p10 | **present p10** |

A first attempt to A/B by page content was discarded as invalid: activating the plugin
redirects to its "Quick Setup Page", so the two captures were of different screens.

## [1.14.0] — 2026-09-05

### Added

- **QuadLayers' review nag, cross-install promos and "QuadLayers News" dashboard widget**
  removed — covering **five fleet plugins across 8 sites** from two rules

  QuadLayers split their admin surfaces into six separate Composer packages under
  `jetpack_vendor/quadlayers/`. The wiring guards with `class_exists()`, so whichever
  plugin loads first owns the package: `insta-gallery`,
  `autocomplete-woocommerce-orders`, `quadmenu`, `search-exclude` and
  `woocommerce-checkout-manager` are all covered, as is anything of theirs installed later

### The cleanest vendor separation found so far

The promotional notice lives in `wp-notice-plugin-promote` and the dependency notice
(*"The %1$s is not working because you need to activate the %2$s plugin"*) lives in
`wp-notice-plugin-required` — **different packages, different classes, different
callbacks**. Removing one cannot affect the other. No mixed output, no collateral,
nothing to weigh.

Every other vendor this week required an argument about what would be lost. This one
required none, because QuadLayers had already drawn the line themselves.

### Deliberately not done

- **`wp-plugin-install-tab`** and **`wp-plugin-suggestions`** are promotional and sit on a
  core screen, but they are the **plugin installer**, not the notice area or the dashboard
- **`wp-plugin-table-links`** adds links to the plugin list row — out of scope
- **`Load::remove_all_data`** only registers when `$this->developer_mode` is true, so it
  never fires on a normal install

### Verified

A/B on WP 7.1 with Insta Gallery 5.0.8 active: `id="wp-dashboard-widget-news"` **1 → 0**,
`quadlayers.com` occurrences **5 → 0**, the *"Enjoying Social Feed Gallery?"* review nag
**1 → 0**, notice divs **19 → 15**, zero PHP fatals, front page 200.

## [1.13.0] — 2026-09-05

### Added

- **Five ElementsKit Lite rules** (10 fleet sites), from a nag spotted on a client site.
  Wpmet ship shared libraries under `libs/`, four of which exist only to put promotional
  content in the admin:

  - `wpmet-stories` dashboard widget — "Wpmet Stories", fetching `api.wpmet.com` on
    render, and **reordering `$wp_meta_boxes` to force itself to the top**. Third vendor
    found doing that, after WP Desk and Elementor
  - `Wpmet\Libs\Rating::fire` — review nag
  - `ElementsKit_Lite\Libs\Pro_Label\Init::show_go_pro_notice` — upsell to
    `wpmet.com/elementskit-pricing`
  - `Wpmet\Libs\Banner::display_content` — remotely-driven banner
  - `Wpmet\Libs\Emailkit::emailkit_admin_head` — cross-sell of another Wpmet product

### Why the producers, not the framework

`Oxaim\Libs\Notice::instance()` does `self::$instance = new self();` — it overwrites the
static each time, so it is **not** a keyed registry. Every notice on `admin_notices` is
the same class with the same `get_notice` method and they cannot be told apart by class
and method.

So the rules remove the four **producers** on `admin_head` instead, each a distinct class.
That leaves `unsupported-elementor-version` and `unsupported-php-version` — created
directly in `elementskit-lite.php`, not through a lib — completely untouched.

Third use of `remove_discarded_instance_callback()`, and it qualifies: no filter, no
constant, no singleton accessor, and `plugin.php` discards every instance.

### Deliberately not done

- **The auto-install / email prompt** is scoped to `page=elementskit` and the Get Help
  page — the vendor's own screens
- **The admin menu "Go Pro" link** survives. Promotional, but the admin menu is not the
  notice area or the dashboard
- **`ekit_user_consent_for_banner`** — Wpmet ship a user-facing consent toggle gating the
  banner, widget and prompt, which is better behaviour than most vendors audited here. It
  is a stored setting rather than a filter, so using it would mean a database write

### Verified

A/B on WP 7.1: `id="wpmet-stories"` **1 → 0**, `wpmet.com` occurrences **11 → 3** (the
remainder being the admin menu link), notice divs **22 → 15**, zero PHP fatals.

`Rating::fire` and `Banner::display_content` were observed being removed. `Pro_Label` and
`Emailkit` were **not registered on the bench** — `Pro_Label` requires more than ten days
since activation — so their removal is structural rather than observed, and both logged
the intended "not registered … no action taken" drift signal.

## [1.12.1] — 2026-09-05

### Changed

- **The repeated `999` priority is now `self::LATE_PRIORITY`**, a documented class
  constant. No behaviour change; the number is unchanged and now explained in one place

  The question that prompted it was whether `PHP_INT_MAX` would be better. **It would be
  worse**, and the evidence is recorded in `CLAUDE.md`: WooCommerce already occupies
  `PHP_INT_MAX` on `admin_notices` (`Loader::inject_after_notices`) and on
  `in_admin_header` (`WC_Settings_Payment_Gateways::suppress_admin_notices`). WordPress
  resolves a tie in registration order, and an mu-plugin registers **first** — so tying
  would make us run *before* the vendor we tied with, the opposite of the intent.

  Surveyed on a 20-plugin bench: `admin_notices` carries callbacks at 999, 1000 and
  `PHP_INT_MAX`; `admin_init` at 999, 1000, 99999 and 999999999. **There is no priority
  that guarantees being last**, so the rule is now stated as "beat the vendor's own
  registration", which 999 does for every audited rule.

- `CLAUDE.md` also gains the `current_screen` timing note from 1.12.0: `admin.php` fires
  `admin_init` at line 180 but calls `set_current_screen()` at line 217, so a vendor that
  registers from a `current_screen` handler is invisible at `admin_init`.

### Verified

All sixteen rules still fire on the bench, both harnesses pass, `e-conversion-banner`,
`pa-stories` and `themeisle` all still 0, zero PHP fatals.

## [1.12.0] — 2026-09-05

### Added

- **Elementor's promotions module suppressed** — the "Build more with Elementor Pro"
  conversion banner, plus the **Black Friday** and **Birthday** pointer banners in the
  same module. All three render on the WordPress dashboard; the conversion banner also
  appears on Elementor's own screens

- **The banner's stylesheet and script are dequeued** by handle
  (`e-conversion-banner`). An anonymous callback enqueues them, so nothing can unhook it
  by name. Without this the page still carried a stylesheet and two script tags for a
  banner that no longer renders: occurrences went 12 → 4 with the unhook alone, and
  12 → **0** once the assets were dequeued

### Changed

- **The `$wp_filter` walk is now one shared reader.** `remove_discarded_instance_callback(
  $hook, $class, $method, $rule_id )` replaces the bespoke walk written for WPB Product
  Slider in 1.3.0, which is now a four-line call into it

  When the first exception was granted, a reusable helper was deliberately *not* built —
  on the grounds that a helper invites reuse. That was right with one case. With a second
  legitimate case, two hand-rolled walks is worse than one audited reader: `$wp_filter`
  now appears in exactly one method in the file. `CLAUDE.md` and `README.md` are rewritten
  to say **one reader, not a licence** — never add a second, extend this one.

  The WPB harness still passes unchanged against the shared reader, including the decoy
  case where a different class exposes a method of the same name.

### Why the exception was justified here

Established before it was used, and recorded in `docs/plugins/elementor.md`:

- **No filter.** `modules/promotions/` has two `apply_filters` calls, both for the admin
  *menu* item's text and URL. `should_display_banner()` reads `Utils::has_pro()` and a
  user-meta dismissal flag — neither filterable, and faking the flag is a database write
- **Instance discarded.** `module.php:90` is `new Conversion_Banner();` with no
  assignment, no singleton accessor. Both pointer classes are the same
- **Not a dashboard widget**

### A new timing trap

`wp-admin/admin.php` fires `admin_init` at line 180 but calls `set_current_screen()` at
line **217**. `Conversion_Banner` only adds its `in_admin_header` callback from a
`current_screen` handler, so at `admin_init` priority 999 there is nothing to remove.
This is the first rule to need a phase other than `admin_init` or `wp_dashboard_setup` —
it runs on `current_screen` priority 999.

### Verified

A/B on WP 7.1 with Elementor 4.2.4 active, using separate capture files and a settle
delay after each deploy: `e-conversion-banner` **12 → 0**, "Build more with Elementor Pro"
**1 → 0**, zero PHP fatals, front page 200. Both harnesses pass.

## [1.11.0] — 2026-09-05

### Added

- **Elementor's "Elementor Overview" dashboard widget removed** (`e-dashboard-overview`),
  and with it the "News & Updates" remote feed. Elementor is on 81 fleet sites, the widest
  reach of any widget rule so far

### A documented decline, reversed by the site owner

`docs/plugins/elementor.md` recorded this widget as *"not removed, ambiguous … left alone
until there is a way to drop only the news section"*. **There is no such way.** Every
filter in both relevant files was checked — three in `core/admin/admin.php`
(`footer_actions`, `create_new_post/meta`, `localize_settings`) and three in
`includes/api.php`, all for library templates. `Api::get_feed_data()` reads its option
directly with no filter, and the widget registration is unconditional.

Two facts found while confirming that tipped the decision:

- **Rendering the widget makes an outbound call** — `get_feed_data()` → `get_info_data()`
  → `wp_remote_get( self::$api_info_url )` when the transient is cold
- **It forces itself to the top of the dashboard**, reordering `$wp_meta_boxes` under the
  comment `// Move our widget to top.` — the same behaviour that made the WP Desk widget
  this project's first target

**The collateral is named, not glossed:** the "Recently Edited" list goes with it. It
duplicates what the Pages screen already shows, and the decision was taken by the site
owner on the grounds that fleet clients do not read the dashboard panel.

If Elementor ever adds a filter around the feed section, **withdraw this rule** and use
the filter instead — that restores Recently Edited at no cost. Recorded in the doc's drift
check.

### Verified

A/B on WP 7.1 with Elementor 4.2.4 active: `id="e-dashboard-overview"` 1 → **0**, "News &
Updates" 1 → **0**, zero PHP fatals, front page 200. "Recently Edited" showed 0 in both
runs because the bench has no Elementor-edited pages, so that section renders empty
regardless — its loss is established from the source, not observed.

This release also **exercised the Elementor notices rule on a live install for the first
time**. That rule shipped in 1.0.0 but Elementor had never been installed on the bench;
the debug log now confirms `Removed Admin_Notices::admin_notices from admin_notices
priority 20`.

## [1.10.0] — 2026-09-05

### Added

- **WP Swings' seasonal offer banners removed** — three named callbacks
  (`wps_banner_notification_plugin_html`, `wps_giftcard_notification_plugin_html`,
  `wps_sfw_banner_notification_html`) that all render the same `wps-offer-notice` markup.
  Reported from a live client site as a US Labor Day sale banner

  All three are plain named functions registered at file scope, so `remove_action()` names
  them directly — no singleton, no `$wp_filter`, no object identity to get wrong.

### The first remotely-driven nag

This is the first vendor audited whose promotional content is **served from the vendor's
own server rather than shipped in the plugin**. The banner image and link are fetched from
`demo.wpswings.com/client-notification/…/wps-client-notify.php` and stored in options,
then rendered from those options.

That explains how a seasonal sale appears on a site whose plugin has not been updated, and
it means the drift check cannot rely on reading the plugin's source for banner content —
only for the callbacks that render it.

The remote endpoint is deliberately **not** blocked. It may carry other information, and
this project removes notices rather than intercepting a vendor's HTTP traffic. Removing
the render callbacks means the banner never displays whatever the endpoint returns.

### Vendor spread

`wps_banner_notification_plugin_html` is registered by **both** WP Swings plugins on the
fleet, each guarded with `function_exists()`, so whichever loads first owns it. One
`remove_action()` covers both and any future WP Swings plugin using the same pattern.

### Deliberately not done

- **Both dashboard widgets stay.** `wps_gift_card_summary` shows the site's own gift card
  figures; `wps_ai_subscription_health` shows subscription health and only registers once
  the owner has configured an AI provider
- **Two notices left as ambiguous** — `wps_wgm_display_notification_bar` and
  `wps_sfw_membership_feature_notice`. Both look promotional by name, but their rendered
  content was not established, and ambiguous does not go in

### Verified

Hook state before and after on WP 7.1 with both plugins active: all three callbacks
HOOKED → **gone**, zero PHP fatals, front page 200. Verified by hook state rather than
rendered output, because the banner only appears once the remote endpoint has populated
its options — which a fresh bench has not done.

## [1.9.0] — 2026-09-05

### Added

- **Premium Addons' three promotional notices removed** — the review request
  (`pa_review_notice`), the Angie "new feature" notice (`pa-angie-not`) and the Connect-AI
  upsell (`pa-connect-ai-not`) — **without dismissing anything on the site owner's
  behalf**

### A rejected finding, reversed

1.6.0 recorded this as impossible. Premium Addons registers one `admin_notices` callback
that prints the Elementor dependency notice *and* the three promos, so unhooking it would
take an operational notice with it. That was documented as mixed output with no rule.

**The conclusion only held if the callback were atomic, and it is not.**
`required_plugins_check()` is declared `public`, as is `admin_notices()`. So the
dispatcher is removed and the dependency check re-added on its own:

```php
remove_action( 'admin_notices', [ $notices, 'admin_notices' ] );
add_action( 'admin_notices', [ $notices, 'required_plugins_check' ] );
```

The three promotional methods are private and called only from the dispatcher, so they
cannot run. Reached through the vendor's own singleton,
`\PremiumAddons\Admin\Includes\Admin_Notices::get_instance()`. No `$wp_filter`, no
option written, nothing left behind when this plugin is removed.

The swap is **guarded**: if `required_plugins_check()` cannot be found, nothing is removed
at all. Leaving three promos in place is better than silently losing a dependency notice.

**The lesson generalises**: when a callback is mixed, check the visibility of its parts
before declaring it atomic. A public operational method can be re-hooked.

### The alternative that was rejected

Writing the three dismissal options (`pa_review_notice`, `pa-angie-not`,
`pa-connect-ai-not`) to `'1'` would have worked, and would have needed a second, opt-in
file to keep this plugin's read-only guarantee intact. Rejected: it writes permanent
per-site residue that removing this plugin would not undo, records a dismissal the site
owner never made, and would need repeating for every vendor that gates on an option.

### Verified

A/B on WP 7.1: `pa-connect-ai-notice` goes 1 → 0 while the Elementor dependency notice
stays at 1. The review and Angie notices could not be observed on the bench — the first is
time-gated, the second needs `ANGIE_VERSION` from a companion plugin — so their removal is
structural rather than observed: both are private and only the dispatcher called them.
Zero PHP fatals.

## [1.8.0] — 2026-09-05

### Added

- **WooCommerce Lottery's "wpgenie.org - Our latest themes and plugins" dashboard widget**
  (`wpgenie_dashboard_products_news`) removed with `remove_meta_box()`, taking with it an
  RSS fetch of `wpgenie.org/tag/dashboard/feed/` on every dashboard render

### Framework hypothesis tested and negative

This audit was run mainly to test whether `wpgenie` is a shared framework, the way
`themeisle-sdk`, `plugin-fw`, `bsf-analytics` and the WP Desk packages turned out to be.
**It is not.** All 1,129 distinct fleet slugs were checked by opening their newest vault
release and testing the file list for `wpgenie`; exactly one matched. Even
`woocommerce-lottery-pick-number`, from the same vendor, does not bundle the dashboard
class.

So this rule covers one site, not a range. Recorded in
`docs/plugins/woocommerce-lottery.md` so the question is not re-opened.

### Deliberately not done

Both of the plugin's `admin_notices` are operational and stay. The cron-job notice looks
like a nag — it reappears until dismissed — but *"recommends that you set up a cron job to
check for finished lotteries"* reports a real gap: without it, finished lotteries are
never closed. On a hosting fleet that is precisely the kind of notice that must survive.

### Verified

A/B on WP 7.1: the widget goes 1 → 0 and `wpgenie.org` occurrences on the dashboard go
10 → 0, confirming the render callback and its outbound fetch never run. The cron-job
notice still renders. Zero PHP fatals.

## [1.7.0] — 2026-09-05

### Added

- **`cky_is_module_active_connect_banner`** removes CookieYes's *"Unlock advanced features
  for seamless compliance — Connect to CookieYes Web App"* banner from `plugins.php`

### Changed

- **A classification reversed.** The connect banner was recorded as `keep` earlier the
  same day on the grounds that connecting enables CookieYes's cookie scanner, and a stale
  cookie list is a genuine compliance problem. Reading the full copy on a live install
  changed that: the framing is *"Unlock advanced features"*, the call to action is signing
  up for a paid SaaS, and the plugin manages consent perfectly well unconnected. The
  notice does not report a problem with the site, it offers a product

  Both sides of the argument are recorded in `docs/plugins/cookie-law-info.md`, along with
  the condition for reversing it again: if a future version reports an actual scan failure
  or a stale cookie list — a statement about the site rather than an offer — it should
  come back.

### Verified

Full regression on WP 7.1 with sixteen plugins active, five admin screens, all HTTP 200,
zero PHP fatals, both harnesses passing. On `plugins.php` the connect banner goes 1 → 0
while six operational notices on the same screen still render: the Elementor dependency
prompt, Yoast's search-engines-discouraged warning, and four licence activation notices
for unlicensed premium plugins.

## [1.6.0] — 2026-09-05

The rest of Paul's live-site list. Four rules across three vendors, and mechanism 3 gets
its first real occupants since YITH vacated it in 1.0.0.

### Added

- **Forminator's "Pro Form Templates—Now Free for Everyone!" dashboard promo and its
  review request**, both unhooked via `\Forminator_Core::get_instance()->admin` — a
  documented singleton with a public `$admin` property, so no `$wp_filter` needed. The
  promo is gated on `'dashboard' === $screen->id`, putting it squarely on the WordPress
  dashboard. `promote_free_plan_scripts` goes with it
- **Premium Addons' "Premium Addons News" dashboard widget** (`pa-stories`), removed with
  `remove_meta_box()`. Note the context is **`column3`**, not the default `normal`. Also
  stops an outbound call to `premiumaddons.com/wp-json/stories/v2/get` on every render
- **CSS Hero's "From the CSS Hero world" RSS widget** (`widget_cssheronews`), and with it
  an RSS fetch per dashboard render

### Deliberately not done

- **Premium Addons' `pa-new-feature-notice` upsells** — the notice Paul actually
  reported — are **left alone**. The plugin registers one `admin_notices` callback that
  runs `required_plugins_check()` first and unconditionally, printing an *install
  Elementor* dependency prompt, before reaching the review and AI upsells. Mixed output,
  and the collateral is a dependency notice on a plugin that cannot function without
  Elementor. The vendor's own `check_hide_notifications()` gate is a Pro white-labelling
  feature, not an opt-out available to us. If the vendor ever splits that dispatcher this
  becomes a clean mechanism 2 target
- **Forminator's `show_pro_available_notice`** is an upsell, but gated on `$_GET['page']`
  starting `forminator` — the vendor's own screens
- **CSS Hero's `wpcss_hidedashnews` option** would suppress the widget at source, and was
  rejected: writing to a vendor's options leaves residue that removing this plugin would
  not undo

### Verified on a live site

A/B tested on WP 7.1 with all three plugins active, over authenticated admin requests:

| Check | Rules off | Rules on |
|---|---|---|
| `id="pa-stories"` on the dashboard | 1 | **0** |
| `id="widget_cssheronews"` on the dashboard | 1 | **0** |
| "Pro Form Templates" on the dashboard | 1 | **0** |
| Forminator rating notice | 1 | **0** |
| CSS Hero licence notice (unlicensed bench) | present | **still present** |
| Notice divs remaining on the dashboard | — | 17 |
| PHP fatals | 0 | 0 |

CSS Hero's licence notice surviving is the check that matters: the bench is unlicensed,
which is exactly the state in which that notice must reach the site owner.

## [1.5.0] — 2026-09-05

Three rules from real nags observed on live client sites, rather than from install-count
ranking. Both vendors put promotional output on **core** admin screens, unlike the large
vendors audited earlier the same day.

### Added

- **`themeisle_sdk_hide_dashboard_widget`** removes ThemeIsle's "WordPress Guides/Tutorials"
  dashboard widget, and with it two RSS fetches on every dashboard render
  (`themeisle.com/blog/feed`, `wpshout.com/feed`) plus `api.wordpress.org` queries listing
  the vendor's other products with Install links

  `themeisle-sdk` is a shared Composer package. Found by opening the newest vault release
  of all 1,129 distinct fleet slugs and testing for a `themeisle-sdk/` path: it is bundled
  in `menu-icons` (8 sites), `wpcf7-redirect` (7) and `robin-image-optimizer` (1), and any
  future ThemeIsle plugin is covered automatically

  The filter is read at the top of `Dashboard_Widget::load()`, so the widget is never
  registered and the feeds are never fetched. Preferred over `remove_meta_box()` because
  the widget guards itself with a `$wp_meta_boxes` mutex — on a multi-ThemeIsle site only
  one plugin registers it, the same trap that ruled out an ID-based rule for WP Desk

- **`cky_is_module_active_review_feedback`** removes CookieYes's review request. CookieYes
  loads every module through a base class whose constructor calls `init()` only
  `if ( true === $this->is_active() )`, and `is_active()` is a per-module filter — so the
  `admin_notices` hook is never registered at all. Also takes the module's
  `admin_footer_text` review link, part of the same nag

- **`CYA11Y_ACCESSYES_BANNER_DISPLAYED`** removes the WebToffee "AccessiYes" cross-promotion
  banner. The vendor uses this constant as a first-loader mutex across their range, so
  defining it from an mu-plugin means the banner's file is never required — in CookieYes
  and in any other WebToffee plugin using the same package, which the vendor's own file
  header names as including WT Smart Coupons

### Deliberately not done

- **CookieYes's connect banner** (*"Unlock advanced features for seamless compliance"*)
  is left alone as ambiguous. It promotes a paid SaaS, but connecting is what enables the
  cookie scanner, and a stale cookie list is a real compliance problem.
  `cky_is_module_active_connect_banner` is there if the judgement changes
- **CookieYes's dashboard widget** is mixed output: consent-rate trends when connected,
  a sign-up CTA when not. One switch governs both, so no rule
- **`affiliate_banner`** is in CookieYes's module list but has no directory in 3.5.5, so
  it never loads. A rule would target something that does not run

### Verified on a live site

A/B tested on WP 7.1 with both plugins active, over authenticated admin requests:

| Check | Rules off | Rules on |
|---|---|---|
| `id="themeisle"` on the dashboard | 1 | **0** |
| AccessiYes banner on the dashboard | 1 | **0** |
| CookieYes review nag on `plugins.php` | 1 | **0** |
| CookieYes connect banner (left alone) | present | **still present** |
| PHP fatals | 0 | 0 |

The connect banner surviving is the important line — it shows the rules are targeted
rather than a blanket suppression of the vendor.

## [1.4.3] — 2026-09-05

### Changed

- **Comments cut back to the house rule.** 341 lines to 299, with no behaviour change.
  The file had drifted into explaining *why* decisions were taken, which is what
  `docs/plugins/` is for

  - Each mechanism 1 rule had a three or four line block restating its rationale. Each
    is now one line: vendor, version verified against, doc path. The only surviving
    second line is the WP Desk priority 999 note, because a load-order trap is exactly
    what an inline comment is for
  - `register_vendor_optouts()` lost a five-line docblock arguing about logging policy;
    that argument lives in `CLAUDE.md`
  - The `class_exists()` guard in `unhook_elementor_notices()` lost its three-line
    explanation. `if ( ! class_exists( '\Elementor\Plugin' ) ) { return; }` needs none
  - Docblocks on `unhook_wpb_product_slider_review_notice()`,
    `remove_core_welcome_panel()` and `get_elementor_admin_notices_component()` trimmed
    to a sentence plus, where it earns its place, the load-order or collateral note

  Both harnesses still pass and the bench shows zero fatals. Terse no-op branch
  comments are kept — they are required by the house style, not incidental.

## [1.4.2] — 2026-09-05

### Changed

- **Debug logging now records what happened, not what was registered.** With
  `HEADWALL_NAG_CLEANUP_DEBUG` on, an admin page load emitted six lines, five of which
  were mechanism 1 filter registrations that fire identically on every request of every
  site whether or not the vendor is installed. The one line that recorded a real
  suppression was buried among them

  - The five per-rule registration lines become **one**: `Registered vendor opt-out
    filters.` A filter registration is not a suppression, and a mechanism 1 rule cannot
    report one — the vendor reads the filter and `__return_false` is core's callback,
    not ours. Adding a wrapper method per rule purely to log would trade five methods
    for information the site's plugin list already gives you
  - **"Not installed" branches are now silent.** Elementor's "component not reachable"
    line fired on every admin request of every site without Elementor. It now logs only
    when Elementor *is* installed but the component cannot be reached — which is the
    drift signal that actually means something

  A dashboard load now logs three lines (one summary, two real removals) and the Plugins
  screen two. Both harnesses — double-include and the WPB `$wp_filter` unhook — still
  pass, and `error.log` on the bench shows zero fatals.

- `CLAUDE.md` gains the rule this follows: log an actual removal, or a vendor that is
  installed but unreachable. Never a registration, never a missing vendor.

## [1.4.1] — 2026-09-05

### Changed

- **Boot logic moved outside the class.** `Plugin::boot()` is gone; the file now ends
  with `$headwall_nag_cleanup = new Plugin(); $headwall_nag_cleanup->run();`. A class
  should not contain its own instantiation ceremony — that belongs to the caller

  The `class_exists` wrapper is what makes a second include a no-op, so `boot()`'s
  internal "already booted" check was dead code and is not replaced. `global
  $headwall_nag_cleanup;` is now declared explicitly: `wp-settings.php` includes
  mu-plugins at global scope so a bare assignment usually works, but a plugin including
  this file from inside a function would create a local, and the instance must stay
  globally reachable for `remove_filter()`

  Verified against WordPress 7.1's real hook API: class declared once, instance created
  once, second include from global scope is a clean no-op, a **third include via a
  different file path from inside a function** is also a no-op (proving `class_exists`
  rather than `include_once`'s realpath dedupe is doing the work), and the instance's
  hooks remain findable and removable by a third party

- **House style clarified.** Hook callbacks are public **instance** methods registered
  as `[ $this, 'method_name' ]`, not static methods. This is what the code already did;
  `CLAUDE.md` said "public static method" and now matches. The load-bearing rule is
  unchanged — never a closure, because a closure cannot be passed to `remove_filter()`

## [1.4.0] — 2026-09-05

One filter covering the Brainstorm Force range — Astra Pro, Spectra, Spectra Pro,
Astra Widgets and Custom Typekit Fonts, ~128 installs on the fleet.

### Added

- **`bsf_usage_tracking_enabled`** disables Brainstorm Force usage tracking across every
  plugin bundling `bsf-analytics`. The vendor documents it in-code as a *"global kill
  switch — allows hosting providers, compliance plugins, or agency developers to disable
  all BSF tracking with one filter"*. Verified against Astra Pro 4.13.8 and Spectra 2.20.3
- `docs/plugins/brainstorm-force.md` — one document covering all five plugins and the
  three shared libraries, in the manner of the YITH framework audit

  The filter stops the outbound payload but **not** the opt-in notice: `option_notice()`
  returns early when tracking is *enabled*, so a `false` here leaves the notice showing.
  That is stated plainly in the doc rather than glossed. It does not make the notice
  worse either — where the opt-in option is unset the notice appears either way.

### Deliberately not done

The largest available win in this vendor's code is the one that must not be taken:

- **`BSF_PRODUCTS_NOTICES`** silences all `bsf_notices` output with one constant. What
  it silences is *"Please activate your copy of [Product] to get update notifications"* —
  a licence activation notice. Using it would leave ~53 Astra Pro and Spectra Pro sites
  quietly not receiving updates. The per-product `BSF_<PRODUCT>_NAG` and
  `BSF_<PRODUCT>_NOTICES` constants are rejected for the same reason
- **`astra_notices_user_cap_check` / `bsf_admin_notices_user_cap_check`** would disable
  the whole `BSF_Admin_Notices` framework. Spectra queues five notices through it and
  four are operational — including **"Spectra Legacy database update required"**.
  Suppressing the framework would destroy a database migration prompt, the exact failure
  the blanket-suppression ban exists to prevent
- **`UAGB_Admin::register_notices`** holds the one real upsell found ("Want to do more
  with Popup Builder? … Upgrade Now") but queues it from the same callback as the
  migration prompt and the "Block Editor required" dependency notice. Mixed output, so
  no rule
- **The `bsf-analytics` opt-in notice** is a genuine target but unreachable:
  `BSF_Analytics_Loader::load_analytics` discards the instance, exactly as WPB Product
  Slider does. Removing it would need a second `$wp_filter` exception, which is left as
  a deliberate decision rather than taken

### Verified on a live site

Tested on WP 7.1 with Astra Pro, Spectra, Astra Widgets and Spectra Pro active, over an
authenticated admin request — `wp-cli` is not a valid harness, since it runs with
`is_admin()` false and this plugin correctly bails. The licence notice (`bsf_notices`)
and Astra's theme-dependency notice both remain hooked, 25 callbacks remain on
`admin_notices`, and `error.log` shows zero fatals.

## [1.3.0] — 2026-09-05

Removes WPB Product Slider for WooCommerce's five-star review notice, and in doing so
opens the project's first and only exception to the `$wp_filter` ban.

### Added

- **WPB Product Slider review notice removed**, verified against 2.4.
  `docs/plugins/wpb-woocommerce-product-slider.md` has the full audit

- **The `$wp_filter` exception.** The vendor registers the notice from
  `new WPB_WPS_Review_Notice();` at `main.php:157` and discards the return value.
  `remove_action()` matches object callbacks by `spl_object_hash()`, so there is no
  instance to name and an instance we build ourselves will not match. There is no
  vendor filter, action or constant anywhere near the notice, and it is not a
  dashboard widget — all three mechanisms are unavailable

  The rule reads `$wp_filter['admin_notices']->callbacks` and removes the single entry
  whose object is `instanceof WPB_WPS_Review_Notice` and whose method is
  `maybe_show_notice`. This is narrower than the banned pattern, which removes
  callbacks because they *look* promotional; this one names a class and a method and
  inspects no content. It guards on `instanceof \WP_Hook`, scans every priority so a
  vendor priority change cannot silently kill it, and logs a no-op when nothing matches

  **This is the only place in the file permitted to read `$wp_filter`.** A second such
  rule needs the same write-up, and the first question is whether mechanisms 1 to 3
  really are all unavailable

  Verified against core 7.1's real `WP_Hook` and `remove_action()`: removal at the
  default priority and at priority 42, removal past a closure on the same hook, an
  empty hook, a hook with only unrelated callbacks, and a decoy class exposing its own
  `maybe_show_notice()` — which survives untouched, as does the vendor's
  `handle_notice_action`, so existing dismissal links keep working

### Rejected

- **Writing or filtering the `wpb_wps_review_dismissed` user meta.** Writing it leaves
  permanent residue in `wp_usermeta` that removing this plugin does not undo, and tells
  the vendor a site owner declined a review they never saw. Filtering the read avoids
  the residue but still works by lying about stored state, fires on every user meta
  read on every request, and generalises to any dismissal-gated notice including
  operational ones

## [1.2.0] — 2026-09-05

### Added

- **`HEADWALL_NAG_CLEANUP_REMOVE_WELCOME_PANEL`** removes core's dashboard "Welcome"
  panel via `remove_action( 'welcome_panel', 'wp_welcome_panel' )` — the removal core
  documents at `wp-admin/index.php:194`. Verified against WordPress 7.1

  **Off by default**, and a separate constant from
  `HEADWALL_NAG_CLEANUP_REMOVE_CORE_DASHBOARD_WIDGETS`. The panel is core output, and
  the boundary rule does not permit suppressing core out of the box; it is also not a
  dashboard widget, so it does not belong behind the widget constant. Someone may
  reasonably want one and not the other

  Two things worth knowing before setting it. Removing the only callback makes core's
  `has_action( 'welcome_panel' )` guard false, which drops the panel wrapper *and* the
  "Welcome" checkbox in Screen Options — so it cannot be toggled back from the UI
  while the constant is set. And it must run on `admin_init`, not at file scope:
  `wp-admin/admin.php` loads mu-plugins at line 35 but does not register
  `wp_welcome_panel` until it includes `admin-filters.php` at line 102. A file-scope
  `remove_action()` would silently do nothing

## [1.1.0] — 2026-09-05

Two rules covering the whole WP Desk range. Both nags come from shared Composer
packages that WP Desk bundles into every plugin they ship, so a single pair of
filters covers Flexible Invoices, Flexible Shipping, Flexible Checkout Fields and
everything else of theirs on a site — including plugins installed in future.

### Added

- **`wpdesk/ltvdashboard/disable`** removes the "Grow your business with WP Desk"
  dashboard widget. It registers at `'normal'` context with `'high'` priority, so it
  takes the top-left slot and pushes Site Health and analytics widgets down the page.
  Suppressing it also drops the `wpdesk.net` catalogue fetch the widget makes on
  render — a request that runs with `sslverify` disabled
- **`wpdesk_tracker_enabled`** turns off the usage-data opt-in notice, the
  deactivation survey, the post-activation redirect to the consent screen, and the
  weekly payload carrying store settings, order counts, plugin list, theme, server
  details and licence emails. Registered at **priority 999**: WP Desk's own
  `UsageDataTracker` adds a callback returning `true` unconditionally at priority 10,
  which would otherwise overwrite an opt-out registered from an mu-plugin
- `docs/plugins/flexible-invoices.md`. Records why `remove_meta_box()` was rejected
  for the widget — each WP Desk plugin sets the widget ID to its own slug and a mutex
  filter means only one of them registers it, so a rule naming an ID would be inert
  on any site where a different WP Desk plugin won the race

Left alone: settings-saved and bulk-action notices, the PHP version warning, the
tracker opt-out confirmation, and every marketing box and "Upgrade to PRO" link on
WP Desk's own Support and settings screens.

## [1.0.0] — 2026-09-04

First stable release. Every rule in the plugin now has a source audit behind it, and
the three mechanisms are exercised by real vendors rather than by design intent.

Version 1.0.0 is a commitment about process, not about coverage. The rule set is
small and will stay that way: it grows one audited vendor at a time, and a vendor
that turns out to need no rule is a completed piece of work, not a gap.

### Changed

- **YITH moves from mechanism 3 to mechanism 1.** `plugin-fw` exposes
  `yith_plugin_fw_show_dashboard_widgets` (`class-yith-dashboard.php:145`), a vendor
  opt-out gating both RSS dashboard widgets. It replaces the two `remove_meta_box()`
  calls and is better on three counts: the widgets are never registered rather than
  registered then removed, the same block also gates an `admin_enqueue_scripts`
  registration so a script and stylesheet stop loading on every admin page, and a
  vendor switch survives a widget being renamed
- Plugin author changed to Paul Faulkner
- **In-code comments trimmed** (278 lines to 226). Comments now describe how the code
  works; rationale, evidence and version archaeology live in `docs/plugins/` and are
  referenced by path. Recorded as a standing preference in `CLAUDE.md`
- `PROMOTIONAL_DASHBOARD_WIDGETS` is now empty. That is the correct outcome, not a
  gap — mechanism 3 has no vendor occupant because its only one was promoted to
  mechanism 1. The machinery stays for the next vendor that offers no switch

### Added

- `docs/plugins/yith-plugin-fw.md`. One rule covers **every** YITH plugin, free and
  premium: `plugin-fw` is a shared framework, confirmed by finding byte-identical
  4.7.8 copies inside `yith-woocommerce-wishlist` 4.18.0 from the vault and
  `yith-woocommerce-eu-vat-premium` installed on a live site

### Fixed

- **Documentation accuracy.** The status block still read "Version 0.1.0" while the
  rules table read 0.1.2, and the README claimed every rule had an analysis in
  `docs/plugins/` when YITH had none. Both corrected; the YITH analysis was written
  rather than the claim weakened
- The `/analyse-plugin` opt-out search now also matches `widget`, `dashboard` and
  `show_`. It previously matched only promotional vocabulary, which is why
  `yith_plugin_fw_show_dashboard_widgets` was missed on the first pass and YITH was
  written as a mechanism 3 rule. The skill now also says to read the registration
  site of any promotional surface and look for a wrapping condition, whatever it is
  named

### Left alone in this release

- YITH's system-requirements warning, its post-deactivation confirmation, and its
  settings-panel tabs — all operational or vendor UI

## [0.1.2] — 2026-09-04

Audit of Essential Addons for Elementor. Opposite result to EmbedPress: the rule is
real, and better built than expected.

### Changed

- `eael/disable_promotions` provenance upgraded from "works in production" to
  **verified against 6.8.3**. It is a genuine vendor kill switch, documented by
  WPDeveloper in `readme.txt`, and read **per-surface** rather than once at
  construction — so registering it early from an mu-plugin is explicitly the
  supported use. It covers the ThinkRank promo banner, both promotional dashboard
  widgets (`eael_xspeed_speed_check`, `eael_thinkrank_seo_check`) and the Black
  Friday pointer

### Added

- `docs/plugins/essential-addons-for-elementor-lite.md`

### Notes

- The filter is **new**: it first appears in 6.7.2 (zero occurrences in 6.7.1 and in
  every one of the 70-odd earlier releases held). The rule works, but can only have
  been working since 6.7.2
- No rule was added for `eael/templately_promo`, which defaults to `false`. Filtering
  it could only turn the promo **on**. Recorded in the analysis as a trap
- `WPDeveloper_Notice`, the same review-and-upsell library bundled dead in EmbedPress,
  is bundled dead here too — present, never instantiated
- Left alone: the `elementor_not_loaded` dependency notice and the bulk
  approve/reject result notice
- Also recorded: Essential Addons calls `remove_all_actions()` on four notice hooks on
  its own settings screen — the same pattern as EmbedPress, one hook more thorough

## [0.1.1] — 2026-09-04

First release driven by an `/analyse-plugin` audit, which immediately invalidated
two of the rules shipped the same day.

### Removed

- **EmbedPress rules `embedpress_show_admin_notices` and `embedpress_admin_notices`.**
  Neither hook exists. Sampling across all 57 EmbedPress releases held in the vault
  (4.0.5 – 4.6.5) returns zero occurrences of either name, so the filters never
  fired. They were inherited from the fleet mu-plugin on the provenance "works in
  production" and were never checked against source. Harmless, but false provenance
  in the README, the changelog and the code — which is worse than no rule at all

### Added

- `docs/plugins/embedpress.md` — full analysis. EmbedPress 4.6.5 has **no**
  suppressible promotional notices: its review-and-upsell framework
  (`EmbedPress_Notice`) is present but never instantiated, and every notice it
  actually registers is operational (licence state, an analytics database cleanup
  prompt, per-user Google Calendar results)

### Notes

- The vault holds only the free EmbedPress plugin. EmbedPress Pro is a separate
  package and is not held, so the absence of those hooks in Pro is unproven
- Recorded in the analysis: EmbedPress itself calls `remove_all_actions('admin_notices')`
  on its own two admin screens, so no plugin's notices — including database upgrade
  prompts — render there. Out of scope for us, but worth knowing about
- `eael/disable_promotions` is unaffected: Essential Addons is a separate plugin and
  its audit is still outstanding

## [0.1.0] — 2026-09-04

First working baseline. The machinery is complete and three mechanisms are
implemented end to end; the rule set is deliberately small and every entry in it was
verified against real plugin source.

### Added

- `headwall-nag-cleanup.php` — the single-file mu-plugin, namespace
  `Headwall_Nag_Cleanup`, class `Plugin`, booted via `Plugin::boot()`
- Double-include guard that wraps the class declaration, so the file is safe to load
  from `mu-plugins/`, a theme and another plugin simultaneously
- Request gate: bails on front end, AJAX, cron and JSON requests before registering
  anything
- Mechanism 1 (vendor opt-out hooks), registered at file scope
- Mechanism 2 (targeted `remove_action()`), on `admin_init` priority 999
- Mechanism 3 (dashboard widget removal), on `wp_dashboard_setup`,
  `wp_network_dashboard_setup` and `wp_user_dashboard_setup`, priority 999
- `HEADWALL_NAG_CLEANUP_DEBUG` — log every suppression to the PHP error log
- `HEADWALL_NAG_CLEANUP_REMOVE_CORE_DASHBOARD_WIDGETS` — opt in to removing core's
  "WordPress Events and News" widget
- `/analyse-plugin` skill and `docs/plugins/` audit documents, giving every rule a
  written, committed provenance
- `docs/plugins/elementor.md` — first full analysis

### Rules in this release

| Vendor | Verified against | Mechanism | What goes |
|---|---|---|---|
| EmbedPress | in production; source audit pending | 1 | Admin notices and promotional nags |
| Essential Addons for Elementor | in production; source audit pending | 1 | Promotions via `eael/disable_promotions` |
| Elementor | 4.2.4 | 2 | Nine promotional notices |
| YITH (`plugin-fw`) | 4.7.8 | 3 | Two RSS dashboard widgets fetching `yithemes.com` |
| WordPress core | 7.1 | 3 | "WordPress Events and News" — **opt-in only** |

### Notes

- The Elementor rule removes one callback that also prints `api_upgrade_plugin` and
  `local_google_fonts_disabled`. That collateral was enumerated and accepted
  deliberately; see `docs/plugins/elementor.md`. It is the project's one standing
  boundary-rule exception
- Elementor's `e-dashboard-overview` widget was examined and **not** removed: it
  mixes a remote feed with genuine site data, and ambiguous rules do not go in

### Superseded

- `archive/README.md` and `archive/HANDOFF.md` describe an earlier, more elaborate
  design — four tiers, a rules-as-data registry, inspect mode and a report screen.
  All dropped as over-engineering before any code was written
