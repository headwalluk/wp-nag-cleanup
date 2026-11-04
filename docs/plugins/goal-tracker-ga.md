# Goal Tracker (pinewise)

- slug: `goal-tracker-ga`
- version analysed: `1.1.6` (the only release in the vault)
- source: `/vault/backups/wordpress/plugins/goal-tracker-ga/goal-tracker-ga,1.1.6.zip`
- licensing: freemium (premium "Personal" plan sold through Freemius, 7-day trial with no payment required)
- Freemius bundled: **yes**, SDK `2.11.0`

## Analysis

Analysed on 4 Nov 2026 by Claude Code (Claude Opus 5).

Paul reported it from a client site: *"Hey! How do you like **Goal Tracker** so far? Test
all our awesome premium features with a 7-day free trial. No credit card required!"*, with
a **Start free trial ➜** button. The HTML carried
`class="fs-notice updated promotion fs-sticky … fs-slug-goal-tracker-ga"`,
`data-id="trial_promotion"` and `data-manager-id="goal-tracker-ga"`.

That is the Freemius trial promotion already handled for Featured Images in RSS (1.22.1),
Role Based Pricing for WooCommerce and Menu Image (1.44.0). **Read
[`featured-images-for-rss-feeds.md`](featured-images-for-rss-feeds.md) first.** It holds
the full SDK analysis and why the rule has two parts. This document records only what
differs for this module.

The plugin's own code adds one more nag: a review request on every admin screen. That one
is new to this rule set.

### SDK 2.11.0 checks

This is the oldest SDK on the trial rule so far (the others are 2.12.1 to 2.13.4), so the
parts the rule depends on were re-read in this copy:

- `class-freemius.php:1580-1581` registers `_add_trial_notice` and
  `_add_affiliate_program_notice` on `admin_init` at the default priority
- `_add_trial_notice()` (line 23867) adds the `fs-trial` menu counter when
  `is_in_trial_promotion()` is true, *before* it consults `show_trial`. This is the same
  ordering that makes the unhook necessary
- `class-fs-admin-notice-manager.php:276` applies `show_admin_notice` through
  `fs_apply_filter()` and skips on `true !== $show_notice`
- `fs_apply_filter()` builds the tag `fs_{$tag}_{$module_unique_affix}`, and
  `get_module_unique_affix()` returns the bare slug for a plugin. So the filter is
  `fs_show_admin_notice_goal-tracker-ga`
- `Freemius::get_instance_by_id()` exists (line 2918)

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | Plugin: one, `Wp_Goal_Tracker_Ga_Admin::gtga_show_review_notice`, through the plugin's loader in `Wp_Goal_Tracker_Ga::define_admin_hooks()`. SDK: `FS_Admin_Notice_Manager::_admin_notices_hook` |
| Multiline `add_action(` form | No hits in the plugin's own code |
| `in_admin_header` / `admin_print_footer_scripts` | SDK only |
| Vendor opt-out filters | None in the plugin's own code. SDK: `fs_show_admin_notice_{slug}`, `fs_show_trial_{slug}`, `fs_show_affiliate_program_notice_{slug}` |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls from widgets | No widgets. No `wp_remote_*` in the plugin's own code. Freemius SDK API calls only |
| Freemius | Yes, SDK 2.11.0. Config (`gtg_fs()` in `goal-tracker-ga.php`): `id 11325`, `slug goal-tracker-ga`, `has_paid_plans => true`, `trial => 7 days, is_require_payment => false`, `is_premium => false`, `menu slug wp-goal-tracker-ga`. No `has_affiliation` key |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Freemius trial promotion | `admin_init` 10 → `Freemius::_add_trial_notice`, sticky id `trial_promotion`, type `promotion` | **suppress** (unhook + render filter) | A trial upsell that says nothing about the site. This is the reported notice |
| Freemius affiliate programme | `admin_init` 10 → `Freemius::_add_affiliate_program_notice`, sticky id `affiliate_program` | **suppress** (same rule) | No `has_affiliation` in the config, so the producer is expected to be a no-op here. Covered anyway by the shared unhook and ID list |
| Freemius opt-in / connect | sticky id `connect_account` | **suppress** (render filter, shared ID list) | Usage-tracking opt-in. See `delete-all-comments-of-website.md` |
| Review request | `admin_notices` → `Wp_Goal_Tracker_Ga_Admin::gtga_show_review_notice` | **suppress** (`pre_option_gtga_review_notice_dismissed`) | *"We'd be super grateful if you could help us spread the word … Give it a ★★★★★ Rating"*. Review begging, on every admin screen, 7 days after activation |

## Deliberately left alone

### Every other Freemius notice

Licence, trial-state (`trial_started`, trial expiry), activation and ownership stickies
render on the vendor's own terms. `hide_freemius_promo_notice()` passes the incoming value
straight back for any ID not in `FREEMIUS_PROMO_NOTICE_IDS`.

### `fs_show_trial_{slug}`: not used

Same reason as the other Freemius modules. `_add_trial_notice()` adds the menu counter
before it consults the filter, so using the filter alone would leave a badge the site
owner could never clear.

### Unhooking the review notice: not used

`run_wp_goal_tracker_ga()` keeps `$plugin` as a local, and `define_admin_hooks()` keeps
`$plugin_admin` as a local, so nothing holds the `Wp_Goal_Tracker_Ga_Admin` instance.
`remove_action()` therefore has nothing to name. The `$wp_filter` reader would work, but it
is the last resort, and core's `pre_option_` short-circuit over the vendor's own dismissal
gate reaches the same result with no reader at all.

### The dismiss AJAX handler

`gtga_dismiss_review_notice` is left registered. With the notice suppressed its script is
never printed, so nothing calls the handler.

## Mechanism

- **Freemius:** mechanism 1 (render filter `fs_show_admin_notice_goal-tracker-ga`) plus mechanism 2 (unhook the two producers)
  - phase: the render filter registers at file scope. The unhook runs from `unhook_freemius_promos()` on `admin_init` @ 1, ahead of the SDK's default-priority registration
  - instance reachable via: `\Freemius::get_instance_by_id( 11325 )`, through `get_freemius_module()`. Do not call `gtg_fs()` or `Freemius::instance()`, because either would construct the module
- **Review request:** core filter `pre_option_gtga_review_notice_dismissed` → `__return_true`, at file scope
  - gate: `if ( ! get_option( 'gtga_review_notice_dismissed' ) )` wraps the whole notice, so `true` skips it. Unlike the `time() > $value` gates rejected for Check & Log Email and Simple Custom Post Order, this is a plain truthiness test
  - the option is read only here (`admin/class-wp-goal-tracker-ga-admin.php:555`) and written only by the dismiss handler (line 589). It appears on no settings screen, so a forced answer cannot be written back through a form
  - nothing is written. Removing this plugin restores the vendor's own state exactly

## Drift check

- `goal-tracker-ga.php`, `gtg_fs()`: confirm `'id' => '11325'` and `'slug' => 'goal-tracker-ga'`. If the ID changes, `get_freemius_module()` returns null and the unhook silently stops. The render filter keeps working on the slug alone
- `admin/class-wp-goal-tracker-ga-admin.php`, `gtga_show_review_notice()`: confirm the gate is still `get_option( 'gtga_review_notice_dismissed' )`, still a truthiness test, and still the only gate in front of the notice markup
- If a later SDK is bundled, re-run the SDK checks above

## Verification

- **Render filter (`trial_promotion`)**: **confirmed** on the client site where Paul saw it, 4 Nov 2026 — rendering before 1.49.0 was deployed, absent after. Probe: `data-id="trial_promotion"` with `data-manager-id="goal-tracker-ga"`
- **Producer unhook**: **source-verified only.** Probe: the `fs-trial` counter bubble is gone from the Goal Tracker admin menu item
- **Review request**: **source-verified only.** It needs `gtga_activation_timestamp` more than 7 days old (backdate it on a bench), and `gtga_review_notice_dismissed` unset. Probe: `id="gtga-review-notice"`

## Additions to `headwall-nag-cleanup.php` (1.49.0)

```php
const GOAL_TRACKER_FREEMIUS_MODULE_ID = 11325;
const GOAL_TRACKER_FREEMIUS_SLUG      = 'goal-tracker-ga';

// register_vendor_optouts()
add_filter(
	'fs_show_admin_notice_' . self::GOAL_TRACKER_FREEMIUS_SLUG,
	[ $this, 'hide_freemius_promo_notice' ],
	self::LATE_PRIORITY,
	2
);
add_filter( 'pre_option_gtga_review_notice_dismissed', '__return_true' );

// unhook_freemius_promos()
$this->unhook_freemius_module_promos( self::GOAL_TRACKER_FREEMIUS_MODULE_ID );
```
