# Independent Analytics

- slug: `independent-analytics`
- version analysed: `2.15.5`
- source: `/vault/backups/wordpress/plugins/independent-analytics/independent-analytics,2.15.5.zip`
- licensing: freemium (free on wordpress.org, Pro sold by Independent Analytics)
- Freemius bundled: **yes**, SDK `2.13.4`

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5).

54 fleet sites. This is the only plugin audited so far whose promotional output actually
lands in the global admin notice area on core WordPress screens — everything else this
week kept its nagging to its own settings pages. It comes from the bundled **Freemius
SDK**, not from the plugin's own code.

**No rule is added**, and unlike the previous three audits that is a close call rather
than an obvious one. The reasoning is set out in full below because it is the strongest
candidate declined so far.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | None in the plugin's own code. **Freemius registers `admin_notices` and `network_admin_notices`** from `FS_Admin_Notice_Manager::_admin_notices_hook` |
| Vendor opt-out filters | Freemius filters are namespaced per module via `fs_apply_filter()`, so there is **no global switch**. None gates the opt-in notice |
| Vendor opt-out constants | `WP_FS__DEMO_MODE`, `WP_FS__DEV_MODE`, `WP_FS__SKIP_EMAIL_ACTIVATION` — development aids, not opt-outs |
| Dashboard widgets | 1: `iawp`. **Site data, not a nag** |
| Outbound calls from widgets | None. The widget reads local `Page_Statistics` |
| Freemius | Yes, SDK 2.13.4 |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Freemius opt-in / connect notice | `admin_notices` → `FS_Admin_Notice_Manager::_admin_notices_hook`, sticky id `connect_account` | suppress — **declined**, see below | *"We made a few tweaks to the plugin, Opt in to make "Independent Analytics" better!"* with Opt In / Skip buttons |
| `iawp` dashboard widget | `wp_dashboard_setup` | keep | Renders the site's own 30-day traffic chart and quick stats |

## Deliberately left alone

### The dashboard widget is the site's own analytics

`IAWP\Dashboard_Widget` renders `Page_Statistics` for the last thirty days — a chart and
quick stats for this site's own traffic. That is site data, in the same category as
Yoast's "Posts Overview". It makes no outbound request, and the plugin already exposes
its own switch (`iawp_disable_widget`) for owners who do not want it. Not a nag,
not ours to remove.

### The Freemius opt-in notice — declined, but it was close

This one is genuinely in scope on the face of it. Confirmed on a live install, it renders
on **every core admin screen except the dashboard**:

| Screen | Freemius notice block |
|---|---|
| Dashboard (`index.php`) | 0 |
| Posts (`edit.php`) | 1 |
| Settings (`options-general.php`) | 1 |
| Media (`upload.php`) | 1 |
| Users (`users.php`) | 1 |
| Plugins (`plugins.php`) | 1 |

Markup: `fs-notice updated success fs-sticky fs-has-title fs-slug-independent-analytics
fs-type-plugin`, with **Opt In** and **Skip** buttons. It is a usage-tracking consent
prompt sitting in the admin notice area of core screens — exactly the territory this
project claims.

It was declined on the available mechanisms, not on the classification:

- **No mechanism 1.** The SDK exposes no filter on this notice.
  `should_add_sticky_optin_notice()` is gated purely on internal state —
  `is_activation_mode()` and `! isset( $this->_storage->sticky_optin_added )`. Freemius's
  filters are namespaced per module through `fs_apply_filter()`, which prepends the
  plugin's unique affix, so even where filters exist there is no SDK-wide switch. A
  Freemius rule can only ever be per plugin slug
- **Mechanism 2 would be blanket.** `FS_Admin_Notice_Manager::instance( $id )` is
  reachable, but `_admin_notices_hook` renders *all* of that module's Freemius notices —
  licence state, trial expiry, update and error notices included. Unhooking it is the
  same blanket suppression rejected for Brainstorm Force's `astra-notices`, and for the
  same reason
- **`remove_sticky( 'connect_account' )` writes to the database.** It is reachable and
  would work, and because `sticky_optin_added` is already set the notice would not be
  re-added, so there would be no per-request churn. It was still declined: writing to a
  vendor's storage to change what it believes is the approach rejected for WPB Product
  Slider, and the objection holds here. It also leaves residue that removing this plugin
  would not undo

**Proportionality settled it.** The notice is added **once**, on activation, and a single
click on "Skip" removes it permanently. It is not a recurring nag — unlike the WP Desk
tracker prompt or the WPB review request, both of which return on a timer. Against that:
54 sites, a per-slug rule that generalises to nothing, and a choice between a database
write and blanket suppression.

**Revisit if** Freemius adds a filter on the opt-in notice, or if the number of
Freemius-bundling plugins on the fleet grows enough that a per-slug list starts to earn
its place. As of this survey it is one plugin — Autoptimize's apparent Freemius
bundling turned out to be a false positive.

### Note on what suppressing it would and would not achieve

Hiding the prompt does not opt the site in or out. Freemius stays in its un-opted-in
state either way, so no tracking payload is sent. Suppression would only hide the ask,
which is worth stating plainly: there is no privacy gain here, only a tidier notice area.

## Mechanism

- tier: N/A — no rule written
- phase: N/A
- vendor registers at: `FS_Admin_Notice_Manager::add()` → `add_action( 'admin_notices',
  array( &$this, '_admin_notices_hook' ) )`, from `Freemius::add_sticky_optin_admin_notice()`
- instance reachable via: `FS_Admin_Notice_Manager::instance( 'independent-analytics' )` —
  a static registry keyed by module id. Reachable; declined on mechanism and
  proportionality

## Drift check

Re-check when a new version appears in the vault:

- `freemius/includes/class-freemius.php` — `should_add_sticky_optin_notice()`. If a filter
  is introduced around it, this becomes a clean mechanism 1 rule
- `freemius/includes/managers/class-fs-admin-notice-manager.php` — currently contains no
  `apply_filters` at all. If one appears on the render path, revisit
- The bundled SDK version, currently 2.13.4
- `IAWP/Dashboard_Widget.php` — if the widget ever carries promotional content rather than
  site statistics

## Verification

Tested on `bench2.local` (WP 7.1) with Independent Analytics 2.15.5 active and
un-opted-in, over authenticated admin requests. No rule deployed. Zero PHP fatals.

## Additions to `headwall-nag-cleanup.php`: NONE
