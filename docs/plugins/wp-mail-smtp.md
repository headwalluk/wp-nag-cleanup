# WP Mail SMTP

- slug: `wp-mail-smtp`
- version analysed: `4.9.0`
- source: `/vault/backups/wordpress/plugins/wp-mail-smtp/wp-mail-smtp,4.9.0.zip`
- licensing: freemium (WP Mail SMTP Pro is separate)
- Freemius bundled: no
- vendor: **Awesome Motive** — see `docs/plugins/wpforms-lite.md` and
  `docs/plugins/google-analytics-for-wordpress.md` for the same review-notice pattern

## Analysis

Analysed on 11 Sep 2026 by Claude Code (Claude Opus 5).

The third Awesome Motive plugin audited, and it follows the house pattern closely: a
`Review` class constructed and discarded, a multi-step "Are you enjoying…?" notice that
branches into a WordPress.org rating ask, and a 14-day wait behind it.

One rule. Everything else WP Mail SMTP puts on screen is about **email delivery failing**,
which is as operational as notices get on this fleet.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` registrations | Numerous, almost all deliverability and setup errors. One promotional |
| Vendor opt-out filters | **None** for the review notice |
| Vendor opt-out constants | **None** |
| Dashboard widgets | 1 — an email-deliverability summary. **Kept** |
| Outbound calls from widgets | Reads locally logged send results |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Review request | `admin_init` → `Review::admin_notices`, which adds `admin_notices` (or `network_admin_notices`) → `Review::review_request` | **suppress** | *"Are you enjoying WP Mail SMTP?"* → *"Would you consider giving it a 5-star rating on WordPress.org?"* |
| Mailer setup / connection errors | `admin_notices` | **keep** | Email is silently broken until fixed. Never suppressed |
| Domain and DNS check failures | `admin_notices` | **keep** | Deliverability problems |
| Deprecated/legacy mailer warnings | `admin_notices` | **keep** | Configuration will stop working |
| Email deliverability dashboard widget | `wp_dashboard_setup` | **keep** | Real send/fail counts for the site |
| Review dismiss AJAX | `wp_ajax_wp_mail_smtp_review_dismiss` | **keep** | The vendor's own dismiss path |

## Deliberately left alone

### Everything about email delivery

This plugin exists because WordPress mail fails silently. Its notices are how a site owner
finds out that password resets, order confirmations and form submissions are not arriving.
On a WooCommerce fleet that is the highest-consequence notice category there is.

No rule here touches any of them, and any future rule must be checked against them
specifically.

### The dashboard widget

Shows sent/failed counts from the plugin's own email log. Real site data, no upsell block
found in the widget body, and no outbound request on render. Kept.

## Mechanism

- tier: 2 (targeted unhook, via the sanctioned `$wp_filter` reader)
- phase: `admin_init` at `self::EARLY_PRIORITY`
- vendor registers at: `src/Admin/Area.php:132` runs `( new Review() )->hooks();`, and
  `hooks()` adds `Review::admin_notices` to `admin_init` at the default priority
- instance reachable via: **no.** `( new Review() )->hooks();` discards the object, and it is
  not stored on the plugin container

### Why the target is `Review::admin_notices`, not `Review::review_request`

`Review::admin_notices()` is a *producer*: it runs on `admin_init` and its entire body is

```php
if ( is_multisite() ) {
    add_action( 'network_admin_notices', [ $this, 'review_request' ] );
} else {
    add_action( 'admin_notices', [ $this, 'review_request' ] );
}
```

Removing it before it runs therefore covers **both** the single-site and multisite paths with
one call, and needs no multisite branch of our own. That is why the rule sits in the
`EARLY_PRIORITY` pass — by `LATE_PRIORITY` the producer has already run.

Targeting `review_request` on `admin_notices` instead would work on single sites and
silently miss multisite. **Do not "simplify" it that way.**

## Drift check

- `src/Admin/Review.php` — `hooks()` and `admin_notices()`. If the notice is ever registered
  directly rather than through a producer, the phase and target both change
- `src/Admin/Area.php:132` — if `Review` is ever stored on the container, **drop the reader
  and name the instance**
- `Review::WAIT_PERIOD` (14) and `NOTICE_OPTION` (`wp_mail_smtp_review_notice`) — bench gates
- **Awesome Motive pattern.** When the next AM plugin is audited, check whether its review
  class has moved to a shared package; three of them now ship near-identical code and a
  shared base would allow one rule instead of three

## Verification

Bench: `bench2.local`, WP 7.1, WP Mail SMTP 4.9.0.

**This one has the most gates of any rule so far**, and it needed all of them opened before
the notice would render at all:

| Gate | Where | Bench action |
|---|---|---|
| Review record exists, not dismissed | `wp_mail_smtp_review_notice` | set to `{"time": <90 days ago>, "dismissed": false}` |
| A mailer other than the default | `wp_mail_smtp['mail']['mailer']` | set to `smtp` (default is `mail`, which bails) |
| **Mailer setup complete** | `$mailer_object->is_mailer_complete()` | full `smtp` block written — host, port, encryption, auth, user, pass |
| Plugin activated ≥ 14 days ago | `wp_mail_smtp_activated_time` | backdated 90 days |
| Super admin | — | bench user is an administrator on a single site |

The SMTP credentials are obvious placeholders pointing at `smtp.example.test`. Rendering a
notice sends no mail, so nothing was transmitted anywhere.

A first attempt with only the first two gates opened produced **nothing in the before
capture**, which would have made the rule source-verified at best. Worth recording: on this
plugin "the notice did not appear" almost always means a gate, not a broken rule.

### Review request — **Confirmed**

| Check | Before | After |
|---|---|---|
| `wp-mail-smtp-review-notice` on the dashboard | **4** | **0** |
| `Review::admin_notices` on `admin_init` | `true` | **`false`** |
| Debug log | — | `wp-mail-smtp: Removed WPMailSMTP\Admin\Review::admin_notices from admin_init priority 10.` |

(Four matches is the notice wrapper plus its three step `div`s.)

### Negative checks

| Check | Result |
|---|---|
| Dashboard, plugins, WooCommerce Status, product list | all 200, screens asserted |
| Other vendors' rules unaffected on the same bench (18 plugins active) | yes |
| Front page | 200 |
| PHP fatals / parse errors | **0** |

The deliverability notices could not be exercised — they require a genuinely broken mail
configuration, and the bench has placeholder SMTP settings that are never used to send. They
are separate callbacks on separate objects and no rule here names them.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_early_vendor_notices():
$this->unhook_wp_mail_smtp_review_request();
```
