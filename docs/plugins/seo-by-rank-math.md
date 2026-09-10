# Rank Math SEO

- slug: `seo-by-rank-math`
- version analysed: `1.0.278`, cross-checked against `seo-by-rank-math-pro` `3.0.95`
- source: `/vault/backups/wordpress/plugins/seo-by-rank-math/seo-by-rank-math,1.0.278.zip`
- licensing: freemium (free on wordpress.org, Rank Math PRO sold at rankmath.com)
- Freemius bundled: no

## Analysis

Analysed on 10 Sep 2026 by Claude Code (Claude Opus 5), alongside MonsterInsights, after
Paul hit nags from both on logging in to the same client site.

**Three rules added.** Rank Math produced two firsts for this project, and both changed
the plugin's architecture rather than just adding a method:

1. Its promotional notices are **stored**, not printed. They go into the
   `rank_math_notifications` option and are rendered later by a shared notification
   centre, so unhooking the producer governs only sites that have never been nagged. That
   forced **mechanism 4** — removing a banked notification by ID through the vendor's own
   removal API. It is the only mechanism in this project that writes to another vendor's
   data
2. Its dashboard widget body is rendered from a **REST route**, not from the dashboard
   request. Suppressing the vendor news feed inside it meant giving this plugin its first
   sanctioned reason to act on a REST request

Both were put to Paul as decisions before being written, because both set precedent.

The second one shipped broken in 1.24.0 and was caught on a live site the same day. The
rule itself was right; the branch that registered it was gated on `wp_is_json_request()`,
which returns false for this vendor's own fetch. Fixed in 1.24.1, written up under
**The Accept-header trap**, and the single most useful thing in this document.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **4**. `RankMath::activation_error`, `Analytics::render_notice`, and `Notification_Center::display` on `all_admin_notices` — the store that carries almost everything |
| Vendor opt-out filters | **`rank_math/admin/add_notification` — documented and broken, see below.** `wp_helpers_notifications_before_storage` works but filters at storage time. `rank_math/show_score`, `rank_math/admin/dashboard_nav_links`, `rank_math/analytics/get_widget` — none promotional |
| Vendor opt-out constants | None. `RANK_MATH_PRO_FILE` is a Pro-presence marker, not a switch |
| Dashboard widgets | **1**: `rank_math_dashboard_widget` — **mixed**, see below |
| Outbound calls from widgets | `https://rankmath.com/wp-json/wp/v2/posts?dashboard_widget_feed=1`, `Dashboard_Widget::get_feed()`, cached 12h in `rank_math_feed_posts_v2` |
| Freemius | Not bundled |

Rank Math wraps `add_action` in a `Hooker` trait, so `$this->action( 'admin_notices', … )`
is the common form and a plain `add_action` grep under-reports badly. Every pass here was
run against both spellings.

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| PRO upsell notice | stored id `rank_math_pro_notice` | **suppress** | "Rank Your Content With the Power of PRO & A.I." + feature list. Pure upsell, 10+ days after install |
| Review request | stored id `rank_math_review_plugin_notice` | **suppress** | Review begging |
| "Latest Blog Posts from Rank Math" | `rank_math/dashboard/widget` priority 98 → `Dashboard_Widget::dashboard_widget_feed` | **suppress** | Vendor news feed with UTM-tagged links, plus an outbound fetch on every render |
| Notification centre renderer | `all_admin_notices` → `Notification_Center::display` | keep — **mixed** | See below. Carries nearly everything operational |
| Rank Math Overview widget | `rank_math_dashboard_widget` | keep | 404s, redirections and analytics for **this** site |
| Widget footer links | `rank_math/dashboard/widget` priority 99 → `dashboard_widget_footer` | keep | Blog / Help / Go Pro link row. Chrome inside the vendor's own widget, and removing it takes Help with it |
| Analytics fetch progress | `admin_notices` → `Analytics::render_notice` | keep | Reports an in-progress data fetch on this site |
| Google reconnect required | stored id `reconnect` | keep | **The Google token is gone and analytics have stopped. Operational** |
| Activation error | `admin_notices` → `RankMath::activation_error` | keep | **Fatal / requirements notice. Never suppressed** |
| Plugin not set up | stored id `plugin_not_setup` | keep | Configuration state — the wizard has not been run |
| New post type detected | stored id `new_post_type` | keep | A post type exists with no Rank Math settings yet |
| WPML settings conversion | stored id `display_wpml_notice` | keep | **Data migration prompt** |
| Permalink change warning | `Notices::permalink_changes_warning` | keep | Warns that changing permalinks affects existing URLs |
| Redirection / 404 / DB-tool notices | stored, various | keep | Conflict warnings, import results, schema tool output |
| Plugin conflict watcher | `Admin\Watcher\Watcher` → stored | keep | **Plugin conflict notices. Never suppressed** |
| Content AI bulk action results | stored, various | keep | Reports what a bulk edit did |
| PRO package notices | 20+ `Helper::add_notification` calls | keep | **All operational** — CSV import results and errors, GTIN migration, watermark validation, redirection sync. Rank Math does not nag people who have paid |

## Deliberately left alone

### The notification centre is the store for almost everything operational

`Notification_Center` is registered as `rank_math()->notification` and puts one callback on
`all_admin_notices`. Everything Rank Math wants to say goes through
`Helper::add_notification()` into that one store: redirection conflicts, 404-monitor
output, the WPML **data migration** prompt, the plugin-conflict watcher, registration
failures, schema tool results, Content AI bulk results, and the Google **reconnect
required** notice that means analytics have silently stopped.

Removing `display` would take all of that with it. It is the AIOSEO `Notices::notices`
situation with a much longer list, so the renderer stays untouched and the two
promotional entries are named individually instead.

### `rank_math/admin/add_notification` is documented, and does not work

`Helper::add_notification()` in `includes/helpers/class-api.php`:

```php
$notification = compact( 'message', 'options' );

/**
 * Filter notification message & arguments before adding.
 * Pass a falsy value to stop the notification from getting added.
 */
apply_filters( 'rank_math/admin/add_notification', $notification );

if ( empty( $notification ) || ! is_array( $notification ) ) {
    return;
}
```

**The return value of `apply_filters()` is discarded.** `$notification` is never
reassigned, so the guard below always tests the unfiltered value and the documented
"pass a falsy value to stop the notification" contract cannot be honoured. A filter
registered against this hook runs, does nothing, and reports nothing.

This is worth remembering as a shape, not just a Rank Math bug: **a documented vendor
filter is not evidence that it is wired up.** Read the call site. Between this and
MonsterInsights' `hide_am_notices` — a working filter whose scope is wrong — both plugins
audited today had a mechanism 1 candidate that failed for a different reason.

It would not have solved this case anyway: stored notices are rebuilt by
`get_from_storage()` calling `new Notification( … )` directly, so they never pass through
`Helper::add_notification()` on subsequent requests. Even a working producer-side filter
governs only the request that first banks the notice.

### The widget footer stays

`dashboard_widget_footer` prints a Blog / Help / Go Pro link row at the foot of the
widget. The "Go Pro" link is promotional, but it is a link in a footer inside the vendor's
own widget rather than a nag competing for attention, and the same callback prints the
Help link. Removing the row to remove one link is not a trade worth making.

### The Overview widget itself stays

`rank_math_dashboard_widget` reports this site's 404s, redirections and search analytics —
and is only registered at all if one of those three modules is active and the user has the
capability for it. Same category as AIOSEO's Overview and Yoast's Posts Overview. Only the
vendor news block inside it is removed.

### Rank Math PRO has no nags

All 20+ notification calls in `seo-by-rank-math-pro` 3.0.95 are operational: CSV
import progress and failures, GTIN migration results, video schema generation, watermark
validation, redirection sync to `.htaccess`. No review nag, no upsell, and `Pro_Notice` is
explicitly skipped when `RANK_MATH_PRO_FILE` is defined. **No Pro-specific rule is needed**,
and the free-version rules are inert on a Pro site.

## Mechanism

Three rules across two new registration points.

### Rules 1 and 2 — mechanism 4, stored-notification removal

- tier: 4 (stored notification)
- phase: `all_admin_notices`, `self::EARLY_PRIORITY`
- vendor registers at: `Notification_Center::__construct`, from
  `rank_math()->container['notification']`, with `display` on `all_admin_notices` at the
  default priority. Running at priority 1 gets in ahead of it
- instance reachable via: **`rank_math()->notification`** — a container entry read through
  `RankMath::__get()`, which returns `null` rather than throwing for an unknown key. No
  reader needed

Why the producer side is not enough: `Pro_Notice::hooks()` calls `add_notice()` only when
`get_option( 'rank_math_pro_notice_added' ) === false`, and `add_notice()` sets that option.
So the notice is banked exactly once and then lives in `rank_math_notifications` until
dismissed. Unhooking `Pro_Notice` on a site that has already been nagged changes nothing —
the 1.22.1 lesson, and the reason mechanism 4 exists.

`remove_by_id()` is the vendor's own dismiss path:

```php
public function dismiss() {
    $this->displayed     = true;
    $this->options['id'] = '';
}
```

`is_persistent()` is `! empty( $this->args( 'id' ) )`, so blanking the id makes
`update_storage()` drop the entry on `shutdown`. **This is irreversible** — removing this
mu-plugin does not bring the notice back, because `rank_math_pro_notice_added` stays set.
That cost was put to Paul explicitly and accepted.

#### The two IDs gate each other, so they go together

`Pro_Notice::hooks()`:

```php
if ( get_option( 'rank_math_pro_notice_added' ) === false && ! Helper::has_notification( 'rank_math_review_plugin_notice' ) ) {
```

`Ask_Review::hooks()`:

```php
if ( get_option( 'rank_math_review_notice_added' ) === false && ! Helper::has_notification( 'rank_math_pro_notice' ) ) {
```

Each producer stands down while the other's notification is banked. Removing only
`rank_math_pro_notice` would therefore clear the way for `Ask_Review` to bank the review
nag on the next request. Both IDs are named in
`self::RANK_MATH_PROMO_NOTIFICATION_IDS` for that reason, and neither should be removed
from the list on its own.

After one admin request the notice is gone from storage, `*_notice_added` blocks
re-banking, and the rule becomes a silent no-op — `has_notification()` returns false and
nothing is logged.

### Rule 3 — mechanism 2, on a REST request

- tier: 2 (targeted unhook)
- phase: **`rest_api_init`**, `self::LATE_PRIORITY`, registered **unconditionally** — see
  the Accept-header trap below
- vendor registers at: `Dashboard_Widget::__construct`, called from
  `Common::__construct()`, which `RankMath::setup()` reaches on **every** request — not
  gated on `is_admin()`, which is why the callback is on the hook during a REST request
- instance reachable via: **not reachable.** `new Dashboard_Widget()` in
  `includes/class-common.php:55`, discarded — **reader use ten**

The widget's `render_dashboard_widget()` emits only
`<div id="rank-math-dashboard-widget" class="rank-math-loading"></div>`. The body arrives
later from a REST route:

```php
// includes/rest/class-admin.php
public function dashboard_widget_items() {
    ob_start();
    $this->do_action( 'dashboard/widget' );
    return ob_get_clean();
}
```

So `rank_math/dashboard/widget` never fires on the dashboard request itself. This plugin's
`is_admin_page_request()` bails on JSON requests by design, which made the rule unreachable
until `run()` was given a REST registration.

#### The Accept-header trap — this cost the rule in 1.24.0

The first implementation gated that registration on `wp_is_json_request()`:

```php
} elseif ( wp_is_json_request() ) {
    add_action( 'rest_api_init', [ $this, 'unhook_rest_rendered_promos' ], self::LATE_PRIORITY );
}
```

**It never fired.** `wp_is_json_request()` is a content-negotiation test — it inspects
`Accept` and `Content-Type` and nothing else. Rank Math fetches the widget with
`assets/admin/js/dashboard.js`:

```js
$.ajax({
    url: rankMath.api.root + "rankmath/v1/dashboardWidget",
    method: "GET",
    beforeSend: function (xhr) { xhr.setRequestHeader("X-WP-Nonce", rankMath.api.nonce); }
})
```

No `dataType: 'json'`, so jQuery sends `Accept: */*`, and a GET carries no `Content-Type`.
`wp_is_json_request()` returns **false** on a genuine REST request, the branch was skipped,
the rule never registered, and the blog feed stayed on the page.

Every source-level check passed. The hook name, the class, the namespace, the priority, the
construction site and the load order were all correct — and the rule was inert, on a live
client site, while the audit doc said it should work. It was caught only because Paul looked
at the actual dashboard widget after deploying, which the notice-area check would never have
covered.

**The fix (1.24.1): register on `rest_api_init` unconditionally.** That hook fires only on a
REST request, so it is its own gate and no request-shape test is needed:

```php
add_action( 'rest_api_init', [ $this, 'unhook_rest_rendered_promos' ], self::LATE_PRIORITY );
```

The cost is one `add_action()` on every request, including front-end and cron, where the
hook never fires and the callback never runs. `is_admin_page_request()` is untouched and
still gates all the notice and dashboard work.

Mechanisms 1 to 3 were each ruled out first:

- **Mechanism 1**: no filter gates the feed. `rank_math/admin/add_notification` is
  unrelated and broken; `rank_math/analytics/get_widget` filters analytics data
- **Singleton accessor**: none. `Dashboard_Widget` is not in the container
- **Mechanism 3**: `remove_meta_box( 'rank_math_dashboard_widget' )` works from a normal
  admin request but takes the site's own 404, redirection and analytics figures with it.
  Rejected on the AIOSEO precedent that data widgets are kept
- **Method visibility**: `dashboard_widget_feed` is public and is its own callback, so the
  operational half needs no re-hooking — the other three callbacks on that action
  (analytics at 10, 404 monitor at 11, redirections at 12) are untouched

**The class is `RankMath\Dashboard_Widget`, not `RankMath\Admin\Dashboard_Widget`.**
`includes/admin/class-dashboard-widget.php` declares `namespace RankMath;` despite its
directory. Getting that wrong makes `class_exists()` fail and the rule no-op silently.

## Drift check

Re-check when a new version appears in the vault:

- `includes/admin/notifications/class-notification-center.php` — `remove_by_id()`,
  `has_notification()` and the `all_admin_notices` priority. If `display` ever moves off
  the default priority, `EARLY_PRIORITY` may stop being early enough
- `includes/admin/class-pro-notice.php` and `includes/admin/class-ask-review.php` — the
  notification IDs `rank_math_pro_notice` and `rank_math_review_plugin_notice`, and the
  mutual `has_notification()` gates. A renamed ID silently no-ops the rule
- `includes/admin/class-dashboard-widget.php` — the **namespace**, the class name, the
  method `dashboard_widget_feed` and its priority 98
- `includes/rest/class-admin.php` — if `dashboard/widget` is ever fired from the dashboard
  request instead of the REST route, the `rest_api_init` registration in `run()` can be
  retired
- `assets/admin/js/dashboard.js` — how the widget is fetched. Not because the rule depends
  on it any more (1.24.1 removed that dependency), but because the `Accept: */*` this file
  sends is the evidence behind the `CLAUDE.md` note that `wp_is_json_request()` must not be
  used to detect a REST request. If the vendor switches to `wp.apiFetch`, that evidence
  changes and the note should say so rather than quietly become wrong
- `includes/helpers/class-api.php` — if the vendor ever assigns the result of
  `apply_filters( 'rank_math/admin/add_notification', … )`, that filter becomes a real
  mechanism 1 opt-out for **newly banked** notices, though mechanism 4 is still needed for
  sites already carrying them
- **New promotional IDs.** The store is the vendor's general-purpose channel, so a
  seasonal or cross-sell notice would arrive as a new ID rather than as new code on a
  hook. Re-read the `add_notification` call sites on each release; that is the drift this
  audit is least able to detect from the outside

## Verification

**Partly confirmed against a live client site on 10 Sep 2026, and that check found a
broken rule.** Paul deployed 1.24.0 to the client site the audit came from and reported
back per surface.

| Rule | Result |
|---|---|
| `rank_math_review_plugin_notice` (mechanism 4) | **Confirmed gone.** This was the nag Paul was actually seeing — the "Rate Us" review request, not the PRO upsell |
| `rank_math_pro_notice` (mechanism 4) | **Source-verified only.** The two IDs gate each other, so only the review nag was banked on this site. The rule ran and found nothing to remove, which is correct behaviour and not evidence |
| `Dashboard_Widget::dashboard_widget_feed` (mechanism 2) | **Failed.** The "Latest Blog Posts from Rank Math" heading and its `rankmath.com` links were still on the dashboard widget after deploying. Diagnosed, fixed in 1.24.1, and **not yet re-confirmed** |

### What the confirmation is worth

The review-nag result is the meaningful one: it is the **first live confirmation of
mechanism 4**, and it exercised the whole path — the entry was already banked in
`rank_math_notifications` on a long-running site, the rule ran at `all_admin_notices`
priority 1, `remove_by_id()` blanked the id, and `update_storage()` dropped it on
`shutdown`. That is precisely the case a producer-side unhook could not have reached, which
is the argument mechanism 4 was introduced on.

### What the failure is worth more

The blog-feed rule passed every source check — hook name, class, namespace, priority,
construction site, load order — and was inert. See **The Accept-header trap** above for the
diagnosis. Three things are worth carrying forward:

- **A rule that renders on a surface you did not look at is unverified, however carefully
  it was read.** The notice-area check was clean and told us nothing about the widget
- **`wp_is_json_request()` is not a REST-request test.** `CLAUDE.md` said to use it and now
  says why not to, for this purpose
- The rule's *target* was right first time. What was wrong was the decision about **when to
  register**, which is the category `CLAUDE.md` already calls the main source of bugs in
  this project: "A rule that 'does nothing' is nearly always a phase problem"

### Still to do

Re-confirm the blog-feed rule under 1.24.1. It has no time gate, so this is cheap:

- On the WP dashboard with the widget loaded, the **"Latest Blog Posts from Rank Math"**
  heading and `class="rank-math-blog-list"` must both be absent
- The 404, redirection and analytics figures in the same widget must still be present —
  that is the half the surgical removal exists to preserve
- Directly on the route, `GET /wp-json/rankmath/v1/dashboardWidget` with an authenticated
  session returns the widget body as a string; probe that response rather than the
  dashboard HTML if a cleaner assertion is wanted

Also still source-verified only: `rank_math_pro_notice`, which needs a site where the PRO
nag rather than the review nag is the banked one. Its gates, for a bench:

| Target | Gate |
|---|---|
| PRO notice | `rank_math_install_date` + a stored `rank_math_pro_notice_date` in the past (10 days by default, random 7–30 for pre-1.0.69 installs); main site only; `rank_math_already_upgraded` unset; PRO not installed; **and `rank_math_review_plugin_notice` not currently banked** |
| Review notice | `rank_math_already_reviewed` unset, `rank_math_review_notice_added` unset, the review date passed, and **`rank_math_pro_notice` not currently banked** |

No PHP fatals were reported on the client site, and no other Rank Math notice went missing
— the store was still rendering, which is the two-sided assertion that matters for
mechanism 4.

## Additions to `headwall-nag-cleanup.php`: 3 rules — 2 × mechanism 4, 1 × mechanism 2

```php
const RANK_MATH_PROMO_NOTIFICATION_IDS = [
	'rank_math_pro_notice',
	'rank_math_review_plugin_notice',
];

private function remove_rank_math_stored_promos() : void {
	if ( ! function_exists( 'rank_math' ) ) {
		// Not installed.
	} else {
		$notification_centre = rank_math()->notification;

		if ( ! is_object( $notification_centre ) || ! method_exists( $notification_centre, 'remove_by_id' )
			|| ! method_exists( $notification_centre, 'has_notification' ) ) {
			$this->log( 'seo-by-rank-math', 'Notification centre not reachable; no action taken.' );
		} else {
			foreach ( self::RANK_MATH_PROMO_NOTIFICATION_IDS as $notification_id ) {
				if ( ! $notification_centre->has_notification( $notification_id ) ) {
					// Never banked on this site, or already removed by an earlier request.
					continue;
				}

				$notification_centre->remove_by_id( $notification_id );
				$this->log( 'seo-by-rank-math', sprintf( 'Removed stored notification %s.', $notification_id ) );
			}
		}
	}
}

private function unhook_rank_math_dashboard_feed() : void {
	$this->remove_discarded_instance_callback(
		'rank_math/dashboard/widget',
		'RankMath\\Dashboard_Widget',
		'dashboard_widget_feed',
		'seo-by-rank-math'
	);
}
```
