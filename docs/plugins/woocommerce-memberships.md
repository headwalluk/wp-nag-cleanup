# WooCommerce Memberships

- slug: `woocommerce-memberships`
- version analysed: `1.30.1`
- source: `/vault/backups/wordpress/plugins/woocommerce-memberships/woocommerce-memberships,1.30.1.zip`
- licensing: premium (sold on woocommerce.com, by SkyVerge)
- Freemius bundled: no

## Analysis

Analysed on 26 Oct 2026 by Claude Code (Claude Opus 5).

Reported by Paul from a client site: a dismissible notice reading "Awesome! you've created
your first membership plan! Are you having a great experience with WooCommerce Memberships
so far? Please consider leaving a review! …", markup
`data-plugin-id="memberships" data-message-id="membership-plan-created"`.

This is a **SkyVerge plugin-framework milestone notice** (framework `v6_2_1` in 1.30.1).
`WC_Memberships::add_milestone_hooks()` (`class-wc-memberships.php`) registers two anonymous
closures. One on `wp_insert_post` calls
`Lifecycle::trigger_milestone( 'membership-plan-created', … )` when the first
`wc_membership_plan` is created. One on `wc_memberships_user_membership_saved` triggers
`user-membership-created`. `register_milestone_message()` banks the message in the option
`wc_memberships_milestone_messages`. `Lifecycle::add_admin_notices()` on `init` then renders
one stored milestone per request through the framework's admin notice handler:

```php
$message = apply_filters( 'wc_' . $this->get_plugin()->get_id() . '_milestone_message',
	$this->generate_milestone_notice_message( $message ), $id );

if ( $message ) {
	$this->get_plugin()->get_admin_notice_handler()->add_admin_notice( $message, $id, … );
	break;
}
```

`generate_milestone_notice_message()` builds only the review request: a random
exclamation, the milestone text, and the "leaving a review" / "reach out to our support team"
sentence. It returns `''` when the plugin has no reviews URL. **The milestone channel
carries nothing else.** Every milestone notice is a review request, so suppressing the whole
channel for this plugin ID removes no operational content.

The rest of the plugin's admin notices are operational and are left alone (below). There is
no dashboard widget and no vendor feed.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 12 single-line hits, none multiline. The framework notice handler, bootstrap, Blocks handler, setup wizard and plugin `add_admin_notices`, the loader's requirement notices, Memberships admin messages/tabs/bulk-action notices, and Action Scheduler's migration notice. Only the milestone path is promotional |
| Vendor opt-out filters | `wc_{plugin_id}_milestone_message`, i.e. `wc_memberships_milestone_message`, in `Plugin/Lifecycle.php`. The pattern grep did not match it, because the filter name is concatenated. Found by reading the renderer |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls from widgets | None. The only remote calls are Action Scheduler's async request and the framework's `api.wordpress.org` WooCommerce version check |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| "First membership plan created" review request | milestone `membership-plan-created`, rendered via `Lifecycle::add_admin_notices()` on `init` | suppress | Review begging. Says nothing about the state of the site |
| "First user membership created" review request | milestone `user-membership-created`, same path | suppress | Same generator, same content |

## Deliberately left alone

- **Free add-on merge notices** (`{add_on}-merged`: directory-shortcode, excerpt-length,
  role-handler, sensei-member-area). They tell the owner an add-on was deactivated and can
  be removed. Operational
- **Member Directory enabled** notice. It describes a feature now active on the site
- **Action Scheduler migration notice** (`wc-memberships-action-scheduler-migration`), about
  the 1.16.0 scheduled-actions bug. Operational
- **Checkout / Cart block incompatibility** (`Blocks_Handler`). It warns the plugin is not
  compatible with the block checkout in use. Operational
- **Setup wizard "Thanks for installing … read the documentation"** notice. Onboarding,
  linking to docs, not a sale or review. Ambiguous at worst, so it stays
- **Framework bootstrap update/deactivation notices** and the **loader's requirement
  notices** (PHP/WC version). Never suppressed
- **"Review" plugin-row action link** (`SV_WC_Plugin::plugin_action_links`). It is a link in
  the plugins list, not a notice, so it is out of scope
- **Other SkyVerge plugins.** The same framework ships in many WooCommerce extensions, each
  with its own `wc_{id}_milestone_message` filter. They are **not** covered by this rule.
  Add each plugin ID only after reading that plugin's own source

## Mechanism

- tier: 1 (vendor hook)
- phase: file scope, `add_filter( 'wc_memberships_milestone_message', '__return_empty_string' )`
- vendor registers at: `Lifecycle::add_hooks()` adds `add_admin_notices` on `init` (admin,
  non-AJAX). The filter is applied inside it, per stored milestone
- instance reachable via: N/A

Rejected alternatives:

- **Not used: unhooking the producers.** Both `add_milestone_hooks()` callbacks are anonymous
  closures, so they cannot be removed. They only produce the notice, and the notice is
  stored, so unhooking would miss already-nagged sites anyway
- **Not used: removing `Lifecycle::add_admin_notices`.** Reachable through
  `wc_memberships()->get_lifecycle_handler()`, but that is mechanism 2 where mechanism 1
  exists
- **Not used: mechanism 4** (editing `wc_memberships_milestone_messages`, or adding to the
  dismissed-notices user meta). The render filter needs no write

The filter name has no framework version in it (`v5_10_13`, `v5_12_1` and `v6_2_1` all
apply the same `wc_memberships_milestone_message`). Framework upgrades do not break it.

## Drift check

Re-check `vendor/skyverge/wc-plugin-framework/woocommerce/Plugin/Lifecycle.php` (before
1.27.1 it was `…/woocommerce/Lifecycle.php`). Two things to confirm:

1. `add_admin_notices()` still applies `'wc_' . get_id() . '_milestone_message'` and gates on
   a truthy result
2. `generate_milestone_notice_message()` still produces only the review request. If a
   milestone ever carries operational content, this rule must be narrowed to milestone IDs
   (the filter's second argument)

The vault confirms the same filter and gate in 1.24.0, 1.26.11 and every release from 1.27.1
to 1.30.1. 1.9.4 is older than the framework's milestone feature, which dates from SkyVerge
framework 5.1.0.

## Verification

- **`wc_memberships_milestone_message`: source-verified only.** It was read from 1.30.1 and
  checked against the vault back to 1.24.0. The client's report confirms the notice renders,
  but it has not yet been seen to disappear after deployment. Surface: the admin notice area,
  via the framework's `SV_WC_Admin_Notice_Handler::render_admin_notices` on `admin_notices`
  at 15. Structural probe: `data-plugin-id="memberships" data-message-id="membership-plan-created"`
- Bench repro: create a first `wc_membership_plan` on a fresh install, or set
  `wc_memberships_milestone_messages` to
  `{"membership-plan-created":"you've created your first membership plan!"}`. Then load any
  admin page and count the `data-message-id` probe. There is no time gate. The only gate is
  `wc_memberships_milestone_version`, which must not be newer than the milestone's `$since`
  (default `1.0.0`). `Lifecycle::init()` sets it to the current version only on an
  *upgrade* where it is empty. A fresh install leaves it empty, so it qualifies. A site
  that upgraded from a pre-milestone release does not
- Negative check: any other `js-wc-plugin-framework-admin-notice` with
  `data-plugin-id="memberships"` (e.g. the block-incompatibility notice) must still render

## Additions to `headwall-nag-cleanup.php`: 1 mechanism 1 filter

```php
// In register_vendor_optouts():
add_filter( 'wc_memberships_milestone_message', '__return_empty_string' );
```
