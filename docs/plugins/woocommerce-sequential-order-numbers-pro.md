# Sequential Order Numbers Pro

- slug: `woocommerce-sequential-order-numbers-pro`
- version analysed: `1.21.8`
- source: `/vault/backups/wordpress/plugins/woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro,1.21.8.zip`
- licensing: premium (SkyVerge, sold on woocommerce.com)
- Freemius bundled: no

## Analysis

Analysed on 26 Oct 2026 by Claude Code (Claude Opus 5), as part of the SkyVerge sweep. The
other SkyVerge plugins and the framework milestone channel are in [skyverge.md](skyverge.md).
This plugin triggers no milestones.

`src/Admin/Onboarding_Tips.php` is constructed in `class-wc-seq-order-number-pro.php` as a
bare `new …\Admin\Onboarding_Tips( $this )`, so the instance is discarded. It hooks
`add_onboarding_notices` on `admin_notices`. That one callback switches on the option
`wc_sequential_order_numbers_pro_onboarding_status` (default `onboard`):

- `onboard`, off the settings screen: notice `wc_sequential_order_numbers_pro_onboarding_status_prompt`,
  "Thanks for purchasing … Would you like to set up the plugin now?" with "Yes, let's go!"
  and "Not now". It starts the pointer tour of the settings
- `complete`: notice `wc_sequential_order_numbers_pro_onboarding_status_complete`, "Nice work!
  You're all set up … sign up for our newsletter!" with "Sign me up!". On click, the browser
  POSTs the admin's email to `https://api.jilt.com/v2/shops/…/customer_sessions` with
  `accepts_marketing: true`
- `dismissed`, `subscribed`, `updated`: nothing

`complete` is written only by `complete_onboarding()` (AJAX, finishing the tour without
subscribing). `Lifecycle` writes `updated` on upgrade, so only fresh installs that walk the
tour see the newsletter.

Vendor bug, recorded and not acted on: on subscribe, `complete_onboarding()` dismisses
`…_completed`, but the notice ID is `…_complete`.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | Loader `admin_notices` (requirement notices), `Onboarding_Tips::add_onboarding_notices`, framework handler |
| Vendor opt-out filters | None for onboarding. The framework's `wc_sequential_order_numbers_pro_milestone_message` exists but nothing triggers a milestone |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls | Jilt newsletter POST, browser-side, on click only |
| Freemius | None |

## Findings

| Item | Hook / ID | Verdict | Reason |
|---|---|---|---|
| Newsletter sign-up | `wc_sequential_order_numbers_pro_onboarding_status_complete` | suppress | Newsletter sign-up. No operational value |
| Set-up prompt | `wc_sequential_order_numbers_pro_onboarding_status_prompt` | keep | Onboarding for a plugin just bought. Leads to configuring the store's order numbers |

## Deliberately left alone

- **The set-up prompt and its pointer tour.** They are onboarding into the plugin's own
  settings, not promotion
- **Loader requirement notices** (`bad_environment`, `update_wordpress`, `update_woocommerce`)

## Mechanism

- tier: 1, answered through core's `option_{name}` filter (like the `‡` rules in the index)
- phase: file scope, `add_filter( 'option_wc_sequential_order_numbers_pro_onboarding_status', [ $this, 'answer_sequential_order_numbers_onboarding_status' ] )`
- behaviour: a stored `complete` is read as `updated`, the vendor's own status for
  "onboarding over without a sign-up". Every other value passes through. Nothing is written
- vendor registers at: `Onboarding_Tips::__construct()`, from `WC_Seq_Order_Number_Pro::init_admin()` on `admin_init` at 0
- instance reachable via: not reachable (discarded), and not needed

Rejected alternatives:

- **Not used: unhooking `add_onboarding_notices`.** That is the same callback as the set-up
  prompt, and its instance is discarded, so it would also need the `$wp_filter` reader
- **Not used: the framework notice handler.** `SV_WC_Admin_Notice_Handler` applies no
  filter. Its dismissals are one array in user meta `_wc_plugin_framework_sequential_order_numbers_pro_dismissed_messages`,
  so answering it would mean merging into the stored array
- **Not used: `dismissed` or `subscribed` as the answer.** Either would also suppress the
  notice, but each claims something the merchant did not do

Only the option name and the `complete` / `updated` values come from the vendor. Readers of
the status: `add_onboarding_notices()` and `should_onboard()`, which tests only `onboard`.

## Drift check

In `src/Admin/Onboarding_Tips.php`, re-check four things:

- `ONBOARDING_COMPLETED = 'complete'` and `ONBOARDING_UPDATED = 'updated'`
- the `elseif ( self::ONBOARDING_COMPLETED === $onboarding_status )` newsletter branch
- `get_onboarding_status_option_key()` is still `wc_%s_onboarding_status`
- `get_onboarding_status()` has no new callers

All four are identical in every vault version, 1.20.3 to 1.21.8.

## Verification

- **Source-verified only.** Bench repro: `update_option( 'wc_sequential_order_numbers_pro_onboarding_status', 'complete' )`,
  then load any admin page and probe
  `data-message-id="wc_sequential_order_numbers_pro_onboarding_status_complete"`. There is no time gate
- Negative check: with the option deleted (status defaults to `onboard`), the
  `…_onboarding_status_prompt` notice must still render on the plugins screen

## Additions to `headwall-nag-cleanup.php`: 1 core-filter answer and 1 method

```php
add_filter( 'option_wc_sequential_order_numbers_pro_onboarding_status', [ $this, 'answer_sequential_order_numbers_onboarding_status' ] );
```
