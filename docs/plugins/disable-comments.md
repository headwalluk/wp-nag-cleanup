# Disable Comments

- slug: `disable-comments`
- version analysed: `2.9.0`
- source: `/vault/backups/wordpress/plugins/disable-comments/disable-comments,2.9.0.zip`
- licensing: free
- Freemius bundled: no

## Analysis

Analysed on 11 Sep 2026 by Claude Code (Claude Opus 5).

Three `admin_notices` registrations, one of which is a review prompt. The vendor provides a
**documented filter** for exactly that prompt, so this is a one-line mechanism 1 rule — the
cheapest and safest kind in the project.

Worth recording that this vendor behaves well: the prompt is gated to the plugin's own
screens, it fires only after the user has actually deleted comments (not on activation), and
there is a filter to turn it off. The rule is taken because it costs nothing, not because
the vendor is misbehaving.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` registrations | 3 — `discussion_notice`, and `review_prompt` on both hooks |
| Vendor opt-out filters | **`disable_comments_show_review_prompt`** — documented in-code, used |
| Vendor opt-out constants | **None** |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Review prompt | `admin_notices` + `network_admin_notices` → `Disable_Comments::review_prompt` | **suppress** | Review begging, via the vendor's own filter |
| Discussion settings notice | `admin_notices` → `Disable_Comments::discussion_notice` | **keep** | Tells the owner their Discussion settings are overridden by this plugin — true, actionable, and easily confusing if hidden |
| Review dismiss AJAX | `wp_ajax_disable_comments_dismiss_review` | **keep** | The vendor's own dismiss path |

## Deliberately left alone

### `discussion_notice`

Explains on `options-discussion.php` that Disable Comments is overriding the settings the
owner is looking at. Without it, the Discussion screen silently lies about the site's
behaviour. Operational, and arguably the most useful notice the plugin produces.

## Mechanism

- tier: **1** (vendor opt-out hook)
- phase: file scope, in `register_vendor_optouts()`
- vendor registers at: the filter is read inside `should_show_review_prompt()`, which
  `review_prompt()` calls on render
- instance reachable via: N/A
- priority: default. Nothing in the plugin registers its own callback on this filter

The vendor documents it directly above the call:

```php
/**
 * Filter whether the review prompt may be shown at all.
 *
 * @param bool $show Whether to consider showing the prompt.
 */
if (!apply_filters('disable_comments_show_review_prompt', true)) {
```

It governs that one prompt and nothing else — which is what separates it from the
stored-setting switches rejected in `docs/plugins/wpforms-lite.md` and
`docs/plugins/astra-theme.md`.

### A note on "vendor's own screens"

`should_show_review_prompt()` bails unless `is_own_screen()`, and the vendor's own comment
says *"Our screens only. Never the dashboard, never the post editor, never a site-wide admin
notice."* Elsewhere in this project — CartFlows' NPS survey, WPForms' `promote_wpforms`,
ShapedPlugin's cross-sells — promotion confined to vendor screens has been left alone.

This one is suppressed anyway, and the distinction is **mechanism risk, not location**.
Those cases needed unhooking or a library-wide filter, where the cost of being wrong was
real. Here the vendor supplies a single-purpose boolean filter built for this exact use, so
the risk is nil and the line costs nothing.

The practical value is admittedly low — you only see this prompt when visiting the Disable
Comments settings screen. Recorded honestly rather than overstated.

## Drift check

- `disable-comments.php` — `should_show_review_prompt()` and the
  `disable_comments_show_review_prompt` filter. If the filter is removed, this becomes a
  mechanism 2 rule against `Disable_Comments::review_prompt`, and the instance **is**
  reachable via `Disable_Comments::get_instance()`, so no `$wp_filter` reader would be needed
- If `is_own_screen()` is ever widened to the dashboard, the priority of this rule rises
  sharply — it would then be a site-wide nag

## Verification

Bench: `bench2.local`, WP 7.1, Disable Comments 2.9.0.

**Gates opened:** the prompt requires a recorded successful bulk delete, so
`disable_comments_review_trigger` was set to `{"at": <1 hour ago>, "deleted": 42}`, and the
`disable_comments_review_dismissed` user meta confirmed unset. Captured on
`options-general.php?page=disable_comments_settings`, one of the plugin's own screens.

### Review prompt — **Confirmed**

| Check | Before | After |
|---|---|---|
| `disable-comments-review-prompt` on the settings screen | 2 | **0** |

(Two matches is the notice `div` plus its `id` attribute on the same element.)

Mechanism 1 rules cannot log a suppression — the vendor reads the filter and `__return_false`
is core's callback, not ours — so the before/after capture is the only evidence, which is why
it was taken on the exact screen the prompt renders on.

### Negative checks

| Check | Result |
|---|---|
| `Disable_Comments::discussion_notice` still on `admin_notices` | **yes** |
| Settings screen renders | 200 |
| PHP fatals / parse errors | **0** |

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 1

```php
// In register_vendor_optouts():
add_filter( 'disable_comments_show_review_prompt', '__return_false' );
```
