# wp-nag-cleanup

A single-file WordPress mu-plugin that clears upsell banners, review-begging and
promotional clutter out of the admin notice area and the dashboard — so the
notices that actually matter become visible again.

Drop it in and forget about it. No settings page, no build step, no dependencies,
no configuration required.

> **Status: stable.** Version 1.14.0. The machinery is complete, all three mechanisms
> work end to end, and every rule has a source audit behind it. The rule set is
> deliberately small and grows one audited vendor at a time.
> See [`CHANGELOG.md`](CHANGELOG.md).

## The problem

On a WordPress site running a normal complement of plugins, the admin notice area
fills up with things that are not notices:

- "Enjoying our plugin? Leave us a 5-star review!"
- "Black Friday — 50% off, this week only"
- "Check out our other plugins"
- "Help us improve by allowing usage tracking"
- "You've been using X for 30 days"

Site owners learn to scroll past the whole region without reading it. That is
banner blindness, and it is actively dangerous, because the same region carries
messages the site owner genuinely needs to act on: a plugin requiring a database
schema update, an expiring licence that will stop security updates, a PHP version
warning, a fatal plugin conflict.

The nags train people to ignore the warnings.

The dashboard has the same problem with an extra edge to it. Promotional and RSS
dashboard widgets are not merely noise — they make outbound HTTP requests when the
dashboard renders, to fetch a vendor's blog feed, deals feed or "news". Those
requests can carry the site URL, the installed plugin list, licence state and
whatever else the vendor decided to attach. A widget nobody reads should not be
phoning a third party every time an administrator loads the dashboard.

## What it does

`wp-nag-cleanup` suppresses **named** promotional notices from **named** plugins,
using each vendor's own documented hooks wherever they exist, and removes **named**
promotional and RSS dashboard widgets.

It is a curated list, not a filter. Every suppression is a deliberate, documented
decision about one specific piece of vendor output, verified against that vendor's
actual source.

## What it does *not* do

**This is not a tool for defeating, bypassing or nullifying premium software.**
If that is what you are looking for, this is the wrong project, and pull requests
in that direction will be rejected.

The distinction the project draws is between promotion and information:

**A licence notice is operational information, not a nag.**

A premium plugin that has stopped receiving security updates because its licence
lapsed is a hosting problem, and that is precisely the notice a site owner most
needs to see. Hiding it would make this tool actively dangerous on the sites it
was built to protect.

So `wp-nag-cleanup` will never suppress:

- Licence expiry, licence invalid, or "activate your licence to receive updates"
- Database schema update required
- PHP or WordPress version warnings
- Security advisories and vulnerability notices
- Plugin conflict, dependency and fatal error notices
- Site Health critical issues
- Anything emitted by WordPress core

It also stays entirely out of the vendor's own interface. Greyed-out Pro features
on a settings screen, "upgrade" tabs, locked panels — those are the vendor's
business and are out of scope by construction. This project only ever touches the
admin notice area and the dashboard.

### WooCommerce is out of scope

`wp-nag-cleanup` does not touch WooCommerce, and will not.

Not because there is nothing to remove, but because WooCommerce cannot be cleaned up
by the means this project allows itself. Some of its tracking carries on regardless of
the setting that claims to disable it, and there is no hook to filter — so removing it
means editing WooCommerce's own files, version by version. That is a fundamentally
different tool: it needs a multi-file patch set, it has to track upstream releases, and
it cannot be a single drop-in file that keeps working when the vendor updates.

That work lives in a separate project:

**[headwalluk/woocommerce-debloat](https://github.com/headwalluk/woocommerce-debloat)** —
performance and privacy patches to debloat WooCommerce.

It applies the same boundary rule as this project: telemetry, marketplace upsells and
remote-install endpoints go; subscription validation, licence state and plugin updates
stay.

## How it works

One file, one class. Three mechanisms, in order of preference.

### 1. Vendor opt-out hooks

Where a vendor provides a documented switch, use it. This is nearly always a
one-line `add_filter()` against a core return helper, registered at file scope:

```php
add_filter( 'eael/disable_promotions', '__return_true', 100 );
add_filter( 'yith_plugin_fw_show_dashboard_widgets', '__return_false' );
```

Sanctioned, stable, and cannot break anything the vendor did not intend to be
switchable. Always prefer this where it exists.

### 2. Targeted unhooking

Where a vendor provides no switch, remove the specific callback that prints the
nag with `remove_action()`, naming the exact hook, callback and priority.

This has to run *late*. An mu-plugin loads before regular plugins, so a
`remove_action()` at file scope silently does nothing — the target hook does not
exist yet. Unhooking happens on `init` or `admin_init` at a high priority number,
whichever the vendor's own registration requires.

Only ever a named callback on a named hook. Never a blanket `remove_all_actions()`
on a notice hook, and never a walk over `$wp_filter` removing whatever looks
promotional — that is how a database migration prompt gets destroyed.

#### The one `$wp_filter` exception

Some vendors register a notice from an object they then throw away:

```php
new WPB_WPS_Review_Notice();   // return value discarded
```

`remove_action()` matches an object callback by `spl_object_hash()`, so there is
nothing to name — an instance you construct yourself will not match. The only way to
reach it is to read `$wp_filter`.

**One** private method reads `$wp_filter` — `remove_discarded_instance_callback()`,
which takes a hook, a class and a method. It is bounded:

- It matches **one class and one method** by `instanceof`, and inspects no content.
  That is categorically different from the banned pattern, which removes callbacks
  because they *look* promotional
- It guards on `instanceof \WP_Hook` and no-ops with a debug log if `$wp_filter` is
  not the shape WordPress has used since 4.7
- It scans every priority, so a vendor changing priority does not silently kill it
- If nothing matches it logs and does nothing

Two rules use it: WPB Product Slider's review notice, and Elementor's promotions
module. Each has its own write-up, and each had to establish that mechanisms 1 to 3
were all unavailable first — a bar that has failed more often than it has passed. See
[`docs/plugins/wpb-woocommerce-product-slider.md`](docs/plugins/wpb-woocommerce-product-slider.md)
and [`docs/plugins/elementor.md`](docs/plugins/elementor.md).

### 3. Dashboard widget removal

`remove_meta_box()` on `wp_dashboard_setup`, naming the widget by ID.

This also stops the outbound request, which is most of the point: a widget that
never renders never fetches, and the dashboard JavaScript that would have gone
back to `admin-ajax.php` for a feed is never emitted either. It does not seal the
AJAX endpoint itself — `wp_ajax_dashboard_widgets()` dispatches without consulting
`wp_dashboard_setup` — but nothing triggers it once the widget is gone.

Core's own "WordPress Events and News" widget also makes an outbound call, but it
is core output and so is left alone by default. There is a constant to remove it
if you want that (see Configuration).

Three vendors use this mechanism as of 1.8.0: Premium Addons for Elementor, CSS
Hero and WooCommerce Lottery, none of which offers a switch. YITH used it until 1.0.0,
when the vendor's own opt-out filter was found and the rule moved up to mechanism 1 —
which is the order of preference working as intended.

## What it suppresses today

Version 1.14.0. Every vendor rule below was verified against that vendor's real source
and has a written analysis in [`docs/plugins/`](docs/plugins/).

| Vendor | Verified against | Mechanism | What goes |
|---|---|---|---|
| Essential Addons for Elementor | 6.8.3 | 1 | Promo banner, two dashboard widgets, seasonal pointer |
| YITH — all plugins, via `plugin-fw` | 4.7.8 | 1 | Two `yithemes.com` RSS dashboard widgets, and their script and style enqueue |
| WP Desk — all plugins, via `ltv-dashboard-widget` | Flexible Invoices 6.2.27 | 1 | "Grow your business with WP Desk" dashboard widget, and its `wpdesk.net` catalogue fetch |
| WP Desk — all plugins, via `wp-wpdesk-tracker` | Flexible Invoices 6.2.27 | 1 | Usage-tracking opt-in notice, deactivation survey, activation redirect, weekly payload |
| Brainstorm Force — Astra, Spectra et al, via `bsf-analytics` | Astra Pro 4.13.8, Spectra 2.20.3 | 1 | Usage-tracking payload. Licence and database-migration notices deliberately preserved |
| ThemeIsle — all plugins, via `themeisle-sdk` | Menu Icons 0.13.24 | 1 | "WordPress Guides/Tutorials" dashboard widget, and its two RSS feed fetches |
| CookieYes | 3.5.5 | 1 | Review request, web-app connect banner, and the WebToffee AccessiYes cross-promotion banner |
| Elementor | 4.2.4 | 2, 3 | Nine promotional notices, the "Elementor Overview" dashboard widget, and the promotions module (Go Pro banner, Black Friday, Birthday) |
| WPB Product Slider for WooCommerce | 2.4 | 2 | Five-star review notice (the one `$wp_filter` exception) |
| Forminator | 1.57.2 | 2 | "Pro Form Templates" dashboard promo, review request |
| Premium Addons for Elementor | 4.11.102 | 2, 3 | Review nag, Angie and Connect-AI upsells, "Premium Addons News" widget and its `premiumaddons.com` fetch |
| CSS Hero | 5.1.0 | 3 | "From the CSS Hero world" RSS widget |
| WooCommerce Lottery (wpgenie) | 1.1.21 | 3 | "wpgenie.org - Our latest themes and plugins" RSS widget |
| WP Swings — Gift Cards Lite, Subscriptions | 3.2.10, 2.0.2 | 2 | Remotely-driven seasonal offer banners (`wps-offer-notice`) |
| ElementsKit Lite (Wpmet) | 4.0.2 | 2, 3 | "Wpmet Stories" widget, rating nag, Go Pro notice, remote banner, EmailKit cross-sell |
| QuadLayers — all plugins, via their Composer packages | Insta Gallery 5.0.8 | 2, 3 | "QuadLayers News" widget, review nag and cross-install promos |
| WordPress core | 7.1 | 3 | "WordPress Events and News" widget — **opt-in only**, off by default |
| WordPress core | 7.1 | 2 | Dashboard "Welcome" panel — **opt-in only**, off by default |

The two core entries are the ones that are not vendor nags. Both are off unless you
turn them on, and both are documented under [Configuration](#configuration) rather
than in `docs/plugins/`, which covers third-party plugins.

The YITH and WP Desk rules are worth singling out. `plugin-fw` is a framework bundled
inside every YITH plugin, free and premium, and `ltv-dashboard-widget` and
`wp-wpdesk-tracker` are Composer packages bundled inside every WP Desk plugin. In
both cases a single filter covers the whole vendor — including plugins from that
vendor you install in future.

The list is short on purpose. A rule that has not been read out of the vendor's own
source does not go in — and one that has been read out and found wanting comes back
out again. EmbedPress shipped two rules in 0.1.0 and both were **removed** in 0.1.1:
the hooks they targeted do not exist in any of the 57 releases we hold. See
[`docs/plugins/embedpress.md`](docs/plugins/embedpress.md).

## The audit trail

Every plugin examined gets a committed document in [`docs/plugins/`](docs/plugins/),
whether or not it produced a rule — a plugin with no nags is a useful, permanent
result. Each document records what was found, which mechanism was chosen, **what was
deliberately left alone and why**, and a drift check naming the file and symbol to
re-read when the vendor ships a new version.

That last section is the important one. It is the evidence that the
promotion-versus-information test was actually applied rather than merely asserted,
and it is how the question "why did I never see that prompt?" gets an answer.

## Installation

Download `headwall-nag-cleanup.php` and pick one:

**As an mu-plugin** (recommended — loads first, cannot be deactivated by accident):

```
wp-content/mu-plugins/headwall-nag-cleanup.php
```

**From a child theme's `functions.php`:**

```php
require_once get_stylesheet_directory() . '/headwall-nag-cleanup.php';
```

**From within an existing plugin:**

```php
require_once __DIR__ . '/headwall-nag-cleanup.php';
```

The file guards against being loaded twice, so a site that has it in more than one
place will not fatal.

## Configuration

There is no settings page, deliberately: a tool that exists to reduce admin
clutter should not add an admin menu entry of its own. Defaults are safe and the
plugin is intended to work with no configuration at all.

Everything below is optional, and goes in `wp-config.php`:

```php
// Log every suppression to the PHP error log. Off by default.
define( 'HEADWALL_NAG_CLEANUP_DEBUG', true );

// Also remove core's "WordPress Events and News" dashboard widget, which fetches
// from api.wordpress.org on every dashboard load. Off by default, because it is
// core output rather than a vendor nag.
define( 'HEADWALL_NAG_CLEANUP_REMOVE_CORE_DASHBOARD_WIDGETS', true );

// Also remove core's dashboard "Welcome" panel. Off by default, because it is core
// output rather than a vendor nag. Worth setting on client sites: it takes the full
// width at the top of the dashboard, and site operators reliably do not realise the
// Dismiss link is there.
define( 'HEADWALL_NAG_CLEANUP_REMOVE_WELCOME_PANEL', true );
```

Both core constants are off by default on purpose. The boundary rule says core output
is never suppressed, and a fire-and-forget drop-in has to honour that without being
told. Turning one on is the site owner overriding that for their own site, which is a
different thing from the plugin deciding to.

The Welcome panel is removed with `remove_action( 'welcome_panel', 'wp_welcome_panel' )`
— the removal core itself documents at `wp-admin/index.php:194`. Because that leaves
no callback on the hook, core's `has_action()` guard also drops the panel wrapper and
the **"Welcome" checkbox in Screen Options**. Nobody can toggle it back from the UI
while the constant is set; unset the constant and the checkbox returns. A plugin that
hooks `welcome_panel` itself is unaffected — only core's callback is removed.

## Adding a rule

Rules live in named methods on the plugin class, grouped by vendor. Adding one
means adding a hook line to the right method, and every rule must:

1. Pass the promotion-versus-information test above.
2. Be verified against the vendor's actual source, not from memory or a blog post.
3. Name the vendor plugin version it was verified against, in a comment.
4. Prefer the earliest mechanism in the list above that works.
5. Be a no-op when the target plugin is not installed or not active — which a
   filter on a hook nobody fires already is.
6. Have a document in [`docs/plugins/`](docs/plugins/) recording the analysis,
   including what was found and left alone.

Working in Claude Code, `/analyse-plugin <slug>` runs the whole process: it extracts
the release, works a fixed search checklist over it, classifies each finding against
the boundary rule, and writes the document from
[`docs/plugins/_TEMPLATE.md`](docs/plugins/_TEMPLATE.md).

When a rule is ambiguous, it does not go in. Precision over coverage: this is a
scalpel, not a filter, and one wrongly suppressed schema-update prompt does more
damage than fifty surviving nags.

## Licence

GPL-2.0-or-later, matching WordPress.
