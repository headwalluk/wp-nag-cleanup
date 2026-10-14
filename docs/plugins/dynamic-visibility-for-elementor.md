# Dynamic Visibility for Elementor

- slug: `dynamic-visibility-for-elementor`
- version analysed: `6.0.6` (vault holds 5.0.13 to 6.0.6, 11 releases)
- source: `/vault/backups/wordpress/plugins/dynamic-visibility-for-elementor/dynamic-visibility-for-elementor,6.0.6.zip`
- licensing: free. It upsells Dynamic.ooo's premium "Dynamic Content for Elementor", a separate plugin. DVE does not load at all when that plugin's `DCE_VERSION` is defined
- Freemius bundled: no

## Analysis

Analysed on 14 Oct 2026 by Claude Code (Claude Opus 5). Reported by Paul from a client site,
where the Novamira cross-sell was showing.

DVE has one notice queue, `DynamicVisibilityForElementor\AdminPages\Notices`, rendered on
`admin_notices`. Only two things are ever queued on it, and both are promotions. An anonymous
`init` closure in `Plugin::__construct()` calls `get_promo_notice_dashboard()` for any user
with `install_plugins`. That queues exactly one of:

- **`novamira_intro`**: "Your AI agent builds inside your WordPress." This is a Novamira
  cross-sell with a 10% promo code. Queued when Novamira is **not** installed
- **`upgrade_10`**: "Buy now Dynamic.ooo - Dynamic Content for Elementor and save 10%". Queued
  instead when Novamira **is** installed (`NOVAMIRA_VERSION` defined)

Each one shows on **every admin screen** until the user dismisses it. Dismissal is per user
and stored as user meta `dve_dismissed_<key>`. The renderer reads that meta through
`get_user_meta( $user, 'dve_dismissed_' . $dismiss_key, true )` and skips the notice when the
value is truthy.

The rule answers that read from core's `get_user_metadata` short-circuit, for the two named
keys only. This is the same pattern as Premium Addons' `pre_transient_` and UpdraftPlus's
`pre_option_` rules. Nothing is written.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 3 single-line hits: `Notices::action_admin_notices` (the queue renderer), `dve_fail_load` and `dve_admin_notice_minimum_elementor_version`. Multiline pass: none. No `in_admin_header`, `admin_print_footer_scripts`, `admin_footer` or `wp-pointer` hits |
| Vendor opt-out filters | **None**. No `apply_filters()` in the plugin's own code matches the pattern |
| Vendor opt-out constants | **None** |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets, and no `wp_remote_*` / `fetch_feed` anywhere outside `vendor/` |
| Freemius | Not bundled |

## Findings

| Item | Hook / dismiss key | Verdict | Reason |
|---|---|---|---|
| Novamira cross-sell, "Your AI agent builds inside your WordPress." | queue key `novamira_intro`, rendered by `Notices::action_admin_notices` on `admin_notices` 10 | **suppress** | Cross-sells another vendor product, with a promo code. Shown on every admin screen. Says nothing about this site |
| "Buy now Dynamic.ooo - Dynamic Content for Elementor and save 10%" | queue key `upgrade_10`, same renderer | **suppress** | Generic upgrade-to-premium banner with a promo code |
| "Elementor is required for Dynamic Visibility to work" | `admin_notices` → `dve_fail_load` | keep | Missing dependency. Never suppressed |
| "requires Elementor version X or greater" | `admin_notices` → `dve_admin_notice_minimum_elementor_version` | keep | Version requirement. Never suppressed |

## Deliberately left alone

- **`dve_fail_load` and `dve_admin_notice_minimum_elementor_version`**: dependency and
  version notices. They call `Notices::render_notice()` statically and never touch the queue
  or the dismissal meta, so the rule cannot reach them
- **The "Go Premium" plugin action link** (`add_action_links`) and the `plugin_row_meta`
  links: these are the vendor's own row on the Plugins screen. Out of scope
- **The `black_friday_early_2025` and `black_friday_2025` keys** (6.0.0 to 6.0.4): these are
  not in the constant. `is_black_friday_period()` hard-codes 17 Nov to 1 Dec 2025, so they
  can no longer render, and 6.0.5 deleted them. If a later release brings back a seasonal
  key, add it by name. Never match `dve_dismissed_%` by pattern
- **Removing `action_admin_notices`**: not used. `Plugin::instance()->notices` is a public
  property, so this is reachable. But it renders the whole queue, so any operational notice a
  later version queues there would go silently. Also, `Plugin` is in the Composer classmap,
  so `instance()` would construct the plugin if called before the vendor's own
  `Plugin::instance()` at the bottom of `core/plugin.php`
- **The `init` closure**: it cannot be unhooked, and it also loads the textdomain and adds
  the plugin-row links

## Mechanism

- tier: 1 in shape. It is a core short-circuit filter answering the vendor's own dismissal
  gate (the "closures need the vendor's own gate" pattern). It is not a vendor-provided
  switch, so the user meta key is what drifts
- phase: registered in `register_vendor_optouts()`, so only on an admin page request.
  `get_user_metadata` at 10, accepted args 3
- vendor registers at: `Plugin::__construct()` → anonymous closure on `init` →
  `get_promo_notice_dashboard()` → `Notices::info( $msg, '<key>' )`. Rendered on
  `admin_notices` 10 by `Notices::action_admin_notices()`, which reads
  `dve_dismissed_<key>` per notice
- instance reachable via: N/A, nothing is unhooked

The callback returns `[ true ]`, not `true`. `get_metadata_raw()` returns `$check[0]` for a
single read when the filter returns an array, and returns the value as-is for a non-single
read. So an array is correct for both. For every other key it returns the incoming value,
which keeps any earlier filter's answer.

## Drift check

In `core/plugin.php`, grep for `notices->(info|warning|error|success)\( *\$msg, *'`. The
second argument is the dismiss key. New promo keys need adding to
`DYNAMIC_VISIBILITY_PROMO_DISMISS_KEYS`. Also confirm that
`includes/admin-pages/notices.php` still has `DB_PREFIX = 'dve_dismissed_'`, and that
`action_admin_notices()` still gates on `get_user_meta( ..., true )`. If the storage moves to
an option or a transient, the rule silently stops working.

Keys by version: `upgrade_10` since 5.0.13 (5.0.14 to 5.0.16 call it from `promo_notice()`
under the same key). `novamira_intro` since 6.0.5.

## Verification

### Both promos: **Confirmed (bench1, wp-cli admin harness)**, 14 Oct 2026

This was **not** an authenticated HTTP capture. devx and bench1 both run quick-2fa, so a
minted auth cookie is redirected to `wp-login.php?q2fa=verify`. The check ran through wp-cli
instead, with `--exec='define( "WP_ADMIN", true );'`. That makes `is_admin()` true, so both
this plugin and DVE boot their admin paths. The harness then sets user 1, calls DVE's own
producer `Plugin::instance()->get_promo_notice_dashboard()` (the method the `init` closure
calls), and captures `Notices::action_admin_notices()`.

Bench: `bench1.local`, Elementor 4.3.4 and DVE 6.0.6 from the vault.

| Run | `dce-generic-notice` | `dve_dismiss=novamira_intro` | `dve_dismiss=upgrade_10` | filter registered |
|---|---|---|---|---|
| 1.39.0 | 1 | 1 | 0 | no |
| 1.39.0, `NOVAMIRA_VERSION` defined | 1 | 0 | 1 | no |
| 1.40.0 | **0** | 0 | 0 | yes |
| 1.40.0, `NOVAMIRA_VERSION` defined | **0** | 0 | 0 | yes |

- Nothing written: a direct `wp_usermeta` count for `dve_dismissed_%` was 0 before and after.
  (`metadata_exists()` cannot be used for this probe, because it goes through the same filter)
- Negative check: a warning with a different dismiss key and an error with no key, both
  pushed onto the same queue, rendered (2 of 2). `get_user_meta( 1, 'nickname', true )` and
  `rich_editing` read normally
- Without `WP_ADMIN`, `get_user_metadata` has no callback. The filter is not registered on
  non-admin requests
- No time gate: the promo is queued on every `init` for `install_plugins` users

Surface: the admin notice area on every admin screen. DVE has no dashboard widget, no
REST-rendered surface and no JavaScript-drawn promo.

**Still to do: a browser check** on the client site or a logged-in bench
session after deploying 1.40.0. The harness exercises real WordPress and real vendor code,
but it is not an HTTP request.

## Additions to `headwall-nag-cleanup.php`: one core short-circuit filter

```php
const DYNAMIC_VISIBILITY_PROMO_DISMISS_KEYS = [
	'dve_dismissed_novamira_intro', // 6.0.5 onwards; current at 6.0.6.
	'dve_dismissed_upgrade_10',     // 5.0.13 onwards; current at 6.0.6.
];

add_filter( 'get_user_metadata', [ $this, 'answer_dynamic_visibility_promo_dismissed' ], 10, 3 );

public function answer_dynamic_visibility_promo_dismissed( $meta_value, $user_id, $meta_key ) {
	if ( null === $meta_value && in_array( $meta_key, self::DYNAMIC_VISIBILITY_PROMO_DISMISS_KEYS, true ) ) {
		$meta_value = [ true ];
		$this->log( 'dynamic-visibility-for-elementor', 'Answered ' . $meta_key . ' as dismissed.' );
	}

	return $meta_value;
}
```
