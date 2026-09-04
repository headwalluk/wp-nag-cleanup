# Essential Addons for Elementor (Lite)

- slug: `essential-addons-for-elementor-lite`
- version analysed: `6.8.3`
- source: `/vault/backups/wordpress/plugins/essential-addons-for-elementor-lite/essential-addons-for-elementor-lite,6.8.3.zip` (80 versions held, 5.9.25 – 6.8.3)
- licensing: freemium (Essential Addons Pro sold separately)
- Freemius bundled: no

## Analysis

Analysed on 4 Sep 2026 by Claude Code (Claude Opus 5).

**The shipped rule is correct.** `eael/disable_promotions` is a real, vendor-provided
and vendor-documented kill switch, and it is unusually well built: it is checked
per-surface rather than once at construction, so an mu-plugin registering the filter
before Essential Addons loads is explicitly the supported use. WPDeveloper document
it in `readme.txt:310` with exactly the call we already make.

This is the opposite result to [EmbedPress](embedpress.md), the same vendor's other
plugin, whose two filters turned out never to have existed.

One nuance worth recording: the filter is **new**. It first appears in **6.7.2**
(zero occurrences in 6.7.1 and in all 70-odd releases before it). The rule was
inherited on the provenance "works in production", and it does — but it can only
have been working since 6.7.2, and was inert on every earlier release.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 6 found (1 commented out) — see Findings |
| Vendor opt-out filters | **`eael/disable_promotions`** (`ThinkRank_Promotion.php:163`), plus `eael/templately_promo` (`Bootstrap.php:294`). Six `eael/business_reviews/*` matches were false positives — "review" as a content feature |
| Vendor opt-out constants | `EAEL_DISABLE_PROMOTIONS` (`ThinkRank_Promotion.php:159`) — equivalent to the filter |
| Dashboard widgets | 2 — `eael_xspeed_speed_check`, `eael_thinkrank_seo_check` (`ThinkRank_Promotion.php:700`) |
| Outbound calls from widgets | None on render; the widgets read local transients |
| Freemius | Not bundled |

## Findings

| Item | Hook / location | Verdict | Reason |
|---|---|---|---|
| ThinkRank promo banner | `admin_notices` → `render_dashboard_banner` | suppress | Cross-sell of a separate product. **Covered by the shipped filter** |
| `eael_xspeed_speed_check` widget | `wp_dashboard_setup` | suppress | Install-prompt for xSpeed. **Covered by the shipped filter** |
| `eael_thinkrank_seo_check` widget | `wp_dashboard_setup` | suppress | Install-prompt for ThinkRank. **Covered by the shipped filter** |
| Black Friday pointer | `includes/bfcm-pointer.php:10` | suppress | Seasonal sale. **Covered by the shipped filter** |
| `WPDeveloper_Notice::upsale_notice` | `WPDeveloper_Notice.php:227` | dead code | Never instantiated — see below |
| `WPDeveloper_Notice::admin_notices` | `WPDeveloper_Notice.php:232` | dead code | Never instantiated |
| `eael_bulk_approve_admin_notice` | `Bootstrap.php:255` | keep | Result of a user-initiated bulk approve/reject on the Users screen |
| `elementor_not_loaded` | `Bootstrap.php:387` | keep | Missing dependency notice. Boundary rule: never suppressed |
| `Plugin_Usage_Tracker::notice` | `Plugin_Usage_Tracker.php:165` | n/a | Registration is commented out in source |

### What the kill switch actually reaches

`promotions_disabled()` (`ThinkRank_Promotion.php:158`) is consulted at three sites,
and between them they cover every promotional surface:

- `render_dashboard_banner()` (`:461`) — the admin notice banner
- `is_hidden()` (`:326`) — which `widget_eligible()` returns through, so both
  dashboard widgets are gated
- `bfcm-pointer.php:10` — the Black Friday pointer

I initially read `widget_eligible()` as bypassing the kill switch, because
`register_dashboard_widget()` does not call `promotions_disabled()` directly. That
was wrong: `widget_eligible()` ends with `return ! $this->is_hidden( $plugin );`, and
`is_hidden()` checks the kill switch first. Recorded because it is an easy misread,
and re-deriving it wastes an hour.

There is one deliberate bypass. `widget_eligible()` returns early when the promoted
plugin is **installed and running**, without consulting the kill switch — at which
point the Speed Check widget shows real measurements rather than an install prompt.
The vendor's own comment says so: *"actually active its widget is functional, not
promotional, so it stays"*. That is correct behaviour and we should not defeat it.

## Deliberately left alone

**`elementor_not_loaded`** (`Bootstrap.php:387`) — fires when Elementor is missing or
too old. A missing-dependency notice, explicitly on the never-suppress list.

**`eael_bulk_approve_admin_notice`** (`Bootstrap.php:255`) — reports the outcome of a
bulk user approve/reject the administrator just performed. Operational feedback.

**`eael/templately_promo`** (`Bootstrap.php:294`) — **no action taken, and this one is
a trap.** It gates the Templately cross-sell button in the Elementor editor, but it
defaults to `false`: the promo is already off, and the only thing filtering this hook
could do is switch it **on**. Adding `add_filter( 'eael/templately_promo', '__return_false' )`
would look like a suppression and achieve nothing; adding `__return_true` would be
actively harmful. Recorded so nobody "completes the set" later.

**`WPDeveloper_Notice`** (`WPDeveloper_Notice.php:227,232`) — WPDeveloper's shared
review-and-upsell notice library, the same one bundled dead in EmbedPress. `new
WPDeveloper_Notice` appears nowhere in the plugin. No rule: there is nothing to
suppress, and targeting an uninstantiated class would be guesswork.

### Vendor behaviour worth knowing about

`Traits/Helper.php:736` — on `toplevel_page_eael-settings`, Essential Addons calls
`remove_all_actions()` on **four** notice hooks (`user_admin_notices`,
`admin_notices`, `all_admin_notices`, `network_admin_notices`), then re-dispatches
its own through `eael_admin_notices`.

Same pattern as EmbedPress, same vendor, one hook more thorough. On the Essential
Addons settings screen a site owner sees no notices from any other plugin at all,
including database upgrade prompts. Out of scope for us; recorded because it
explains the symptom if anyone ever reports it.

## Mechanism

- tier: 1 (vendor opt-out hook)
- phase: file scope
- vendor registers at: n/a — the filter is read at render time, per surface. The
  vendor explicitly supports registering it early from an mu-plugin
- instance reachable via: N/A
- alternative: `define( 'EAEL_DISABLE_PROMOTIONS', true )` is equivalent. The filter
  is preferred: it needs no `wp-config.php` edit and keeps configuration in one file

## Drift check

Re-check when a new version appears in the vault:

1. `includes/Classes/ThinkRank_Promotion.php` — does `promotions_disabled()` still
   exist and still read `eael/disable_promotions`? It has only existed since 6.7.2,
   so it is young enough to be renamed.
2. Any **new** `add_action( 'admin_notices'` or `wp_add_dashboard_widget(` that does
   not route through `is_hidden()` / `promotions_disabled()`. A new promo surface
   that forgets the kill switch is the most likely regression.
3. `WPDeveloper_Notice` — is it instantiated yet? Search `new WPDeveloper_Notice`.
4. `eael/templately_promo` — does it still default to `false`?

## Additions to `headwall-nag-cleanup.php`: NONE

No new rule. The existing rule is **confirmed** and its provenance upgraded from
"works in production" to "verified against 6.8.3":

```php
add_filter( 'eael/disable_promotions', '__return_true', 100 );
```
