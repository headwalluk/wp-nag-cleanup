# ExactMetrics (Google Analytics Dashboard for WP)

- slug: `google-analytics-dashboard-for-wp`
- version analysed: `10.3.0`
- source: `/vault/backups/wordpress/plugins/google-analytics-dashboard-for-wp/google-analytics-dashboard-for-wp,10.3.0.zip`
- licensing: freemium (ExactMetrics Lite on wordpress.org, ExactMetrics Pro sold at exactmetrics.com)
- Freemius bundled: no

## Analysis

Analysed on 27 Sep 2026 by Claude Code (Claude Opus 5), from two nags Paul reported on a
live client site running 10.3.0: the WPConsent cross-sell notice and the "Get Better
Insights. Grow FASTER!" upsell bubble pinned to the admin menu.

**Three rules added, all mechanism 2, in `unhook_exactmetrics_promos()`.** ExactMetrics
is Awesome Motive's second Google Analytics plugin, and it is **MonsterInsights' codebase
renamed**: `monsterinsights_` becomes `exactmetrics_`, `MonsterInsights_` becomes
`ExactMetrics_`, file layout identical. Every finding in
[`google-analytics-for-wordpress.md`](google-analytics-for-wordpress.md) was re-checked
against this source rather than assumed, and each one carries over, including the
`hide_am_notices` trap. Read that document for the full reasoning; this one records what
was verified here and anything that differs.

The rename is incomplete in one place that matters for probing: the tooltip's element id
is still `monterinsights-admin-menu-tooltip` — MonsterInsights' prefix **and** its typo —
while every class on it is `exactmetrics-`. The rule matches the function name, so it is
unaffected.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **14**, the same set as MonsterInsights: setup/licence/version dispatcher (on both notice hooks, registered twice on network), review, WPConsent, Pro-conflict, PHP and WP version, measurement-protocol, two addon-deprecation, two addon-superseded, screen-clearing on own pages. Multiline pass: no notice hooks |
| Vendor opt-out filters | `exactmetrics_get_option_hide_am_notices` (via `exactmetrics_get_option()`, **rejected — same trap as MonsterInsights**), `exactmetrics_show_dashboard_widget` (multisite only, gates the analytics widget). None for the tooltip, review nag or WPConsent notice |
| Vendor opt-out constants | `EXACTMETRICS_DISABLE_TRACKING`, `EXACTMETRICS_VERSION_NOTICE_ACTIVE`, `EM_NO_TRACKING_OPTOUT`. None promotional |
| Dashboard widgets | **1**: `lite/includes/admin/dashboard-widget.php` — the site's own Google Analytics report. Kept |
| Outbound calls from widgets | `https://app.exactmetrics.com/` (relay for the site's own analytics). Kept with the widget |
| Freemius | Not bundled |

Extra sweep of `adminmenu`, `admin_head`, `admin_footer`, `in_admin_footer` and
`admin_menu`, as for MonsterInsights: found the tooltip, the WooCommerce Marketing card,
the deactivation survey and the footer credit.

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Admin menu upsell tooltip | `adminmenu` → `exactmetrics_get_admin_menu_tooltip` | **suppress** | "Grow Your Business with ExactMetrics Pro", 50% off. Pure upsell. **Reported by Paul** |
| WPConsent cross-sell | `admin_notices` → `exactmetrics_wpconsent_install_notice` | **suppress** | Cross-sell of a sister plugin. Same decision as MonsterInsights, see below. **Reported by Paul** |
| Review request | `admin_notices` → `ExactMetrics_Review::review_request` | **suppress** | Review begging, 14 days after connecting |
| Setup / licence / version dispatcher | `admin_notices`, `network_admin_notices` → `exactmetrics_admin_setup_notices` | keep — **mixed** | Same chain as MonsterInsights; Woo and EDD upsells are inline in it with licence and PHP-version notices |
| Lite-alongside-Pro warning | `admin_notices` → `ExactMetrics_Lite::exactmetrics_pro_notice` | keep | **Plugin conflict notice. Never suppressed** |
| PHP / WP version warnings | `admin_notices` → `ExactMetrics_Compatibility_Check::display_php_notice`, `display_wp_notice` | keep | **Version warnings. Never suppressed** |
| Measurement Protocol secret blank | `admin_notices` → `exactmetrics_empty_measurement_protocol_token` | keep | Real configuration gap |
| Addon deprecated / superseded (FB Instant Articles, Google Optimize, Ads, AI Insights) | `admin_notices` → `_exactmetrics_notice_deprecated_*`, `exactmetrics_ads_addon_installed_notice`, `exactmetrics_ai_insights_addon_installed_notice` | keep | Each gated on the addon being installed. Ads is the ambiguous one, as for MonsterInsights |
| Analytics dashboard widget | `wp_dashboard_setup` → `lite/includes/admin/dashboard-widget.php` | keep | The site's own Google Analytics data |
| WooCommerce Marketing card | `admin_footer` → `ExactMetrics_WooCommerce_Marketing::output_analytics_card_template` | keep | See below |
| Promo menu items | `admin_menu` → `exactmetrics_admin_menu`, `exactmetrics_woocommerce_menu_item` | keep | Menu items. Out of scope by construction |
| Deactivation survey modal | `admin_footer` → `ExactMetrics_AM_Deactivation_Survey::modal` | keep | Only on a deactivation click the site owner initiated |
| Footer credit | `in_admin_footer` → `exactmetrics_in_admin_footer` | keep | "Made with ♥ by the ExactMetrics Team" on the vendor's own screens |
| Notice clearing on own screens | `admin_notices` priority 0 → `ExactMetrics_Rest_Routes::hide_old_notices`; `admin_head` → `hide_non_exactmetrics_warnings` | keep | The vendor tidying its own screens |

## Deliberately left alone

### `hide_am_notices` is the same trap as in MonsterInsights

`review_request()` returns early on `exactmetrics_get_option( 'hide_am_notices' )`, and
`exactmetrics_get_option()` ends in `apply_filters( 'exactmetrics_get_option_' . $key, … )`,
so a one-line mechanism 1 rule is available. **Not used, because** the vendor's settings
screen describes the switch, verbatim, in
`lite/assets/vue3/js/chunks/exactmetrics-SettingsTabAdvanced-BOFqQfH-.js`:

> Hides plugin announcements and update details. This includes critical notices we use to
> inform about deprecations and important required configuration changes.

### The WPConsent notice — decision carried over, not re-litigated

`exactmetrics_wpconsent_install_notice` is byte-for-byte the MonsterInsights notice with
the prefix changed. Same gates: not on `update-core.php`, WPConsent not active, no other
CMP plugin active (`exactmetrics_wpconsent_is_cmp_plugin_active()`), not dismissed
(`exactmetrics_wpconsent_notice_dismissed`), and authenticated. Paul decided the
MonsterInsights case on 10 Sep 2026: it advertises software the site is not running and
reports no fault it detected, so it is a cross-sell. That reasoning applies unchanged. See
[`google-analytics-for-wordpress.md`](google-analytics-for-wordpress.md#the-wpconsent-notice--the-judgement-call).

### `exactmetrics_admin_setup_notices` is mixed output

Same priority chain as MonsterInsights: UA-sunset / no-tracking alert, not-connected,
licence missing / expired / invalid, PHP version, manual GA4 ID reauth, then the
WooCommerce and EDD upsells as inline `echo` blocks. There is no separate callback holding
the upsells, so the function stays whole.

### The WooCommerce Marketing card

`lite/includes/admin/woocommerce-marketing.php` enqueues a script and prints an analytics
card, only on `woocommerce_page_wc-admin` with `path=/marketing` and only for Lite. That
screen is WooCommerce's own marketing hub, which exists to list marketing extensions; a
card there is the integration surface Woo provides, not an overlay on the site owner's
work. It never reaches the notice area or any other screen. Left alone.

### Dashboard widget and menu items

As for MonsterInsights: the widget is the site's own analytics, and menu items are the
vendor's own menu. `lite/includes/admin/dashboard-widget.php` has a commented-out
`admin_footer` → `load_notice` registration; it is dead code.

## Mechanism

Three rules, all mechanism 2, in `unhook_exactmetrics_promos()`.

- tier: 2 (targeted unhook) ×3
- phase: `admin_init`, `self::LATE_PRIORITY`
- vendor registers at: `plugins_loaded` → `ExactMetrics()` → `ExactMetrics_Lite::get_instance()`
  → `require_files()` loads `includes/admin/admin.php` and `includes/admin/review.php`. The
  tooltip's `lite/includes/admin/helpers.php` is loaded from an `init` **priority 0**
  closure in `lite/includes/load.php`. All before `admin_init`; `adminmenu` fires later,
  while the menu is painted. Identical to MonsterInsights, where this chain is live-confirmed
- instance reachable via: N/A for the tooltip and the WPConsent notice (plain named global
  functions). **Not reachable** for the review nag: `review.php` ends
  `new ExactMetrics_Review();`, no `get_instance()`, not held on `ExactMetrics()`. It goes
  through `remove_discarded_instance_callback()`, with the same checks as MonsterInsights —
  mechanism 1 rejected on its merits, no singleton, no delegation, `review_request()`
  prints one thing only
- gate: `defined( 'EXACTMETRICS_VERSION' )`, set in `define_globals()`. When ExactMetrics
  Pro is also active, Lite returns before `define_globals()` after registering its
  Pro-conflict notice, so none of Lite's targets are registered

The tooltip lives in `lite/` only. On ExactMetrics Pro the tooltip line logs "not
registered", which is correct.

## Drift check

- `lite/includes/admin/helpers.php` — `exactmetrics_get_admin_menu_tooltip` on
  `adminmenu`, default priority. Element id `monterinsights-admin-menu-tooltip`; if the
  vendor fixes the prefix, bench probes break, the rule does not
- `includes/admin/admin.php` — `exactmetrics_wpconsent_install_notice`. A rename silently
  no-ops the rule
- `includes/admin/review.php` — class `ExactMetrics_Review`, method `review_request`. The
  file carries a vendor TODO, "Check if this class is actually working"; if it is removed,
  the reader logs "not found", which is the drift signal
- The `Hide Announcements` description in `exactmetrics-SettingsTabAdvanced-*.js`
- When MonsterInsights' rules change, check whether ExactMetrics needs the same change

## Verification

| Rule | Result |
|---|---|
| `exactmetrics_get_admin_menu_tooltip` on `adminmenu` | **Confirmed gone.** Rendering on the reporting client site before, absent after Paul deployed 1.36.0, 27 Sep 2026 |
| `exactmetrics_wpconsent_install_notice` | **Confirmed gone.** Same site, same deploy, 27 Sep 2026 |
| `ExactMetrics_Review::review_request` | **Source-verified only.** Not reported rendering |

Both confirmed rules were seen rendering on the reporting site before the deploy, which
also showed the tooltip's 30-day gate had passed and a GA4 property was connected. For
future checks, probe structurally: `id="monterinsights-admin-menu-tooltip"` (vendor typo) and
`id="exactmetrics-wpconsent-notice"`. Negative check: `exactmetrics_admin_setup_notices`
output and the dashboard widget should be unchanged.

Review nag gates for a bench: `exactmetrics_over_time['connected_date']` more than 14 days
old, a GA4 ID set, `exactmetrics_review['dismissed']` false and `['time']` more than a day
old, and `is_super_admin()`.

## Additions to `headwall-nag-cleanup.php`: 3 rules, mechanism 2

```php
public function unhook_exactmetrics_promos() : void {
	if ( ! defined( 'EXACTMETRICS_VERSION' ) ) {
		// Not installed.
	} else {
		if ( false === has_action( 'adminmenu', 'exactmetrics_get_admin_menu_tooltip' ) ) {
			$this->log( 'exactmetrics', 'exactmetrics_get_admin_menu_tooltip not registered on adminmenu; no action taken.' );
		} else {
			remove_action( 'adminmenu', 'exactmetrics_get_admin_menu_tooltip' );
			$this->log( 'exactmetrics', 'Removed exactmetrics_get_admin_menu_tooltip from adminmenu.' );
		}

		if ( false === has_action( 'admin_notices', 'exactmetrics_wpconsent_install_notice' ) ) {
			$this->log( 'exactmetrics', 'exactmetrics_wpconsent_install_notice not registered on admin_notices; no action taken.' );
		} else {
			remove_action( 'admin_notices', 'exactmetrics_wpconsent_install_notice' );
			$this->log( 'exactmetrics', 'Removed exactmetrics_wpconsent_install_notice from admin_notices.' );
		}

		$this->remove_discarded_instance_callback( 'admin_notices', 'ExactMetrics_Review', 'review_request', 'exactmetrics' );
	}
}
```
