# WP Mail Bank (Tech Banker)

- slug: `wp-mail-bank`
- version analysed: `4.0.14`
- source: `/vault/backups/wordpress/plugins/wp-mail-bank/wp-mail-bank,4.0.14.zip`
- licensing: freemium (free on wordpress.org, Premium Editions sold at tech-banker.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a live
client site — a "Leave a 5 Star Review" banner.

**1 fleet site** (hhw6). The smallest reach of any rule so far, and it goes in anyway
because the nag is unusually intrusive: it styles itself with core's own `update-nag`
class.

**One rule added**, mechanism 2. Two operational notices and one mixed dashboard widget are
left alone.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | **3** — one review nag, one database upgrade prompt, one plugin conflict notice. A clean split, one callback each |
| Vendor opt-out filters | **None.** Only 3 `apply_filters` in the whole plugin, all core `wp_mail` hooks |
| Vendor opt-out constants | None |
| Dashboard widgets | **1**: `mb_dashboard_widget`, "Mail Bank Statistics". **Mixed output — left alone** |
| Outbound calls from widgets | None. The widget queries local tables only |
| Freemius | Not bundled |
| Shared vendor framework | **None.** A sweep of every vault zip for "Tech Banker" matched only `wp-mail-bank`, and the fleet carries no other Tech Banker slug. Unlike YITH's `plugin-fw` or the WP Desk Composer packages, this rule buys exactly one plugin |

## Findings

| Item | Hook / callback | Verdict | Reason |
|---|---|---|---|
| "Leave a 5 Star Review" | `admin_notices` → `Mail_Bank_Admin_Notices::mb_display_admin_notices` | **suppress** | Review begging, on every admin screen, wearing core's `update-nag` class |
| Database upgrade prompt | `admin_notices` → `upgrade_database_admin_notice` | keep | **Database migration prompt. Never suppressed** |
| SMTP plugin conflict warning | `admin_notices` → `display_admin_notice_mail_bank` | keep | **Plugin conflict notice. Never suppressed** |
| "Mail Bank Statistics" widget | `wp_dashboard_setup` → `mb_dashboard_widget` | keep — **mixed** | See below |

## Deliberately left alone

### The database upgrade prompt

`upgrade_database_admin_notice()` counts rows in `{prefix}mail_bank_email_logs` and, if
there are any, prints:

> **Important Announcement - Mail Bank?** We have made imminent changes to our Database to
> improve the Performance. You would need to update the Database to view prior Email
> Reports. … **Update Database!**

A data migration prompt with a nonced action button, named explicitly on the never-suppress
list. It is registered conditionally — only when `get_option( 'mail_bank_update_database' )`
is false — so on a migrated site it never hooks at all.

Note it also uses the bare `update-nag` class. That is not a reason to touch it.

### The SMTP conflict warning

`display_admin_notice_mail_bank()` checks fifteen named SMTP and email-logging plugins with
`is_plugin_active()` and, for any it finds, prints a warning naming each one with a
Deactivate button:

> WP Mail Bank has detected the following plugins are activated. Please deactivate them to
> prevent conflicts.

Two SMTP plugins fighting over `phpmailer_init` is a real way to stop a site sending mail
at all. This is a plugin conflict notice, on the never-suppress list, and for a hosting
fleet it is exactly the kind of thing that must survive. Verified still rendering — see
Verification below.

### The dashboard widget is mixed output

`mb_dashboard_widget` renders a table of Sent / Not Sent counts for today, this week, last
month and this year, read from `{prefix}mail_bank_logs`, with each figure linking through to
the email log. For a mail plugin, a "Not Sent" count is operational information of the first
order.

The last row of that table is:

```php
<a href="https://tech-banker.com/wp-mail-bank/">
    <strong>Upgrade Now to Premium Editions</strong>
</a>
```

One upsell row appended to a table of real delivery data. Removing the meta box to hide it
would take the delivery figures with it. Mixed output, so no rule — the same call made for
CookieYes's consent widget and AIOSEO's TruSEO Overview.

The widget makes no outbound request, so the usual second argument for mechanism 3 does not
apply here either.

### There is no vendor opt-out to use

The plugin has a `disable_admin_notices` key in its `mb_admin_notice` option, read by
`Mail_Bank_Admin_Notices::mb_admin_notices()`:

```php
$settings = get_option( 'mb_admin_notice' );
if ( ! isset( $settings['disable_admin_notices'] ) || ( isset( $settings['disable_admin_notices'] ) && 0 === $settings['disable_admin_notices'] ) ) {
```

Setting it would work and would be tempting. It is an **option write**, which this project
does not do — the same reasoning that rejected writing Premium Addons' three dismissal
options. It would also disable the notice system wholesale rather than the review nag
specifically, and the review nag is the only thing that system carries.

There are three `apply_filters` calls in the entire plugin, all of them core's `wp_mail`
and `wp_mail_content_type`. No filter, no constant.

## Mechanism

One rule, tier 2, via the sanctioned `$wp_filter` reader.

### Why the reader is needed

`Mail_Bank_Admin_Notices` is declared **inside a function**, and instantiated into a local:

```php
function mail_bank_admin_notice_class() {
    class Mail_Bank_Admin_Notices {
        public function __construct( $config = array() ) {
            add_action( 'admin_init', array( $this, 'mb_admin_notice_ignore' ) );
            add_action( 'admin_init', array( $this, 'mb_admin_notice_temp_ignore' ) );
            add_action( 'admin_notices', array( $this, 'mb_display_admin_notices' ) );
        }
        // …
    }
    $plugin_info_mail_bank = new Mail_Bank_Admin_Notices();
}
add_action( 'init', 'mail_bank_admin_notice_class' );
```

`$plugin_info_mail_bank` is a **local variable**, not a global — it goes out of scope the
moment the function returns, leaving the object reachable only through `$wp_filter`. There
is no singleton accessor and no registry. That is precisely the case
`remove_discarded_instance_callback()` exists for.

Unlike Converter for Media, no disambiguation is needed: `mb_display_admin_notices` is the
only method of that class on `admin_notices`, and there is only one instance.

### The callback is atomic in the right direction

`mb_display_admin_notices()` builds exactly one notice — `two_week_review` — and passes it
to the renderer. It does not dispatch anything operational, because the vendor put the
database prompt and the conflict warning in separate global functions on the same hook.
Removing this one callback therefore costs nothing operational.

That is a better-structured plugin than several audited today, and it is what made this a
three-line rule.

### Phase

`admin_init`, `LATE_PRIORITY` (999). The class is declared and constructed from
`mail_bank_admin_notice_class()` on `init` priority 10, so by `admin_init` both
`class_exists()` and the hook entry are in place.

### The dismiss handlers stay

`mb_admin_notice_ignore` and `mb_admin_notice_temp_ignore` remain on `admin_init`. They are
inert once the notice never renders, and removing them would break dismissal for any notice
the vendor adds later.

### On the `update-nag` class

The nag renders as `<div class="update-nag mb-admin-notice">`. `update-nag` is WordPress
core's class for "please update WordPress" prompts — the yellow-bordered bar at the top of
the screen. Borrowing it makes a review request inherit the visual weight of a core update
notice. It has no bearing on the mechanism, since the rule targets the callback rather than
the markup, but it is worth recording: it is the clearest example yet of a vendor
deliberately dressing promotion as system output.

## Drift check

Re-check when a new version appears in the vault:

- `wp-mail-bank.php` — the class name `Mail_Bank_Admin_Notices` and the method
  `mb_display_admin_notices`, copied case-exactly. Both are matched by string
- **`mb_display_admin_notices()` must stay a review-nag-only builder.** If the vendor moves
  the database prompt or the conflict warning into it, the callback becomes mixed and this
  rule must come out. This is the single most important line to re-read
- `lib/dashboard-widget.php` — if the upsell row grows to dominate the widget, or the
  delivery statistics move elsewhere, the mixed-output verdict changes
- Any new `apply_filters` around the notice, which would demote this to mechanism 1

Only one version (4.0.14) is held in the vault, so there is no drift history to compare
against.

## Verification

Tested on `bench2.local` (WP 7.1) with WP Mail Bank 4.0.14 active, over authenticated admin
requests, A/B against v1.20.0 (no rule) and v1.21.0.

The nag is time-gated — the install script writes
`mb_admin_notice.two_week_review.start` seven days ahead — so `start` was backdated to
`08/01/2026` on the bench to make it render.

| Check | Rules off | Rules on |
|---|---|---|
| `mb-admin-notice` on the dashboard | **1** | **0** |
| `wp-mail-bank/reviews` link on the dashboard | **1** | **0** |
| `mb-admin-notice` on `plugins.php` | **1** | **0** |
| `wp-mail-bank/reviews` link on `plugins.php` | **1** | **0** |
| `id="mb_dashboard_widget"` | 1 | **1** — preserved |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |
| `index.php`, `plugins.php`, `options-general.php`, `edit.php`, `upload.php` | HTTP 200 | HTTP 200 |

The important check is the conflict notice. Easy WP SMTP 2.15.1 — one of the fifteen
plugins `display_admin_notice_mail_bank()` looks for — was activated on the bench, and the
dashboard re-requested **with the rules on**:

| Check, rules on, conflicting plugin active | Result |
|---|---|
| `tech-banker-compatiblity-warning` present | **1** |
| *"Please deactivate them to prevent conflicts"* | **1** |
| "Easy WP SMTP" named in the notice | **present** |
| `wp-mail-bank/reviews` review nag | **0** |

The conflict warning and the review nag were on the same request; one survived and one did
not. Easy WP SMTP was deactivated afterwards.

Debug log with `HEADWALL_NAG_CLEANUP_DEBUG` on:

```
[headwall-nag-cleanup 1.21.0] wp-mail-bank: Removed Mail_Bank_Admin_Notices::mb_display_admin_notices from admin_notices priority 10.
```

The database upgrade prompt could not be observed — it needs rows in
`{prefix}mail_bank_email_logs`, which a bench install with no sent mail does not have. It is
a separate global function on `admin_notices` and nothing in the rule touches it.

### An unrelated bug noticed on the bench

`wp-mail-bank.php:523` calls `apply_filters( 'wp_mail', 'wp_mail' )` — passing a **string**
where core passes an array of `to`, `subject`, `message`, `headers` and `attachments`. Any
other plugin filtering `wp_mail` and expecting core's contract gets a string instead. Not
this project's problem and not something a nag rule should touch, but recorded here because
it surfaced during testing and is a genuine defect in the plugin.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
public function unhook_mail_bank_review_notice() : void {
	$this->remove_discarded_instance_callback( 'admin_notices', 'Mail_Bank_Admin_Notices', 'mb_display_admin_notices', 'wp-mail-bank' );
}
```
