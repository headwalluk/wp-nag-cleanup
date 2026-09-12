# Premium Addons for Elementor

- slug: `premium-addons-for-elementor`
- version analysed: `4.11.103` (first pass `4.11.102`)
- source: `/vault/backups/wordpress/plugins/premium-addons-for-elementor/premium-addons-for-elementor,4.11.103.zip`
- licensing: freemium (free on wordpress.org, Premium Addons PRO sold at premiumaddons.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a live
client site — an upsell notice carrying the `pa-new-feature-notice` CSS class.

Re-analysed on 12 Sep 2026 by Claude Code (Claude Opus 5), from a second nag Paul reported
on a live client site — a `wp-pointer` popup, "Summer Sale 2026!", anchored to the Premium
Addons admin menu item. **A third rule was added**, and the two existing rules were
re-verified against 4.11.103 unchanged.

4 fleet sites. **Two rules added on the first pass.** The "Premium Addons News" dashboard widget is
removed, and the three promotional notices are removed by **swapping the vendor's notice
dispatcher for its dependency check alone**.

The second rule was written on a later pass. The first pass concluded no rule was
possible, because one callback prints both the Elementor dependency notice and three
promos. That was wrong: both halves are public methods, so the dispatcher can be replaced
with the operational half rather than suppressed wholesale. The rejected reasoning is kept
below, because the technique generalises.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | **1** — `Admin_Notices::admin_notices`, a dispatcher printing several notices |
| `in_admin_header` registrations | **1** — an anonymous closure in `includes/promotion-pointer.php`, the seasonal sale pointer. **Missed by the first pass**; see below |
| Vendor opt-out filters | None relevant. Only `premium_addons/angie/is_pa_widget` and a WooCommerce filter |
| Vendor opt-out constants | None usable — see `check_hide_notifications()` below |
| Dashboard widgets | **1**: `pa-stories`, "Premium Addons News", context `column3`, registered on `wp_dashboard_setup` priority 111 |
| Outbound calls from widgets | `https://premiumaddons.com/wp-json/stories/v2/get`, cached in a transient |
| Freemius | Not bundled |

## Findings

| Item | Hook / ID | Verdict | Reason |
|---|---|---|---|
| "Premium Addons News" widget | `wp_dashboard_setup` → `pa-stories` | **suppress** | Vendor news feed plus an outbound API call on render. No site state |
| Elementor dependency check | `admin_notices` → `required_plugins_check()` | keep | *Install Elementor* prompt — a missing dependency notice |
| Review request | `admin_notices` → `show_review_notice()` | **suppress** | Review begging (`pa_review_notice`) |
| "New feature" / Angie notices | `admin_notices` → `get_angie_notice()` | **suppress** | `pa-new-feature-notice` (`pa-angie-not`) |
| Connect AI notice | `admin_notices` → `get_connect_ai_notice()` | **suppress** | *"Connect ChatGPT/Claude … Check it Out!"* (`pa-connect-ai-not`) |
| Seasonal sale pointer | `in_admin_header` → anonymous closure | **suppress** | *"Summer Sale 2026! … Save 30% Now"*, linking to `premiumaddons.com/pro/`. Pure discount advertising |
| MCP adapter dependency notice | `admin_notices` → closure in bundled `wordpress/mcp-adapter` | keep | *"Abilities API not available"* — a missing-dependency error, and `dismiss => false` |

## The seasonal sale pointer — second pass, 12 Sep 2026

`includes/promotion-pointer.php`, included unconditionally from `PA_Core::pa_init()` on
`plugins_loaded`. It registers an **anonymous closure** on `in_admin_header` which prints
an inline script opening a core `wp-pointer` popup against `#toplevel_page_premium-addons`:

> **Summer Sale 2026!** Unlock the full power of Elementor with 90+ advanced elements and
> 580+ templates. … **Save 30% Now** → `premiumaddons.com/pro/?utm_campaign=summer26`

Its gates, in order:

```php
if ( Helper_Functions::check_papro_version()                      // PA Pro installed
  || time() > strtotime( '09:59:59pm 30th September, 2026' )      // campaign end
  || ( $GLOBALS['pagenow'] !== 'index.php'
       && get_current_screen()->id !== 'toplevel_page_premium-addons' )
  || get_transient( 'pa_sumr26_pointer_dismiss' ) ) {             // dismissed
    return;
}
```

A discount advert with no site state in it, on the dashboard and on the vendor's own page.
It carries no licence, version, dependency or security content — suppress.

### Why the first pass missed it

The checklist's pass (a) greps for `add_action( 'admin_notices'` and friends **on one
line**. `promotion-pointer.php` writes its registration across several:

```php
add_action(
	'in_admin_header',
	function () {
```

4.11.102 — the version the 5 Sep pass read — already contained this file. The rule was not
rejected, it was never seen. `SKILL.md` pass (a) now greps for the hook name on the
following line as well, and `in_admin_header` is in the hook list.

### Why `remove_action()` cannot reach it

The callback is a closure, so there is no name to pass to `remove_action()` and no object
to reconstruct. The sanctioned `$wp_filter` reader does not help either: it matches an
**instance of a named class**, and a closure is neither — `is_instance_callback()` rejects
it in its first branch. Matching a closure would mean matching on its declaring file,
which is a different kind of reader, and `CLAUDE.md` allows exactly one.

Elementor's equivalent was solved by dequeuing the promotion's script and style by handle;
that route does not exist here, because the script is printed inline rather than enqueued.

### The mechanism that does work: the vendor's own dismissal gate

The four bail-out conditions are OR'd, so answering any one of them ends the closure. The
last, `get_transient( 'pa_sumr26_pointer_dismiss' )`, is the only one reachable from
outside — and core's `get_transient()` opens with:

```php
$pre = apply_filters( "pre_transient_{$transient}", false, $transient );
if ( false !== $pre ) {
	return $pre;
}
```

So `add_filter( 'pre_transient_pa_sumr26_pointer_dismiss', '__return_true' )` answers the
gate from memory, per request. The closure returns at line 14 and the pointer is never
printed — and because the `update_option( '_pa_plugin_pointer_priority', 1 )` write sits
*after* the gate, it is skipped too.

**Nothing is written.** That is the distinction from the option-writing route rejected on
the first pass: no dismissal is recorded, no residue survives removing this plugin, and the
30-day transient the vendor would have set is never created.

The hook is **core's**, not the vendor's. Only the transient *name* comes from the vendor,
and that is the part that drifts.

### The campaign name is the maintenance cost

The transient is scoped to the campaign, so a new campaign silently escapes the rule. Five
names in the 4.11.62–4.11.103 range, every one found by reading the vault:

| Transient | Campaign | Versions |
|---|---|---|
| `pa_xmas25_pointer_dismiss` | Christmas 2025 | 4.11.62 – 4.11.67 |
| `pa_val26_pointer_dismiss` | "Biggest Sale Until Summer 2026" | 4.11.68 – 4.11.72 |
| `pa_spring26_pointer_dismiss` | Spring 2026 | 4.11.73 – 4.11.76 |
| `pa_summer26_pointer_dismiss` | Summer Sale 2026 | 4.11.77 – 4.11.83 |
| `pa_sumr26_pointer_dismiss` | Summer Sale 2026 | 4.11.84 – 4.11.103 |

The last two are the **same campaign under two names**: 4.11.84 renamed the transient
without changing the copy, which re-shows the pointer to every site that had dismissed it.
Worth knowing before assuming a name is stable for the life of a campaign.

All five are listed in `PREMIUM_ADDONS_POINTER_TRANSIENTS`, not just the current one — the
fleet runs whatever version each site happens to have, and an old release still nags. No
pattern match, for the same reason mechanism 4 names every ID: a sweep over
`pre_transient_*` cannot exist, and would be the wrong shape if it could.

### Suppressing it on the vendor's own page too

The pointer renders on `index.php` **and** on `toplevel_page_premium-addons`, and the
filter answers the gate on both. That is not the "vendor's own settings screen" carve-out
in `CLAUDE.md`, which is about modifying the vendor's own UI — locked panels, upgrade tabs,
feature gating. This is one floating overlay injected into the admin header, identical on
both screens, and the Premium Addons settings page itself is untouched.

## Deliberately left alone

### Why the first pass concluded no rule was possible

Kept because the mistake is instructive.

Premium Addons registers exactly one `admin_notices` callback, which dispatches
everything in sequence:

```php
public function admin_notices() {
    if ( wp_doing_ajax() ) { return; }

    $this->required_plugins_check();          // operational

    $review_state = self::get_notice_state( self::REVIEW_OPTION );
    if ( '1' !== $review_state && (int) $review_state < time() ) {
        $this->show_review_notice();          // review nag
    }

    if ( Helper_Functions::check_hide_notifications() ) { return; }

    if ( defined( 'ANGIE_VERSION' ) ) { $this->get_angie_notice(); }
    $this->get_connect_ai_notice();           // upsells, pa-new-feature-notice
}
```

`required_plugins_check()` runs **first and unconditionally**. It prints an *install
Elementor* prompt with a nonced install URL when Elementor is missing — a dependency
notice, on the never-suppress list. Unhooking `admin_notices` to remove the upsells takes
that with it, on a plugin whose entire function depends on Elementor being present.

The first pass stopped there: mixed output, collateral is a dependency notice, no rule.

That conclusion held only if the callback is atomic. It is not.
`required_plugins_check()` is declared **`public`** (line 196), as is `admin_notices()`
(line 132). So the dispatcher can be removed and the dependency check re-added on its own:

```php
remove_action( 'admin_notices', [ $notices, 'admin_notices' ] );
add_action( 'admin_notices', [ $notices, 'required_plugins_check' ] );
```

The three promotional methods — `show_review_notice()`, `get_angie_notice()`,
`get_connect_ai_notice()` — are private and only ever called from the dispatcher, so
removing it means they cannot run. Nothing is dismissed on the site owner's behalf and no
option is written.

**The lesson generalises**: when a callback is mixed, check the visibility of its parts
before declaring it atomic. A public operational method can be re-hooked.

### Why the option-writing alternative was rejected

The three notices are gated on options `pa_review_notice`, `pa-angie-not` and
`pa-connect-ai-not`, each set to `'1'` when the user dismisses. Writing those three
options would suppress the promos and leave the dependency notice untouched, and was
seriously considered — it would have needed a second, opt-in file to keep this plugin's
read-only guarantee intact.

Rejected once the dispatcher swap was found. It writes permanent per-site residue that
removing this plugin would not undo, it records a dismissal the site owner never made, and
it would need doing again for every vendor that gates on an option. The hook-level route
achieves the same visible result, per request, with nothing left behind.

### The vendor's own gate is not usable

`Helper_Functions::check_hide_notifications()` returns true only when Premium Addons
**PRO** is installed *and* white labelling is switched on. It is a Pro feature, not an
opt-out available to us — and it sits *after* the review notice, so even Pro users get
that one.

## Mechanism

Three rules.

### Seasonal pointer — tier 1, core filter over the vendor's gate

- phase: **file scope**, in `register_vendor_optouts()`. No load-order problem: the filter
  only has to exist before `in_admin_header` fires, and mu-plugins load first
- `add_filter( 'pre_transient_<campaign>', '__return_true' )` for every name in
  `PREMIUM_ADDONS_POINTER_TRANSIENTS`
- writes nothing, reads nothing, and no-ops on every site where Premium Addons is absent —
  nothing else calls `get_transient()` on those names

### Notices — tier 2, dispatcher swap

- phase: `admin_init`, priority 999. Premium Addons registers on `admin_notices` from
  `Admin_Notices::__construct`, reached via `Admin_Helper` during plugin load, so ours
  runs comfortably later and before `admin_notices` fires
- instance reachable via: **`\PremiumAddons\Admin\Includes\Admin_Notices::get_instance()`**
  — a public static singleton accessor
- the swap is guarded: if `required_plugins_check()` cannot be found, **nothing is
  removed**. Losing an operational dependency notice would be worse than leaving three
  promos in place
- `required_plugins_check` is re-added at default priority 10, preserving the original
  ordering relative to other plugins' notices

### Dashboard widget — tier 3
- phase: `wp_dashboard_setup`, priority 999
- vendor registers at: `Admin_Notices::show_story_widget` on `wp_dashboard_setup`
  priority 111, so our 999 runs after it and `remove_meta_box()` finds the widget
- instance reachable via: N/A for mechanism 3
- context: **`column3`**, not the default `normal` — `wp_add_dashboard_widget()` is called
  with `$context = 'column3'` and `$priority = 'core'`, and `remove_meta_box()` must match

Removing the meta box means the render callback never runs, so the
`premiumaddons.com/wp-json/stories/v2/get` request is never made.

## Drift check

Re-check when a new version appears in the vault:

- `admin/includes/admin-notices.php` — `show_story_widget()`. If the widget ID or the
  `column3` context changes, the rule silently stops matching
- `admin/includes/admin-notices.php` — **`required_plugins_check()` must stay `public`
  and must stay the whole of the operational output of `admin_notices()`.** If the
  dispatcher gains further operational notices, the swap would silently drop them. This is
  the single most important line to re-read on a new version
- `includes/promotion-pointer.php` — **the transient name in the first `get_transient()`
  call.** A new campaign renames it and the rule stops matching, silently and with no log
  line, because a mechanism 1 filter has nothing to report. This is the one thing in this
  document that needs checking on every release, not just on a major one:

  ```bash
  unzip -p "<zip>" '*/includes/promotion-pointer.php' | command grep -o "pa_[a-z0-9]*_pointer_dismiss" | head -1
  ```

  If the file is gone entirely, the campaign has ended and the constant is history, not a
  bug — leave the old names in place for sites still on an older release
- Any new `apply_filters` around the notices, which would make this a mechanism 1 rule

## Verification

Tested on `bench2.local` (WP 7.1) with Premium Addons 4.11.102 active, A/B with the rule
enabled and disabled, over authenticated admin requests:

| Check | Rules off | Rules on |
|---|---|---|
| `id="pa-stories"` on the dashboard | **1** | **0** |
| Elementor dependency notice on `plugins.php` | **1** | **1** — preserved |
| `pa-connect-ai-notice` on `plugins.php` | **1** | **0** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

**Confirmed gone on a live client site**, 12 Sep 2026, after Paul deployed 1.27.0 to the
site this second pass came from. The pointer was opening on every dashboard load before the
deploy and was absent afterwards — the rule and the reported nag are the same thing, so the
deploy exercised it directly.

Re-tested on `bench2.local` on 12 Sep 2026 with Premium Addons **4.11.103** active and
Elementor **absent**, so the dependency notice was on screen throughout. Deployed 1.25.0
for the "before" capture, then 1.27.0:

| Check | 1.25.0 | 1.27.0 |
|---|---|---|
| `#toplevel_page_premium-addons').pointer(` on the dashboard | **1** | **0** |
| same, on `admin.php?page=premium-addons` | **1** | **0** |
| `wp-pointer` references on the dashboard | 7 | 1 — core's own stylesheet |
| "Install Elementor plugin" notice | **1** | **1** — preserved |
| `_pa_plugin_pointer_priority` written on a dashboard view | yes | **no** — deleted first, still absent after |
| `_transient_pa_sumr26_pointer_dismiss` | absent | **absent** |
| PHP fatals, warnings, notices | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

The option row is the one that matters: it proves the closure returned at its first gate
rather than being suppressed further down, and that the rule leaves no trace in the
database.

The dispatcher swap and the widget rule were re-confirmed at 4.11.103 on the same run —
`required_plugins_check()` is still `public` and still the whole of the operational output
of `admin_notices()` (the only change since 4.11.102 is that Angie and Connect-AI are now
an `if`/`else` rather than two calls), and the widget is still `pa-stories` in `column3`.

The review and Angie notices could not be observed on the bench — the first is time-gated
and the second requires `ANGIE_VERSION` to be defined by a companion plugin. Their removal
is structural rather than observed: both are private methods called only from the
dispatcher, which no longer runs.

## Additions to `headwall-nag-cleanup.php`: 3 rules

The pointer rule, added 1.27.0, is the constant plus three lines in
`register_vendor_optouts()`:

```php
foreach ( self::PREMIUM_ADDONS_POINTER_TRANSIENTS as $pointer_transient ) {
	add_filter( 'pre_transient_' . $pointer_transient, '__return_true' );
}
```

The two rules from the first pass:

```php
public function unhook_premium_addons_promos() : void {
	$notices_class = '\\PremiumAddons\\Admin\\Includes\\Admin_Notices';

	if ( ! class_exists( $notices_class ) || ! method_exists( $notices_class, 'get_instance' ) ) {
		return;
	}

	$notices = $notices_class::get_instance();

	if ( ! is_object( $notices ) || ! method_exists( $notices, 'required_plugins_check' ) ) {
		// Without the dependency check to put back, removing the dispatcher would
		// lose an operational notice. Leave the promos rather than risk that.
		$this->log( 'premium-addons', 'required_plugins_check not reachable; no action taken.' );
	} else {
		remove_action( 'admin_notices', [ $notices, 'admin_notices' ] );
		add_action( 'admin_notices', [ $notices, 'required_plugins_check' ] );
		$this->log( 'premium-addons', 'Swapped admin_notices dispatcher for required_plugins_check.' );
	}
}
```

Plus the mechanism 3 widget entry:

```php
[
    'widget_id' => 'pa-stories',
    'context'   => 'column3',
    'vendor'    => 'Premium Addons for Elementor 4.11.102',
    'reason'    => 'Premium Addons News; fetches premiumaddons.com on render',
],
```
