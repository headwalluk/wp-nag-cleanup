<?php
/**
 * Plugin Name: Headwall Nag Cleanup
 * Plugin URI:  https://github.com/headwalluk/wp-nag-cleanup
 * Description: Removes promotional clutter from the WordPress admin notice area and dashboard, leaving operational notices intact.
 * Version:     1.14.0
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

		const VERSION = '1.14.0';

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
		 * Widgets removed by mechanism 3, as widget ID, meta box context, vendor and reason.
		 */
		const PROMOTIONAL_DASHBOARD_WIDGETS = [
			[
				'widget_id' => 'pa-stories',
				'context'   => 'column3',
				'vendor'    => 'Premium Addons for Elementor 4.11.102',
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
			if ( $this->is_admin_page_request() ) {
				$this->register_vendor_optouts();

				add_action( 'admin_init', [ $this, 'unhook_vendor_notices' ], self::LATE_PRIORITY );
				add_action( 'admin_init', [ $this, 'remove_core_welcome_panel' ], self::LATE_PRIORITY );
				add_action( 'current_screen', [ $this, 'unhook_late_vendor_notices' ], self::LATE_PRIORITY );
				add_action( 'wp_dashboard_setup', [ $this, 'remove_promotional_dashboard_widgets' ], self::LATE_PRIORITY );
				add_action( 'wp_network_dashboard_setup', [ $this, 'remove_promotional_dashboard_widgets' ], self::LATE_PRIORITY );
				add_action( 'wp_user_dashboard_setup', [ $this, 'remove_promotional_dashboard_widgets' ], self::LATE_PRIORITY );
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

			// ThemeIsle SDK, bundled in Menu Icons, WPCF7 Redirect and others.
			// Menu Icons 0.13.24. docs/plugins/themeisle-sdk.md
			add_filter( 'themeisle_sdk_hide_dashboard_widget', '__return_true' );

			// CookieYes 3.5.5 review request and web-app connect banner.
			// docs/plugins/cookie-law-info.md
			add_filter( 'cky_is_module_active_review_feedback', '__return_false' );
			add_filter( 'cky_is_module_active_connect_banner', '__return_false' );

			// WebToffee cross-promotion banner, shared across their range. The vendor
			// uses this constant as a first-loader mutex, so defining it here skips the
			// banner everywhere. CookieYes 3.5.5. docs/plugins/cookie-law-info.md
			defined( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED' ) || define( 'CYA11Y_ACCESSYES_BANNER_DISPLAYED', true );

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
		 * Mechanism 2, for vendors that only register once the screen is known.
		 */
		public function unhook_late_vendor_notices() : void {
			$this->unhook_elementor_promotion_banners();
			$this->unhook_elementskit_promos();
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
		 * Premium Addons for Elementor 4.11.102. docs/plugins/premium-addons-for-elementor.md
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
		 * Remove the single callback that prints Elementor's $plain_notices.
		 *
		 * Collateral and withdrawal conditions: docs/plugins/elementor.md
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
		 * docs/plugins/wpb-woocommerce-product-slider.md
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
		 * docs/plugins/elementor.md
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
		 * The only place in this file permitted to read $wp_filter. It matches one class
		 * and one method and never inspects content, which is what separates it from the
		 * banned pattern. Every use needs its own write-up in docs/plugins/, and the first
		 * question is always whether mechanisms 1 to 3 really are all unavailable.
		 */
		private function remove_discarded_instance_callback( string $hook_name, string $class_name, string $method_name, string $rule_id ) : void {
			global $wp_filter;

			$found_callback = null;
			$found_priority = null;

			if ( ! class_exists( $class_name ) ) {
				// Not installed.
			} elseif ( ! isset( $wp_filter[ $hook_name ] ) || ! $wp_filter[ $hook_name ] instanceof \WP_Hook ) {
				// Not the WP_Hook shape used since 4.7.
				$this->log( $rule_id, sprintf( '%s is not a WP_Hook; no action taken.', $hook_name ) );
			} else {
				foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks_at_priority ) {
					foreach ( $callbacks_at_priority as $callback ) {
						if ( $this->is_instance_callback( $callback, $class_name, $method_name ) ) {
							$found_callback = $callback['function'];
							$found_priority = $priority;
							break 2;
						}
					}
				}

				if ( null === $found_callback ) {
					$this->log( $rule_id, sprintf( '%s::%s not registered on %s; no action taken.', $class_name, $method_name, $hook_name ) );
				} else {
					remove_action( $hook_name, $found_callback, $found_priority );
					$this->log( $rule_id, sprintf( 'Removed %s::%s from %s priority %d.', $class_name, $method_name, $hook_name, $found_priority ) );
				}
			}
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
