# wp-nag-cleanup

A single-file WordPress mu-plugin that clears upsell banners, review-begging and
promotional clutter out of the admin notice area and the dashboard — so the
notices that actually matter become visible again.

Drop it in and forget about it. No settings page, no build step, no dependencies,
no configuration required.

> **Status: early development, working baseline.** Version 0.1.0. The machinery is
> complete and the three mechanisms work end to end; the rule set is small and
> growing. See [`CHANGELOG.md`](CHANGELOG.md).

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

## How it works

One file, one class. Three mechanisms, in order of preference.

### 1. Vendor opt-out hooks

Where a vendor provides a documented switch, use it. This is nearly always a
one-line `add_filter()` against a core return helper, registered at file scope:

```php
add_filter( 'embedpress_show_admin_notices', '__return_false' );
add_filter( 'embedpress_admin_notices', '__return_empty_array', 100 );
add_filter( 'eael/disable_promotions', '__return_true', 100 );
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

## What it suppresses today

Version 0.1.0. Every entry was verified against real plugin source, and each has a
written analysis in [`docs/plugins/`](docs/plugins/).

| Vendor | Verified | How | What goes |
|---|---|---|---|
| EmbedPress | in production | 1 | Admin notices and promotional nags |
| Essential Addons for Elementor | in production | 1 | Promotions |
| Elementor | 4.2.4 | 2 | Nine promotional notices |
| YITH (`plugin-fw`) | 4.7.8 | 3 | Two RSS dashboard widgets fetching `yithemes.com` |
| WordPress core | 7.1 | 3 | "WordPress Events and News" — opt-in only |

The list is short on purpose. A rule that has not been read out of the vendor's own
source does not go in.

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
```

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
