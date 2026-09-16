# WordPress Importer

- slug: `wordpress-importer`
- version analysed: `0.9.6`
- source: `/vault/backups/wordpress/plugins/wordpress-importer/wordpress-importer,0.9.6.zip`
- licensing: free (wordpressdotorg)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

13 fleet installs. 189 PHP files, most of them the bundled `php-toolkit` data-liberation
library. **No notice-hook registrations, no dashboard widget, no tracking, no promotional
content.** It is a wordpress.org-maintained importer and behaves like core.

The single outbound call is `wp_safe_remote_get()` in `class-wp-import.php:1398`, which
fetches remote attachments named in a WXR file during an import the site owner has
started.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **0** |
| `in_admin_header` / `admin_print_footer_scripts` registrations | **0** |
| Multiline `add_action(` form | **0** |
| Vendor opt-out filters | None promotional |
| Vendor opt-out constants | None |
| Dashboard widgets | **0** |
| Outbound calls | 1 — remote attachment fetch during an import, user-initiated |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Import progress and error output | printed inline on the importer screen | keep | Reports the result of an import the site owner just ran |

## Deliberately left alone

Nothing borderline was found.

Two keyword-scan artefacts are recorded so they are not re-investigated:

- The **`deal` / `dealer` / `deals` / `discount` hits** are entries in
  `php-toolkit/DataLiberation/URL/public-suffix-list.php` — the IANA public suffix list,
  which contains every gTLD. They are data, not copy
- The **`tracking` / `analytics` filename matches** in pass (f) are XML and block-markup
  processors whose comments mention tracking parameters in URLs. No analytics library is
  bundled

The importer prints its output directly on the Tools → Import screen rather than through
the notice area, so there is no notice surface here at all.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: N/A
- instance reachable via: N/A

## Drift check

Re-check when a new version appears in the vault:

- The plugin is being rewritten around the `php-toolkit` data-liberation library, so the
  file layout will churn. What matters stays the same: any first `add_action()` on a
  notice hook, and any outbound call that is not a user-initiated attachment fetch

## Verification

Source-verified against the vault release. No rule, so nothing to bench.

## Additions to `headwall-nag-cleanup.php`: NONE
