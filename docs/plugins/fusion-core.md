# Avada Core (Fusion Core)

- slug: `fusion-core`
- version analysed: `5.16.1`
- source: `/vault/backups/wordpress/plugins/fusion-core/fusion-core,5.16.1.zip`
- licensing: premium (bundled with the Avada theme, sold on ThemeForest)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on live
client sites — the `themefusion-news` dashboard widget.

**4 fleet sites** — hhw1 1, hhw3 1, hhw6 2.

**One rule added**, mechanism 3. The plugin's only `admin_notices` callback is a WordPress
and PHP version warning, which is on the never-suppress list — a clean split, and a short
audit.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | **1** — `fusion_core_compat_upgrade_notice`, a WP/PHP minimum-version warning. Also one `all_admin_notices`, screen-gated to the plugin's own slider screens |
| Vendor opt-out filters | **None usable.** `avada_dashboard_widget_title` filters the widget's *title*, not whether it renders. The rest are `awb_role_manager_access_capability` |
| Vendor opt-out constants | None |
| Dashboard widgets | **1**: `themefusion-news`, "Avada News", context `normal`, registered on `wp_dashboard_setup` priority **100** |
| Outbound calls from widgets | `https://avada.com/feed/`, four items, via `wp_dashboard_primary_output()` |
| Freemius | Not bundled |

## Findings

| Item | Hook / ID | Verdict | Reason |
|---|---|---|---|
| "Avada News" widget | `wp_dashboard_setup` → `themefusion-news` | **suppress** | Vendor blog feed, a Buy Now licence button, and Blog/Docs/Ticket links. No site state |
| WP/PHP version warning | `admin_notices` → `fusion_core_compat_upgrade_notice` | keep | **Version warning. Never suppressed** |
| Slider screen header | `all_admin_notices` → `Fusion_Slider::get_admin_screens_header` | keep | Navigation header on the plugin's own slider screens |

## Deliberately left alone

### The compatibility notice is exactly what the boundary rule protects

`includes/bootstrap-compat.php` registers one `admin_notices` callback, and all it does is
compare `$GLOBALS['wp_version']` against `FUSION_CORE_MIN_WP_VER_REQUIRED` and `PHP_VERSION`
against `FUSION_CORE_MIN_PHP_VER_REQUIRED`, printing:

> Avada Core requires at least WordPress version %1$s. You are running version %2$s.
> Please upgrade and try again.

WordPress and PHP version warnings are named on the never-suppress list. It is also only
loaded when the site has already failed one of those checks, so on a healthy site it never
registers at all.

### The slider header is not a notice

`Fusion_Slider::get_admin_screens_header()` hooks `all_admin_notices`, which reads like a
notice rule, but `get_current_screen()` gates it to `edit-slide-page`, `edit-slide`,
`slide` and `avada_slider_export_import`. It prints a heading and a "to edit sliders, go
to the Sliders Page" link — navigation furniture on the vendor's own screens. Out of scope
by construction.

### The title filter is not an opt-out

`apply_filters( 'avada_dashboard_widget_title', … )` is the only filter anywhere near the
widget and it only changes the heading text. Renaming a widget does not remove it, so this
is a mechanism 3 rule and not a mechanism 1 one.

## Mechanism

One rule, tier 3.

- phase: `wp_dashboard_setup`, `LATE_PRIORITY` (999)
- vendor registers at: `Fusion_Core::add_dashboard_widget` on `wp_dashboard_setup`
  priority **100**, so 999 runs after it and `remove_meta_box()` finds the widget
- widget ID: `themefusion-news`
- context: **`normal`** — `wp_add_dashboard_widget()` is called with only three arguments,
  so context and priority take their defaults

Removing the meta box means `display_news_dashboard_widget()` never runs, so the
`avada.com/feed/` fetch is never made.

### The sixth vendor to reorder `$wp_meta_boxes`

Avada does not merely add a widget, it promotes itself to the top of the dashboard:

```php
wp_add_dashboard_widget( 'themefusion-news', … );

// Make sure our widget is on top off all others.
global $wp_meta_boxes;
$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];

$fusion_widget_backup = [];
if ( isset( $normal_dashboard['themefusion-news'] ) ) {
	$fusion_widget_backup = [ 'themefusion-news' => $normal_dashboard['themefusion-news'] ];
	unset( $normal_dashboard['themefusion-news'] );
}

$sorted_dashboard = array_merge( $fusion_widget_backup, $normal_dashboard );
$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
```

That is the sixth vendor found doing this, after WP Desk, Elementor, Wpmet, HasThemes and
HappyMonster. It is why the widget pushes Site Health and At a Glance down the page, and
it is the single best argument for this project existing.

The reorder is harmless once the widget is gone: our removal runs at 999, long after the
`array_merge` at 100, and `remove_meta_box()` unsets the entry wherever it now sits.

### What the widget actually renders

Worth recording, because "news widget" undersells it:

- an Avada logo and the installed `AVADA_VERSION`
- a **"Buy Another License"** button linking to `https://1.envato.market/E7kX9`, an
  affiliate URL — and when `Avada::get_data()` reports a sale, the button becomes
  **"On Sale - Only %s"** with a sale styling class
- four items from `https://avada.com/feed/`
- Blog, Docs and Ticket links to `avada.com` and `theme-fusion.com`

The Buy Now button is a purchase CTA, not a licence-state warning. It does not report that
this site's licence has lapsed — it invites the owner to buy another one. That distinction
is what puts this on the suppress side.

## Drift check

Re-check when a new version appears in the vault:

- `includes/class-fusioncore-plugin.php` — `add_dashboard_widget()`. If the widget ID
  `themefusion-news` or the default `normal` context changes, the rule silently stops
  matching
- The `wp_dashboard_setup` priority (currently 100). If it ever exceeds `LATE_PRIORITY`,
  our removal would run first and find nothing
- `includes/bootstrap-compat.php` — if the compat notice ever gains promotional content it
  becomes mixed output and this audit changes
- Any new `apply_filters` around the widget registration, which would demote this to a
  mechanism 1 rule

## Verification

Tested on `bench2.local` (WP 7.1) with Avada Core 5.16.1 active, over authenticated admin
requests, A/B against v1.19.0 (no rule) and v1.20.0. Counted structurally, by the meta
box's `id` attribute:

| Check | Rules off | Rules on |
|---|---|---|
| `id="themefusion-news"` on the dashboard | **1** | **0** |
| `avada.com/feed` referenced on the page | 1 | **0** |
| `1.envato.market` referenced on the page | 1 | **0** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |
| `index.php`, `plugins.php`, `options-general.php`, `edit.php` | HTTP 200 | HTTP 200 |

Debug log with `HEADWALL_NAG_CLEANUP_DEBUG` on:

```
[headwall-nag-cleanup 1.20.0] Avada Core (fusion-core) 5.16.1: Removed dashboard widget "themefusion-news" (Avada News; avada.com feed and a Buy Now licence button).
```

The compatibility notice could not be observed — the bench runs a supported WordPress and
PHP, so `bootstrap-compat.php` is never loaded. Nothing in the rule touches
`admin_notices`, so it cannot affect it.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 3

```php
[
    'widget_id' => 'themefusion-news',
    'context'   => 'normal',
    'vendor'    => 'Avada Core (fusion-core) 5.16.1',
    'reason'    => 'Avada News; avada.com feed and a Buy Now licence button',
],
```
