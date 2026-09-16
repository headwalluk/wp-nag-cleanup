# Alt Text Fixer

- slug: `alt-text-fix`
- version analysed: `1.6`
- source: `/vault/backups/wordpress/plugins/alt-text-fix/alt-text-fix,1.6.zip`
- licensing: free (Cookehouse)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

9 fleet installs, the smallest in the sweep. **One PHP file, and every pass returned
empty.** No notice-hook registration, no dashboard widget, no outbound call, no tracking,
no Freemius, no promotional string.

The plugin copies image titles into the Alternative Text field from a single settings page
registered with `add_options_page()`, and says nothing anywhere else.

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

Nothing was found to leave alone. The two `cookehouse.net` URLs are the plugin and author
URI headers.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: N/A
- instance reachable via: N/A

## Drift check

Re-check when a new version appears in the vault:

- One version only, and a single-file plugin. Any growth at all — a second file, a notice
  registration, an SDK — means re-reading it from scratch, which costs minutes

## Verification

Source-verified against the vault release. No rule, so nothing to bench.

## Additions to `headwall-nag-cleanup.php`: NONE
