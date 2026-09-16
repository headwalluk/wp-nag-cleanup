# Database Collation Fix

- slug: `database-collation-fix`
- version analysed: `1.2.11`
- source: `/vault/backups/wordpress/plugins/database-collation-fix/database-collation-fix,1.2.11.zip`
- licensing: free (Dave Jesch / davejesch.com)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

47 fleet installs. **One PHP file, and every pass returned empty.** No notice-hook
registration of any kind, no dashboard widget, no outbound HTTP call, no tracking, no
Freemius, no promotional string.

The plugin converts tables using `utf8mb4_unicode_520_ci` or `utf8_unicode_520_ci` to a
standard collation, on a cron interval, with a Tools screen to run it manually. It is
network-capable (`Network: True`) and says nothing in the admin outside its own page.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **0** |
| `in_admin_header` / `admin_print_footer_scripts` registrations | **0** |
| Multiline `add_action(` form | **0** |
| Vendor opt-out filters | None present |
| Vendor opt-out constants | None |
| Dashboard widgets | **0** |
| Outbound calls | **0** |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| — | — | — | Nothing found on any surface this project touches |

## Deliberately left alone

Nothing was found to leave alone, which is the result.

The only admin surface is `add_management_page()` at `databasecollationfix.php:350` — the
"Collation Fix" screen under Tools, which reports what the plugin converted. The
`davejesch.com` URLs in the source are the author and plugin URI headers, not requests.

Note for a future pass: this plugin **writes to the database on a cron schedule**
(`ds_database_collation_fix`), which makes it operationally significant on the fleet even
though it is silent. That is a reason to leave it entirely alone, not a reason to look
harder.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: N/A
- instance reachable via: N/A — singleton via `DS_DatabaseCollationFix::get_instance()`
  if it ever mattered. It does not

## Drift check

Re-check when a new version appears in the vault:

- Only two versions exist in the vault and the plugin is barely maintained. Any first
  appearance of a notice-hook registration, an outbound call, or a version-nag on its own
  update channel is the signal to re-read

## Verification

Source-verified against the vault release. No rule, so nothing to bench.

## Additions to `headwall-nag-cleanup.php`: NONE
