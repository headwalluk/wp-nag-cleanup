# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
