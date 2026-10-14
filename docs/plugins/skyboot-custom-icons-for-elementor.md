# Skyboot Custom Icons for Elementor

- slug: `skyboot-custom-icons-for-elementor`
- version analysed: `1.2.0`
- source: `/vault/backups/wordpress/plugins/skyboot-custom-icons-for-elementor/skyboot-custom-icons-for-elementor,1.2.0.zip`
- licensing: freemium in intent. The free plugin ships the PRO UI locked; a separate PRO
  add-on (not yet released, "Coming Soon") unlocks it via `SKB_CIFE_PRO_VERSION` or the
  `skb_cife_is_pro` filter
- Freemius bundled: no
- vendor: Skybootstrap

## Analysis

Analysed on 14 Oct 2026 by Claude Code (Claude Opus 5).

Reported by Paul from a client site: two notices in the admin notice area. One is a
*"Skyboot Pro is Coming Soon!"* waitlist banner with a *Join Waitlist* button. The other is
an *"Enjoying Skyboot Custom Icons?"* review request with *Yes, You Deserve It!*, *Already
Rated!*, *Remind Me Later* and *No Thanks* buttons. Both are new in 1.2.0, which added
`includes/class-admin.php` ("Admin marketing, onboarding & engagement"). 1.0.8 to 1.1.1
register only the three dependency notices in `Skb_Cife_Base`.

- **Waitlist notice**: `Skb_Cife_Admin::upgrade_notice`, on `admin_notices`. Shown to
  `manage_options` users on the Dashboard, the Plugins screen and the plugin's own
  `skyboot_icons*` pages, unless PRO is active or the user has dismissed it (user meta
  `skb_cife_upgrade_notice_dismissed`)
- **Review request**: `Skb_Cife_Admin::review_notice`, on `admin_notices`. Shown on
  `index.php` only, to `manage_options` users, 7 days after `skb_review_start_time`.
  *Remind Me Later* brings it back 3 days later. *No Thanks* (`skb_review_status = no_thanks`)
  is **reset on every plugin version change** by `maybe_record_install_time()`, so the nag
  returns with each update

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 5. `Skb_Cife_Admin`: `upgrade_notice`, `review_notice`. `Skb_Cife_Base`: `admin_notice_for_missing_elementor`, `admin_notice_for_minimum_elementor_version`, `admin_notice_for_minimum_php_version`. The multiline `add_action(` pass found nothing more. No `in_admin_header` or `admin_print_footer_scripts` |
| Vendor opt-out filters | None for the notices. `skb_cife_is_pro` exists, but it is the PRO licence gate (see below) |
| Vendor opt-out constants | None. `SKB_CIFE_PRO_VERSION` is the PRO add-on's presence marker, not an opt-out |
| Dashboard widgets | 1. `skb_cife_dashboard_widget`, "Skyboot Custom Icons" |
| Outbound calls from widgets | None. The widget's "News & Updates" list is hard-coded. The only `wp_remote_post` is `subscribe_to_mailmint()`, which posts an email address to `skybootstrap.com` only when an admin ticks consent on the onboarding form |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| "Skyboot Pro is Coming Soon!" waitlist | `admin_notices` → `Skb_Cife_Admin::upgrade_notice` | suppress | Generic upgrade banner for a product that does not exist yet. Nothing about the site |
| "Enjoying Skyboot Custom Icons?" review request | `admin_notices` → `Skb_Cife_Admin::review_notice` | suppress | Review begging |
| Elementor missing / not activated | `admin_notices` → `Skb_Cife_Base::admin_notice_for_missing_elementor` | keep | Missing dependency |
| Minimum Elementor version | `admin_notices` → `Skb_Cife_Base::admin_notice_for_minimum_elementor_version` | keep | Version requirement |
| Minimum PHP version | `admin_notices` → `Skb_Cife_Base::admin_notice_for_minimum_php_version` | keep | PHP version warning |
| "Skyboot Custom Icons" dashboard widget | `skb_cife_dashboard_widget` | keep (for now) | Mixed: see below |
| Activation redirect to the welcome page | `admin_init` → `Skb_Cife_Admin::maybe_redirect` | keep | Not a notice. One-shot, skipped on bulk activation |

## Deliberately left alone

- **The three `Skb_Cife_Base` dependency notices.** Elementor missing, Elementor too old,
  PHP too old. All operational. They are callbacks on a different class, so a rule matching
  `Skb_Cife_Admin` cannot reach them
- **The dashboard widget, `skb_cife_dashboard_widget`.** It is mixed, and has no outbound
  call. Contents: an "Icon sets enabled N/16" count, a static "14,300+ icons available"
  figure, a *Manage icon sets* button, three hard-coded changelog lines, and (when PRO is
  not active) a "Skyboot Pro Coming Soon — Join Waitlist →" box. The count is a settings
  summary, not a problem report, so a case can be made for removing the whole widget, as
  was done for Elementor's `e-dashboard-overview`. It is held back pending Paul's call
  because Paul did not report it, it makes no outbound request, and removing it would also
  remove the count and the settings shortcut. If it is added later, it goes in
  `PROMOTIONAL_DASHBOARD_WIDGETS` with context `normal`
- **The activation redirect** to `admin.php?page=skyboot_icons_welcome`. That page carries
  an optional email opt-in. It is the vendor's own screen and out of scope by construction.
  The redirect is one-shot (`skb_cife_activation_redirect` transient)
- **The Go Pro page, PRO-locked settings tabs, plugin action links and row meta.** Vendor's
  own screens and the plugin list, not the notice area
- **`skb_cife_is_pro` / `SKB_CIFE_PRO_VERSION`, not used.** Returning true does hide the
  waitlist notice and the widget's PRO box, but only because it tells the plugin the PRO
  add-on is active, which unlocks the locked PRO UI. That defeats premium software and is
  out of bounds whatever it hides
- **`pre_option_skb_review_status` answering `rated`, not used.** It would gate the review
  request, but `maybe_record_install_time()` also reads that option on every `admin_init`.
  The unhook leaves the vendor's own state alone and is available, so it wins

## Mechanism

- tier: 2 (targeted unhook) through `remove_discarded_instance_callback()`, the sanctioned
  `$wp_filter` reader
- phase: `admin_init` @ `self::LATE_PRIORITY` (999), from `unhook_vendor_notices()`
- vendor registers at: `skyboot-custom-icons-for-elementor.php`, inside `if ( is_admin() )`:
  `add_action( 'plugins_loaded', function () { new Skb_Cife_Admin(); } );`. The constructor
  adds both callbacks to `admin_notices` at priority 10. This is all done well before
  `admin_init`
- instance reachable via: **nothing.** The object is created inside an anonymous closure
  and discarded. `Skb_Cife_Admin` has no singleton accessor, and `Skb_Cife_Base::instance()`
  does not hold it. Only its own hook callbacks keep it alive

`Skb_Cife_Admin` is in the global namespace. It is a distinctive name, and the matcher
needs both the class (by `instanceof`) and the method name.

## Drift check

```bash
for PLUGIN_ZIP in $(ls -1 /vault/backups/wordpress/plugins/skyboot-custom-icons-for-elementor/*.zip | sort -V); do
  echo "$(basename "${PLUGIN_ZIP}") admin=$(unzip -p "${PLUGIN_ZIP}" '*/includes/class-admin.php' 2>/dev/null | command grep -c "class Skb_Cife_Admin\|'upgrade_notice'\|'review_notice'") boot=$(unzip -p "${PLUGIN_ZIP}" '*/skyboot-custom-icons-for-elementor.php' | command grep -c 'new Skb_Cife_Admin();')"
done   # 1.2.0: admin=3 boot=1. 1.0.8–1.1.1: admin=0 boot=0, no promos exist
```

- If `Skb_Cife_Admin` gains a singleton, or the main file starts keeping the instance,
  switch to a plain `remove_action()` on that instance
- If the class or method is renamed, the rule finds nothing and logs
  `Skb_Cife_Admin::upgrade_notice not registered on admin_notices`. That only fires when
  the class exists, so it is the drift signal
- If the PRO add-on ships, re-read `Skb_Cife_Pro::is_active()`: a licence notice from the
  add-on would be operational and must not be caught by this rule

## Verification

### Waitlist notice and review request — **Confirmed** (bench, HTTP)

Bench: `bench1.local`, WP 7.1, Skyboot **1.2.0** unzipped from the vault, no Elementor,
14 Oct 2026. quick-2fa was inactive on bench1, so this was a real authenticated HTTP
capture with a minted admin cookie, not the wp-cli harness. Before = bench1's installed
1.39.0 (no Skyboot rule). After = working copy (1.41.0), with `HEADWALL_NAG_CLEANUP_DEBUG`
from a temporary `mu-plugins/00-nag-debug-temp.php`. Three warm-up requests after the deploy.

Time gate: `skb_review_start_time` was backdated 8 days with `wp option update`, and
`skb_review_status` was seeded as `pending`, which is what a fresh install writes. Then
`wp cache flush`. The activation-redirect transient was deleted before the first capture.

| Check | Before | After |
|---|---|---|
| Dashboard asserted: `id="dashboard-widgets"` | 1 | 1 |
| Plugins asserted: `id="the-list"` | 1 | 1 |
| Waitlist `skb_cife_dismiss_notice=1`, dashboard / plugins | **1 / 1** | **0 / 0** |
| Review `id="skb-review-notice"`, dashboard | **1** | **0** |
| Debug log | — | `skyboot-custom-icons-for-elementor: Removed Skb_Cife_Admin::upgrade_notice from admin_notices priority 10.` and `… ::review_notice …` |

### Negative checks

| Check | Result |
|---|---|
| Elementor-missing notice (`plugin=elementor` install link), dashboard / plugins | 1 / 1 before, **1 / 1 after** |
| `skb_cife_dashboard_widget` meta box | 1 before, 1 after (deliberately kept) |
| `skb_review_status` after the rule | still `pending`. Nothing written |
| `skb_cife_upgrade_notice_dismissed` user meta | absent before and after |
| PHP fatals / warnings in `error.log` | **0** |

Bench left as found: plugin deactivated and deleted. Its four leftover options
(`skb_cife_version`, `skb_review_start_time`, `skb_review_status`, `skb_review_version`) and
the `skyboot_custom_icons` administrator capability were removed. The temp mu-plugin was
removed and the bench's 1.39.0 nag-cleanup file restored. The table, option and cron
snapshots match.

### Live — not yet checked

On the client site where Paul first saw it.

## Additions to `headwall-nag-cleanup.php`: 1 rule method, mechanism 2

```php
// In unhook_vendor_notices():
$this->unhook_skyboot_icons_promos();

public function unhook_skyboot_icons_promos() : void {
	$this->remove_discarded_instance_callback( 'admin_notices', 'Skb_Cife_Admin', 'upgrade_notice', 'skyboot-custom-icons-for-elementor' );
	$this->remove_discarded_instance_callback( 'admin_notices', 'Skb_Cife_Admin', 'review_notice', 'skyboot-custom-icons-for-elementor' );
}
```
