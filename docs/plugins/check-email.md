# Check & Log Email

- slug: `check-email`
- version analysed: `2.0.16`
- source: `/vault/backups/wordpress/plugins/check-email/check-email,2.0.16.zip`
- licensing: freemium (Check & Log Email Pro is separate; `CK_MAIL_PRO_VERSION` marks it)
- Freemius bundled: no
- vendor: WPOmnia (bought from WPChill/MachoThemes in Oct 2023). The newsletter class is
  Magazine3 code and posts to `magazine3.company`

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5).

Reported by Paul from a live dev site: two nags from this plugin. One is a **newsletter
sign-up pointer** (a core `wp-pointer` anchored to the plugin's admin menu item) with the
current admin's email address, display name and site URL already filled in. The other is
a standard **"please rate us"** notice in the admin notice area, which appears one day
after the plugin is first seen.

The pointer is the worse of the two. `Check_Email_Newsletter::ck_mail_add_localize_footer_data()`
puts `$current_user->user_email` and `display_name` into a `wp_localize_script()` object
**on every admin page load**, for every admin user, before anyone has agreed to anything.
Nothing is sent until someone clicks Subscribe (then it goes to
`http://magazine3.company/wp-json/api/central/email/subscribe` — plain HTTP,
`sslverify => false`). But one click sends a private admin address to a third party, and
the admin did not type it in.

Two rules, both mechanism 2 through the sanctioned `$wp_filter` reader. Everything else
the plugin shows is about email delivery, and stays.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 5: PHP < 5.6 compatibility, SMTP "retype credentials" (×2 sites), log-threshold warning (×2 sites), review request. One promotional |
| Multiline `add_action(` form | None |
| `in_admin_header` / `admin_print_footer_scripts` | `Check_Email_Review::ajax_script` — the review notice's dismiss script |
| `admin_enqueue_scripts` (added for this plugin; the pointer is drawn by JS) | `Check_Email_Newsletter::ck_mail_enqueue_newsletter_js` — **the pointer**. The rest load assets for the vendor's own screens, the dashboard chart and the plugins-page deactivation form |
| Vendor opt-out filters | **None** for either nag. `check_mail_pro_upgrade_banner` and `ck_mail_localize_filter` exist — see below |
| Vendor opt-out constants | None |
| Dashboard widgets | 2 — `checmail_dashboard_widget` (email activity chart) and `check_email_dashboard_widget` (log count). **Both kept** |
| Outbound calls | Newsletter subscribe (on click only), deactivation feedback (on submit only), DNS check / spam analyser / Twilio / failure notifications (features the owner configures). None on page render |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Newsletter sign-up pointer, pre-filled with the admin's email | `admin_enqueue_scripts` → `Check_Email_Newsletter::ck_mail_enqueue_newsletter_js` | **suppress** | *"Do you want the latest updates … and some best resources on monetization in a single email?"* A mailing-list sign-up. Nothing about the site |
| Review request | `admin_notices` → `CheckEmail\Core\Check_Email_Review::five_star_wp_rate_notice` | **suppress** | *"… please consider rating it. It would mean the world to us."* |
| Review dismiss script | `admin_print_footer_scripts` → `Check_Email_Review::ajax_script` | **suppress** | Only binds the notice's buttons; nothing to bind once the notice is gone |
| SMTP "retype credentials" | `admin_notices` → `Check_Email_SMTP_Tab::retype_credentials_notice` | **keep** | SMTP credentials need re-entering; mail may be failing |
| Log threshold met | `admin_notices` → `render_log_threshold_met_notice` | **keep** | Log table has grown past the owner's own threshold |
| PHP < 5.6 notice | `admin_notices` → `check_email_compatibility_notice` | **keep** | PHP version warning |
| Email activity widget | `checmail_dashboard_widget` | **keep** | Sent/failed counts from the site's own log; chart data is fetched from local admin-ajax |
| Email summary widget | `check_email_dashboard_widget` | **keep** | Log count and links. No promo, no outbound call |

## Deliberately left alone

### `check_mail_pro_upgrade_banner` — the "50% discount" PRO banner

A named filter the free build registers as a closure (only when `CK_MAIL_PRO_VERSION` is
not defined), which renders a "Solve email delivery issues faster with PRO version (50%
discount)" banner. It is only called from the vendor's own pages: Dashboard, Logs, Error
Tracker, Settings and Status. **Out of scope by construction** — vendor screens are not
touched (see `contact-form-7.md` for the same call).

### The deactivation feedback form

`ck_mail_enqueue_makebetter_email_js` loads a "why are you deactivating?" overlay on
`plugins.php`. It only opens when someone clicks Deactivate, and only posts if they submit
it. It interrupts nobody. Left alone.

### `ck_mail_localize_filter` — not used as a lever

It looks like an obvious mechanism 1 route: add a later filter that sets `do_tour` to
false for `ck_mail_localize_data`, and the JS never opens the pointer. **Not used**,
because:

- it is a general "script data" filter, not an opt-out, and the deactivation-form script
  runs it too (with `'eztoc_admin_data'`, copied from another Magazine3 plugin)
- it would need a new callback method in our class, where unhooking needs one line
- it would still enqueue the pointer script and print the admin's email and name into
  every admin page. Removing the enqueue gets rid of both

### `pre_option_check-email-rate-time` — not used, and a trap

The review gate is `time() > get_option( 'check-email-rate-time' )`. The Premium Addons
route (`__return_true` on core's `pre_*` filter, see
`premium-addons-for-elementor.md`) **does the opposite here**: `true` compares as
`1`, `time() > 1` is true, and the nag shows. Doing it properly would need a method that
returns a timestamp in the future — more code than the unhook, and not needed, because
the callback belongs to a class and can be found by name.

### `dismissed_wp_pointers` — not used

Filtering core's user meta so it always contains `ck_mail_subscribe_pointer` would also
stop the pointer. **Not used**: it changes what core reports as a user's saved setting,
and still prints the email into the page.

### Other outbound calls

The DNS checker (`enchain.tech`), spam analyser, Twilio SMS and `check-email.tech`
failure notifications are all features the owner uses on purpose or sets up. None of them
fires on page render. Not nags, so out of scope.

## Mechanism

- tier: 2 (targeted unhook, via the sanctioned `$wp_filter` reader), three calls
- phase: `admin_init` at `self::LATE_PRIORITY`, in `unhook_vendor_notices()`
- vendor registers at:
  - pointer: `check-email.php:47` requires `include/class-check-email-newsletter.php` when
    `is_admin()`, which ends `new Check_Email_Newsletter();`. The constructor adds the
    enqueue to `admin_enqueue_scripts` at priority 10, so it exists as soon as the plugin
    loads
  - review: `check_email_log()` runs `$check_email->add_loadie( new Check_Email_Review() )`.
    The constructor adds `init` → `Check_Email_Review::init`, which (in admin, for
    `manage_options`, once the gate has passed) adds `admin_notices` and
    `admin_print_footer_scripts` at priority 10. `init` runs before `admin_init`, so both
    are there at `LATE_PRIORITY`
- instance reachable via: **neither.**
  - `new Check_Email_Newsletter();` throws the object away
  - `wpchill_check_email()` returns the `Check_Email_Log` container, but `$loadies` is
    `private`, and anyway `add_loadie()` **rejects** `Check_Email_Review`: it does not
    `implement Loadie`, so `add_loadie()` returns `false` and never stores it. The object
    is only kept alive by its own hook callbacks

The pointer script (`assets/js/admin/ck_mail-newsletter-script.js`) does nothing except
draw the pointer and handle its form, so removing its enqueue has no side effects. The
matching `ck_mail_localize_filter` callback stays registered; without the script it still
enqueues core's `wp-pointer` CSS/JS on the plugins page, which draws nothing.

## Drift check

Both targets are unchanged across **every** version in the vault, 2.0.8 → 2.0.16:

```bash
for PLUGIN_ZIP in $(ls -1 /vault/backups/wordpress/plugins/check-email/*.zip | sort -V); do
  echo "$(basename "${PLUGIN_ZIP}") newsletter=$(unzip -p "${PLUGIN_ZIP}" check-email/include/class-check-email-newsletter.php | command grep -c 'ck_mail_enqueue_newsletter_js\|^class Check_Email_Newsletter\|^new Check_Email_Newsletter') review=$(unzip -p "${PLUGIN_ZIP}" check-email/include/Core/Check_Email_Review.php | command grep -c 'five_star_wp_rate_notice\|ajax_script\|^class Check_Email_Review {')"
done   # expect newsletter=4 review=5 on each line
```

- `include/class-check-email-newsletter.php` — if the instance is ever stored, or the
  pointer moves into a different script, the rule changes
- `include/Core/Check_Email_Review.php` — if the class ever `implements Loadie`, it gets
  stored in the container but is still `private`; the reader stays correct. If the class
  moves out of `CheckEmail\Core`, the namespaced class name in the rule has to change too

## Verification

Bench: `bench2.local`, WP 7.1, Check & Log Email **2.0.16** unzipped from the vault,
16 Sep 2026. Before = 1.27.0 (HEAD), after = working copy, both loaded through
`headwall-hosting.php` with `HEADWALL_NAG_CLEANUP_DEBUG` on. Three warm-up requests after
each deploy.

Time gate: the first admin request writes `check-email-rate-time` as now + 1 day. It was
backdated one hour into the past with `wp option update` before the "before" capture.
The pointer is not time-gated; it shows until `ck_mail_subscribe_pointer` is in the
user's `dismissed_wp_pointers`.

### Newsletter pointer — **Confirmed** (bench)

| Check (dashboard and plugins.php) | Before | After |
|---|---|---|
| `ck_mail-newsletter-script` in page | **3** | **0** |
| `"do_tour":"1"` | present | absent |
| `current_user_email` printed into page | present (`nagtest@bench2.local`) | **absent** |
| Debug log | — | `check-email: Removed Check_Email_Newsletter::ck_mail_enqueue_newsletter_js from admin_enqueue_scripts priority 10.` |

Also absent on the plugin's own `check-email-status` screen.

### Review request — **Confirmed** (bench)

| Check (dashboard and plugins.php) | Before | After |
|---|---|---|
| `check-email-epsilon-review-notice` | **3** | **0** |
| Debug log | — | `Removed CheckEmail\\Core\\Check_Email_Review::five_star_wp_rate_notice from admin_notices priority 10.` and `… ::ajax_script from admin_print_footer_scripts priority 10.` |

(Three matches: the notice `id` and two selectors in the dismiss script.)

### Negative checks

| Check | Result |
|---|---|
| Screens asserted: `id="dashboard-widgets"`, `id="the-list"` on plugins.php | yes |
| `checmail_dashboard_widget` still on the dashboard | yes |
| `check-email-rate-time` after the rule | unchanged (still the backdated value) — nothing written |
| `dismissed_wp_pointers` for the bench user | empty — nothing written |
| PHP fatals / warnings in `error.log` | **0** |

The SMTP and log-threshold notices were not exercised: they need a failing SMTP setup or
a log table over its threshold. They are separate callbacks on separate classes, and no
rule here names them.

### Live — **Confirmed**, 16 Sep 2026

Paul deployed 1.28.0 to the client staging site where both nags were reported and
confirmed both gone the same day.

## Additions to `headwall-nag-cleanup.php`: 1 rule method, mechanism 2

```php
// In unhook_vendor_notices():
$this->unhook_check_email_promos();

public function unhook_check_email_promos() : void {
	$this->remove_discarded_instance_callback( 'admin_enqueue_scripts', 'Check_Email_Newsletter', 'ck_mail_enqueue_newsletter_js', 'check-email' );
	$this->remove_discarded_instance_callback( 'admin_notices', 'CheckEmail\\Core\\Check_Email_Review', 'five_star_wp_rate_notice', 'check-email' );
	$this->remove_discarded_instance_callback( 'admin_print_footer_scripts', 'CheckEmail\\Core\\Check_Email_Review', 'ajax_script', 'check-email' );
}
```
