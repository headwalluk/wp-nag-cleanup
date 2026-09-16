# Classic Editor

- slug: `classic-editor`
- version analysed: `1.7.0`
- source: `/vault/backups/wordpress/plugins/classic-editor/classic-editor,1.7.0.zip`
- licensing: free (WordPress Contributors)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

33 fleet installs. One PHP file. **Classic Editor registers nothing on any notice hook.
The only notice-hook lines in the plugin are `remove_action()` calls** — it removes
Gutenberg's own notices when the Gutenberg plugin is active, which makes it the one
plugin in this sweep that does a small amount of this project's job for it.

No promotional content of any kind: no upsell, no review request, no dashboard widget, no
outbound call, no tracking. It is maintained by the WordPress contributors and behaves
like core.

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
| `remove_action( 'admin_notices', 'gutenberg_wordpress_version_notice' )` | line 168 | keep (not ours) | Classic Editor removing Gutenberg's version notice when both are active |
| `remove_action( 'admin_notices', 'gutenberg_build_files_notice' )` | line 175 | keep (not ours) | As above, Gutenberg's build-files warning |
| `remove_action( 'admin_notices', [ 'WP_Privacy_Policy_Content', 'notice' ] )` | line 950 | keep (not ours) | Core's privacy-policy notice, **relocated not removed** — the next line re-adds the same callback to `edit_form_after_title`, moving it under the title field on the classic post screen |

## Deliberately left alone

There is nothing of this plugin's own to leave alone.

The three `remove_action()` calls above are worth recording because a keyword scan flags
them and they are the opposite of a finding: Classic Editor is *removing* notices, not
adding them. Two belong to the Gutenberg feature plugin and one to core's privacy tool.

The privacy one is not even a removal. `on_admin_init()` bails unless `$pagenow` is
`post.php`, then unhooks `WP_Privacy_Policy_Content::notice` from `admin_notices` and
immediately re-adds the same callback to `edit_form_after_title` — the notice still
renders, just under the title field instead of at the top of the screen. Read the line
after a `remove_action()` before recording it as a suppression.

This project does not interact with any of them, and must not: re-adding a notice another
plugin deliberately removed is not something this project does, in either direction.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: N/A
- instance reachable via: N/A

## Drift check

Re-check when a new version appears in the vault:

- The plugin is effectively feature-frozen and maintained by core contributors, with
  support committed to 2026 and beyond. Any first appearance of an `add_action()` on a
  notice hook would be a change of character — most plausibly an end-of-life
  announcement, which would be operational and would stay

## Verification

Source-verified against the vault release. No rule, so nothing to bench.

## Additions to `headwall-nag-cleanup.php`: NONE
