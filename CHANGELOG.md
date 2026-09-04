# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
