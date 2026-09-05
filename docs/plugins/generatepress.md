# GeneratePress family — GenerateBlocks, GenerateBlocks Pro, GP Premium

- slug: `generateblocks`, `generateblocks-pro`, `gp-premium`
- version analysed: `2.4.1`, `2.7.1`, `2.5.6`
- source: `/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip`, cross-checked
  against the licensed install at `/var/www/devx.headwall.tech/web` (read only)
- licensing: freemium (GenerateBlocks free; GenerateBlocks Pro and GP Premium are paid)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5).

91 fleet installs across the three. **Nothing promotional in the admin notice area, on
the dashboard, or anywhere else this project looks.** No dashboard widgets, no review
requests, no upsell banners, no usage tracking, no newsletter prompts, no Freemius.

This is the cleanest result of the day and, unusually, there is nothing to argue about:
every one of the nine `admin_notices` registrations across the three plugins reports a
real condition of the site. Tom Usborne does not use the admin notice area to sell
things.

One document covers all three, in the manner of the YITH and Brainstorm Force audits,
because the finding is identical for each and the vendor is the same.

### Search checklist

| Pass | Result | | |
|---|---|---|---|
| | `generateblocks` | `generateblocks-pro` | `gp-premium` |
| `admin_notices` registrations | 1 | 3 | 5 |
| Promotional among them | **0** | **0** | **0** |
| Dashboard widgets | 0 | 0 | 0 |
| Outbound calls | 1 | 5 | 6 — all licence/update API to `generatepress.com` |
| Freemius | no | no | no |
| Vendor opt-out filters for promo | none needed; the filters present are feature toggles (`generate_dashboard_tabs`, `generateblocks_show_incompatible_global_styles` and similar) | | |

## Findings

| Item | Plugin | Verdict | Reason |
|---|---|---|---|
| Dynamic data disabled notice | `generateblocks` | keep | Security: dynamic tag output is disabled for a post. Only renders on post edit screens |
| Failed to load | `generateblocks-pro` | keep | Dependency failure |
| Required version not met | `generateblocks-pro` | keep | Version mismatch with the free plugin |
| Form schema builder notices | `generateblocks-pro` | keep | Operational form configuration errors |
| PHP version check | `gp-premium` | keep | **PHP version warning. Never suppressed** |
| Hooks admin errors | `gp-premium` | keep | "Hooks saved." confirmation, scoped to the Hooks settings screen |
| Module activated / deactivated | `gp-premium` | keep | Confirms an action the user just took |
| Licence errors | `gp-premium` | keep | **Licence state. Never suppressed** |
| GeneratePress theme information | `gp-premium` | keep | **A theme update is available.** Never suppressed |

## Deliberately left alone

There is no borderline case here, which is itself worth recording. The three surfaces
that might have looked like candidates on a keyword scan are all operational:

- **`generate_premium_theme_information`** sounds promotional and is not. It reads the
  `update_themes` transient and tells the site owner a GeneratePress update exists,
  deliberately staying quiet on the Themes screen where WordPress already says so
- **`generate_license_errors`** is a licence notice, first on the never-suppress list. It
  is also the reason a fully licensed site sees fewer GP notices than an unlicensed one —
  which is the correct behaviour, not something to work around
- **`generate_premium_notices`** is a pair of "Module activated." / "Module deactivated."
  confirmations rendered through `add_settings_error`

The outbound calls are licence validation and update checks against `generatepress.com`.
Update and licence traffic is operational by definition; this project removes vendor
*news and catalogue* fetches, not update checks.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: file scope and `plugins_loaded` in each plugin, using named
  function strings such as `'generate_license_errors'`
- instance reachable via: N/A. Every callback is a named function, so any of these would
  be a trivial unhook if one were ever warranted. None is

## Drift check

Re-check when a new version appears in the vault:

- `gp-premium/gp-premium.php` — `generate_premium_theme_information()`. If it starts
  advertising rather than reporting an available update, revisit
- Any new `add_action( 'admin_notices', … )` in any of the three. The current count is
  1 / 3 / 5; a jump is the signal to re-read
- First appearance of a dashboard widget, a Freemius SDK, or an analytics library in any
  of the three

## Verification

Source-verified against the vault releases and cross-checked against the **licensed**
GeneratePress install on `devx.headwall.tech`, which runs the same versions
(`generateblocks` 2.4.1, `gp-premium` 2.5.6, GeneratePress theme 3.6.1) and registers the
same five `gp-premium` notices. The licensed reference matters here: it confirms the
audit reflects what a paying site actually runs, not just an unlicensed copy.

Not installed on `bench2.local` — there was nothing to suppress and therefore nothing to
test at runtime.

## Additions to `headwall-nag-cleanup.php`: NONE
