# Yoast SEO

- slug: `wordpress-seo`
- version analysed: `28.4`
- source: `/vault/backups/wordpress/plugins/wordpress-seo/wordpress-seo,28.4.zip`
- licensing: freemium (free on wordpress.org, Premium and addons sold at yoast.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5).

Yoast SEO is the most-installed third-party plugin on the fleet at 198 of 248 sites, and
was expected to be the largest audit of the day. It is not. **Yoast puts nothing
promotional in the WordPress admin notice area or on the dashboard.**

Everything Yoast shows globally is operational. Its promotional content — and there is
some — renders only inside Yoast's own admin screens, which this project does not
touch. Two candidate rules were designed, tested against a live install, and both
withdrawn on evidence.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 10. Nine operational; one onboarding notice classified `keep`. One `all_admin_notices` — the notification centre |
| Vendor opt-out filters | `yoast_notifications_before_storage` only, and it is on the persistence path, not display |
| Vendor opt-out constants | None |
| Dashboard widgets | 2, **both site data** (see below) |
| Outbound calls from widgets | None from either widget |
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
| `wpseo-dashboard-overview` widget | `wp_dashboard_setup` | keep | "Posts Overview" — SEO scores for the site's own posts |
| `wpseo-wincher-dashboard-overview` widget | `wp_dashboard_setup` | keep | Only renders when the owner has actively connected Wincher |
| Premium upsell + 5-star request | — | **no rule: dead code** | `WPSEO_Product_Upsell_Notice` is never instantiated |
| WooCommerce SEO cross-sell | notification centre | **no rule: vendor's own screens** | Renders only inside Yoast admin pages |

## Deliberately left alone

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
- vendor registers at: `WPSEO_Suggested_Plugins::add_notifications` on `admin_init` 10,
  from an instance created and discarded in `WPSEO_Admin_Init::load_plugin_suggestions()`
- instance reachable via: `Yoast_Notification_Center::get()` — a public singleton, so the
  notification *was* reachable. The rule was withdrawn on scope, not on reachability

## Drift check

Re-check when a new version appears in the vault:

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

## Additions to `headwall-nag-cleanup.php`: NONE
