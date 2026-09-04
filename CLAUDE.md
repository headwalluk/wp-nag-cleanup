# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What this project is

`wp-nag-cleanup` is a single-file WordPress mu-plugin that suppresses promotional
clutter in the WordPress admin notice area and on the dashboard. Read `README.md`
first — the positioning in it is not marketing copy, it is a design constraint.

The deliverable is one file, `headwall-nag-cleanup.php`, that a user drops into
`wp-content/mu-plugins/`. No build step, no Composer, no dependencies.

It is a **fire-and-forget drop-in**. It must work correctly with zero
configuration, and it must never be the reason a site breaks. When a choice
presents itself between more coverage and less risk, take less risk.

`archive/` holds the original README and handoff document from before the design
was simplified. They are superseded. Do not treat them as a specification — the
tier model, the rules-as-data registry, the inspect mode and the report screen
described there were all deliberately dropped as over-engineering.

## The boundary rule — hard constraint

**This project does not defeat, bypass or nullify premium software.**

Every rule must pass this test before it is written:

> Does this notice tell the site owner something true and actionable about the
> state of their site?

If yes, it stays — **even when it is commercial**. A licence notice is operational
information, not a nag: a premium plugin that has stopped receiving security
updates because the licence lapsed is a hosting problem, and hiding that would
make this tool dangerous.

Never suppress, under any circumstances:

- Licence expiry, invalid licence, "activate to receive updates"
- Database schema / data migration prompts
- PHP or WordPress version warnings
- Security advisories, vulnerability notices
- Plugin conflict, missing dependency, or fatal error notices
- Site Health criticals
- Anything emitted by WordPress core

Suppress only content with no operational value: review begging, seasonal sales,
cross-selling other products, newsletter sign-ups, usage-tracking opt-in prompts,
"you have been using this for N days", generic "upgrade to Pro" banners.

When a rule is ambiguous, it does not go in. Precision over coverage — this is a
scalpel, not a filter, and one wrongly suppressed schema-update prompt does more
damage than fifty surviving nags.

If a change would ever require touching the vendor's own settings screens, locked
panels or upgrade tabs, stop. That is out of scope by construction.

## Architecture

**One file, one class.** Rules are hook registrations grouped into named methods
by vendor. There is no registry, no descriptor schema and no dispatcher — if a new
vendor seems to need machinery rather than a hook line in a method, that is a
signal the rule is too clever and probably should not be written.

- Namespace `Headwall_Nag_Cleanup`, constants `HEADWALL_NAG_CLEANUP_*`
- Guard with `defined( 'ABSPATH' ) || die();` — check that spelling, it is easy to
  typo and the failure mode is a silent blank page on every request
- Admin only. Bail early on front end, AJAX, REST and cron
- Never a blanket `remove_all_actions()` on any notice hook
- Never walk `$wp_filter` removing whatever looks promotional
- Keep it well under ~1000 lines

### Double-include guard — verified behaviour, get this right

The file can be loaded from `mu-plugins/`, from a theme, and from inside another
plugin, so it must tolerate being included twice. Tested on PHP 8.5:

- An **unconditional top-level `class`** followed by a `return` guard **does not
  work**. PHP early-binds the class when the include is compiled, so
  `class_exists()` is already true the *first* time the guard runs, the file
  returns, and the plugin silently never boots
- An **unconditional top-level `function`** is fatal on the second include
  (`Cannot redeclare function`) before any guard gets the chance to run

So the guard **wraps** the declarations; it does not precede them, and there are no
top-level functions at all:

```php
if ( ! class_exists( __NAMESPACE__ . '\\Plugin' ) ) {
	class Plugin { /* ... */ }
	Plugin::boot();
}
```

Use `__NAMESPACE__ . '\\Plugin'` rather than a hand-written string — a typo in a
literal class name makes the guard silently never match.

### The three mechanisms

Rules use one of three mechanisms. Always prefer the earliest one that works.

1. **Vendor opt-out hook** — sanctioned and stable, nearly always
   `add_filter( 'vendor_hook', '__return_false' )`. Registers at file scope
2. **Targeted unhook** — `remove_action()` naming the exact hook, callback and
   priority. Must run *late*
3. **Dashboard widget removal** — `remove_meta_box()` on `wp_dashboard_setup`,
   naming the widget ID. This also prevents the outbound HTTP request the widget
   would have made on render, which is a large part of why the rule exists

### Load order

This is the main source of bugs. An mu-plugin loads *before* regular plugins, so
`remove_action()` at file scope silently does nothing — the target hook does not
exist yet.

- Mechanism 1 filters: register at file scope
- Mechanism 2 unhooks: `init` or `admin_init`, high priority number, whichever the
  vendor's own registration actually requires
- Mechanism 3: `wp_dashboard_setup` (and `wp_network_dashboard_setup` on
  multisite), late

A rule that "does nothing" is nearly always a phase problem, not a wrong hook name.

Note that `REST_REQUEST` is not defined at mu-plugin load time, so an early bail
cannot test it — use `wp_is_json_request()`. `is_admin()` is true during AJAX, so
test `wp_doing_ajax()` explicitly.

### Debug logging

`HEADWALL_NAG_CLEANUP_DEBUG` — off by default; when on, `error_log()` a line per
suppression. This is the only remaining answer to "why did I never see that
prompt?", so keep it working, but keep it small. It is not a reporting system.

## Verifying rules against real plugin source

There is a corpus of real plugin releases on this machine:

```
/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip
```

~1,477 slugs, multiple versions each. **Read from it; never write to it.** Extract
to a scratch directory. Use it to confirm every hook name, notice ID, widget ID and
meta key before writing a rule — do not guess a filter name and do not trust one
from memory or from a blog post.

Note `grep` in this environment is a `ugrep -I` shim that skips binary files; use
`command grep` when that matters.

Every rule records, in a comment, the plugin version it was verified against, so
drift is detectable when a vendor renames something.

Live sites for testing, and the deployment path to the hosting fleet, are in
`dev-notes/` — which is gitignored and stays that way.

## Coding style

PHP, matching the wider Headwall house style:

- **Single entry, single exit** where reasonable. One `return` at the bottom of a
  function. A guard clause at the top is fine; a `return` from inside a loop is not
  — set the result, `break`, and return at the end
- Array callbacks (`map`/`filter`/`find`) returning a value are functions, not
  loops, and are fine. A `.forEach()`-style bare `return` to skip an item is a
  `continue` in disguise — write the loop properly
- **No silent exception handling.** No empty `catch`, no `catch { /* ignore */ }`.
  Every `catch` rethrows or logs and leaves a durable trace
- Enumerate no-op branches explicitly with a comment saying why no action is taken,
  rather than letting conditions fall through invisibly
- **Descriptive names, no single-character identifiers**, anywhere, ever
- **Hook callbacks are named functions or named static methods. Never closures.**
  Beyond being the house style, this is load-bearing: an anonymous function cannot
  be passed to `remove_filter()`, so a closure makes this plugin's own hooks
  unremovable by anyone debugging a site. WordPress core's `__return_false` /
  `__return_true` / `__return_empty_array` and friends cover nearly every
  mechanism 1 rule; anything more goes in the plugin class as a public static
  method
- Data-driven output over long strings of concatenation
- Lean on nothing. A dependency must earn its place against the code it saves;
  in this project nothing has earned it

## Things not to do

- Do not add a settings page. Configuration is constants. A tool that removes admin
  clutter must not add an admin menu entry — this has been decided, do not
  re-propose it
- Do not add an inspect mode, a report screen, or a suppression log beyond the
  debug constant. These were considered and cut. Re-proposing them is re-proposing
  the fragility this rewrite existed to remove
- Do not add a build step, Composer, or split the distributable into multiple files
- Do not add telemetry, update checks, or any outbound network call. This plugin
  never phones home. Removing other people's outbound calls is the point of it
- Do not use blanket suppression as a shortcut for a vendor that is hard to target
- Do not add a rule you have not verified against real plugin source
