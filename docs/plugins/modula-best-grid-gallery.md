# Modula (WPChill)

- slug: `modula-best-grid-gallery`
- version analysed: `2.14.39`
- source: `/vault/backups/wordpress/plugins/modula-best-grid-gallery/modula-best-grid-gallery,2.14.39.zip`
- licensing: freemium (free on wordpress.org, Modula Pro sold at wp-modula.com)
- Freemius bundled: no

## Analysis

Analysed on 10 Sep 2026 by Claude Code (Claude Opus 5), from a telemetry consent prompt
Paul hit on the same client site as MonsterInsights and Rank Math.

**One rule, mechanism 1, one line.** Modula turned out to be the cleanest audit of the
three: WPChill's telemetry component reads its whole configuration through a single
filter, and the `enabled` key in that configuration is tested at both of the two points
that matter — before the hooks are registered, and before the consent notice is added.
One `add_filter` reaches the prompt, the cron schedule, the AJAX handlers, the footer
script and every send path.

Everything else Modula does in the admin is either operational or confined to its own
screens. Notably the **review nag is not in scope** — it looks like an `admin_notices`
callback and is not one, which is the finding most likely to trip up a future pass.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **8**. One telemetry consent notice, one review feeder, two Elementor/PHP requirement warnings, three from the bundled Action Scheduler, one bulk-action result |
| Vendor opt-out filters | **`wpchill_telemetry_config`**, plus `wpchill_telemetry_products` / `_extensions` / `_themes` / `_third_party` / `_settings` / `_settings_allowlist` (payload contributors, not switches) and `wpchill_notifications_allowed_screens` |
| Vendor opt-out constants | None |
| Dashboard widgets | **None.** Modula registers no dashboard widget at all |
| Outbound calls from widgets | N/A — no widgets. The telemetry endpoint is `https://telemetry.wpchill.com`; the remote upsell and notification channels are `https://wp-modula.com/wp-json/upsells/v1/get` and `.../notification/v1/get`, both on cron and both consumed only on Modula's own screens |
| Freemius | Not bundled |

The `adminmenu` / `in_admin_header` / `admin_head` / `admin_footer` sweep — added to this
checklist after MonsterInsights hid a nag on `adminmenu` — found the telemetry consent
script, the review nag's AJAX script, and two `clear_admin_notices` callbacks that are
Modula tidying **other people's** notices off its own screens. Nothing new in scope.

### When this arrived, and how far it has spread

Swept every `.zip` Modula release in the vault for `class-wpchill-telemetry-core.php`:

| Releases | Telemetry core |
|---|---|
| 2.10.2 → 2.13.9 (51 releases) | **absent** |
| 2.14.1 → 2.14.39 (33 releases) | **present** |

A clean cutover at **2.14.1**, which is why this prompt is newly visible on the fleet
rather than something long-standing. `wpchill_telemetry_config` and both `enabled` guards
are present and identical in 2.14.1, 2.14.20, 2.14.30 and 2.14.39, so the rule covers the
whole telemetry era rather than just the newest build.

Swept all ~1,477 vault slugs for the same file: **Modula is the only plugin that carries
it.** Strong Testimonials 3.3.7 bundles WPChill's *notification system* but not the
telemetry core, and Download Monitor is not held in the vault. So this is a one-plugin
rule today — but the class is `WPChill_Telemetry_Core`, not `Modula_Telemetry`, it is
loaded by a generic `wpchill-telemetry-loader.php`, and its public API is a set of
`wpchill_telemetry_*` functions with `modula_telemetry_*` kept only as deprecated
aliases. It is built to be rolled out across WPChill's range, and the filter will cover
that automatically when it is.

## Findings

| Item | Hook / ID | Verdict | Reason |
|---|---|---|---|
| Telemetry consent prompt | `admin_notices` → `WPChill_Telemetry_Core::show_consent_notice` | **suppress** | "Help us improve Modula! We'd like to collect anonymous usage data." **Usage-tracking opt-in prompt**, named explicitly on the suppress list |
| Telemetry itself | weekly + hourly cron → `telemetry.wpchill.com` | **suppress** | Taken with the same key. Payload carries the site URL, WP and PHP versions, Modula settings and a **full third-party plugin inventory** |
| Review request | `admin_notices` → `Modula_Review::five_star_wp_rate_notice` | keep | **Renders only on Modula's own screens.** See below — it prints nothing |
| Minimum Elementor version | `admin_notices` → `Modula_Elementor_Check::admin_notice_minimum_elementor_version` | keep | **Missing-dependency notice. Never suppressed** |
| Minimum PHP version | `admin_notices` → `Modula_Elementor_Check::admin_notice_minimum_php_version` | keep | **PHP version warning. Never suppressed** |
| Action Scheduler migration | `admin_notices` → `Controller::display_migration_notice` | keep | **Data migration prompt**, and it belongs to a shared library, not to Modula |
| Action Scheduler past-due | `admin_notices` → `ActionScheduler_AdminView::maybe_check_pastdue_actions` | keep | Scheduled actions are overdue — a real fault on this site |
| Action Scheduler comment cleanup | `admin_notices` → `ActionScheduler_WPCommentCleaner::print_admin_notice` | keep | Tells the owner logs were moved. Operational |
| Bulk action result | `admin_notices` → `Modula_Admin::modula_media_bulk_notice` | keep | Reports what a bulk edit did. Transient-backed, shows once |
| Remote upsell channel | cron → `wp-modula.com/wp-json/upsells/v1/get` | keep | Feeds `modula_upsell_buttons`, consumed only by upgrade modals **inside Modula's own screens** |
| Remote notification channel | cron → `wp-modula.com/wp-json/notification/v1/get` | keep | Rendered only on WPChill screens — see below |
| Pro upgrade modals | `modula_upsell_buttons` in `includes/admin/templates/modal/` | keep | Vendor's own settings and metabox screens. Out of scope by construction |
| `clear_admin_notices` | `in_admin_header` priority 99 | keep | Modula removing *other* plugins' notices from its own pages. Not our business |

## Deliberately left alone

### The review nag is not an admin notice, and that is the trap here

`Modula_Review` registers what looks like a textbook target:

```php
add_action( 'admin_notices', array( $this, 'five_star_wp_rate_notice' ) );
```

The callback prints nothing. It builds an array and hands it to the WPChill notification
store:

```php
WPChill_Notifications::add_notification( 'five-star-rate', $notice );
```

which is `update_option( 'wpchill_notification_five-star-rate', $notice )`. Rendering
happens client-side, from a React bundle that is only enqueued behind:

```php
if ( ! $this->is_wpchill_admin_page() || ! current_user_can( 'manage_options' ) ) {
    return;
}
```

and `is_wpchill_admin_page()` matches only screen ids containing `modula-gallery`,
`modula-albums`, `dlm_download` or `wpm-testimonial`. So the review request appears
**only on Modula's own screens**, which `CLAUDE.md` puts out of scope, and never in the
general notice area.

A future pass that greps for `admin_notices` and stops at the hook name will read this as
a live review nag and write a rule for it. The rule would remove a callback that produces
no visible output anywhere this project cares about, and would look like it worked. It is
recorded here so that does not happen.

The one real side effect is an `update_option()` call on every admin page load, but the
notification array is byte-identical each time — `timestamp` is explicitly set to `false`
in the array, so the `! isset()` guard never refreshes it — and WordPress short-circuits
`update_option()` when the value is unchanged. No write, no churn.

### The remote upsell and notification channels stay

Both fetch vendor-controlled content on cron, and both would be worrying if they could
inject into the general notice area. Neither can:

- `WPChill_Remote_Upsells` stores promotions and hooks `modula_upsell_buttons`, a filter
  applied only inside `includes/admin/templates/modal/*-upgrade.php` — the upgrade modals
  on Modula's own settings and gallery screens
- `WPChill_Notifications` gates its renderer on `is_wpchill_admin_page()` as above

This is the AIOSEO `ScreenCallout` decision again: a vendor-controlled promotional channel
confined to the vendor's own screens is out of scope, however much it looks like a target.
If either ever loses its screen gate, both come into scope immediately — that is in the
drift check.

### The Action Scheduler notices are not Modula's

`includes/libraries/action-scheduler/` is the standard shared library, also carried by
WooCommerce and many others. Its three notices report a background-job migration, overdue
scheduled actions and a log-storage move. All operational, and all would need to be
considered as a library-wide question rather than a Modula rule if they were ever targeted.

### There is no dashboard widget

Worth stating plainly because it is the one thing this project checks for on every plugin:
Modula 2.14.39 calls `wp_add_dashboard_widget()` nowhere and hooks `wp_dashboard_setup`
nowhere. Nothing to remove, and nothing fetched on dashboard render.

## Mechanism

- tier: 1 (vendor hook)
- phase: file scope
- vendor registers at: `WPChill_Telemetry_Core::__construct()`, reached from
  `wpchill-telemetry-loader.php`, which `Modula_Dependency_Loader` requires during normal
  plugin load. An mu-plugin filter registered at file scope is always in place first
- instance reachable via: N/A

The constructor runs `init_config()` then `setup_hooks()`, and `init_config()` ends with:

```php
$this->config = apply_filters( 'wpchill_telemetry_config', $this->config );
```

The return value **is** assigned — checked at the call site, after Rank Math's identically
shaped filter turned out not to be — so `enabled` can be overridden. It is then tested at
both points that matter:

```php
private function setup_hooks() {
    if ( ! $this->config['enabled'] ) {
        return;                       // no cron, no AJAX handlers, no admin_footer script
    }
    ...

public function is_enabled() {
    if ( ! $this->config['enabled'] ) {
        return false;                 // before the consent notice is registered
    }

    $consent   = get_option( self::CONSENT_OPTION, null );
    $dismissed = get_option( self::CONSENT_DISMISSED_OPTION, false );
    if ( null === $consent && ! $dismissed ) {
        add_action( 'admin_notices', array( $this, 'show_consent_notice' ) );
        return false;
    }
    ...
```

That second guard is why this is a mechanism 1 rule rather than an unhook: the consent
notice is registered from **inside** `is_enabled()`, at whatever point in the request the
first caller happens to ask. There is no stable phase at which `remove_action()` could
name it. Setting `enabled` false means it is never added in the first place.

### What actually calls `is_enabled()`

Worth tracing, because a guard that nothing reaches produces no notice and the rule would
be pointless. `Modula_Telemetry_Integration` hooks `init` at priority 20:

```
init:20  init_telemetry()
      -> maybe_send_initial_registration()
      -> wpchill_telemetry_register_now( true )
      -> WPChill_Telemetry_Core::send_registration()
      -> is_enabled()                       <- registers the consent notice here
```

`maybe_send_initial_registration()` is gated on `modula_telemetry_registration_sent`, which
is only written when the send **succeeds** — so on a site that has never consented the
registration always fails with `WP_Error( 'telemetry_disabled' )`, the option is never set,
and the chain runs again on the next request. That is what makes the prompt persistent
rather than one-shot, and it also means `is_enabled()` is reached on essentially every
request until the owner answers. `init` fires long before `admin_notices`, so the
registration is always in time.

With the filter in place the first guard returns false and the chain stops before the
`add_action()`, on every one of those requests.

Every send path (`send_registration`, `send_state`, `send_settings`, `send_event`,
`send_events_batch`) opens with its own `is_enabled()` guard and returns a `WP_Error`, and
`Modula_Telemetry_Integration` checks `wpchill_telemetry_is_enabled()` before calling any
of them. Nothing assumes the hooks exist, so nothing breaks.

### Why the whole feature and not just the prompt

The narrower reading would be to suppress the prompt and leave telemetry able to run if
the owner opts in. That is not available: the prompt is the only opt-in surface, and with
`consent` never set, `is_enabled()` already returns false. Turning `enabled` off makes the
site's existing default answer — no — permanent and silent.

It is also the established precedent. `bsf_usage_tracking_enabled`, `wpdesk_tracker_enabled`
and the Happy Addons Appsero rule all suppress the tracking outright rather than only its
prompt, and `CLAUDE.md` lists "usage-tracking opt-in prompts" as suppressible while making
removing other people's outbound calls an explicit goal.

**One side effect worth knowing.** On a site where Modula has already run,
`wpchill_telemetry_weekly_report` and `wpchill_telemetry_process_queue` are already in the
cron table. `setup_hooks()` returning early means the listeners are never registered, so
those events fire against nothing and are rescheduled. Inert, but they stay in the cron
table until Modula is removed. This plugin does not unschedule other people's cron events.

## Drift check

Re-check when a new version appears in the vault:

- `includes/features/telemetry/class-wpchill-telemetry-core.php` — the
  `wpchill_telemetry_config` filter name, that its **return value is still assigned**, and
  both `$this->config['enabled']` guards. Losing either guard, or the assignment, silently
  no-ops the rule
- The same file's `DEFAULT_BASE_URL`, if the endpoint moves
- `includes/admin/wpchill/class-wpchill-notifications.php` — `is_wpchill_admin_page()` and
  the `wpchill_notifications_allowed_screens` list. **If that gate is dropped or widened to
  core screens, the review nag and the remote notification channel both come into scope**
- `includes/admin/wpchill/class-wpchill-remote-upsells.php` — whether `modula_upsell_buttons`
  is still only applied inside `includes/admin/templates/modal/`
- **Other WPChill plugins.** Re-run the vault sweep for `class-wpchill-telemetry-core.php`
  when Download Monitor or Strong Testimonials appear or update; the component is built to
  spread, and the existing filter will already cover them

## Verification

**Confirmed gone on a live client site**, 10 Sep 2026, after Paul deployed 1.24.0 to the
site this audit came from. The telemetry consent prompt was rendering before the deploy and
was absent afterwards.

| Rule | Result |
|---|---|
| `wpchill_telemetry_config` → `enabled` false | **Confirmed.** The consent prompt is gone |

This is the only one of the three plugins audited that day whose single rule was fully
exercised by the deploy, because the rule and the reported nag are the same thing.

The result confirms more than the prompt's absence. The filter is read in
`init_config()` **before** `setup_hooks()` and before anything calls `is_enabled()`, so a
prompt that no longer renders means the mu-plugin's file-scope filter was in place ahead of
Modula's own load — the load-order claim in the Mechanism section — and that
`apply_filters()`' return value really is assigned at that call site, which was checked in
source precisely because Rank Math's identically shaped filter discards it.

### Still to do

The prompt's absence does not by itself prove the telemetry stopped, only that the opt-in
surface is gone. Two assertions separate this rule from one that merely hides the prompt,
and neither has been made yet:

- `wp_next_scheduled( 'wpchill_telemetry_weekly_report' )` returns false on a site where
  Modula is activated with the rule already in place. On a site where Modula ran **before**
  the rule arrived, that event and `wpchill_telemetry_process_queue` are already in the cron
  table and will stay there, firing against no listener — inert, but present. **The client
  site is in that second category**, so a clean check needs a fresh activation
- No request to `telemetry.wpchill.com` appears in the outbound log

Both are cheap on a bench, and this remains the least expensive of the seven rules added
that day to verify properly: no time gate, no vendor authentication, and the prompt renders
on `dashboard`, `plugins` and `toplevel_page_modula`. Capture `index.php`, assert the
screen, and probe `class="wpchill-telemetry-consent"`.

One negative check still outstanding: the Elementor-requirement and PHP-version notices from
`Modula_Elementor_Check` must still render. They are the operational half of Modula's notice
output and the thing this rule must not touch. Nothing was reported missing on the client
site, but that plugin combination may not have been present to test it.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 1

```php
// WPChill telemetry, in Modula only across the whole vault today. Setting
// enabled to false reaches the consent prompt, the cron schedule and every send
// path at once. Modula 2.14.39, unchanged since 2.14.1.
// docs/plugins/modula-best-grid-gallery.md
add_filter( 'wpchill_telemetry_config', [ $this, 'disable_wpchill_telemetry' ] );
```

```php
/**
 * Turn WPChill's telemetry off before its core reads the config.
 *
 * enabled is tested twice: setup_hooks() returns before scheduling cron or adding
 * the consent script, and is_enabled() returns before it registers the consent
 * notice on admin_notices. One key reaches both.
 */
public function disable_wpchill_telemetry( $telemetry_config ) {
	if ( is_array( $telemetry_config ) ) {
		$telemetry_config['enabled'] = false;
	} else {
		// Vendor changed the config shape; pass it through rather than guess.
	}

	return $telemetry_config;
}
```
