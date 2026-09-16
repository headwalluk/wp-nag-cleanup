# Role Based Pricing for WooCommerce (Meow Crew)

- slug: `role-and-customer-based-pricing-for-woocommerce`
- version analysed: `2.0.0` (also read: `1.6.4`)
- source: `/vault/backups/wordpress/plugins/role-and-customer-based-pricing-for-woocommerce/role-and-customer-based-pricing-for-woocommerce,2.0.0.zip`
- licensing: freemium (premium sold through Freemius, 7-day trial)
- Freemius bundled: **yes**, SDK `2.13.2` (1.6.4: `2.13.0`)

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5).

Paul reported it from a live client site (WooCommerce): *"Hey! How do you like **Role Based
Pricing for Woo by Meow Crew** so far? Test all our awesome premium features with a 7-day
free trial. No commitment for 7 days - cancel anytime!"*, with a **Start free trial ➜**
button. The HTML carried `class="fs-notice … promotion fs-sticky"` and
`data-id="trial_promotion"`.

That is **exactly** the Freemius trial promotion already handled for Featured Images in RSS
in 1.22.1. **Read [`featured-images-for-rss-feeds.md`](featured-images-for-rss-feeds.md)
first.** It holds the full SDK analysis, why the rule has two parts, and the 1.22.0 failure
that proved both parts are needed. This document only records what differs for this
module, and the rule is an extension of that one, not a new mechanism.

The plugin's own code adds nothing promotional to the notice area or the dashboard.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | Plugin: 2 closures. `AdminNotifier::push()` (error and flash messages from the plugin's own actions) and `RoleSpecificPricingCPT` (pricing-rule validation warning on its own post type's edit screen). SDK: `FS_Admin_Notice_Manager::_admin_notices_hook` |
| Multiline `add_action(` form | Nothing notice-related in the plugin's own code |
| `in_admin_header` / `admin_print_footer_scripts` / `admin_footer` | SDK only (trial menu URL fix, sticky dismiss JS, deactivation feedback dialog, opt-out dialog) |
| Vendor opt-out filters | None in the plugin's own code. SDK: `fs_show_admin_notice_{slug}`, `fs_show_trial_{slug}`, `fs_show_affiliate_program_notice_{slug}` — see the FIRSS doc for why only the first one is used |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls from widgets | No widgets. SDK updater and clone manager only |
| Freemius | Yes, SDK 2.13.2. Config (`license.php`): `id 9596`, `slug role-and-customer-based-pricing-for-woocommerce`, `has_paid_plans => true`, `trial => 7 days, is_require_payment => true`, `is_org_compliant => true`, `is_premium => false`, **no `has_affiliation`** |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Freemius trial promotion | `admin_init` 10 → `Freemius::_add_trial_notice`, sticky id `trial_promotion`, type `promotion` | **suppress** (unhook + render filter) | Trial upsell, says nothing about the site. Re-shows every 30 days |
| Freemius affiliate program | `admin_init` 10 → `Freemius::_add_affiliate_program_notice` | **suppress** (same rule, no-op today) | The module declares no affiliate program, so the producer bails at `! $this->has_affiliate_program()`. The shared rule unhooks it anyway, so the rule stays correct if the vendor turns one on |
| Freemius opt-in / connect | sticky id `connect_account` | keep | Declined for every Freemius module, see `independent-analytics.md`. **Seen still rendering on the bench after the rule** — the negative check |
| `AdminNotifier` messages | `admin_notices` → closure | keep | Result and error messages from actions the admin just took |
| Pricing-rule validation warning | `admin_notices` → closure in `RoleSpecificPricingCPT` | keep | Pricing rule is misconfigured. Operational, and on the vendor's own post type |
| "You are using the free version … Upgrade to premium" | `views/admin/alerts/upgrade-alert.php`, from `Settings::…` | out of scope | Rendered inside the plugin's own WooCommerce settings section |

## Deliberately left alone

### Everything that is not a Freemius promotional sticky

The two closures carry operational messages, and closures cannot be unhooked by name
anyway. The upgrade alert lives on the vendor's own settings section. The `connect_account`
opt-in is declined here for the same reasons as for every other Freemius module.

### `fs_show_trial_{slug}` — not used

Rejected for the reason recorded in the FIRSS doc and in the PHP comment on
`unhook_freemius_promos()`: `_add_trial_notice()` adds the menu counter badge *before* it
reads that filter, whenever the sticky is already stored. The bench showed that badge
(`fs-trial`) rendering next to the stored sticky, and gone after the rule.

### `remove_sticky( 'trial_promotion' )` — not used

It would write to the vendor's storage. The render filter hides the stored sticky without
changing it, and the bench confirmed it **stays stored** after the rule (see below).
Remove Headwall Nag Cleanup and the nag comes back, dismissable as normal.

## Mechanism

Identical to Featured Images in RSS. The module's id and slug are new constants, and the
existing code is reused:

- tier: **1** (render-time `fs_show_admin_notice_{slug}`) + **2** (targeted unhook of the
  two producers)
- phase: file scope for the filter; `admin_init` at `EARLY_PRIORITY` for the unhook
- vendor registers at: `license.php` runs `racbpfw_fs()` → `fs_dynamic_init()` at plugin
  load, and the SDK's `class-freemius.php:1580-1581` adds `_add_trial_notice` and
  `_add_affiliate_program_notice` to `admin_init` at priority 10 on that module's instance
- instance reachable via: `Freemius::get_instance_by_id( 9596 )`, the SDK's own registry.
  No `$wp_filter` walk

### What changed in the shared code

- Constants `RACBPFW_FREEMIUS_MODULE_ID` (`9596`) and `RACBPFW_FREEMIUS_SLUG`
- A second `add_filter( 'fs_show_admin_notice_' . self::RACBPFW_FREEMIUS_SLUG, … )` in
  `register_vendor_optouts()`
- `unhook_freemius_promos()` now calls a new private `unhook_freemius_module_promos( $id )`
  once per module, so each module's pair is removed from **its own** instance. A module
  left out of that list would lose its stored sticky (the render filter) but **keep the
  menu badge**
- Logging: `hide_freemius_promo_notice()` now logs under `freemius` and names the module's
  `manager_id`. It used to log under `firss-freemius` for any module. The "module not
  present" line is gone: it fired on every admin request of every site without FIRSS,
  which `CLAUDE.md` says "not installed" branches must not do

`FREEMIUS_PROMO_NOTICE_IDS` is SDK-wide and did not change. The FIRSS doc's advice still
holds: turn the id/slug pairs into one array and loop only once there are three or four
modules.

### The SDK copy that runs is not necessarily this plugin's

When several plugins bundle Freemius, only the newest SDK on the site is loaded
(`fs_active_plugins`). So on a site with FIRSS (2.13.4) and this plugin (2.13.2), this
module runs on 2.13.4. Every line the rule depends on is unchanged across 2.13.0, 2.13.2
and 2.13.4. Check this again whenever a bundled SDK moves to a new minor version.

## Drift check

- `license.php` — `'id' => '9596'` and `'slug'`. A change to either kills both parts of
  the rule silently
- Freemius SDK — the load-bearing lines listed in `featured-images-for-rss-feeds.md`
  (`_admin_notices_hook()`'s `show_admin_notice` filter and its `true !==` gate, the two
  `admin_init` registrations, `get_instance_by_id()`)

```bash
ZIP=/vault/backups/wordpress/plugins/role-and-customer-based-pricing-for-woocommerce/role-and-customer-based-pricing-for-woocommerce,<version>.zip
unzip -p "$ZIP" role-and-customer-based-pricing-for-woocommerce/license.php | command grep -n "'id'\|'slug'"
unzip -p "$ZIP" role-and-customer-based-pricing-for-woocommerce/freemius/start.php | command grep -n "this_sdk_version *="
unzip -p "$ZIP" role-and-customer-based-pricing-for-woocommerce/freemius/includes/class-freemius.php | command grep -n "_add_trial_notice' )\|_add_affiliate_program_notice' )"
unzip -p "$ZIP" role-and-customer-based-pricing-for-woocommerce/freemius/includes/managers/class-fs-admin-notice-manager.php | command grep -n "'show_admin_notice',\|true !== \$show_notice"
```

## Verification

Bench: `bench2.local`, WP 7.1, WooCommerce 11.1.0 (a hard `Requires Plugins` dependency)
and Role Based Pricing for WooCommerce **2.0.0**, both unzipped from the vault,
16 Sep 2026. Before = 1.29.0 (HEAD), after = working copy, `HEADWALL_NAG_CLEANUP_DEBUG` on,
three warm-up requests after each deploy.

**The sticky was seeded, not produced.** `_add_trial_notice()` needs a trial plan synced
from Freemius's API, an install more than 24 hours old, and an admin user. That is a live
vendor connection the bench should not make. Instead, `wp eval` reached the module's
private `_admin_notices` manager through `ReflectionProperty` and called the SDK's own
`add_sticky()` with the same id (`trial_promotion`) and type (`promotion`) as the vendor.
That stores the notice exactly as a site that has already been nagged stores it, which is
the case the reported site is in and the case 1.22.0 got wrong. The producer unhook is
confirmed by the menu badge, which the producer adds on every request while the sticky is
stored.

### Trial promotion — **Confirmed** (bench)

| Check (dashboard and plugins.php) | Before | After |
|---|---|---|
| `data-id="trial_promotion"` | **1** | **0** |
| Trial menu badge `fs-trial` | **1** | **0** |
| Debug log | — | `freemius: Removed trial and affiliate notice producers for module 9596 from admin_init.` and `freemius: Hid sticky notice "trial_promotion" for role-and-customer-based-pricing-for-woocommerce.` |

### Negative checks

| Check | Result |
|---|---|
| Screens asserted: `id="dashboard-widgets"`, `id="the-list"` | yes |
| `data-id="connect_account"` opt-in still rendering | **yes**, 1 before and 1 after |
| `trial_promotion` still in Freemius storage after the rule | **yes** — `has_sticky()` true. Hidden, not deleted |
| PHP fatals / warnings / parse errors | **0** |

Bench reset afterwards. WooCommerce's own `uninstall.php` was run with `WC_REMOVE_ALL_DATA`
to remove its tables, pages, terms, roles and capabilities. The remaining options
(including Freemius's `fs_accounts`, `fs_active_plugins`, `fs_debug_mode`) were deleted by
comparing against a snapshot taken before install.

**Not yet live-confirmed.** Reported from a live client site. Promote once the release is
deployed there and the notice is seen gone.

## Additions to `headwall-nag-cleanup.php`: second Freemius module on the existing rule

```php
const RACBPFW_FREEMIUS_MODULE_ID = 9596;
const RACBPFW_FREEMIUS_SLUG      = 'role-and-customer-based-pricing-for-woocommerce';

// register_vendor_optouts(), file scope
add_filter( 'fs_show_admin_notice_' . self::RACBPFW_FREEMIUS_SLUG, [ $this, 'hide_freemius_promo_notice' ], self::LATE_PRIORITY, 2 );

// unhook_freemius_promos(), admin_init at EARLY_PRIORITY
$this->unhook_freemius_module_promos( self::FIRSS_FREEMIUS_MODULE_ID );
$this->unhook_freemius_module_promos( self::RACBPFW_FREEMIUS_MODULE_ID );
```
