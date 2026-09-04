# EmbedPress

- slug: `embedpress`
- version analysed: `4.6.5`
- source: `/vault/backups/wordpress/plugins/embedpress/embedpress,4.6.5.zip` (57 versions held, 4.0.5 – 4.6.5)
- licensing: freemium (EmbedPress Pro sold separately, **not held in the vault**)
- Freemius bundled: no

## Analysis

Analysed on 4 Sep 2026 by Claude Code (Claude Opus 5).

**EmbedPress 4.6.5 has no suppressible promotional notices.** It ships a complete
review-and-upsell notice framework, but that framework is never instantiated: it is
dead code in this release. Every notice EmbedPress actually registers is either
operational or a closure belonging to another mechanism.

This analysis also **invalidated two rules that were already shipping** in
`headwall-nag-cleanup.php` 0.1.0. See *Correction* below.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 6 found — see Findings |
| Vendor opt-out filters | None. 38 matches were all false positives on `embedpress/google_reviews/*`, where "review" is a **content feature** (embedding Google Reviews), not review-begging |
| Vendor opt-out constants | None applicable (`EPGC_NOTICES_VERIFY_SUCCESS`, `EMBEDPRESS_GOOGLE_REVIEWS_API_KEY` — neither is a notice switch) |
| Dashboard widgets | None |
| Outbound calls from widgets | N/A, no widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook / location | Verdict | Reason |
|---|---|---|---|
| `EmbedPress_Notice::upsale_notice` | `EmbedPress_Notice.php:207` | **dead code** | Upsell nag, but the class is never instantiated — see below |
| `EmbedPress_Notice::admin_notices` | `EmbedPress_Notice.php:210` | **dead code** | Review-begging framework, same |
| `Helper::show_license_admin_notices` | `Helper.php:1525` | keep | Licence state. Boundary rule: never suppressed |
| `Analytics_Manager::display_cleanup_notice` | `Analytics_Manager.php:341` | keep | "Your analytics database may contain redundant data" — a database maintenance prompt with an action link |
| Google Calendar result notices | `Embedpress_Google_Helper.php:467` | keep | Per-user operational results of an action the user just took; also a closure |
| `ep_admin_notices` re-emitter | `Shared.php:207` | keep | Not a notice; a wrapper. Also a closure |

### The upsell framework is dead code

`EmbedPress\Includes\Classes\EmbedPress_Notice` is WPDeveloper's shared notice
library — review begging on a timer, plus cross-sell of other WPDeveloper plugins.
It registers `admin_notices` at `EmbedPress_Notice.php:207` and `:210`.

It is never constructed. `new EmbedPress_Notice` appears nowhere in the plugin, and
the only reference to the class in the entire codebase is an unused `use` statement
at `Includes/Traits/Shared.php:10`.

No rule is written for it. There is nothing to remove, and a rule targeting an
uninstantiated class would be exactly the unverified guesswork this project exists
to avoid. If a future release wires it up, the drift check below will catch it.

## Deliberately left alone

**`Helper::show_license_admin_notices`** (`Helper.php:1525`) — licence validity and
Pro feature state. This is the canonical example of the boundary rule: commercial in
origin, operational in content. Never suppressed.

**`Analytics_Manager::display_cleanup_notice`** (`Analytics_Manager.php:341`) —
prompts to clean up redundant analytics data after 30 days, linking to the cleanup
screen. It is a nudge, and a case could be made that it is noise. It stays: it
describes the actual state of the site's database and offers a concrete action, and
ambiguous rules do not go in.

**Google Calendar per-user notices** (`Embedpress_Google_Helper.php:467`) — messages
queued into a per-user option and shown once after a calendar action, then deleted.
Operational feedback, not promotion. Registered as a **closure**, so it could not be
removed with `remove_action()` even if we wanted to.

### Vendor behaviour worth knowing about

`Shared.php::remove_admin_notice()` (line ~197) has EmbedPress calling
`remove_all_actions('admin_notices')` and `remove_all_actions('user_admin_notices')`
on its own two admin screens, then re-emitting only its own notices through
`ep_admin_notices`.

That is precisely the blanket suppression `CLAUDE.md` forbids us from doing, applied
by the vendor to everyone else's notices. On `toplevel_page_embedpress` and
`embedpress_page_embedpress-analytics`, a site owner sees **no** admin notices —
including database upgrade prompts and security warnings from any other plugin.

Nothing for us to act on; it is the vendor's own screen, and out of scope by
construction. Recorded because it is a genuine site-safety observation, and because
it explains any future report of "notices vanish on the EmbedPress page".

## Correction: two shipped rules were invalid

Version 0.1.0 shipped these, inherited from the pre-existing fleet mu-plugin on the
provenance "works in production":

```php
add_filter( 'embedpress_show_admin_notices', '__return_false' );
add_filter( 'embedpress_admin_notices', '__return_empty_array', 100 );
```

**Neither hook exists.** Sampling every 7th release across all 57 versions held
(4.0.5 through 4.6.5) returns zero occurrences of either name anywhere in the PHP
source. They are not vendor hooks, and they have never fired.

They were harmless — a filter on a hook nobody applies costs nothing — but they were
false provenance in the README, the changelog and the code, which is worse than
having no rule at all. **Removed in 0.1.1.**

Caveat, recorded honestly: the vault holds only the free plugin. EmbedPress Pro is a
separate package and is not held, so this analysis cannot prove the hooks are absent
there. If a Pro release ever lands in the vault, re-check before concluding.

This is the first thing the `/analyse-plugin` runbook caught, and the reason the
"verified against real source" rule exists.

## Mechanism

- tier: N/A — no rule
- phase: N/A
- vendor registers at: `init` (`EmbedPress_Notice::init()`, if it were ever constructed)
- instance reachable via: N/A

## Drift check

Re-check when a new EmbedPress version appears in the vault:

1. `EmbedPress/Includes/Classes/EmbedPress_Notice.php` — is the class **instantiated**
   yet? Search for `new EmbedPress_Notice`. If it is, the review and upsell nags go
   live and this plugin needs a real rule.
2. `EmbedPress/Includes/Traits/Shared.php` — does the unused `use` statement at
   line 10 become a real usage?
3. Any new `wp_add_dashboard_widget(` call — there were none in 4.6.5.

Point 1 is the one that matters.

## Additions to `headwall-nag-cleanup.php`: NONE

No rule added. Two invalid rules **removed** — see *Correction* above.
