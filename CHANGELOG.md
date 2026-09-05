# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
