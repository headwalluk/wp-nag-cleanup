# Simple Custom Post Order

- slug: `simple-custom-post-order`
- version analysed: `2.8.8`
- source: `/vault/backups/wordpress/plugins/simple-custom-post-order/simple-custom-post-order,2.8.8.zip`
- licensing: free
- Freemius bundled: no
- vendor: Colorlib. The review class is the Epsilon/WPChill `*_Review` pattern also shipped
  by Check & Log Email (`check-email.md`): same `five_star_wp_rate_notice` / `ajax_script`
  method names, same `epsilon-*` button IDs

## Analysis

Analysed on 18 Sep 2026 by Claude Code (Claude Opus 5).

Reported by Paul from a live site: a "please rate us" notice in the admin notice area,
`#simple-custom-post-order-epsilon-review-notice`, with *Rate the plugin*, *Remind me later*
and *Don't show again* buttons. It appears one day after the plugin is first seen by an
admin, on every admin screen, for `manage_options` users.

The only other notice is the "select which post types to order" setup prompt, which stays.
There are no dashboard widgets, no outbound calls and no SDK. One rule, mechanism 2 through
the sanctioned `$wp_filter` reader.

**The notice was dead from 2.5.10 to 2.8.6, and 2.8.7 brought it back.** 2.5.10 moved
`load_dependencies()`, which includes `class-simple-review.php`, onto `init` at priority 10.
The `Simple_Review` constructor then added *another* `init`/10 callback. That callback was
added while priority 10 was already running, so it never ran. 2.8.7 (26 Aug 2026) runs
`init()` straight away when `did_action( 'init' )`, and the vendor changelog calls this a
fix. That is why the nag is new on sites that have had this plugin for years.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 2: `SCPO_Engine::scporder_notice_not_checked` (setup prompt), `Simple_Review::five_star_wp_rate_notice` (review request) |
| Multiline `add_action(` form | None |
| `in_admin_header` / `admin_print_footer_scripts` | `Simple_Review::ajax_script`, the review notice's dismiss script |
| Vendor opt-out filters | None. The only `apply_filters` in the plugin are `scpo_post_types_args` and ordering filters |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls | None. The only URL is the wordpress.org reviews link, opened by the user's click |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Review request | `admin_notices` → `Simple_Review::five_star_wp_rate_notice` | **suppress** | *"Stoked to see you're using Simple Custom Post Order for a few days now … please consider rating it. It would mean the world to us."* Tells the owner nothing about the site |
| Review dismiss script | `admin_print_footer_scripts` → `Simple_Review::ajax_script` | **suppress** | Only binds the notice's buttons. It returns early when the notice is absent, so it is dead weight once the notice is gone |
| "Select which post types to order" setup notice | `admin_notices` → `SCPO_Engine::scporder_notice_not_checked` | **keep** | See below |

## Deliberately left alone

### `scporder_notice_not_checked` — the setup prompt

*"Thank you for installing our awesome plugin, in order to enable it you need to go to the
settings page and select which custom post or taxonomy you want to order."* with a
**Get started !** button. It has the large branded styling of a promo, but it is
operational: until a post type is selected the plugin does nothing, and the notice goes
away as soon as one is set. It links to the plugin's own settings page, not to a sale.
Kept: the branded styling does not decide it, the text does.

### `pre_option_simple-rate-time` — not used, and a trap

The gate is `time() > get_option( 'simple-rate-time' )`. `__return_true` on core's
`pre_option_` filter **shows** the nag: `true` compares as `1`. Doing it properly would
need a new method that returns a future timestamp. That is more code than the unhook, and
it changes what the site reports as the vendor's stored value. Same finding as Check & Log
Email's `check-email-rate-time`.

### `SCPO_Engine::load_dependencies` — not unhooked

Removing the vendor's `init` callback on `$scporder` (a global) would stop
`class-simple-review.php` from loading at all, and remove no other behaviour today. **Not
used**: the method is named for general dependency loading, so a future release that loads
anything else there would lose it without any error. The rule targets the review class by
name instead.

### `wp_ajax_epsilon_simple_review`

The dismissal handler. Left hooked: it only runs when the notice's own buttons post to it,
which cannot happen once the notice is gone.

## Mechanism

- tier: 2 (targeted unhook, via the sanctioned `$wp_filter` reader), two calls
- phase: `admin_init` at `self::LATE_PRIORITY`, in `unhook_vendor_notices()`
- vendor registers at: the main file runs `$scporder = new SCPO_Engine();` at load. Its
  constructor hooks `init` → `load_dependencies()`, which runs
  `include_once SCPORDER_DIR . 'class-simple-review.php'`. That file ends
  `new Simple_Review();`. `did_action( 'init' )` is already true there, so the constructor
  calls `init()` directly. For admins (`manage_options`), once the gate has passed, that
  adds `admin_notices` and `admin_print_footer_scripts` at priority 10. All of this runs
  during `init`, before `admin_init`
- instance reachable via: **nothing.** `new Simple_Review();` throws the object away.
  `SCPO_Engine` does not hold it, and the class has no singleton accessor. Only its own
  hook callbacks keep it alive

The class is in the global namespace (`Simple_Review`). The name is generic, so another
plugin could declare a class with the same name. The matcher needs both the class and the
method `five_star_wp_rate_notice` / `ajax_script`, and those method names only appear in
Epsilon-family review classes.

## Drift check

```bash
for PLUGIN_ZIP in $(ls -1 /vault/backups/wordpress/plugins/simple-custom-post-order/*.zip | sort -V); do
  echo "$(basename "${PLUGIN_ZIP}") review=$(unzip -p "${PLUGIN_ZIP}" simple-custom-post-order/class-simple-review.php | command grep -c "^class Simple_Review {\|'five_star_wp_rate_notice' \]\|'ajax_script' \]\|^new Simple_Review();") did_action=$(unzip -p "${PLUGIN_ZIP}" simple-custom-post-order/class-simple-review.php | command grep -c "did_action( 'init' )")"
done   # 2.8.8: review=4 did_action=1. 2.6.0–2.8.6: review=4 did_action=0, notice never
       # registers. 2.5.x: review=2 (array() syntax, so the `[ ... ]` patterns miss)
```

- `class-simple-review.php`: if the class gets a namespace, is renamed, or is kept by
  `SCPO_Engine`, the rule has to change
- If the `did_action` fix is ever reverted, the rule finds nothing and logs
  `Simple_Review::five_star_wp_rate_notice not registered on admin_notices`. That is correct
  behaviour, not a failure
- Versions in the vault: 2.5.7 → 2.8.8 (no 2.8.5 or 2.8.7). 2.5.7–2.5.9 include the file
  at construction time and register the notice. Their old `enqueue` method (jQuery only) is
  not targeted: it loads nothing promotional

## Verification

Bench: `bench2.local`, WP 7.1, Simple Custom Post Order **2.8.8** unzipped from the vault,
18 Sep 2026. Before = 1.32.0 (HEAD), after = working copy (1.33.0), both loaded through
`headwall-hosting.php` with `HEADWALL_NAG_CLEANUP_DEBUG` on. `bench2`'s `wp-config.php` is
not writable, so the constant came from a temporary `mu-plugins/00-nag-debug-temp.php`,
which loads before `headwall-hosting.php`. Three warm-up requests after each deploy.

Time gate: the first admin request writes `simple-rate-time` as now + 1 day. It was
backdated one hour into the past with `wp option update` (then `wp cache flush`) before the
"before" capture.

### Review request — **Confirmed** (bench)

| Check (dashboard and plugins.php) | Before | After |
|---|---|---|
| Screen asserted: `id="dashboard-widgets"` / `id="the-list"` | 1 / 1 | 1 / 1 |
| `simple-custom-post-order-epsilon-review-notice` | **2** | **0** |
| `epsilon_simple_review` (dismiss script) | **1** | **0** |
| Debug log | — | `simple-custom-post-order: Removed Simple_Review::five_star_wp_rate_notice from admin_notices priority 10.` and `… ::ajax_script from admin_print_footer_scripts priority 10.` |

(Two matches before: the notice `id` and the ID string in the dismiss script.)

### Negative checks

| Check | Result |
|---|---|
| `id="scpo-notice"` setup prompt, no post types selected | 1 before, **1 after**, on both screens |
| `simple-rate-time` after the rule | unchanged (still the backdated value). Nothing written |
| PHP fatals / warnings in `error.log` | **0** |

Bench left as found: plugin uninstalled through its own uninstaller, which drops
`wp_terms.term_order` and its options. Test user removed, temp mu-plugin removed, bench
nag-cleanup file restored. The table, option, cron, user and `wp_terms` column snapshots
all match.

### Live — **Confirmed**, 18 Sep 2026

On the live site where Paul first saw it. The site's web designer had already dismissed the
notice, so Paul brought it back with
`wp option update simple-rate-time $(( $(date +%s) - 3600 ))`. He saw it render under the
old mu-plugin, then deployed 1.33.0 and it was gone.

## Additions to `headwall-nag-cleanup.php`: 1 rule method, mechanism 2

```php
// In unhook_vendor_notices():
$this->unhook_simple_custom_post_order_review_notice();

public function unhook_simple_custom_post_order_review_notice() : void {
	$this->remove_discarded_instance_callback( 'admin_notices', 'Simple_Review', 'five_star_wp_rate_notice', 'simple-custom-post-order' );
	$this->remove_discarded_instance_callback( 'admin_print_footer_scripts', 'Simple_Review', 'ajax_script', 'simple-custom-post-order' );
}
```
