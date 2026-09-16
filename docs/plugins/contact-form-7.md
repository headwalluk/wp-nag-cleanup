# Contact Form 7

- slug: `contact-form-7`
- version analysed: `6.1.7`
- source: `/vault/backups/wordpress/plugins/contact-form-7/contact-form-7,6.1.7.zip`
- licensing: free (Rock Lobster Inc.; no paid tier, donations invited)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep of
high-install / low-suspicion plugins.

143 fleet installs, the largest install count on the fleet after Yoast. **Contact Form 7
registers nothing on any admin-notice hook — not one callback on `admin_notices`,
`network_admin_notices`, `all_admin_notices`, `in_admin_header` or
`admin_print_footer_scripts`, across 111 PHP files.**

It does have promotional content — a donation appeal and a cross-sell for the author's
own Flamingo plugin — but it lives in a welcome panel printed inside CF7's own admin
screen, which this project does not touch. There is nothing to suppress.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **0** |
| `in_admin_header` / `admin_print_footer_scripts` registrations | **0** |
| Multiline `add_action(` form | 34 hits, none on a notice hook |
| Vendor opt-out filters | 2, both operational: `wpcf7_admin_menu_change_notice` (menu bubble count) and `wpcf7_subscribers_only_notice` (front-end form message) |
| Vendor opt-out constants | None |
| Dashboard widgets | **0** |
| Outbound calls from widgets | N/A — no widgets. The 13 `wp_remote_*` calls are all configured service integrations (Stripe, Brevo/Sendinblue, reCAPTCHA, Turnstile, Constant Contact, Akismet) |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Welcome panel: "Contact Form 7 needs your support." | none — printed by `wpcf7_welcome_panel()` | **no rule: vendor's own screen** | Donation appeal. Rendered inside the CF7 admin page only |
| Welcome panel: Flamingo cross-sell | as above | **no rule: vendor's own screen** | Cross-promotes the author's other free plugin |
| Welcome panel: anti-spam and integration columns | as above | keep | Configuration guidance for features the site has |
| `wpcf7_admin_menu_change_notice` | admin menu bubble | keep | Count of configuration-validator alerts. Operational |
| Config validator warnings | `wpcf7_admin_warnings` (CF7's own action) | keep | Reports real mail-configuration faults |

## Deliberately left alone

### The welcome panel is promotional, and out of scope by construction

`admin/includes/welcome-panel.php` builds a four-column panel, two columns of which are
unambiguously promotional: `WPCF7_WelcomePanelColumn_Donation` ("It is hard to continue to
maintain this plugin without support from users like you") and
`WPCF7_WelcomePanelColumn_Flamingo`, which recommends installing Flamingo.

It is printed from `wpcf7_welcome_panel()`, called at `admin/admin.php:484` from inside
`wpcf7_admin_management_page()` — the render path for CF7's own screen, between CF7's own
`wpcf7_admin_warnings` and `wpcf7_admin_notices` actions. It is **not** on a core notice
hook and never appears on the dashboard, the plugins screen, or anywhere else.

`CLAUDE.md` puts vendor settings screens out of scope by construction, so no rule. This is
the same disposition as the Yoast WooCommerce cross-sell
(`docs/plugins/wordpress-seo.md`), reached for the same reason.

Worth noting for anyone tempted: the panel is already dismissible per major version,
storing `wpcf7_hide_welcome_panel_on` in user meta, so a site owner who does not want it
can close it and it stays closed until the next major release.

### Everything else is operational

CF7's own `wpcf7_admin_warnings` and `wpcf7_admin_notices` actions carry config-validator
output — mail configuration faults, invalid mailbox syntax, missing reCAPTCHA keys. All
of it reports true and actionable state, and none of it renders outside CF7's screens
anyway.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: N/A — nothing on a core notice hook
- instance reachable via: N/A

## Drift check

Re-check when a new version appears in the vault:

- The count that matters is zero. Any first appearance of
  `add_action( 'admin_notices'` (or the other four notice hooks) anywhere in the plugin
  is the signal to re-read — today there are none at all
- `admin/includes/welcome-panel.php` — if a column is ever moved onto a core notice hook
  or onto the dashboard, it becomes in scope immediately
- First appearance of `wp_add_dashboard_widget(` or a Freemius SDK

## Verification

Source-verified against the vault release. Nothing was installed or benched, because
there is no rule to test and nothing was found that renders outside the vendor's own
screens.

## Additions to `headwall-nag-cleanup.php`: NONE
