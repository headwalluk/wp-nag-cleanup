# Advanced Custom Fields (and ACF PRO)

- slug: `advanced-custom-fields`, `advanced-custom-fields-pro`
- version analysed: `6.8.9` (both)
- source: `/vault/backups/wordpress/plugins/advanced-custom-fields{,-pro}/…,6.8.9.zip`
- licensing: freemium (free on wordpress.org, PRO sold by WP Engine)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5).

ACF is on 106 fleet sites and ACF PRO on a further 29. **Neither puts anything
promotional in the WordPress admin notice area or on the dashboard.**

The audit specifically looked for upsell surfaces added since WP Engine acquired ACF,
since that was the reason to prioritise it. There is one — an email newsletter opt-in
banner — but it renders on `admin_footer`, only on ACF's own list screens. That is the
vendor's own interface and out of scope by construction, the same conclusion reached for
Yoast SEO on the same day.

Free and PRO are the same codebase plus a `pro/` directory. One document covers both.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 5 in free, 6 in PRO. **All operational** |
| Vendor opt-out filters | `acf/admin/show_email_opt_in_banner` (gates the newsletter banner), `acf/admin/prevent_escaped_html_notice` (gates a security notice — not touched) |
| Vendor opt-out constants | None |
| Dashboard widgets | **None** in either |
| Outbound calls from widgets | No widgets. The opt-in banner posts an email address, but only on submit |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Escaped HTML notice | `admin_notices` → `maybe_show_escaped_html_notice` | keep | Security warning about unescaped output in field values |
| Select2 v3 deprecation | `admin_notices` → `maybe_show_select2_v3_deprecation_notice` | keep | Deprecation warning |
| ACF notice framework | `admin_notices` 99 → `acf_render_admin_notices` | keep | Carries only operational output; see below |
| Database upgrade required | `admin_notices` / `network_admin_notices` → `admin-upgrade.php` | keep | **Database migration prompt. Never suppressed** |
| Legacy block version notice (PRO) | `admin_notices` → `acf_maybe_show_legacy_block_version_notice` | keep | Block compatibility warning |
| Email opt-in banner | `admin_footer` → `ACF_Admin_Email_Opt_In_Banner::render` | **no rule: vendor's own screens** | See below |

## Deliberately left alone

### The email opt-in banner — the one promotional surface

`includes/admin/admin-email-opt-in-banner.php` renders:

> **Join the ACF community.** Get critical ACF security updates, releases, news, and
> workflow improvements.

That is a newsletter sign-up, named on the suppress list in `CLAUDE.md`, and the vendor
even provides a clean opt-out: `apply_filters( 'acf/admin/show_email_opt_in_banner', $default )`.
A one-line mechanism 1 rule was available.

It was not taken, for two independent reasons, either of which is sufficient:

- **It is not in the admin notice area.** It hooks `admin_footer`, not `admin_notices`
- **It only appears on ACF's own screens.** `is_supported_screen()` allows exactly
  `edit-acf-field-group`, `edit-acf-post-type`, `edit-acf-taxonomy`, and the
  `acf_options_preview` submenu page. The class comments say so directly: *"Renders the
  ACF Free email opt-in banner on ACF admin screens"* and *"Hooks the banner onto ACF
  admin screens only"*

Confirmed by fetching three screens on a live install:

| Screen | Banner present |
|---|---|
| Dashboard (`index.php`) | no |
| Plugins (`plugins.php`) | no |
| `edit.php?post_type=acf-field-group` | **yes** |

A site owner sees this only after navigating into ACF's own field-group administration.
`CLAUDE.md` is explicit that the vendor's own interface is out of scope: *"This project
only ever touches the admin notice area and the dashboard."*

Recorded here rather than left implicit, because `acf/admin/show_email_opt_in_banner` is
exactly the sort of filter a future pass would find and be tempted by.

### The ACF notice framework carries nothing promotional

`acf_render_admin_notices` on `admin_notices` priority 99 renders whatever
`acf_add_admin_notice()` has queued. Every caller was read. All of them are operational:
import and export results ("No field groups selected", "Error uploading file. Please try
again", "Incorrect file type", "Import file empty"), local JSON sync confirmations, and
bulk action results on ACF's list screens.

No upsell, no review request, no newsletter prompt goes through it. Nothing to remove.

### The database upgrade notice

`includes/admin/admin-upgrade.php` registers on both `admin_notices` and
`network_admin_notices`. It is a database migration prompt and is first on the
never-suppress list. Untouched, and it must stay that way.

### `acf/admin/prevent_escaped_html_notice`

ACF exposes a filter that suppresses its escaped-HTML security warning. It is a vendor
opt-out gating an admin notice, so it will surface in any future search of this plugin.
**It must never be used.** The notice warns that field content is being output
unescaped, which is a security advisory about the state of the site.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: `ACF_Admin_Email_Opt_In_Banner::__construct` on `current_screen`,
  which then hooks `admin_footer`. The class self-instantiates at the bottom of its own
  file
- instance reachable via: not needed — `acf/admin/show_email_opt_in_banner` is a clean
  mechanism 1 filter. The rule was declined on scope, not reachability

## Drift check

Re-check when a new version appears in the vault:

- `includes/admin/admin-email-opt-in-banner.php` — `is_supported_screen()`. If ACF ever
  widens the banner beyond its own screens, or moves it to `admin_notices`, it becomes an
  in-scope target and `acf/admin/show_email_opt_in_banner` is the rule
- `includes/admin/admin-notices.php` — the `acf_add_admin_notice()` callers. If a
  promotional one appears, it would render globally at priority 99
- `includes/admin/admin-upgrade.php` — must remain untouched
- `acf/admin/prevent_escaped_html_notice` — must remain unused

## Verification

Tested on `bench2.local` (WP 7.1) with ACF 6.8.9 active, over authenticated admin
requests. No rule was deployed; the checks establish that none is needed.

| Check | Result |
|---|---|
| Opt-in banner on dashboard | absent |
| Opt-in banner on Plugins | absent |
| Opt-in banner on ACF Field Groups | present |
| `acf-admin-notice` markup on dashboard or Plugins | none |
| PHP fatals | 0 |

## Additions to `headwall-nag-cleanup.php`: NONE
