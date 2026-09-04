# Handoff — building the scaffold and tier 1

Seed document for the first development session. Read `README.md` and `CLAUDE.md`
first; this document assumes both and does not repeat them.

**Current state:** empty repository. `README.md` and `CLAUDE.md` exist. No code.

## Goal for the first session

A working `nag-cleanup.php` that:

1. Loads safely as an mu-plugin and does nothing harmful if every rule is disabled
2. Implements the phase scheduler and the rule registry
3. Implements tier 1 (vendor filters) end to end
4. Implements inspect mode, because it is how every subsequent rule gets written
5. Ships with a small number of tier 1 rules, each verified against real source

Tiers 2, 3 and 4 are **not** in this session. Design the registry so they slot in,
then stop.

## Build order

1. **File skeleton** — header block, `ABSPATH` guard (check the spelling), admin-only
   bail-out, namespace, constants, class definition, single instantiation
2. **Rule registry** — the static array and its schema (below), plus loading of
   `NAG_CLEANUP_DISABLED_RULES` and the `nag_cleanup_disabled_rules` filter
2. **Phase scheduler** — group rules by phase, register at the right hooks and
   priorities, dispatch to the tier handler
3. **Suppression log** — an in-request record of what was removed, plus
   `NAG_CLEANUP_DEBUG` writing to `error_log()`
4. **Inspect mode** — `?nag-cleanup=inspect`, `manage_options` only
6. **Tier 1 handler** — the simplest of the four; mostly `add_filter()` with
   `__return_false` / `__return_empty_array` and vendor constants
7. **Tier 1 rules** — verified against the corpus, one documentation page each
8. **Report screen** — deferred if time is short, but the log must exist from day one

## Rule schema — decided

Rules are **pure data**. No closures anywhere in the registry: `apply` is a list of
declarative descriptors, and every callback is named. See `CLAUDE.md` for why this
is a hard rule rather than a preference.

```php
[
    'id'       => 'embedpress-promotions',        // stable, kebab-case, never reused
    'plugin'   => 'embedpress/embedpress.php',    // basename, for the active check
    'label'    => 'EmbedPress promotional notices',
    'verified' => '4.2.4',                        // vendor version confirmed against
    'docs'     => 'docs/rules/embedpress.md',
    'apply'    => [
        [
            'type'     => 'filter',
            'hook'     => 'embedpress_show_admin_notices',
            'callback' => '__return_false',
        ],
        [
            'type'     => 'filter',
            'hook'     => 'embedpress_admin_notices',
            'callback' => '__return_empty_array',
            'priority' => 100,
        ],
    ],
]
```

`apply` is always a list, even for a single descriptor. One logical suppression is
one rule — EmbedPress needs two filters to silence one category of nag, and a user
disabling the rule expects both to stop. Do not split them into two rules.

### Descriptor types

`type` is the dispatch discriminator and selects the handler. `tier` is **derived**
from it through a single type-to-tier map, not stored on the rule — two fields that
can disagree is a bug waiting to happen.

```php
// Tier 1 — the vendor's own filter. The common case.
[ 'type' => 'filter', 'hook' => '...', 'callback' => '__return_false',
  'priority' => 10, 'args' => 1 ]

// Tier 1 — a vendor opt-out constant.
[ 'type' => 'constant', 'name' => 'SOME_PLUGIN_DISABLE_NOTICES', 'value' => true ]

// Tier 2 — remove notice callbacks originating in a named plugin directory.
[ 'type' => 'unhook', 'hooks' => [ 'admin_notices' ], 'source' => 'embedpress/' ]

// Tier 3 — report named notices as already dismissed, without writing anything.
[ 'type' => 'virtual-dismissal', 'meta_key' => 'elementor_admin_notices',
  'notice_ids' => [ 'rate_us_feedback', 'tracker' ] ]
```

Only `filter` and `constant` are implemented in session one. Define the other two
in the map so the shape is fixed, and have the dispatcher log and skip an
unimplemented type rather than failing silently.

### Callbacks

For tier 1, `callback` is nearly always one of WordPress core's named return
helpers, which covers the overwhelming majority of vendor opt-out filters:

```
__return_false   __return_true   __return_null
__return_zero    __return_empty_array   __return_empty_string
```

Where a rule genuinely needs logic, `callback` names a public static method on a
dedicated callbacks class — `[ 'NagCleanup\\Callbacks', 'method_name' ]` — which is
documented alongside the rule. Never an anonymous function.

`phase` sits on the rule, not the descriptor, and defaults to `file-scope` for
tier 1. A rule whose descriptors need different phases is two rules.

## Verified findings — Elementor 4.2.4

Confirmed by reading the source in the corpus, not from memory. Use these as the
first worked examples; they cover most of the interesting cases in one vendor.

`core/admin/admin-notices.php` holds a **private** `$plain_notices` array:

```
api_notice, api_upgrade_plugin, tracker, tracker_last_update, rate_us_feedback,
role_manager_promote, experiment_promotion, site_mailer_promotion,
plugin_image_optimization, ally_pages_promotion, local_google_fonts_disabled
```

Applying the boundary rule to that list:

| Notice | Verdict | Reason |
|---|---|---|
| `rate_us_feedback` | suppress | Review begging |
| `role_manager_promote` | suppress | Upsell |
| `experiment_promotion` | suppress | Upsell |
| `site_mailer_promotion` | suppress | Cross-sell of a separate product |
| `plugin_image_optimization` | suppress | Cross-sell |
| `ally_pages_promotion` | suppress | Cross-sell |
| `tracker`, `tracker_last_update` | suppress | Usage-tracking opt-in prompt |
| `api_notice` | suppress | Remote-fed promotional feed |
| `api_upgrade_plugin` | **keep** | An available update — operational |
| `local_google_fonts_disabled` | **keep** | Privacy/GDPR configuration state |

Three things follow, and they are the reason this vendor is a good first target:

- `apply_filters( 'elementor/core/admin/notices', [] )` exists, but it starts
  **empty** and is for add-ons adding their own notices. It does **not** filter the
  core list above. It is not the hook we want — worth documenting so nobody
  rediscovers this the hard way.
- `$plain_notices` is private, so there is no tier 1 route to the individual core
  notices. Tier 2 (unhooking the whole `Admin_Notices` module) would take
  `api_upgrade_plugin` and `local_google_fonts_disabled` with it — which the table
  above says we must keep. **So Elementor is a tier 3 rule**, not tier 1: filter
  the dismissal lookup per notice ID.
- Elementor stores dismissal in user meta `elementor_admin_notices`, via
  `User::set_user_notice()` / `is_user_notice_viewed()` in `includes/user.php`.
  Filter `get_user_metadata` to report the named IDs as seen. **Do not write** —
  read-only virtual dismissal, per `CLAUDE.md`.

Also note `admin_notices` carries `elementor_fail_php_version` and
`elementor_fail_wp_version`. Those are exactly the notices a blanket suppressor
would destroy. Worth citing in the docs as the concrete argument for the approach.

**So Elementor is deferred to the tier 3 session** — but the analysis is done, and
it is the strongest illustration of why the tiers exist.

## Finding tier 1 rules for this session

Tier 1 needs vendors that expose a real opt-out filter. Search the corpus:

```
/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip
```

Extract the newest version of a slug, then look for the shapes that indicate a
sanctioned opt-out:

- `apply_filters` on names containing `notice`, `promo`, `upsell`, `nag`, `review`,
  `deals`, `banner`
- `defined( 'SOMETHING_DISABLE_NOTICES' )` style constants
- Freemius (`freemius/` or `fs_` prefixes) — it powers the review, opt-in and
  upsell nags in a large share of the freemium market, so **one Freemius handler
  is probably worth more than twenty individual vendor rules**. Its current opt-out
  surface is unverified; verifying it is a high-value early job

Carried over from existing Headwall code and needing verification before use:

```php
add_filter( 'embedpress_show_admin_notices', '__return_false' );
add_filter( 'embedpress_admin_notices', '__return_empty_array', 100 );
add_filter( 'eael/disable_promotions', '__return_true', 100 );
add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_false' );
```

These work in production but were written from observation, not from source. Check
each against the corpus, record the version, and note that the WooCommerce one
suppresses helper/marketplace notices — confirm it does not also hide subscription
or licence state before keeping it.

Aim for perhaps five to eight solid tier 1 rules. Breadth is not the goal in
session one; the machinery and one honest documented example of each pattern is.

## Documentation

One page per vendor under `docs/rules/`, covering: which notices are suppressed,
which are deliberately left alone and why, the mechanism and tier, the verified
version, and how to check the vendor has not moved the hook.

The "deliberately left alone" section is the important one. It is the audit trail
proving the boundary rule was applied, and on Elementor it is most of the value.

## Open questions

1. **Multisite.** `network_admin_notices` is in scope, but is per-site rule
   configuration needed, or is network-wide acceptable for v1? Suggest
   network-wide, revisit on demand.
2. **Report storage.** In-request logging is enough for inspect mode, but the
   report wants history. An option-backed ring buffer costs a write on admin
   loads — probably throttle behind a transient, or make history opt-in.
3. **Inspect mode as a rule generator.** Should it emit a paste-ready registry
   stub for an unrecognised notice? Very good for contribution flow, but risks
   people submitting rules they have not thought about. Lean yes, with the
   boundary-rule test printed alongside the output.
4. **Dashboard widgets** are in v1 scope but not session one. `wp_dashboard_setup`
   plus `remove_meta_box`, same registry, probably a fifth tier or a rule `type`.
   Decide which when the first widget rule is written.

## Out of scope for v1

Admin menu "Upgrade to Pro" entries, admin-bar nodes, `plugin_row_meta` upsells.
All are reachable by the same registry later; none are in v1.
