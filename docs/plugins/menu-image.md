# Menu Image (Freshlight Lab)

- slug: `menu-image`
- version analysed: `3.13` (also read: `3.11`, `3.12` for the Freemius config)
- source: `/vault/backups/wordpress/plugins/menu-image/menu-image,3.13.zip`
- licensing: freemium (premium sold through Freemius, 7-day trial, affiliate programme)
- Freemius bundled: **yes**, SDK `2.12.1` (3.12: `2.9.0`, 3.11: `2.6.1`)

## Analysis

Analysed on 21 Oct 2026 by Claude Code (Claude Opus 5).

Paul reported it from a client site: *"Hey! How do you like **Menu Image** so far? Test all
our awesome premium features with a 7-day free trial. No commitment for 7 days - cancel
anytime!"*, with a **Start free trial ➜** button. The HTML carried
`class="fs-notice updated promotion fs-sticky … fs-slug-menu-image"`,
`data-id="trial_promotion"` and `data-manager-id="menu-image"`.

That is the Freemius trial promotion already handled for Featured Images in RSS (1.22.1)
and Role Based Pricing for WooCommerce. **Read
[`featured-images-for-rss-feeds.md`](featured-images-for-rss-feeds.md) first.** It holds
the full SDK analysis and why the rule has two parts. This document records only what
differs for this module. The rule extends that one and adds no new mechanism.

Unlike Role Based Pricing, this module **does** declare an affiliate programme
(`has_affiliation => 'selected'`). That means `_add_affiliate_program_notice()` is a live
producer here, not a no-op, and the `affiliate_program` sticky can appear 30 days after
first use.

The plugin's own code adds one notice, on its own settings screen only. It adds nothing to
the dashboard.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | Plugin: one, `WP_Menu_Image::wp_menu_image_fontawesome_admin_notice`, added from `admin_enqueue_scripts` only when `$hook === 'toplevel_page_menu-image-options'`. SDK: `FS_Admin_Notice_Manager::_admin_notices_hook` |
| Multiline `add_action(` form | Plugin hits are `wp_ajax_set-menu-item-settings`, `wp_update_nav_menu_item`, `wp_nav_menu_item_custom_fields`. None are notice-related |
| `in_admin_header` / `admin_print_footer_scripts` | SDK only |
| Vendor opt-out filters | None in the plugin's own code. SDK: `fs_show_admin_notice_{slug}`, `fs_show_trial_{slug}`, `fs_show_affiliate_program_notice_{slug}`. See the FIRSS doc for why only the first is used |
| Vendor opt-out constants | None. `WP_FS__PRODUCT_4123_MULTISITE` is a network-integration switch, not an opt-out |
| Dashboard widgets | None |
| Outbound calls from widgets | No widgets. Freemius SDK API calls only |
| Freemius | Yes, SDK 2.12.1. Config (`WP_Menu_Image::mi_fs()`): `id 4123`, `slug menu-image`, `has_paid_plans => true`, `has_affiliation => 'selected'`, `trial => 7 days, is_require_payment => true`, `is_premium => false`, `menu slug menu-image-options`. Identical id, slug, trial and affiliation in 3.11 and 3.12 |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Freemius trial promotion | `admin_init` 10 → `Freemius::_add_trial_notice`, sticky id `trial_promotion`, type `promotion` | **suppress** (unhook + render filter) | Trial upsell that says nothing about the site. This is the reported notice |
| Freemius affiliate programme | `admin_init` 10 → `Freemius::_add_affiliate_program_notice`, sticky id `affiliate_program` | **suppress** (same rule) | Recruitment pitch for the vendor's affiliate scheme. Live for this module |
| Freemius opt-in / connect | sticky id `connect_account` | **suppress** (render filter, shared ID list since 1.35.0) | Usage-tracking opt-in. See `delete-all-comments-of-website.md` |
| Font Awesome setup notice | `admin_notices` → `WP_Menu_Image::wp_menu_image_fontawesome_admin_notice` | keep | Only on the vendor's own settings screen, so it is out of scope. It is also a setup instruction for using FA icons, not a sale |
| "Upgrade Now!" premium button and badge blocks | `menu_image_options_page_html()` | out of scope | Rendered inside the vendor's own settings page |

## Deliberately left alone

### Font Awesome setup notice

It tells the admin to install the official Font Awesome plugin and choose SVG mode so FA
icons work in menus. That is configuration guidance, and it points to a free third-party
plugin, not the vendor's own Pro. It also renders only on `toplevel_page_menu-image-options`,
the vendor's own screen, which is out of scope by construction. It is dismissible, and the
dismissal is stored in `wp_menu_image_fa_dismissed`.

### Premium upsell blocks on the settings page

They are inside the vendor's own settings screen. Out of scope, do not touch.

### Every other Freemius notice

Licence, trial-state (`trial_started`, trial expiry), activation and ownership stickies
render on the vendor's terms. `hide_freemius_promo_notice()` passes the incoming value
straight back for any ID not in `FREEMIUS_PROMO_NOTICE_IDS`.

### `fs_show_trial_{slug}` / `fs_show_affiliate_program_notice_{slug}`: not used

`fs_show_trial_{slug}` is not used because `_add_trial_notice()` adds the menu counter
bubble before it consults that filter. Using the filter alone would leave a badge the site
owner could never clear. The full reasoning is in the FIRSS doc.

## Mechanism

- tier: 1 (render filter `fs_show_admin_notice_menu-image`) and 2 (unhook the two producers)
- phase: render filter at file scope. Unhook from `unhook_freemius_promos()` on `admin_init` @ 1, ahead of the SDK's default-priority registration
- vendor registers at: `Freemius::_register_hooks()` → `add_action( 'admin_init', [ &$this, '_add_trial_notice' ] )` and `_add_affiliate_program_notice`, both at default priority 10
- instance reachable via: `\Freemius::get_instance_by_id( 4123 )`, through `get_freemius_module()`. Do not call `mi_fs()` or `Freemius::instance()`, because either would construct the module

## Drift check

- `menu-image.php`, `WP_Menu_Image::mi_fs()`: confirm `'id' => '4123'` and `'slug' => 'menu-image'`. If the ID changes, `get_freemius_module()` returns null and the unhook silently stops. The render filter keeps working on the slug alone
- `freemius/includes/class-freemius.php`: confirm `_add_trial_notice` and `_add_affiliate_program_notice` are still registered on `admin_init`
- `freemius/includes/managers/class-fs-admin-notice-manager.php`: confirm `fs_apply_filter( …, 'show_admin_notice', … )` and the `true !== $show_notice` test

## Verification

- **Render filter (`trial_promotion`)**: **confirmed** on a client site, 21 Oct 2026. Paul saw it rendering before 1.44.0 was deployed and absent after. To reproduce it on a bench instead, a bench needs Menu Image active with the trial sticky stored. `_add_trial_notice()` waits until the module's `install_timestamp` is more than 24 hours old (`show_first_trial_after_n_sec`, default `WP_FS__TIME_24_HOURS_IN_SEC`) and skips activation and pending-activation mode. Backdate `install_timestamp` for `menu-image` in the `fs_accounts` option, and skip the opt-in, before capturing. Probe: `data-id="trial_promotion"` with `data-manager-id="menu-image"`
- **Producer unhook**: **source-verified only.** Probe: the menu counter bubble on the Menu Image admin menu item is gone after the rule
- **Affiliate sticky**: **source-verified only.** It needs 30 days past first use. Not expected on a fresh bench
- **Negative check**: the Font Awesome notice must still render on the Menu Image settings screen when the Font Awesome plugin is not active

## Additions to `headwall-nag-cleanup.php`: Menu Image joins the existing Freemius rule

```php
const MENU_IMAGE_FREEMIUS_MODULE_ID = 4123;
const MENU_IMAGE_FREEMIUS_SLUG      = 'menu-image';

// register_vendor_optouts()
add_filter(
	'fs_show_admin_notice_' . self::MENU_IMAGE_FREEMIUS_SLUG,
	[ $this, 'hide_freemius_promo_notice' ],
	self::LATE_PRIORITY,
	2
);

// unhook_freemius_promos()
$this->unhook_freemius_module_promos( self::MENU_IMAGE_FREEMIUS_MODULE_ID );
```
