<?php
/**
 * Plugin Name: Headwall Nag Cleanup
 * Plugin URI:  https://github.com/headwalluk/wp-nag-cleanup
 * Description: Removes promotional clutter from the WordPress admin notice area and dashboard, leaving operational notices intact.
 * Version:     1.27.0
 * Author:      Paul Faulkner
 * Author URI:  https://headwall-hosting.com/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Single-file mu-plugin. Safe to include more than once.
 *
 * Optional constants, set in wp-config.php:
 *   HEADWALL_NAG_CLEANUP_DEBUG                          Log suppressions to error_log().
 *   HEADWALL_NAG_CLEANUP_REMOVE_CORE_DASHBOARD_WIDGETS  Also remove core's Events and News widget.
 *   HEADWALL_NAG_CLEANUP_REMOVE_WELCOME_PANEL           Also remove core's dashboard Welcome panel.
 *
 * Per-rule provenance is in docs/plugins/. Design and boundary rule: README.md.
 *
 * @package Headwall_Nag_Cleanup
 */

namespace Headwall_Nag_Cleanup;

defined( 'ABSPATH' ) || die();

// Declarations sit inside the guard, not after an early return: PHP early-binds an
// unconditional top-level class, so class_exists() above it is true on first include.
if ( ! class_exists( __NAMESPACE__ . '\\Plugin' ) ) {

	/**
	 * Suppresses named promotional notices and dashboard widgets from named plugins.
	 */
	class Plugin {

		const VERSION = '1.27.0';

		/**
		 * Priority for our own unhooking and for overriding vendor filter values.
		 *
		 * Not PHP_INT_MAX: WooCommerce already occupies it on admin_notices and
		 * in_admin_header, and a tie resolves in registration order — an mu-plugin
		 * registers first, so we would run before the vendor we tied with. 999 only
		 * has to beat the vendor's own registration, which it does for every audited
		 * rule; see docs/plugins/ for the per-vendor priorities.
		 */
		const LATE_PRIORITY = 999;

		/**
		 * Priority for removing a producer that itself runs on admin_init.
		 *
		 * Vendors that build a notice from an admin_init callback at the default
		 * priority have already run by LATE_PRIORITY, so those are unhooked before
		 * them instead. Only used where the target is on admin_init itself.
		 */
		const EARLY_PRIORITY = 1;

		/**
		 * Upper bound when a vendor registers the same callback more than once.
		 *
		 * Code Snippets constructs Promotion_Manager twice in Plugin.php, so its promotion
		 * lands on admin_notices twice and one remove_action() leaves a copy rendering.
		 */
		const MAX_DUPLICATE_CALLBACKS = 16;

		/**
		 * Widgets removed by mechanism 3, as widget ID, meta box context, vendor and reason.
		 */
		/**
		 * Featured Images in RSS's Freemius module. The id keys Freemius's instance
		 * registry; the slug forms its per-module filter tags.
		 * docs/plugins/featured-images-for-rss-feeds.md
		 */
		const FIRSS_FREEMIUS_MODULE_ID = 195;
		const FIRSS_FREEMIUS_SLUG      = 'featured-images-for-rss-feeds';

		/**
		 * Freemius sticky ids that carry promotion only. These are the sole two notices
		 * the SDK types 'promotion'; licence, update and opt-in stickies are not listed.
		 */
		const FREEMIUS_PROMO_NOTICE_IDS = [
			'trial_promotion',
			'affiliate_program',
		];

		/**
		 * Rank Math notification-centre IDs that hold nothing operational.
		 *
		 * The two gate each other — each producer checks for the other's notification
		 * before adding its own — so they are removed together or not at all.
		 * docs/plugins/seo-by-rank-math.md
		 */
		const RANK_MATH_PROMO_NOTIFICATION_IDS = [
			'rank_math_pro_notice',
			'rank_math_review_plugin_notice',
		];

		/**
		 * Premium Addons' seasonal pointer campaigns, named by the transient each one
		 * writes when the site owner dismisses it.
		 *
		 * The name is campaign-scoped, so a new campaign needs a new entry here rather
		 * than a pattern: five names appeared between 4.11.62 and 4.11.103. pa_summer26
		 * and pa_sumr26 are two different campaigns, not a typo — 4.11.84 renamed the
		 * transient, which re-showed the pointer to everyone who had dismissed it.
		 * docs/plugins/premium-addons-for-elementor.md
		 */
		const PREMIUM_ADDONS_POINTER_TRANSIENTS = [
			'pa_xmas25_pointer_dismiss',   // 4.11.62 to 4.11.67.
			'pa_val26_pointer_dismiss',    // 4.11.68 to 4.11.72.
			'pa_spring26_pointer_dismiss', // 4.11.73 to 4.11.76.
			'pa_summer26_pointer_dismiss', // 4.11.77 to 4.11.83.
			'pa_sumr26_pointer_dismiss',   // 4.11.84 onwards; current at 4.11.103.
		];

		/**
		 * Mechanism 3: promotional dashboard widgets, removed by id on wp_dashboard_setup.
		 *
		 * Write-ups in docs/plugins/: premium-addons-for-elementor, css-hero,
		 * woocommerce-lottery, ht-mega-and-happy-addons, quadlayers, elementskit-lite,
		 * elementor, fusion-core, ultimate-post-kit.
		 */
		const PROMOTIONAL_DASHBOARD_WIDGETS = [
			[
				'widget_id' => 'pa-stories',
				'context'   => 'column3',
				'vendor'    => 'Premium Addons for Elementor 4.11.103',
				'reason'    => 'Premium Addons News; fetches premiumaddons.com on render',
			],
			[
				'widget_id' => 'widget_cssheronews',
				'context'   => 'normal',
				'vendor'    => 'CSS Hero 5.1.0',
				'reason'    => 'From the CSS Hero world; RSS feed fetched on render',
			],
			[
				'widget_id' => 'wpgenie_dashboard_products_news',
				'context'   => 'normal',
				'vendor'    => 'WooCommerce Lottery 1.1.21',
				'reason'    => 'wpgenie.org latest themes and plugins; RSS feed fetched on render',
			],
			[
				'widget_id' => 'hasthemes-dashboard-stories',
				'context'   => 'normal',
				'vendor'    => 'HT Mega for Elementor 3.2.5',
				'reason'    => 'HasThemes Stories; vendor feed',
			],
			[
				'widget_id' => 'happy_addons_news_update',
				'context'   => 'normal',
				'vendor'    => 'Happy Elementor Addons 3.23.1',
				'reason'    => 'HappyAddons News & Updates; vendor feed',
			],
			[
				'widget_id' => 'wp-dashboard-widget-news',
				'context'   => 'normal',
				'vendor'    => 'QuadLayers wp-dashboard-widget-news, Insta Gallery 5.0.8',
				'reason'    => 'QuadLayers News; vendor feed and shop links',
			],
			[
				'widget_id' => 'wpmet-stories',
				'context'   => 'normal',
				'vendor'    => 'ElementsKit Lite 4.0.2',
				'reason'    => 'Wpmet Stories; fetches wpmet.com on render',
			],
			[
				'widget_id' => 'e-dashboard-overview',
				'context'   => 'normal',
				'vendor'    => 'Elementor 4.2.4',
				'reason'    => 'Elementor Overview; News & Updates feed, and takes Recently Edited with it',
			],
			[
				'widget_id' => 'themefusion-news',
				'context'   => 'normal',
				'vendor'    => 'Avada Core (fusion-core) 5.16.1',
				'reason'    => 'Avada News; avada.com feed and a Buy Now licence button',
			],
			[
				'widget_id' => 'bdt-dashboard-overview',
				'context'   => 'column4',
				'vendor'    => 'BdThemes admin-feeds, Ultimate Post Kit 4.5.3',
				'reason'    => 'BdThemes News & Updates; fetches bdthemes.com/feed on render',
			],
		];

		/**
		 * Core widgets, removed only when HEADWALL_NAG_CLEANUP_REMOVE_CORE_DASHBOARD_WIDGETS is set.
		 */
		const CORE_DASHBOARD_WIDGETS = [
			[
				'widget_id' => 'dashboard_primary',
				// Core forces this widget into the side column.
				'context'   => 'side',
				'vendor'    => 'WordPress core 7.1',
				'reason'    => 'WordPress Events and News; fetches api.wordpress.org on render',
			],
		];

		/**
		 * Register everything this plugin does.
		 */
		public function run() : void {
			// rest_api_init fires only on a REST request, so it needs no gate of its own.
			// Testing the request shape here does not work: the one vendor that needs this
			// is fetched with Accept: */*. docs/plugins/seo-by-rank-math.md
			add_action( 'rest_api_init', [ $this, 'unhook_rest_rendered_promos' ], self::LATE_PRIORITY );

			if ( $this->is_admin_page_request() ) {
				$this->register_vendor_optouts();

				add_action( 'admin_init', [ $this, 'unhook_early_vendor_notices' ], self::EARLY_PRIORITY );
				add_action( 'admin_init', [ $this, 'unhook_vendor_notices' ], self::LATE_PRIORITY );
				add_action( 'admin_init', [ $this, 'remove_core_welcome_panel' ], self::LATE_PRIORITY );
				add_action( 'current_screen', [ $this, 'unhook_late_vendor_notices' ], self::LATE_PRIORITY );
				add_action( 'wp_dashboard_setup', [ $this, 'remove_promotional_dashboard_widgets' ], self::LATE_PRIORITY );
				add_action( 'wp_network_dashboard_setup', [ $this, 'remove_promotional_dashboard_widgets' ], self::LATE_PRIORITY );
				add_action( 'wp_user_dashboard_setup', [ $this, 'remove_promotional_dashboard_widgets' ], self::LATE_PRIORITY );
				add_action( 'all_admin_notices', [ $this, 'remove_stored_vendor_notifications' ], self::EARLY_PRIORITY );
			} else {
				// No notice area and no dashboard on this request type.
			}
		}

		/**
		 * Can this request render an admin notice or a dashboard?
		 */
		private function is_admin_page_request() : bool {
			$is_admin_page_request = true;

			if ( ! is_admin() ) {
				$is_admin_page_request = false;
			} elseif ( wp_doing_ajax() ) {
				// is_admin() is also true during admin-ajax.php.
				$is_admin_page_request = false;
			} elseif ( wp_doing_cron() ) {
				$is_admin_page_request = false;
			} elseif ( wp_is_json_request() ) {
				// REST_REQUEST is not defined this early, so test the request shape.
				$is_admin_page_request = false;
			} else {
				// Normal admin page request.
			}

			return $is_admin_page_request;
		}

		/**
		 * Mechanism 1: vendor opt-out hooks, registered at file scope.
		 */
		private function register_vendor_optouts() : void {
			// Essential Addons for Elementor 6.8.3. docs/plugins/essential-addons-for-elementor-lite.md
			add_filter( 'eael/disable_promotions', '__return_true', 100 );

			// YITH plugin-fw 4.7.8. docs/plugins/yith-plugin-fw.md
			add_filter( 'yith_plugin_fw_show_dashboard_widgets', '__return_false' );

			// WP Desk ltv-dashboard-widget 1.x. docs/plugins/flexible-invoices.md
			add_filter( 'wpdesk/ltvdashboard/disable', '__return_true' );

			// WP Desk wp-wpdesk-tracker, Flexible Invoices 6.2.27. docs/plugins/flexible-invoices.md
			// Late priority: UsageDataTracker adds its own callback returning true at 10.
			add_filter( 'wpdesk_tracker_enabled', '__return_false', self::LATE_PRIORITY );

			// Brainstorm Force bsf-analytics, Astra Pro 4.13.8. docs/plugins/brainstorm-force.md
			add_filter( 'bsf_usage_tracking_enabled', '__return_false' );

			// Disable Comments 2.9.0 review prompt. The vendor documents this filter in-code
			// as "whether the review prompt may be shown at all".
			// docs/plugins/disable-comments.md
			add_filter( 'disable_comments_show_review_prompt', '__return_false' );

			// CartFlows 5-star review request. The vendor added this filter in 2.2.5 and
			// documents it in-code as an override for site owners and white-label
			// distributors. CartFlows 3.2.0. docs/plugins/cartflows.md
			add_filter( 'cartflows_show_review_notice', '__return_false' );

			// ThemeIsle SDK, bundled in Menu Icons, WPCF7 Redirect and others.
			// Menu Icons 0.13.24. docs/plugins/themeisle-sdk.md
			add_filter( 'themeisle_sdk_hide_dashboard_widget', '__return_true' );

			// CookieYes 3.5.5 review request and web-app connect banner.
			// docs/plugins/cookie-law-info.md
			add_filter( 'cky_is_module_active_review_feedback', '__return_false' );
			add_filter( 'cky_is_module_active_connect_banner', '__return_false' );

			// All in One SEO "What's New in AIOSEO" dashboard widget. Same filter in Lite
			// and Pro. AIOSEO 5.0.1.1, AIOSEO Pro 4.3.4.1.
			// docs/plugins/all-in-one-seo-pack.md
			add_filter( 'aioseo_show_seo_news', '__return_false' );

			// WebToffee cross-promotion banner, shared across their range. The vendor
			// uses this constant as a first-loader mutex, so defining it here skips the
			// banner everywhere. CookieYes 3.5.5. docs/plugins/cookie-law-info.md
			defined( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED' ) || define( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED', true );

			// Freemius promotional stickies, bundled in Featured Images in RSS 1.7.3
			// (SDK 2.13.4). Filters are namespaced per module as fs_{tag}_{slug}, so this
			// is necessarily per slug. Read at render, which is what reaches a sticky the
			// vendor stored before this plugin arrived.
			// docs/plugins/featured-images-for-rss-feeds.md
			add_filter(
				'fs_show_admin_notice_' . self::FIRSS_FREEMIUS_SLUG,
				[ $this, 'hide_freemius_promo_notice' ],
				self::LATE_PRIORITY,
				2
			);

			// WPChill telemetry, in Modula only across the whole vault today. Setting
			// enabled to false reaches the consent prompt, the cron schedule and every send
			// path at once. Modula 2.14.39, unchanged since 2.14.1.
			// docs/plugins/modula-best-grid-gallery.md
			add_filter( 'wpchill_telemetry_config', [ $this, 'disable_wpchill_telemetry' ] );

			// Premium Addons for Elementor's seasonal sale pointer, a wp-pointer popup
			// anchored to its admin menu item on the dashboard and on its own page. It is
			// printed from an anonymous closure on in_admin_header (includes/promotion-pointer.php),
			// so remove_action() has nothing to name and the $wp_filter reader cannot help
			// either — it matches instances of a class, and a closure is neither.
			//
			// The last of the closure's four bail-out conditions is its own dismissal
			// transient, and pre_transient_* is core's short-circuit over it — the four are
			// OR'd, so answering any one of them ends the closure. get_transient() returns
			// this value without a lookup, the closure returns, and the pointer is never
			// printed. Nothing is written — the vendor's own _pa_plugin_pointer_priority
			// option write sits after the gate and is skipped with it, so this leaves no
			// residue and records no dismissal the site owner did not make.
			//
			// The hook is core's rather than the vendor's; only the transient name comes
			// from the vendor, and that is what drifts. Premium Addons 4.11.103.
			// docs/plugins/premium-addons-for-elementor.md
			foreach ( self::PREMIUM_ADDONS_POINTER_TRANSIENTS as $pointer_transient ) {
				add_filter( 'pre_transient_' . $pointer_transient, '__return_true' );
			}

			// EmbedPress needs no rule. docs/plugins/embedpress.md

			$this->log( 'vendor-optouts', 'Registered vendor opt-out filters.' );
		}

		/**
		 * Mechanism 2: targeted unhooking, late enough that vendor hooks exist.
		 */
		public function unhook_vendor_notices() : void {
			$this->unhook_elementor_notices();
			$this->unhook_wpb_product_slider_review_notice();
			$this->unhook_forminator_dashboard_promo();
			$this->unhook_premium_addons_promos();
			$this->unhook_wp_swings_offer_banners();
			$this->unhook_quadlayers_promote_notice();
			$this->unhook_essential_blocks_campaigns();
			$this->unhook_happy_addons_promos();
			$this->unhook_easy_fancybox_review_request();
			$this->unhook_webp_converter_promos();
			$this->unhook_mail_bank_review_notice();
			$this->unhook_bdthemes_review_and_tracking_notices();
			$this->unhook_monsterinsights_promos();
			$this->unhook_shapedplugin_promos();
			$this->unhook_complianz_review_notice();
			$this->unhook_cptui_pro_upsell();
		}

		/**
		 * Remove WP Mail Bank's "Leave a 5 Star Review" notice.
		 *
		 * Mail_Bank_Admin_Notices is declared and instantiated inside a function hooked to
		 * init, so the instance is a local with nothing holding it. The vendor's database
		 * upgrade prompt and its plugin-conflict notice are separate callbacks and are
		 * untouched. WP Mail Bank 4.0.14. docs/plugins/wp-mail-bank.md
		 */
		public function unhook_mail_bank_review_notice() : void {
			$this->remove_discarded_instance_callback( 'admin_notices', 'Mail_Bank_Admin_Notices', 'mb_display_admin_notices', 'wp-mail-bank' );
		}

		/**
		 * Remove Converter for Media's review request, PRO upsell and seasonal sale notices.
		 *
		 * All six of the vendor's notices are rendered by NoticeIntegrator::load_notice, one
		 * throwaway integrator per notice, so admin_notices cannot tell them apart. Each
		 * integrator also registers set_disable_value on wp_ajax_<option>, one hook name per
		 * notice, which is what identifies the instance holding the notice we want.
		 * Converter for Media 6.6.5. docs/plugins/webp-converter-for-media.md
		 */
		public function unhook_webp_converter_promos() : void {
			$integrator_class = 'WebpConverter\\Notice\\NoticeIntegrator';

			if ( ! class_exists( $integrator_class ) ) {
				return;
			}

			$promotional_notice_options = [
				'webpc_notice_thanks',      // Review request.
				'webpc_notice_pro_version', // PRO upsell carrying a discount coupon.
				'webpc_notice_bf2026',      // Black Friday sale.
			];

			foreach ( $promotional_notice_options as $notice_option ) {
				$found = $this->find_instance_callback( 'wp_ajax_' . $notice_option, $integrator_class, 'set_disable_value', 'webp-converter' );

				if ( null === $found ) {
					// Notice not built on this request, or the vendor renamed the option.
					continue;
				}

				$notice_callback = [ $found['function'][0], 'load_notice' ];

				foreach ( [ 'admin_notices', 'network_admin_notices' ] as $notice_hook ) {
					$notice_priority = has_action( $notice_hook, $notice_callback );

					if ( false === $notice_priority ) {
						// is_available()/is_active() said no; nothing was hooked.
						continue;
					}

					remove_action( $notice_hook, $notice_callback, $notice_priority );
					$this->log( 'webp-converter', sprintf( 'Removed %s from %s priority %d.', $notice_option, $notice_hook, $notice_priority ) );
				}
			}
		}

		/**
		 * Remove Easy FancyBox's review request.
		 *
		 * A static callback, so it is named directly. The sibling admin_notice callback
		 * is a Pro version-compatibility warning and is left alone.
		 * Easy FancyBox 2.3.22. docs/plugins/easy-fancybox.md
		 */
		public function unhook_easy_fancybox_review_request() : void {
			$review_callback = [ 'easyFancyBox_Admin', 'show_review_request' ];

			if ( false !== has_action( 'admin_notices', $review_callback ) ) {
				remove_action( 'admin_notices', $review_callback );
				$this->log( 'easy-fancybox', 'Removed easyFancyBox_Admin::show_review_request from admin_notices.' );
			}
		}

		/**
		 * Remove Happy Elementor Addons' review notices and tracking opt-in.
		 *
		 * The two review callbacks are static, so they are named directly. The Appsero
		 * opt-in is reached through the plugin's own singleton.
		 * Happy Elementor Addons 3.23.1. docs/plugins/ht-mega-and-happy-addons.md
		 */
		public function unhook_happy_addons_promos() : void {
			// The sibling Classes\Notice carries a campaign window that closed in March
			// 2025, so it is not targeted. See the doc before adding it back.
			$review_callback = [ '\\Happy_Addons\\Elementor\\Classes\\Review', 'ha_void_grid_display_admin_notice' ];

			if ( false !== has_action( 'admin_notices', $review_callback ) ) {
				remove_action( 'admin_notices', $review_callback );
				$this->log( 'happy-addons', 'Removed Review::ha_void_grid_display_admin_notice from admin_notices.' );
			}

			$this->unhook_happy_addons_appsero_optin();
		}

		/**
		 * Remove the Appsero tracking opt-in notice Happy Addons bundles.
		 */
		private function unhook_happy_addons_appsero_optin() : void {
			$base_class = '\\Happy_Addons\\Elementor\\Base';

			if ( ! class_exists( $base_class ) || ! method_exists( $base_class, 'instance' ) ) {
				return;
			}

			$base = $base_class::instance();

			if ( ! is_object( $base ) || ! isset( $base->appsero ) || ! is_object( $base->appsero )
				|| ! isset( $base->appsero->insights ) || ! is_object( $base->appsero->insights ) ) {
				// Appsero not initialised on this request.
			} else {
				remove_action( 'admin_notices', [ $base->appsero->insights, 'admin_notice' ] );
				$this->log( 'happy-addons', 'Removed Appsero Insights::admin_notice from admin_notices.' );
			}
		}

		/**
		 * Remove Essential Blocks' campaign notices.
		 *
		 * Its bundled PriyoMukul\WPNotice bank holds only promotional notices — two
		 * seasonal upsells, a review request and a tracking opt-in — so removing the
		 * renderer takes nothing operational. The Facebook token expiry notice is a
		 * separate callback and survives.
		 * Essential Blocks 6.4.3. docs/plugins/essential-blocks.md
		 */
		public function unhook_essential_blocks_campaigns() : void {
			$cache_bank_class = '\\PriyoMukul\\WPNotice\\Utils\\CacheBank';

			if ( ! class_exists( $cache_bank_class ) || ! method_exists( $cache_bank_class, 'get_instance' ) ) {
				return;
			}

			$cache_bank = $cache_bank_class::get_instance();

			if ( ! is_object( $cache_bank ) ) {
				$this->log( 'essential-blocks', 'CacheBank not reachable; no action taken.' );
			} else {
				remove_action( 'admin_notices', [ $cache_bank, 'notices' ] );
				remove_action( 'admin_footer', [ $cache_bank, 'scripts' ] );
				$this->log( 'essential-blocks', 'Removed CacheBank::notices from admin_notices.' );
			}
		}

		/**
		 * Remove QuadLayers' cross-sell and review notice.
		 *
		 * Their wp-notice-plugin-promote package is bundled in every QuadLayers plugin
		 * and guarded with class_exists, so one removal covers the range. The separate
		 * wp-notice-plugin-required package, which prints dependency notices, is
		 * untouched. Insta Gallery 5.0.8. docs/plugins/quadlayers.md
		 */
		public function unhook_quadlayers_promote_notice() : void {
			$this->remove_discarded_instance_callback(
				'admin_notices',
				'\\QuadLayers\\WP_Notice_Plugin_Promote\\Load',
				'admin_notices',
				'quadlayers'
			);
		}

		/**
		 * Remove BdThemes' review requests and its usage-tracking opt-in.
		 *
		 * Both plugins bundle the same feedback-hub SDK, forked per plugin so the class
		 * name differs; Element Pack also bundles the DCI insights SDK. All three objects
		 * are constructed and discarded — each class declares a get_instance() its own
		 * bootstrap never calls, so the static holder stays null and remove_action() has
		 * nothing to name. Licence, Elementor-dependency and mini-cart-conflict notices
		 * are separate callbacks and survive.
		 * Element Pack Pro 7.11.2, Ultimate Post Kit 4.5.3.
		 * docs/plugins/bdthemes-element-pack.md, docs/plugins/ultimate-post-kit.md
		 */
		public function unhook_bdthemes_review_and_tracking_notices() : void {
			$this->remove_discarded_instance_callback(
				'admin_notices',
				'RC_Reviews_Collector',
				'display_global_notice',
				'bdthemes-element-pack'
			);
			$this->remove_discarded_instance_callback(
				'admin_notices',
				'Insights_SDK',
				'display_global_notice',
				'bdthemes-element-pack'
			);
			$this->remove_discarded_instance_callback(
				'admin_notices',
				'Ultimate_Post_Kit_Reviews_Collector',
				'display_global_notice',
				'ultimate-post-kit'
			);
		}

		/**
		 * Remove MonsterInsights' menu tooltip, review request and WPConsent cross-sell.
		 *
		 * The tooltip is a floating upsell bubble anchored to the Insights menu item rather
		 * than a notice, so it hangs off adminmenu. It and the cross-sell are plain named
		 * functions. MonsterInsights_Review is constructed and discarded at the foot of its
		 * own file, so remove_action() has nothing to name.
		 *
		 * The vendor's own hide_am_notices switch is not used: its settings screen describes
		 * it as also hiding deprecation and required-configuration notices.
		 * MonsterInsights 11.2.0. docs/plugins/google-analytics-for-wordpress.md
		 */
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

		/**
		 * Mechanism 4: drop a stored notification the vendor has already banked.
		 *
		 * For vendors that write notices into an option and render them from a shared
		 * store, unhooking the producer only ever governs sites that have not been nagged
		 * yet. This runs before the store's own renderer and asks the vendor to drop the
		 * entry, which is a durable change to the vendor's own data — so it is reserved for
		 * IDs that carry nothing operational, and each one is named.
		 */
		public function remove_stored_vendor_notifications() : void {
			$this->remove_rank_math_stored_promos();
		}

		/**
		 * Remove Rank Math's stored PRO upsell and review request.
		 *
		 * Notification_Center::display() also renders redirection, 404-monitor,
		 * plugin-conflict and database-migration notices, so the renderer cannot be
		 * unhooked. remove_by_id() is the vendor's own dismiss path: it blanks the entry's
		 * id, and update_storage() then drops it on shutdown.
		 * Rank Math SEO 1.0.278. docs/plugins/seo-by-rank-math.md
		 */
		private function remove_rank_math_stored_promos() : void {
			if ( ! function_exists( 'rank_math' ) ) {
				// Not installed.
			} else {
				$notification_centre = rank_math()->notification;

				if ( ! is_object( $notification_centre ) || ! method_exists( $notification_centre, 'remove_by_id' )
					|| ! method_exists( $notification_centre, 'has_notification' ) ) {
					$this->log( 'seo-by-rank-math', 'Notification centre not reachable; no action taken.' );
				} else {
					foreach ( self::RANK_MATH_PROMO_NOTIFICATION_IDS as $notification_id ) {
						if ( ! $notification_centre->has_notification( $notification_id ) ) {
							// Never banked on this site, or already removed by an earlier request.
							continue;
						}

						$notification_centre->remove_by_id( $notification_id );
						$this->log( 'seo-by-rank-math', sprintf( 'Removed stored notification %s.', $notification_id ) );
					}
				}
			}
		}

		/**
		 * Mechanism 2, for producers that only run inside a REST request.
		 */
		public function unhook_rest_rendered_promos() : void {
			$this->unhook_rank_math_dashboard_feed();
		}

		/**
		 * Remove the rankmath.com blog feed from Rank Math's dashboard widget.
		 *
		 * The widget mixes the site's own 404, redirection and analytics figures with a
		 * vendor news feed, but the feed is a separate callback on the vendor's own
		 * rank_math/dashboard/widget action, so the figures survive. This also drops the
		 * wp_remote_get to rankmath.com made on render.
		 *
		 * Dashboard_Widget is constructed and discarded in Common::__construct(), which
		 * RankMath::setup() reaches on every request. It declares namespace RankMath despite
		 * living in includes/admin/, so the class is RankMath\Dashboard_Widget, not
		 * RankMath\Admin\Dashboard_Widget.
		 * Rank Math SEO 1.0.278. docs/plugins/seo-by-rank-math.md
		 */
		private function unhook_rank_math_dashboard_feed() : void {
			$this->remove_discarded_instance_callback(
				'rank_math/dashboard/widget',
				'RankMath\\Dashboard_Widget',
				'dashboard_widget_feed',
				'seo-by-rank-math'
			);
		}

		/**
		 * Mechanism 2, for producers that themselves run on admin_init.
		 */
		public function unhook_early_vendor_notices() : void {
			$this->unhook_wpcode_promos();
			$this->unhook_freemius_promos();
			$this->unhook_bsf_analytics_optin_notice();
			$this->unhook_astra_theme_wc_upsell();
			$this->unhook_wpforms_review_promos();
			$this->unhook_wp_mail_smtp_review_request();
		}

		/**
		 * Remove WP Mail SMTP's review request before it registers its notice.
		 *
		 * Review::admin_notices() is the producer: it runs on admin_init at the default
		 * priority and does nothing but add review_request to admin_notices (or to
		 * network_admin_notices on multisite), so removing it covers both. The instance is
		 * discarded by `( new Review() )->hooks();` in Admin\Area.
		 * WP Mail SMTP 4.9.0. docs/plugins/wp-mail-smtp.md
		 */
		public function unhook_wp_mail_smtp_review_request() : void {
			$this->remove_discarded_instance_callback( 'admin_init', 'WPMailSMTP\\Admin\\Review', 'admin_notices', 'wp-mail-smtp' );
		}

		/**
		 * Remove WPForms Lite's review request and its admin-footer rating text.
		 *
		 * Both belong to a discarded WPForms_Review instance; review_request builds its
		 * notice from admin_init at the default priority, so it is removed before that runs.
		 * WPForms Lite 2.0.1.1. docs/plugins/wpforms-lite.md
		 */
		public function unhook_wpforms_review_promos() : void {
			$this->remove_discarded_instance_callback( 'admin_init', 'WPForms_Review', 'review_request', 'wpforms-lite' );
			$this->remove_discarded_instance_callback( 'admin_footer_text', 'WPForms_Review', 'admin_footer', 'wpforms-lite' );
		}

		/**
		 * Remove the Astra theme's "Upgrade to Business Toolkit" notice on WooCommerce screens.
		 *
		 * Registered from after_setup_theme priority 99 onto admin_init at the default
		 * priority, so it is removed before that runs. The callback is static, so it is
		 * named directly rather than through the $wp_filter reader.
		 * Astra theme 4.13.11. docs/plugins/astra-theme.md
		 */
		public function unhook_astra_theme_wc_upsell() : void {
			// Case matters: _wp_filter_build_unique_id() keys a static callback by literal
			// string, so a mismatch here removes nothing and looks like success.
			$astra_wc_upsell_callback = 'Astra_Admin_Settings::upgrade_to_pro_wc_notice';

			if ( ! class_exists( 'Astra_Admin_Settings' ) ) {
				// Astra theme not active.
			} elseif ( false === has_action( 'admin_init', $astra_wc_upsell_callback ) ) {
				$this->log( 'astra-theme', 'Astra_Admin_Settings::upgrade_to_pro_wc_notice not registered on admin_init; no action taken.' );
			} else {
				remove_action( 'admin_init', $astra_wc_upsell_callback );
				$this->log( 'astra-theme', 'Removed Astra_Admin_Settings::upgrade_to_pro_wc_notice from admin_init.' );
			}
		}

		/**
		 * Remove the bsf-analytics usage-tracking opt-in notice.
		 *
		 * The library constructs BSF_Analytics on init and discards the instance, so the
		 * callback is matched by class rather than named. It queues the notice from an
		 * admin_init callback at the default priority, so it is removed before that runs.
		 * The loader builds one instance for every Brainstorm Force plugin on the site,
		 * so this covers all of them at once.
		 * CartFlows 3.2.0, bsf-analytics 1.1.29.
		 * docs/plugins/cartflows.md, docs/plugins/brainstorm-force.md
		 */
		public function unhook_bsf_analytics_optin_notice() : void {
			$this->remove_discarded_instance_callback( 'admin_init', 'BSF_Analytics', 'option_notice', 'bsf-analytics' );
		}

		/**
		 * Remove WPCode's review request, Pro Tip upsell and library promo.
		 *
		 * These build their notices from admin_init callbacks at the default priority,
		 * so they are removed before those run rather than after. The plugin's notice
		 * framework and its Pro/Lite conflict notice are left alone.
		 * WPCode 2.3.9. docs/plugins/insert-headers-and-footers.md
		 */
		public function unhook_wpcode_promos() : void {
			$this->remove_discarded_instance_callback( 'admin_init', 'WPCode_Review', 'review_request', 'wpcode' );
			$this->remove_discarded_instance_callback( 'admin_footer_text', 'WPCode_Review', 'admin_footer', 'wpcode' );
			$this->remove_discarded_instance_callback( 'admin_init', 'WPCode_Features_Notices', 'maybe_show_notices', 'wpcode' );

			if ( false !== has_action( 'admin_init', 'wpcode_maybe_add_library_connect_notice' ) ) {
				remove_action( 'admin_init', 'wpcode_maybe_add_library_connect_notice' );
				$this->log( 'wpcode', 'Removed wpcode_maybe_add_library_connect_notice from admin_init.' );
			}
		}

		/**
		 * Remove Freemius's trial and affiliate notice producers for Featured Images in RSS.
		 *
		 * Both run from admin_init at the default priority, so they are removed before
		 * those run. Unhooking rather than using the SDK's own show_trial filter is
		 * deliberate: _add_trial_notice() tests for an already-stored sticky and adds a
		 * menu counter bubble *before* it consults that filter, so the filter alone would
		 * leave a badge the site owner could never clear.
		 * Featured Images in RSS 1.7.3, Freemius SDK 2.13.4.
		 * docs/plugins/featured-images-for-rss-feeds.md
		 */
		public function unhook_freemius_promos() : void {
			$freemius_module = $this->get_freemius_module( self::FIRSS_FREEMIUS_MODULE_ID );

			if ( null === $freemius_module ) {
				// Freemius has not booted, or this module is not installed.
				$this->log( 'firss-freemius', 'Freemius module 195 not present; nothing to unhook.' );
			} else {
				remove_action( 'admin_init', [ $freemius_module, '_add_trial_notice' ] );
				remove_action( 'admin_init', [ $freemius_module, '_add_affiliate_program_notice' ] );
				$this->log( 'firss-freemius', 'Removed Freemius trial and affiliate notice producers from admin_init.' );
			}
		}

		/**
		 * Hide Freemius's promotional stickies at render, leaving its other notices alone.
		 *
		 * Freemius suppresses a notice on any value that is not exactly true, so the
		 * incoming value is passed straight back for ids we do not claim.
		 *
		 * @param mixed $show_notice Whether Freemius intends to render this notice.
		 * @param array $notice      Freemius message, keyed id, type, manager_id, plugin.
		 * @return mixed
		 */
		public function hide_freemius_promo_notice( $show_notice, $notice ) {
			$notice_id = ( is_array( $notice ) && isset( $notice['id'] ) ) ? $notice['id'] : '';

			if ( in_array( $notice_id, self::FREEMIUS_PROMO_NOTICE_IDS, true ) ) {
				$this->log( 'firss-freemius', sprintf( 'Hid Freemius sticky notice "%s".', $notice_id ) );
				$show_notice = false;
			} else {
				// Every other Freemius notice renders on the vendor's own terms.
			}

			return $show_notice;
		}

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

		/**
		 * Freemius module instance by id, or null when it has not booted.
		 *
		 * get_instance_by_id() reads the SDK's existing registry and returns false when
		 * the module is absent. Freemius::instance() would construct one, so it is not
		 * used here.
		 */
		private function get_freemius_module( int $module_id ) : ?object {
			$freemius_module = null;

			if ( class_exists( 'Freemius' ) && method_exists( 'Freemius', 'get_instance_by_id' ) ) {
				$found_module = \Freemius::get_instance_by_id( $module_id );

				if ( is_object( $found_module ) ) {
					$freemius_module = $found_module;
				} else {
					// Freemius is loaded by another plugin, but not for this module.
				}
			} else {
				// No Freemius on this site.
			}

			return $freemius_module;
		}

		/**
		 * Mechanism 2, for vendors that only register once the screen is known.
		 */
		public function unhook_late_vendor_notices() : void {
			$this->unhook_elementor_promotion_banners();
			$this->unhook_elementskit_promos();
			$this->unhook_code_snippets_promotions();
		}

		/**
		 * Remove Wpmet's promotional notices from ElementsKit's shared libs.
		 *
		 * Each lib builds its notice from a separate admin_head callback, so removing
		 * those leaves the version and dependency notices — which the plugin creates
		 * directly — untouched. ElementsKit Lite 4.0.2. docs/plugins/elementskit-lite.md
		 */
		public function unhook_elementskit_promos() : void {
			$promo_callbacks = [
				[ '\\Wpmet\\Libs\\Rating', 'fire' ],
				[ '\\Wpmet\\Libs\\Banner', 'display_content' ],
				[ '\\Wpmet\\Libs\\Emailkit', 'emailkit_admin_head' ],
				[ '\\ElementsKit_Lite\\Libs\\Pro_Label\\Init', 'show_go_pro_notice' ],
			];

			foreach ( $promo_callbacks as $promo_callback ) {
				$this->remove_discarded_instance_callback(
					'admin_head',
					$promo_callback[0],
					$promo_callback[1],
					'elementskit'
				);
			}
		}

		/**
		 * Remove WP Swings' remotely-configured seasonal offer banners.
		 *
		 * Plain named functions, so no instance is needed. The first name is shared by
		 * every WP Swings plugin and guarded with function_exists, so whichever loads
		 * first owns it. Gift Cards Lite 3.2.10, Subscriptions 2.0.2.
		 * docs/plugins/wp-swings.md
		 */
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

		/**
		 * Replace Premium Addons' notice dispatcher with its dependency check alone.
		 *
		 * One callback prints the Elementor dependency notice and three promos. Both
		 * methods are public, so the dispatcher is swapped for the operational half
		 * rather than the promos being dismissed on the site owner's behalf.
		 * Premium Addons for Elementor 4.11.103. docs/plugins/premium-addons-for-elementor.md
		 */
		public function unhook_premium_addons_promos() : void {
			$notices_class = '\\PremiumAddons\\Admin\\Includes\\Admin_Notices';

			if ( ! class_exists( $notices_class ) || ! method_exists( $notices_class, 'get_instance' ) ) {
				return;
			}

			$notices = $notices_class::get_instance();

			if ( ! is_object( $notices ) || ! method_exists( $notices, 'required_plugins_check' ) ) {
				// Without the dependency check to put back, removing the dispatcher would
				// lose an operational notice. Leave the promos rather than risk that.
				$this->log( 'premium-addons', 'required_plugins_check not reachable; no action taken.' );
			} else {
				remove_action( 'admin_notices', [ $notices, 'admin_notices' ] );
				add_action( 'admin_notices', [ $notices, 'required_plugins_check' ] );
				$this->log( 'premium-addons', 'Swapped admin_notices dispatcher for required_plugins_check.' );
			}
		}

		/**
		 * Remove Forminator's dashboard promo and its review request.
		 *
		 * Forminator 1.57.2. docs/plugins/forminator.md
		 */
		public function unhook_forminator_dashboard_promo() : void {
			if ( ! class_exists( 'Forminator_Core' ) || ! method_exists( '\\Forminator_Core', 'get_instance' ) ) {
				return;
			}

			$forminator_core = \Forminator_Core::get_instance();

			if ( ! is_object( $forminator_core ) || ! isset( $forminator_core->admin ) || ! is_object( $forminator_core->admin ) ) {
				$this->log( 'forminator', 'Installed, but the admin object is not reachable; no action taken.' );
			} else {
				remove_action( 'admin_notices', [ $forminator_core->admin, 'promote_free_plan' ] );
				remove_action( 'admin_enqueue_scripts', [ $forminator_core->admin, 'promote_free_plan_scripts' ] );
				remove_action( 'admin_notices', [ $forminator_core->admin, 'show_rating_notice' ] );
				$this->log( 'forminator', 'Removed promote_free_plan and show_rating_notice from admin_notices.' );
			}
		}

		/**
		 * Remove ShapedPlugin's review request, footer rating text and seasonal offer banner.
		 *
		 * All three go through the $wp_filter reader. Dashboard_Notice is constructed and
		 * discarded in Admin::__construct(), so it has nothing to name.
		 *
		 * ShapedPlugin_Offer_Banner does have an instance() singleton, and it is deliberately
		 * not used: main.php only calls it behind the SHAPEDPLIUGIN_OFFER_BANNER_LOADED mutex
		 * (the vendor's typo, not ours), so on a site where a sibling ShapedPlugin product won
		 * that mutex this plugin's copy was never constructed — and calling instance() would
		 * construct it and *add* the banner hooks. The reader only ever matches what is
		 * already registered.
		 *
		 * That mutex also bounds the rule: the live banner may belong to a sibling plugin's
		 * namespace, which this does not match. WooCommerce-missing and cross-install notices
		 * are separate callbacks and survive.
		 * Product Slider for WooCommerce 2.8.13. docs/plugins/woo-product-slider.md
		 */
		public function unhook_shapedplugin_promos() : void {
			$notice_class = 'ShapedPlugin\\WooProductSlider\\Admin\\Notices\\Dashboard_Notice';
			$banner_class = 'ShapedPlugin\\WooProductSlider\\Admin\\Notices\\ShapedPlugin_Offer_Banner';

			$this->remove_discarded_instance_callback( 'admin_notices', $notice_class, 'display_admin_notice', 'woo-product-slider' );
			$this->remove_discarded_instance_callback( 'admin_footer_text', $notice_class, 'admin_footer', 'woo-product-slider' );
			$this->remove_discarded_instance_callback( 'admin_notices', $banner_class, 'render_offer_banner', 'woo-product-slider' );
		}

		/**
		 * Remove Complianz's "leave a review" notice.
		 *
		 * cmplz_review::this() returns the stored instance and never constructs one, so it is
		 * safe to call — unlike ShapedPlugin's accessor. The vendor registers the notice only
		 * when its own gate passes (free build, not multisite, activated over a month ago and
		 * not yet dismissed), so "not registered" is the normal steady state here and is left
		 * silent rather than logged as drift.
		 * Complianz's compliance warnings come from a different object and survive.
		 * Complianz GDPR 7.5.5. docs/plugins/complianz-gdpr.md
		 */
		public function unhook_complianz_review_notice() : void {
			if ( ! class_exists( 'cmplz_review' ) ) {
				// Not installed.
			} else {
				$complianz_review = \cmplz_review::this();

				if ( ! is_object( $complianz_review ) ) {
					// Class loaded but never constructed.
				} elseif ( false === has_action( 'admin_notices', [ $complianz_review, 'show_leave_review_notice' ] ) ) {
					// Vendor gate not met; nothing queued. Expected, so not logged.
				} else {
					remove_action( 'admin_notices', [ $complianz_review, 'show_leave_review_notice' ] );
					$this->log( 'complianz-gdpr', 'Removed cmplz_review::show_leave_review_notice from admin_notices.' );
				}
			}
		}

		/**
		 * Remove Custom Post Type UI's Pro upsell notice.
		 *
		 * A plain named function at an explicit priority, so it is named directly.
		 * Custom Post Type UI 1.19.3. docs/plugins/custom-post-type-ui.md
		 */
		public function unhook_cptui_pro_upsell() : void {
			if ( false !== has_action( 'admin_notices', 'cptui_pro_upsell_notification' ) ) {
				remove_action( 'admin_notices', 'cptui_pro_upsell_notification', 11 );
				$this->log( 'custom-post-type-ui', 'Removed cptui_pro_upsell_notification from admin_notices priority 11.' );
			}
		}

		/**
		 * Remove Code Snippets' competitor-plugin promotion notice.
		 *
		 * Seven Promotion_Base subclasses each target a different competitor's admin screens,
		 * and the screen lists are disjoint, so at most one is ever registered on a request —
		 * which is why matching the abstract parent once is complete. Runs on current_screen
		 * because the vendor only adds the notice from its own current_screen handler.
		 * Code Snippets 3.10.2. docs/plugins/code-snippets.md
		 */
		public function unhook_code_snippets_promotions() : void {
			$promotion_class = 'Code_Snippets\\Integration\\Promotions\\Notices\\Promotion_Base';
			$removed_count   = 0;

			// Plugin.php constructs Promotion_Manager twice, so the matching promotion is on
			// admin_notices twice and a single remove_action() leaves one still rendering.
			// The finder returns one entry per call, so loop until it stops matching.
			for ( $attempt = 0; $attempt < self::MAX_DUPLICATE_CALLBACKS; $attempt++ ) {
				$found = $this->find_instance_callback( 'admin_notices', $promotion_class, 'display_promotion', 'code-snippets' );

				if ( null === $found ) {
					break;
				}

				remove_action( 'admin_notices', $found['function'], $found['priority'] );
				$removed_count++;
			}

			if ( 0 === $removed_count ) {
				// No competitor screen matched, so the vendor queued nothing. Expected.
			} else {
				$this->log( 'code-snippets', sprintf( 'Removed %d Promotion_Base::display_promotion callback(s) from admin_notices.', $removed_count ) );
			}
		}

		/**
		 * Remove the single callback that prints Elementor's $plain_notices.
		 *
		 * Collateral and withdrawal conditions are in the doc.
		 * Elementor 4.2.4. docs/plugins/elementor.md
		 */
		public function unhook_elementor_notices() : void {
			if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
				return;
			}

			$admin_notices_component = $this->get_elementor_admin_notices_component();

			if ( null === $admin_notices_component ) {
				$this->log( 'elementor', 'Installed, but Admin_Notices component not reachable; no action taken.' );
			} else {
				remove_action( 'admin_notices', [ $admin_notices_component, 'admin_notices' ], 20 );
				$this->log( 'elementor', 'Removed Admin_Notices::admin_notices from admin_notices priority 20.' );
			}
		}

		/**
		 * Remove WPB Product Slider's five-star review notice.
		 *
		 * WPB WooCommerce Product Slider 2.4. docs/plugins/wpb-woocommerce-product-slider.md
		 */
		public function unhook_wpb_product_slider_review_notice() : void {
			$this->remove_discarded_instance_callback(
				'admin_notices',
				'WPB_WPS_Review_Notice',
				'maybe_show_notice',
				'wpb-product-slider'
			);
		}

		/**
		 * Remove Elementor's promotional banners from the promotions module.
		 *
		 * Runs on current_screen, not admin_init: wp-admin/admin.php calls
		 * set_current_screen() after admin_init, and Conversion_Banner only adds its
		 * in_admin_header callback once the screen is known.
		 * Elementor 4.2.4. docs/plugins/elementor.md
		 */
		public function unhook_elementor_promotion_banners() : void {
			$this->remove_discarded_instance_callback(
				'in_admin_header',
				'\\Elementor\\Modules\\Promotions\\Conversion_Banner',
				'render_banner_container',
				'elementor-promotions'
			);

			foreach ( [ 'Black_Friday', 'Birthday' ] as $pointer_class ) {
				$this->remove_discarded_instance_callback(
					'admin_print_footer_scripts-index.php',
					'\\Elementor\\Modules\\Promotions\\Pointers\\' . $pointer_class,
					'enqueue_notice',
					'elementor-promotions'
				);
			}

			add_action( 'admin_enqueue_scripts', [ $this, 'dequeue_elementor_promotion_assets' ], self::LATE_PRIORITY );
		}

		/**
		 * Drop the conversion banner's stylesheet and script.
		 *
		 * An anonymous callback enqueues them, so there is nothing to unhook by name;
		 * dequeuing by handle is the supported route. Harmless when nothing is enqueued.
		 */
		public function dequeue_elementor_promotion_assets() : void {
			wp_dequeue_style( 'e-conversion-banner' );
			wp_dequeue_script( 'e-conversion-banner' );
		}

		/**
		 * Remove one hooked callback belonging to a vendor object we cannot otherwise reach.
		 *
		 * Reads $wp_filter through find_instance_callback() below, which is the only place
		 * in this file permitted to touch it. It matches one class and one method and never
		 * inspects content, which is what separates it from the banned pattern. Every use
		 * needs its own write-up in docs/plugins/, and the first question is always whether
		 * mechanisms 1 to 3 really are all unavailable.
		 */
		private function remove_discarded_instance_callback( string $hook_name, string $class_name, string $method_name, string $rule_id ) : void {
			if ( ! class_exists( $class_name ) ) {
				// Not installed.
			} else {
				$found = $this->find_instance_callback( $hook_name, $class_name, $method_name, $rule_id );

				if ( null === $found ) {
					$this->log( $rule_id, sprintf( '%s::%s not registered on %s; no action taken.', $class_name, $method_name, $hook_name ) );
				} else {
					remove_action( $hook_name, $found['function'], $found['priority'] );
					$this->log( $rule_id, sprintf( 'Removed %s::%s from %s priority %d.', $class_name, $method_name, $hook_name, $found['priority'] ) );
				}
			}
		}

		/**
		 * Find the named method of an instance of the named class on a hook.
		 *
		 * The only place in this file permitted to read $wp_filter, and the reason
		 * remove_discarded_instance_callback() is split: a rule may need the instance
		 * itself to reach a sibling callback, not just the entry to remove.
		 *
		 * Returns the callback array and its priority, or null.
		 */
		private function find_instance_callback( string $hook_name, string $class_name, string $method_name, string $rule_id ) : ?array {
			global $wp_filter;

			$found = null;

			if ( ! isset( $wp_filter[ $hook_name ] ) || ! $wp_filter[ $hook_name ] instanceof \WP_Hook ) {
				// Not the WP_Hook shape used since 4.7.
				$this->log( $rule_id, sprintf( '%s is not a WP_Hook; no action taken.', $hook_name ) );
			} else {
				foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks_at_priority ) {
					foreach ( $callbacks_at_priority as $callback ) {
						if ( $this->is_instance_callback( $callback, $class_name, $method_name ) ) {
							$found = [
								'function' => $callback['function'],
								'priority' => $priority,
							];
							break 2;
						}
					}
				}
			}

			return $found;
		}

		/**
		 * Is this hook entry the named method on an instance of the named class?
		 */
		private function is_instance_callback( array $callback, string $class_name, string $method_name ) : bool {
			$is_match = false;

			if ( ! isset( $callback['function'] ) || ! is_array( $callback['function'] ) ) {
				// Named function, closure or static call; never a target.
			} elseif ( 2 !== count( $callback['function'] ) || ! is_object( $callback['function'][0] ) ) {
				// Class-name-and-method array rather than an instance method.
			} else {
				$is_match = $callback['function'][0] instanceof $class_name
					&& $method_name === $callback['function'][1];
			}

			return $is_match;
		}

		/**
		 * Remove core's Welcome panel from the dashboard, when the site owner opts in.
		 *
		 * Runs on admin_init: admin.php registers wp_welcome_panel after it loads
		 * mu-plugins, so at file scope there is nothing to remove.
		 */
		public function remove_core_welcome_panel() : void {
			if ( $this->is_constant_enabled( 'HEADWALL_NAG_CLEANUP_REMOVE_WELCOME_PANEL' ) ) {
				// Leaving the hook empty also drops the Screen Options checkbox, which
				// core gates on has_action().
				remove_action( 'welcome_panel', 'wp_welcome_panel' );
				$this->log( 'wordpress-core', 'Removed wp_welcome_panel from welcome_panel.' );
			} else {
				// Core output stays unless the site owner opts in.
			}
		}

		/**
		 * Elementor's Admin_Notices component, or null if it cannot be reached.
		 */
		private function get_elementor_admin_notices_component() : ?object {
			$component = null;

			if ( ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
				// Elementor has not bootstrapped.
			} elseif ( ! isset( \Elementor\Plugin::$instance->admin ) || ! is_object( \Elementor\Plugin::$instance->admin ) ) {
				// Admin module absent; Elementor only builds it for admin requests.
			} elseif ( ! method_exists( \Elementor\Plugin::$instance->admin, 'get_component' ) ) {
				// Component API gone.
			} else {
				$candidate = \Elementor\Plugin::$instance->admin->get_component( 'admin-notices' );

				if ( is_object( $candidate ) && method_exists( $candidate, 'admin_notices' ) ) {
					$component = $candidate;
				} else {
					// Component renamed or removed.
				}
			}

			return $component;
		}

		/**
		 * Mechanism 3: remove promotional dashboard widgets before they render.
		 */
		public function remove_promotional_dashboard_widgets() : void {
			$widgets_to_remove = self::PROMOTIONAL_DASHBOARD_WIDGETS;

			if ( $this->is_constant_enabled( 'HEADWALL_NAG_CLEANUP_REMOVE_CORE_DASHBOARD_WIDGETS' ) ) {
				$widgets_to_remove = array_merge( $widgets_to_remove, self::CORE_DASHBOARD_WIDGETS );
			} else {
				// Core widgets stay unless the site owner opts in.
			}

			foreach ( $widgets_to_remove as $widget ) {
				// A null screen resolves to the current one, covering all three dashboards.
				remove_meta_box( $widget['widget_id'], null, $widget['context'] );

				$this->log(
					$widget['vendor'],
					sprintf( 'Removed dashboard widget "%s" (%s).', $widget['widget_id'], $widget['reason'] )
				);
			}
		}

		/**
		 * Is a constant defined and truthy?
		 */
		private function is_constant_enabled( string $constant_name ) : bool {
			return defined( $constant_name ) && constant( $constant_name );
		}

		/**
		 * Log a suppression when HEADWALL_NAG_CLEANUP_DEBUG is set.
		 */
		private function log( string $rule_id, string $message ) : void {
			if ( $this->is_constant_enabled( 'HEADWALL_NAG_CLEANUP_DEBUG' ) ) {
				error_log( sprintf( '[headwall-nag-cleanup %s] %s: %s', self::VERSION, $rule_id, $message ) );
			} else {
				// Logging is off by default.
			}
		}
	}

	// Declared global so an include from inside a function still leaves the instance
	// reachable to remove_filter().
	global $headwall_nag_cleanup;

	$headwall_nag_cleanup = new Plugin();
	$headwall_nag_cleanup->run();
}
