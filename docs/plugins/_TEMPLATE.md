# Plugin Name

- slug: `plugin-slug`
- version analysed: `0.0.0`
- source: `/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip`
- licensing: free | freemium | premium
- Freemius bundled: no | yes (SDK `x.y.z`)

## Analysis

Analysed on <D Mon YYYY> by Claude Code (Claude Opus 5).

<One paragraph: what this plugin does in the admin notice area and on the
dashboard. If nothing, say so plainly.>

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | |
| Vendor opt-out filters | |
| Vendor opt-out constants | |
| Dashboard widgets | |
| Outbound calls from widgets | |
| Freemius | |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| | | suppress / keep | |

## Deliberately left alone

<The notices and widgets found and NOT suppressed, each with the reason. This is
the audit trail for the boundary rule. If a rule was rejected as ambiguous, record
it here with what made it ambiguous — that stops it being re-litigated later.>

## Mechanism

- tier: 1 (vendor hook) | 2 (targeted unhook) | 3 (dashboard widget) | 4 (stored notification)
- phase: file scope | `init` | `admin_init` | `wp_dashboard_setup`
- vendor registers at: <where and when the vendor adds the hook>
- instance reachable via: <exact expression, for mechanism 2; N/A otherwise>

## Drift check

<How to tell this analysis has gone stale: the file and symbol to re-check when a
new version appears in the vault.>

## Verification

<State plainly which of these each rule is, per rule — never for the set as a whole:

- **Confirmed** — observed rendering before, and absent after. Say where and when
- **Source-verified only** — read from the vendor's source, not yet seen to work
- **Failed** — say so, diagnose it, and do not ship the rule as though it worked

A rule that was never observed rendering before the change is source-verified, not
confirmed: a rule that removes something already absent looks identical to one that works.

Name the surface each rule renders on, and check that surface. A clean notice area says
nothing about a dashboard widget, and nothing at all about anything rendered from a REST
route or by JavaScript — that gap cost a rule in 1.24.0.

Prefer structural probes to content greps: a meta box `id`, a `data-` attribute,
`has_action()`. Record the time gates a bench would have to backdate, and the negative
check — the operational notice that must still be there afterwards.>

## Additions to `headwall-nag-cleanup.php`: NONE | <summary>

```php
// The exact lines added, or omit this block when NONE.
```
