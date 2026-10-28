# SureForms

- slug: `sureforms`
- version analysed: `2.12.7`, re-read at `2.12.9` on 28 Oct 2026 (both filters checked
  across every vault release, `2.1.0` to `2.12.9`)
- source: `/vault/backups/wordpress/plugins/sureforms/sureforms,2.12.9.zip`
- licensing: freemium (SureForms Pro is a separate plugin)
- Freemius bundled: no. Bundles Brainstorm Force's `bsf-analytics` **1.1.29**,
  `astra-notices` **1.2.1** and Action Scheduler
- vendor: **Brainstorm Force**. Treat as high-churn; see `brainstorm-force.md`

## Analysis

Analysed on 18 Sep 2026 by Claude Code (Claude Opus 5).

Paul found a SureForms notice on the same client site as SureRank and judged it
operational. It was not pasted; the two candidates that fit are the "Finish setting up
*form*" prompt and the form-check action items, and **both are kept**. He asked for the
plugin to be examined anyway, as a BSF sibling of SureRank.

Two rules: the 5-star review request and, since 1.47.0, the Getting Started notice, both
through the vendor's own filters. The usage-tracking
opt-in was already covered by the 1.25.0 `bsf-analytics` rules. SureForms' notice
work is otherwise mostly operational. It includes a `has_action_item_warnings()` precedence
chain so that a broken form outranks every engagement notice. The engagement notices that
remain are onboarding for the plugin itself, and they are left as ambiguous.

### 28 Oct 2026 — Getting Started notice moved to suppress (1.47.0)

Paul pasted the Getting Started notice from a client site. He agreed it might just about
count as operational, but said it *"reads exactly like a review nag, and drowns out the
admin notification area"*. It repeats weekly, with "Maybe later" / "I already know"
buttons. The ambiguity call below was always his to make. The doc said
`srfm_show_getting_started_notice` was there if he decided otherwise, and he has.
2.12.9 re-read:

- `display_srfm_getting_started_notice()` still returns on
  `! apply_filters( 'srfm_show_getting_started_notice', true )`. That is read **before**
  `Astra_Notices::add_notice()`, so the notice is never queued. Present from 2.10.1
- **No other notice gates on it.** It yields to the rating notice, the Thank You prompt
  and the action items through its own `show_if`. None of them looks at Getting Started,
  so removing it releases nothing. Grep of `admin/admin.php` for `getting_started` /
  `getting-started`: the producer, its filter and its analytics labels only

**New in 2.12.8: `srfm_hide_promotions`. Not used.** `Helper::hide_promotions()` is a
blanket "hide SureForms promotions" switch. Its call sites are:

| Call site | What it hides | Operational? |
|---|---|---|
| `__construct` | SureMail admin menu page | no, but it is the vendor's own menu |
| `register_form_setup_widget`, `maybe_register_dashboard_widget`, both `enqueue_*_widget_assets` | **all** the SureForms dashboard widgets, including Recent Entries | Recent Entries is real site data. See below |
| `display_srfm_rating_notice` | the review request | no |
| `get_connect_surecontact_action` | SureContact connect action item | no |
| **`get_email_delivery_action_items`** | `email_delivery_setup` / `email_delivery_limited`, which come back *"even if it was ignored before"* once a notification has **failed** | **yes** |
| **`get_email_quota_action_items`** | `email_delivery_quota`, the SureContact sending quota running low or **exhausted** | **yes** |
| `is_admin_pointer_visible`, `editor-nudge.php` | the admin pointer, the editor nudge | no |
| `email-delivery/status.php` | the "install SureMail" next-step link (the status text stays) | no |

So it hides notices that say form notifications are not being delivered. That is the
"broad switch with the wrong scope" case (Monster/ExactMetrics' `hide_am_notices` is the
precedent). The narrow per-notice filters are the correct lever, and the docblock says so.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | `admin/admin.php`: `render_action_item_notices`, `render_database_repair_notice`, `srfm_pro_version_compatibility`, `display_srfm_rating_notice`, `display_srfm_getting_started_notice`, `render_thankyou_prompt_notice`, `suppress_foreign_admin_notices` (×3 hooks, `PHP_INT_MIN`). Also Stripe `webhook_configuration_notice`, `BSF_Admin_Notices::show_notices`, and three Action Scheduler notices. One promotional |
| Multiline `add_action(` form | Action Scheduler only |
| `in_admin_header` / `admin_print_footer_scripts` | None. A `wp-pointer` is enqueued on `admin_enqueue_scripts` (see below) |
| Vendor opt-out filters | **`srfm_show_rating_notice`**, used. Also `srfm_show_getting_started_notice` and `srfm_show_thankyou_prompt`, not used. `bsf_usage_tracking_enabled`, already used |
| Vendor opt-out constants | None |
| Dashboard widgets | 3: `sureforms_recent_entries`, `sureforms_ai_quick_draft`, `srfm_form_setup_checklist`. **All kept** |
| Outbound calls from widgets | None on render. AI Quick Draft calls SureForms' AI service only when the admin submits a prompt |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| 5-star review request (`#srfm-plugin-review-notice`) | `admin_notices` → `SRFM\Admin\Admin::display_srfm_rating_notice`, through `Astra_Notices` | **suppress** | Review request once there are 3+ entries or 3+ published forms |
| Usage-tracking opt-in | `admin_init` → `BSF_Analytics::option_notice` | already suppressed | Existing 1.25.0 rule. `brainstorm-force.md` |
| Form-check action items | `admin_notices` → `render_action_item_notices` | keep | Forms that are failing or misconfigured. Warnings and errors only; passing checks are not shown |
| "Finish setting up *form*" | `admin_notices` → `render_thankyou_prompt_notice` | keep | See below |
| Database repair | `admin_notices` → `render_database_repair_notice` | keep | The entries table is missing, so submissions are not being saved |
| Pro version compatibility | `admin_notices` → `srfm_pro_version_compatibility` | keep | Version warning |
| Stripe webhook | `admin_notices` → `webhook_configuration_notice` | keep | Payments configuration |
| Action Scheduler notices | three, bundled library | keep | Past-due actions, migration, comment cleanup |
| Getting Started notice (`#srfm-getting-started-notice`) | `admin_notices` → `display_srfm_getting_started_notice`, through `Astra_Notices` | **suppress** (1.47.0) | Weekly-repeating feature tour; Paul's call, 28 Oct. Was "keep, ambiguous" |
| "SureForms is waiting for you!" pointer | `admin_enqueue_scripts` → `enqueue_admin_pointer` | keep, ambiguous | See below |
| Recent entries widget, and its "Upgrade" footer | `sureforms_recent_entries` | keep | Mixed output; see below |
| AI Quick Draft widget | `sureforms_ai_quick_draft` | keep | A working form-creation box, like core's Quick Draft |
| "Finish setting up your form" widget | `srfm_form_setup_checklist` | keep | Same basis as the notice of the same name |
| `suppress_foreign_admin_notices` | `admin_notices` etc. @ `PHP_INT_MIN` | n/a | See below |

## Deliberately left alone

### "Finish setting up *form*" — the notice Paul judged operational

It appears only for a form **imported from a starter template**. It names that form and
links to the three things that make it work: edit the form, edit its Thank You message,
set where replies go. A template form with the vendor's default replies address sends
submissions nowhere useful. So it is true, specific and actionable, and it **stays**. It
stays hidden on the main dashboard (the checklist widget covers that screen), and while a
form-check warning exists. `srfm_show_thankyou_prompt` would turn it off, and is
deliberately not used. Compare SureRank's permalink upsell in `surerank.md`, which names
nothing and offers only a purchase.

### Getting Started notice — superseded 28 Oct 2026, now suppressed

Kept for the record. The reasoning below was the original ambiguity call. Paul overruled
it in 1.47.0 (see the top of this document). The cross-gating note is still correct.

*"SureForms is ready to power your forms — explore what's possible! … discover features
like AI Form Builder, payment integrations, and more."* After 7 days, for sites that have
not reached the review milestone, repeating weekly. It is a tour of the free plugin's own
features. It names no price and no Pro product, and it is not a review request, a
cross-sell or a newsletter. **Ambiguous, so no rule.** It is the same call as Magical
Addons' GSAP feature notice and Copy & Delete Posts' "Try it first" notice.
`srfm_show_getting_started_notice` exists if Paul decides otherwise.

**The two notices gate each other, one way.** Getting Started's `show_if` is
`! $this->maybe_display_rating_notice()`, the raw milestone, *not* the
`srfm_show_rating_notice` filter. So filtering the review off does **not** let Getting
Started through on a site past the milestone. Bench-confirmed: 0 before and after on a
site with 3 forms.

### The admin pointer

A core `wp-pointer` on `index.php` and `options-general.php`: *"SureForms is waiting for
you! Get started by building your first form. Experience the power of our intuitive AI
Form Builder"*. It shows until dismissed, accepted, or until more than one form is
published. It is onboarding for the plugin's own core feature and names no price.
**Ambiguous, so no rule**, for the same reason as Getting Started.

### Recent entries widget — mixed output

The widget lists this site's forms and their entry counts for the last 7 days. With Pro
absent and 3+ entries or forms, `render_dashboard_widget_footer()` (**private**) adds a
footer line. It is a random Pro feature from `get_random_premium_feature_text()` (*"Use
Conditional Logic to show only what matters"*), with an **Upgrade** link to the pricing
page. It is printed in the same render call as the real data, with no hook in between.
**No rule.** It is the same decision as 404 to 301's Broken Link Checker footer: one line
of promotion does not justify removing real site data.

### `suppress_foreign_admin_notices`

On SureForms' own screens, SureForms removes every other plugin's notice from the three
notice hooks at `PHP_INT_MIN`. It does not touch this project's rules and is not ours to
change. It is noted because a debug session on a SureForms screen will find notices
"missing" that no rule of ours removed. Same pattern as 404 to 301.

## Mechanism

- tier: 1 (vendor opt-out filter), both rules
- phase: file scope, in `register_vendor_optouts()`
- `add_filter( 'srfm_show_rating_notice', '__return_false' )`
- `add_filter( 'srfm_show_getting_started_notice', '__return_false' )`, read at the top of
  `display_srfm_getting_started_notice()` before `Astra_Notices::add_notice()`.
  **Present from 2.10.1**
- read at the top of `display_srfm_rating_notice()`, before `Astra_Notices::add_notice()`,
  so the notice is never queued. **Present from 2.10.1**. 2.1.0 and 2.3.0 have no review
  notice at all
- instance reachable via: N/A

**Not used: `astra_notices_user_cap_check` / `bsf_admin_notices_user_cap_check`.** They
gate the whole shared library, which also carries Astra's and Spectra's migration prompts
and SureForms' own "Finish setting up" notice. See `brainstorm-force.md`.

## Drift check

- `admin/admin.php`: `display_srfm_getting_started_notice()` still returns on
  `! apply_filters( 'srfm_show_getting_started_notice', true )`, and still before
  `add_notice()`
- `inc/helper.php`: `hide_promotions()`. If the email-delivery action items stop
  checking it, re-assess it. It is still too broad while Recent Entries is behind it
- `admin/admin.php`: `display_srfm_rating_notice()` still returns on
  `! apply_filters( 'srfm_show_rating_notice', true )`. A filter nobody reads fails
  silently, and this is a BSF plugin, so re-grep on every new release
- If the review notice ever moves out of `SRFM\Admin\Admin` into its own class (SureRank's
  docblock says its `Review_Notice` "mirrors the SureForms 5-star review notice flow"),
  check that the filter moved with it

```bash
unzip -p /vault/backups/wordpress/plugins/sureforms/sureforms,<version>.zip \
  sureforms/admin/admin.php | command grep -n "srfm_show_rating_notice\|srfm_show_getting_started_notice\|function display_srfm_"
```

## Verification

Bench: `bench2.local`, SureForms **2.12.7** and SureRank 1.10.1 unzipped from the vault,
18 Sep 2026. Before = 1.31.0 (HEAD), after = working copy, `HEADWALL_NAG_CLEANUP_DEBUG` on.
Gate opened with three published `sureforms_form` posts (threshold 3). The object-cache
trap in `surerank.md` applies here too.

### Review request — **Confirmed** (bench)

| Check (dashboard, plugins.php, SureRank's page) | Before | After |
|---|---|---|
| `id="srfm-plugin-review-notice"` | **1** | **0** |

No log line: a mechanism 1 rule cannot report a suppression (see Debug logging in
`CLAUDE.md`).

### Getting Started notice — **Confirmed** (bench, 28 Oct 2026)

Bench: `bench1.local`, SureForms **2.12.9** and Ultimate Post Kit 4.6.0 from the vault,
captured over an authenticated admin HTTP request. Before = 1.46.0 (HEAD), after = 1.47.0
working copy. Zero forms, so the review milestone is unmet and Getting Started is the
eligible notice. The 7-day `display-notice-after` gate was opened with the vendor's own
state. The first view sets the `srfm-getting-started-notice` transient and user meta
`delayed-notice`. Deleting the transient leaves the meta, which `is_expired()` reads as
"delay completed".

| Check | Before | After |
|---|---|---|
| `id="srfm-getting-started-notice"`, `index.php` (`id="dashboard-widgets"` asserted) | **1** | **0** |
| `id="srfm-getting-started-notice"`, `plugins.php` (`id="the-list"` asserted) | **1** | **0** |
| `has_filter( 'srfm_show_getting_started_notice' )`, harness | — | true |
| PHP fatals / warnings | 0 | 0 |

**Bench trap, cost one capture:** bench1's `headwall-hosting` mu-plugin bundles its own
copy of this plugin (`mu-plugins/headwall-hosting/headwall-nag-cleanup.php`, 1.44.0 at the
time). It loads first, so a copy dropped into `mu-plugins/` is a silent no-op behind the
`class_exists` guard. The first "after" capture ran 1.44.0 and showed nothing removed.
Assert `Plugin::VERSION` through the harness, and swap the bundled file, not a sibling.

**Live-confirmed 28 Oct 2026**: Paul deployed 1.47.0 to the client site where he found it and reports the notice gone.

### Negative checks

| Check | Result |
|---|---|
| Screens asserted: `id="dashboard-widgets"`, `id="the-list"` | yes |
| `sureforms_ai_quick_draft` widget still on the dashboard | yes |
| `id="srfm-getting-started-notice"` | 0 before, 0 after, so not released by the filter |
| `srfm-plugin-review-notice` transient | not set, so nothing written |
| PHP fatals / warnings / parse errors | **0** |

### Live — 18 Sep 2026

Paul deployed 1.32.0 to the client site and reported it good. His report did not name the
SureForms review request, so it was not observed there and this rule remains
**bench-confirmed only**.

The "Finish setting up" notice and the action items were not exercised: one needs a
starter-template import, the other a failing form check. No rule names either.

## Additions to `headwall-nag-cleanup.php`: 2 rules, mechanism 1

```php
// In register_vendor_optouts():
add_filter( 'srfm_show_rating_notice', '__return_false' );
add_filter( 'srfm_show_getting_started_notice', '__return_false' ); // 1.47.0
```
