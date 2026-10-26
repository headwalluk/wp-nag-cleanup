# SkyVerge plugin framework (vault sweep)

- slug: family document, covering every vault plugin that bundles `skyverge/wc-plugin-framework`
- versions analysed: newest in the vault for each plugin, listed below
- source: `/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip`
- licensing: premium (all sold on woocommerce.com)
- Freemius bundled: no, in all eight

## Analysis

Analysed on 26 Oct 2026 by Claude Code (Claude Opus 5). This sweep followed the
WooCommerce Memberships rule (1.45.0, confirmed live). Every vault slug's newest zip was
listed and checked for `class-sv-wc-plugin.php`. Eight plugins bundle the framework:

| Plugin | Slug | Version | `PLUGIN_ID` | Framework | Rule |
|---|---|---|---|---|---|
| WooCommerce Memberships | `woocommerce-memberships` | 1.30.1 | `memberships` | v6_2_1 | milestone filter. [woocommerce-memberships.md](woocommerce-memberships.md) |
| Global Payments HPP | `woocommerce-gateway-realex-redirect` | 3.0.1 | `realex_redirect` | v5_10_2 | milestone filter (this doc) |
| Sequential Order Numbers Pro | `woocommerce-sequential-order-numbers-pro` | 1.21.8 | `sequential_order_numbers_pro` | v5_15_11 | newsletter sign-up. [woocommerce-sequential-order-numbers-pro.md](woocommerce-sequential-order-numbers-pro.md) |
| Google Analytics Pro | `woocommerce-google-analytics-pro` | 2.2.0 | `google_analytics_pro` | v5_12_5 | none |
| Memberships for Teams | `woocommerce-memberships-for-teams` | 1.7.4 | `memberships-for-teams` | v5_11_2 | none |
| Order Status Manager | `woocommerce-order-status-manager` | 1.15.7 | `order_status_manager` | v5_15_9 | none |
| Local Pickup Plus | `woocommerce-shipping-local-pickup-plus` | 2.11.5 | `local_pickup_plus` (`SHIPPING_METHOD_ID`) | v5_11_12 | none |
| Tab Manager | `woocommerce-tab-manager` | 1.16.3 | `tab_manager` | v5_15_12 | none |

### The milestone channel

`Lifecycle::add_admin_notices()` renders stored milestones through
`apply_filters( 'wc_' . get_id() . '_milestone_message', generate_milestone_notice_message( $message ), $id )`
and skips any falsy result. `generate_milestone_notice_message()` returns only the review
request ("Are you having a great experience with … Please consider leaving a review!"). This
is identical in every framework version above, apart from punctuation in v5_10_2. The
mechanism is described in full in [woocommerce-memberships.md](woocommerce-memberships.md).

**A filter is only added for plugins that actually trigger a milestone.** Milestones are
triggered from three places:

- plugin-specific code. Only Memberships (`membership-plan-created`, `user-membership-created`)
- `SV_WC_Payment_Gateway::add_milestone_hooks()` (`payment-processed`, `refund-processed`).
  Every plugin bundles this class, but it only runs in a gateway. Realex is the only gateway
  here: `WC_Gateway_Realex_Redirect extends SV_WC_Payment_Gateway_Hosted`, and it defines
  `get_sales_page_url()`, so `get_reviews_url()` is non-empty and the review renders
- the `wc_{id}_milestone_reached` action, which nothing in these eight plugins fires

So Google Analytics Pro, Memberships for Teams, Order Status Manager, Sequential Order
Numbers Pro, Local Pickup Plus and Tab Manager get **no** milestone filter. Adding one
would be a rule for a nag that does not exist. **Re-check for `trigger_milestone(` and
`_milestone_reached` outside `/wc-plugin-framework/` when a new version appears.**

### Search checklist

The passes were run per plugin, excluding `vendor/`. The framework's own notices were
classified once, in [woocommerce-memberships.md](woocommerce-memberships.md).

| Pass | Result |
|---|---|
| `admin_notices` / `all_admin_notices` / `in_admin_header` / `admin_print_footer_scripts` | Loader requirement notices in all eight. Per-plugin notices are listed below. Tab Manager's `admin_print_footer_scripts` hit includes JS templates, not a notice |
| Vendor opt-out filters | Only the framework's `wc_{id}_milestone_message`. GA Pro's `wc_google_analytics_pro_*tracking*` filters are front-end analytics, not promos |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls | GA Pro's Google OAuth refresh (operational). SON Pro's newsletter button POSTs the admin's email to `api.jilt.com` from the browser, but only on click |
| Freemius | None |

## Findings

| Item | Hook / ID | Verdict | Reason |
|---|---|---|---|
| Global Payments HPP first-payment / first-refund review request | milestones `payment-processed`, `refund-processed` | suppress | Review begging |
| Sequential Order Numbers Pro newsletter sign-up | `wc_sequential_order_numbers_pro_onboarding_status_complete` | suppress | Newsletter. See its own doc |

## Deliberately left alone

- **Global Payments HPP:** the "upgraded to a major new version … review our Upgrade Guide"
  notice and `wc-realex-redirect-migration-completed`. Both are operational (merchant account
  compatibility, migration status). `woothemes_updater_notice` from `woo-includes` is the
  Woo updater prompt. Operational
- **Google Analytics Pro:** free-integration-deactivated, the `onboarding` "connect to Google
  Analytics" notice, `ga4-compatibility`, `ua-warning`, `optimize-warning`, the tracker
  function rename, the duplicate tracking code detector, missing Analytics account, API
  errors, currency mismatch, missing Measurement Protocol secret, Enhanced Ecommerce
  required. All describe the site's tracking setup. `ga4-compatibility` reads like an
  announcement, but it tells the owner a setup step is now available for their store, so it
  is ambiguous and stays
- **Memberships for Teams:** "Thanks for installing … read the documentation" (onboarding),
  "team" term settings change, `team-zero-renewal-{id}` (billing bug remediation),
  `switched_team_{id}`. Operational
- **Order Status Manager:** "Thanks for installing … read the documentation" (onboarding)
  and the "Pending Payment" status change. Operational
- **Local Pickup Plus:** shipping disabled, pickup locations upgraded, conflicting category
  pickup settings. Operational
- **Tab Manager:** `update_products_after_upgrade_nag`. Despite the name, it asks the owner to
  re-index products for the new search, or for Relevanssi. Operational
- **Every plugin:** the framework's "Review" plugin-row action link. It is not a notice

## Mechanism

- tier: 1 (vendor hook), `add_filter( 'wc_realex_redirect_milestone_message', '__return_empty_string' )`
- phase: file scope, in `register_vendor_optouts()`
- vendor registers at: `Lifecycle::add_admin_notices()` on `init`, admin non-AJAX
- instance reachable via: N/A

## Drift check

- New SkyVerge plugins: re-run the sweep. List each slug's newest zip for
  `class-sv-wc-plugin.php`, then check for gateway classes and plugin-side `trigger_milestone(`
- Realex: `PLUGIN_ID` in `class-wc-realex-redirect.php`, `get_sales_page_url()`, and the
  `Lifecycle.php` filter (in `woocommerce/Lifecycle.php` up to v5_12, `woocommerce/Plugin/Lifecycle.php` after)

## Verification

- **`wc_realex_redirect_milestone_message`: source-verified only.** Only 3.0.1 is in the
  vault. Bench repro: set `wc_realex_redirect_milestone_messages` to
  `{"payment-processed":"you successfully processed a payment!"}` and probe
  `data-plugin-id="realex_redirect" data-message-id="payment-processed"`. There is no time gate
- Negative check: `wc-realex-redirect-migration-completed` must still render where its option is set

## Additions to `headwall-nag-cleanup.php`: 1 mechanism 1 filter (Realex)

```php
add_filter( 'wc_realex_redirect_milestone_message', '__return_empty_string' );
```
