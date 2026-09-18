# Copy & Delete Posts

- slug: `copy-delete-posts`
- version analysed: `1.5.6` (module history checked back to `1.4.6`)
- source: `/vault/backups/wordpress/plugins/copy-delete-posts/copy-delete-posts,1.5.6.zip`
- licensing: freemium (the premium build is a separate `copy-delete-posts-premium` plugin)
- Freemius bundled: no. Bundles Inisev's own `analyst` opt-in SDK instead
- vendor: **Inisev** (also makes Backup Migration / BackupBliss). The notices come from
  shared `Inisev\Subs` modules that every Inisev plugin ships a copy of

## Analysis

Analysed on 18 Sep 2026 by Claude Code (Claude Opus 5).

Paul reported it from a live client site: a full-width review banner, *"You've been using
the Copy & Delete Posts plugin for over 61 days now – entirely for FREE :) … maybe you can
give us a nice review"*, on every admin screen except the post editor, profile, widgets,
Customizer and site editor.

Two rules: that review banner, and a second shared module that cross-sells Backup
Migration after 30 days. The plugin's own notices about duplicating posts, its settings
screens, the `analyst` SDK and the plugin-install-screen feature notice are all left alone.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 5: `Inisev_Review::display_review`, `New_BB_Banner::display_banner`, `Inisev_Try_Out_Plugins::informativeAdminNoticeHandler`, the `analyst` SDK's `Mutator` closure, and the plugin's own tooltip/modal closure in `copy-delete-posts.php:620`. Two promotional |
| Multiline `add_action(` form | None |
| `in_admin_header` / `admin_print_footer_scripts` | None. `analyst` prints its opt-in/opt-out templates on `admin_footer` (see below) |
| Vendor opt-out filters | **None** |
| Vendor opt-out constants | None. `IRB_H_CHECK_LOADED`, `IRB_H_HTML_LOADED` and `NEW_BB_BANNER_H_HTML_LOADED` exist but are internal once-per-request flags (see Mechanism) |
| Dashboard widgets | None |
| Outbound calls from widgets | None. `analyst` talks to its own API only after an explicit opt-in |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Review banner (`#nri-slug-wrapper`) | `admin_notices` → `Inisev\Subs\Inisev_Review::display_review` | **suppress** | Review request after 30 days of use. Says nothing about the site |
| Review banner assets | `admin_enqueue_scripts` → `Inisev_Review::add_assets` | **suppress** | Enqueues `inisev-review-script`/`-style`, which only target `#nri-*` banner markup |
| Backup Migration cross-sell (`#new-bb-banner`) | `admin_notices` → `Inisev\Subs\New_BB_Banner::display_banner` | **suppress** | *"You seem to like the Duplicate Post plugin. Then you'll love Backup Migration"*, with an in-place install button. Cross-selling another product, after 30 days, when `backup-backup` is not installed |
| Cross-sell assets | `admin_enqueue_scripts` → `New_BB_Banner::add_assets` | **suppress** | `new-bb-banner-script`/`-style`, only target `.bmi-banner__*` markup |
| "Try it first" feature notice | `admin_notices` → `Inisev_Try_Out_Plugins::informativeAdminNoticeHandler` | keep | See below |
| `analyst` notices | `admin_notices` → closure in `analyst/src/Mutator.php` | keep | See below |
| Tooltip / copy modal markup | `admin_notices` → closure in `copy-delete-posts.php` | keep | The plugin's own UI, hidden markup for its duplicate modal |
| Footer carousel | `ins_global_print_carrousel` → `Inisev_Carousel::_print` | out of scope | Vendor's own settings screen only |

## Deliberately left alone

### `Inisev_Try_Out_Plugins` — the "Try it first" notice

*"New: Add a 'Try it first' button to try out plugins before installing on your site."*
It only registers when `$pagenow` is `plugin-install.php` (or `admin-ajax.php`) and the
user can `install_plugins`, and it offers a feature (a TasteWP preview button added to
plugin cards). That is a feature announcement on the one screen it applies to, not a
review request or an upsell. **Ambiguous, so no rule.** It has its own
"Not needed, I'm good" dismissal, stored in `_tifm_hide_notice_forever`.

### The `analyst` SDK

`analyst_init()` runs at file scope in the main plugin file. Its `admin_notices` output is
a closure printing `NoticeFactory` entries. The only producer is `Account::optIn()`
(*"Please confirm your email by clicking on the link we sent to …"*), which only happens
after the admin has opted in. That is true and actionable for someone who opted in. The
opt-in/opt-out modals printed on `admin_footer` are hidden until the activation or
deactivation flow opens them. A closure cannot be unhooked anyway. **No rule.**

### `Inisev_Carousel` — footer banner

Printed via `do_action( 'ins_global_print_carrousel' )` from inside the plugin's own
settings screen. **Out of scope by construction.**

## Mechanism

- tier: 2 (targeted unhook, via the sanctioned `$wp_filter` reader)
- phase: `admin_init` at `self::LATE_PRIORITY`, in `unhook_vendor_notices()`
- vendor registers at: `plugins_loaded` → `do_action( 'cdp_loaded' )` → a closure that
  runs `$review_banner = new \Inisev\Subs\Inisev_Review( … );` (skipped if
  `copy-delete-posts-premium` is installed) and `new \Inisev\Subs\New_BB_Banner( … );`.
  Each constructor adds `wp_loaded` → `init_review` / `init_new_bb_banner`. Those add the
  `admin_notices` and `admin_enqueue_scripts` callbacks when the banner's gate passes. By
  `admin_init` they are in place
- instance reachable via: **no.** `$review_banner` is a local in a closure; the
  `New_BB_Banner` return value is not kept at all. No singleton, no global

Four `remove_discarded_instance_callback()` calls, matching by class:

| Hook | Class | Method |
|---|---|---|
| `admin_notices` | `Inisev\Subs\Inisev_Review` | `display_review` |
| `admin_enqueue_scripts` | `Inisev\Subs\Inisev_Review` | `add_assets` |
| `admin_notices` | `Inisev\Subs\New_BB_Banner` | `display_banner` |
| `admin_enqueue_scripts` | `Inisev\Subs\New_BB_Banner` | `add_assets` |

**Why match by class and not by plugin.** Both classes are wrapped in
`if ( ! class_exists( … ) )`, and Copy & Delete Posts only constructs `New_BB_Banner` if
the class is *not* already loaded. With two Inisev plugins on a site, whichever loads the
module first owns the class and the instance. A class match covers that case. It also
covers every other Inisev plugin that ships these modules. **Only Copy & Delete Posts is in
the vault**: `backup-backup`, `pop-up-pop-up` and `redirect-redirection` are not there, so
their copies are unverified. Their review banner is the same shape (the image is even named
`BM-background.svg`).

### Not used, and why

- **Defining `IRB_H_CHECK_LOADED` (or `IRB_H_HTML_LOADED`, `NEW_BB_BANNER_H_HTML_LOADED`)
  ahead of the vendor.** It would work: `can_be_displayed()` returns false when the first
  is defined, and `display_*()` prints nothing when the others are. But they are
  coordination flags between Inisev plugins, so only one banner shows per request. They
  are not opt-outs. Defining another vendor's internal constant is a bet on an
  implementation detail, and it cannot log what it suppressed
- **Removing `wp_loaded` → `init_review` instead.** It works, but `IRB_H_CHECK_LOADED` is
  defined *inside* `can_be_displayed()` by the first instance that qualifies. Removing one
  plugin's `init_review` would hand the banner to a sibling Inisev plugin on the same
  request. Removing `display_review` after the gate has run leaves the flag set, so no
  sibling picks it up
- **Writing the dismissal into `_irb_h_bn_review`** (`users[uid][slug]['dismiss'] = true`).
  That writes to another vendor's data, per user, and is mechanism 4 territory for a nag
  that mechanism 2 reaches cleanly

## Drift check

- `modules/review/review.php` — still `namespace Inisev\Subs`, class `Inisev_Review`,
  `init_review()` still adds `admin_notices` → `display_review` and
  `admin_enqueue_scripts` → `add_assets`
- `modules/new-bb-banner/misc.php` — class `New_BB_Banner`, `init_new_bb_banner()` still
  adds `display_banner` and `add_assets`. The module first appears in **1.5.1**. On 1.4.6
  to 1.5.0 the two `New_BB_Banner` calls find no class and stay silent
- `copy-delete-posts.php` — if either `new` is ever stored on something reachable, **drop
  the reader and name the instance**

```bash
unzip -p /vault/backups/wordpress/plugins/copy-delete-posts/copy-delete-posts,<version>.zip \
  copy-delete-posts/modules/review/review.php copy-delete-posts/modules/new-bb-banner/misc.php \
  | command grep -n "^ *namespace\|class \|add_action"
```

## Verification

Bench: `bench2.local`, Copy & Delete Posts **1.5.6** unzipped from the vault,
18 Sep 2026. Before = 1.30.0 (HEAD), after = working copy, `HEADWALL_NAG_CLEANUP_DEBUG`
on, three warm-up requests after the deploy. Activation redirects to the plugin's own
page, absorbed with `curl -L` first.

Time gates: both banners need 30 days. `_irb_h_bn_review['copy-delete-posts']` and
`_new_bb_banner['using_since']` were both backdated 61 days with `wp option patch update`.
The cross-sell also needs `backup-backup` and `backup-backup-pro` to be absent from
`wp-content/plugins/`.

### Review banner — **Confirmed** (bench)

| Check (dashboard and plugins.php) | Before | After |
|---|---|---|
| `id="nri-slug-wrapper"` | **1** | **0** |
| `inisev-review-script` | **3** | **0** |

### Backup Migration cross-sell — **Confirmed** (bench)

| Check (dashboard and plugins.php) | Before | After |
|---|---|---|
| `id="new-bb-banner"` | **1** | **0** |
| `new-bb-banner-script` | **3** | **0** |

Debug log, one request:

```
inisev: Removed Inisev\Subs\Inisev_Review::display_review from admin_notices priority 10.
inisev: Removed Inisev\Subs\Inisev_Review::add_assets from admin_enqueue_scripts priority 10.
inisev: Removed Inisev\Subs\New_BB_Banner::display_banner from admin_notices priority 10.
inisev: Removed Inisev\Subs\New_BB_Banner::add_assets from admin_enqueue_scripts priority 10.
```

### Negative checks

| Check | Result |
|---|---|
| Screens asserted: `id="dashboard-widgets"`, `id="the-list"` | yes |
| The plugin's own `cdp` markup and assets on both screens | identical before and after (164 / 165 matches) |
| `_irb_h_bn_review` `users` | still `[]`, so no dismissal written |
| `_new_bb_banner` `dismissed` | still `false` |
| PHP fatals / warnings / parse errors | **0** |

Expected log noise: while either banner's gate is closed (the first 30 days, or after a
dismissal) and the plugin is active, the matching lines read
`… not registered on admin_notices; no action taken.` That is correct, not drift.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_vendor_notices():
$this->unhook_inisev_promos();

public function unhook_inisev_promos() : void {
	$this->remove_discarded_instance_callback( 'admin_notices', 'Inisev\\Subs\\Inisev_Review', 'display_review', 'inisev' );
	$this->remove_discarded_instance_callback( 'admin_enqueue_scripts', 'Inisev\\Subs\\Inisev_Review', 'add_assets', 'inisev' );
	$this->remove_discarded_instance_callback( 'admin_notices', 'Inisev\\Subs\\New_BB_Banner', 'display_banner', 'inisev' );
	$this->remove_discarded_instance_callback( 'admin_enqueue_scripts', 'Inisev\\Subs\\New_BB_Banner', 'add_assets', 'inisev' );
}
```
