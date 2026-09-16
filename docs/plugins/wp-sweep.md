# WP-Sweep

- slug: `wp-sweep`
- version analysed: `2.0.1`
- source: `/vault/backups/wordpress/plugins/wp-sweep/wp-sweep,2.0.1.zip`
- licensing: free (Lester 'GaMerZ' Chan)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

112 fleet installs. **Every pass of the checklist returned empty.** Ten PHP files, no
notice-hook registrations of any kind, no dashboard widget, no outbound HTTP call
anywhere in the plugin, no tracking, no Freemius, no promotional string.

This is the cleanest result in the sweep. The plugin does one thing — sweeps orphaned
data from the database — from a Tools screen, and says nothing anywhere else.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **0** |
| `in_admin_header` / `admin_print_footer_scripts` registrations | **0** |
| Multiline `add_action(` form | **0** |
| Vendor opt-out filters | None needed; none present |
| Vendor opt-out constants | None |
| Dashboard widgets | **0** |
| Outbound calls | **0** — no `wp_remote_*`, no `fetch_feed()`, anywhere |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Sweep result messages | `add_settings_error( 'wp_sweep', … )` | keep | "Nothing was selected, so nothing was swept." and similar. Confirms an action the site owner just took, on the plugin's own Tools screen |

## Deliberately left alone

Nothing borderline was found, which is the finding.

The only admin output is three `add_settings_error()` calls in
`includes/class-wp-sweep-admin.php` (lines 543, 549, 564), all of them results of a sweep
the site owner has just run, rendered on the plugin's own `add_management_page()` screen.
They pass the boundary test outright and would be out of scope even if they did not.

The vendor hosts listed in the source (`lesterchan.net`, plus `wpml.org`,
`elementor.com`, `revolution.themepunch.com`, `codecanyon.net`) are **not** outbound
calls — they appear in comments and in the table of third-party option keys the sweeper
knows how to recognise. No HTTP request is made by this plugin at all.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: N/A
- instance reachable via: N/A

## Drift check

Re-check when a new version appears in the vault:

- Any first appearance of a notice-hook registration, a dashboard widget, or a
  `wp_remote_*` call. All three are currently zero, so any hit is a change of character
  worth reading in full

## Verification

Source-verified against the vault release. No rule, so nothing to bench.

## Additions to `headwall-nag-cleanup.php`: NONE
