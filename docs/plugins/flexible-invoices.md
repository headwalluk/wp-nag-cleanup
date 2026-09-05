# Flexible Invoices for WooCommerce and WordPress

- slug: `flexible-invoices`
- version analysed: `6.2.27`
- source: `/vault/backups/wordpress/plugins/flexible-invoices/flexible-invoices,6.2.27.zip`
- licensing: freemium (free on wordpress.org, PRO sold at flexibleinvoices.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5).

Flexible Invoices is published by WP Desk, and almost none of the promotional
surfaces found here belong to Flexible Invoices itself. They come from two shared
Composer packages, `wpdesk/ltv-dashboard-widget` and `wpdesk/wp-wpdesk-tracker`,
which WP Desk bundles (Mozart-prefixed into `vendor_prefixed/`) in every plugin
they ship. The dashboard widget is titled "Grow your business with WP Desk", is
registered at `'normal'` context with `'high'` priority so it takes the top-left
slot ahead of Site Health, and fetches a plugin catalogue from `wpdesk.net` on
render. The tracker adds a usage-data opt-in notice, a deactivation survey, and a
weekly payload send. Both packages expose a documented opt-out filter, so both
rules are mechanism 1.

Everything Flexible Invoices emits in its own right — settings-saved confirmations,
bulk-action results, the PHP version warning — is operational and untouched.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 8 found. 1 promotional (tracker opt-in), 7 operational. No `network_admin_notices` or `all_admin_notices` registrations at all |
| Vendor opt-out filters | 2 relevant: `wpdesk/ltvdashboard/disable`, `wpdesk_tracker_enabled`. Also `wpdesk_tracker_do_not_ask` (narrower, covers only the deactivation survey) and `wpdesk_tracker_notice_screens` (narrower, screen allow-list) |
| Vendor opt-out constants | None |
| Dashboard widgets | 1: `wp_add_dashboard_widget()` in `ltv-dashboard-widget/src/DashboardWidget.php:107`, widget ID `flexible-invoices` |
| Outbound calls from widgets | `https://www.wpdesk.net?wpdesk_api=1&t=1` (`www.wpdesk.pl` under `pl_PL`), `sslverify => false`, cached 24h / 6h on failure. Tracker posts separately; marketing boxes call `marketing.wpdesk.org` from the vendor's own Support page only |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| "Grow your business with WP Desk" dashboard widget | `wp_dashboard_setup` → widget ID `flexible-invoices` | suppress | Pure cross-selling. Renders a remote catalogue of other WP Desk products with "Buy now" buttons and UTM-tagged links. No site state in it |
| Usage-tracking opt-in notice | `admin_notices` → `WPDesk_Tracker::admin_notices` | suppress | Usage-tracking opt-in prompt, named on the suppress list in `CLAUDE.md` |
| Deactivation survey | `plugins.php` script + `wpdesk_tracker_deactivate` page | suppress | Interstitial asking why you deactivated. Carries no site state |
| Opt-in redirect on activation | `activated_plugin` → `wp_redirect()` to `admin.php?page=wpdesk_tracker` | suppress | Hijacks the post-activation screen to ask for tracking consent |
| Weekly tracking payload | `wpdesk_tracker_send_event` → `wp_remote_post` | suppress | Outbound request carrying store settings, order counts, plugin list, theme, server and licence emails |
| Settings saved confirmation | `admin_notices` → `GeneralSettingsMenu`, `ReportsMenu`, `KSeFDummyMenu::show_settings_saved_notice` | keep | Operational: confirms the save the user just made |
| Bulk action result | `admin_notices` → `BulkActions::bulk_notice` | keep | Operational: "N invoices marked as paid" |
| PHP < 5.3 warning | `admin_notices` (php52 bootstrap) | keep | PHP version warning. Never suppressed |
| Tracker opt-out confirmation | `admin_notices` → `Tracker\OptOut::handle_opt_out` | keep | Confirms a consent change the user requested |
| "Upgrade to PRO" settings-field links | Rendered inside settings fields | keep | On the vendor's own settings screens. Out of scope by construction |
| Marketing boxes and rate-plugin box | `SupportMenuPage` | keep | On the vendor's own Support page. Out of scope by construction |

## Deliberately left alone

**The vendor's own screens.** `wpdesk/wp-wpdesk-marketing` renders remote marketing
boxes from `marketing.wpdesk.org` and a "rate this plugin" box, and several settings
fields render "Upgrade to PRO and enable options below →" links. All of it is
promotional and none of it is touched: it renders only inside WP Desk's own Support
and settings pages, never in the notice area or on the dashboard. `CLAUDE.md` puts
the vendor's own screens out of scope by construction — a user on WP Desk's Support
page has gone looking for WP Desk.

**Settings-saved and bulk-action notices.** Three menu classes and `BulkActions`
register `admin_notices` callbacks. They report the outcome of an action the user
just took. Operational.

**The PHP < 5.3 notice.** `wp-plugin-flow-common/src/plugin-init-php52.php` registers
a notice on the `else` branch of a PHP version check. It is a PHP version warning and
is on the never-suppress list. It is also unreachable on any supported host, but the
rule does not depend on that.

**The tracker opt-out confirmation notice.** `Tracker\OptOut::handle_opt_out` prints
a success notice after the user opts out via a nonce-checked link. It confirms a
change the user asked for.

**`remove_meta_box()` was rejected for the dashboard widget.** It would work, but the
widget ID is `self::ID`, which each WP Desk plugin sets to its own slug —
`flexible-invoices` here, `flexible-checkout-fields` in that plugin. The package also
guards registration with a mutex filter, `wpdesk/ltvdashboard/initialized`, so on a
site running several WP Desk plugins exactly one registers the widget and *which one*
depends on load order. A rule naming a widget ID would therefore be correct on some
sites and silently inert on others. The vendor filter has no such problem.

**`wpdesk_tracker_do_not_ask` and `wpdesk_tracker_notice_screens` were rejected as
too narrow.** `wpdesk_tracker_do_not_ask` covers only the deactivation survey and the
activation redirect, not the notice. `wpdesk_tracker_notice_screens` suppresses the
notice by returning an empty screen allow-list, which is an abuse of a filter meant
for widening, not disabling. Neither stops the payload send. `wpdesk_tracker_enabled`
is the switch the vendor uses themselves.

### Collateral of `wpdesk_tracker_enabled`, named and accepted

`should_enable_wpdesk_tracker()` also gates the `wpdesk_tracker_opt_out` branch of
`WPDesk_Tracker::admin_notices` (`class-wpdesk-tracker.php:326`), which writes
`wpdesk_tracker_agree = '0'` when that query arg is present. With the filter false,
a site that had previously opted *in* will no longer have the stored consent flag
flipped by that link.

Accepted, because `send_tracking_data()` returns early on the same check
(`class-wpdesk-tracker.php:437`). The stored flag becomes stale but inert: no payload
is sent either way. The net effect is strictly less tracking, never more.

Verified that the filter gates no operational path — it appears only in the opt-in
notice, the deactivation survey, the plugin action link and the send. Licensing,
updates and schema handling live in different packages and do not consult it. The
`wpdesk_tracker` submenu pages are registered unconditionally under a parent slug
that does not exist, so no menu entry changes.

## Mechanism

Two rules, both tier 1.

**Dashboard widget**

- tier: 1 (vendor hook)
- phase: file scope
- vendor registers at: `Plugin.php:117`, inside an `admin_init` closure calling
  `( new DashboardWidget() )->hooks()`, which reads the filter before adding its
  `wp_dashboard_setup` action
- instance reachable via: N/A
- note: the vendor tests `=== true`, so `__return_true` is required; a truthy
  non-boolean would not match

**Tracker**

- tier: 1 (vendor hook)
- phase: file scope, **priority 999**
- vendor registers at: `Plugin.php:102`, inside a `plugins_loaded` priority 1 closure,
  and only when WooCommerce is active
- instance reachable via: N/A
- note: `src/Tracker/UsageDataTracker.php:26` registers its own
  `wpdesk_tracker_enabled` callback that returns `true` unconditionally, ignoring the
  incoming value, at default priority 10. A default-priority opt-out registered from
  an mu-plugin runs *first* and is then overwritten. Priority 999 is load-bearing

## Drift check

Re-check when a new version appears in the vault:

- `vendor_prefixed/wpdesk/ltv-dashboard-widget/src/DashboardWidget.php` — the
  `apply_filters('wpdesk/ltvdashboard/disable', false) === true` guard at the top of
  `hooks()`. If the filter name changes or the strict comparison is dropped, the rule
  needs revisiting
- `vendor_prefixed/wpdesk/wp-wpdesk-tracker/src/class-wpdesk-tracker.php` —
  `should_enable_wpdesk_tracker()` and the sites that call it, currently lines 169,
  199, 326 and 437. If a licensing or update path starts consulting it, the rule must
  be withdrawn
- `src/Tracker/UsageDataTracker.php` — the `wpdesk_tracker_enabled` callback returning
  `true`. If WP Desk raises its priority above 999, ours must go higher

Both packages are shared across the WP Desk range. Verified present in
Flexible Shipping 6.12.1 and Flexible Checkout Fields 4.1.41, so these two rules
cover every WP Desk plugin on the fleet, not just Flexible Invoices.

## Additions to `headwall-nag-cleanup.php`: 2 rules, both mechanism 1

```php
// WP Desk ltv-dashboard-widget 1.x, bundled in every WP Desk plugin.
// Verified against Flexible Invoices 6.2.27.
// docs/plugins/flexible-invoices.md
add_filter( 'wpdesk/ltvdashboard/disable', '__return_true' );
$this->log( 'wpdesk-ltv-dashboard', 'Registered wpdesk/ltvdashboard/disable opt-out.' );

// WP Desk wp-wpdesk-tracker, bundled in every WP Desk plugin. Gates the
// usage-tracking opt-in notice, the deactivation survey and the payload send.
// Verified against Flexible Invoices 6.2.27.
// docs/plugins/flexible-invoices.md
//
// Priority 999: UsageDataTracker::hooks() adds its own callback returning
// true at priority 10, so a default-priority opt-out here is overwritten.
add_filter( 'wpdesk_tracker_enabled', '__return_false', 999 );
$this->log( 'wpdesk-tracker', 'Registered wpdesk_tracker_enabled opt-out.' );
```
