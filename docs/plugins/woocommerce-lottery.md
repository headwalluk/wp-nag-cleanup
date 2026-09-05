# WooCommerce Lottery (wpgenie)

- slug: `woocommerce-lottery`
- version analysed: `1.1.21`
- source: `/vault/backups/wordpress/plugins/woocommerce-lottery/woocommerce-lottery,1.1.21.zip`
- licensing: premium (sold by wpgenie.org)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a live
client site.

**1 fleet site.** The audit was run mainly to test a hypothesis: that `wpgenie` might be a
shared framework across several plugins, the way `themeisle-sdk`, `plugin-fw`,
`bsf-analytics` and the WP Desk packages turned out to be. **It is not** — see below. The
rule therefore covers exactly one site.

One rule added: a vendor catalogue dashboard widget that pulls an RSS feed on every
dashboard render.

### The framework hypothesis — tested and negative

Every one of the 1,129 distinct slugs installed across the fleet was checked by opening
its newest vault release and testing the file list for `wpgenie`. Exactly one matched:

| Plugin | Sites |
|---|---|
| `woocommerce-lottery` | 1 |

Notably `woocommerce-lottery-pick-number` (1 site), from the same vendor, does **not**
bundle the dashboard class. `wpgenie` is a vendor name here, not a shared SDK, so there is
no framework-wide win. Recorded so the question is not re-opened.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 2, **both operational** |
| Vendor opt-out filters | **None.** `class-wpgenie-dashboard.php` contains no `apply_filters` and no constant check |
| Vendor opt-out constants | None |
| Dashboard widgets | **1**: `wpgenie_dashboard_products_news`, default `normal` context |
| Outbound calls from widgets | `fetch_feed( 'https://wpgenie.org/tag/dashboard/feed/' )`, cached in the `wpgenie_feed` transient |
| Freemius | Not bundled |

## Findings

| Item | Hook / ID | Verdict | Reason |
|---|---|---|---|
| "wpgenie.org - Our latest themes and plugins" | `wp_dashboard_setup` → `wpgenie_dashboard_products_news` | **suppress** | Vendor catalogue feed. No site state, and an outbound RSS fetch per render |
| WooCommerce required | `admin_notices` → `wc_lottery_error_notice` | keep | Missing dependency — the plugin cannot work without WooCommerce |
| Cron job recommended | `admin_notices` → `woocommerce_simple_lottery_admin_notice` | keep | **Operational and important**: without the cron job, finished lotteries are never closed |

## Deliberately left alone

### The cron notice is the opposite of a nag

> Woocommerce Lottery recommends that you set up a cron job to check for finished
> lotteries: `<site>/?lottery-cron=check`. Set it to every minute.

This looks like a persistent nag — it reappears until dismissed via a user-meta flag — but
it reports a real and consequential gap in the site's configuration. Without that cron
job, lotteries run past their end date and never resolve. On a hosting fleet this is
exactly the kind of notice that must survive.

### The dependency notice

`wc_lottery_error_notice` prints only when WooCommerce is inactive, and only on
`plugins.php` (`$current_screen->parent_base == 'plugins'`). A dependency notice, kept.

## Mechanism

- tier: 3 (dashboard widget removal)
- phase: `wp_dashboard_setup`, priority 999
- vendor registers at: `Wpgenie_Dashboard::__construct` adds `dashboard_widget_setup` to
  `wp_dashboard_setup` at default priority 10, so our 999 runs after it
- instance reachable via: **nothing.** The class file ends with a bare
  `new Wpgenie_Dashboard();` and discards the return value — the same pattern as WPB
  Product Slider and Brainstorm Force's analytics loader. Mechanism 3 does not need the
  instance, so this does not matter here
- context: `normal`. `wp_add_dashboard_widget()` is called with three arguments, so the
  context defaults

Removing the meta box means `dashboard_products_news()` never runs, so the
`wpgenie.org/tag/dashboard/feed/` request is never made.

## Drift check

Re-check when a new version appears in the vault:

- `admin/class-wpgenie-dashboard.php` — the widget ID `wpgenie_dashboard_products_news`
  and the default context. If either changes the rule silently stops matching
- If the vendor ever adds a filter around the widget, promote this to mechanism 1
- If `wpgenie` appears in a second fleet plugin, re-run the framework check — the negative
  result above is only true for the releases currently in the vault
- `woocommerce_simple_lottery_admin_notice` — must remain untouched

## Verification

Tested on `bench2.local` (WP 7.1) with WooCommerce Lottery 1.1.21 and WooCommerce active,
A/B with the rule enabled and disabled, over authenticated admin requests:

| Check | Rule off | Rule on |
|---|---|---|
| `id="wpgenie_dashboard_products_news"` on the dashboard | **1** | **0** |
| `wpgenie.org` occurrences on the dashboard | **10** | **0** |
| Cron-job advice notice on `plugins.php` | present | **still present** |
| PHP fatals | 0 | 0 |

The `wpgenie.org` count dropping from ten to zero is the feed content itself disappearing,
which confirms the render callback — and therefore the outbound fetch — never runs.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 3

```php
[
	'widget_id' => 'wpgenie_dashboard_products_news',
	'context'   => 'normal',
	'vendor'    => 'WooCommerce Lottery 1.1.21',
	'reason'    => 'wpgenie.org latest themes and plugins; RSS feed fetched on render',
],
```
