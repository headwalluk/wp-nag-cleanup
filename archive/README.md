# wp-nag-cleanup

A single-file WordPress mu-plugin that clears upsell banners, review-begging and
promotional clutter out of the admin notice area — so the notices that actually
matter become visible again.

> **Status: early development.** The design is settled; the code is not written yet.
> See [`HANDOFF.md`](HANDOFF.md) for the build plan.

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

## What it does

`wp-nag-cleanup` suppresses **named** promotional notices from **named** plugins,
using each vendor's own documented hooks wherever they exist. It is a curated list,
not a filter — every suppression is a deliberate, documented, version-checked
decision about one specific piece of vendor output.

It also removes promotional dashboard widgets by the same mechanism.

Everything it suppresses is recorded and visible in a report, so nothing disappears
silently and you can always find out what was removed and why.

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

Four mechanisms, in order of preference. Every rule declares which one it uses.

| Tier | Mechanism | Notes |
|------|-----------|-------|
| 1 | The vendor's own filter or constant | Always preferred. Stable, documented, sanctioned. |
| 2 | Targeted unhooking | Walk the registered `admin_notices` callbacks, resolve each to its defining file, remove the ones belonging to a named plugin. |
| 3 | Virtual dismissal | Filter the "has this user dismissed it?" lookup so a named notice reports as already seen. Read-only — writes nothing to the database. |
| 4 | Output buffering | Last resort, opt-in only. Fragile and expensive. Discouraged. |

Load order matters and is handled for you: tier 1 filters must be registered early,
tier 2 removals must run late (an mu-plugin loads *before* the plugins whose hooks
it needs to remove), and different vendors register at different points. Rules
declare their phase and the loader schedules them.

## Installation

Download `nag-cleanup.php` and pick one:

**As an mu-plugin** (recommended — loads first, cannot be deactivated by accident):

```
wp-content/mu-plugins/nag-cleanup.php
```

**From a child theme's `functions.php`:**

```php
require_once get_stylesheet_directory() . '/nag-cleanup.php';
```

**From within an existing plugin:**

```php
require_once __DIR__ . '/nag-cleanup.php';
```

No build step, no Composer, no dependencies.

## Configuration

Defaults are safe and the plugin works with no configuration. To adjust it, use
constants in `wp-config.php` or filters — there is deliberately no settings page,
because a tool that exists to reduce admin clutter should not add an admin menu
entry of its own.

```php
// Turn off individual rules.
define( 'NAG_CLEANUP_DISABLED_RULES', 'elementor-rate-us,wpforms-upsell' );

// Log every suppression to the PHP error log.
define( 'NAG_CLEANUP_DEBUG', true );
```

```php
// Or from PHP:
add_filter( 'nag_cleanup_disabled_rules', function ( $rule_ids ) {
    $rule_ids[] = 'elementor-rate-us';
    return $rule_ids;
} );
```

## Seeing what was removed

Nothing is suppressed silently.

**Report** — a summary of what has been removed on this site, which rule did it,
and which tier it used.

**Inspect mode** — `?nag-cleanup=inspect` lists *every* notice callback currently
registered on the site, resolved to its originating plugin and file. Use it to
discover nags this project does not yet know about.

Inspect mode output is the ideal contents of a new-rule issue.

## Contributing

Rules are data. Adding one is a single entry in the rule registry plus a short
documentation page, and every rule must:

1. Pass the promotion-versus-information test above.
2. Name the vendor plugin and the exact version it was verified against.
3. Prefer the lowest-numbered tier that works.
4. Be a no-op when the target plugin is not installed or not active.

Run inspect mode on a site showing the nag and open an issue with the output.

See [`CLAUDE.md`](CLAUDE.md) for the full engineering rules.

## Licence

GPL-2.0-or-later, matching WordPress.
