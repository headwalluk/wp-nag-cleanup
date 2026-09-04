<?php
/**
 * Plugin Name: Headwall Nag Cleanup
 * Plugin URI:  https://github.com/headwalluk/wp-nag-cleanup
 * Description: Removes promotional clutter from the WordPress admin notice area and dashboard, leaving operational notices intact.
 * Version:     0.1.2
 * Author:      Headwall Hosting
 * Author URI:  https://headwall-hosting.com/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * A single-file mu-plugin. Drop it into wp-content/mu-plugins/, or require it from
 * another plugin or a child theme's functions.php. It is safe to load more than once.
 *
 * Configuration is by constant only. There is deliberately no settings page: a tool
 * that exists to reduce admin clutter must not add an admin menu entry of its own.
 *
 *   HEADWALL_NAG_CLEANUP_DEBUG
 *       Log every suppression to the PHP error log. Default off.
 *
 *   HEADWALL_NAG_CLEANUP_REMOVE_CORE_DASHBOARD_WIDGETS
 *       Also remove core's "WordPress Events and News" widget, which fetches from
 *       api.wordpress.org on every dashboard load. Default off, because it is core
 *       output rather than a vendor nag, so removing it is the site owner's call.
 *
 * @package Headwall_Nag_Cleanup
 */

namespace Headwall_Nag_Cleanup;

defined( 'ABSPATH' ) || die();

/*
 * The class is declared INSIDE this guard rather than after an early return.
 *
 * PHP early-binds an unconditional top-level class when the file is compiled, so a
 * class_exists() check placed above the declaration is already true on the very
 * first include: the file would return and the plugin would silently never boot.
 * A top-level function is worse still, fatalling on the second include before any
 * guard can run. Verified on PHP 8.5. See CLAUDE.md.
 */
if ( ! class_exists( __NAMESPACE__ . '\\Plugin' ) ) {

	/**
	 * Suppresses named promotional notices and dashboard widgets from named plugins.
	 */
	class Plugin {

		const VERSION = '0.1.2';

		/**
		 * Promotional dashboard widgets removed on every dashboard load.
		 *
		 * Each entry names the widget ID, the meta box context it was registered in,
		 * the vendor and version the ID was verified against, and why it goes.
		 */
		const PROMOTIONAL_DASHBOARD_WIDGETS = [
			[
				'widget_id' => 'yith_dashboard_products_news',
				'context'   => 'normal',
				'vendor'    => 'yith-plugin-fw 4.7.8',
				'reason'    => 'RSS widget fetching https://yithemes.com/latest-updates/feeds/ on dashboard render',
			],
			[
				'widget_id' => 'yith_dashboard_blog_news',
				'context'   => 'normal',
				'vendor'    => 'yith-plugin-fw 4.7.8',
				'reason'    => 'RSS widget fetching https://yithemes.com/feed/ on dashboard render',
			],
		];

		/**
		 * Core dashboard widgets, removed only when the site owner opts in.
		 */
		const CORE_DASHBOARD_WIDGETS = [
			[
				'widget_id' => 'dashboard_primary',
				// Core forces this widget into the side column regardless of the
				// context passed to wp_add_dashboard_widget(). See wp-admin/includes/dashboard.php.
				'context'   => 'side',
				'vendor'    => 'WordPress core 7.1',
				'reason'    => 'WordPress Events and News; fetches api.wordpress.org on dashboard render',
			],
		];

		/**
		 * Create the single instance and run it, unless an earlier include already did.
		 */
		public static function boot() : void {
			global $headwall_nag_cleanup;

			if ( isset( $headwall_nag_cleanup ) && $headwall_nag_cleanup instanceof self ) {
				// Already booted by an earlier include of this file. Nothing to do.
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
				// Front end, AJAX, cron or a JSON request. None of these print admin
				// notices or build a dashboard, so registering anything here would only
				// add work to requests that can never show a nag.
			}
		}

		/**
		 * Is this a request that can actually render an admin notice or a dashboard?
		 */
		private function is_admin_page_request() : bool {
			$is_admin_page_request = true;

			if ( ! is_admin() ) {
				// Front end: the notice area and the dashboard are admin-only.
				$is_admin_page_request = false;
			} elseif ( wp_doing_ajax() ) {
				// admin-ajax.php reports is_admin() as true but renders no notices.
				$is_admin_page_request = false;
			} elseif ( wp_doing_cron() ) {
				// Cron has no output surface.
				$is_admin_page_request = false;
			} elseif ( wp_is_json_request() ) {
				// REST_REQUEST is not defined this early in the load, so the shape of
				// the request is tested rather than the constant.
				$is_admin_page_request = false;
			} else {
				// A normal admin page request. Proceed.
			}

			return $is_admin_page_request;
		}

		/**
		 * Mechanism 1: the vendor's own opt-out hooks. Registered at file scope.
		 */
		private function register_vendor_optouts() : void {
			// Essential Addons for Elementor (WPDeveloper) 6.8.3. A real, documented
			// vendor kill switch, covering the ThinkRank promo banner, both
			// promotional dashboard widgets and the Black Friday pointer. The vendor
			// reads it per-surface rather than once at construction, so registering it
			// this early is explicitly the supported use. Note it has only existed
			// since 6.7.2, and is inert on anything older.
			// See docs/plugins/essential-addons-for-elementor-lite.md.
			add_filter( 'eael/disable_promotions', '__return_true', 100 );
			$this->log( 'essential-addons', 'Registered eael/disable_promotions.' );

			// EmbedPress carried two filters here until 0.1.1. Neither hook name
			// exists in any of the 57 releases held in the vault (4.0.5 to 4.6.5), so
			// they never fired. EmbedPress 4.6.5 has no suppressible promotional
			// notices at all: its review and upsell framework is present but never
			// instantiated, and everything it does register is operational. No action
			// is taken for this vendor. See docs/plugins/embedpress.md.
		}

		/**
		 * Mechanism 2: targeted unhooking. Runs on admin_init, late.
		 */
		public function unhook_vendor_notices() : void {
			$this->unhook_elementor_notices();
		}

		/**
		 * Remove Elementor's promotional admin notices.
		 *
		 * Elementor 4.2.4 prints all eleven of its $plain_notices from a single
		 * callback, nine of which are promotional. Removing it also loses
		 * api_upgrade_plugin and local_google_fonts_disabled: an accepted and
		 * documented trade, not an oversight. The database upgrade prompts and the
		 * PHP/WP version failures use separate callbacks and are untouched.
		 *
		 * See docs/plugins/elementor.md, including the drift check that says when
		 * this rule must be withdrawn.
		 */
		public function unhook_elementor_notices() : void {
			$admin_notices_component = $this->get_elementor_admin_notices_component();

			if ( null === $admin_notices_component ) {
				// Elementor is absent, or has moved the component. Either way there is
				// nothing to remove, and nothing worth warning a site owner about.
				$this->log( 'elementor', 'Admin_Notices component not reachable; no action taken.' );
			} else {
				remove_action( 'admin_notices', [ $admin_notices_component, 'admin_notices' ], 20 );
				$this->log( 'elementor', 'Removed Admin_Notices::admin_notices from admin_notices priority 20.' );
			}
		}

		/**
		 * Resolve Elementor's Admin_Notices component, or null if it is not reachable.
		 *
		 * Every step is guarded: this must degrade to doing nothing on a site without
		 * Elementor, and on a future Elementor that has moved things around.
		 */
		private function get_elementor_admin_notices_component() : ?object {
			$component = null;

			if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
				// Elementor is not installed or not active.
			} elseif ( ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
				// Elementor has not bootstrapped, so it has hooked nothing either.
			} elseif ( ! isset( \Elementor\Plugin::$instance->admin ) || ! is_object( \Elementor\Plugin::$instance->admin ) ) {
				// The Admin module is absent. Elementor only builds it for admin requests.
			} elseif ( ! method_exists( \Elementor\Plugin::$instance->admin, 'get_component' ) ) {
				// Elementor has moved away from the component API this rule relies on.
			} else {
				$candidate = \Elementor\Plugin::$instance->admin->get_component( 'admin-notices' );

				if ( is_object( $candidate ) && method_exists( $candidate, 'admin_notices' ) ) {
					$component = $candidate;
				} else {
					// The component was renamed, removed, or no longer prints notices.
				}
			}

			return $component;
		}

		/**
		 * Mechanism 3: remove promotional and feed-fetching dashboard widgets.
		 *
		 * Removing the widget before it renders also prevents the outbound request it
		 * would have made, which is most of the reason these rules exist.
		 */
		public function remove_promotional_dashboard_widgets() : void {
			$widgets_to_remove = self::PROMOTIONAL_DASHBOARD_WIDGETS;

			if ( $this->is_constant_enabled( 'HEADWALL_NAG_CLEANUP_REMOVE_CORE_DASHBOARD_WIDGETS' ) ) {
				$widgets_to_remove = array_merge( $widgets_to_remove, self::CORE_DASHBOARD_WIDGETS );
			} else {
				// Core widgets are left in place by default. Core output is the site
				// owner's to remove, not ours to decide.
			}

			foreach ( $widgets_to_remove as $widget ) {
				// A null screen resolves to the current screen, so this one method
				// serves the site, network and user dashboards alike.
				remove_meta_box( $widget['widget_id'], null, $widget['context'] );

				$this->log(
					$widget['vendor'],
					sprintf( 'Removed dashboard widget "%s" (%s).', $widget['widget_id'], $widget['reason'] )
				);
			}
		}

		/**
		 * Is a configuration constant defined and truthy?
		 */
		private function is_constant_enabled( string $constant_name ) : bool {
			return defined( $constant_name ) && constant( $constant_name );
		}

		/**
		 * Record a suppression. Silent unless HEADWALL_NAG_CLEANUP_DEBUG is on.
		 */
		private function log( string $rule_id, string $message ) : void {
			if ( $this->is_constant_enabled( 'HEADWALL_NAG_CLEANUP_DEBUG' ) ) {
				// Deliberately error_log() rather than a logging dependency.
				error_log( sprintf( '[headwall-nag-cleanup %s] %s: %s', self::VERSION, $rule_id, $message ) );
			} else {
				// Debug logging is off. This is the default and costs nothing.
			}
		}
	}

	Plugin::boot();
}
