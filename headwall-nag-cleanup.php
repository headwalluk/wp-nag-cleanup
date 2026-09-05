<?php
/**
 * Plugin Name: Headwall Nag Cleanup
 * Plugin URI:  https://github.com/headwalluk/wp-nag-cleanup
 * Description: Removes promotional clutter from the WordPress admin notice area and dashboard, leaving operational notices intact.
 * Version:     1.5.0
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

		const VERSION = '1.5.0';

		/**
		 * Widgets removed by mechanism 3, as widget ID, meta box context, vendor and reason.
		 */
		const PROMOTIONAL_DASHBOARD_WIDGETS = [];

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

				add_action( 'admin_init', [ $this, 'unhook_vendor_notices' ], 999 );
				add_action( 'admin_init', [ $this, 'remove_core_welcome_panel' ], 999 );
				add_action( 'wp_dashboard_setup', [ $this, 'remove_promotional_dashboard_widgets' ], 999 );
				add_action( 'wp_network_dashboard_setup', [ $this, 'remove_promotional_dashboard_widgets' ], 999 );
				add_action( 'wp_user_dashboard_setup', [ $this, 'remove_promotional_dashboard_widgets' ], 999 );
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
			// Priority 999: UsageDataTracker adds its own callback returning true at 10.
			add_filter( 'wpdesk_tracker_enabled', '__return_false', 999 );

			// Brainstorm Force bsf-analytics, Astra Pro 4.13.8. docs/plugins/brainstorm-force.md
			add_filter( 'bsf_usage_tracking_enabled', '__return_false' );

			// ThemeIsle SDK, bundled in Menu Icons, WPCF7 Redirect and others.
			// Menu Icons 0.13.24. docs/plugins/themeisle-sdk.md
			add_filter( 'themeisle_sdk_hide_dashboard_widget', '__return_true' );

			// CookieYes 3.5.5 review request module. docs/plugins/cookie-law-info.md
			add_filter( 'cky_is_module_active_review_feedback', '__return_false' );

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
		 * The vendor discards the notice instance, so $wp_filter is read to find it.
		 * The only sanctioned such read in this file.
		 * docs/plugins/wpb-woocommerce-product-slider.md
		 */
		public function unhook_wpb_product_slider_review_notice() : void {
			global $wp_filter;

			$review_notice_callback = null;
			$review_notice_priority = null;

			if ( ! class_exists( 'WPB_WPS_Review_Notice' ) ) {
				// Not installed.
			} elseif ( ! isset( $wp_filter['admin_notices'] ) || ! $wp_filter['admin_notices'] instanceof \WP_Hook ) {
				// Not the WP_Hook shape used since 4.7.
				$this->log( 'wpb-product-slider', 'admin_notices is not a WP_Hook; no action taken.' );
			} else {
				foreach ( $wp_filter['admin_notices']->callbacks as $priority => $callbacks_at_priority ) {
					foreach ( $callbacks_at_priority as $callback ) {
						if ( $this->is_wpb_review_notice_callback( $callback ) ) {
							$review_notice_callback = $callback['function'];
							$review_notice_priority = $priority;
							break 2;
						}
					}
				}

				if ( null === $review_notice_callback ) {
					$this->log( 'wpb-product-slider', 'Review notice callback not registered; no action taken.' );
				} else {
					remove_action( 'admin_notices', $review_notice_callback, $review_notice_priority );
					$this->log(
						'wpb-product-slider',
						sprintf( 'Removed WPB_WPS_Review_Notice::maybe_show_notice from admin_notices priority %d.', $review_notice_priority )
					);
				}
			}
		}

		/**
		 * Is this hook entry WPB_WPS_Review_Notice::maybe_show_notice?
		 */
		private function is_wpb_review_notice_callback( array $callback ) : bool {
			$is_review_notice = false;

			if ( ! isset( $callback['function'] ) || ! is_array( $callback['function'] ) ) {
				// Named function, closure or static call; never this rule's target.
			} elseif ( 2 !== count( $callback['function'] ) || ! is_object( $callback['function'][0] ) ) {
				// Class-name-and-method array rather than an instance method.
			} else {
				$is_review_notice = $callback['function'][0] instanceof \WPB_WPS_Review_Notice
					&& 'maybe_show_notice' === $callback['function'][1];
			}

			return $is_review_notice;
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
