# Brainstorm Force — `bsf-analytics`, `bsf-core`, `astra-notices`

- slug: covers `astra-addon`, `ultimate-addons-for-gutenberg`, `spectra-pro`,
  `astra-widgets`, `custom-typekit-fonts`
- version analysed: Astra Pro `4.13.8`, Spectra `2.20.3`, Spectra Pro `1.3.2`,
  Astra Widgets `1.2.17`, Custom Typekit Fonts `2.1.1`
- source: `/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip`
- licensing: freemium (Astra Pro and Spectra Pro are premium; the rest are free)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5).

Brainstorm Force ship three shared libraries across their range, so this is one
document for five plugins, in the manner of `yith-plugin-fw.md`:

| Library | What it is | In |
|---|---|---|
| `bsf-analytics` | Usage tracking, opt-in notice, deactivation survey | Astra Pro, Spectra, Astra Widgets, Custom Typekit Fonts |
| `bsf-core` | Product registration, licensing, updates, rollback | Astra Pro, Spectra Pro |
| `astra-notices` | Generic admin notice framework (`BSF_Admin_Notices`) | Spectra, Astra Widgets, Custom Typekit Fonts |

**One rule is added**, against `bsf-analytics`. The other two libraries are left
entirely alone, and the reasons matter more than the rule does — `bsf-core` prints
licence activation notices and `astra-notices` carries database migration prompts.
Both offer tempting one-line kill switches that this project must not use.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 16 across the five plugins. 1 promotional, 1 telemetry opt-in, the rest operational |
| Vendor opt-out filters | `bsf_usage_tracking_enabled` (global tracking kill switch), `{$key}_tracking_enabled` (per-entity), `astra_notices_user_cap_check` / `bsf_admin_notices_user_cap_check` (framework-wide, rejected), `{$source}_white_label_options` (rejected), `bsf_display_product_activation_notice_{id}` and `bsf_product_activation_notice_{id}` (licence, not touched) |
| Vendor opt-out constants | `BSF_PRODUCTS_NOTICES`, `BSF_<PRODUCT>_NAG`, `BSF_<PRODUCT>_NOTICES` — **all licence-related, all rejected** |
| Dashboard widgets | **None** in any of the five |
| Outbound calls from widgets | No widgets. `bsf-analytics` sends a usage payload; the rule stops it |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Usage-tracking payload | `init` 99 → `BSF_Analytics::maybe_track_analytics` | **suppress** | Outbound telemetry. Gated by `bsf_usage_tracking_enabled` |
| Usage-tracking opt-in notice | `admin_init` → `BSF_Analytics::option_notice` | suppress — **blocked** | "Help shape the future of…". Instance discarded; see below |
| Licence activation notice | `admin_notices` 1000 → `bsf_notices` | **keep** | *"Please activate your copy of the [Product] to get update notifications"*. Never suppressed |
| Spectra Legacy DB update required | `admin_notices` → `UAGB_Admin::register_notices` | **keep** | Database migration prompt. Never suppressed |
| DB update in progress / success | same callback | keep | Operational status for the migration above |
| Block Editor required | same callback | keep | *"Plugin is currently NOT RUNNING"* — dependency/conflict notice |
| Spectra Pro popup upsell | same callback | suppress — **blocked** | "Want to do more with Popup Builder? … Upgrade Now". Mixed output; see below |
| Astra theme requirement | `admin_notices` → `astra_addon_theme_requirement_notice` | keep | Missing dependency |
| Rollback version managers (×2) | `admin_notices` | keep | Operational, and only on the vendor's rollback flow |
| Spectra / Spectra Pro load failures | `admin_notices` | keep | Dependency and version-mismatch errors |
| UAG PHP / WP version failures | `admin_notices` | keep | Version warnings |
| Gutenberg templates file permission | `admin_notices` | keep | Real filesystem problem on the site |
| Deactivation survey | `admin_footer` | keep | Renders only on `plugins.php` during deactivation, not in the notice area |

## Deliberately left alone

### `BSF_PRODUCTS_NOTICES` — the trap

`bsf-core` gates its entire notice output on a constant
(`auto-update/admin-functions.php:261`):

```php
if ( defined( 'BSF_PRODUCTS_NOTICES' ) && ( 'false' === BSF_PRODUCTS_NOTICES || false === BSF_PRODUCTS_NOTICES ) ) {
    return false;
}
```

One line in `wp-config.php` silences every `bsf_notices` output across every
Brainstorm Force product. It is exactly the sort of switch this project looks for, and
it must not be used.

What `bsf_notices` actually prints is:

> Please **activate** your copy of the *[Product]* to get update notifications, access
> to support features & other resources!

That is a licence activation notice, first on the never-suppress list. Using this
constant on the fleet would mean ~38 Astra Pro and ~15 Spectra Pro sites silently
stop receiving updates with nothing on screen to say why. The same applies to the
per-product `BSF_<PRODUCT>_NAG` and `BSF_<PRODUCT>_NOTICES` constants and to the
`bsf_display_product_activation_notice_{id}` filter.

This is the clearest example so far of why the boundary rule is a hard constraint
rather than a guideline: the biggest available win in this vendor's code is the one
thing we must not take.

### `astra-notices` — a general framework carrying migration prompts

`BSF_Admin_Notices::show_notices` is registered on `admin_notices` at priority 30 and
renders whatever any BSF plugin has queued. Two filters would disable it wholesale —
`astra_notices_user_cap_check` and `bsf_admin_notices_user_cap_check`, both wrapping
the capability check.

Not used, because the framework carries operational content. Spectra queues five
notices through it and four are operational, including:

> **Spectra Legacy database update required** — We've detected that some of your pages
> were created with an older version of Spectra Legacy… we recommend updating the
> Spectra Legacy database now.

Suppressing the framework would destroy a database migration prompt. That is the
literal example `CLAUDE.md` gives when it bans blanket suppression.

### `UAGB_Admin::register_notices` — mixed output

The one genuine upsell found in this vendor's notice area is
`uagb-spectra-pro-popup-note`: *"Want to do more with Popup Builder? … Unlock enhanced
features … Upgrade Now"*, shown only on `edit.php?post_type=spectra-popup` when Spectra
Pro is not installed.

It cannot be removed on its own. It is queued from the same callback that queues the
database migration prompt, the migration progress and success notices, and the "Block
Editor required / plugin is currently NOT RUNNING" dependency notice. Unhooking
`register_notices` takes all five. Per the analysis checklist, mixed output is a reason
not to write the rule, and here the collateral is a schema migration prompt.

Note it is also narrow in practice — one screen, one condition — so the cost of leaving
it is low.

### The `bsf-analytics` opt-in notice — blocked, same shape as WPB Product Slider

`BSF_Analytics::option_notice` queues the tracking consent nag:

> **Help shape the future of [Product].** Share how you use the plugin so we can build
> features that matter…

It is a usage-tracking opt-in prompt, squarely on the suppress list, and it recurs
monthly after "Skip" (`MONTH_IN_SECONDS`). It cannot be reached:

- **Mechanism 1** — no filter or constant gates the notice.
  `bsf_usage_tracking_enabled` does **not** suppress it (see "What the rule does and
  does not do"). `{$source}_white_label_options` would skip it, but that filter exists
  to rebrand the vendor's whole UI; using it to hide a notice is the same abuse this
  project rejected for `wpdesk_tracker_notice_screens`, with worse side effects
- **Mechanism 2** — `BSF_Analytics_Loader::load_analytics` ends with
  `new BSF_Analytics( $unique_entities, $this->analytics_path, $this->analytics_version );`
  and discards the return value. There is no singleton on `BSF_Analytics` itself and
  the loader keeps no reference. Identical to `WPB_WPS_Review_Notice`
- **Mechanism 3** — not a dashboard widget

Removing it would need a **second** `$wp_filter` exception. Per `CLAUDE.md` that
requires this write-up plus a deliberate decision, and it is left for that decision
rather than taken here. Two further complications if it is ever revisited:

- The callback is on `admin_init` at priority 10 in the current library, so an unhook
  would have to run *before* `admin_init:10` — earlier than this plugin's usual 999 —
  whereas the older library in `astra-widgets` and `custom-typekit-fonts` puts
  `option_notice` directly on `admin_notices`. Two shapes, one rule
- The notice only renders where `BSF_Admin_Notices` exists, so a site running Astra Pro
  alone never sees it

## Mechanism

- tier: 1 (vendor hook)
- phase: file scope
- vendor registers at: `BSF_Analytics_Loader::load_analytics` on `init` priority 10,
  which constructs `BSF_Analytics`; the filter is read later, on `admin_init` and on
  `init` priority 99
- instance reachable via: N/A
- priority: default. Verified that **nothing** in any of the five plugins registers its
  own `bsf_usage_tracking_enabled` callback, so the WP Desk problem — a vendor
  overriding its own opt-out at priority 10 — does not arise here

### What the rule does and does not do

`bsf_usage_tracking_enabled` is read in exactly one place,
`BSF_Analytics::is_tracking_enabled()`, which has two callers:

- `maybe_track_analytics()` — returns early when tracking is disabled, so **the payload
  is never sent**. This is what the rule buys
- `option_notice()` — returns early when tracking is *enabled*, so a `false` here does
  **not** suppress the opt-in notice

That asymmetry is deliberate on the vendor's part and worth stating plainly: the rule
stops the telemetry, not the nag. It does not make the notice situation worse either —
where the `{$key}_usage_optin` site option is unset the notice shows with or without
the filter, and where it is set the notice is skipped either way.

The vendor documents the filter in-code, and the comment is unusually direct:

```php
// Global kill switch — allows hosting providers, compliance plugins,
// or agency developers to disable all BSF tracking with one filter.
```

### Library version coverage

`BSF_Analytics_Loader` scans every registered entity, picks the **highest**
`bsf-analytics` version found across all installed BSF plugins, and constructs a single
`BSF_Analytics` with every entity registered against it. So one modern plugin covers
the rest.

The global filter exists only in the newer library (present in Astra Pro 4.13.8 and
Spectra 2.20.3). The older copy in Astra Widgets 1.2.17 and Custom Typekit Fonts 2.1.1
has only the per-entity `{$key}_tracking_enabled` filter. **A site running only those
two older plugins and nothing else from Brainstorm Force is not covered by this rule.**
Given both are almost always installed alongside Astra or Spectra, that gap is accepted
rather than papered over with a list of per-entity filter names.

### On non-consented opt-in

Paul's recollection is that BSF have auto-enabled tracking without consent in the past.
That behaviour is **not present in 4.13.8**: `{$key}_usage_optin` is written only from
`handle_optin_optout()` in response to a nonce-checked user action, and
`maybe_migrate_options()` only carries a pre-existing value across to the renamed
option. This analysis cannot speak to older releases.

The filter is worth having regardless — it makes a site immune to a future release
that opts in on the site owner's behalf, which is precisely the risk.

## Verification

Tested on `bench2.local` (WP 7.1) with Astra Pro 4.13.8, Spectra 2.20.3, Astra Widgets
1.2.17 and Spectra Pro 1.3.2 installed and active, over an authenticated admin request.
`wp-cli` is not a valid harness here: it runs with `is_admin()` false, so this plugin
bails and none of its filters register.

| Check | Result |
|---|---|
| `apply_filters( 'bsf_usage_tracking_enabled', true )` | `false` — rule applies |
| `bsf_notices` function exists and is hooked on `admin_notices` | **yes** — licence notice preserved |
| `astra_addon_theme_requirement_notice` hooked | **yes** — dependency notice preserved |
| Total `admin_notices` callbacks | 25 — no blanket effect |
| Resolved `bsf-analytics` path | Spectra's `lib/bsf-analytics`, confirming the loader picks the highest version |
| PHP fatals / parse errors in `error.log` | 0 |
| Front page | HTTP 200 |

### Confirmed in the browser

Checked by Paul on 5 Sep 2026 on the same bench, in two stages. This closes what was
initially recorded here as untestable — the licence path **is** observable on an
unlicensed install, because the notice fires for an unregistered product:

- **Astra theme absent.** Astra Pro's dependency notice rendered normally: *"Astra Pro
  requires Astra to be your active theme. Install and activate now."*
- **Astra theme activated**, so `bsf-core` fully loads. The licence activation notices
  rendered for **both** Astra Pro and Spectra Legacy Pro, on the dashboard and on the
  Plugins screen: *"Please activate your license to enable premium features, automatic
  updates, and access to support."*
- Paul's assessment of the resulting notice area: *"important notices only. Things the
  site operator should see, and that require action and/or acknowledgement. There's no
  additional noise."*

That is the rule behaving exactly as intended — telemetry gone, every operational
notice intact. No licences were sourced or activated for this; the notices appear
precisely *because* the products are unregistered.

## Drift check

Re-check when a new version appears in the vault:

- `admin/bsf-analytics/class-bsf-analytics.php` — `is_tracking_enabled()` and its two
  callers. If a third caller appears, or a licensing or update path starts consulting
  it, **withdraw the rule**
- `admin/bsf-core/auto-update/admin-functions.php` — `bsf_notices()`. If the licence
  wording changes to something purely promotional, revisit; as long as it says
  "activate … to get update notifications" it stays
- `classes/class-uagb-admin.php` — `register_notices()`. If the Spectra Pro popup upsell
  is ever split into its own callback, it becomes a clean mechanism 2 target
- `lib/astra-notices/class-bsf-admin-notices.php` — if a per-notice filter is added
  (something like `bsf_admin_notices_show_{id}`), both the upsell and the opt-in notice
  become mechanism 1 rules and this document's two blocked findings can be closed

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 1

```php
// Brainstorm Force bsf-analytics, bundled in Astra Pro, Spectra and others.
// The vendor documents this in-code as a kill switch for hosting providers.
// Verified against Astra Pro 4.13.8 and Spectra 2.20.3.
// docs/plugins/brainstorm-force.md
add_filter( 'bsf_usage_tracking_enabled', '__return_false' );
$this->log( 'brainstorm-force', 'Registered bsf_usage_tracking_enabled opt-out.' );

// BSF licence-activation and database-migration notices are deliberately
// untouched; BSF_PRODUCTS_NOTICES would take both. See the doc.
```
