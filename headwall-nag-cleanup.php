<?php
/**
 * Plugin Name: Headwall Nag Cleanup
 * Plugin URI:  https://github.com/headwalluk/wp-nag-cleanup
 * Description: Removes promotional clutter from the WordPress admin notice area and dashboard, leaving operational notices intact.
 * Version:     1.1.0
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

		const VERSION = '1.1.0';

		/**
		 * Widgets removed by mechanism 3, as widget ID, meta box context, vendor and reason.
		 *
		 * Empty since 1.0.0: YITH moved to a vendor filter. See docs/plugins/yith-plugin-fw.md.
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
		 * Create the instance and run it, unless an earlier include already did.
		 */
		public static function boot() : void {
			global $headwall_nag_cleanup;

			if ( isset( $headwall_nag_cleanup ) && $headwall_nag_cleanup instanceof self ) {
				// Already booted by an earlier include.
			} else {
				$headwall_nag_cleanup = new self();
				$headwall_nag_cleanup->run();
			}
		}

		/**
		 * Register everything this plugin does.
		 */
		public function run() : void {
			if ( $this->is_admin_page_request() ) {
				$this->register_vendor_optouts();

				add_action( 'admin_init', [ $this, 'unhook_vendor_notices' ], 999 );
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
			// Essential Addons for Elementor 6.8.3, read per-surface by the vendor.
			// docs/plugins/essential-addons-for-elementor-lite.md
			add_filter( 'eael/disable_promotions', '__return_true', 100 );
			$this->log( 'essential-addons', 'Registered eael/disable_promotions.' );

			// YITH plugin-fw 4.7.8, bundled in every YITH plugin. Gates both RSS
			// dashboard widgets and their asset enqueue.
			// docs/plugins/yith-plugin-fw.md
			add_filter( 'yith_plugin_fw_show_dashboard_widgets', '__return_false' );
			$this->log( 'yith-plugin-fw', 'Registered yith_plugin_fw_show_dashboard_widgets opt-out.' );

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

			// EmbedPress needs no rule; its promo framework is never instantiated.
			// docs/plugins/embedpress.md
		}

		/**
		 * Mechanism 2: targeted unhooking, late enough that vendor hooks exist.
		 */
		public function unhook_vendor_notices() : void {
			$this->unhook_elementor_notices();
		}

		/**
		 * Remove the single callback that prints Elementor's $plain_notices.
		 *
		 * Takes api_upgrade_plugin and local_google_fonts_disabled with it as accepted
		 * collateral. Database upgrade notices use separate callbacks and survive.
		 * Rationale and withdrawal conditions: docs/plugins/elementor.md
		 */
		public function unhook_elementor_notices() : void {
			$admin_notices_component = $this->get_elementor_admin_notices_component();

			if ( null === $admin_notices_component ) {
				$this->log( 'elementor', 'Admin_Notices component not reachable; no action taken.' );
			} else {
				remove_action( 'admin_notices', [ $admin_notices_component, 'admin_notices' ], 20 );
				$this->log( 'elementor', 'Removed Admin_Notices::admin_notices from admin_notices priority 20.' );
			}
		}

		/**
		 * Elementor's Admin_Notices component, or null if it cannot be reached.
		 */
		private function get_elementor_admin_notices_component() : ?object {
			$component = null;

			if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
				// Elementor not installed or not active.
			} elseif ( ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
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
				// A null screen resolves to the current one, covering the site, network
				// and user dashboards from this one method.
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

	Plugin::boot();
}
