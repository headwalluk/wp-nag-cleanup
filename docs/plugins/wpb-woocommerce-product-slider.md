# WPB Product Slider for WooCommerce

- slug: `wpb-woocommerce-product-slider`
- version analysed: `2.4`
- source: `/vault/backups/wordpress/plugins/wpb-woocommerce-product-slider/wpb-woocommerce-product-slider,2.4.zip`
- licensing: freemium (free on wordpress.org, PRO sold at wpbean.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5).

A small plugin — nine PHP files, no framework, no vendor SDK. It puts exactly **one**
promotional notice in the admin notice area: a five-star review request that appears a
week after installation, added in 2.4. The vault holds only 2.3 and 2.4; 2.3 has no
review notice class at all.

The plugin's other promotional surfaces are real but all sit outside this project's
scope — the vendor's own settings screens, the plugin list row, and the admin menu.
Its second `admin_notices` registration looks promotional from its name but is a
plugin-conflict notice and stays.

**One rule is added, and it is the first in this project to read `$wp_filter`.** The
vendor discards the notice object, so no other mechanism can reach it. The exception
and its boundaries are set out in "The `$wp_filter` exception" below.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 2 live, 1 commented out. No `network_admin_notices` or `all_admin_notices` registrations |
| Vendor opt-out filters | **None.** The plugin exposes 10 `apply_filters()` calls in total; every one is about slider markup, query args, thumbnail size, menu capability or the PRO sidebar feature list. Not one gates a notice |
| Vendor opt-out constants | None |
| Dashboard widgets | None. No `wp_add_dashboard_widget()` anywhere |
| Outbound calls from widgets | None. No `wp_remote_get`, `wp_remote_post`, `fetch_feed` or feed transient anywhere in the plugin |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Five-star review request | `admin_notices` → `WPB_WPS_Review_Notice::maybe_show_notice`, priority 10 | **suppress** | "Enjoying…? a quick 5-star review would mean the world to us." Review begging, no site state |
| Free/premium conflict notice | `admin_notices` → `wpb_wps_install_free_admin_notice`, priority 10 | keep | Plugin conflict notice. Never suppressed |
| PRO 10% discount notice | `wpb_wps_pro_discount_admin_notice` | keep (moot) | **Registration is commented out** at `main.php:146` in both 2.3 and 2.4. Never hooked, so nothing to remove |
| Review request in admin footer | `admin_footer_text` → `wpb_wps_wp_admin_bottom_left_text` | keep | Filter is global but the callback only rewrites the text on the plugin's own two screens. Vendor's own interface |
| "Upgrade to Pro" menu button CSS | `admin_head` → `admin_upgrade_pro_styles` | keep | Styles an admin menu item. Not the notice area or the dashboard |
| "Upgrade to PRO!" action link | `plugin_action_links_*` | keep | Plugin list row. Out of scope |
| PRO sidebar on settings page | `wpb_wps_settings_sidebar` | keep | Vendor's own settings screen. Out of scope by construction |
| Post-activation redirect to settings | `activated_plugin` → `wpb_wps_free_activation_redirect` | keep | Not a notice, and it lands on the plugin's own settings page rather than an upsell |

## Deliberately left alone

**`wpb_wps_install_free_admin_notice` — read the callback, not the name.** This one is
worth recording because the name reads like a cross-sell ("install free"). It is not.
The whole block is wrapped in `if ( defined( 'WPB_WPS_PREMIUM' ) )`, prints *"You
can't activate the free version … while you are using the premium one"*, and then
calls `deactivate_plugins()` on itself. It is a plugin-conflict notice explaining why
a plugin just deactivated, which is squarely on the never-suppress list. Suppressing
it would leave a site owner with a plugin that silently refuses to activate and no
explanation anywhere.

**The PRO discount notice is already dead.** `wpb_wps_pro_discount_admin_notice()` is
defined at `main.php:105` and prints a "10% exclusive discount / use code
10PERCENTOFF" banner. Its `add_action` at `main.php:146` is **commented out**, in 2.3
and 2.4 alike. Only the dismissal handler is still registered. A rule targeting it
would be a rule against a hook nobody fires — the exact mistake corrected in 0.1.1 for
EmbedPress. If a future version uncomments that line it becomes a clean mechanism 2
target, because it is a plain named function.

**The admin footer review request.** `add_filter( 'admin_footer_text', … )` is
registered globally, which looks alarming, but the callback checks
`get_current_screen()->base` and only substitutes its text on the plugin's own two
screens, returning `$text` untouched everywhere else. Vendor's own interface, so out
of scope. Noted in passing: the two screen bases it tests (`toplevel_page_wpb-wps-about`,
`woo-slider_page_wpb-wps-settings`) do not match the menu slugs the plugin actually
registers in 2.4 (`wpb-woocommerce-product-slider-settings` / `-about`), so the
condition looks like it never matches at all. Either way it is not ours to fix.

**The "Upgrade to Pro" menu styling.** `admin_upgrade_pro_styles()` runs on
`admin_head` on every admin page and emits CSS turning the Pro submenu link into a
crimson button. It is promotional and it is global, but it styles an **admin menu
item**, not a notice or a dashboard widget. This project touches the notice area and
the dashboard only; widening that to the admin menu is a different tool.

## The `$wp_filter` exception

The review notice is the one clear-cut suppression target in this plugin, and none of
the three standard mechanisms can reach it.

- **Mechanism 1 is unavailable.** There is no vendor filter, action or constant gating
  the notice. The plugin's only gates are `get_user_meta( $user_id,
  'wpb_wps_review_dismissed' )`, `get_user_meta( $user_id, 'wpb_wps_review_later' )`,
  the `wpb_wps_installed` option, and a `DEV_MODE` class constant that is `false` and
  is not `defined()`-overridable
- **Mechanism 2 is unavailable.** The callback is `array( $this, 'maybe_show_notice' )`
  on an instance created at `main.php:157` as a bare `new WPB_WPS_Review_Notice();`
  inside `wpb_wps_free_plugin_init()`, on `plugins_loaded` priority 10. The return
  value is discarded. There is no singleton, no static accessor, no global, and no
  public property on any other object holding it. `remove_action()` matches an object
  callback by `spl_object_hash()`, so a second instance we construct ourselves would
  not match. The instance is only recoverable by walking `$wp_filter`, which the
  project bans
- **Mechanism 3 does not apply.** It is not a dashboard widget

Disabling the plugin's `plugins_loaded` bootstrap would take the shortcodes, widgets
and settings screen with it, so that is not an option either.

### The option that was rejected

**Short-circuiting `get_user_metadata` for `wpb_wps_review_dismissed`**, or writing
that meta directly, would make the plugin believe the notice was already dismissed.
Both were rejected.

Writing the meta is the worse of the two: it leaves permanent residue in `wp_usermeta`
on every admin user of every site, it is not undone by removing this plugin, and it
silently tells the vendor a site owner declined to review when they never saw the
request. It also turns a read-only hook-level tool into one that mutates third-party
state. Filtering the read avoids the residue but keeps the rest: it works by lying
about stored state rather than by unhooking, it fires on every user meta read on every
request, and it generalises to any notice gated on a dismissal flag — including
operational ones. That is the general-purpose lever this project has refused to build.

A sketch of the write-the-meta version was also wrong in two ways worth recording, in
case it is proposed again. The plugin stores the **string** `'true'`
(`class-wpb-wps-review-notice.php:130`) and gates on a truthy check, so a `=== true`
comparison never matches; and writing boolean `true` stores `'1'`, which defeats the
unchanged-value skip in `update_metadata()` at `wp-includes/meta.php:259`, so it would
issue an `UPDATE` on every admin page load.

### What was built instead

A **class-scoped `$wp_filter` lookup**: scan `$wp_filter['admin_notices']->callbacks`,
match only a callback whose object is `instanceof WPB_WPS_Review_Notice` and whose
method is `maybe_show_notice`, and `remove_action()` that one entry.

This is narrower than the pattern `CLAUDE.md` bans. That rule prohibits walking
`$wp_filter` "removing whatever looks promotional" — heuristic removal by appearance.
This names one class and one method and inspects no content at all. It is still an
exception, and it is the only one: **nothing else in `headwall-nag-cleanup.php` may
read `$wp_filter` without an equivalent write-up here.**

Costs accepted:

- `WP_Hook::$callbacks` is a public property but not a documented API, and its shape
  changed in WP 4.7. The rule guards on `instanceof \WP_Hook` and no-ops with a debug
  log if the shape is not what it expects
- It scans every priority rather than the vendor's current 10, so a vendor priority
  change does not silently kill the rule
- If the callback is not found the rule logs and does nothing, so
  `HEADWALL_NAG_CLEANUP_DEBUG` still answers "why did I never see that prompt?"

Verified against core 7.1's real `WP_Hook` and `remove_action()` with a harness
covering: removal at the default priority; removal at priority 42; removal with a
closure earlier on the hook; an empty hook; a hook with only unrelated callbacks; and
— the important one — a decoy class exposing a `maybe_show_notice()` method of its
own, which survives untouched. The vendor's own `handle_notice_action` also survives,
so the dismissal links keep working for anyone who has already seen the notice.

## Mechanism

- tier: 2 (targeted unhook), with the `$wp_filter` exception above
- phase: `admin_init`, priority 999
- vendor registers at: `WPB_WPS_Review_Notice::__construct`, reached from
  `wpb_wps_free_plugin_init()` on `plugins_loaded` priority 10 — so `admin_init` is
  comfortably late enough
- instance reachable via: **nothing the vendor exposes.** `new WPB_WPS_Review_Notice();`
  at `main.php:157` discards the return value. Recovered from
  `$wp_filter['admin_notices']->callbacks` by `instanceof` match

## Drift check

Re-check when a new version appears in the vault:

- `main.php:146` — if `//add_action( 'admin_notices', 'wpb_wps_pro_discount_admin_notice' );`
  is uncommented, that notice becomes a clean mechanism 2 target: a plain named
  function on `admin_notices` at default priority
- `main.php:157` — if `new WPB_WPS_Review_Notice()` is ever assigned to a global, a
  static property or a singleton, **withdraw the `$wp_filter` exception** and rewrite
  the rule as an ordinary mechanism 2 unhook naming the instance
- `inc/class-wpb-wps-review-notice.php` — if a filter or constant is added around
  `maybe_show_notice()`, that is a mechanism 1 rule and likewise retires the exception.
  A rename of the class or of `maybe_show_notice` makes the rule a silent no-op, which
  the debug log will report as "callback not registered"
- `wp-includes/class-wp-hook.php` — if `WP_Hook::$callbacks` stops being a public
  `array<int, array<string, array>>`, the rule no-ops rather than breaking, but it
  needs revisiting
- `main.php:61` — `wpb_wps_install_free_admin_notice` must stay untouched. If a future
  version reuses that function name for an actual cross-sell, re-read it before
  assuming the classification still holds

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2 with the `$wp_filter` exception

Called from `unhook_vendor_notices()` on `admin_init` priority 999.

```php
public function unhook_wpb_product_slider_review_notice() : void {
	global $wp_filter;

	$review_notice_callback = null;
	$review_notice_priority = null;

	if ( ! class_exists( 'WPB_WPS_Review_Notice' ) ) {
		// Plugin not installed, not active, or its bootstrap did not run.
	} elseif ( ! isset( $wp_filter['admin_notices'] ) || ! $wp_filter['admin_notices'] instanceof \WP_Hook ) {
		// Nothing on the hook, or $wp_filter is not the WP_Hook shape used since 4.7.
		$this->log( 'wpb-product-slider', 'admin_notices is not a WP_Hook; no action taken.' );
	} else {
		// Every priority is scanned rather than just the vendor's current 10, so
		// the rule survives the vendor changing it.
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
```
