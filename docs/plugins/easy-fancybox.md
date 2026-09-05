# Easy FancyBox (FirelightWP)

- slug: `easy-fancybox`
- version analysed: `2.3.22`
- source: `/vault/backups/wordpress/plugins/easy-fancybox/easy-fancybox,2.3.22.zip`
- licensing: freemium (free on wordpress.org, Easy FancyBox Pro sold at firelightwp.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a
client site.

1 fleet site. **One rule added**: the review request. The plugin's only other
`admin_notices` callback is a Pro version-compatibility warning and is left alone.

No shared framework. The other `easy-*` plugins on the fleet are unrelated vendors —
ShapedPlugin, WPExplorer, EasyRegistrationForms — and three (`easy-csp-headers`,
`easy-g-maps`, `easy-logo-carousel`) are Headwall's own. This is a single-plugin rule.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | **2**, both static callbacks on `easyFancyBox_Admin` |
| Vendor opt-out filters | **None** |
| Vendor opt-out constants | None |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Review request | `admin_notices` → `easyFancyBox_Admin::show_review_request` | **suppress** | Links to `wordpress.org/support/plugin/easy-fancybox/reviews/` and `firelightwp.com/contact/`, and carries the email opt-in |
| Pro compatibility warning | `admin_notices` → `easyFancyBox_Admin::admin_notice` | **keep** | Only renders when `$do_compat_warning` is set |

## Deliberately left alone

**`admin_notice` is a version-compatibility warning, not a promotion.** Despite the
generic name it renders nothing unless `self::$do_compat_warning` is true, which
`compat_warning()` sets only when `easyFancyBox_Advanced` (the Pro plugin) is installed at
a version below `$compat_pro_min`. That is a genuine "your two halves are incompatible"
notice, and hiding it would leave a site with a broken lightbox and no explanation.
Verified still hooked with the rule active.

## Mechanism

- tier: 2 (targeted unhook)
- phase: `admin_init`, `self::LATE_PRIORITY`. Both callbacks are registered from
  `easyFancyBox_Admin::__construct` at plugin load
- instance reachable via: **not needed.** Both are static callbacks registered as
  `array( __CLASS__, … )`, so `remove_action()` names the class directly

### The class name is case-sensitive — a trap worth recording

The class is declared `class easyFancyBox_Admin` with a **lowercase `e`**. PHP class names
are case-insensitive, so `class_exists( 'EasyFancyBox_Admin' )` would return true and the
mistake would look harmless. It is not.

`_wp_filter_build_unique_id()` keys a static array callback by literal string
concatenation (`wp-includes/plugin.php`):

```php
} elseif ( is_string( $callback[0] ) ) {
    // Static Calling.
    return $callback[0] . '::' . $callback[1];
}
```

So `'EasyFancyBox_Admin::show_review_request'` and
`'easyFancyBox_Admin::show_review_request'` are different keys, and `remove_action()` with
the wrong case silently removes nothing. Demonstrated on the bench: with the plugin active
and the callback registered, `has_action()` returned `false` for the mis-cased form and a
priority for the correct one.

**Copy static class names from the source, never from the file name or from memory.**

## Drift check

- `inc/class-easyfancybox-admin.php` — the `easyFancyBox_Admin` class name (including its
  case) and the `show_review_request` method name. A rename makes the rule a silent no-op
- `admin_notice` / `compat_warning` — must stay untouched. If `admin_notice` ever gains
  promotional content alongside the compatibility warning it becomes mixed output and
  needs re-reading
- If FirelightWP ship other plugins onto the fleet, check whether they share this review
  code before assuming this rule covers them

## Verification

Tested on `bench2.local` (WP 7.1) with Easy FancyBox 2.3.22 active, A/B with the rule
enabled and disabled, over authenticated admin requests:

| Check | Rule off | Rule on |
|---|---|---|
| `easyFancyBox_Admin::show_review_request` (correct case) | **HOOKED** | **gone** |
| `EasyFancyBox_Admin::show_review_request` (wrong case) | gone | gone |
| `easyFancyBox_Admin::admin_notice` (must survive) | present | **present** |
| PHP fatals | 0 | 0 |

The middle row is the point: the mis-cased lookup reports "gone" even while the callback
is registered, which is exactly how a mis-cased rule would fail — silently, and looking
like success.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
public function unhook_easy_fancybox_review_request() : void {
	$review_callback = [ 'easyFancyBox_Admin', 'show_review_request' ];

	if ( false !== has_action( 'admin_notices', $review_callback ) ) {
		remove_action( 'admin_notices', $review_callback );
		$this->log( 'easy-fancybox', 'Removed easyFancyBox_Admin::show_review_request from admin_notices.' );
	}
}
```
