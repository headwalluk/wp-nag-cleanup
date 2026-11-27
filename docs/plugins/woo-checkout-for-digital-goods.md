# Digital Goods for WooCommerce Checkout (theDotstore)

- slug: `woo-checkout-for-digital-goods`
- version analysed: `3.8.6` (newest in the vault; `3.8.4` and `3.8.5` also present)
- source: `/vault/backups/wordpress/plugins/woo-checkout-for-digital-goods/woo-checkout-for-digital-goods,3.8.6.zip`
- licensing: freemium (Pro sold through Freemius, 14-day trial, payment required)
- Freemius bundled: **yes**, SDK `2.13.5`

## Analysis

Analysed on 27 Nov 2026 by Claude Code (Claude Opus 5).

Paul reported it from a client site on Black Friday: *"Hey! How do you like **Digital Goods
for WooCommerce Checkout** so far? Test all our awesome premium features with a 14-day free
trial. No commitment for 14 days - cancel anytime!"*, with a **Start free trial ➜** button.
The HTML carried `class="fs-notice updated promotion fs-sticky … fs-slug-woo-checkout-for-digital-goods"`,
`data-id="trial_promotion"` and `data-manager-id="woo-checkout-for-digital-goods"`.

That is the Freemius trial promotion already handled for Featured Images in RSS, Role Based
Pricing, Menu Image and Goal Tracker. **Read
[`featured-images-for-rss-feeds.md`](featured-images-for-rss-feeds.md) first.** It holds the
full SDK analysis and explains why the rule has two parts. This document records only what
differs for this module. It is not a Black Friday campaign: `trial_promotion` is the SDK's
standing trial upsell, and the timing is coincidence.

The plugin's own code adds nothing in the notice area beyond a WooCommerce-missing error.
All of theDotstore's own promotion is on the plugin's own settings screens, and those are
out of scope.

### SDK 2.13.5 checks

This is the newest SDK on the rule so far (the others are 2.11.0 to 2.13.4), so the parts
the rule depends on were re-read in this copy:

- `class-freemius.php:1580-1581` registers `_add_trial_notice` and
  `_add_affiliate_program_notice` on `admin_init` at the default priority
- `_add_trial_notice()` (line 23957) adds the `fs-trial` menu counter when
  `is_in_trial_promotion()` is true, *before* it consults `show_trial`. The ordering is
  unchanged, so the unhook is still needed
- `class-fs-admin-notice-manager.php:277-282` applies `show_admin_notice` and skips on
  `true !== $show_notice`
- `fs_apply_filter()` (`fs-core-functions.php:1480`) builds `fs_{$tag}_{$module_unique_affix}`,
  and `get_module_unique_affix()` (line 19440) returns the bare slug for a plugin. So the filter
  is `fs_show_admin_notice_woo-checkout-for-digital-goods`
- `Freemius::get_instance_by_id()` exists (line 2918)

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | Plugin: `wcdg_plugin_admin_notice` only, added by `wcdg_initialize_plugin()` when WooCommerce is not active (`woo-checkout-for-digital-goods.php:258, 264`). SDK: `FS_Admin_Notice_Manager::_admin_notices_hook` |
| Multiline `add_action(` form | Hits are WooCommerce checkout hooks, `init`, `admin_head` (`wcdg_remove_admin_submenus`, menu housekeeping) and `admin_init` (`wcdg_handle_convert_to_pro_dismiss`, see below). None in the notice area |
| `in_admin_header` / `admin_print_footer_scripts` | SDK only |
| Vendor opt-out filters | None in the plugin's own code. SDK: `fs_show_admin_notice_{slug}`, `fs_show_trial_{slug}`, `fs_show_affiliate_program_notice_{slug}` |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls | `wcdg_get_promotional_bar()` fetches `thedotstore.com/wp-json/dpb-promotional-banner/v2/…` on every render of the plugin's own header. `dots-upgrade-dashboard.php` and `dots-upgrade-popup.php` fetch upgrade pricing for the plugin's own upgrade screen. `wcdg_send_wizard_data_after_plugin_activation()` sends the Freemius user's email and the wizard answer to `dotstore-sendinblue-data`, once, after the setup wizard. None renders outside the vendor's own screens |
| Freemius | Yes, SDK 2.13.5. Config (`wcfdg_fs()` in `woo-checkout-for-digital-goods.php`): `id 4703`, `slug woo-checkout-for-digital-goods`, `has_paid_plans => true`, `trial => 14 days, is_require_payment => true`, `is_premium => false`, `is_org_compliant => true`, menu slug `wcdg-general-setting`. No `has_affiliation` key |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Freemius trial promotion | `admin_init` 10 → `Freemius::_add_trial_notice`, sticky id `trial_promotion`, type `promotion` | **suppress** (unhook + render filter) | A trial upsell that says nothing about the site. This is the reported notice |
| Freemius affiliate programme | `admin_init` 10 → `Freemius::_add_affiliate_program_notice`, sticky id `affiliate_program` | **suppress** (same rule) | No `has_affiliation` in the config, so expected to be a no-op here. Covered by the shared unhook and ID list |
| Freemius opt-in / connect | sticky id `connect_account` | **suppress** (render filter, shared ID list) | Usage-tracking opt-in. See `delete-all-comments-of-website.md` |
| WooCommerce-missing notice | `admin_notices` → `wcdg_plugin_admin_notice` | **keep** | Missing dependency |

## Deliberately left alone

### Every other Freemius notice

Licence, trial-state, activation and ownership stickies render on the vendor's own terms.
`hide_freemius_promo_notice()` passes the incoming value straight back for any ID not in
`FREEMIUS_PROMO_NOTICE_IDS`.

### `fs_show_trial_{slug}`: not used

Same reason as the other Freemius modules. `_add_trial_notice()` adds the menu counter
before it consults the filter.

### theDotstore's dynamic promotional bar

`wcdg_get_promotional_bar()` is called only from `admin/partials/header/plugin-header.php`,
which is the plugin's own settings header. It is a remote-driven sale banner and it makes an
uncached outbound request on every render, but it is on the vendor's own screens, so it is
out of scope by construction. If outbound blocking is wanted, it belongs in the fleet mu-plugin,
not here.

### The "convert to Pro" notice

`plugin-header.php:30-54` shows install-the-Pro-build instructions when `wcdg_convert_to_pro`
is set, which `wcdg_convert_to_pro_purchase()` does after a Freemius purchase. It is on the
vendor's own header, and it is operational: the customer has paid and needs to install the
Pro build. Left alone. `wcdg_handle_convert_to_pro_dismiss` on `admin_init` @ 5 only handles
its dismiss link.

### The upgrade dashboard and popup, the admin-footer review text

`dots-upgrade-dashboard.php`, `dots-upgrade-popup.php` and `wcdg_admin_footer_review` (added
only when `page` contains `wcdg`) are all on the vendor's own screens. Out of scope.

## Mechanism

- **Freemius:** mechanism 1 (render filter `fs_show_admin_notice_woo-checkout-for-digital-goods`) plus mechanism 2 (unhook the two producers)
  - phase: the render filter registers at file scope. The unhook runs from `unhook_freemius_promos()` on `admin_init` @ 1, ahead of the SDK's default-priority registration
  - instance reachable via: `\Freemius::get_instance_by_id( 4703 )`, through `get_freemius_module()`. Do not call `wcfdg_fs()` or `Freemius::instance()`, because either would construct the module

## Drift check

- `woo-checkout-for-digital-goods.php`, `wcfdg_fs()`: confirm `'id' => '4703'` and
  `'slug' => 'woo-checkout-for-digital-goods'`. If the ID changes, `get_freemius_module()`
  returns null and the unhook silently stops. The render filter keeps working on the slug alone
- If a later SDK is bundled, re-run the SDK checks above

## Verification

- **Render filter (`trial_promotion`)**: **confirmed** on the client site where Paul saw it,
  27 Nov 2026. It was rendering before 1.53.0 was deployed and absent after. Probe:
  `data-id="trial_promotion"` with `data-manager-id="woo-checkout-for-digital-goods"`
- **Producer unhook**: **source-verified only**. Probe: the `fs-trial` counter bubble is gone
  from the plugin's admin menu item
- Negative check: with WooCommerce deactivated, the `wcdg_plugin_admin_notice` dependency
  notice still renders

## Additions to `headwall-nag-cleanup.php` (1.53.0)

```php
const WCDG_FREEMIUS_MODULE_ID = 4703;
const WCDG_FREEMIUS_SLUG      = 'woo-checkout-for-digital-goods';

// register_vendor_optouts()
add_filter(
	'fs_show_admin_notice_' . self::WCDG_FREEMIUS_SLUG,
	[ $this, 'hide_freemius_promo_notice' ],
	self::LATE_PRIORITY,
	2
);

// unhook_freemius_promos()
$this->unhook_freemius_module_promos( self::WCDG_FREEMIUS_MODULE_ID );
```
