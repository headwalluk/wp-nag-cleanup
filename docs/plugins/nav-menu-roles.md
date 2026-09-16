# Nav Menu Roles

- slug: `nav-menu-roles`
- version analysed: `2.1.3`
- source: `/vault/backups/wordpress/plugins/nav-menu-roles/nav-menu-roles,2.1.3.zip`
- licensing: free (Kathy Darling)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

11 fleet installs. Nine PHP files. **No notice-hook registrations, no dashboard widget, no
outbound call, no tracking, no Freemius.**

The one promotional item in the plugin is a "Donate" link, and it is added to the plugin's
own row on the Plugins screen — not the notice area, not the dashboard.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **0** |
| `in_admin_header` / `admin_print_footer_scripts` registrations | **0** |
| Multiline `add_action(` form | **0** |
| Vendor opt-out filters | None promotional |
| Vendor opt-out constants | None |
| Dashboard widgets | **0** |
| Outbound calls | **0** |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| "Donate" link | `plugin_row_meta` → `Nav_Menu_Roles::add_action_links` (`inc/class-nav-menu-roles.php:282`) | **no rule: out of scope** | Plugin row meta on the Plugins screen. This project touches the notice area and the dashboard only |
| FAQ link | as above | keep | Documentation link, same row |

## Deliberately left alone

### The donate link is out of scope, not tolerated

`add_action_links()` appends a `DONATE_URL` link and an FAQ link to the plugin's row on
`plugins.php` via `plugin_row_meta`. It is promotional in the loosest sense — a solicited
donation for a free plugin — but it renders in the vendor's own plugin row, which is
neither surface this project claims.

Recorded explicitly because the string "Donate" trips a keyword scan and this is the only
plugin in the sweep where it does. The disposition is the same as User Switching's and
Query Monitor's plugin row meta: out of scope by construction.

### PayPal and Transifex URLs are readme and comment text

`paypal.me`, `paypal.com`, `transifex.com` and `kathyisawesome.com` appear in the readme,
in the plugin header, and in translator comments. None is an outbound request; the plugin
makes no HTTP calls at all.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: `plugin_row_meta`, registered in `Nav_Menu_Roles::__construct()`
- instance reachable via: N/A

## Drift check

Re-check when a new version appears in the vault:

- Only two versions are in the vault. Any first appearance of a notice-hook registration
  — most plausibly a donation or review prompt promoted out of the plugin row and into the
  notice area — is the signal to re-read

## Verification

Source-verified against the vault release. No rule, so nothing to bench.

## Additions to `headwall-nag-cleanup.php`: NONE
