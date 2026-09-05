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

**No rule is added.** The one legitimate target cannot be reached by any mechanism
this project permits — see "Why no rule" below. That is a blocked result, not a clean
one, and it is recorded here so the question is not re-opened from scratch.

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
| Five-star review request | `admin_notices` → `WPB_WPS_Review_Notice::maybe_show_notice`, priority 10 | **suppress — but blocked** | "Enjoying…? a quick 5-star review would mean the world to us." Review begging, no site state. Instance unreachable; see below |
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

## Why no rule — the blocker

The review notice is the one clear-cut suppression target in this plugin, and it
cannot be removed by any mechanism `CLAUDE.md` permits.

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

### Options that exist, and what each would cost

Both require relaxing a rule that is currently absolute, so neither was taken
unilaterally. Recorded here so the trade-off does not have to be re-derived.

1. **Short-circuit `get_user_metadata` for `wpb_wps_review_dismissed`.** A core filter
   at file scope, making the plugin believe the notice was already dismissed. It reads
   as mechanism 1 but is not: it works by lying to the vendor about stored state rather
   than by unhooking, it fires on *every* user meta read on every request, and the
   short-circuit return value has to be shaped correctly for `$single`. Worst of all it
   generalises — the same trick suppresses any notice gated on a dismissal flag,
   including operational ones, which is precisely the general-purpose lever this
   project has refused to build
2. **A class-scoped `$wp_filter` lookup.** Read
   `$wp_filter['admin_notices']->callbacks[10]`, match only callbacks whose object is
   `instanceof WPB_WPS_Review_Notice`, and `remove_action()` that one. This is
   narrower than the banned pattern — `CLAUDE.md` prohibits walking `$wp_filter`
   "removing whatever looks promotional", i.e. heuristic removal, whereas this names
   the class and the method. But `$wp_filter` internals are not a public API, their
   shape changed in WP 4.7, and once the helper exists it will be reached for again

The honest position for 2.4 is that this vendor gives us nothing to hold on to, and a
review nag that appears once a week is not worth either concession. If WPBean ships a
version that registers the discount notice again, or moves the review notice to a
named function, revisit.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: `WPB_WPS_Review_Notice::__construct`, reached from
  `wpb_wps_free_plugin_init()` on `plugins_loaded` priority 10
- instance reachable via: **nothing.** `new WPB_WPS_Review_Notice();` at `main.php:157`
  discards the instance

## Drift check

Re-check when a new version appears in the vault:

- `main.php:146` — if `//add_action( 'admin_notices', 'wpb_wps_pro_discount_admin_notice' );`
  is uncommented, that notice becomes a clean mechanism 2 target: a plain named
  function on `admin_notices` at default priority
- `main.php:157` — if `new WPB_WPS_Review_Notice()` is ever assigned to a global, a
  static property or a singleton, the review notice becomes reachable and a mechanism 2
  rule can be written
- `inc/class-wpb-wps-review-notice.php` — if a filter or constant is added around
  `maybe_show_notice()`, that is a mechanism 1 rule
- `main.php:61` — `wpb_wps_install_free_admin_notice` must stay untouched. If a future
  version reuses that function name for an actual cross-sell, re-read it before
  assuming the classification still holds

## Additions to `headwall-nag-cleanup.php`: NONE
