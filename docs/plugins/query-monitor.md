# Query Monitor

- slug: `query-monitor`
- version analysed: `4.0.7`
- source: `/vault/backups/wordpress/plugins/query-monitor/query-monitor,4.0.7.zip`
- licensing: free (John Blackbourn)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

53 fleet installs. 150 PHP files and **three notice registrations, all three of which fire
only when Query Monitor itself is broken or unusable**: PHP too old, Composer
dependencies missing, built assets missing. Nothing promotional anywhere — no upsell, no
review request, no dashboard widget, no outbound call, no tracking.

Query Monitor is also the one plugin in this sweep that *reads* the notice hooks: its
Admin collector records what is registered on them so a developer can see it. That is
introspection, not output.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 3 — `QM_PHP::php_version_nope`, `QM_PHP::vendor_nope` (twice: plugin bootstrap and the `db.php` drop-in), `QM_Dispatcher_Html::admin_notice_missing_assets` |
| `in_admin_header` / `admin_print_footer_scripts` registrations | 1 — `QM_Collector_Assets::action_print_footer_scripts` at 9999, which collects enqueued assets and removes itself again at line 78. Not output |
| Multiline `add_action(` form | **0** |
| Vendor opt-out filters | 2, neither promotional: `qm/trace/show_args`, `qm/show_extended_query_prompt` |
| Vendor opt-out constants | None of the promotional kind |
| Dashboard widgets | **0** |
| Outbound calls | **0**. The `wp_safe_remote_*` hits in `collectors/http.php` are a lookup table of function names the HTTP collector recognises, not calls |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| "Query Monitor requires PHP version X" | `all_admin_notices` → `QM_PHP::php_version_nope` | **keep — never suppress** | PHP version warning. First class on the never-suppress list |
| "Query Monitor's dependencies are missing" | `all_admin_notices` → `QM_PHP::vendor_nope` | keep | Broken install: `vendor/autoload.php` absent |
| "Query Monitor's assets are missing" | `admin_notices` → `admin_notice_missing_assets` | keep | Broken install: the built JS/CSS is absent, usually a git checkout without a build |
| Plugin row meta links | `plugin_row_meta` | keep | Documentation and GitHub links on the Plugins screen. Out of scope |

## Deliberately left alone

### All three notices are broken-install warnings

Two of them (`php_version_nope`, `vendor_nope`) are registered in the plugin bootstrap
*instead of* loading the plugin — `query-monitor.php` lines 45 and 50 sit in the failure
branches of version and autoload checks, so if either notice renders, Query Monitor is not
running at all. The third reports that the dispatcher cannot find its built assets.

Suppressing any of them would leave a site owner with a plugin that silently does nothing.
`CLAUDE.md` names PHP version warnings and broken-install notices as never-suppress, and
these are both.

`vendor_nope` is registered twice — once from the plugin and once from the `wp-content/db.php`
drop-in — which is correct, because the drop-in loads on requests where the plugin has not.

### The Admin collector reads the notice hooks; it does not write to them

`collectors/admin.php` lines 31–35 list `admin_notices`, `all_admin_notices`,
`in_admin_header`, `network_admin_notices` and `user_admin_notices`. This is Query Monitor
enumerating what *other* plugins have registered, for its own Admin panel. A keyword scan
flags it; reading it clears it. Recorded here so the next pass does not re-open it.

It is also, incidentally, the best tool on the fleet for verifying this project's own
rules — `has_action()` counts without writing a probe.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: file scope, inside failure branches of the bootstrap checks
- instance reachable via: N/A — all three callbacks are static (`QM_PHP::…`,
  `QM_Dispatcher_Html::…`) and would be trivially unhookable if one ever needed to be.
  None does

## Drift check

Re-check when a new version appears in the vault:

- `query-monitor.php` — the notice count is 3 and has been for the plugin's whole life in
  the vault. A fourth registration is the signal to re-read, particularly any registration
  outside a failure branch
- First appearance of a dashboard widget, a `wp_remote_*` call, or a sponsorship or
  funding notice — the author funds the plugin through GitHub Sponsors, currently
  mentioned only in the readme and the plugin row meta

## Verification

Source-verified against the vault release. No rule, so nothing to bench.

## Additions to `headwall-nag-cleanup.php`: NONE
