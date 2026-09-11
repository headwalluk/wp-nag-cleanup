# WPForms Lite

- slug: `wpforms-lite`
- version analysed: `2.0.1.1`
- source: `/vault/backups/wordpress/plugins/wpforms-lite/wpforms-lite,2.0.1.1.zip`
- licensing: freemium (WPForms Pro is a separate premium plugin)
- Freemius bundled: no

## Analysis

Analysed on 11 Sep 2026 by Claude Code (Claude Opus 5).

WPForms Lite has a large notice surface — 24 `admin_notices` registrations — but almost
all of it is operational: Stripe, PayPal and Square webhook and domain health checks,
legacy-addon compatibility warnings, PHP/WP requirement failures and a Lite/Pro conflict
notice. Two things are nags, both owned by `WPForms_Review`, and both are suppressed.

The review request is the one Paul asked for. It is gated on a genuine usage milestone —
14 days installed, at least one published form, no Constant Contact notice pending — which
is why it does not appear on a fresh install.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 24. 22 operational, plus the shared `Notice::display` renderer and the Lite/Pro conflict notice |
| Vendor opt-out filters | `wpforms_setting` (global setting override, **rejected** — see below), `wpforms_admin_splash_screen_hide_splash_modal` (splash only, not used) |
| Vendor opt-out constants | **None** |
| Dashboard widgets | 1 — `wpforms_reports_widget_lite`. **Kept**, mixed output |
| Outbound calls from widgets | **None on render.** The widget reads local form and entry data |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| 5-star review request | `admin_init` → `WPForms_Review::review_request` | **suppress** | *"Would you do us a favor and take a few seconds to give us a 5-star review?"* Review begging |
| Admin footer rating text | `admin_footer_text` 1 → `WPForms_Review::admin_footer` | **suppress** | *"Please rate WPForms ★★★★★ on WordPress.org to help us spread the word."* Replaces core's footer text on WPForms screens |
| Pre-footer promotion block | `in_admin_footer` → `WPForms_Review::promote_wpforms` | **keep** | *"Made with ♥ by the WPForms Team"* plus Support/Docs/VIP Circle links. Vendor branding inside the vendor's own pages; out of scope by construction |
| Lite/Pro conflict notice | `admin_notices` → `wpforms_lite_notice` | **keep** | *"Your site already has WPForms Pro activated…"* Plugin conflict, with the fix |
| Requirements failures | `admin_notices` → `Requirements::show_notices` | **keep** | PHP/WP/extension version warnings |
| Stripe webhook + domain health | `admin_notices` → 3 callbacks | **keep** | Payment processing is broken or degraded. Never suppressed |
| Stripe low-amount surge detector | `admin_notices` → `LowAmountSurgeDetector` | **keep** | Card-testing fraud warning. Security notice |
| PayPal / Square webhook + domain | `admin_notices` → 4 callbacks | **keep** | As Stripe |
| Legacy addon compatibility (×3) | `admin_notices` → `AddonCompatibility` | **keep** | Addon/core version mismatch |
| Square cURL missing | `admin_notices` → `CurlCompatibility` | **keep** | Missing PHP extension |
| Lite Connect errors | `admin_notices` → `LiteConnect\Admin` | **keep** | Entry-backup failures — real data loss risk |
| User template entry notices | `admin_notices` → `UserTemplates` | **keep** | Operational status on the vendor's own screens |
| Addons page notices | `admin_notices` → `Lite\Admin\Pages\Addons` | **keep** | On the vendor's own screen |
| Setup wizard failed installs | `admin_notices` → `SetupWizard` | **keep** | Reports installs that failed |
| WPForms dashboard widget | `wpforms_reports_widget_lite` | **keep** | Mixed output — see below |

## Deliberately left alone

### `wpforms_setting` and `hide-announcements` — rejected, and this one would have persisted

`review_request()` bails immediately when `wpforms_setting( 'hide-announcements' )` is
truthy, and `wpforms_setting()` ends with `apply_filters( 'wpforms_setting', $value, $key, $default_value, $option )`
(since 1.7.8). So a filter callback returning `true` for that one key stops the review nag
without touching a hook.

The scope is far tighter than the equivalent Astra switch — `hide-announcements` is read in
only four places, and all four are promotional:

| Reader | Surface |
|---|---|
| `class-review.php:89` | the review request |
| `SplashScreen.php:390` | the "what's new" splash modal |
| `Pointers/Pointer.php:102` | education pointers |
| `Notifications.php:90` | the in-plugin notifications feed |

**Rejected anyway, because the value is written back.** `hide-announcements` is a real
setting in WPForms → Settings → General ("Hide Announcements"), and
`includes/admin/settings-api.php` renders every field's current state through
`wpforms_setting( $args['id'], ... )` — eleven call sites, one per field type. So filtering
it would:

1. Show the "Hide Announcements" toggle as **on** to a site owner who never set it
2. Persist that `true` into the `wpforms_settings` option the next time **anyone** clicks
   Save Settings on that page

That is worse than the `ast-disable-upgrade-notices` case in `docs/plugins/astra-theme.md`,
which only misreported state — this one writes it. A mechanism 1 rule that silently edits
the vendor's stored settings through the vendor's own form is not a mechanism 1 rule, it is
mechanism 4 by accident, without the safeguards mechanism 4 demands.

The `remove_action()` pair below changes no stored state at all.

### `promote_wpforms` — the vendor's own footer, left alone

`in_admin_footer` → `promote_wpforms()` renders *"Made with ♥ by the WPForms Team"* with
Support, Docs and VIP Circle links, on WPForms admin pages only.

Left alone deliberately, and the distinction against `admin_footer` is worth stating
because the two look alike:

- `admin_footer` hijacks **WordPress core's** `admin_footer_text`, replacing "Thank you for
  creating with WordPress" with a rating ask. Core's furniture, repurposed
- `promote_wpforms` renders the vendor's **own** block in its **own** page footer

The second is the vendor branding its own screens, which `CLAUDE.md` puts out of scope by
construction. The same line was drawn for WPCode in 1.20.0.

### The dashboard widget

`wpforms_reports_widget_lite` shows form count and entry counts for the last 7 days — real
data about the site — and below it a dismissible *"Upgrade to Pro and get access to the
reports"* block with a `utm_campaign` link.

Kept, same reasoning as CartFlows' Funnel Performance widget: `remove_meta_box()` is
all-or-nothing and would take the entry figures with the upsell, the upsell is already
dismissible by the site owner, and `widget_content()` makes **no outbound request**, so
removing it would buy no privacy and save no HTTP call.

### The payment health checks are the reason this plugin gets a careful read

Eight of the 24 notice registrations belong to Stripe, PayPal and Square integrations, and
they report webhook failures, domain verification failures, missing cURL, and — in
`LowAmountSurgeDetector` — a card-testing fraud pattern. On a site taking payments these
are the most operationally important notices WPForms produces. None is touched, and any
future rule here must be checked against them specifically.

## Mechanism

- tier: 2 (targeted unhook, via the sanctioned `$wp_filter` reader)
- phase: `admin_init` at `self::EARLY_PRIORITY`
- vendor registers at: `WPForms_Review::__construct()` → `hooks()`, run from
  `includes/admin/class-review.php` at plugin-load time (the file ends with
  `new WPForms_Review();`). `review_request` goes on `admin_init` at the default priority,
  `admin_footer` on the `admin_footer_text` filter at priority 1
- instance reachable via: **not reachable.** `new WPForms_Review();` at
  `includes/admin/class-review.php:348` discards the return value, and the class is not in
  WPForms' own registry — `WPForms::objects()` registers only `form` and `process`, so
  `wpforms()->obj( 'review' )` returns null

Mechanisms 1 to 3 were re-checked first, per `CLAUDE.md`:

- **Mechanism 1** — `wpforms_setting` works but writes back; rejected above
- **Mechanism 2 by name** — impossible, the instance is discarded and not registered
- **Mechanism 3** — the review nag is not a dashboard widget

`remove_discarded_instance_callback()` is used for both callbacks. It calls
`remove_action()`, which is an alias of `remove_filter()`, so it removes the
`admin_footer_text` **filter** entry as well — the same pattern already used for WPCode.

`EARLY_PRIORITY` is required for `review_request` because the producer is itself on
`admin_init` at priority 10. The `admin_footer_text` removal would work at any phase; it
rides along in the same method.

## Drift check

- `includes/admin/class-review.php` — the three `add_action`/`add_filter` lines in `hooks()`,
  and the `new WPForms_Review();` on the last line. If WPForms ever registers this class
  through `WPForms::register()` instead, **drop the reader and name the instance** via
  `wpforms()->obj( ... )`
- `includes/functions/forms.php` — `wpforms_setting()`. If the vendor ever adds a
  notice-specific filter, or stops rendering settings fields through this function, the
  mechanism 1 rejection above should be revisited
- `src/Integrations/{Stripe,PayPalCommerce,Square}/` — if any payment health check is ever
  queued from the same callback as a promotion, **withdraw the rule**
- `src/Lite/Admin/DashboardWidget.php` — if the upsell block is ever split out of
  `widget_content()`, or the widget starts making an outbound call, the mechanism 3
  question reopens

## Verification

Bench: `bench2.local`, WP 7.1, WPForms Lite 2.0.1.1, over authenticated admin HTTP requests.

**Time gates that had to be backdated.** The review nag has two, and a fresh install
satisfies neither:

| Gate | Where | Bench action |
|---|---|---|
| 14 days since Lite activation | `wpforms_activated['lite']` | backdated 30 days |
| 24 hours since the notice was first scheduled | `wpforms_admin_notices['review_request']['time']` | backdated 30 days (the first admin request creates this record and returns without showing) |
| At least one published form | `wpforms` post count | one published |
| Constant Contact notice not pending | `wpforms_constant_contact` | confirmed unset |

### Review request — **Confirmed**

Surface: admin notice area, dashboard and WPForms screens.

| Check | Before | After |
|---|---|---|
| `wpforms-review-notice` in dashboard HTML | 1 | **0** |
| `review_lite_request` (the notice slug) | 1 | **0** |
| `WPForms_Review::review_request` on `admin_init` | `true` | **`false`** |
| Callbacks on `admin_init` | 153 | **152** |
| Debug log | — | `wpforms-lite: Removed WPForms_Review::review_request from admin_init priority 10.` |

### Admin footer rating text — **Source-verified and hook-verified, not visually confirmed**

`WPForms_Review::admin_footer` is confirmed removed from `admin_footer_text`
(`HOOKED` → `gone`, and the debug log records it), but **its rendered output could not be
observed before the change, because `headwall-hosting` already suppresses it.**

`includes/class-plugin.php:72` registers `HWH_Admin_Hooks::admin_thank_you_footer` on
`admin_footer_text` at the default priority 10. WPForms registers at priority **1**, and the
Headwall callback **replaces** the text rather than appending, so on every Headwall fleet
site the WPForms rating ask is already gone — overwritten, not removed.

That makes this half of the rule unobservable on any Headwall site by rendered output. It
still earns its place: this plugin is distributed standalone on GitHub, where no such
override exists, and removing the filter is cheaper than letting it run and be discarded.
Recorded honestly rather than claimed as confirmed, per the
"prove the notice renders before claiming you removed it" rule — the before capture showed
`Created with WordPress .::. Powered by Headwall Hosting`, not a rating ask.

### Negative checks

| Check | Result |
|---|---|
| `WPForms\Admin\Notice::display` still on `admin_notices` | **yes** — the shared renderer every other WPForms notice uses |
| `WPForms\Requirements\Requirements::show_notices` still hooked | **yes** |
| `WPForms_Review::promote_wpforms` still on `in_admin_footer` | **yes** — deliberately left |
| `wpforms_reports_widget_lite` widget markup | 9 matches before, **9 after** |
| Callbacks on `admin_notices` | 50, unchanged |
| Dashboard, WPForms overview, WC Status, Plugins screens | all 200, screens asserted |
| Front page | 200 |
| PHP fatals / parse errors in `error.log` | **0** |

### A bench trap that produced a false negative

The first after-capture reported the rule doing nothing — `review_request` still hooked, no
debug line, identical hook counts. The rule was correct; **opcache was serving the previous
copy of the mu-plugin** because the capture ran immediately after `cp`. `CLAUDE.md` already
requires "a settle delay after each deploy" and this is why. Every comparison above was
re-run with three warm requests between deploy and capture.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_early_vendor_notices():
$this->unhook_wpforms_review_promos();

public function unhook_wpforms_review_promos() : void {
    $this->remove_discarded_instance_callback( 'admin_init', 'WPForms_Review', 'review_request', 'wpforms-lite' );
    $this->remove_discarded_instance_callback( 'admin_footer_text', 'WPForms_Review', 'admin_footer', 'wpforms-lite' );
}
```
