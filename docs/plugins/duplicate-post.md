# Yoast Duplicate Post

- slug: `duplicate-post`
- version analysed: `4.7` (vault also holds 4.5 and 4.6; the notice and its registration are identical in all three)
- source: `/vault/backups/wordpress/plugins/duplicate-post/duplicate-post,4.7.zip`
- licensing: free
- Freemius bundled: no

## Analysis

Analysed on 7 Oct 2026 by Claude Code (Claude Opus 5).

Paul reported it from a live site. `docs/plugins/wordpress-seo.md` had flagged it as
needing its own audit, and the audit had not been done.

Duplicate Post has one promotional surface. It is a large welcome notice headed **"You've
successfully installed Yoast Duplicate Post!"**, and its body is a Yoast newsletter
sign-up form (`Newsletter::newsletter_signup_form()`). It has an email field and a
Subscribe button that posts to `https://my.yoast.com/api/Mailing-list/subscribe`. It shows
on the Dashboard and the Plugins screen. It carries nothing operational: there is no
version information, no changelog and no setup step, only the title and the form.

It does **not** use Yoast SEO's notification centre (`Yoast_Notification_Center` appears
nowhere in it), so nothing in the Yoast SEO analysis covers it.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 9 single-line hits. `duplicate_post_show_update_notice` on `admin_notices` or `network_admin_notices` (`admin-functions.php:51,54`), and 7 watcher callbacks in `src/watchers/`. Multiline pass found nothing. No `all_admin_notices`, `in_admin_header` or `admin_print_footer_scripts` |
| Vendor opt-out filters | **None**. `duplicate_post_show_link` controls the copy links, not the notice |
| Vendor opt-out constants | **None** |
| Dashboard widgets | **None** |
| Outbound calls | One: `Newsletter::newsletter_subscribe_to_mailblue()` posts the entered email to `my.yoast.com`. It runs only when the form is submitted, and the form exists only inside the notice |
| Freemius | Not bundled |

## Findings

| Item | Hook / callback | Verdict | Reason |
|---|---|---|---|
| Welcome notice with newsletter sign-up | `admin_notices` 10 (single site) or `network_admin_notices` 10 (multisite) → `duplicate_post_show_update_notice` | **suppress** | A newsletter sign-up form with a congratulations heading. No operational content |
| Bulk clone result | `Bulk_Actions_Watcher::add_bulk_clone_admin_notice` | keep | Result of the admin's own action |
| Bulk Rewrite & Republish result | `Bulk_Actions_Watcher::add_bulk_rewrite_and_republish_admin_notice` | keep | Result of the admin's own action |
| Clone / R&R link results | `Link_Actions_Watcher::add_clone_admin_notice`, `::add_rewrite_and_republish_admin_notice` | keep | Result of the admin's own action |
| Original-post changed | `Original_Post_Watcher::add_admin_notice` | keep | Warns that the original changed while a Rewrite & Republish copy was being edited. Operational |
| Copy-of-post notice | `Copied_Post_Watcher::add_admin_notice` | keep | Says the post being edited is a Rewrite & Republish copy, and of what. Operational |
| Republished notice | `Republished_Post_Watcher::add_admin_notice` | keep | Confirms a Rewrite & Republish completed. Operational |

### Why it lingers

The gate is `get_site_option( 'duplicate_post_show_notice' )`. `duplicate_post_plugin_upgrade()`
sets it to 1 **only on a fresh install** (an upgrade sets 0), and only the AJAX dismiss sets
it back. Two details keep it alive on real sites:

- The dismiss script binds to **every** `.notice-dismiss` on the page
  (`jQuery('body').on('click', '.notice-dismiss', …)`). Dismissing any other notice also
  dismisses this one, but closing this one by itself is easy to miss, and nothing else
  ever clears it
- The settings screen has a "Show welcome notice" checkbox (Settings → Duplicate Post →
  Display). Few site owners will look there

## Deliberately left alone

- **All seven watcher notices** (table above). They report the admin's own actions, or the
  state of a Rewrite & Republish copy
- **`plugin_row_meta` links** (`duplicate_post_add_plugin_links`). These are on the vendor's
  own Plugins-screen row. Out of scope, as for User Switching, Query Monitor and Nav Menu Roles
- **The "Show welcome notice" setting**. The vendor's own settings screen is out of scope.
  The rule does not read or write it

## Mechanism

- tier: 2 (targeted unhook, **by name**)
- phase: `admin_init` at `self::LATE_PRIORITY`
- vendor registers at: `duplicate_post_admin_init()`, itself on `admin_init` at the default
  priority (`admin-functions.php:39`). It adds the notice only when the option is 1, and only
  on one hook: `network_admin_notices` when `is_multisite()`, `admin_notices` otherwise.
  `admin-functions.php` is included only when `is_admin()`
- instance reachable via: N/A. It is a plain global function

```php
remove_action( 'admin_notices', 'duplicate_post_show_update_notice' );
remove_action( 'network_admin_notices', 'duplicate_post_show_update_notice' );
```

Each removal is behind `has_action()`, so a site that has dismissed the notice logs nothing.

**Not used: answering the gate through `pre_site_option_duplicate_post_show_notice`.** That
would write nothing either, but the vendor's settings screen renders the same option as its
"Show welcome notice" checkbox. A forced 0 would show there as unchecked, and the next
settings save would write it back. That reaches into the vendor's settings screen, which is
out of scope by construction. Unhooking leaves the option and the checkbox exactly as the
site owner set them.

The newsletter form's POST handler (`newsletter_handle_form()`) is called from inside the
form render. With the notice unhooked it never runs, so no request goes to `my.yoast.com`.

## Drift check

- `admin-functions.php`: the two conditional `add_action(…, 'duplicate_post_show_update_notice')`
  lines inside `duplicate_post_admin_init()`. If either gains a priority, or if
  `duplicate_post_admin_init` moves to a priority above 999, the rule stops matching. The
  `has_action()` guard means the debug line disappears on a site with an undismissed notice
- `duplicate_post_show_update_notice()`: if it ever gains a changelog or upgrade content
  alongside the form, it becomes mixed-output and the rule must be withdrawn. The function
  is still named `_update_notice` but carries no update content in 4.5 to 4.7, the only
  releases in the vault

## Verification

Bench: `bench2.local`, WP 7.1.3, PHP 8.5, Duplicate Post 4.7 freshly installed, 7 Oct 2026.

**No time gate.** A fresh install sets `duplicate_post_show_notice` to 1 on the first
`admin_init`, so the notice appears on the next admin page load.

### Welcome notice — **Confirmed (bench)**

Probes are the `id="duplicate-post-notice"` wrapper and the `id="newsletter-subscribe-form"`
form. Each screen was asserted structurally.

| Screen | Assertion | Before (1.36.0) | After (1.38.0) |
|---|---|---|---|
| `index.php` | `id="dashboard-widgets"` | notice 1, form 1 | **0, 0** |
| `plugins.php` | `id="the-list"` | notice 1, form 1 | **0, 0** |

The inline dismiss script (`duplicate_post_dismiss_notice`) is gone too: it is printed from
inside the same callback. The debug log reads
`duplicate-post: Removed duplicate_post_show_update_notice from admin_notices.`.

Nothing was written: `duplicate_post_show_notice` was still `1` after the run.

Multisite (`network_admin_notices`) is source-verified only.

### Negative checks

| Check | Result |
|---|---|
| Dashboard, Plugins and Posts screens render | 200, screen assertions pass |
| Plugin's own UI on `edit.php` | Bulk Clone and Bulk Rewrite & Republish actions present. The bench has no posts, so the row links were not exercised |
| PHP fatals / warnings | **0** |

**The plugin has no uninstaller.** Deleting it leaves 25 `duplicate_post_*` options and the
`copy_posts` capability on editor and administrator. The bench was restored by deleting
exactly the options new since the snapshot and restoring `wp_user_roles` from its saved
JSON. Tables, options, cron, users and roles then diffed clean.

### Live — welcome notice **Confirmed**, 7 Oct 2026

Paul deployed the rule to production and confirmed that the newsletter welcome notice was
gone. Multisite remains source-verified only.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_vendor_notices():
$this->unhook_duplicate_post_newsletter_notice();
```
