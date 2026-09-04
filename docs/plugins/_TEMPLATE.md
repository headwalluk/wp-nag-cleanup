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

- tier: 1 (vendor hook) | 2 (targeted unhook) | 3 (dashboard widget)
- phase: file scope | `init` | `admin_init` | `wp_dashboard_setup`
- vendor registers at: <where and when the vendor adds the hook>
- instance reachable via: <exact expression, for mechanism 2; N/A otherwise>

## Drift check

<How to tell this analysis has gone stale: the file and symbol to re-check when a
new version appears in the vault.>

## Additions to `headwall-nag-cleanup.php`: NONE | <summary>

```php
// The exact lines added, or omit this block when NONE.
```
