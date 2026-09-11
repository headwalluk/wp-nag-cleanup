# Code Snippets

- slug: `code-snippets`
- version analysed: `3.10.2`
- source: `/vault/backups/wordpress/plugins/code-snippets/code-snippets,3.10.2.zip`
- licensing: freemium
- Freemius bundled: no

## Analysis

Analysed on 11 Sep 2026 by Claude Code (Claude Opus 5).

Code Snippets runs a **competitor-conversion promotion framework**. Seven
`Promotion_Base` subclasses each target a rival plugin, and when you are on *that rival's*
admin screen Code Snippets injects a notice into it.

That makes this different from most promotional surfaces audited here. It is not a vendor
advertising on its own screens — it is a vendor advertising on **someone else's**. One rule,
against the promotion notice.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 2 — `Promotion_Base::display_promotion` (priority 1) and `code_snippets_deactivation_notice` |
| Vendor opt-out filters | **None.** `get_setting()` (`php/Settings/settings.php:77`) has no filter at all |
| Vendor opt-out constants | **None** |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Competitor promotion notice | `admin_notices` 1 → `Promotion_Base::display_promotion` | **suppress** | *"Code Snippets Promotion"*, injected into a competitor plugin's admin screen |
| PHP version notice | `admin_notices` → `code_snippets_deactivation_notice` | **keep** | *"Code Snippets PHP version notice"*. Only loaded when the PHP version is unsupported |
| Promotion dismiss AJAX | `wp_ajax_code_snippets_dismiss_promotion` | **keep** | The vendor's own dismiss path |
| `Other\Elementor_Editor` | not a notice | **keep** | An editor integration, not promotional output |

### The seven promotion targets

| Subclass | Screens it appears on |
|---|---|
| `Elementor` | `elementor_custom_code`, `elementor_page_elementor_custom_code`, `edit-elementor_snippet` |
| `Header_Footer_Code` | `tools_page_head-footer-code` |
| `Header_Footer_Code_Manager` | `toplevel_page_hfcm-list`, `hfcm_page_hfcm-tools`, `hfcm_page_hfcm-create`, `admin_page_hfcm-update` |
| `Insert_HTML_Snippet` | 4 `insert-html-snippet` screens |
| `Insert_PHP_Code_Snippet` | 4 `insert-php-code-snippet` screens |
| `Insert_PHP` | 5 `wbcr-snippets` screens |
| `WP_Headers_And_Footers` | `settings_page_wp-headers-and-footers` |

The screen lists are **disjoint**, so at most one subclass matches per request. That is why
matching the abstract parent once finds the right one — but see the duplicate problem below,
which is a different issue and did bite.

## Deliberately left alone

### `hide_upgrade_menu` — a stored setting with no filter

`Promotion_Base::is_promotion_dismissed()` returns true when
`get_setting( 'general', 'hide_upgrade_menu' )` is set, so that setting would suppress the
promotion.

Not usable. `get_setting()` is a plain array lookup with **no `apply_filters()` anywhere**,
so there is nothing to hook — and even if there were, it is a stored site-owner setting
rendered back into the vendor's own settings screen, which is the objection already recorded
against `wpforms_setting`/`hide-announcements` and `ast-disable-upgrade-notices`.

### `code_snippets_deactivation_notice`

Included from `code-snippets.php:63` and titled "Code Snippets PHP version notice". A
version warning, first-class operational content. It was observed **absent** on the bench,
which is correct — the include sits behind a PHP-version guard and the bench runs 8.5.

## Mechanism

- tier: 2 (targeted unhook, via the sanctioned `$wp_filter` reader)
- phase: `current_screen` at `self::LATE_PRIORITY`
- vendor registers at: `Promotion_Base::__construct()` hooks `current_screen`, and
  `register_promotion_hooks()` adds `admin_notices` at priority 1 — but only when
  `is_plugin_admin_screen() && ! is_promotion_dismissed()`
- instance reachable via: **no.** `Promotion_Manager::__construct()` runs
  `new Notices\Elementor();` and seven siblings, discarding every one

`current_screen` is the required phase, for the same reason as Elementor's conversion
banner: the vendor only adds the notice from its own `current_screen` handler, so at
`admin_init` there is nothing to remove yet.

The reader matches the **abstract parent** `Promotion_Base`, which `instanceof` satisfies for
every subclass. One rule covers all seven.

### The vendor registers the promotion twice

`Plugin.php` calls `new Promotion_Manager();` in **two** separate `is_admin()` blocks
(around lines 128 and 137). Each builds its own set of promotion objects, so the matching
promotion lands on `admin_notices` **twice** and renders twice.

A single `remove_action()` therefore leaves one copy on screen. This was caught on the
bench — two promotion blocks before, one after — and the rule now loops
`find_instance_callback()` until it stops matching, bounded by
`self::MAX_DUPLICATE_CALLBACKS`.

**Do not simplify this back to a single `remove_discarded_instance_callback()` call.** It
looks equivalent and is not.

## Drift check

- `php/Plugin.php` — the two `new Promotion_Manager()` calls. If the vendor fixes the
  duplicate, the loop simply runs once and the rule still works; no change needed
- `php/Integration/Promotions/Notices/` — new subclasses need no rule change, because the
  rule matches the abstract parent. **A new subclass that does not extend `Promotion_Base`
  would escape it**, so check the parent when the directory grows
- `php/Integration/Promotions/Notices/Promotion_Base.php` — `register_promotion_hooks()`.
  If the notice moves off `current_screen`, the phase needs re-deriving
- `php/Settings/settings.php` — if `get_setting()` ever gains a filter, re-evaluate, though
  the stored-setting objection would still stand

## Verification

Bench: `bench2.local`, WP 7.1, Code Snippets 3.10.2.

**Gate opened:** the promotion only fires on a competitor's screen, so
`header-footer-code-manager` 1.1.46 — one of the seven targets, and the only one in the
vault — was installed and the capture taken on `admin.php?page=hfcm-list`
(`toplevel_page_hfcm-list`). The other six competitor plugins are not in the vault.

### Promotion notice — **Confirmed**

| Check | Before | After |
|---|---|---|
| `class="notice notice-info is-dismissible code-snippets-promotion"` on `hfcm-list` | **2** | **0** |
| Debug log | — | `code-snippets: Removed 2 Promotion_Base::display_promotion callback(s) from admin_notices.` |

The "2" is the duplicate-registration bug above. An earlier build of this rule scored 2 → 1
and was corrected.

### Negative checks

| Check | Result |
|---|---|
| `code_snippets_deactivation_notice` | Not hooked — **and not hooked in the rule-free build either**, so this is the vendor's PHP-version guard, not our doing |
| Dashboard with Code Snippets active, no competitor screen | Rule logs nothing and removes nothing; the notice was never queued |
| Dashboard, product list, plugins, WooCommerce Status | 200, screens asserted |
| PHP fatals / parse errors | **0** |

### Live fleet check — attempted, inconclusive

Paul checked the fleet sites carrying this plugin on 11 Sep 2026, before 1.26.0 was
deployed, and **no instance of this notice was showing**. That is neither confirmation nor
refutation of the rule: the vendor's gates (one of seven specific competitor plugins must be installed, and the check must be made on that plugin's admin screen) are not met on those particular sites,
so there was nothing to see either way.

Status therefore stands as **bench-confirmed, not live-confirmed** — the notice was
observed rendering before the rule and absent after on `bench2.local`, but has not yet been
seen suppressed on a production site. Promote this to live-confirmed only when an actual
instance is observed gone in the wild, and record the site and date as
`docs/plugins/brainstorm-force.md` does.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_late_vendor_notices(), plus const MAX_DUPLICATE_CALLBACKS = 16:
$this->unhook_code_snippets_promotions();
```
