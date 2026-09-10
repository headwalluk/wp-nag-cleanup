# Google Analytics for WordPress by MonsterInsights

- slug: `google-analytics-for-wordpress`
- version analysed: `11.2.0`
- source: `/vault/backups/wordpress/plugins/google-analytics-for-wordpress/google-analytics-for-wordpress,11.2.0.zip`
- licensing: freemium (Lite on wordpress.org, MonsterInsights Pro sold at monsterinsights.com)
- Freemius bundled: no

## Analysis

Analysed on 10 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul hit on logging in
to a client site: a floating upsell bubble pinned to the "Insights" admin menu item.

**Three rules added, all mechanism 2.** MonsterInsights is a heavy user of the notice area
— fifteen `admin_notices` registrations — but almost all of it is operational, and the one
promotional thing that looks easiest to switch off turned out to be a trap. This is a
plugin where the boundary rule did most of the work.

The reported nag is not an admin notice at all. `monsterinsights_get_admin_menu_tooltip()`
hangs off **`adminmenu`**, the hook core fires while painting the admin menu, and prints an
absolutely-positioned `<div id="monterinsights-admin-menu-tooltip">` (the vendor's typo,
not ours — `monter`, and it matters, see Drift check) that JavaScript then anchors to the
Insights menu item. It is a first for this project: a nag that never enters the notice
area, so no amount of work on `admin_notices` would have touched it.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **15**. One dispatcher printing nine notices, plus review, WPConsent, two addon-deprecation, two Pro-conflict, one measurement-protocol and one screen-clearing callback |
| Vendor opt-out filters | `monsterinsights_get_option_hide_am_notices` (**rejected — see below**), `monsterinsights_show_dashboard_widget` (multisite only, gates the analytics widget), `monsterinsights_admin_notifications_has_access`. None for the tooltip, the review nag or the WPConsent notice |
| Vendor opt-out constants | `MONSTERINSIGHTS_DISABLE_TRACKING`, `MONSTERINSIGHTS_VERSION_NOTICE_ACTIVE`, `MI_NO_TRACKING_OPTOUT`. None promotional |
| Dashboard widgets | **1**: `monsterinsights_reports_widget` — the site's own Google Analytics report. Kept |
| Outbound calls from widgets | `https://app.monsterinsights.com/` (relay for the site's own analytics). Kept with the widget |
| Freemius | Not bundled |

Extra pass, prompted by the tooltip being on `adminmenu` rather than `admin_notices`:
`in_admin_header`, `admin_head`, `admin_footer` and `adminmenu` were swept for promotional
output. That found the tooltip, the seasonal menu items and the deactivation survey.

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Admin menu upsell tooltip | `adminmenu` → `monsterinsights_get_admin_menu_tooltip` | **suppress** | "Grow Your Business with MonsterInsights Pro", 50% off. Pure upsell, no site state |
| Review request | `admin_notices` → `MonsterInsights_Review::review_request` | **suppress** | Review begging, 14 days after connecting |
| WPConsent cross-sell | `admin_notices` → `monsterinsights_wpconsent_install_notice` | **suppress** | Cross-sell of a sister plugin. See below — this one was a judgement call |
| Setup / licence / version dispatcher | `admin_notices` → `monsterinsights_admin_setup_notices` | keep — **mixed** | See below |
| Lite-alongside-Pro warning | `admin_notices` → `MonsterInsights_Lite::monsterinsights_pro_notice` | keep | **Plugin conflict notice. Never suppressed** |
| PHP / WP version warnings | `admin_notices` → `MonsterInsights_Compatibility_Check::display_php_notice`, `display_wp_notice` | keep | **Version warnings. Never suppressed** |
| Measurement Protocol secret blank | `admin_notices` → `monsterinsights_empty_measurement_protocol_token` | keep | Real configuration gap on this site: eCommerce/Forms tracking is silently incomplete without it |
| Facebook Instant Articles addon deprecated | `admin_notices` → `_monsterinsights_notice_deprecated_facebook_instant_articles` | keep | Deprecation of an addon **actually installed here** |
| Google Optimize addon deprecated | `admin_notices` → `_monsterinsights_notice_deprecated_google_optimize` | keep | As above |
| Ads addon superseded by PPC Tracking | `admin_notices` → `monsterinsights_ads_addon_installed_notice` | keep — **ambiguous** | Gated on `class_exists( 'MonsterInsights_Ads' )`. Reports a real installed addon being superseded, though the Lite branch is an upsell |
| AI Insights addon replaced by AI Charlie | `admin_notices` → `monsterinsights_ai_insights_addon_installed_notice` | keep | Gated on the addon being installed. "Deactivate this, it is no longer needed" |
| Analytics dashboard widget | `wp_dashboard_setup` → `monsterinsights_reports_widget` | keep | The site's own Google Analytics data — the reason the plugin is installed |
| Rotating promo submenu | `admin_menu` → `monsterinsights_get_rotating_promo_submenu` | keep | Vendor's own menu. Out of scope by construction |
| Seasonal promo menu items | `admin_menu` → `monsterinsights_automated_menu` | keep | Vendor's own menu, **and every date window closed in 2023** |
| Notifications inbox | `wp_ajax_monsterinsights_vue_get_notifications` | keep | Inside MonsterInsights' own Vue UI, not the notice area |
| Deactivation survey modal | `admin_footer` → `MonsterInsights_AM_Deactivation_Survey::modal` | keep | Only fires on a deactivation click the site owner initiated |
| Notice clearing on own screens | `admin_notices` priority 0 → `Routes::hide_old_notices` | keep | The vendor tidying its own settings screen |

## Deliberately left alone

### The vendor's own opt-out switch is a trap — this is the important finding

`hide_am_notices` looks like the perfect mechanism 1 rule. It is read through
`monsterinsights_get_option()`, which ends with:

```php
return apply_filters( 'monsterinsights_get_option_' . $key, $value, $key, $default );
```

so `add_filter( 'monsterinsights_get_option_hide_am_notices', '__return_true' )` is one
line and would kill the review nag outright.

It was rejected because of what the vendor's own settings screen says the switch does.
From `lite/assets/vue3/js/chunks/monsterinsights-SettingsTabAdvanced-DKNAOKuR.js`:

> **Hide Announcements** — Hides plugin announcements and update details. **This includes
> critical notices we use to inform about deprecations and important required
> configuration changes.**

The vendor is stating in writing that this switch suppresses deprecation and
required-configuration notices. Flipping it silently on a client's behalf is exactly the
failure mode `CLAUDE.md` exists to prevent, and it would have looked like a clean, cheap,
sanctioned mechanism 1 rule right up until a required configuration change went unseen.

**Read what a vendor's opt-out switch claims to cover before using it.** A documented
filter is the preferred mechanism, not an automatically safe one.

### `monsterinsights_admin_setup_notices` is mixed output, and mostly operational

One 300-line function on both `admin_notices` and `network_admin_notices`, structured as a
priority chain of `if … return`. In order:

| # | Notice | Character |
|---|---|---|
| 0 | "Urgent: Your Website is Not Tracking Any Google Analytics Data!" — UA sunset, no GA4 property | **Operational** |
| 1 | Not connected to Google Analytics | Operational, with marketing padding |
| 2 | Pro: no licence key entered — "not getting updates" | **Licence. Never suppressed** |
| 3 | Pro: licence expired / disabled / invalid | **Licence. Never suppressed** |
| 4 | PHP version below required / warning / recommended | **Version warning. Never suppressed** |
| 6 | Manual GA4 ID in use, reauthentication needed | Operational |
| 8 | WooCommerce upsell, plugins screen only | Promotional |
| 9 | Easy Digital Downloads upsell, plugins screen only | Promotional |
| — | Cross-domain settings were migrated, review the custom code field | **Operational — data migration** |

Items 2, 3, 4 and the cross-domain migration prompt are all named explicitly on the
never-suppress list. There is no public method holding the promotional half — 8 and 9 are
inline `echo` blocks inside the chain, not separate callbacks — so the whole function
stays. Two surviving upsells that only appear on `plugins.php` to Lite users running Woo
or EDD is a far better outcome than a suppressed licence-expiry or migration notice.

### The WPConsent notice — the judgement call

`monsterinsights_wpconsent_install_notice` prints "Make Your Website Analytics Compliant
with Privacy Laws", pitching WPConsent, the vendor's sister plugin. Its gates are: not on
`update-core.php`, WPConsent not active, no other CMP plugin active, not previously
dismissed, and MonsterInsights authenticated.

**The case for keeping it:** on a UK/EU fleet, "you are running Google Analytics with no
consent mechanism" is a true and arguably actionable fact about the site, and the CMP
detection means it only fires when that is genuinely so. Under "when a rule is ambiguous,
it does not go in", that would be enough.

**The case for suppressing it, which won** — Paul's call, taken explicitly during this
analysis: it advertises software the site is *not* running, which is the textbook
"cross-selling other products" case. It reports no fault it detected; the CMP check is
cross-sell targeting, not diagnostics — the same shape as a backup plugin noticing you
have no backup plugin. The only action it offers is installing one specific vendor's
product, and the compliance framing is the pitch rather than the finding.

Recorded here so it is not re-litigated. If MonsterInsights ever changes this notice to
report an actual consent-configuration problem it detected — rather than the absence of
its sibling — the decision should be revisited.

### The addon-deprecation notices stay, including the one with an upsell in it

`monsterinsights_ads_addon_installed_notice` is the borderline one: for Lite users its
call to action is "Upgrade Now" pointing at monsterinsights.com. But it is gated on
`class_exists( 'MonsterInsights_Ads' )` — it only appears to sites that have the Ads addon
**installed and active** — and what it says is that the addon they are running has been
superseded by PPC Tracking. That is true and actionable about the state of their site.
Ambiguous, so it stays.

### The dashboard widget is the site's own data

`monsterinsights_reports_widget` renders this site's Google Analytics reports. It makes an
outbound call to `app.monsterinsights.com` on render, which elsewhere in this project has
been a reason to remove a widget — but here that call fetches the site's own analytics,
which is the entire point of installing the plugin. Not comparable to a vendor news feed.

Its unauthenticated state (`widget_content_no_auth()`) is a connect prompt, which is
configuration state, not a nag.

### The promotional menu items are the vendor's own menu

`monsterinsights_get_rotating_promo_submenu()` rotates a submenu entry under Insights every
14 days between UserFeedback, Privacy Compliance, SEO and RewardsWP.
`monsterinsights_automated_menu()` adds seasonal sale entries — Earth Day, Cinco De Mayo,
Summer Sale, Halloween. Both are `add_submenu_page()` calls inside the vendor's own menu,
which `CLAUDE.md` puts out of scope, and every one of the seasonal windows ends in 2023, so
the code is dead in any case.

The tooltip is different and is in scope precisely because it is *not* a menu item: it is
an overlay painted on every admin screen, positioned over whatever the site owner was
looking at.

## Mechanism

Three rules, all mechanism 2, in `unhook_monsterinsights_promos()`.

- tier: 2 (targeted unhook) ×3
- phase: `admin_init`, `self::LATE_PRIORITY`
- vendor registers at: `plugins_loaded` priority 10 → `MonsterInsights_Lite::get_instance()`
  → `require_files()` loads `includes/admin/admin.php` and `includes/admin/review.php`; the
  tooltip's `helpers.php` is loaded from an `init` **priority 0** closure in
  `lite/includes/load.php`. Everything is registered before `admin_init`, and `adminmenu`
  does not fire until the admin menu is painted, well after
- instance reachable via: N/A for the tooltip and the WPConsent notice (plain named global
  functions, removable by name). **Not reachable** for the review nag — see below

### The review nag needs the sanctioned `$wp_filter` reader — use nine

`includes/admin/review.php` ends with:

```php
new MonsterInsights_Review();
```

Constructed at file scope and discarded. The class has no `get_instance()`, and
`MonsterInsights()` does not hold it as a property, so `remove_action()` has nothing to
name. `remove_discarded_instance_callback()` is the route.

Mechanisms 1 to 3 were each checked properly first, as `CLAUDE.md` requires:

- **Mechanism 1** exists (`monsterinsights_get_option_hide_am_notices`) and was rejected on
  its merits, not overlooked — see the trap section above
- **Singleton accessor**: none on `MonsterInsights_Review`
- **Delegation to a reachable library**: none; `review_request()` is the class's own method
- **Mixed dispatcher with a public operational half**: not applicable. `review_request()`
  prints one thing, the review nag, and nothing else. Its only sibling registration is the
  `wp_ajax_monsterinsights_review_dismiss` handler, which is untouched
- **Mechanism 3**: not a dashboard widget

The other two are plain named functions, so the `has_action()` / `remove_action()` form is
used directly and no reader is involved.

## Drift check

Re-check when a new version appears in the vault:

- `lite/includes/admin/helpers.php` — the function name
  `monsterinsights_get_admin_menu_tooltip` and its `add_action( 'adminmenu', … )` at
  default priority. **Note the element id is `monterinsights-admin-menu-tooltip`**, missing
  the `s`, while every class on it is spelled `monsterinsights-`. If the vendor ever fixes
  that typo, any bench probe written against the id breaks — the rule does not, since it
  matches the function name
- `includes/admin/admin.php` — `monsterinsights_wpconsent_install_notice`. A renamed
  function silently no-ops the rule
- `includes/admin/review.php` — the class name `MonsterInsights_Review` and the method
  `review_request`. If the vendor ever adds a `get_instance()` **and calls it**, drop the
  reader for a direct `remove_action()`
- `lite/assets/vue3/js/chunks/monsterinsights-SettingsTabAdvanced-*.js` — the description
  of the **Hide Announcements** setting. If the vendor ever narrows it so it no longer
  covers deprecation and required-configuration notices, `hide_am_notices` becomes a clean
  mechanism 1 rule and the reader use can be retired
- `includes/admin/admin.php` — `monsterinsights_admin_setup_notices`. If the Woo and EDD
  upsells are ever split into their own callbacks, they become targetable
- `includes/admin/admin.php` — `monsterinsights_automated_menu`. Currently dead (all
  windows end 2023). If the vendor refreshes the dates, re-read whether menu items are
  still out of scope

## Verification

**The menu tooltip is confirmed gone on a live client site**, 10 Sep 2026, after Paul
deployed 1.24.0 to the site this audit came from. The other two rules are source-verified
only.

| Rule | Result |
|---|---|
| `monsterinsights_get_admin_menu_tooltip` on `adminmenu` | **Confirmed gone.** This was the reported nag: the upsell bubble pinned to the Insights menu item |
| `monsterinsights_wpconsent_install_notice` | **Source-verified only.** Not observed rendering before the deploy, so its absence afterwards is not evidence |
| `MonsterInsights_Review::review_request` | **Source-verified only.** Same — the review nag was not among what Paul reported seeing |

The tooltip result is worth more than one rule, because it also confirms the load order
reasoning for this vendor: `helpers.php` is required from an `init` **priority 0** closure,
the unhook runs on `admin_init` at `LATE_PRIORITY`, and `adminmenu` fires later still while
the menu is painted. A phase error anywhere in that chain would have left the bubble on the
page, and it did not.

It also confirms the vendor's 30-day install gate had elapsed on this site, which is what
made the tooltip visible in the first place and what a fresh bench would not reproduce.

### Still to do

The two remaining rules both need a site where the notice is actually rendering first —
otherwise a rule that removes something already absent looks identical to one that works.
Gates to backdate:

| Target | Gate |
|---|---|
| Review nag | `monsterinsights_over_time['connected_date']` more than **14 days** old, a GA4 ID set, `monsterinsights_review['dismissed']` false and `['time']` more than a day old |
| WPConsent notice | Authenticated, no CMP plugin active, `monsterinsights_wpconsent_notice_dismissed` unset. **Note this one cannot be reproduced on a site that already runs a consent plugin**, which much of the fleet does |

Both additionally require a connected GA4 property, which means a real authentication
against MonsterInsights' relay — the expensive part of testing this plugin, and the reason
these are deferred rather than skipped.

Probe structurally, not on copy: `id="monterinsights-admin-menu-tooltip"` for the tooltip
(with the vendor's typo), `id="monsterinsights-wpconsent-notice"` for the cross-sell, and
the review nag's `monsterinsights-review-dismiss` nonce for the review request.

No PHP fatals were reported on the client site, and the operational notices this rule set
must not touch — the UA-sunset alert, licence and PHP-version warnings, the
measurement-protocol prompt — were not reported missing. That is a weaker assertion than a
positive capture, and a bench A/B should still confirm
`monsterinsights_admin_setup_notices` output survives intact.

## Additions to `headwall-nag-cleanup.php`: 3 rules, mechanism 2

```php
public function unhook_monsterinsights_promos() : void {
	if ( ! defined( 'MONSTERINSIGHTS_VERSION' ) ) {
		// Not installed.
	} else {
		if ( false === has_action( 'adminmenu', 'monsterinsights_get_admin_menu_tooltip' ) ) {
			$this->log( 'monsterinsights', 'monsterinsights_get_admin_menu_tooltip not registered on adminmenu; no action taken.' );
		} else {
			remove_action( 'adminmenu', 'monsterinsights_get_admin_menu_tooltip' );
			$this->log( 'monsterinsights', 'Removed monsterinsights_get_admin_menu_tooltip from adminmenu.' );
		}

		if ( false === has_action( 'admin_notices', 'monsterinsights_wpconsent_install_notice' ) ) {
			$this->log( 'monsterinsights', 'monsterinsights_wpconsent_install_notice not registered on admin_notices; no action taken.' );
		} else {
			remove_action( 'admin_notices', 'monsterinsights_wpconsent_install_notice' );
			$this->log( 'monsterinsights', 'Removed monsterinsights_wpconsent_install_notice from admin_notices.' );
		}

		$this->remove_discarded_instance_callback( 'admin_notices', 'MonsterInsights_Review', 'review_request', 'monsterinsights' );
	}
}
```
