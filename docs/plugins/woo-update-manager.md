# WooCommerce.com Update Manager

- slug: `woo-update-manager`
- version analysed: `1.0.3`
- source: `/vault/backups/wordpress/plugins/woo-update-manager/woo-update-manager,1.0.3.zip`
- licensing: free (Automattic; a delivery mechanism for paid Woo.com subscriptions)
- Freemius bundled: no

## Analysis

Analysed on 16 Sep 2026 by Claude Code (Claude Opus 5), as part of the `NONE` sweep.

20 fleet installs. **Two PHP files, and every checklist pass returned empty.** No notice
registrations, no dashboard widget, no outbound call of its own, no tracking, no
promotional string.

The plugin exists to route update packages for Woo.com subscription products. Its entire
hook surface is two lines: `before_woocommerce_init` to declare HPOS compatibility, and a
filter on `update_woo_com_subscription_details` that rewrites a plugin package URL.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **0** |
| `in_admin_header` / `admin_print_footer_scripts` registrations | **0** |
| Multiline `add_action(` form | **0** |
| Vendor opt-out filters | None present |
| Vendor opt-out constants | None |
| Dashboard widgets | **0** |
| Outbound calls | **0** of its own; it rewrites the package URL WooCommerce then fetches |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| — | — | — | Nothing found on any surface this project touches |

## Deliberately left alone

Nothing was found to leave alone.

Worth stating explicitly, because the plugin name invites suspicion: this is **update and
licence infrastructure**, and `CLAUDE.md` puts that firmly on the keep side — "a premium
plugin that has stopped receiving security updates because the licence lapsed is a hosting
problem". Anything this plugin ever starts saying about a Woo.com subscription's state
would be operational by definition.

Note that the subscription *nags* a fleet site sees about Woo.com — connect prompts,
expiry warnings — come from **WooCommerce itself and the Woo Helper**, not from this
plugin. Those have not been audited yet and belong in a `woocommerce` document; do not
assume this NONE result covers them.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: file scope (`before_woocommerce_init`) and
  `Automattic\WooUpdateManager\Woo_Subscription_Data_Updater::load()`
- instance reachable via: N/A — the one filter callback is static

## Drift check

Re-check when a new version appears in the vault:

- Only one version is in the vault (1.0.3). If Automattic folds connect-prompt or
  subscription-expiry messaging into this plugin, it would appear as a first
  notice-hook registration — and would still need the boundary test, because expiry
  warnings stay

## Verification

Source-verified against the vault release. No rule, so nothing to bench.

## Additions to `headwall-nag-cleanup.php`: NONE
