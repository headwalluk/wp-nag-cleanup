# Essential Blocks (WPDeveloper)

- slug: `essential-blocks`
- version analysed: `6.4.3`
- source: `/vault/backups/wordpress/plugins/essential-blocks/essential-blocks,6.4.3.zip`
- licensing: freemium (free on wordpress.org, Essential Blocks PRO sold at essential-blocks.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a
client site.

2 fleet sites. **One rule added**, removing the renderer for a notice bank that contains
nothing but promotion.

WPDeveloper also publish Essential Addons for Elementor (4 fleet sites) and EmbedPress
(2), both already audited. Worth recording: **they do not share this notice library.**
Neither bundles `PriyoMukul\WPNotice` — checked by listing their newest vault releases —
so this rule covers Essential Blocks alone, and the existing `eael/disable_promotions`
rule is unaffected.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 3 |
| Vendor opt-out filters | **None.** The bundled `WPNotice` library contains no `apply_filters` at all |
| Vendor opt-out constants | None |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Campaign notice bank | `admin_notices` → `PriyoMukul\WPNotice\Utils\CacheBank::notices` | **suppress** | Renders four notices, all promotional — see below |
| Facebook token expiry | `admin_notices` → `Integrations\Facebook::render_expiry_notice` | **keep** | A real credential problem: the connected feed will stop working |
| Promotion on the vendor's page | `admin_notices` 1 → `Admin::promotion_message_on_admin_screen` | keep | Only registered when `'toplevel_page_essential-blocks' == $current_screen->id` — the vendor's own screen |

### What is in the bank

All four notices added in `Admin::notices()` are promotional, which is what makes removing
the renderer clean rather than a mixed-output problem:

| Id | What it is |
|---|---|
| `summer_campaign2026` | *"🏖️ Summer Savings: Get 70+ AI-powered blocks … now up to $150 OFF!"* with **Upgrade To Pro Now** and **Give Me LIFETIME Access** buttons |
| `early_bird` | *"🔥 Essential Blocks PRO: Get access to premium Gutenberg blocks…"* |
| `review` | Review request |
| `opt_in` | Usage-tracking opt-in |

`review` and `opt_in` are independently on the suppress list, so even judged individually
every notice in the bank qualifies.

Worth noting the dismiss buttons, which are confirmshaming rather than neutral:
*"I Don't Want Any Discount"* and *"I Don't Want To Save Money"*.

## Deliberately left alone

**The Facebook token expiry notice.** `Integrations\Facebook::render_expiry_notice` is a
separate callback on the same hook, and it reports that a connected Instagram/Facebook
feed's credentials are expiring — the feed will silently stop updating. Operational, and
verified still hooked with the rule active.

**The vendor's own admin page promotion.** `promotion_message_on_admin_screen` is added
only from `remove_admin_notice()`, and only when the current screen is
`toplevel_page_essential-blocks`. Vendor's own interface, out of scope by construction —
the same conclusion reached for Yoast, ACF, Autoptimize and ElementsKit.

## Mechanism

- tier: 2 (targeted unhook)
- phase: `admin_init`, `self::LATE_PRIORITY`. `Admin::notices()` runs on `init`, and
  constructing `Notices` instantiates `CacheBank`, whose constructor adds the
  `admin_notices` hook — so it exists well before `admin_init`
- instance reachable via: **`\PriyoMukul\WPNotice\Utils\CacheBank::get_instance()`** — a
  public static singleton. **No `$wp_filter` exception needed**, which is why this rule is
  ordinary mechanism 2 despite the vendor discarding the `Notices` object itself
- `CacheBank::scripts` is removed from `admin_footer` alongside, since it exists only to
  drive the notices

This is a good example of the habit recorded in `CLAUDE.md`: the *outer* object
(`$notices = new Notices( … )`) is a discarded local, which looks like it needs the
`$wp_filter` reader. But the object actually on the hook is the `CacheBank` singleton, and
that has a public accessor. **Check what is really on the hook before reaching for the
exception.**

## Drift check

Re-check when a new version appears in the vault:

- `includes/Dependencies/Notice/Utils/CacheBank.php` — the `get_instance()` accessor and
  the `notices` / `scripts` method names
- `includes/Admin/Admin.php` — the `$notices->add( … )` calls. **If an operational notice
  is ever added to this bank, withdraw the rule**: removing the renderer would take it too
- `includes/Integrations/Facebook.php` — `render_expiry_notice` must stay a separate
  callback
- If Essential Addons or EmbedPress start bundling `PriyoMukul\WPNotice`, re-check whether
  their banks are also promotion-only before relying on this rule covering them

## Verification

Tested on `bench2.local` (WP 7.1) with Essential Blocks 6.4.3 active, A/B with the rule
enabled and disabled, over authenticated admin requests.

**Verified structurally rather than by rendered output**, because none of the four notices
is eligible on a freshly installed bench:

- `summer_campaign2026` expired on 25 June 2026; the analysis date is 5 September 2026
- `early_bird` starts `+1 days` from registration, `opt_in` `+2 days`, `review` `+7 days`

| Check | Rule off | Rule on |
|---|---|---|
| `CacheBank::notices` on `admin_notices` | **hooked** | **gone** |
| `CacheBank::scripts` on `admin_footer` | **hooked** | **gone** |
| `Facebook::render_expiry_notice` (must survive) | present p10 | **present p10** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

A first attempt to A/B by page content was discarded as invalid: activating the plugin
redirects to its "Quick Setup Page", so the two captures were of different screens.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
public function unhook_essential_blocks_campaigns() : void {
	$cache_bank_class = '\\PriyoMukul\\WPNotice\\Utils\\CacheBank';

	if ( ! class_exists( $cache_bank_class ) || ! method_exists( $cache_bank_class, 'get_instance' ) ) {
		return;
	}

	$cache_bank = $cache_bank_class::get_instance();

	if ( ! is_object( $cache_bank ) ) {
		$this->log( 'essential-blocks', 'CacheBank not reachable; no action taken.' );
	} else {
		remove_action( 'admin_notices', [ $cache_bank, 'notices' ] );
		remove_action( 'admin_footer', [ $cache_bank, 'scripts' ] );
		$this->log( 'essential-blocks', 'Removed CacheBank::notices from admin_notices.' );
	}
}
```
