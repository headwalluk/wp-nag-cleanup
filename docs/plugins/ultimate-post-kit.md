# Ultimate Post Kit (BdThemes)

- slug: `ultimate-post-kit`
- version analysed: `4.5.3`, re-read at `4.6.0` on 28 Oct 2026 (with 3.12.11,
  4.1.10–4.1.18, 4.2.0 and 4.5.10 read for history)
- source: `/vault/backups/wordpress/plugins/ultimate-post-kit/ultimate-post-kit,4.6.0.zip`
- licensing: freemium (free on wordpress.org, Pro at postkit.pro)
- Freemius bundled: no

## Analysis

Analysed on 9 Sep 2026 by Claude Code (Claude Opus 5), alongside
`bdthemes-element-pack` — read that document first, it carries the shared-infrastructure
table.

1 fleet site, running exactly 4.5.3.

**Two rules added**: the feedback-hub review nag, and the "BdThemes News & Updates"
dashboard widget. The plugin's own notices are all dependency or version notices and are
left alone.

**A third rule since 1.47.0**: the BdThemes deals banner, new in 4.5.10. See the next
section.

This slug is the reason the audit was worth doing, but not for the reason expected: the
interesting finding is **historical**, and BdThemes have already removed it upstream.

### 28 Oct 2026 — the deals banner, `BdThemes_Deals_Biggopti` (4.5.10+)

Paul pasted it from a client site: *"Build More. Spend Less. Save Up to $4,899 … Customize
your agency bundle with 14 plugins"*, a **Grab the Deal** button to `bdthemes.com/deals/`
and a 135px "Up To 86% OFF" SVG badge. It is a seasonal bundle sale with no operational
content. **Suppress.**

It is **not** the biggopti framework described below. That is still inert in 4.6.0
(`$api_url = ''`, no injector script). This is a new, self-contained file,
`includes/bdthemes-deals-biggopti.php`, first seen in **4.5.10** (absent in 4.5.9).
`ultimate-post-kit.php` requires it whenever `is_admin()`, before the plugin's own
bootstrap and **before the Elementor check**. So it renders even on a site where UPK is
otherwise inert because Elementor is missing. The bench showed exactly that.

- `final class BdThemes_Deals_Biggopti`, **all static**. `init()` runs at file load and
  adds `admin_notices` → `[ __CLASS__, 'render' ]` at priority 10. A static callback is
  named directly, so mechanism 2 needs no `find_instance_callback()`
- Its docblock says every BdThemes free plugin ships an identical copy behind
  `class_exists( 'BdThemes_Deals_Biggopti' )`, and the first copy to load registers it.
  So **one removal covers the range**, whichever plugin won the load race. Its `SCREENS`
  list names Element Pack Lite, Prime Slider, Store Kit, Pixel Gallery, ZoloBlocks, Smart
  Admin Assistant, One Accessibility, Dark Reader, AI Image and Spin Wheel. **Only UPK is
  in the vault**, so the "identical copy" claim is the vendor's, not verified. If a
  sibling forks the class name the way the feedback-hub SDK was forked, the rule misses it
  silently
- Screens: `dashboard`, `plugins` and each BdThemes plugin's own top-level page.
  `manage_options` only. No time gate
- Dismissal is site-wide: `bdthemes_dismissed_biggopties` (array of ids), id
  `bdthemes_deals_2026`. The docblock says to change the id to show a new campaign, so
  each year's sale is a fresh notice. **That is why the rule unhooks the renderer rather
  than seeding the dismissal option.** Seeding would cover one campaign id and write to
  vendor data. Unhooking covers every id and writes nothing

**Left alone: the "Up To 86% Off" admin menu entry.** 4.6.0 also adds a free-only submenu
under the UPK menu (`ultimate_post_kit_options_deals`). It is styled as a highlight, and
`admin_init` redirects it off-site to the deals page. It is a promotion, but it lives in
the vendor's own menu, and the boundary rule says to stop at the vendor's own screens and
upgrade tabs. No rule.

### `api.sigmative.io` — the remote banner channel, 4.1.10 to 4.2.0

`admin/admin-biggopti.php` ("biggopti", বিজ্ঞপ্তি, notice) is a promo-banner framework
whose content came from a remote endpoint. Between 4.1.10 and 4.2.0 it ran two routes to
the same third-party host, neither of them `bdthemes.com`:

1. **Server side.** `Biggopties::get_api_biggopties_data()` `wp_remote_get`s
   `https://api.sigmative.io/prod/store/api/biggopti/api-data-records`, then renders
   `$biggopti->content` through `wp_kses_post()`, `$biggopti->image` into a
   `background-image:` style, `$biggopti->logo` into an `<img src>` and `$biggopti->link`
   into an anchor. Reached over `wp_ajax_upk_fetch_api_biggopties`
2. **Client side.** `admin/assets/js/upk-admin-api-biggopti.min.js` — enqueued on *every*
   admin page from `Admin::enqueue_admin_script` on `admin_init` — `fetch()`es
   `https://api.sigmative.io/prod/store/api/biggopti/api-data-all-records` **from the
   administrator's own browser** and injects the result into `#wpbody-content .wrap`,
   sanitising against a tag allowlist written in the JS

Route 2 is the one that matters architecturally: **no server-side unhook can stop it.**
`remove_action()` has nothing to remove, because the request never goes through PHP. Only
preventing the script being enqueued would, and dequeuing a vendor script handle is a
fourth mechanism this plugin does not have. Recorded here as evidence for that design
question, not as a rule.

`admin_notices` was never used for any of this — the registration is commented out in
every version that carries the file. The banner has always been JS-injected.

**All of it is gone in 4.5.2 and 4.5.3**, and the removal looks deliberate:

| | 4.2.0 | 4.5.3 |
|---|---|---|
| `$api_url` | `https://api.sigmative.io/…/api-data-records` | `''` (empty) |
| `upk-admin-api-biggopti.min.js` | present, 11,765 bytes, contains the `fetch()` | **file removed** |
| `upk-biggopti.min.js` | 586 bytes, dismissal only | 586 bytes, dismissal only |
| `admin-api-biggopti.php` | present | **file removed** |
| News & Updates widget | registered unconditionally | gated on an opt-in setting, default `off` |
| `white-label/` | present | removed |

So on 4.5.3 the biggopti framework is inert: an empty URL makes `wp_remote_get` return a
`WP_Error`, the handler returns `[]`, and nothing calls the AJAX action anyway. **No rule
is written for it.** A rule against a dead code path is untestable, and the project's
own instruction is to prove the thing renders before claiming to have removed it.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | **3** — 2 dependency notices, 1 review nag from the bundled SDK |
| Vendor opt-out filters | **None** for notices. `ultimatepostkit/settings/dashboard` gates the vendor's own settings *page*, not the widget — out of scope |
| Vendor opt-out constants | `BDTUPK_WL` (white label) only |
| Dashboard widgets | **1** — `bdt-dashboard-overview`, opt-in since 4.5.2 (`bdt-upk-dashboard-overview` in 3.12.x) |
| Outbound calls from widgets | `bdthemes.com/feed` RSS. The `dashboard.bdthemes.io` product feed was dropped after 3.12.x |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Review request | `admin_notices` → `Ultimate_Post_Kit_Reviews_Collector::display_global_notice` | **suppress** | "Give us your Review", 3-day gate, links to `bdt.to/ultimate-post-kit-elementor-addons-review` |
| BdThemes News & Updates | `bdt-dashboard-overview` (context `column4`) | **suppress** | Vendor blog RSS and a "Get Pro" footer link; outbound request on render |
| Elementor missing | `admin_notices` → `ultimate_post_kit_fail_load` | **keep** | Missing dependency. Explicitly protected |
| Pro version not sufficient | `admin_notices` → `ultimate_post_kit_pro_version_not_sufficient` | **keep** | Free/Pro version mismatch. Explicitly protected |
| Biggopti remote banner | JS injection, no PHP hook | **no rule** | Inert in 4.5.3 and 4.6.0 — empty URL, injector script deleted. See above |
| BdThemes deals banner (`data-biggopti="bdthemes_deals_2026"`) | `admin_notices` → `BdThemes_Deals_Biggopti::render` (static) | **suppress** (1.47.0) | Bundle sale, "Save Up to $4,899". 4.5.10+ |
| "Up To 86% Off" menu entry | `admin_menu` submenu, `admin_init` redirect | **keep** | Vendor's own menu. Out of scope by construction |

## Deliberately left alone

**Both of the plugin's own notices are dependency notices.** `ultimate_post_kit_fail_load`
fires when Elementor is absent; `ultimate_post_kit_pro_version_not_sufficient` fires when
the installed Pro is older than the Free half's API contract. Both are the "your plugins
do not fit together" case the boundary rule protects, and both leave the site with
missing widgets if unheeded.

**The news widget is already opt-in on 4.5.3, and the rule is still worth having.** Since
4.5.2 `admin-feeds.php` only constructs `Admin_Feeds` when the `news_feed` option is
`on`, defaulting `off` — the vendor did the right thing unprompted. The `remove_meta_box`
rule therefore does nothing on a default 4.5.3 install. It is kept because the widget id
is the **generic BdThemes one** (`bdt-dashboard-overview`, title "BdThemes News &
Updates", not "Ultimate Post Kit News & Updates"), so it is the id the rest of the
BdThemes range is likely to use, and because a site owner who turned the setting on has
still opted into a vendor RSS widget rather than into an outbound request per dashboard
load. Recorded here so it is not later mistaken for an unverified rule.

**`bdt-upk-dashboard-overview`, the 3.12.x id, is not in the table.** Nothing on the
fleet runs 3.12.x, and the vault has no 3.12 → 4.1 intermediate to say when the id
changed. Precision over coverage: an id nothing renders is dead weight in a table that
runs on every dashboard load.

**The biggopti dismissal endpoints are left alone.** `wp_ajax_ultimate-post-kit-biggopties`
and `wp_ajax_upk_fetch_api_biggopties` are still registered on 4.5.3. Both are AJAX-only
hooks, and this plugin bails on `wp_doing_ajax()` by design, so they are unreachable from
here regardless — and with no banner to dismiss they do nothing.

## Mechanism

- tier: 2 (targeted unhook) for the review nag and the deals banner, 3 (dashboard
  widget) for the feed
- phase: `admin_init` at `self::LATE_PRIORITY`; `wp_dashboard_setup` for the widget
- vendor registers at: `admin_init` **priority 10** —
  `ultimate_post_kit_reviews_bootstrap` is hooked there, and the SDK constructor adds
  `admin_notices` from inside it. 999 beats 10
- instance reachable via: **nothing — `find_instance_callback()` again.**
  `ultimate_post_kit_reviews_automate()` does `new Ultimate_Post_Kit_Reviews_Collector( $params )`
  and discards it; the class's `get_instance()` is never called, so the static holder is
  null. Same shape as Element Pack, different class name

### The class name is per-plugin

This is the same file as Element Pack's `includes/feedback-hub/notice.php`, with the
class renamed from `RC_Reviews_Collector` to `Ultimate_Post_Kit_Reviews_Collector` and
the AJAX actions renamed to match. **One rule does not cover the BdThemes range** — each
plugin needs its class name read from source. That is why the plugin carries three
`remove_discarded_instance_callback()` calls for two plugins rather than one shared call.

## Drift check

- `includes/feedback-hub/notice.php` — the `Ultimate_Post_Kit_Reviews_Collector` class
  name and `display_global_notice`. This name has already changed once
- `includes/bdthemes-deals-biggopti.php` — the class name `BdThemes_Deals_Biggopti`
  (case included: a static callback is keyed by literal string), `render`, and the
  `admin_notices` registration in `init()`. A renamed class or a move to an instance
  makes the rule a silent no-op
- `admin/admin-feeds.php` — the `bdt-dashboard-overview` id, the `column4` context, and
  the `news_feed` opt-in gate. If the gate is removed the widget becomes live again
- `admin/admin-biggopti.php` — **`$api_url`. If it stops being empty, re-read this
  document.** A repopulated URL means the remote banner channel is back, and if
  `upk-admin-api-biggopti.min.js` returns with it, the client-side route is back too and
  no rule in this plugin can reach it
- `admin/assets/js/` — the reappearance of any script performing `fetch()` against a
  non-WordPress host

## Verification

### Deals banner — **Confirmed** (bench, 28 Oct 2026)

Bench: `bench1.local`, UPK **4.6.0** from the vault, Elementor **not** installed. Captured
over an authenticated admin HTTP request. Before = 1.46.0 (HEAD), after = 1.47.0 working
copy, swapped into the bench's bundled `headwall-hosting` copy (see the bench trap in
`sureforms.md`).

| Check | Before | After |
|---|---|---|
| `data-biggopti="bdthemes_deals_2026"`, `index.php` (`id="dashboard-widgets"` asserted) | **1** | **0** |
| `data-biggopti="bdthemes_deals_2026"`, `plugins.php` (`id="the-list"` asserted) | **1** | **0** |
| `has_action( 'admin_notices', [ 'BdThemes_Deals_Biggopti', 'render' ] )`, harness, before → after `admin_init` | 10 | false |
| Debug line | — | `bdthemes: Removed BdThemes_Deals_Biggopti::render from admin_notices.` |
| UPK's Elementor-missing notice (`ultimate_post_kit_fail_load`) | present | **present** |
| `bdthemes_dismissed_biggopties` option | absent | absent, so nothing written |
| PHP fatals / warnings | 0 | 0 |

**Live-confirmed 28 Oct 2026**: Paul deployed 1.47.0 to the client site where he found it and reports the banner gone.

### Review nag and news widget

**Source-verified across 3.12.11 through 4.5.3. Not yet bench-verified** — the plugin is
not installed on any bench. The review nag is gated 3 days past install
(`rc_date_<name>_installed`), so backdate that option before capturing the "before", per
`docs/plugins/wp-mail-bank.md`. The news widget needs `news_feed` set to `on` in
`ultimate_post_kit_other_settings` before it renders at all.

| Check | Expected, rule off | Expected, rule on |
|---|---|---|
| `has_action( 'admin_notices' )` entry for a `Ultimate_Post_Kit_Reviews_Collector` instance | present | gone |
| `ultimate_post_kit_fail_load` on `admin_notices` (Elementor deactivated) | present | **present** |
| `grep -c 'id="bdt-dashboard-overview"'` on `index.php`, `news_feed` on | ≥ 1 | 0 |
| PHP fatals | 0 | 0 |

## Additions to `headwall-nag-cleanup.php`: 3 rules, mechanisms 2 and 3

The deals banner (1.47.0) is the last block of `unhook_bdthemes_promos()`:

```php
$deals_callback = [ 'BdThemes_Deals_Biggopti', 'render' ];

if ( false !== has_action( 'admin_notices', $deals_callback ) ) {
	remove_action( 'admin_notices', $deals_callback );
	$this->log( 'bdthemes', 'Removed BdThemes_Deals_Biggopti::render from admin_notices.' );
}
```

The review-nag rule is the third call inside
`unhook_bdthemes_promos()` — see
`docs/plugins/bdthemes-element-pack.md` for the whole method.

```php
[
	'widget_id' => 'bdt-dashboard-overview',
	'context'   => 'column4',
	'vendor'    => 'BdThemes admin-feeds, Ultimate Post Kit 4.5.3',
	'reason'    => 'BdThemes News & Updates; fetches bdthemes.com/feed on render',
],
```
