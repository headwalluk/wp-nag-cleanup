# Element Pack Pro (BdThemes)

- slug: `bdthemes-element-pack`
- version analysed: `7.11.2`
- source: `/vault/backups/wordpress/plugins/bdthemes-element-pack/bdthemes-element-pack,7.11.2.zip`
- licensing: premium (the vault copy is the Pro build; there is a free `element-pack-lite` on wordpress.org)
- Freemius bundled: no — BdThemes ship their own SDKs, see below

## Analysis

Analysed on 9 Sep 2026 by Claude Code (Claude Opus 5), prompted by a report of a
supply-chain attack on a BdThemes admin API call. Analysed together with
`ultimate-post-kit`, the only other BdThemes slug in the vault.

1 fleet site, running exactly 7.11.2 with the plugin active.

**Two rules added**, both `admin_notices` unhooks. Every notice the plugin raises
*itself* — licence, template-library deprecation, Elementor mini-cart conflict — is
operational and left alone. Everything suppressed comes from a bundled SDK, not from
Element Pack's own code.

**No dashboard widget rule.** `bdt-ep-dashboard-overview` is declared but unreachable in
7.11.2 — see "The feed widget never registers" below. That was caught on a live fleet
site, after a first pass through the source had recorded it as registering
unconditionally.

### The shared BdThemes admin layer

This is the point of the audit. Three components recur across the BdThemes range, and
each is a separate `require` in the plugin's main file:

| Component | Path | Purpose | In Ultimate Post Kit? |
|---|---|---|---|
| feedback-hub | `includes/feedback-hub/` | Review nag, 3-day gate | Yes, forked and renamed |
| DCI insights | `dci/` | Usage-tracking opt-in, posts to `analytics.bdthemes.com` | No |
| admin-feeds | `admin/admin-feeds.php` | "News & Updates" dashboard widget | Yes |

feedback-hub is the same file in both plugins, but the class is renamed per plugin:
`RC_Reviews_Collector` here, `Ultimate_Post_Kit_Reviews_Collector` there. **A single
class-name rule does not cover the range** — each BdThemes plugin needs its own name
confirmed from source. That is the opposite of the QuadLayers and YITH results.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | **6** — 4 from the plugin itself, 2 from bundled SDKs |
| Vendor opt-out filters | **None** for notices, widgets or tracking |
| Vendor opt-out constants | `BDTEP_WL` (white label) and `BDTEP_HIDE` only; neither touches notices |
| Dashboard widgets | **1 declared, 0 reachable** — `bdt-ep-dashboard-overview` is never registered, see below |
| Outbound calls from widgets | None reached. The `dashboard.bdthemes.io` product feed and `bdthemes.com/feed` RSS are in dead code |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Review request | `admin_notices` → `RC_Reviews_Collector::display_global_notice` | **suppress** | "Give us your Review", links to `bdt.to/element-pack-elementor-addons-review`. Pure review begging |
| Usage-tracking opt-in | `admin_notices` → `Insights_SDK::display_global_notice` | **suppress** | "share non-sensitive plugin data … receive valuable emails periodically". A tracking opt-in prompt, named in the boundary rule |
| Element Pack News & Updates | `bdt-ep-dashboard-overview` (context `column4`) | **no rule** | The widget is never registered in this build. Dead code, nothing to remove |
| Licence not activated | `admin_notices` → `Notices::show_notices`, id `license-error` | **keep** | Licence state. Explicitly protected |
| Activate your licence for updates | id `license-issue` | **keep** | "activate to receive updates". Explicitly protected |
| Elementor mini-cart conflict | id `ep-el-use-mini-cart` | **keep** | Plugin conflict warning. Explicitly protected |
| Template library deprecation | id `template-library-issue` | **keep** | Tells the owner a feature is going away and which setting to move to. True and actionable |
| DCI modal on the settings page | `in_admin_header` closure, priority 99999 | **keep** | Only fires when `current_page === menu_slug`, i.e. on BdThemes' own settings screen — out of scope by construction. It is also a closure, so nobody can remove it |

## Deliberately left alone

### The feed widget never registers — the guard is inverted

`admin/admin-feeds.php` ends with:

```php
if ( ! function_exists( 'element_pack_pro_activated' ) ) {
	new Element_Pack_Admin_Feeds();
}
```

and `element_pack_pro_activated()` is **defined unconditionally** at
`bdthemes-element-pack.php:57`, inside `bdthemes_element_pack_load_plugin()` on
`plugins_loaded` priority 9. That runs *before* the same function requires `loader.php`,
which requires `admin/admin.php`, which requires `admin-feeds.php`. So by the time line
157 is reached `function_exists()` is always true, the class is never constructed,
`wp_dashboard_setup` never gets the callback, and neither
`dashboard.bdthemes.io/wp-json/bdthemes/v1/product-feed/` nor `bdthemes.com/feed` is ever
fetched.

The only path that skips the definition is the early `! did_action( 'elementor/loaded' )`
return — but that returns before `loader.php` too, so `admin-feeds.php` is never included
at all. There is no configuration of 7.11.2 in which this widget appears. It reads like a
vendor slip: the guard almost certainly meant to *call* `element_pack_pro_activated()`
rather than test for its existence.

Confirmed on a fleet site running 7.11.2 with Element Pack active since April 2024: the
widget id appears in neither administrator's `meta-box-order_dashboard`, while every
other registered widget does — including `hwh_widget` in the same `column4` context, and
Elementor's `e-dashboard-overview`.

**Recorded rather than ruled on**, because a one-line vendor fix brings it back. If a
later release corrects the guard, add:

```php
[
	'widget_id' => 'bdt-ep-dashboard-overview',
	'context'   => 'column4',
	'vendor'    => 'Element Pack Pro (bdthemes-element-pack) <version>',
	'reason'    => 'Element Pack News & Updates; fetches dashboard.bdthemes.io and bdthemes.com/feed on render',
],
```


**All four of Element Pack's own notices are operational.** `Notices::add_notice()` is
called from exactly four places and every one passes the boundary test. Two are licence
notices, which the boundary rule names explicitly: a lapsed Element Pack licence stops
security updates on a plugin with a large widget surface, and hiding that would be
dangerous. One is an Elementor Pro / Element Pack mini-cart conflict warning. The fourth
warns that the old template library is being retired and points at the setting that
replaces it — commercial in tone, but it is a deprecation notice, and a site owner who
never sees it loses a feature without explanation.

**The `Notices` framework itself is left in place.** It has a real `get_instance()` that
*is* called, so removing `Notices::show_notices` from `admin_notices` would be a one-line
rule — and it would take all four operational notices with it. Suppressing a framework
because its renderer is reachable is the blanket-suppression shortcut the project
forbids. The two SDK notices are removed individually instead.

**The DCI SDK sends nothing before consent.** Worth recording, because the obvious
assumption is wrong. `Insights_SDK::__construct` only reaches `data_prepare()` — the path
that calls `dci_send_data_to_server()` against
`https://analytics.bdthemes.com/wp-json/dci/v1/data-insights` — once
`dci_allow_status_*` is `yes` and the monthly date has passed. Until the owner answers,
it draws a notice and posts nothing. The rule removes the prompt; it does not need to
block a transmission, because there is not one.

**Wincher is not a nag.** `includes/wincher/` talks to `api.wincher.com`, but only behind
an OAuth flow the site owner starts. Left alone.

**No `Insights_SDK` collision seen, but the name is generic.** The class is declared
inside `if ( ! class_exists( 'Insights_SDK' ) )` with no vendor prefix. Nothing else in
the vault corpus declares it, and the rule matches class *and* method
(`display_global_notice`) *and* hook, so a collision needs all three. Recorded here as a
known sharp edge rather than a reason not to write the rule.

## Mechanism

- tier: 2 (targeted unhook), both rules
- phase: `admin_init` at `self::LATE_PRIORITY`
- vendor registers at: `admin_init` **priority 10** — `bdthemes-element-pack.php` hooks
  `rc_ep_pro_plugin` and `dci_plugin_element_pack_pro` there, and each constructor adds
  its `admin_notices` callback. 999 beats 10, so the normal late pass is correct and
  `EARLY_PRIORITY` is not needed
- instance reachable via: **nothing — this needs `find_instance_callback()`.** Both SDKs
  declare a `get_instance()` and then never call it: `rc_sdk_automate()` and
  `dci_sdk_insights()` both do `new …( $params )` and discard the object, leaving the
  static holder null. Calling `get_instance()` ourselves would *construct a second
  object*, which is worse than doing nothing. There is no opt-out filter, the callbacks
  are not static, and neither is a dashboard widget, so mechanisms 1 to 3 are genuinely
  all unavailable

## Drift check

- `includes/feedback-hub/notice.php` — the `RC_Reviews_Collector` class name and the
  `display_global_notice` method. BdThemes have already renamed this class once per
  plugin, so treat a rename as likely, not hypothetical
- `dci/insights.php` — the `Insights_SDK` class name and `display_global_notice`
- `admin/admin-feeds.php` — **the `function_exists( 'element_pack_pro_activated' )` guard
  at the foot of the file.** If it becomes a call, or the definition moves after the
  require, the widget goes live and needs the mechanism 3 entry above. The `column4`
  context and the `bdt-ep-dashboard-overview` id are both already confirmed
- If either SDK gains a real singleton (`get_instance()` actually called, or the object
  stored on a global), drop the `find_instance_callback()` use and name it directly
- BdThemes ship Prime Slider, Ultimate Store Kit, ZoloBlocks and Pixel Gallery, none of
  which are in the vault. If one arrives, its feedback-hub class name must be read from
  source — do not assume `RC_Reviews_Collector`

## Verification

**Source-verified against 7.11.2.** The tracking opt-in rule is confirmed against a real
render on a live fleet site; the review nag rule is not yet verified. Neither is
bench-verified — the plugin is not installed on any bench.

### The tracking opt-in: confirmed live, 9 Sep 2026

Both SDKs are gated on an option the site owner had already answered — `rc_allow_*` and
`dci_allow_status_*` both read `disallow`, so both constructors returned early and never
reached `add_action( 'admin_notices', … )`. Nothing was on the hook, so the site could
not verify anything as it stood.

Paul cleared both options, flushed the cache, removed `headwall-nag-cleanup.php`, and the
notice appeared immediately. Its markup identifies it exactly:

```html
<div class="dci-global-notice dci-notice-data notice notice-success is-dismissible dci_element_pack_pro">
  … <h3>Love using Element Pack Pro? Congrats 🎉  ( Never miss an Important Update )</h3>
  <p>Be Top-contributor by sharing non-sensitive plugin data …</p>
  <input type="hidden" name="dci_allow_name" value="dci_allow_status_dci_element_pack_pro_…">
  <button name="dci_allow_status" value="yes"  class="dci-button-allow">Yes, I'd Love To Contribute</button>
  <button name="dci_allow_status" value="skip" class="dci-button-skip">Skip For Now</button>
  <button name="dci_allow_status" value="disallow" class="dci-button-disallow …">No Thanks</button>
```

Restoring the file removed it. That is `Insights_SDK::display_global_notice`, reached
through `notice_modal()`'s non-settings-page branch — **the `Insights_SDK` rule is
verified end to end against a real render**, including that
`find_instance_callback()` matches a discarded instance on a live site.

### The review nag is still unverified, and that A/B could not have shown it

Worth being precise about, because the run looks like it covered both rules and does not.
Deleting `rc_allow_*` also deleted `rc_date_<name>_installed`, so the SDK constructor
rewrote it with `time()` on the next admin load — observed going from `1714396029`
(29 Apr 2024) to `1788953727` (9 Sep 2026, 11:35 UTC). The next line is:

```php
if ($installed && (time() - $installed) < 3 * DAY_IN_SECONDS) {
	return;
}
```

so `RC_Reviews_Collector` returns before `display_notice()` and registers nothing. The
review nag on that site cannot render until **12 Sep 2026, 11:35 UTC**. Clearing the
options to expose a nag re-arms this one instead of exposing it.

To verify it, either wait for that window or backdate `rc_date_<name>_installed` by more
than three days first, per `docs/plugins/wp-mail-bank.md`.

### Remaining probes

Structural, not copy greps:

| Check | Expected, rule off | Expected, rule on | Status |
|---|---|---|---|
| `has_action( 'admin_notices' )` entry for an `Insights_SDK` instance | present | gone | **confirmed live** |
| `has_action( 'admin_notices' )` entry for an `RC_Reviews_Collector` instance | present | gone | outstanding |
| `Notices::show_notices` on `admin_notices` | present | **present** | outstanding |
| PHP fatals | 0 | 0 | **0 either way** |

## Additions to `headwall-nag-cleanup.php`: 2 rules, mechanism 2

```php
public function unhook_bdthemes_review_and_tracking_notices() : void {
	$this->remove_discarded_instance_callback(
		'admin_notices',
		'RC_Reviews_Collector',
		'display_global_notice',
		'bdthemes-element-pack'
	);
	$this->remove_discarded_instance_callback(
		'admin_notices',
		'Insights_SDK',
		'display_global_notice',
		'bdthemes-element-pack'
	);
	$this->remove_discarded_instance_callback(
		'admin_notices',
		'Ultimate_Post_Kit_Reviews_Collector',
		'display_global_notice',
		'ultimate-post-kit'
	);
}
```
