# Elementor

- slug: `elementor`
- version analysed: `4.2.4`
- source: `/var/www/devx.headwall.tech/web/wp-content/plugins/elementor` (installed copy; same version present on `fib` and `hotdogzone`)
- licensing: freemium (Elementor Pro sold separately)
- Freemius bundled: no

## Analysis

Analysed on 4 Sep 2026 by Claude Code (Claude Opus 5).

Elementor prints admin notices from a single callback that walks a **private**
array of eleven notice IDs, nine of which are promotional and two of which are not.
There is no vendor filter and no per-notice hook, so the only available mechanism is
an all-or-nothing `remove_action()`. The two non-promotional notices are therefore
unavoidable collateral, and removing them was an explicit decision — see
*Deliberately left alone*.

Two facts make that decision defensible. First, the database-upgrade notices, which
are the ones the boundary rule exists to protect, are registered on **separate**
callbacks and are untouched. Second, Elementor prints **only one notice per page
load** (`admin-notices.php:1066-1071` returns after the first match), so a
promotional nag currently displaces the operational notice queued behind it. The
nags are actively suppressing Elementor's own useful output.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 4 found — see Findings |
| Vendor opt-out filters | None applicable. `elementor/core/admin/notices` (`core/admin/admin-notices.php:60`) exists but starts as `[]` and builds the *object* notice list for add-ons; it never sees `$plain_notices` |
| Vendor opt-out constants | None |
| Dashboard widgets | 1 — `e-dashboard-overview` (`core/admin/admin.php:456`) |
| Outbound calls from widgets | Yes — `Api::get_feed_data()` (`core/admin/admin.php:585`) |
| Freemius | Not bundled |

## Findings

`core/admin/admin-notices.php:29-41` — the private `$plain_notices` array, all
printed by `Admin_Notices::admin_notices()`, registered in the constructor at
`admin-notices.php:1144` as priority 20 on `admin_notices`.

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| `api_notice` | `admin_notices` p20 | suppress | Remote-fed promotional feed |
| `rate_us_feedback` | `admin_notices` p20 | suppress | Review begging |
| `tracker` | `admin_notices` p20 | suppress | Usage-tracking opt-in prompt |
| `tracker_last_update` | `admin_notices` p20 | suppress | Usage-tracking opt-in prompt |
| `role_manager_promote` | `admin_notices` p20 | suppress | Upsell to Pro |
| `experiment_promotion` | `admin_notices` p20 | suppress | Upsell |
| `site_mailer_promotion` | `admin_notices` p20 | suppress | Cross-sell of a separate product |
| `plugin_image_optimization` | `admin_notices` p20 | suppress | Cross-sell |
| `ally_pages_promotion` | `admin_notices` p20 | suppress | Cross-sell |
| `api_upgrade_plugin` | `admin_notices` p20 | **keep, lost as collateral** | Genuine update notice — see below |
| `local_google_fonts_disabled` | `admin_notices` p20 | **keep, lost as collateral** | Privacy/GDPR configuration state |
| `e-dashboard-overview` widget | `wp_dashboard_setup` | **suppress** (since 1.11.0) | News & Updates remote feed; takes Recently Edited as collateral — see below |

## Deliberately left alone

**`admin_notice_upgrade_is_running` / `admin_notice_upgrade_is_completed`**
(`core/base/db-upgrades-manager.php:228,256`) — the database schema migration
prompts. Registered as **separate callbacks** on `admin_notices`, so the
`remove_action()` below does not touch them. These are exactly the notices this
project exists to keep visible.

**`elementor_fail_php_version` / `elementor_fail_wp_version`** — environment
warnings, on their own registrations. Untouched.

**Connect/licence notices** (`core/common/modules/connect/apps/base-app.php:852`) —
a separate `admin_notices` registration carrying account and licence state.
Untouched, per the boundary rule.

**`e-dashboard-overview` dashboard widget** — *originally left alone as ambiguous;
**removed in 1.11.0** on the site owner's decision.* The original reasoning is kept
below, followed by what changed.

> Not removed, ambiguous. It fetches a remote feed via `Api::get_feed_data()`, which is
> a legitimate reason to want it gone, but the same widget also renders a "recently
> edited" list built from real site data. Mixed output: removing it would take a
> genuinely useful panel with it. Left alone until there is a way to drop only the news
> section.

**There is no way to drop only the news section.** Every filter in the two relevant files
was checked: `core/admin/admin.php` exposes
`elementor/admin/dashboard_overview_widget/footer_actions`,
`elementor/admin/create_new_post/meta` and `elementor/admin/localize_settings`;
`includes/api.php` exposes three filters, all for library templates.
`Api::get_feed_data()` reads `get_option( 'elementor_remote_info_feed_data' )` directly
with no filter, and the widget registration on `wp_dashboard_setup` is unconditional —
no filter, constant or option gates it.

Two facts found while confirming that tipped the decision:

- **Rendering the widget makes an outbound call.** `get_feed_data()` → `get_info_data()`
  → `wp_remote_get( self::$api_info_url )` whenever the transient is cold
- **It forces itself to the top of the dashboard.** `register_dashboard_widgets()`
  reorders `$wp_meta_boxes` with the comment `// Move our widget to top.` — the same
  behaviour that made the WP Desk widget the first target of this project

**The collateral is accepted and named:** the "Recently Edited" list goes too. It is a
convenience panel duplicating what the Pages screen already shows, and unlike a notice
nothing is lost that the site owner cannot reach in two clicks. The decision was taken
by the site owner on 5 Sep 2026, on the grounds that fleet clients do not read the
dashboard panel at all.

If Elementor ever adds a filter around the feed section, **withdraw this rule** and use
the filter instead — that restores Recently Edited at no cost.

**`elementor/announcements/raw_announcements`** (`modules/announcements/module.php:98`)
and **`elementor/admin/homescreen_promotion_tier`**
(`modules/home/transformations/base/transformations-abstract.php:42`) — genuine
vendor opt-out filters for in-app announcements and the Home screen promo tier.
**Out of scope**, not out of bounds: both target Elementor's own interface rather
than the admin notice area or the dashboard, and this project stays out of vendor
UI by construction. Recorded here so they are not rediscovered as an opportunity.

### The collateral, and why it was accepted

`api_upgrade_plugin` (`admin-notices.php:92-122`) is a real update notice: it reads
the `update_plugins` site transient and builds a nonced
`update.php?action=upgrade-plugin` link. It is operational information, and under a
strict reading of the boundary rule it should stay.

It was accepted as collateral on two grounds, decided explicitly by the project
owner on 4 Sep 2026:

1. It is **redundant**. Core surfaces the same pending update on the Plugins screen,
   the Updates screen and the admin-bar update counter. Removing this copy hides
   nothing from the site owner.
2. It renders **only on Elementor's own admin screens**
   (`is_elementor_admin_screen_with_system_info()`), so it is not part of the
   general admin notice flow anyway.

`local_google_fonts_disabled` (`admin-notices.php:473`) is a privacy configuration
hint shown only to installs that predate Elementor 3.33.3. Accepted as collateral on
the same decision.

**This is the project's one standing boundary-rule exception.** It is not a
precedent: it was taken with the full list of losses enumerated in advance. Any
future mixed-output callback gets the same treatment — enumerate, decide, record —
and the default remains "ambiguous means it does not go in".

## Mechanism

- tier: 2 (targeted unhook)
- phase: `admin_init`, priority 999
- vendor registers at: `Admin_Notices::__construct()` (`core/admin/admin-notices.php:1144`).
  The component is created in `Admin::__construct()` (`core/admin/admin.php:1004`),
  which runs from `Plugin::init()` (`includes/plugin.php:731`, inside an `is_admin()`
  guard), itself hooked at `add_action( 'init', [ $this, 'init' ], 0 )`
  (`includes/plugin.php:826`). So anything on `admin_init` is comfortably late enough.
- instance reachable via: `\Elementor\Plugin::$instance->admin->get_component( 'admin-notices' )`
  — `$admin` is a public property (`includes/plugin.php:218`) and `get_component()`
  is public on the module base (`core/base/module.php:202`). No `$wp_filter` walk
  required.

## Drift check

Re-check when a new Elementor version appears in the vault:

1. `core/admin/admin-notices.php` — does `$plain_notices` still hold the same IDs?
   New IDs are new nags; a removed `api_upgrade_plugin` would remove the collateral
   argument entirely.
2. `admin-notices.php:1144` — still `add_action( 'admin_notices', [ $this, 'admin_notices' ], 20 )`?
   A changed priority silently breaks the `remove_action()`.
3. `core/admin/admin.php:1004` — still `add_component( 'admin-notices', ... )`?
4. `core/base/db-upgrades-manager.php` — are the upgrade notices still on their own
   callbacks? If Elementor ever folds them into `$plain_notices`, **this rule must be
   withdrawn immediately**.

Point 4 is the one that matters. Check it first.

## The promotions module — added in 1.12.0

`modules/promotions/` is a separate surface from `core/admin/admin-notices.php`, and the
1.0.0 rule does not touch it. It holds three banner classes, each constructed and
discarded by `modules/promotions/module.php`:

| Class | Hook | Where it renders |
|---|---|---|
| `Conversion_Banner` | `in_admin_header` priority 11 | **WordPress dashboard** and Elementor's own pages |
| `Pointers\Black_Friday` | `admin_print_footer_scripts-index.php` | Dashboard |
| `Pointers\Birthday` | `admin_print_footer_scripts-index.php` | Dashboard |

`Conversion_Banner` renders *"Build more with Elementor Pro — Add the theme builder,
popup builder, and 85+ advanced widgets"* with an **Upgrade now** button linking to
`go.elementor.com/go-pro-wp-admin-upgrade-notice/`. `Black_Friday` is a seasonal sale
banner, which is this project's canonical example of what to remove.

### Why this needed the `$wp_filter` reader

All three mechanisms were established as unavailable before the exception was used:

- **Mechanism 1 — no filter.** `modules/promotions/` contains exactly two
  `apply_filters` calls, both in `admin-menu-items/go-pro-promotion-item.php`, for the
  admin *menu* item's text and URL. `Conversion_Banner::should_display_banner()` reads
  `Utils::has_pro()` and a user-meta dismissal flag; neither is filterable, and faking
  the flag would be a database write
- **Mechanism 2 — instance discarded.** `module.php:90` is `new Conversion_Banner();`
  with no assignment. Nothing retains it, and there is no singleton accessor. The two
  pointer classes are the same
- **Mechanism 3 — not a dashboard widget.** It is `in_admin_header` output

### The timing trap

`wp-admin/admin.php` calls `do_action( 'admin_init' )` at line 180 and
`set_current_screen()` at line **217**. `Conversion_Banner` only adds its
`in_admin_header` callback from a `current_screen` handler, so **at `admin_init`
priority 999 the callback does not yet exist**. This rule runs on `current_screen`
priority 999 instead — the first rule in the project to need a phase other than
`admin_init` or `wp_dashboard_setup`.

### The assets are dequeued separately

Removing `render_banner_container` stops the markup, but the banner's stylesheet and
script are enqueued by an **anonymous** callback added in the same method, which nothing
can unhook by name. They are dropped with `wp_dequeue_style()` and `wp_dequeue_script()`
on handle `e-conversion-banner`, at `admin_enqueue_scripts` priority 999.

Without that, the page still carried a stylesheet and two script tags for a banner that
no longer renders. Verified: `e-conversion-banner` occurrences went 12 → 4 with the
unhook alone, and 12 → **0** once the assets were dequeued.

### Collateral

The banner also renders on Elementor's own admin pages, and this removes it there too.
That is beyond the project's remit rather than against it — nothing operational is lost,
and there is no way to separate the dashboard occurrence from the vendor-screen ones.

## Mechanism 3 — the dashboard widget

- tier: 3 (dashboard widget removal)
- phase: `wp_dashboard_setup`, priority 999
- vendor registers at: `Admin::register_dashboard_widgets` on `wp_dashboard_setup` at
  default priority 10, so our 999 runs after both the registration and the reorder
- context: `normal`. `wp_add_dashboard_widget()` is called with three arguments and the
  widget is then moved within `$wp_meta_boxes['dashboard']['normal']['core']`

Removing the meta box means `elementor_dashboard_overview_widget()` never runs, so the
`Api::get_feed_data()` call — and the `wp_remote_get` behind it — never happens.

### Widget drift check

- `core/admin/admin.php` — the `e-dashboard-overview` widget ID and the `normal` context
- **Any new filter around the News & Updates section**: if one appears, withdraw this rule
  and use it, restoring the Recently Edited list

## Additions to `headwall-nag-cleanup.php`: Elementor promotional notices, and the overview widget

```php
[
	'widget_id' => 'e-dashboard-overview',
	'context'   => 'normal',
	'vendor'    => 'Elementor 4.2.4',
	'reason'    => 'Elementor Overview; News & Updates feed, and takes Recently Edited with it',
],
```


```php
// Elementor 4.2.4 — remove the single callback that prints all eleven
// $plain_notices. Nine are promotional; api_upgrade_plugin and
// local_google_fonts_disabled are accepted collateral (see docs/plugins/elementor.md).
// Database upgrade and version-failure notices are on separate callbacks and survive.
remove_action( 'admin_notices', [ $admin_notices_component, 'admin_notices' ], 20 );
```
