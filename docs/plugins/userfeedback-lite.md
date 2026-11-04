# UserFeedback Lite (Awesome Motive)

- slug: `userfeedback-lite`
- version analysed: `1.12.0` (newest in the vault; also present: 1.11.1 to 1.11.4)
- source: `/vault/backups/wordpress/plugins/userfeedback-lite/userfeedback-lite,1.12.0.zip`
- licensing: freemium (UserFeedback Pro sold separately, licensed through the vendor's own API)
- Freemius bundled: **no**

## Analysis

Analysed on 4 Nov 2026 by Claude Code (Claude Opus 5).

Paul raised it from a client site, about a PHP warning rather than a nag:

```
PHP Warning:  foreach() argument must be of type array|object, null given in
wp-content/plugins/userfeedback-lite/includes/admin/class-userfeedback-notifications.php on line 236
```

That warning was investigated first (see "The notifications feed warning" below). It was
fixed outside this plugin. A full audit followed.

This is MonsterInsights' sibling: the same vendor, and much of the same scaffolding.
`UserFeedback_Review` is `MonsterInsights_Review` with the names changed, and the
notifications inbox is the same design. **Read
[`google-analytics-for-wordpress.md`](google-analytics-for-wordpress.md) for the shared
reasoning.**

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | `UserFeedback_Review::review_request`; `UserFeedback_Lite::userfeedback_pro_notice` (only when Pro is also active); `UserFeedback_Compatibility_Check::display_php_notice` / `display_wp_notice`; bundled Action Scheduler: `display_migration_notice`, `maybe_check_pastdue_actions`, `ActionScheduler_WPCommentCleaner::print_admin_notice` |
| Multiline `add_action(` form | Action Scheduler WP-CLI hooks, `init`, `wp_head`. None are notice-related |
| `in_admin_header` / `admin_print_footer_scripts` | None in the plugin's own code |
| Vendor opt-out filters | `userfeedback_admin_script_localization` (the script data for every UF screen and the dashboard widget, **used**); `userfeedback_usage_tracking_url`; `userfeedback_vue_notices` / `userfeedback_vue_wp_notices` (notices inside the vendor's own Vue screens); `userfeedback_exclusion_banner_config` (front end) |
| Vendor opt-out constants | `USERFEEDBACK_VERSION_NOTICE_ACTIVE`, a once-only mutex for the PHP and WP version notices, not an opt-out |
| Dashboard widgets | `UserFeedback_Dashboard_Widget`, id from `WIDGET_KEY`. It is an empty div that `assets/vue/js/dashboard-widget.js` fills in the browser |
| Outbound calls | `UserFeedback_Notifications::fetch_feed()` → `plugin.userfeedback.com/wp-content/notifications.json` (WP-Cron only, see below). Usage tracking → `miusage.com/v1/uf-checkin/`, only when `allow_usage_tracking` is set. Licensing, addons, AI summary and email-summary calls are user-initiated or Pro |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Review request | `admin_notices` → `UserFeedback_Review::review_request` | **suppress** (unhook via the reader) | *"Hey - we noticed you've been using UserFeedback for a while … give it a 5-star review"*. 14 days after install, on every admin screen, for super admins |
| Duplicator cross-sell in the dashboard widget | `userfeedback_admin_script_localization` → `recommended_plugin_is_installed` / `_is_activated` | **suppress** (vendor filter) | *"Recommended Plugin: Duplicator - Install / Learn More"*, under the survey figures. Cross-selling another product |
| Dashboard widget itself | `wp_dashboard_setup` → `UserFeedback_Dashboard_Widget::register_dashboard_widget` | keep | "Total Entries" and "Total Entries by Survey" are the site's own data |
| "Pro and Lite both active" notice | `admin_notices` → `userfeedback_pro_notice` | keep | A plugin conflict on the site |
| PHP / WordPress version notices | `admin_notices` → `UserFeedback_Compatibility_Check` | keep | Version warnings are never suppressed |
| Action Scheduler notices | bundled library | keep | Migration, past-due actions and log cleanup. Operational |
| Notifications inbox and its menu-count badge | REST `userfeedback/v1/notifications`, `get_count_for_admin_sidebar()` in the menu title | keep | Mixed: "Your first survey has a response" and "Your survey is popular" sit beside "Upgrade to Pro" and the MonsterInsights cross-sell. Rendered in the vendor's own UI, same verdict as MonsterInsights' inbox |
| "NEW!" indicators and submenus | `admin_menu` | out of scope | The vendor's own menu |
| Deactivation survey | `UserFeedback_AM_Deactivation_Survey`, Plugins screen | keep | Modal on deactivation, not the notice area. Same verdict as `brainstorm-force.md` and `cookie-law-info.md` |
| Usage tracking | `userfeedback_usage_tracking_cron` | not ours | Opt-in, no notice, never sends unless `allow_usage_tracking` is set. Outbound blocking belongs in headwall-hosting |
| Notifications feed fetch | `userfeedback_admin_notifications_update` (WP-Cron) | not ours, fixed in headwall-hosting | See below |

## Deliberately left alone

### The notifications feed warning

`fetch_feed()` runs only from the single WP-Cron event `userfeedback_admin_notifications_update`.
`get_all_notifications()` schedules that event, from the admin menu badge, whenever the stored
`update` timestamp is more than a day old. On 4 Nov 2026 the feed URL returned 404 to a
WordPress user-agent and a Cloudflare 403 HTML page to curl's. The body is non-empty but not
JSON, so `json_decode()` returns null and the `foreach` on line 236 warns. A `WP_Error`, by
contrast, takes the vendor's own `return array()` branch.

**Unhooking the cron callback was rejected.** `is_admin_page_request()` bails on cron, so
it would need a second always-on exception. Worse, `update_notifications()` is the only thing
that writes `update => time()`. Without it the timestamp stays stale, and every admin page
load would reschedule the event, swapping one daily request for a cron spawn per admin view.

**The fix lives in headwall-hosting instead.** Paul added the URL to
`block_unwanted_http_calls()` on `pre_http_request`. The fetch then gets a `WP_Error`, the
vendor writes an empty feed and a fresh timestamp, and the daily cadence is unchanged, with
no warning and no outbound call.

### `hide_am_notices`: not used

UserFeedback reads it only in `review_request()`, so here its scope would be correct, unlike
in MonsterInsights. But it is a key of the `userfeedback_settings` array, so answering it
means filtering the whole option. `userfeedback_save_options()` (the settings screen) and
`userfeedback_update_option()` (single keys, including the tracking opt-in and opt-out
handlers) both read the whole array back through `get_option()` and write it whole. The
forced value would be written to the database the next time anything saves a setting.

### `pre_option_userfeedback_review`: not used

`review()` skips on `$review['dismissed']`, so an answered array would also work and write
nothing. It was passed over for uniformity: it needs a bespoke method, and the identical
`MonsterInsights_Review` and `ExactMetrics_Review` rules already use the reader for this
class shape. An agent extending the family should find one pattern, not two.

### Removing the dashboard widget: not used

`remove_meta_box()` would take the site's survey entry counts with it. The filter removes
only the Duplicator line.

### The onboarding wizard's recommended plugins

`onboarding.js` offers WP Mail SMTP, AIOSEO and Uncanny Automator as opt-in checkboxes in the
vendor's own setup wizard. It reads its own `recommended_plugins` group, not the
`recommended_plugin_*` keys, so the filter does not touch it. Out of scope either way.

## Mechanism

- **Review request:** mechanism 2 through `remove_discarded_instance_callback( 'admin_notices', 'UserFeedback_Review', 'review_request', 'userfeedback-lite' )`, called from `unhook_vendor_notices()` (late `admin_init`)
  - vendor registers at: `class-userfeedback-review.php` constructor, at include time, from `UserFeedback::require_files()` on `plugins_loaded`
  - instance reachable via: nothing. The file ends `new UserFeedback_Review();`, and `UserFeedback()` keeps no reference to it
- **Duplicator cross-sell:** mechanism 1, the vendor's `userfeedback_admin_script_localization` filter at `LATE_PRIORITY`, through `hide_userfeedback_recommended_plugin()`
  - sets `recommended_plugin_is_installed` and `recommended_plugin_is_activated` to true. The widget shows its Install line on `!is_installed` and its Activate line on `is_installed && !is_activated`, so both are skipped
  - only `dashboard-widget.js` reads those two keys (checked across every bundle in `assets/`). The data is never written back
  - the filter's other user, `UserFeedback_Plugin_Integration::register_integration`, adds its own key and is unaffected

## Drift check

- `includes/admin/class-userfeedback-review.php`: confirm the class name `UserFeedback_Review`, the method `review_request`, and that the file still ends by discarding the instance. If `UserFeedback()` starts holding it, switch to `remove_action()` on that property
- `includes/admin/common.php`, `userfeedback_get_common_script_localization_object()`: confirm the filter name and both `recommended_plugin_is_*` keys. If the recommended plugin changes from Duplicator, the rule still holds, because it never names the plugin
- `assets/vue/js/dashboard-widget.js`: confirm the two computed tests (`recommendedPluginNotInstalled`, `recommendedPluginNotActivated`) still read those keys. Re-grep every bundle for `recommended_plugin_is` to confirm no new reader treats them as fact

## Verification

**Bench-confirmed on bench1 over HTTP, 4 Nov 2026.** UserFeedback Lite 1.12.0 from the vault,
`userfeedback_over_time.installed_date` backdated 15 days to open the 14-day gate, admin user
9 with a minted cookie. The 1.50.0 build was copied over the bundled
`mu-plugins/headwall-hosting/headwall-nag-cleanup.php`. `Plugin::VERSION` was asserted as
1.50.0, then the bundled file was restored and its sha256 checked.

| Probe on `wp-admin/index.php` (`id="dashboard-widgets"` present both times) | Before (1.48.0) | After (1.50.0) |
|---|---|---|
| `userfeedback-review-notice` | 2 (notice div + dismiss script) | 0 |
| `id="userfeedback-dashboard-widget"` | 1 | 1 |
| `recommended_plugin_is_installed` / `_is_activated` | `""` / `""` | `"1"` / `"1"` |

`wp_localize_script()` stringifies booleans, so `""` is false and `"1"` is true. The Duplicator
line itself is drawn in the browser and was not captured. The probe is the script data the
widget tests. Negative check: `admin.php?page=userfeedback_surveys` still received its script
data (`"plugin_version":"1.12.0"`).

**Vendor uninstaller fatals under WP-CLI.** `uninstall_hook()` defines `WP_ADMIN` and
constructs `UserFeedback_License_Actions`, a class that `require_files()` does not load:
`Class "UserFeedback_License_Actions" not found` at `userfeedback-base.php:534`. bench1 was
cleaned by hand from a snapshot diff: 7 `wp_userfeedback_*` tables, 6 `userfeedback_*`
options plus Action Scheduler's `as_has_wp_comment_logs`, and 2 cron hooks. Even a working
uninstaller would not drop the tables. Yoast's `wpseo_start_cleanup_indexables` single event,
scheduled by the plugin deactivation, was left for Yoast to run.

## Additions to `headwall-nag-cleanup.php` (1.50.0)

```php
// register_vendor_optouts()
add_filter( 'userfeedback_admin_script_localization', [ $this, 'hide_userfeedback_recommended_plugin' ], self::LATE_PRIORITY );

// unhook_vendor_notices() → unhook_userfeedback_review_request()
$this->remove_discarded_instance_callback( 'admin_notices', 'UserFeedback_Review', 'review_request', 'userfeedback-lite' );
```
