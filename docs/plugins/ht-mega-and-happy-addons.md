# HT Mega for Elementor, and Happy Elementor Addons

- slug: `ht-mega-for-elementor` (HasThemes), `happy-elementor-addons` (HappyMonster)
- version analysed: `3.2.5`, `3.23.1`
- source: `/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip`
- licensing: freemium (both sell a Pro edition)
- Freemius bundled: no. Happy Addons bundles **Appsero** instead

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from noisy dashboard widgets Paul
reported on a client site.

1 fleet site each. **Different vendors** despite both being Elementor addon packs — HT
Mega is HasThemes, Happy Addons is HappyMonster — so there is no shared framework between
them. One document covers both because they were audited together and their findings are
near-identical.

**Three rules added**: two dashboard widgets and one review nag.

Note the slug: the plugin is `ht-mega-for-elementor`, not `ht-meta-`.

### Both widgets force themselves to the top

Each widget registers, then reorders `$wp_meta_boxes['dashboard']['normal']['core']` to
put itself first:

```php
$all_dashboard_widget = array_merge( $hastheme_dashboard_widget, $dashboard_widget_list );
```

That makes **five vendors** found doing this — WP Desk, Elementor, Wpmet, HasThemes and
HappyMonster. It is worth treating as a genre rather than a quirk.

### Search checklist

| Pass | HT Mega | Happy Addons |
|---|---|---|
| `admin_notices` registrations | 7 | 8 |
| Vendor opt-out filters | `hastech_notice_user_cap_check` (framework-wide, rejected), `htmega_sidebar_adds_banner` | None relevant |
| Vendor opt-out constants | None | None |
| Dashboard widgets | **1**: `hasthemes-dashboard-stories` | **1**: `happy_addons_news_update` |
| Freemius | No | No — **Appsero** |

## Findings

| Item | Plugin | Hook | Verdict |
|---|---|---|---|
| "HasThemes Stories" widget | HT Mega | `wp_dashboard_setup` → `hasthemes-dashboard-stories` | **suppress** |
| "HappyAddons News & Updates" widget | Happy Addons | `wp_dashboard_setup` → `happy_addons_news_update` | **suppress** |
| Review request | Happy Addons | `admin_notices` → `Classes\Review::ha_void_grid_display_admin_notice` | **suppress** |
| Appsero tracking opt-in | Happy Addons | `admin_notices` → `Appsero\Insights::admin_notice` | **suppress** |
| Fast asset mode prompt | HT Mega | `admin_notices` → `HTMega_Performance_Upgrade_Notice::render_notice` | **keep** — operational |
| Pro extension out of date | HT Mega | `admin_notices` closure | keep — operational |
| Diagnostic data opt-in | HT Mega | `admin_notices` closure, priority 0 | **blocked** — see below |
| Dynamic notice framework | HT Mega | `admin_notices` → `Dynamic_Notice::show_admin_notices` | keep — nothing populates it |
| March 2025 campaign | Happy Addons | `admin_notices` → `Classes\Notice::…` | keep (moot) — window closed |
| `Attention_Seeker::seek_attention` | Happy Addons | `admin_notices` | keep (moot) — dead code |
| PHP / Elementor version and dependency notices | both | `admin_notices` | **keep** |

## Deliberately left alone

**HT Mega's "fast asset mode" notice is operational, not an upsell.** Despite the class
name `HTMega_Performance_Upgrade_Notice`, it reads: *"This version ships a faster asset
loader. Compatibility mode keeps legacy global styles on every page. If your site looks
correct in fast mode, you can load HT Mega CSS/JS only on pages that need them."* with an
**Enable fast mode** button. That is a real performance setting for this site. Verified
still hooked with the rules active.

**HT Mega's diagnostic-data opt-in is a closure and cannot be reached.**
`class.diagnostic-data.php` registers `add_action( 'admin_notices', function () {
$this->show_notices(); }, 0 )`. A closure has neither a class nor a method name, so the
`$wp_filter` reader — which matches on exactly those — cannot identify it, and
`remove_action()` has nothing to name. Suppressing it would need matching on a closure's
bound scope, which is a far bigger escalation than the reader represents. **Not attempted.**
It is gated on `htmega_diagnostic_data_agreed` / `htmega_diagnostic_data_notice`, so a
site owner who answers it once will not see it again.

**HT Mega's `Dynamic_Notice` framework renders nothing here.** `self::$notices` is a
private static array populated only through `set_notice()` / `add_notice()`, and nothing
in HT Mega calls either. Like EmbedPress's promo framework, the machinery is present but
unfed. Removing `show_admin_notices` would be a rule against something that does not run.

**`hastech_notice_user_cap_check`** would disable that whole framework via its capability
check. Rejected on the same grounds as Astra's `astra_notices_user_cap_check`: a general
notice framework may carry operational content, and blanket suppression is banned.

**Happy Addons' `Classes\Notice` targets a closed campaign window.** It registers only
between `2025-03-18` and `2025-03-30`, hardcoded. Writing a rule for it would be a rule
against a hook that cannot fire — the mistake corrected for EmbedPress in 0.1.1 and
avoided again for WPB Product Slider's commented-out discount notice. **If a future
version opens a new window, add
`[ '\Happy_Addons\Elementor\Classes\Notice', 'ha_void_grid_display_admin_notice' ]`
alongside the Review rule** — it is a static callback, so it is a one-line addition.

**`Attention_Seeker::seek_attention` is dead code.** It loops over
`self::get_attentions()`, which returns `[]` unconditionally.

## Mechanism

### Two dashboard widgets — tier 3

- phase: `wp_dashboard_setup`, `self::LATE_PRIORITY`
- context: `normal` for both (three-argument `wp_add_dashboard_widget`), then reordered
  within `normal`/`core` — the reorder does not change the context, so
  `remove_meta_box()` matches

### Happy Addons review nag — tier 2

- `Classes\Review::ha_void_grid_display_admin_notice` is a **static** callback
  (`[__CLASS__, …]`), so `remove_action()` names it directly. No instance, no `$wp_filter`
- guarded with `has_action()` so it stays silent when not registered

### Appsero opt-in — tier 2, through the plugin's own singleton

Reached by a three-step chain, each part public:

```
\Happy_Addons\Elementor\Base::instance()   // public static singleton
    ->appsero                              // public property, the Appsero Client
    ->insights                             // public property, the Insights object
```

Every step is guarded with `isset()` and `is_object()`, so the rule no-ops silently when
Appsero has not been initialised on the request.

**This covers Happy Addons only.** Appsero is bundled by three fleet plugins —
`happy-elementor-addons`, `order-sync-with-google-sheets-for-woocommerce` and
`woo-product-carousel-slider-and-grid-ultimate` — but each creates its own `Client`, and
the SDK exposes no global switch. Its only filters are `appsero_endpoint`,
`appsero_is_local` and `appsero_custom_deactivation_reasons`, none of which gates the
notice. A general Appsero rule would need a per-plugin reachability chain each time.

## Drift check

- `ht-mega-for-elementor/admin/admin-init.php` — widget id `hasthemes-dashboard-stories`
- `happy-elementor-addons/classes/dashboard-widgets.php` — widget id
  `happy_addons_news_update`
- `happy-elementor-addons/classes/review.php` — the `Review` class and method name
- `happy-elementor-addons/classes/notice.php` — **the campaign window**. If the dates
  move into the future, add the rule
- `happy-elementor-addons/base.php` — the `Base::instance()` → `appsero` → `insights`
  chain
- `ht-mega-for-elementor/admin/include/class.dynamic-notice.php` — if anything starts
  calling `set_notice()`, re-audit what it carries

## Verification

Tested on `bench2.local` (WP 7.1) with both plugins active, A/B with the rules enabled and
disabled, over authenticated admin requests.

**Verified structurally.** A content-based A/B was attempted first and discarded as
invalid: activating Happy Addons redirects to its own dashboard, so the two captures were
of different screens — the same trap hit with Essential Blocks.

| Check | Rules off | Rules on |
|---|---|---|
| `hasthemes-dashboard-stories` in `$wp_meta_boxes` | **PRESENT in normal** | **gone** |
| `happy_addons_news_update` in `$wp_meta_boxes` | **PRESENT in normal** | **gone** |
| `HTMega_Performance_Upgrade_Notice::render_notice` (must survive) | present | **present** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

**The review nag and the Appsero opt-in could not be exercised on the bench**, and their
removal is structural rather than observed:

- `Review` registers only after 10 days since install, or 15 days after a "remind me
  later" click
- Appsero's `Insights` object is created lazily by `Client::insights()`, which had not
  been called

Both rules are guarded so that a missing target is a silent no-op, and both were confirmed
not to fire spuriously (`gone` / `n/a` in the before state as well as after).

## Additions to `headwall-nag-cleanup.php`: 3 rules

```php
[
	'widget_id' => 'hasthemes-dashboard-stories',
	'context'   => 'normal',
	'vendor'    => 'HT Mega for Elementor 3.2.5',
	'reason'    => 'HasThemes Stories; vendor feed',
],
[
	'widget_id' => 'happy_addons_news_update',
	'context'   => 'normal',
	'vendor'    => 'Happy Elementor Addons 3.23.1',
	'reason'    => 'HappyAddons News & Updates; vendor feed',
],
```

Plus `unhook_happy_addons_promos()` and its private `unhook_happy_addons_appsero_optin()`.
