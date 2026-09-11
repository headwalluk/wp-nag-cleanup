# Complianz GDPR/CCPA Cookie Consent

- slug: `complianz-gdpr`, and `complianz-gdpr-premium` (**no rule needed — see below**)
- version analysed: `7.5.5` free, `7.6.5` premium
- source: `/vault/backups/wordpress/plugins/<slug>/<slug>,<version>.zip`
- licensing: freemium
- Freemius bundled: no

## Analysis

Analysed on 11 Sep 2026 by Claude Code (Claude Opus 5).

Complianz has exactly two `admin_notices` registrations. One is a review request; the other
is the plugin's **compliance warning system** — the notices that tell a site owner their
cookie banner is misconfigured or their privacy statement is missing. On a consent plugin
that second category is the most operationally important output there is, and it is not
touched.

One rule, against the review request only.

### The premium build already suppresses its own nag

`complianz-gdpr-premium` ships the same `class-review.php`, but the notice is registered
inside `if ( ! defined( 'cmplz_premium' ) && ! is_multisite() )`, and
`complianz-gpdr-premium.php:353` defines `cmplz_premium`. **The vendor does not nag paying
customers**, so the premium slug needs no rule and gets none.

This corrects the 11 Sep gap analysis, which assumed the shared callback name meant shared
exposure and scored this target at 20 fleet sites. The real figure is **9** — the free
installs only.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 2 — `cmplz_review::show_leave_review_notice` and `cmplz_admin::show_admin_notice` |
| Vendor opt-out filters | **None** for the review notice. The compliance warnings honour the site owner's own `disable_notifications` setting, which is theirs to set and not ours to force |
| Vendor opt-out constants | `cmplz_premium` — defined by the premium build, not a switch |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| "Leave a review" notice | `admin_notices` → `cmplz_review::show_leave_review_notice` | **suppress** | Review begging. Free build only, after a month, until dismissed |
| Compliance warnings | `admin_notices` → `cmplz_admin::show_admin_notice` | **keep** | Cookie-banner and privacy-statement problems. The reason the plugin exists; never suppressed |
| Review dismiss AJAX | `wp_ajax_cmplz_dismiss_review_notice` | **keep** | The vendor's own dismiss path |
| Review dismiss script | `admin_print_footer_scripts` → `insert_dismiss_review` | **keep** | Left deliberately — see below |
| `process_get_review_dismiss` | `admin_init` | **keep** | Handles a dismiss link arriving by GET |

## Deliberately left alone

### The compliance warnings, and why `disable_notifications` is not touched

`cmplz_admin::show_admin_notice()` renders one queued warning at a time from
`get_warnings()`. These are statements about the site's legal configuration. Suppressing
them on a consent plugin would be the clearest possible breach of the boundary rule.

The vendor gates them on `cmplz_get_option( 'disable_notifications' )` — a site-owner
setting. Forcing it would hide the warnings, and is rejected for the same reason as
`wpforms_setting`/`hide-announcements` in `docs/plugins/wpforms-lite.md`: it is the owner's
switch, not ours.

### `insert_dismiss_review` is left hooked

Removing the notice leaves its dismiss JavaScript printing in the footer, which is dead but
harmless — a jQuery handler bound to a selector that no longer matches. It is left because
it is not a notice, it renders nothing visible, and removing it would mean a second
`remove_action()` for no user-visible gain. If a site owner ever dismisses the notice by
another route, the handler is what the vendor expects to be present.

## Mechanism

- tier: 2 (targeted unhook, **by name** — no `$wp_filter` reader)
- phase: `admin_init` at `self::LATE_PRIORITY`
- vendor registers at: `cmplz_review::__construct()`, called from `complianz-gpdr.php:383`
  as `self::$review = new cmplz_review();` at plugin load. The `admin_notices` callback is
  added **conditionally**, only when the vendor's own gate passes
- instance reachable via: **`cmplz_review::this()`** — a static accessor that returns the
  stored instance and **does not construct one**

That last point is the trap worth knowing. ShapedPlugin's `instance()` in
`docs/plugins/woo-product-slider.md` *does* construct, which is why the reader was used
there. Complianz's accessor is a plain getter:

```php
static function this() {
    return self::$_this;
}
```

so calling it can never register anything. Check which shape a singleton is before using it.

### Why "not registered" is not logged here

The vendor only adds the hook when its gate passes — free build, not multisite, activated
over a month ago, not yet dismissed. On a site where the owner dismissed the notice last
year, the callback is legitimately absent on **every** admin request.

The usual `remove_discarded_instance_callback()` would log "not registered; no action taken"
each time, which the debug-logging rules in `CLAUDE.md` specifically warn against — that is
noise, not drift. So this rule is hand-rolled and stays silent when the callback is absent.
**Do not "simplify" it back to the shared helper.**

## Drift check

- `class-review.php` — the `! defined( 'cmplz_premium' ) && ! is_multisite()` gate and the
  `add_action( 'admin_notices', ... )` inside it. If the vendor starts showing this to
  premium users, the premium slug needs its own rule
- `class-review.php` — `static function this()`. If it ever gains lazy construction,
  **switch to the `$wp_filter` reader**, as with ShapedPlugin
- `class-admin.php` — `show_admin_notice()` and `get_warnings()`. If a promotional warning
  is ever queued through the same store, this document's "keep" needs revisiting, and the
  answer is still not to unhook the renderer

## Verification

Bench: `bench2.local`, WP 7.1, Complianz GDPR 7.5.5, over authenticated admin requests.

**Gates opened:** `cmplz_activation_time` backdated 90 days (the notice requires over a
month since activation); `cmplz_review_notice_shown` confirmed unset. The bench is single
site, which the `! is_multisite()` gate requires.

### Review notice — **Confirmed**

Structural probe on the notice's own wrapper class, not its copy.

| Check | Before | After |
|---|---|---|
| `cmplz-review really-simple-plugins` on the dashboard | 1 | **0** |
| `cmplz_review::show_leave_review_notice` on `admin_notices` | `true` | **`false`** |
| Debug log | — | `complianz-gdpr: Removed cmplz_review::show_leave_review_notice from admin_notices.` |

### Negative checks

| Check | Result |
|---|---|
| `cmplz_admin::show_admin_notice` still on `admin_notices` | **yes** — the compliance warnings |
| Dashboard, plugins, WooCommerce Status screens | 200, screens asserted |
| PHP fatals / parse errors | **0** |

The premium build's suppression was verified by reading source only — no premium licence was
used, and none is needed to read the `cmplz_premium` define.

### Live fleet check — attempted, inconclusive

Paul checked the fleet sites carrying this plugin on 11 Sep 2026, before 1.26.0 was
deployed, and **no instance of this notice was showing**. That is neither confirmation nor
refutation of the rule: the vendor's gates (free build only, single site only, activated over a month ago, not yet dismissed) are not met on those particular sites,
so there was nothing to see either way.

Status therefore stands as **bench-confirmed, not live-confirmed** — the notice was
observed rendering before the rule and absent after on `bench2.local`, but has not yet been
seen suppressed on a production site. Promote this to live-confirmed only when an actual
instance is observed gone in the wild, and record the site and date as
`docs/plugins/brainstorm-force.md` does.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_vendor_notices():
$this->unhook_complianz_review_notice();
```
