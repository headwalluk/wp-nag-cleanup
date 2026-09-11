# CartFlows

- slug: `cartflows`
- version analysed: `3.2.0`
- source: `/vault/backups/wordpress/plugins/cartflows/cartflows,3.2.0.zip`
- licensing: freemium (CartFlows Pro is a separate premium plugin)
- Freemius bundled: no
- bundled libraries: `bsf-analytics` 1.1.29, `astra-notices` (`BSF_Admin_Notices`) 1.2.3,
  `nps-survey`, `action-scheduler`

## Analysis

Analysed on 11 Sep 2026 by Claude Code (Claude Opus 5).

CartFlows is a Brainstorm Force product and ships the same three shared libraries
covered in `docs/plugins/brainstorm-force.md`. Its notice area is mostly operational —
WooCommerce dependency errors, a script migration prompt, Pro version-mismatch warnings
and setup-wizard navigation — but it carries **two** clear nags, and both are now
suppressed:

- the `bsf-analytics` usage-tracking opt-in, *"Help shape the future of CartFlows"*
- a WordPress.org 5-star review request

The telemetry notice is the one `brainstorm-force.md` recorded as **blocked** in
September 2026. It is no longer blocked: `find_instance_callback()` has since become an
established mechanism with ten uses, so the discarded-instance problem that stopped it
then is now routine. That finding is closed in `brainstorm-force.md`, and the rule
covers **every** Brainstorm Force plugin on a site, not just CartFlows — see "Library
scope" below.

Everything CartFlows brands heavily but which is genuinely operational — the Legacy UI
deprecation notice and the custom-script database migration prompt Paul flagged as
*"branded, so they look and feel like marketing nags"* — is deliberately left in place.
They are dressed like adverts; they are not adverts.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 17. 13 in CartFlows itself, 3 in bundled `action-scheduler`, 1 in `astra-notices`. Two promotional, the rest operational |
| Vendor opt-out filters | `cartflows_show_review_notice` (**used**), `bsf_usage_tracking_enabled` (already used), `nps_survey_show_notice` (available, not used), `cf_white_label_options` (**rejected**), `astra_notices_user_cap_check` / `bsf_admin_notices_user_cap_check` (framework-wide, rejected), `cartflows_enable_non_sensitive_data_tracking` (redundant), `cartflows_admin_notices` (not the notice area — see below) |
| Vendor opt-out constants | **None** |
| Dashboard widgets | 2 — `cartflows_funnel_performance`, `cartFlows_setup_dashboard_widget`. **Both kept** |
| Outbound calls from widgets | **None on render.** Both widgets read local data only. CartFlows does fetch `cartflows.com` for its docs panel, but from a scheduled job, not a widget |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Usage-tracking opt-in, `cf-optin-notice` | `admin_init` 10 → `BSF_Analytics::option_notice` | **suppress** | *"Help shape the future of CartFlows. Share how you use the plugin…"* Telemetry consent prompt, recurs every 7 days after Skip |
| 5-star review request, `cartflows-5-start-notice` | `admin_head` → `Cartflows_Admin_Notices::show_admin_notices` | **suppress** | *"It would be awesome if you could leave us a 5-star review"*. Review begging, first item on the suppress list |
| NPS survey, `nps-survey-cartflows` | `admin_footer` 999 → `show_nps_notice` | keep — this pass | *"How likely are you to recommend CartFlows…"* Suppressible via `nps_survey_show_notice`; not taken here, see below |
| Legacy UI deprecation, `cartflows-switch-to-new-ui-notice` | same callback as the review nag | **keep** | Product-change announcement with a working toggle and a stated way back (Settings → Advanced). Operational |
| Custom script migration prompt | `admin_notices` → `show_script_migration_notice` | **keep** | Data migration prompt. Never suppressed |
| Script migration complete | `admin_notices` → `show_script_migration_complete_notice` | **keep** | Status for the migration above |
| Weekly report email feature notice | `admin_notices` → `show_weekly_report_email_settings_notice` | keep — ambiguous | Announces a new feature and links to its setting. Borderline promotional; ambiguous means keep |
| Gutenberg plugin conflict | `admin_notices` → `gutenberg_plugin_deactivate_notice` | **keep** | Plugin conflict notice |
| CartFlows Pro too old | `admin_notices` → `required_cartflows_pro_notice` | **keep** | Version mismatch between CartFlows and CartFlows Pro |
| CartFlows Pro update available | `admin_notices` → `show_upgrade_starter_plan_notice` | **keep** | *"The new version … is released. Please download the latest zip."* Update notification, and gated to exactly Pro 1.11.14 |
| WooCommerce missing / step load failure | `admin_notices` → `fails_to_load` | **keep** | Missing dependency |
| Setup wizard prompts (×2) | `admin_notices` → `show_setup_wizard` | **keep** | Onboarding state, dismissible, only until setup completes |
| AI account connected | `admin_notices` → `render_auth_connected_notice` | **keep** | One-shot transient-backed success confirmation |
| Back-to-flow link on step editor | `admin_notices` → `back_to_new_step_ui_for_classic_editor` (×2) | **keep** | Navigation control, not a notice |
| Action Scheduler notices (×3) | `admin_notices` | **keep** | Past-due actions, comment cleanup, migration status. Operational |
| Funnel Performance widget | `cartflows_funnel_performance` | **keep** | Mixed output — see below |
| CartFlows Setup widget | `cartFlows_setup_dashboard_widget` | **keep** | Only when setup was started and skipped; a resume link, not a promotion |

## Deliberately left alone

### `cf_white_label_options` — the tempting wrong switch

`BSF_Analytics::is_white_label_enabled()` reads `apply_filters( $source . '_white_label_options', array() )`
and treats any `true` in the returned array as white-label mode. That flag does two things
at once: it skips the opt-in notice *and* disables the tracking send.

So a single `add_filter( 'cf_white_label_options', ... )` returning `[ true ]` would have
solved this whole document in one mechanism 1 line, with no `$wp_filter` read at all.

It is not used, for the same reason `brainstorm-force.md` rejected it and
`wpdesk_tracker_notice_screens` was rejected before that: the filter's purpose is to
declare the product rebranded, not to hide a notice. Asserting white-label state to get a
side effect is a lie told to the vendor's code, and its blast radius is whatever that
vendor decides white-label means in the next release.

Worth recording that in CartFlows 3.2.0 specifically, **nothing else reads
`cf_white_label_options`** — a grep across the plugin outside `libraries/bsf-analytics`
returns no hits, so the practical side effects today would be nil. That is an argument
about this release, not about the filter, and it does not change the decision.

### The NPS survey

`Nps_Survey::show_nps_notice()` renders *"How likely are you to recommend CartFlows to
your friends or colleagues?"*, escalating a positive score into a WordPress.org review
ask. It is a nag by this project's definition and there is a documented opt-out —
`nps_survey_show_notice`, since NPS library 1.0.13, which receives the plugin slug.

Not taken in this pass, for two reasons:

- It renders only on `edit-cartflows_flow` and `toplevel_page_cartflows` — the vendor's
  own screens, which `CLAUDE.md` puts out of scope by construction. It never reaches the
  general admin notice area
- `nps-survey` is a shared Brainstorm Force library resolved through the globals
  `$nps_survey_version` / `$nps_survey_init`, so an unscoped `__return_false` would reach
  every BSF product on the site. Doing it properly needs a slug-scoped instance method,
  and that belongs in its own analysis with its own bench run

Recorded here so it is not re-discovered as new. If it is ever written, it is a
mechanism 1 rule and this is the filter.

### `cartflows_admin_notices` is not what it sounds like

`Cartflows_Admin_Menu::cartflows_admin_notices()` builds an array of *recommended
integration* prompts consumed by CartFlows' own React settings app. It never touches
`admin_notices` and never renders in the WordPress notice area. Named to look like a
prize; it is not one.

### The Funnel Performance dashboard widget — mixed output

`cartflows_funnel_performance` prints the site's real WooCommerce revenue and order count
for the last 30 days. On a site without CartFlows Pro it swaps the summary line for an
upsell — *"Want to boost funnel revenue by 30%+? Unlock order bumps, upsells…"* — and adds
locked feature cards with a `utm_campaign=dashboard-widget-card` link.

Kept. The widget is genuinely reporting the state of the site, the upsell is one line
inside it, and `remove_meta_box()` is all-or-nothing — it would take the revenue figures
with it. The usual secondary justification for mechanism 3 is absent too: `render_widget()`
queries the local database and makes **no** outbound request, so removing it buys no
privacy and saves no HTTP call.

### The Legacy UI notice, and why "branded" is not "promotional"

Paul's initial read was that the Legacy UI deprecation and database update notices
*"look and feel like marketing nags"* because they carry the CartFlows logo, the same
button styling and the same dismiss affordance as the telemetry nag — they all go through
`BSF_Admin_Notices`, so they are visually identical by construction.

The boundary rule tests content, not costume. *"Your CartFlows got a fresh new look ✨ …
Not ready? You can always switch back from Settings → Advanced"* tells the site owner
something true about their site and offers an action, so it stays. The shared styling is
exactly why the test has to be applied to the text.

### `BSF_Admin_Notices` has no per-notice escape hatch

Checked, because the drift check in `brainstorm-force.md` asks for it. In 1.2.3 the
framework exposes `astra_notice_before_markup_{id}` / `bsf_admin_notice_before_markup_{id}`
and friends, but these are all `do_action` — no return value, and they fire after output
has begun. The notice list is `private static $notices` with no removal API. The only
filters are `astra_notices_user_cap_check` and `bsf_admin_notices_user_cap_check`, which
are framework-wide capability overrides and would take the migration prompts with them.

So the review nag could not have been reached through the framework. It was reached
because CartFlows added its own filter in 2.2.5.

## Mechanism

Two rules.

### 1. Review request — mechanism 1

- tier: 1 (vendor hook)
- phase: file scope
- vendor registers at: `Cartflows_Admin_Notices::show_admin_notices` on `admin_head`,
  which calls `Astra_Notices::add_notice()` with `'show_if' => $this->should_show_review_notice()`
- instance reachable via: N/A
- priority: default. Nothing in CartFlows registers its own callback on this filter

`cartflows_show_review_notice` was added in CartFlows 2.2.5 and the vendor's own docblock
states the intent:

> Allows site owners, agencies, and white-label distributors to override the default
> gating logic. Return false to suppress the notice entirely.

It governs that one notice and nothing else, which is what separates it from
`cf_white_label_options` above.

### 2. Usage-tracking opt-in — mechanism 2

- tier: 2 (targeted unhook, via the sanctioned `$wp_filter` reader)
- phase: `admin_init` at `self::EARLY_PRIORITY`
- vendor registers at: `BSF_Analytics_Loader::load_analytics()` on `init` priority 10
  constructs `BSF_Analytics`, whose constructor does
  `add_action( 'admin_init', array( $this, 'option_notice' ) )` at the default priority
- instance reachable via: **not reachable.** `class-bsf-analytics-loader.php:114` is
  `new BSF_Analytics( $unique_entities, $this->analytics_path, $this->analytics_version );`
  with the return value discarded, and `BSF_Analytics` has no singleton accessor

Mechanisms 1 to 3 were re-checked before reaching for the reader, per `CLAUDE.md`:

- **Mechanism 1** — no filter gates the notice. `bsf_usage_tracking_enabled` does not
  (documented in `brainstorm-force.md`: a `false` there makes `is_tracking_enabled()`
  return false, and `option_notice()` bails only when it returns *true*).
  `cf_tracking_enabled` returning true *would* suppress the notice — by **enabling
  telemetry**, which is the exact opposite of the intent. `cf_white_label_options`
  rejected above
- **Mechanism 2 by name** — impossible, the instance is discarded
- **Mechanism 3** — not a dashboard widget
- **Mechanism 4** — not applicable. `BSF_Admin_Notices::add_notice()` keeps the notice in
  a request-scoped static array; the only thing stored is the notice **id** in
  `astra_notices_allowed`, which is a permission list, not the notice

The phase is the WPCode case from `CLAUDE.md`: the producer is itself on `admin_init` at
priority 10, so `LATE_PRIORITY` would run after it has already queued the notice.
`EARLY_PRIORITY` removes it first. Confirmed on the bench — the probe reports
`option_notice_on_admin_init=yes@10` before and `no` after.

### Library scope

`BSF_Analytics_Loader` is a singleton. Every Brainstorm Force plugin calls `set_entity()`
on it at `init` priority 5, and `load_analytics()` constructs **one** `BSF_Analytics` at
priority 10 holding every entity. So there is exactly one `option_notice` callback on a
site regardless of how many BSF plugins are installed, and removing it suppresses the
opt-in nag for **all** of them — CartFlows, Astra Pro, Spectra, SureCart and the rest.

This also means the rule does not need CartFlows to be present. It is keyed on the
`BSF_Analytics` class, not on any plugin.

## Drift check

Re-check when a new version appears in the vault:

- `libraries/bsf-analytics/class-bsf-analytics.php` — the `add_action( 'admin_init', array( $this, 'option_notice' ) )`
  line in the constructor. If the priority changes, or the callback moves to
  `admin_notices` as it is in the older library shipped with Astra Widgets and Custom
  Typekit Fonts, the hook and phase both need re-deriving
- `libraries/bsf-analytics/class-bsf-analytics-loader.php:114` — if the vendor ever
  assigns the instance or adds a singleton, **drop the reader and name the callback**
- `classes/class-cartflows-admin-notices.php` — `should_show_review_notice()`. If
  `cartflows_show_review_notice` disappears, the review nag is back and there is no
  framework-level fallback; re-read this document's "no per-notice escape hatch" section
  before reaching for anything broader
- `libraries/astra-notices/class-bsf-admin-notices.php` — if a per-notice *filter* is
  added, the NPS and weekly-report questions can be revisited cleanly
- **BSF churn applies here.** `brainstorm-force.md` records `bsf_usage_tracking_enabled`
  already missing from Spectra's 3.0 beta. The mechanism 2 rule above is unaffected by
  that — it names a class and a method, not a filter — but re-verify both on every
  CartFlows release

## Verification

Bench: `bench2.local`, WP 7.1, WooCommerce 11.1.0, CartFlows 3.2.0, over authenticated
admin HTTP requests as an administrator. `wp-cli` is not a valid harness — it runs with
`is_admin()` false, so the plugin bails and registers nothing.

**Time gates that had to be backdated.** Neither nag shows on a fresh install:

| Gate | Where | Bench action |
|---|---|---|
| `+24 hours` from install | `cf_usage_installed_time` site option | backdated 30 days |
| 7-day re-show cooldown | `bsf_usage_last_displayed_time` | confirmed unset |
| ≥1 published funnel | `cartflows_flow` post count | one published |
| First funnel order received | `cartflows_first_funnel_order_received` | set to 1 |
| 2-week delay before first show | transient + user meta `cartflows-5-start-notice` | requested once to arm the vendor's own delay, then deleted the transient |

The activation request 302s to `index.php?page=cartflows-onboarding`, so the first capture
was absorbed with `curl -L -o /dev/null` and every capture asserts
`grep -c 'id="dashboard-widgets"'` rather than trusting the URL.

The hook counts above were re-measured after the bench probe was corrected — it had been
reporting `count( ..., COUNT_RECURSIVE )`, which is not a callback count. See the note at
the end of `docs/plugins/astra-theme.md`.

### Usage-tracking opt-in — **Confirmed**

Surface: admin notice area, dashboard. Structural probe on the `id` the framework emits.

| Check | Before | After |
|---|---|---|
| `cf-optin-notice` in dashboard HTML | 1 | **0** |
| `option_notice` on `admin_init` (probe) | `yes@10` | **`no`** |
| Debug log | — | `bsf-analytics: Removed BSF_Analytics::option_notice from admin_init priority 10.` |

### 5-star review request — **Confirmed**

Surface: admin notice area, dashboard and plugins screens. Captured with the rule
temporarily stripped from the deployed file, so the "before" is a genuine render.

| Check | Before | After |
|---|---|---|
| `cartflows-5-start-notice` in dashboard HTML | 1 | **0** |
| `cartflows-5-star` wrapper class | 1 | **0** |
| Same on `plugins.php` | — | **0** |

### Negative checks — the operational notices that must still be there

The strongest of these: `show_admin_notices` queues the review nag **and** registers the
script-migration and weekly-report notices. Arming the weekly-report notice
(`cartflows_show_weekly_report_email_notice = yes`) proves the dispatcher still runs and
its operational half still renders with the review nag gone.

| Check | Result |
|---|---|
| `weekly-report-email-notice` renders with both rules active | **yes** |
| `cartflows_funnel_performance` widget markup | 36 matches before, **36 after** |
| Callbacks on `admin_notices` | 41 before, **41 after** — the removal is on `admin_init`, nothing was stripped from the notice hook |
| Callbacks on `admin_init` | 92 before, **90 after** — exactly the two removed (this rule, plus the Astra theme rule active on the same bench) |
| `plugins.php` renders | 200, `id="the-list"` present |
| `admin.php?page=cartflows` renders | 200 |
| Front page | 200 |
| PHP fatals / parse errors in `error.log` | **0** |

## Additions to `headwall-nag-cleanup.php`: 2 rules, mechanisms 1 and 2

```php
// In register_vendor_optouts():
// CartFlows 5-star review request. The vendor added this filter in 2.2.5 and
// documents it in-code as an override for site owners and white-label
// distributors. CartFlows 3.2.0. docs/plugins/cartflows.md
add_filter( 'cartflows_show_review_notice', '__return_false' );

// In unhook_early_vendor_notices():
$this->unhook_bsf_analytics_optin_notice();

public function unhook_bsf_analytics_optin_notice() : void {
    $this->remove_discarded_instance_callback( 'admin_init', 'BSF_Analytics', 'option_notice', 'bsf-analytics' );
}
```
