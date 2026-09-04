---
name: analyse-plugin
description: Analyse a WordPress plugin from the vault for admin-notice nags and promotional dashboard widgets, then write a per-plugin audit document to docs/plugins/. Use when asked to analyse, audit or check a plugin for nags, or to verify a hook or notice ID against real plugin source. Takes a plugin slug.
---

# Analysing a plugin for nags

Produces one committed markdown document per plugin in `docs/plugins/<slug>.md`,
recording what was found, what was suppressed, **what was deliberately left alone**,
and why. A "nothing found" result is a valid and valuable outcome — it stops the
plugin being re-analysed forever.

Read `CLAUDE.md` first if it is not already in context. The boundary rule is not
advisory: a notice that tells the site owner something true and actionable about
their site stays, even when it is commercial.

## 1. Locate and extract

The vault holds every plugin release discovered across the hosting fleet:

```
/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip
```

**Read-only. Never write to `/vault/`.** Extract into `work/`, which is gitignored:

```bash
PLUGIN_SLUG=some-foobar-plugin
VAULT_PATH="/vault/backups/wordpress/plugins/${PLUGIN_SLUG}"
ls -1 "${VAULT_PATH}/" | sort -V | tail -5          # newest versions last
PLUGIN_ZIP=$( ls -1 "${VAULT_PATH}/"*.zip | sort -V | tail -1 )
PLUGIN_VERSION=$( basename "${PLUGIN_ZIP}" .zip | cut -d, -f2 )
WORK_PATH="work/${PLUGIN_SLUG}-${PLUGIN_VERSION}"
mkdir -p "${WORK_PATH}"
unzip -q -o "${PLUGIN_ZIP}" -d "${WORK_PATH}"
```

Prefer the newest version in the vault. If the plugin is also installed on a live
site under `/var/www/`, analysing that copy is fine and often faster — record which
source and which version was actually read.

`grep` here is a `ugrep -I` shim that skips binary files. Use `command grep` when
that matters. Exclude `node_modules/`, `/vendor/`, `.min.js` and language files from
searches — they generate noise and never register hooks.

## 2. Identity

Record from the main plugin file header: plugin name, version, and whether it is
free, freemium or premium.

## 3. The search checklist — run all of it

Run every pass, even when an early one finds something. The document has to be able
to say "we looked for X and there was none", and that is only true if X was checked.

**a. Admin notice registrations**

```bash
command grep -rn "add_action( *['\"]admin_notices\|add_action( *['\"]network_admin_notices\|add_action( *['\"]all_admin_notices" "${WORK_PATH}"
```

For each hit, read the callback. Note the hook, the callback, the priority, and
whether the callback prints one notice or many.

**b. Vendor opt-out surfaces — always look for these first**

```bash
command grep -rnE "apply_filters\( *['\"][^'\"]*(notice|promo|upsell|nag|review|deal|banner|sale|discount|tracking|opt_?in|announcement|widget|dashboard|show_)" "${WORK_PATH}"
```

A documented vendor filter is mechanism 1 and always beats unhooking.

`widget`, `dashboard` and `show_` are in that list because of a real miss: YITH's
`yith_plugin_fw_show_dashboard_widgets` contains none of the promotional words, so
the first pass over `plugin-fw` reported "no vendor opt-out" and the rule was written
as a `remove_meta_box()` instead. If a vendor gates a promotional surface at all, the
switch is often named after the surface rather than after the promotion.

Do not treat this list as complete. When passes (a), (d) or (e) find a promotional
surface, go and read the code that registers it and look for a condition wrapping the
registration — that is where an opt-out lives, whatever it is called.

**c. Opt-out constants**

```bash
command grep -rnE "defined\( *['\"][A-Z_]*(NOTICE|PROMO|UPSELL|NAG|REVIEW|BANNER|TRACKING)" "${WORK_PATH}"
```

**d. Dashboard widgets**

```bash
command grep -rn "wp_add_dashboard_widget(" "${WORK_PATH}"
```

**e. Outbound calls made by those widgets**

```bash
command grep -rn "fetch_feed\|wp_remote_get\|wp_remote_post\|get_transient.*feed" "${WORK_PATH}"
```

A widget that fetches a vendor feed on dashboard render is a strong candidate: it
is noise *and* an outbound request carrying site data. Record the exact URL.

**f. Freemius**

```bash
command grep -rln "freemius\|fs_dynamic_init\|Freemius" "${WORK_PATH}" | head
command grep -rn "'version' *=>" "${WORK_PATH}/freemius/start.php" 2>/dev/null | head -3
```

Freemius powers review, opt-in and upsell nags across much of the freemium market.
Record whether it is bundled and at what SDK version — a single Freemius finding may
retire many individual vendor rules.

## 4. Classify every finding

For each notice or widget found, apply the boundary rule test:

> Does this tell the site owner something true and actionable about the state of
> their site?

Classify as **suppress**, **keep**, or **ambiguous**. Ambiguous means keep — "when a
rule is ambiguous, it does not go in".

Watch for **mixed output**: one callback that prints both promotional and
operational notices, or a widget combining a vendor news feed with real site data.
Note it explicitly. Mixed output is usually a reason not to write the rule, and when
it is not, the collateral must be named in the document and agreed before shipping.

## 5. Choose the mechanism

Lowest that works, per `CLAUDE.md`:

1. Vendor opt-out hook or constant — file scope
2. Targeted `remove_action()` — late, on `init` or `admin_init`
3. `remove_meta_box()` on `wp_dashboard_setup`

For mechanism 2, establish **how the callback's object is reached** without walking
`$wp_filter` (which is banned). Find where the vendor stores the instance — a
singleton, a module registry, a public property — and record the exact expression.
If it cannot be reached, the rule cannot be written; say so and stop.

Also record **when** the vendor registers the hook, since that sets the phase.

## 6. Write the document

Copy `docs/plugins/_TEMPLATE.md` to `docs/plugins/<slug>.md` and fill it in. Every
field is required. `## Deliberately left alone` is the most important section —
it is the audit trail proving the boundary rule was applied.

Record the analysing model honestly (currently Claude Opus 5) and the date.

## 7. Implement, or record NONE

If there are additions, add them to `headwall-nag-cleanup.php` in the appropriate
method, with a comment naming the plugin and the version verified against. Keep the
document and the code in the same commit.

If there is nothing to add, the document still gets written and committed with
`## Additions: NONE`. That is a completed analysis, not a failure.

## 8. Clean up

Leave `work/` in place during a session; it is gitignored. Do not delete anything
from `/vault/`.
