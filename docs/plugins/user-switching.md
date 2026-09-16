# User Switching

- slug: `user-switching`
- version analysed: `1.12.2`
- source: `/vault/backups/wordpress/plugins/user-switching/user-switching,1.12.2.zip`
- licensing: free (John Blackbourn)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

57 fleet installs. One PHP file. **One notice-hook registration, and it is one of the most
operational notices any plugin on the fleet emits** — the banner telling an administrator
they are currently switched into another user's account, with the link to switch back.

Nothing promotional anywhere: no upsell, no review request, no tracking, no dashboard
widget, no outbound call.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 1 — `all_admin_notices` priority **1**, `User_Switching::action_admin_notices` |
| `in_admin_header` / `admin_print_footer_scripts` registrations | **0** |
| Multiline `add_action(` form | **0** |
| Vendor opt-out filters | None promotional. The two that touch admin output are `user_switching_switched_message` (line 545, the wording of the operational banner) and `user_switching_in_footer` (line 797, the switch-back link in the admin footer) |
| Vendor opt-out constants | None |
| Dashboard widgets | **0** |
| Outbound calls | **0** |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| "Switched to <user>. Switch back to <you>?" | `all_admin_notices` 1 → `action_admin_notices` | **keep — never suppress** | Reports the current session identity. Hiding it would leave an administrator acting as another user with no indication |
| Plugin row meta links | `plugin_row_meta` | keep | Support and donate links on the Plugins screen. Out of scope: this project touches the notice area and the dashboard only |

## Deliberately left alone

### The switched-user banner is the opposite of a nag

`action_admin_notices()` (`user-switching.php:497`) renders only when
`self::get_old_user()` returns a `WP_User` — that is, only while a switch is actually in
effect. It reports session state that exists nowhere else in the interface, and the
"Switch back" link inside it is the documented way out of the switch.

It registers at **priority 1** on `all_admin_notices`, which is worth noting because this
project's mechanism 4 pass also runs at `EARLY_PRIORITY` (1) on that hook. There is no
conflict — the plugin's ordering is deliberate so the banner sits above other notices, and
nothing in this project touches it.

Suppressing it would be dangerous in exactly the way `CLAUDE.md` describes: it is true,
actionable, and the site owner needs it.

### The plugin row meta is out of scope, not overlooked

`filter_plugin_row_meta()` adds support and donate links to the plugin's own row on the
Plugins screen. That is neither the notice area nor the dashboard, so it is out of scope
by construction — recorded here so the next pass does not re-open it.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: `plugins_loaded` → `User_Switching::action_init`, then
  `all_admin_notices` priority 1
- instance reachable via: N/A. The callback is `[ $this, … ]` on a discarded instance, so
  a rule would need the sanctioned `$wp_filter` reader — recorded only to note that it
  would be available if one were ever warranted. None is

## Drift check

Re-check when a new version appears in the vault:

- `user-switching.php` — first appearance of a second notice registration, a dashboard
  widget, or any `wp_remote_*` call. The plugin has had none of these for its whole life
  in the vault

## Verification

Source-verified against the vault release. No rule, so nothing to bench. The negative
check, if a rule ever were proposed here, is that the switched-user banner must still
render while a switch is in effect.

## Additions to `headwall-nag-cleanup.php`: NONE
