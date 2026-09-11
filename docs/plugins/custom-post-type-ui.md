# Custom Post Type UI

- slug: `custom-post-type-ui`
- version analysed: `1.19.3`
- source: `/vault/backups/wordpress/plugins/custom-post-type-ui/custom-post-type-ui,1.19.3.zip`
- licensing: freemium (CPT UI Pro is separate)
- Freemius bundled: no

## Analysis

Analysed on 11 Sep 2026 by Claude Code (Claude Opus 5).

One `admin_notices` registration, and it is a Pro upsell. It is worth more attention than
its install count suggests, because **one of its two branches renders outside the vendor's
own screens** — on the post-list screen of any public custom post type, which is a screen
site owners use in ordinary work.

The rule is the simplest kind in the project: a plain named function at an explicit
priority.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 1 — `cptui_pro_upsell_notification` at priority 11 |
| Vendor opt-out filters | **None** |
| Vendor opt-out constants | **None**. `class_exists( 'CPTUI_Pro' )` short-circuits it, but that is the Pro plugin, not a switch |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Pro upsell, CPT UI screens | `admin_notices` 11 → `cptui_pro_upsell_notification` | **suppress** | Upsell on the Add/Manage Post Types and Taxonomies tabs |
| Pro upsell, post-type list screens | same callback | **suppress** | Same function's second branch: `$screen->base === 'edit'` and the post type is public and non-builtin. **Not a vendor screen** |
| Dismiss handling | `cptui_user_dismissed_pro_upsell()` / `cptui-user-dismissed-pro-upsell` option | **keep** | The vendor's own dismiss state; untouched |

### The second branch is the reason this rule matters

`cptui_pro_upsell_notification()` does two things in one function. The first branch is a
normal vendor-screen upsell. The second:

```php
if ( $screen->base === 'edit' && ! empty( $_GET['post_type'] ) && in_array( $_GET['post_type'], $public, true ) ) {
```

puts a Pro upsell on `edit.php?post_type=<anything public and custom>`. On a WooCommerce
site that is the **Products** list. On a site using CPT UI for its actual purpose, it is
every content type the owner created.

A first pass that only read the top of the function would have classified this as
vendor-screens-only and left it. Reading to the end of the function changed the verdict.

## Deliberately left alone

Nothing else — this plugin has exactly one notice and no widgets. The dismiss machinery is
untouched, so a site owner who dismissed the upsell before this rule arrived keeps that
state, and one who has not is simply never shown it.

## Mechanism

- tier: 2 (targeted unhook, **by name**)
- phase: `admin_init` at `self::LATE_PRIORITY`
- vendor registers at: `inc/utility.php:1308`, at file scope on include — so the callback
  exists long before `admin_init`
- instance reachable via: N/A — it is a plain function, not a method

```php
remove_action( 'admin_notices', 'cptui_pro_upsell_notification', 11 );
```

**The priority must be 11.** `remove_action()` matches on hook, callback *and* priority; the
default 10 would silently remove nothing and look like success — the same class of failure as
the static-name case trap recorded in `docs/plugins/easy-fancybox.md`.

## Drift check

- `inc/utility.php` — the `add_action( 'admin_notices', 'cptui_pro_upsell_notification', 11 )`
  line. **If the priority changes, the rule silently stops working.** The rule guards with
  `has_action()` before removing, so a changed priority shows up as no debug line on a site
  that has the plugin
- The second branch's `$screen->base === 'edit'` condition — if it widens further, this
  becomes a higher-priority rule
- `cptui_add_new_pro_upsell_messaging()` / `cptui_post_type_list_pro_upsell_messaging()` — if
  either ever carries operational content alongside the upsell, the rule becomes mixed-output
  and must be withdrawn

## Verification

Bench: `bench2.local`, WP 7.1, Custom Post Type UI 1.19.3, WooCommerce active.

**No time gate.** The upsell shows immediately on a qualifying screen.

Captured on `edit.php?post_type=product` — the second branch, and the one that matters.
A first attempt on `edit.php?post_type=cartflows_step` produced nothing in either build;
that post type did not satisfy the vendor's `get_post_types( [ '_builtin' => false, 'public' => true ] )`
lookup, so the capture proved nothing and was discarded rather than reported.

### Pro upsell — **Confirmed**

Structural probe on the wrapper `cptui_admin_notices_helper()` emits, `<div id="message">`,
rather than on the marketing copy.

| Check | Before | After |
|---|---|---|
| `id="message"` on `edit.php?post_type=product` | 1 | **0** |
| `has_action( 'admin_notices', 'cptui_pro_upsell_notification' )` | `true` | **`false`** |
| Debug log | — | `custom-post-type-ui: Removed cptui_pro_upsell_notification from admin_notices priority 11.` |

An earlier measurement counted the string "Upgrade to Pro" and scored 4 → 4. Those four
matches were **WPForms and WP Mail SMTP sidebar widgets** on the same screen, not CPT UI.
Content greps mislead; the structural probe is the one reported.

### Negative checks

| Check | Result |
|---|---|
| Product list screen renders | 200, `id="the-list"` present |
| Other vendors' rules unaffected on the same bench | yes |
| PHP fatals / parse errors | **0** |

### Live fleet check — attempted, inconclusive

Paul checked the fleet sites carrying this plugin on 11 Sep 2026, before 1.26.0 was
deployed, and **no instance of this notice was showing**. That is neither confirmation nor
refutation of the rule: the vendor's gates (a public non-builtin post type must exist and CPT UI Pro must not be installed) are not met on those particular sites,
so there was nothing to see either way.

Status therefore stands as **bench-confirmed, not live-confirmed** — the notice was
observed rendering before the rule and absent after on `bench2.local`, but has not yet been
seen suppressed on a production site. Promote this to live-confirmed only when an actual
instance is observed gone in the wild, and record the site and date as
`docs/plugins/brainstorm-force.md` does.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_vendor_notices():
$this->unhook_cptui_pro_upsell();
```
