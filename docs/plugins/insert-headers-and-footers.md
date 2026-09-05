# WPCode (Insert Headers and Footers)

- slug: `insert-headers-and-footers`
- version analysed: `2.3.9`
- source: `/vault/backups/wordpress/plugins/insert-headers-and-footers/insert-headers-and-footers,2.3.9.zip`
- licensing: freemium (free on wordpress.org, WPCode Pro sold at wpcode.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on client
sites.

**19 fleet sites** — the widest reach of any single-plugin rule in the project, behind only
Elementor. **Four rules added**, all removing *producers* rather than the plugin's notice
framework, which is left intact so anything operational still renders.

### A new phase: `EARLY_PRIORITY`

This is the first vendor whose promotional notices are **built from `admin_init`
callbacks at the default priority**. `WPCode_Review::review_request()` calls
`$this->review()` directly, and `WPCode_Features_Notices::maybe_show_notices()` adds its
`admin_notices` hook — both at `admin_init` priority 10.

The project's usual `LATE_PRIORITY` (999) is **too late**: by then the producer has
already run and queued its notice. So these are removed from `admin_init` **priority 1**,
before the producers fire, via a new `unhook_early_vendor_notices()` pass.

`EARLY_PRIORITY` is only for targets that are themselves on `admin_init`. Everything else
still uses the late pass.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 4 |
| Vendor opt-out filters | **None.** Only `wpcode_add_snippet_show_library` and `wpcode_shortcode_preview`, neither notice-related |
| Vendor opt-out constants | None |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Review request | `admin_init` 10 → `WPCode_Review::review_request` | **suppress** | Review nag |
| Review link in the footer | `admin_footer_text` 1 → `WPCode_Review::admin_footer` | **suppress** | Part of the same nag |
| "Pro Tip" upsell | `admin_init` 10 → `WPCode_Features_Notices::maybe_show_notices` | **suppress** | *"Pro Tip: Did you know all WPCode premium plans include access to a private snippet library? … Upgrade today with a special discount"* |
| Library connect promo | `admin_init` → `wpcode_maybe_add_library_connect_notice` | **suppress** | *"Connect to the WPCode Library to get access to FREE snippets!"* |
| Notice framework | `admin_notices` 999000 → `WPCode_Notice::display` | **keep** | Generic renderer — see below |
| Pro/Lite conflict | `admin_notices` → `wpcode_lite_notice` | **keep** | Registered only when WPCode Pro is active and Lite bails |
| Safe mode | `admin_notices` → `wpcode_safe_mode_notice` | **keep** | Reports the plugin is running in safe mode — real site state |
| Lite top bar / bottom notice | `wpcode_admin_page*` | keep | Render only inside WPCode's own pages |
| WPConsent cross-promo | `trait-wpcode-wpconsent-notice.php` | keep | Rendered from WPCode's own admin pages |

## Deliberately left alone

**The notice framework stays.** `WPCode_Notice::display` renders whatever
`WPCode_Notice::add()` has queued, at `admin_notices` priority 999000. Its callers are the
Pro Tip upsell and `class-wpcode-admin-page-generator.php`, and the generator's messages
are ordinary operational feedback. Removing the renderer would be blanket suppression of a
general framework — the pattern rejected for Astra's `astra-notices` and CookieYes's
dashboard widget. Removing the **producers** instead achieves the same visible result with
no collateral, and was verified: the framework is still hooked at p999000 with the rules
active.

**`wpcode_safe_mode_notice`** reports that WPCode is running in safe mode, so snippets are
not executing. That is exactly the kind of "why is my site behaving oddly" notice this
project exists to make visible.

**`wpcode_lite_notice`** is registered inside the block that runs when WPCode Pro is
present and Lite bails out (`ihaf.php` ends that block with `return;`). A plugin-conflict
notice.

**The WPConsent cross-promo and the Lite top-bar notices** render from
`wpcode_admin_page` hooks — inside WPCode's own screens. Out of scope by construction,
the same call made for Yoast, ACF, Autoptimize, ElementsKit and Essential Blocks.

## Mechanism

- tier: 2 (targeted unhook)
- phase: **`admin_init`, `self::EARLY_PRIORITY` (1)** — see above
- instance reachable via: **nothing.** Both classes end their own file with a bare
  `new WPCode_Review();` / `new WPCode_Features_Notices();`, discarding the instance. This
  is the **fifth** use of `remove_discarded_instance_callback()`
- `wpcode_maybe_add_library_connect_notice` is a **named function**, so it is removed
  directly with `remove_action()` and needs no reader

`remove_action()` is used for the `admin_footer_text` filter too; in WordPress
`remove_action()` is a straight alias for `remove_filter()` (`wp-includes/plugin.php:624`).

## Drift check

Re-check when a new version appears in the vault:

- `includes/admin/class-wpcode-review.php` — `WPCode_Review`, `review_request`,
  `admin_footer`
- `includes/admin/class-wpcode-features-notices.php` — `WPCode_Features_Notices`,
  `maybe_show_notices`
- `includes/lite/admin/notices.php` — `wpcode_maybe_add_library_connect_notice`
- **If a producer moves off `admin_init`**, it should move from the early pass to the late
  one. If a producer stays on `admin_init` but moves to a priority below 1, the early pass
  needs revisiting
- `includes/admin/class-wpcode-admin-notice.php` — `WPCode_Notice::display` must stay
  untouched, and if a promotional caller of `WPCode_Notice::add()` appears that is not one
  of the producers above, re-audit

## Verification

Tested on `bench2.local` (WP 7.1) with WPCode 2.3.9 active, A/B with the rules enabled and
disabled, over an authenticated admin request. The probe runs at `admin_init` priority
500 — after the early pass, before the late one:

| Check | Rules off | Rules on |
|---|---|---|
| `WPCode_Review::review_request` on `admin_init` | **HOOKED p10** | **gone** |
| `WPCode_Review::admin_footer` on `admin_footer_text` | **HOOKED p1** | **gone** |
| `WPCode_Features_Notices::maybe_show_notices` on `admin_init` | **HOOKED p10** | **gone** |
| `wpcode_maybe_add_library_connect_notice` on `admin_init` | **HOOKED** | **gone** |
| `WPCode_Notice::display` framework (must survive) | HOOKED p999000 | **HOOKED p999000** |
| PHP fatals | 0 | 0 |

The last row is the one that matters: the framework is untouched, so the producers were
removed rather than the renderer suppressed.

`wpcode_lite_notice` and `wpcode_safe_mode_notice` reported `n/a` in both states — WPCode
Pro is not installed on the bench and safe mode is off, so neither registers. Neither is
targeted by any rule.

## Additions to `headwall-nag-cleanup.php`: 4 rules, mechanism 2

```php
public function unhook_wpcode_promos() : void {
	$this->remove_discarded_instance_callback( 'admin_init', 'WPCode_Review', 'review_request', 'wpcode' );
	$this->remove_discarded_instance_callback( 'admin_footer_text', 'WPCode_Review', 'admin_footer', 'wpcode' );
	$this->remove_discarded_instance_callback( 'admin_init', 'WPCode_Features_Notices', 'maybe_show_notices', 'wpcode' );

	if ( false !== has_action( 'admin_init', 'wpcode_maybe_add_library_connect_notice' ) ) {
		remove_action( 'admin_init', 'wpcode_maybe_add_library_connect_notice' );
		$this->log( 'wpcode', 'Removed wpcode_maybe_add_library_connect_notice from admin_init.' );
	}
}
```
