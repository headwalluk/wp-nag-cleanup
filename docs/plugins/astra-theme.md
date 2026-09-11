# Astra (theme)

- slug: `astra` — **the free theme**, not the `astra-addon` plugin
- version analysed: `4.13.11`
- source: `/var/www/<bench>/wp-content/themes/astra` — **not the vault**, which holds
  plugins only. See "Sourcing a theme" below
- licensing: free (GPL, wordpress.org). Astra Pro ships separately as `astra-addon`
- Freemius bundled: no
- bundled libraries: `astra-notices` (`BSF_Admin_Notices`) 1.2.3, `bsf-analytics` 1.1.29

## Analysis

Analysed on 11 Sep 2026 by Claude Code (Claude Opus 5).

The free Astra theme is a **separate codebase** from the Astra Pro plugin covered in
`docs/plugins/brainstorm-force.md`, and it has its own notice surface. It was reached
because Paul found a Brainstorm Force upsell on a client site's **WooCommerce → Status**
screen that appeared nowhere else on the site.

One rule is added: the *"Running a WooCommerce store? You need more than just a theme"*
banner pushing the Business Toolkit. Everything else Astra puts in the notice area is
operational — PHP memory limit warnings, Astra Pro version-mismatch errors and deprecated
hook warnings for developers.

The theme also bundles `bsf-analytics` and registers the entity key `astra`, so it emits
the same *"Help shape the future of Astra"* opt-in nag that CartFlows does. **No separate
rule is needed** — the mechanism 2 rule added in 1.25.0 keys on the `BSF_Analytics` class
and removes the single shared callback. See `docs/plugins/cartflows.md`.

### Sourcing a theme

The vault is `/vault/backups/wordpress/plugins/` and has **no themes tree**, so the usual
`analyse-plugin` extraction does not apply here. Astra 4.13.11 was copied read-only from a
bench site on this host into `work/astra-theme-4.13.11/`; four sites carry the same
version. If a theme ever needs analysing against a specific release that is not on this
host, it has to come from wordpress.org — there is no local archive.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 2 — `BSF_Admin_Notices::show_notices` (the framework) and `astra_show_deprecated_admin_hooks_warnings`. Everything else is queued into the framework from `admin_init` |
| Queued notices (`BSF_Admin_Notices::add_notice` callers) | 6. One promotional, one telemetry (covered elsewhere), one ambiguous, three operational |
| Vendor opt-out filters | `astra_get_option_{$option}` (dynamic, **rejected** — see below), `astra_disable_starter_templates_promotions` (available, not used), `astra_showcase_starter_templates_notice` (available, not used), `astra_notices_user_cap_check` / `bsf_admin_notices_user_cap_check` (framework-wide, rejected), `bsf_usage_tracking_enabled` (already used since 1.4.0) |
| Vendor opt-out constants | **None** |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Hook / notice ID | Verdict | Reason |
|---|---|---|---|
| WooCommerce "Business Toolkit" upsell | `admin_init` → `Astra_Admin_Settings::upgrade_to_pro_wc_notice`, id `astra-upgrade-pro-wc` | **suppress** | *"Running a WooCommerce store? You need more than just a theme … Upgrade to Business Toolkit"*. Pure upsell with a `utm_campaign=woocommerce` link. No operational content |
| Usage-tracking opt-in | `admin_init` → `BSF_Analytics::option_notice`, id `astra-optin-notice` | **suppress** — already covered | Telemetry consent. Removed by the shared `bsf-analytics` rule added in 1.25.0; no Astra-specific rule needed |
| PHP memory limit warning | `admin_init` → `Astra_Memory_Limit_Notice`, id `astra-memory-limit-warning` | **keep** | *"Your site is nearing its PHP memory limit, which may affect stability."* A true, actionable fact about the site |
| Astra Pro below minimum version | `admin_init` → `minimum_addon_version_notice`, id `ast-minimum-addon-version-notice` | **keep** | Version mismatch; the addon will misbehave until updated |
| Astra Pro incompatible version | `admin_init` → `minimum_addon_supported_version_notice`, id `ast-addon-minimum-supported-version-notice` | **keep** | *"…is incompatible with Astra theme."* Compatibility warning |
| Deprecated hook warnings | `admin_notices` 999 → `astra_show_deprecated_admin_hooks_warnings` | **keep** | Tells a developer their child theme uses a removed hook. Operational for the person who can act on it |
| Starter Templates welcome banner | `admin_init` → `register_notices`, id `astra-sites-on-active` | keep — ambiguous | *"Thank you for choosing Astra! … agree to install and activate the Starter Templates plugin."* Cross-sell in substance, onboarding in context; see below |

## Deliberately left alone

### `astra_get_option_ast-disable-upgrade-notices` — the switch that looked perfect

The WooCommerce upsell is registered behind a gate:

```php
if ( astra_showcase_upgrade_notices() ) {
    add_action( 'admin_init', self::class . '::upgrade_to_pro_wc_notice' );
    add_action( 'wp_nav_menu_item_custom_fields', self::class . '::add_custom_fields', 10, 4 );
}
```

and that helper is one line (`inc/extras.php:1136`):

```php
return ! defined( 'ASTRA_EXT_VER' ) && astra_get_option( 'ast-disable-upgrade-notices', true ) ? true : false;
```

`astra_get_option()` ends with `apply_filters( "astra_get_option_{$option}", $value, $option, $default )`,
so `add_filter( 'astra_get_option_ast-disable-upgrade-notices', '__return_false' )` is a
one-line mechanism 1 rule that stops the notice registering at all. It works.

**Rejected on scope.** `astra_showcase_upgrade_notices()` is read in **27 places** across
the theme, and only one of them is this notice:

- Customizer section configs for WooCommerce, EDD, LifterLMS, typography, sidebar,
  container and the header/footer builders — these add or withhold upgrade controls in the
  **Customizer**, which `CLAUDE.md` puts out of scope by construction
- `class-astra-meta-boxes.php` — a post meta box, plus a `show_upgrade_notice` flag handed
  to its script
- `class-astra-api-init.php`, `class-astra-menu.php`, `class-astra-customizer.php` — flags
  passed into Astra's React admin apps
- `header-builder.php` / `footer-builder.php` — *inverts* into a CSS class:
  `'divider' => astra_showcase_upgrade_notices() ? array() : array( 'ast_class' => 'ast-pro-available' )`.
  Filtering the option false does not remove anything here, it **adds** `ast-pro-available`
  markup

So a filter aimed at one admin notice would quietly restructure Customizer config arrays
across the theme. That is a large, untested blast radius for a small win.

There is a second reason, independent of scope: `ast-disable-upgrade-notices` is a **real
stored site-owner setting**, written by `astra_update_option()` in two migration paths
(`class-astra-builder-admin.php:103`, `class-astra-admin-ajax.php:139`). Filtering it makes
the theme read back something the owner did not set — the same objection that rejected
`cf_white_label_options` in `docs/plugins/cartflows.md`.

The `remove_action()` in this document removes exactly one callback and touches nothing
else. That is the right trade even though it is a higher-numbered mechanism.

### The Starter Templates welcome banner

`register_notices()` queues `astra-sites-on-active`: *"Thank you for choosing Astra! Your
Website, Ready in Minutes"*, with a **Start Building Now** button that installs and
activates the Starter Templates plugin.

In substance that is a cross-sell for another product. It is kept anyway, because its gate
makes it onboarding rather than nagging:

```php
current_user_can( 'install_plugins' ) && ! defined( 'ASTRA_SITES_NAME' ) && '1' == get_option( 'fresh_site' )
```

`fresh_site` flips to `0` the moment anything is published, so this appears only on a brand
new site, to someone who just activated the theme, once. Ambiguous means keep.

Recording the available switches so this is not re-derived: `astra_showcase_starter_templates_notice`
(read in `register_notices` directly) and `astra_disable_starter_templates_promotions`
(documented by the vendor since 4.8.9, and broader — it also governs Starter Templates
promotion elsewhere in the admin). Either would work if the decision ever changes.

### The deprecated-hooks warning

`astra_show_deprecated_admin_hooks_warnings` on `admin_notices` at 999 prints a warning
when a child theme or plugin uses a hook Astra has removed. It is noisy and it is branded,
but it is addressed to a developer who can act on it and it names a real problem in the
site's own code. Kept.

### `astra-notices` capability filters

Same finding as `brainstorm-force.md` and `cartflows.md`: `astra_notices_user_cap_check`
and `bsf_admin_notices_user_cap_check` would disable the whole framework, taking the memory
limit warning and both version-mismatch notices with them. Not used.

## Mechanism

- tier: 2 (targeted unhook)
- phase: `admin_init` at `self::EARLY_PRIORITY`
- vendor registers at: `Astra_Admin_Settings::init_admin_settings()`, hooked to
  `after_setup_theme` priority 99, which calls
  `add_action( 'admin_init', self::class . '::upgrade_to_pro_wc_notice' )` at the default
  priority
- instance reachable via: **N/A — the callback is static.** No `$wp_filter` read is needed
  and none is used
- priority: `EARLY_PRIORITY` (1). The producer is itself on `admin_init` at priority 10, so
  `LATE_PRIORITY` would run after it had already queued the notice. Same shape as WPCode
  and the `bsf-analytics` rule

The callback is atomic: `upgrade_to_pro_wc_notice()` checks the current `page` against
`wc-admin`, `wc-reports`, `wc-status`, `wc-addons` and `wc-settings`, and queues one notice.
It emits nothing else, so there is no mixed-output problem.

**The static-name case trap applies here.** `_wp_filter_build_unique_id()` keys a static
callback by literal string concatenation, so `remove_action()` with the wrong case removes
nothing and looks like success — the Easy FancyBox failure in 1.17.0. The class is declared
`class Astra_Admin_Settings` and registered through `self::class`, which yields exactly
`Astra_Admin_Settings`, so the spelling in the rule is correct. Re-check it if the vendor
ever renames the class.

## Drift check

Re-check when a new Astra release lands on a bench or a fleet site:

- `inc/core/class-astra-admin-settings.php` — the
  `add_action( 'admin_init', self::class . '::upgrade_to_pro_wc_notice' )` line and the
  class name's spelling. If the notice moves into `register_notices()` it becomes mixed
  output alongside the Starter Templates banner and the rule has to be re-derived
- `inc/extras.php` — `astra_showcase_upgrade_notices()`. If its readers ever shrink to just
  this notice, the mechanism 1 filter becomes viable and this document's rejection should be
  revisited
- `inc/class-astra-memory-limit-notice.php` and the two version-mismatch notices — if any of
  them is ever queued from the same callback as the upsell, **withdraw the rule**
- `admin/class-astra-bsf-analytics.php` — the `astra` entity registration. The telemetry nag
  is covered by the shared rule only while the theme keeps using `BSF_Analytics_Loader`
- **BSF churn applies.** See `bsf-vendors-need-extra-scrutiny`; this vendor moves hooks
  between releases

## Verification

Bench: `bench2.local`, WP 7.1, Astra theme 4.13.11 active, WooCommerce 11.1.0, **Astra Pro
not installed** — required, since `astra_showcase_upgrade_notices()` returns false as soon
as `ASTRA_EXT_VER` is defined, so the notice does not exist on a site that has Astra Pro.

No time gate: the notice has none, it shows on the listed screens immediately.

Surface: the WooCommerce admin screens only. Captured on **WooCommerce → Status**
(`admin.php?page=wc-status`), which is where Paul found it, asserting the screen rather
than trusting the URL.

### WooCommerce "Business Toolkit" upsell — **Confirmed**

| Check | Before | After |
|---|---|---|
| `astra-upgrade-pro-wc` in page HTML | 1 | **0** |
| `Business Toolkit` occurrences | 2 | **0** |
| `has_action( 'admin_init', 'Astra_Admin_Settings::upgrade_to_pro_wc_notice' )` | `true` | **`false`** |
| Debug log | — | `astra-theme: Removed Astra_Admin_Settings::upgrade_to_pro_wc_notice from admin_init.` |

### Negative checks

| Check | Result |
|---|---|
| `Astra_Admin_Settings::register_notices` still on `admin_init` | **yes** |
| `Astra_Admin_Settings::minimum_addon_version_notice` still hooked | **yes** |
| `Astra_Admin_Settings::minimum_addon_supported_version_notice` still hooked | **yes** |
| `astra_show_deprecated_admin_hooks_warnings` still on `admin_notices` | **yes** |
| Callbacks on `admin_init` | 91 → **90** — exactly one removed |
| Callbacks on `admin_notices` | 38 → **38** — unchanged |
| WooCommerce → Status renders | 200, screen asserted |
| PHP fatals / parse errors in `error.log` | **0** |

**A note on that callback count, because the first measurement was wrong.** The bench probe
originally reported `count( $wp_filter['admin_init']->callbacks, COUNT_RECURSIVE )`, which
showed a drop of **3** for a single removed hook and looked like collateral damage.
`COUNT_RECURSIVE` descends into each callback's `['function', 'accepted_args']` array — and
into the two elements of an array callback — so it never was a callback count. The probe now
counts callbacks per priority and sums them, which gives the 91 → 90 above. Any future
probe comparing hook counts should do the same; the recursive count is not comparable
between builds and is not a number worth recording.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_early_vendor_notices():
$this->unhook_astra_theme_wc_upsell();

public function unhook_astra_theme_wc_upsell() : void {
    // Case matters: _wp_filter_build_unique_id() keys a static callback by literal
    // string, so a mismatch here removes nothing and looks like success.
    $astra_wc_upsell_callback = 'Astra_Admin_Settings::upgrade_to_pro_wc_notice';

    if ( ! class_exists( 'Astra_Admin_Settings' ) ) {
        // Astra theme not active.
    } elseif ( false === has_action( 'admin_init', $astra_wc_upsell_callback ) ) {
        $this->log( 'astra-theme', '... not registered on admin_init; no action taken.' );
    } else {
        remove_action( 'admin_init', $astra_wc_upsell_callback );
        $this->log( 'astra-theme', 'Removed ... from admin_init.' );
    }
}
```
