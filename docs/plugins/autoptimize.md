# Autoptimize

- slug: `autoptimize`
- version analysed: `3.1.15.1`
- source: `/vault/backups/wordpress/plugins/autoptimize/autoptimize,3.1.15.1.zip`
- licensing: freemium (free on wordpress.org, Autoptimize Pro sold via autoptimize.com)
- Freemius bundled: **no** — see below

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5).

Autoptimize is on 166 fleet sites, second only to Yoast among third-party plugins. It
registers nine `admin_notices` callbacks and no dashboard widget. Two of the nine are
promotional, and **both are gated to Autoptimize's own settings screens**, so neither is
in scope. Everything that renders elsewhere in the admin is operational.

**Correction to the fleet survey.** `dev-notes/fleet-plugin-survey.md` recorded
Autoptimize as bundling Freemius, and that was the main reason M4 was prioritised. It
was a false positive: the only match in the whole plugin is a `checkout.freemius.com`
purchase URL inside the Pro tab's marketing copy (`classes/autoptimizeProTab.php:108`).
There is no Freemius SDK here.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 9. Seven operational, two promotional but scoped to the vendor's own screens |
| Vendor opt-out filters | `autoptimize_filter_main_imgopt_plug_notice`, `autoptimize_filter_main_show_pagecache_notice`, `autoptimize_filter_main_imgopt_issue_notice` — all available, none needed |
| Vendor opt-out constants | None. `AO_PRO_VERSION` is a presence check, not an opt-out |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | **Not bundled** |

## Findings

Every callback is a **static method reference passed as a string**
(`'autoptimizeMain::notice_plug_imgopt'`), so any of these would be a trivially clean
mechanism 2 unhook — no instance to reach. Reachability is not the constraint here;
scope is.

| Item | Callback | Verdict | Reason |
|---|---|---|---|
| ShortPixel image optimisation plug | `autoptimizeMain::notice_plug_imgopt` | **no rule: vendor's own screens** | Promotional, but gated on `is_ao_settings()` |
| No page cache detected | `autoptimizeMain::notice_nopagecache` | **no rule: vendor's own screens** | Mixed — genuine advice with an AO Pro plug appended. Gated on `is_ao_settings()` |
| ShortPixel cannot reach your site | `autoptimizeMain::notice_imgopt_issue` | keep | Operational: images are silently not being optimised |
| Incompatible plugin | `autoptimize_incompatible_admin_notice` | keep | Plugin conflict |
| Potential conflict | `autoptimizeMain::notice_potential_conflict` | keep | Conflicting minification in another cache plugin |
| Cache unavailable | `autoptimizeMain::notice_cache_unavailable` | keep | Cache directory not writable — a real site fault |
| Cache size warning | `autoptimizeCacheChecker::show_admin_notice` | keep | Operational |
| Just installed | `autoptimizeMain::notice_installed` | keep | One-time post-activation guidance: test the frontend |
| Just updated | `autoptimizeMain::notice_updated` | keep | One-time: "test your site now and adapt config if needed" |

## Deliberately left alone

### The two promotional notices are scoped to Autoptimize's own settings

`notice_plug_imgopt` reads *"Did you know that Autoptimize offers on-the-fly image
optimization (with support for WebP and AVIF) and CDN via ShortPixel?"*, and
`notice_nopagecache` appends *"or consider **Autoptimize Pro** which not only has page
caching but also image optimization…"* to otherwise reasonable advice.

Both conditions include `$_is_ao_settings_page`, from:

```php
public static function is_ao_settings() {
    return ( str_replace( array( 'autoptimize', 'autoptimize_imgopt', 'ao_critcss', 'autoptimize_extra',
        'ao_partners', 'ao_pro_boosters', 'ao_pro_pagecache', 'ao_protab' ), '', $_SERVER['REQUEST_URI'] )
        !== $_SERVER['REQUEST_URI'] );
}
```

So they render only on URLs containing an Autoptimize page slug. Confirmed on a live
install:

| Screen | ShortPixel plug | AO Pro link |
|---|---|---|
| Dashboard (`index.php`) | no | no |
| Plugins (`plugins.php`) | no | only the author URI in the plugin's own list row |
| `options-general.php?page=autoptimize` | **yes** | **yes** |

The `plugins.php` hit needed checking rather than counting: it is
`By <a href="https://autoptimize.com/pro/">Frank Goossens (futtta)</a>` in the plugin
list row, not a notice. Zero `ao-nopagecache` or `ao-img-opt-plug` dismissible markers
appear on that page.

Third audit in a row to land here. The vendor's own interface is out of scope by
construction.

### `notice_nopagecache` is mixed output anyway

Even if it were in scope, it would not be a clean removal. The notice tells a site owner
their site appears to have no page caching, which is true and actionable, and *then*
suggests Autoptimize Pro. Suppressing it would take a genuine performance warning with
it. The vendor provides `autoptimize_filter_main_show_pagecache_notice` for anyone who
wants it gone; this project does not use it.

### The install and update notices

*"Thank you for installing and activating Autoptimize. Your site is being optimized
immediately, please test the frontend…"* and *"Autoptimize has just been updated. Please
test your site now."*

Kept. Both are one-time, neither names a paid product, and both tell the site owner
something true and immediately actionable — a plugin that changes frontend output has
just started or changed behaviour. On a hosting fleet that is a useful prompt, not a nag.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: `autoptimizeMain::instance()` on `plugins_loaded`, adding static
  string callbacks to `admin_notices`
- instance reachable via: not needed — every callback is a static string, so
  `remove_action()` could name any of them directly. Declined on scope, not reachability

## Drift check

Re-check when a new version appears in the vault:

- `classes/autoptimizeUtils.php` — `is_ao_settings()`. If either promotional notice stops
  consulting it, or the function widens, they become in-scope targets and
  `autoptimize_filter_main_imgopt_plug_notice` / `autoptimize_filter_main_show_pagecache_notice`
  are the mechanism 1 rules
- `classes/autoptimizeMain.php` — the `$_is_ao_settings_page` term in the
  `notice_plug_imgopt` and `notice_nopagecache` conditions
- If Freemius is ever genuinely bundled, re-audit — this analysis assumes it is not

## Verification

Tested on `bench2.local` (WP 7.1) with Autoptimize 3.1.15.1 active, over authenticated
admin requests. No rule deployed; the checks establish none is needed. Zero PHP fatals.

## Additions to `headwall-nag-cleanup.php`: NONE
