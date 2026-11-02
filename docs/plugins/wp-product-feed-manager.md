# Product Feed Manager for WooCommerce

- slug: `wp-product-feed-manager`
- version analysed: `2.24.1` (vault also holds 2.23.6 to 2.24.0; the installed copy on a client
  site was byte-identical to the vault zip)
- source: `/vault/backups/wordpress/plugins/wp-product-feed-manager/wp-product-feed-manager,2.24.1.zip`
- licensing: freemium (vendor WP Marketing Robot; paid "Google" and "Multichannel" editions
  are separate builds, told apart by `WPPFM_PLUGIN_VERSION_ID`)
- Freemius bundled: no

Not to be confused with WebAppick's Product Feed (`webappick-product-feed-for-woocommerce`),
which is a different vendor and codebase.

## Analysis

Analysed on 2 Nov 2026 by Claude Code (Claude Opus 5).

Reported by Paul from a client site: a full-width **"Product Feed Manager - Black Friday:
Save 35% on Premium"** banner with a discount code and a "See The Offer!" button, on every
admin screen. It comes from one plain named function, `wppfm_sales_promotion_notice()`, on
`admin_notices`. The plugin's other two notices are operational. It has no dashboard widget
and no outbound promotional calls.

The campaign is gated by date (`wppfm_is_black_friday_2026_promotion_period_active()`,
1 to 30 Nov 2026, Europe/Amsterdam), by edition, and by a per-edition dismiss option. The
function name is older than the campaign: 2.23.6 and 2.23.8 use the same
`wppfm_sales_promotion_notice` for the Black Friday 2024 banner
(`wppfm_black_friday_promotion_2024_dismissed`). So it is the vendor's stable "seasonal sale"
slot, and the rule names that slot rather than this year's campaign.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 3 single-line hits, all on `admin_notices` (table below). The multiline pass found nothing. No `all_admin_notices`, `in_admin_header` or `admin_print_footer_scripts` hits |
| Vendor opt-out filters | **None**. The three matching `apply_filters()` calls are `wppfm_review_feed_item_value`, `wppfm_temporary_feed_promotion_validation_rules` ("promotion" in the sense of moving a temp feed file to its final path) and `wppfm_feed_failure_notice_delay_seconds`. None of them is an opt-out |
| Vendor opt-out constants | **None** |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets. The `wp_remote_post` hits are the feed-processing dispatcher and its logger |
| Freemius | Not bundled |

## Findings

| Item | Hook / callback | Verdict | Reason |
|---|---|---|---|
| Black Friday 2026 sale banner (free: 35% off Premium, code `BLCKFD26FRMUM`; Google edition: €30 off Multichannel, code `BLCKFD26DLPREMIUM`) | `admin_notices` 10 → `wppfm_sales_promotion_notice` (`includes/user-interface/wppfm-admin-actions.php:177`) | **suppress** | Pure seasonal sale. The function does nothing else except enqueue the banner's own `wppfm-promotion-notice.css` |
| Old logger plugin still active | `admin_notices` 10 → `wppfm_check_old_logger_installed` (`includes/packages/logger/wp-product-feed-manager-logger.php:96`) | keep | Plugin conflict notice. Never suppressed by rule |
| Cron memory-limit warning | `admin_notices` 10 → `wppfm_render_memory_warning_notice` (`includes/application/wppfm-feed-health-monitor.php:185`) | keep | Automated feed runs are failing for lack of memory. Operational |

## Deliberately left alone

- **Black Friday cards on the vendor's Support page** (`WPPFM_Support_Page::black_friday_2026_card()`
  and `black_friday_2026_google_card()`). These are on the vendor's own screen, so out of scope
  by construction. They are gated on `wppfm_should_show_black_friday_2026_promotion()`, not
  on the notice callback, so the rule does not touch them
- **The "show your love" (review), Facebook group and feature-request cards** on the same
  Support page. Same reason
- **The `wppfm_promotion_notice` script localisation** in `WPPFM_Register_Scripts`. It only
  passes the edition name to the dismiss handler; with no banner on the page it does nothing
- **`pre_option_wppfm_black_friday_promotion_2026_dismissed` answering `canceled`**, rejected.
  It would work this month, but the option is year-stamped (2024 used
  `wppfm_black_friday_promotion_2024_dismissed`), so it would silently stop matching at the
  next campaign. The function name has survived both campaigns
- **The two operational notices** in the table above

## Mechanism

- tier: 2 (targeted unhook, **by name**)
- phase: `admin_init` at `self::LATE_PRIORITY`, from `unhook_vendor_notices()`
- vendor registers at: `includes/user-interface/wppfm-admin-actions.php:177`, at file scope.
  `WP_Product_Feed_Manager::includes()` `require_once`s it from the singleton's constructor,
  and `WP_Product_Feed_Manager::get_instance()` runs when the plugin file loads, so the
  callback exists long before `admin_init`
- instance reachable via: N/A. It is a plain global function

## Drift check

In a new release, check `includes/user-interface/wppfm-admin-actions.php` for
`add_action( 'admin_notices', 'wppfm_sales_promotion_notice' )`. If the next campaign is
given a new function name, the debug log will be silent and the banner will be back. Also
check that the function still does nothing but render the promotion. Look at
`WPPFM_Notice` and the `wppfm_get_active_*_promotion_edition()` gate it calls.

## Verification

- **Confirmed on bench1, 2 Nov 2026, over HTTP** (minted admin cookie, user 9, quick-2fa
  inactive). WooCommerce 11.2.1 and WPPFM 2.24.1 from the vault, free edition. The date gate
  was already open, so nothing was backdated
  - before (bundled 1.44.0, no rule): Dashboard `id="dashboard-widgets"` 1,
    `id="wppfm-discount-promotion-notice"` 1, `wppfm-promotion-notice.css` 1
  - after (1.48.0 copied over the bundled `headwall-hosting/headwall-nag-cleanup.php`,
    `Plugin::VERSION` asserted as 1.48.0): Dashboard banner 0, stylesheet 0; Plugins screen
    (`id="the-list"` 1) banner 0, stylesheet 0
  - `wppfm_black_friday_promotion_2026_dismissed` still absent afterwards, so nothing was written
  - negative check (WP_ADMIN harness, after `do_action( 'admin_init' )`):
    `wppfm_check_old_logger_installed` and `wppfm_render_memory_warning_notice` both still on
    `admin_notices` at 10; `wppfm_sales_promotion_notice` gone
- **Live-confirmed by Paul, 2 Nov 2026**, on the client site where it was reported (free edition)
- Google edition: **source-verified only**. Same callback, and no Google build is in the vault
- Surface: `admin_notices` on every admin screen. No REST or JavaScript rendering

## Additions to `headwall-nag-cleanup.php`: `unhook_wp_product_feed_manager_sale_banner()`

```php
public function unhook_wp_product_feed_manager_sale_banner() : void {
	if ( false !== has_action( 'admin_notices', 'wppfm_sales_promotion_notice' ) ) {
		remove_action( 'admin_notices', 'wppfm_sales_promotion_notice' );
		$this->log( 'wp-product-feed-manager', 'Removed wppfm_sales_promotion_notice from admin_notices.' );
	}
}
```

Called from `unhook_vendor_notices()`.
