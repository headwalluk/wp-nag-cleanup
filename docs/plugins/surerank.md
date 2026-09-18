# SureRank

- slug: `surerank`
- version analysed: `1.10.1` (the review filter and the upsell checked across every vault
  release, `1.7.3` to `1.10.1`)
- source: `/vault/backups/wordpress/plugins/surerank/surerank,1.10.1.zip`
- licensing: freemium (SureRank Pro is a separate plugin)
- Freemius bundled: no. Bundles Brainstorm Force's `bsf-analytics` **1.1.26**,
  `astra-notices` and `nps-survey` **1.0.17**
- vendor: **Brainstorm Force**. Treat as high-churn; see `brainstorm-force.md`

## Analysis

Analysed on 18 Sep 2026 by Claude Code (Claude Opus 5).

Paul reported it from a live client site: *"Changed a permalink? SureRank Pro automatically
redirects old URLs to keep your SEO intact."*, with **Upgrade Now** and **Learn more**
buttons, on the dashboard, the plugins screen and the post and page lists.

Two rules: that upsell (mechanism 2, through the vendor's own singleton), and the 5-star
review request (mechanism 1, the vendor's own filter). The usage-tracking opt-in was already
covered by the 1.25.0 `bsf-analytics` rules. Everything else SureRank prints is operational
or on its own screens.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 5: `Admin_Notice::render_notice` (the upsell), `Review_Notice::display_notice`, `BSF_Admin_Notices::show_notices` (the library renderer), `Admin\Bulk_Actions::admin_notices` and `Content_Generation\Bulk_Actions::show_batch_processing_notice` (bulk-action results) |
| Multiline `add_action(` form | Only a `shutdown` re-hook in `loader.php`, not a notice |
| `in_admin_header` / `admin_print_footer_scripts` | None. `Nps_Notice` prints on `admin_footer` @ 999 |
| Vendor opt-out filters | **`surerank_show_rating_notice`**, used. `bsf_usage_tracking_enabled`, already used. No filter for the permalink upsell |
| Vendor opt-out constants | None |
| Dashboard widgets | 1: `surerank_search_console_widget`. **Kept** |
| Outbound calls from widgets | None from PHP. The widget's React app calls SureRank's own REST routes, which read Google Search Console for this site |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Permalink-redirect upsell (`#surerank-admin-notice`) | `admin_notices` → `SureRank\Inc\Admin\Admin_Notice::render_notice`, plus its `admin_enqueue_scripts` → `::admin_enqueue_scripts` | **suppress** | Generic "upgrade to Pro" pitch. See the boundary note below |
| 5-star review request (`#surerank-rating-notice`) | `admin_notices` → `Review_Notice::display_notice`, through `BSF_Admin_Notices` | **suppress** | Review request once three posts or terms have been optimised |
| Usage-tracking opt-in | `admin_init` → `BSF_Analytics::option_notice` | already suppressed | Existing 1.25.0 rule. `brainstorm-force.md` |
| NPS survey | `admin_footer` → `Nps_Notice::show_nps_notice` | out of scope | SureRank's own top-level screen only. See the coupling note below |
| Bulk-action and batch-processing notices | `admin_notices` → both `Bulk_Actions` classes | keep | Results of an action the admin just ran, including errors |
| "SureRank Website Insights" widget | `surerank_search_console_widget` | keep | The site's own Search Console data. Registered only when `enable_google_console` is on |
| "What's New" feed | `useWhatsNewRSS.js`, `surerank.com/whats-new/feed/` | out of scope | Browser-fetched, in SureRank's own admin layout. See `browser-rendered-nags` |

## Deliberately left alone

### The boundary call on the permalink upsell

The notice is set off by a real event: `Admin_Notice` watches `post_updated` and
`edited_term`. When a published URL changes, it writes
`surerank_nudges['permalink_redirect']`. So one could argue it says something true about
the site: a URL changed, and the old one may now 404.

It was classed as **suppress** because it names no URL and gives no count. Its only
actions are **Upgrade Now** (pricing page) and **Learn more**. The admin gets nothing they
did not already know, since they just made the change themselves, and nothing they can act
on without buying Pro. The redirect problem is real, but the notice is not how a site
owner finds out about it. A redirect plugin (Redirection, Yoast Premium, Rank Math) does
that job and is unaffected. By contrast, SureForms' "Finish setting up *this form*"
notice names the form and links straight to the fix. It is kept; see `sureforms.md`.

### The post and term hooks that arm the nudge

`pre_post_update`, `post_updated`, `edit_term` and `edited_term` on the same `Admin_Notice`
instance are **left hooked**. They only read permalinks and write the `surerank_nudges`
option, which nothing renders once `render_notice` is gone. Removing them would save a
`get_permalink()` per save and nothing else, and would reach further into the vendor
than the rule needs.

### The NPS survey, and why suppressing the review notice releases it

`Nps_Notice::show_nps_notice` returns early while
`Review_Notice::is_notice_eligible_for_current_user()` is true. That check calls
`surerank_show_rating_notice`, so with the filter at `false` the NPS survey *can* show where
it would have waited. It is limited to `toplevel_page_surerank`, SureRank's own dashboard,
by `nps_survey_enabled_for_admin_only` (default `true`), so this moves nothing onto a
screen we cover. The vendor itself hits the same path once a user has dismissed the review
notice. Its own gates still apply: 5 days after install, and 2 weeks after a dismissal.

**Not used: `nps_survey_show_notice`.** It is library-wide: every BSF product bundling
`nps-survey` reads it, with the plugin slug as its second argument. It would also only
reach a vendor-screen surface. Same decision as CartFlows' NPS in `cartflows.md`.

## Mechanism

### Permalink upsell — tier 2, via the vendor's singleton

- phase: `admin_init` at `self::LATE_PRIORITY`, in `unhook_vendor_notices()`
- vendor registers at: `Loader::__construct()` adds `plugins_loaded` → `load_routes` @ 10,
  which calls `Admin_Notice::get_instance()` **unconditionally, on every request**. The
  private constructor adds `admin_notices` → `render_notice` and (through
  `Enqueue::enqueue_scripts_admin()`) `admin_enqueue_scripts` → `admin_enqueue_scripts`,
  but only when `! Utils::is_pro_active() && get_option( 'permalink_structure' )`
- instance reachable via: `SureRank\Inc\Admin\Admin_Notice::get_instance()`, from the
  `Get_Instance` trait. It constructs on first call and caches in `private static $instance`

**Why calling `get_instance()` is safe here** (the ShapedPlugin question): by `admin_init`
the loader has already called it on `plugins_loaded`, so the call returns the existing
object and adds nothing. `class_exists()` goes first. SureRank's autoloader is only
registered when SureRank is active, so on a site without it the check is false and nothing
is constructed.

`render_notice` only prints an empty `<div id="surerank-admin-notice" class="notice">`.
The notice text lives in the React bundle `build/admin-notice/`. Removing the enqueue too
stops the bundle and its vendor chunk loading on every covered screen. The bundle renders
nothing else: `src/admin-notice/components/notice.js` is the permalink nudge only.

### Review request — tier 1

- `add_filter( 'surerank_show_rating_notice', '__return_false' )`, file scope
- read in `Review_Notice::compute_eligibility()`, **present from 1.7.4**. 1.7.3 has no
  `review-notice.php` at all

**Not used: `BSF_Admin_Notices`' `bsf_admin_notices_user_cap_check` /
`astra_notices_user_cap_check`.** They gate every notice the shared library renders,
including Astra's and Spectra's migration prompts. See `brainstorm-force.md`,
"`astra-notices` — a general framework carrying migration prompts".

## Drift check

- `inc/admin/admin-notice.php`: class `SureRank\Inc\Admin\Admin_Notice`, still
  `use Get_Instance`, still `add_action( 'admin_notices', [ $this, 'render_notice' ] )`
- `loader.php`: `load_routes()` still calls `Admin_Notice::get_instance()` on
  `plugins_loaded`. **If that call moves behind a condition, or later than `admin_init`,
  the rule's `get_instance()` would construct the object and add the hooks.** Re-check this
  line on every release
- `inc/admin/review-notice.php`: `surerank_show_rating_notice` still read in
  `compute_eligibility()`

```bash
PLUGIN_ZIP=/vault/backups/wordpress/plugins/surerank/surerank,<version>.zip
unzip -p "${PLUGIN_ZIP}" surerank/loader.php | command grep -n "Admin_Notice::get_instance\|'load_routes'"
unzip -p "${PLUGIN_ZIP}" surerank/inc/admin/admin-notice.php | command grep -n "add_action\|^class"
unzip -p "${PLUGIN_ZIP}" surerank/inc/admin/review-notice.php | command grep -n "surerank_show_rating_notice"
```

## Verification

Bench: `bench2.local`, SureRank **1.10.1** and SureForms 2.12.7 unzipped from the vault,
18 Sep 2026. Before = 1.31.0 (HEAD), after = working copy, `HEADWALL_NAG_CLEANUP_DEBUG` on,
three warm-up requests after the deploy.

Gates opened, each in the vendor's own data shape:

- upsell: `surerank_nudges` set to
  `{"permalink_redirect":{"count":0,"next_time_to_display":0,"display":true}}`. That is
  what `initialize_permalink_redirect_nudge()` writes when a published URL changes.
  bench2 has pretty permalinks
- review: `surerank_post_optimized_at` added to three published posts (threshold 3)

**Bench trap:** bench2's memcached `object-cache.php` does not see options written by
wp-cli. SureRank's `surerank_redirect_on_activation` stayed `yes` in the cache after the
web request had written `no`, so every admin request redirected to onboarding.
`wp cache flush` after every wp-cli write.

### Permalink upsell — **Confirmed** (bench)

| Check (dashboard and plugins.php) | Before | After |
|---|---|---|
| `id="surerank-admin-notice"` | **1** | **0** |
| `build/admin-notice/` (bundle) | **2** | **0** |
| Debug log | — | `surerank: Removed Admin_Notice::render_notice from admin_notices priority 10.` and `… ::admin_enqueue_scripts from admin_enqueue_scripts priority 10.` |

### Review request — **Confirmed** (bench)

| Check (dashboard, plugins.php, SureRank's own page) | Before | After |
|---|---|---|
| `id="surerank-rating-notice"` | **1** | **0** |

### Negative checks

| Check | Result |
|---|---|
| Screens asserted: `id="dashboard-widgets"`, `id="the-list"` | yes |
| `surerank_search_console_widget` still on the dashboard | yes |
| `surerank_nudges` after the run | unchanged, `display: true`, so no dismissal written |
| `surerank-rating-notice` transient / user meta | not set |
| PHP fatals / warnings / parse errors | **0** |

### Live — permalink upsell **Confirmed**, 18 Sep 2026

Paul deployed 1.32.0 to the live client site where the upsell was reported and confirmed it
gone the same day. The review request was not showing on that site before the deploy, so it
remains bench-confirmed only.

The NPS release was not seen on the bench: its 5-day `display_after` gate had not passed,
so `nps-survey-surerank` was 0 before and after. The coupling is **source-verified**.

## Additions to `headwall-nag-cleanup.php`: 2 rules, mechanisms 1 and 2

```php
// In register_vendor_optouts():
add_filter( 'surerank_show_rating_notice', '__return_false' );

// In unhook_vendor_notices():
$this->unhook_surerank_permalink_upsell();

public function unhook_surerank_permalink_upsell() : void {
	$notice_class = 'SureRank\\Inc\\Admin\\Admin_Notice';

	if ( ! class_exists( $notice_class ) ) {
		// Not installed.
	} else {
		$notice = $notice_class::get_instance();

		$upsell_callbacks = [
			'admin_notices'         => [ $notice, 'render_notice' ],
			'admin_enqueue_scripts' => [ $notice, 'admin_enqueue_scripts' ],
		];

		foreach ( $upsell_callbacks as $hook_name => $upsell_callback ) {
			$priority = has_action( $hook_name, $upsell_callback );

			if ( false === $priority ) {
				// Pro is active, or the site has no pretty permalinks.
			} else {
				remove_action( $hook_name, $upsell_callback, $priority );
				$this->log( 'surerank', sprintf( 'Removed Admin_Notice::%s from %s priority %d.', $upsell_callback[1], $hook_name, $priority ) );
			}
		}
	}
}
```
