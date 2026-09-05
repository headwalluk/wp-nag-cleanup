# WP Swings — Gift Cards Lite and Subscriptions for WooCommerce

- slug: `woo-gift-cards-lite`, `subscriptions-for-woocommerce`
- version analysed: `3.2.10`, `2.0.2`
- source: `/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip`
- licensing: freemium (free on wordpress.org, Pro sold at wpswings.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on
`classicmotoringbooks.co.uk` — a **US Labor Day sale banner** with the `wps-offer-notice`
class.

4 fleet sites across the two plugins. **One rule added**, removing three named callbacks
that all render the same remotely-configured offer banner.

This is the first vendor audited whose promotional output is **driven from the vendor's
server**. The banner's image and link are not in the plugin: they are pulled from
`https://demo.wpswings.com/client-notification/woo-gift-cards-lite/wps-client-notify.php`
and stored in options, then rendered from those options. So the plugin ships with no
seasonal content at all and gains it later — which is why a seasonal sale appears on a
site whose plugin has not been updated.

### The framework question

Checked across all 1,129 distinct fleet slugs by opening each newest vault release and
testing the file list for `wpswings` or `wps-client-notify`. Two matched:

| Plugin | Sites |
|---|---|
| `subscriptions-for-woocommerce` | 3 |
| `woo-gift-cards-lite` | 1 |

Not a shared SDK in the `themeisle-sdk` sense, but the two plugins **share a callback
name**: `wps_banner_notification_plugin_html` is registered by both, each guarded with
`function_exists()`, so whichever plugin loads first defines it. One `remove_action()`
therefore covers both, and any future WP Swings plugin using the same pattern.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 6 in Gift Cards, 8 in Subscriptions. **Three promotional**, the rest operational |
| Vendor opt-out filters | **None** |
| Vendor opt-out constants | None. `WPS_GIFT_TEMPLATE_URL` is the remote notification endpoint, not a switch |
| Dashboard widgets | 2: `wps_gift_card_summary` and `wps_ai_subscription_health`. **Both site data** |
| Outbound calls | `demo.wpswings.com/client-notification/…/wps-client-notify.php`, which populates the banner options |
| Freemius | Not bundled |

## Findings

| Item | Callback | Verdict | Reason |
|---|---|---|---|
| Offer banner (shared) | `wps_banner_notification_plugin_html` | **suppress** | The Labor Day banner. Renders on `plugins`, `dashboard` and the vendor's own page |
| Offer banner (Gift Cards) | `wps_giftcard_notification_plugin_html` | **suppress** | Same `wps-offer-notice` markup, same banner options |
| Offer banner (Subscriptions) | `wps_sfw_banner_notification_html` | **suppress** | Same again |
| Gift Card Summary widget | `wps_gift_card_summary` | keep | The site's own gift card totals |
| AI Insights widget | `wps_ai_subscription_health` | keep | Subscription health data, and only registered once the owner has configured an AI provider |
| Setting notice on activation | `wps_wgm_setting_notice_on_activation` | keep | Post-activation setup prompt |
| Notification bar | `wps_wgm_display_notification_bar` | keep — **ambiguous** | Remotely driven, but scoped mostly to the vendor's own screens; content not established |
| Bulk coupon action notices | `wps_custom_coupon_bulk_action_notices` | keep | Operational bulk-action results |
| Activation failure | `wps_sfw_activation_failure_admin_notice` | keep | Dependency/activation error |
| Update notices ×3 | `wps_sfw_lite_add_updatenow_notice`, `wps_sfw_check_and_inform_update`, `wps_subscripition_plugin_updation_notice` | keep | Update prompts |
| Membership feature notice | `wps_sfw_membership_feature_notice` | keep — **ambiguous** | Probably promotional, but its content was not established. Ambiguous stays |

## Deliberately left alone

**Both dashboard widgets are site data.** `wps_gift_card_summary` renders the site's own
gift card figures; `wps_ai_subscription_health` renders subscription health and is only
registered when `wps_ai_provider()->get_config()` succeeds — that is, when the owner has
deliberately configured a provider. Neither is a catalogue or news feed, so neither is a
target, in the same category as Yoast's Posts Overview and Independent Analytics' widget.

**Two notices were left as ambiguous.** `wps_wgm_display_notification_bar` and
`wps_sfw_membership_feature_notice` both look promotional by name, but their rendered
content was not established during this audit. The rule is that ambiguous does not go in.
They are recorded here so a later pass starts from the right place rather than
re-discovering them.

**The remote endpoint is not blocked.** `wps-client-notify.php` is fetched to populate the
banner options. It would be possible to neutralise the banner by stopping that request,
but the same endpoint may carry other information, and this project removes notices rather
than intercepting a vendor's HTTP traffic. Removing the render callbacks means the banner
never displays regardless of what the endpoint returns.

## Mechanism

- tier: 2 (targeted unhook)
- phase: `admin_init`, priority 999. All four registrations are at **file scope** in the
  plugins' main files at default priority 10, so nothing re-adds them later
- instance reachable via: **N/A — all three are plain named functions**, so
  `remove_action()` names them directly. No singleton, no `$wp_filter`
- the loop skips any callback not registered on the current site, so a site running only
  one of the two plugins logs only what it actually removed

## Drift check

Re-check when a new version appears in the vault:

- `woocommerce_gift_cards_lite.php` and `subscriptions-for-woocommerce.php` — the
  `add_action( 'admin_notices', 'wps_*' )` lines. If a callback is renamed the rule
  silently stops, and the debug log will show one fewer removal
- If a third WP Swings plugin appears on the fleet, check whether it adds a fourth banner
  callback name
- `wps_wgm_display_notification_bar` and `wps_sfw_membership_feature_notice` — the two
  ambiguous ones. Worth reading properly if they turn up in the wild

## Verification

Tested on `bench2.local` (WP 7.1) with Gift Cards Lite 3.2.10 and Subscriptions 2.0.2
active, over authenticated admin requests:

| Callback | Rule off | Rule on |
|---|---|---|
| `wps_banner_notification_plugin_html` | HOOKED | **gone** |
| `wps_giftcard_notification_plugin_html` | HOOKED | **gone** |
| `wps_sfw_banner_notification_html` | HOOKED | **gone** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

Verified by hook state rather than rendered output: the banner only renders once the
remote endpoint has populated `wps_wgm_notify_new_banner_id`, `…_image` and `…_url`, which
a fresh bench install has not done. Since all three callbacks are plain named functions,
`has_action()` is an exact test — there is no object identity to get wrong.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
public function unhook_wp_swings_offer_banners() : void {
	$offer_banner_callbacks = [
		'wps_banner_notification_plugin_html',
		'wps_giftcard_notification_plugin_html',
		'wps_sfw_banner_notification_html',
	];

	foreach ( $offer_banner_callbacks as $offer_banner_callback ) {
		if ( false === has_action( 'admin_notices', $offer_banner_callback ) ) {
			// Not registered on this site.
			continue;
		}

		remove_action( 'admin_notices', $offer_banner_callback );
		$this->log( 'wp-swings', sprintf( 'Removed %s from admin_notices.', $offer_banner_callback ) );
	}
}
```
