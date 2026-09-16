# Yoast SEO

- slug: `wordpress-seo`
- version analysed: `28.4`, re-examined at `28.5`
- source: `/vault/backups/wordpress/plugins/wordpress-seo/wordpress-seo,28.4.zip`,
  `/vault/backups/wordpress/plugins/wordpress-seo/wordpress-seo,28.5.zip`
- licensing: freemium (free on wordpress.org, Premium and addons sold at yoast.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5). Re-examined on 16 Sep 2026
against 28.5, after Paul found the "Latest blog posts on Yoast.com" feed inside the
dashboard widget on a live site. The two files that matter here are byte-identical in
28.4 and 28.5.

Yoast SEO is the most-installed third-party plugin on the fleet at 198 of 248 sites, and
was expected to be the largest audit of the day. It is not. **Yoast puts nothing
promotional in the WordPress admin notice area. It does put one promotional thing on the
dashboard, and no PHP mu-plugin can reach it.**

Everything Yoast shows in the notice area is operational. Its promotional content — and
there is some — renders inside Yoast's own admin screens, which this project does not
touch, and in a yoast.com blog feed inside the `wpseo-dashboard-overview` widget, which
is fetched by the administrator's browser rather than by the server. Three candidate
rules have been designed for this plugin and all three withdrawn on evidence — one as
dead code, one after bench testing showed it changed nothing on screen, and one because
it would have taken the site's own SEO figures with it.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 10. Nine operational; one onboarding notice classified `keep`. One `all_admin_notices` — the notification centre |
| Vendor opt-out filters | `yoast_notifications_before_storage` only, and it is on the persistence path, not display |
| Vendor opt-out constants | None |
| Dashboard widgets | 2. `wpseo-wincher-dashboard-overview` is site data; `wpseo-dashboard-overview` mixes site data with a yoast.com blog feed (see below) |
| Outbound calls from widgets | None from the **server**. `wpseo-dashboard-overview` fetches `https://yoast.com/feed/widget/` from the administrator's **browser** on every dashboard load, carrying the WordPress and PHP versions |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Missing SPL / missing autoload | `admin_notices` | keep | Broken install warnings |
| Permalink settings notice | `admin_notices` → `WPSEO_Admin_Init::permalink_settings_notice` | keep | Operational configuration problem |
| WPML glue plugin missing | `admin_notices` | keep | Missing dependency |
| **Search engines discouraged** | `admin_notices` → `search-engines-discouraged-watcher` | keep | Site is set to noindex. One of the most important notices WordPress can show |
| Migration error | `admin_notices` → `migration-error-integration` | keep | Database migration failure |
| Premium deactivated | `admin_notices` → `deactivated-premium-integration` | keep | Licence/state information |
| No owned addons warning | `admin_notices` → `addon-installation/dialog-integration` | keep | Install-flow error |
| First-time configuration | `admin_notices` → `first-time-configuration-notice-integration` | keep | See below |
| `wpseo-dashboard-overview` widget | `wp_dashboard_setup` | keep | "Posts Overview" — SEO scores for the site's own posts. Carries the blog feed below, and cannot be separated from it |
| **"Latest blog posts on Yoast.com" feed** | client-side, inside `wpseo-dashboard-overview` | **no rule: unreachable from PHP** | Promotional, and would be suppressed on sight in any other vendor's widget. Rendered in the browser, in the same React root as the site data |
| `wpseo-wincher-dashboard-overview` widget | `wp_dashboard_setup` | keep | Only renders when the owner has actively connected Wincher |
| Premium upsell + 5-star request | — | **no rule: dead code** | `WPSEO_Product_Upsell_Notice` is never instantiated |
| WooCommerce SEO cross-sell | notification centre | **no rule: vendor's own screens** | Renders only inside Yoast admin pages |

## Deliberately left alone

### The Yoast.com blog feed is client-side, and shares a React root with the site data

Added 16 Sep 2026. The feed is genuinely promotional: two yoast.com articles under a
"Latest blog posts on Yoast.com" heading, every link tagged
`utm_source=yoast-seo&utm_medium=software&utm_campaign=wordpress-general&utm_content=wordpress-dashboard`,
with a "Read more like this on our SEO blog" footer link. Ten vendor feeds of exactly this
shape are already removed by mechanism 3 — CSS Hero, HasThemes, HappyAddons, Wpmet,
QuadLayers, Avada, BdThemes, Elementor, Premium Addons, WooCommerce Lottery.

**No rule, because nothing in PHP can reach it.**

`Yoast_Dashboard_Widget::display_dashboard_widget()`
(`admin/class-yoast-dashboard-widget.php:100`) echoes one element and nothing else:

```php
echo '<div id="yoast-seo-dashboard-widget"></div>';
```

Everything in the rendered widget is drawn by `js/dist/dashboard-widget.js`, a single
React root mounted on that div, whose `render()` composes both halves into one tree:

```js
render(){const e=[this.getSeoAssessment(),this.getYoastFeed()].filter(e=>null!==e);
  return 0===e.length?null:(0,a.jsx)("div",{children:e})}
```

`getYoastFeed()` renders as soon as its own fetch resolves:

```js
getFeed(){(0,i.getPostFeed)("https://yoast.com/feed/widget/?wp_version="
  +wpseoDashboardWidgetL10n.wp_version+"&php_version="
  +wpseoDashboardWidgetL10n.php_version,2).then(...)}
```

`getPostFeed` resolves to `jQuery.ajax` in `js/dist/externals/helpers.js`, so the request
is issued **by the administrator's browser**, cross-origin to yoast.com. It carries the
WordPress version with a classic-editor flag appended and the PHP major.minor, both
supplied by `localize_dashboard_script()` — the only PHP surface anywhere near the feed,
and it supplies nothing but those two versions and the header and footer strings.

The URL exists in exactly one file in the whole plugin, in 28.4 and 28.5 alike:

```
$ grep -rl 'yoast\.com/feed' .
./js/dist/dashboard-widget.js
```

Each mechanism, and why it does not apply:

| Mechanism | Why not |
|---|---|
| 1, vendor opt-out | None exists. `apply_filters` appears **zero** times in both `admin/class-yoast-dashboard-widget.php` and `admin/class-admin-asset-manager.php`, and the widget is constructed as `'dashboard_widget' => new Yoast_Dashboard_Widget()` into the unfiltered `$this->admin_features` array (`admin/class-admin.php:77`; `get_admin_features()` is a bare getter) |
| 2, targeted unhook | The class registers two callbacks — `queue_dashboard_widget` on `admin_init` and `enqueue_dashboard_assets` on `admin_enqueue_scripts`. Both govern the whole widget. Unhooking the second leaves an empty box titled "Yoast SEO Posts Overview", which is worse than the feed |
| 3, widget removal | Works, and takes the site's own SEO figures with it — see the next section |
| 4, stored notification | Nothing is stored. The feed is fetched fresh on each dashboard load. The only related storage is the `wpseo-statistics-totals` transient, which caches the site's own post counts; `Yoast_Dashboard_Widget::CACHE_TRANSIENT_KEY` (`wpseo-dashboard-totals`) is declared but never read or written |
| `pre_http_request` | **Checked and rejected — do not retry.** It short-circuits `WP_Http::request()`, and `WP_Http` never sees this URL. The 16 `wp_remote_*` calls in the plugin are the MyYoast proxy, the AI client, `tracking.yoast.com/stats` and the sitemap self-ping; not one fetches this feed |

This is the same conclusion as the `api.sigmative.io` client-side injector in
`docs/plugins/ultimate-post-kit.md`: a nag drawn in the browser lies outside what a PHP
mu-plugin can reach, and that is a property of the mechanism list rather than a gap in it.

Rank Math looks identical on screen and is not. Its feed is a `wp_remote_get` inside
`Dashboard_Widget::dashboard_widget_feed()`, a server-side callback on the vendor's own
`rank_math/dashboard/widget` action, which is why 1.24.0 could drop the feed and keep the
site's 404 and redirection figures. Yoast has no equivalent seam.

### The whole widget was not removed, and that was a decision

`remove_meta_box( 'wpseo-dashboard-overview', 'dashboard', 'normal' )` is a one-line
mechanism 3 rule and it works. It takes the feed, and the browser's call to yoast.com with
it, because the React app bails when `getElementById` returns null.

It also takes **Posts Overview**: post counts by SEO score, posts with no focus keyphrase,
posts set to noindex, each linking to the site's own filtered post list. That passes the
boundary test in `CLAUDE.md` outright — it tells the site owner something true and
actionable about the state of their site — and it is the widget's stated purpose. The box
is titled "Yoast SEO Posts Overview", not "Yoast SEO News".

Put to Paul on 16 Sep 2026 as an explicit three-way choice: remove the whole widget, write
no rule, or gate removal behind a per-vendor constant. **Decision: no rule.** Precision
over coverage — one wrongly suppressed piece of site data costs more than a surviving
two-item blog feed, on 198 sites either way.

Elementor's `e-dashboard-overview` reads as a counter-example and is not. That widget is a
News & Updates feed that happens to carry a Recently Edited list; this one is a site report
that happens to carry a feed. The ratio is the whole argument.

Reopen only if Paul asks, or if the drift check below fires.

### The Premium upsell notice is dead code

`admin/class-product-upsell-notice.php` defines `WPSEO_Product_Upsell_Notice`, which
builds a notification with the id `wpseo-upsell-notice` reading *"By the way, did you
know we also have a Premium plugin? … We'd be thrilled if you could give us a 5 stars
rating on WordPress.org!"*. On paper it is the single most obvious target in the plugin.

It is never constructed. Grepping the whole plugin for `Product_Upsell` outside the
Composer autoload maps returns exactly one hit: the `class WPSEO_Product_Upsell_Notice`
declaration itself. Nothing calls `new` on it, and the file does not self-register.

A rule for it would be a rule against a hook nobody fires — the mistake corrected for
EmbedPress in 0.1.1 and avoided again for WPB Product Slider's commented-out discount
notice.

### The WooCommerce cross-sell renders only on Yoast's own screens

This one was **built, tested, and then withdrawn**, so the reasoning is worth recording
in full.

`WPSEO_Suggested_Plugins::add_notifications` runs on `admin_init` and queues a
notification per Yoast addon whose dependencies are satisfied. Only one entry in
`WPSEO_Plugin_Availability` has a `_dependencies` key — `yoast-woocommerce-seo`,
depending on WooCommerce — so in practice there is exactly one, with the id
`wpseo-suggested-plugin-yoast-woocommerce-seo`. Its text is unambiguously promotional:

> It looks like you aren't using our **Yoast WooCommerce SEO addon**. **Upgrade today**
> to unlock more tools and SEO features to make your products stand out in search
> results.

It is typed `Yoast_Notification::WARNING`, which overstates it considerably.

A rule was written against it. `Yoast_Notification_Center::get()` is a proper singleton
with a public `remove_notification_by_id()`, so unlike the WPB and Brainstorm Force
cases the object was cleanly reachable — no `$wp_filter` needed. On the bench the rule
worked exactly as intended: the queued notification count went from 3 to 2, the other
two were untouched, `admin_notices` callback count was unchanged, and zero fatals.

**Then the rendered HTML showed it changed nothing a site owner would ever see.**

`Yoast_Notification_Center::display_notifications()`, the `all_admin_notices` callback,
filters through a method whose name is the exact opposite of what it does:

```php
private function is_notification_persistent( Yoast_Notification $notification ) {
    return ! $notification->is_persistent();
}
```

`is_persistent()` returns true when a notification has an id. So the global notice area
renders only notifications **without** an id. Every identified notification — including
this cross-sell — is excluded, and appears instead in Yoast's own alert centre inside
its admin pages.

Confirmed by fetching four admin screens with the rule disabled and grepping for the
cross-sell text:

| Screen | Cross-sell present |
|---|---|
| Dashboard (`index.php`) | no |
| Plugins (`plugins.php`) | no |
| `admin.php?page=wpseo_dashboard` | **yes** |
| `admin.php?page=wpseo_page_settings` | **yes** |

That places it squarely inside the vendor's own interface, which `CLAUDE.md` puts out
of scope by construction: *"This project only ever touches the admin notice area and the
dashboard."* A site owner sees it only after navigating into Yoast's settings, where
Yoast is entitled to advertise Yoast.

The rule was removed. It would have added a per-request notification-centre lookup and a
storage write on every admin page load of 198 sites, in exchange for changing nothing on
any screen this project claims.

### The first-time configuration notice

*"Get started quickly with the Yoast SEO First-time configuration and configure Yoast
SEO with the optimal SEO settings for your site!"* — this one **does** render in the
global notice area, and it is the closest thing to a nag Yoast puts there.

Kept, because it passes the boundary test: it reports true and actionable state — Yoast
is installed but has never been configured — and configuring it changes the site's
output. It is onboarding, not promotion; it names no paid product and links to a local
admin screen, not to yoast.com. It also stops of its own accord once the configuration
is completed or dismissed.

Ambiguous cases stay, and this one is not even especially ambiguous.

### `duplicate-post` is a separate audit

Yoast also publish Duplicate Post (82 sites), which does **not** share the notification
centre — `Yoast_Notification_Center` appears nowhere in it. It needs its own document
and has not been analysed here.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- blog feed: N/A — client-side only. No PHP surface exists to hook, filter or unhook; see
  "Deliberately left alone" above before proposing one
- vendor registers at: `WPSEO_Suggested_Plugins::add_notifications` on `admin_init` 10,
  from an instance created and discarded in `WPSEO_Admin_Init::load_plugin_suggestions()`
- instance reachable via: `Yoast_Notification_Center::get()` — a public singleton, so the
  notification *was* reachable. The rule was withdrawn on scope, not on reachability

## Drift check

Re-check when a new version appears in the vault:

- `js/dist/dashboard-widget.js` — **the feed becomes reachable the moment it moves into
  PHP.** One command answers it, run from the extracted plugin root:

  ```
  grep -rl 'yoast\.com/feed' .
  ```

  While that returns only the JS bundle, no rule is possible. If a PHP file appears — a
  `wp_remote_get`, a `fetch_feed()`, or a REST route proxying the feed — write the
  mechanism 2 rule, because the site data would then survive it
- `admin/class-yoast-dashboard-widget.php` — if the feed is split into its own
  `wp_add_dashboard_widget` id, mechanism 3 applies to that id alone and Posts Overview
  survives. If `localize_dashboard_script()` gains a gate, or the class gains any
  `apply_filters`, mechanism 1 applies. Both currently absent
- `admin/class-yoast-notification-center.php` — `is_notification_persistent()`. If the
  negation is removed, or `display_notifications()` stops filtering on it, identified
  notifications would start rendering in the global notice area and the WooCommerce
  cross-sell would become an in-scope target. This is the single most important line to
  re-read
- `admin/class-product-upsell-notice.php` — if `WPSEO_Product_Upsell_Notice` is ever
  instantiated, its `wpseo-upsell-notice` becomes a live candidate, subject to the same
  render-location test
- `admin/class-plugin-availability.php` — `_dependencies` currently appears once. More
  entries mean more suggestion notifications, all subject to the same scope reasoning
- `src/integrations/admin/first-time-configuration-notice-integration.php` — if the
  wording turns into a Premium pitch rather than a configuration prompt, revisit

## Verification

Tested on `bench2.local` (WP 7.1) with Yoast SEO 28.4 and WooCommerce 11.1.0 active, over
authenticated admin requests. A/B tested by deploying the plugin with the candidate rule
enabled and disabled:

| Check | Rule off | Rule on |
|---|---|---|
| `wpseo-suggested-plugin-yoast-woocommerce-seo` queued | YES | NO |
| Total Yoast notifications queued | 3 | 2 |
| `admin_notices` callbacks | 44 | 44 |
| Cross-sell text on dashboard / Plugins | absent | absent |
| Cross-sell text on Yoast's own screens | present | *(rule withdrawn)* |
| PHP fatals | 0 | 0 |

The rule worked. It was withdrawn because the thing it removed was never on screen in
the first place, outside Yoast's own pages.

### 16 Sep 2026 re-examination

Source only, against 28.5 — no bench work, because no rule was written and there is
nothing to A/B. The evidence for the feed is a live capture of the rendered widget from a
production site, supplied by Paul, plus the source reading above.
`admin/class-yoast-dashboard-widget.php` and `js/dist/dashboard-widget.js` are
byte-identical between 28.4 and 28.5, so the 5 Sep findings stand unchanged.

## Additions to `headwall-nag-cleanup.php`: NONE
