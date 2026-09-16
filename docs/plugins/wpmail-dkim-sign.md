# WP Mail DKIM Sign

- slug: `wpmail-dkim-sign`
- version analysed: `1.0.0`
- source: `/vault/backups/wordpress/plugins/wpmail-dkim-sign/wpmail-dkim-sign,1.0.0.zip`
- licensing: free, **first-party** — written by Headwall Hosting and deployed to the fleet
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

15 fleet installs. This is **Headwall's own plugin**, swept alongside the third-party ones
rather than assumed clean — the point of the exercise is that a plugin with no nags is a
completed result, and that holds whoever wrote it.

**Nothing on any notice hook, no dashboard widget, no tracking, no promotional content.**
24 PHP files, all of the admin output confined to one options page.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **0** |
| `in_admin_header` / `admin_print_footer_scripts` registrations | **0** |
| Multiline `add_action(` form | **0** |
| Vendor opt-out filters | None needed; none present |
| Vendor opt-out constants | None |
| Dashboard widgets | **0** |
| Outbound calls | 1 — `wp_remote_get()` to `api.github.com` in `includes/class-github-updater.php:223`, the update check |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Settings-page notices | `admin-templates/partials/notice.php`, required from `settings-page.php:20` | keep | "Settings saved.", "Some inputs were not valid." and similar, rendered inside the plugin's own options page from a `NOTICE_*` identifier passed in a query arg. Not on a notice hook |
| GitHub update check | `wp_remote_get()` to `api.github.com`, transient-cached | keep | Update check. Operational by definition, and the plugin is distributed outside wordpress.org so it has no other update path |

## Deliberately left alone

### The notice partial is not on a notice hook

`admin-templates/partials/notice.php` looks like a finding in a file listing and is not.
It is `require`d from `settings-page.php` and renders a WordPress-styled block inside the
plugin's own options page, mapping a `NOTICE_*` constant from a query arg to a message.
Nothing registers it on `admin_notices`, so it never appears on any other screen.

### The update check is an update check

`class-github-updater.php` fetches
`https://api.github.com/repos/<repo>/releases/latest`, caches it in a transient, and
reports failures through `log_error()`. `CLAUDE.md` distinguishes update and licence
traffic — operational — from vendor news and catalogue fetches, which is what this project
removes. This is the former.

Recorded because the one thing `headwall-nag-cleanup` must never do is phone home, and a
future pass should not confuse *this* plugin's update check with that rule. They are
different plugins with different jobs.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: N/A — nothing on a notice hook
- instance reachable via: N/A

## Drift check

Re-check when a new version appears in the vault:

- This is first-party, so the drift check is really a design note: if a future version
  ever wants to tell a site owner something, it should go through the notice area only
  when the message is operational — a failed DKIM signature, a missing DNS record — and
  never for anything else. A first-party nag would be embarrassing and would still need a
  rule

## Verification

Source-verified against the vault release. No rule, so nothing to bench.

## Additions to `headwall-nag-cleanup.php`: NONE
