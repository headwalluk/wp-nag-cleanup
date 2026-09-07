# Featured Images in RSS for Mailchimp & More

- slug: `featured-images-for-rss-feeds`
- version analysed: `1.7.3`
- source: `/vault/backups/wordpress/plugins/featured-images-for-rss-feeds/featured-images-for-rss-feeds,1.7.3.zip`
- licensing: freemium (free on wordpress.org, premium sold by 5 Star Plugins, 14-day trial)
- Freemius bundled: **yes**, SDK `2.13.4`

## Analysis

Analysed on 7 Sep 2026 by Claude Code (Claude Opus 5), from a suspected Freemius nag
Paul reported.

The plugin's own code is small (822 lines) and puts almost all of its promotion on its
own settings screen, which is out of scope by construction. It does contain a 30-day
review nag on `admin_notices` — but that nag **can never render**, for the reason set
out below, and it is a closure, so it could not be unhooked even if it did.

The finding that produced a rule is the bundled **Freemius SDK**. Unlike Independent
Analytics — the only other Freemius plugin audited, on the same SDK 2.13.4 — this
module declares `has_paid_plans`, a 14-day `trial` and `has_affiliation => 'all'`.
That lights up two promotional notice paths Independent Analytics does not have: the
**trial promotion**, which re-shows every 30 days, and the **affiliate program** notice.

It takes two rules, not one. Freemius *persists* a sticky notice when it adds it and
renders it from storage thereafter, so a producer-side filter governs new sites only and
does nothing for a site that already has the nag. The working rule unhooks the two
producers (which also stops a menu counter badge) **and** filters the render path (which
reaches the stored notice). The first attempt, shipped in 1.22.0, used the producer-side
filter alone and did not work on the site the nag was reported from; that is written up
in full under [Mechanism](#mechanism) rather than quietly corrected.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **1 in the plugin's own code** — an anonymous closure at `featured_images_in_rss.php:351`, registered *from inside* `firss_settings_page()`. Dead code, see below. Plus Freemius's `FS_Admin_Notice_Manager::_admin_notices_hook` on `admin_notices` and `network_admin_notices` |
| Vendor opt-out filters | None in the plugin's own code (its only three `apply_filters` are `plugin_locale`, `firss_image_sizes`, `firss_image_styles`). **Two in Freemius**: `fs_show_trial_featured-images-for-rss-feeds` and `fs_show_affiliate_program_notice_featured-images-for-rss-feeds` |
| Vendor opt-out constants | None. Freemius's `WP_FS__DEV_MODE` / `WP_FS__DEMO_MODE` are development aids, not opt-outs |
| Dashboard widgets | **None**. No `wp_add_dashboard_widget()` anywhere in the plugin or the SDK |
| Outbound calls from widgets | No widgets. The plugin's own code makes no `wp_remote_*` call at all |
| Freemius | Yes, SDK 2.13.4. Config: `id 195`, `has_paid_plans => true`, `trial => 14 days`, `has_affiliation => 'all'`, `is_org_compliant => true`, `is_premium => false` |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Freemius trial promotion | `admin_init` 10 → `Freemius::_add_trial_notice`, sticky id `trial_promotion`, type `promotion` | **suppress** (unhook + render filter) | *"Hey! How do you like **Featured Images in RSS…** so far? Test all our awesome premium features with a 14-day free trial. No credit card required!"* with a **Start free trial ➜** button. Pure upsell, no site state. Re-shows **every 30 days** |
| Freemius affiliate program | `admin_init` 10 → `Freemius::_add_affiliate_program_notice`, sticky id `affiliate_program`, type `promotion` | **suppress** (unhook + render filter) | *"Hey there, did you know that **…** has an affiliate program? If you like the plugin you can become our ambassador and earn some cash!"* Pure promotion, no site state |
| 30-day review request | `admin_notices` → anonymous closure, `featured_images_in_rss.php:351` | keep — **unreachable**, see below | Would be a review nag, but it is registered too late to fire, is gated to the vendor's own settings page, and is a closure |
| Freemius opt-in / connect notice | `admin_notices` → `FS_Admin_Notice_Manager::_admin_notices_hook`, sticky id `connect_account` | keep | Declined for this plugin on the same reasoning as `independent-analytics.md` |
| `firss_call_to_action`, `firss_inform_premium` | `firss_settings_form_actions`, `firss_settings_after_form` | keep | Vendor's own settings screen. Out of scope by construction |
| "Please rate and review" plugin row link | `plugin_row_meta` | keep | A plugin-list row link, not a notice. Not the notice area |

## Deliberately left alone

### The plugin's own review nag is dead code — it can never render

This is the notice that looks like the obvious target, and it does not need a rule.
`featured_images_in_rss.php:351` registers it like this:

```php
function firss_settings_page() {          // line 165
    // …822-line settings screen echoed here…
    add_action( 'admin_notices', function () {   // line 351
        // 30-day review request, gated on $_GET['page'] === 'featured-images-for-rss-feeds'
    } );
}                                          // line 401
```

`firss_settings_page` is the render callback passed to `add_menu_page()` at line 145,
and that is its **only** call site. Verified against WordPress 7.1 core on `bench2.local`:

| Step | File:line |
|---|---|
| `require_once ABSPATH . 'wp-admin/admin-header.php'` | `wp-admin/admin.php:244` |
| `do_action( 'admin_notices' )` | `wp-admin/admin-header.php:313` |
| `do_action( $page_hook )` → `firss_settings_page()` | `wp-admin/admin.php:264` |

`admin_notices` has already fired, twenty lines earlier, by the time the callback that
registers the closure runs. The notice is added to a hook that will not fire again on
that request. It has been in this position in **every** version that has it — 1.7,
1.7.1, 1.7.2 and 1.7.3 — always inside `firss_settings_page()`. It was not present at
all in 1.6.5.

Three independent reasons not to write a rule, then:

1. It never renders
2. Even if it did, it is gated on `$_GET['page'] === 'featured-images-for-rss-feeds'` —
   the vendor's own settings screen, which `CLAUDE.md` puts out of scope by construction
3. It is an anonymous closure, so `remove_action()` cannot name it. Suppressing it would
   need blanket removal on `admin_notices`, which is banned

Worth stating plainly because it will look like a miss on a future re-read: **we found
it, and it is not a nag we can or should act on.** If the vendor ever moves that
`add_action` up into `firss_init()`, it becomes live, still unremovable as a closure,
and still confined to the vendor's own screen.

### The Freemius opt-in notice — declined, consistent with Independent Analytics

`is_org_compliant => true` means this module shows the same sticky `connect_account`
opt-in prompt analysed at length in `docs/plugins/independent-analytics.md`. Nothing
about this plugin changes that reasoning: the SDK exposes no filter on it, the only
unhook target renders every Freemius notice for the module including licence and error
notices, and `remove_sticky()` would write to the vendor's storage. It is added once and
a single click on "Skip" clears it permanently.

Declining it here while suppressing the trial and affiliate notices is not inconsistent
— it is exactly the distinction the boundary rule asks for. The trial notice **returns
every 30 days** and has a filter; the opt-in notice fires once and has none.

### The settings-screen promotion is out of scope

`firss_call_to_action()` and `firss_inform_premium()` render upgrade copy through the
plugin's own `firss_settings_form_actions` and `firss_settings_after_form` hooks, and
the settings page footer carries a "Rate and Review" link and a 5 Star Plugins logo.
All of it is on the vendor's own settings screen. `CLAUDE.md`: *"If a change would ever
require touching the vendor's own settings screens, locked panels or upgrade tabs,
stop."*

### No dashboard widget, no outbound call

Neither the plugin nor the SDK registers a dashboard widget, and the plugin's own code
contains no `wp_remote_get`, `wp_remote_post` or `fetch_feed`. There is nothing here for
mechanism 3.

## Mechanism

Two rules, because one surface is not enough. **Corrected in 1.22.1** — see
"The first attempt was wrong" below, which is kept deliberately.

- tier: **1** (render-time vendor filter) + **2** (targeted unhook)
- phase: file scope for the filter; `admin_init` at `EARLY_PRIORITY` for the unhook
- vendor registers at: `class-freemius.php:1580-1581`,
  `add_action( 'admin_init', [ &$this, '_add_trial_notice' ] )` and
  `_add_affiliate_program_notice`, both at the default priority 10
- instance reachable via: `Freemius::get_instance_by_id( 195 )` — the SDK's own static
  registry, keyed `'m_' . $id`. It returns `false` when the module is absent and, unlike
  `Freemius::instance()`, never constructs one. No `$wp_filter` walk needed

### Rule 1 — unhook the producers, before they run

```php
remove_action( 'admin_init', [ $freemius_module, '_add_trial_notice' ] );
remove_action( 'admin_init', [ $freemius_module, '_add_affiliate_program_notice' ] );
```

Both producers run *on* `admin_init` at priority 10, so this is the `EARLY_PRIORITY`
pass CLAUDE.md describes for WPCode, not the late one.

This is mechanism 2 where a mechanism 1 filter exists, which needs justifying. The SDK
does expose `fs_show_trial_{slug}`, but `_add_trial_notice()` consults it **too late**:

```php
if ( $this->is_in_trial_promotion() ) {              // has_sticky( 'trial_promotion' )
    add_action( 'admin_footer', … '_fix_start_trial_menu_item_url' );
    $this->_menu->add_counter_to_menu_item( 1, 'fs-trial' );   // ← red "1" badge
    return false;
}
…
if ( ! $this->apply_filters( 'show_trial', true ) ) { return false; }   // ← never reached
```

On any site where the sticky is already stored, the early `is_in_trial_promotion()`
branch returns before the filter is read, and adds a `update-plugins count-1` badge to
the plugin's admin menu item. So `fs_show_trial_{slug}` cannot stop the badge, and
hiding only the notice would leave a permanent badge the owner can never clear — the
notice they would have clicked "Dismiss" on is the one we just hid. Unhooking the
producer removes the notice, the badge and the storage write together.

### Rule 2 — hide the sticky the vendor already stored

```php
add_filter( 'fs_show_admin_notice_featured-images-for-rss-feeds',
    [ $this, 'hide_freemius_promo_notice' ], self::LATE_PRIORITY, 2 );
```

Rule 1 stops the notice being *added*. It does nothing for the installed base, where
`add_sticky()` already ran and the notice renders from stored state on every admin page
— which is exactly what was observed on `footballinberkshire.co.uk`.

`FS_Admin_Notice_Manager::_admin_notices_hook()` applies a per-notice filter at render:

```php
$show_notice = call_user_func_array( 'fs_apply_filter', array(
    $this->_module_unique_affix, 'show_admin_notice', $this->show_admin_notices(), $msg ) );

if ( true !== $show_notice ) { continue; }
```

`$msg` carries `id`, `type`, `manager_id` and `plugin`, so the callback names the two
sticky ids exactly — `trial_promotion` and `affiliate_program` — rather than matching on
`type === 'promotion'`. Matching the type would be removal by appearance, and would
capture anything Freemius later classifies that way.

Two details worth keeping:

- The gate is `true !== $show_notice`, so the callback returns the **incoming** value
  untouched for every other id. Returning `true` would force-render notices the SDK had
  decided to hide (it passes `false` on `about.php` and in the block editor)
- Freemius's own docblock at `class-fs-admin-notice-manager.php:270` gives this exact
  filter with `'trial_promotion' != $msg['id']` as its worked example. This is the
  sanctioned route, not a workaround

`hide_freemius_promo_notice()` is a **public instance method** registered as
`[ $this, … ]`, so it stays removable via the global `$headwall_nag_cleanup`.

### What is deliberately not done

`$freemius_module->_admin_notices->remove_sticky( 'trial_promotion' )` would clear the
stored notice outright and is reachable. It is **not** used, for the reason given in
`independent-analytics.md`: it writes to the vendor's storage to change what the vendor
believes, and leaves residue that uninstalling this plugin would not undo. The two rules
above achieve the same visible result without touching vendor state.

The consequence, stated plainly: the stored sticky **stays in the database**, invisible.
Remove Headwall Nag Cleanup and the nag comes back, dismissable in the normal way. That
is the correct behaviour for a drop-in — it suppresses, it does not mutate.

### The first attempt was wrong — kept as the record

1.22.0 shipped this, and it did not work:

```php
add_filter( 'fs_show_trial_featured-images-for-rss-feeds', '__return_false' );
add_filter( 'fs_show_affiliate_program_notice_featured-images-for-rss-feeds', '__return_false' );
```

Both filter names are real and correctly built. The rule failed because it was reasoned
entirely from the *producer* — `_add_trial_notice()` — without following the notice to
where it is actually **rendered**. Freemius stickies are persisted at add time and drawn
from storage afterwards, so a filter on the add path cannot reach a notice that is
already stored, and on the site where the nag was first spotted it had been stored for
weeks. The audit had even recorded this as a caveat and still shipped the rule as the
fix.

The general lesson, which applies well beyond Freemius: **for any notice that is stored
rather than generated per request, find the render path before choosing the mechanism.**
A producer-side filter only ever governs new sites.

## Drift check

Re-check when a new version appears in the vault:

- `includes/freemius/includes/managers/class-fs-admin-notice-manager.php` — the render
  filter in `_admin_notices_hook()`, currently
  `fs_apply_filter( $this->_module_unique_affix, 'show_admin_notice', … )`, and its
  `if ( true !== $show_notice ) { continue; }` gate. **This is the load-bearing one.** If
  the tag is renamed, the `$msg` keys change, or the gate is inverted, the stored nag
  comes back with no other symptom
- `includes/freemius/includes/class-freemius.php` — the sticky ids `trial_promotion`
  (line 24118) and `affiliate_program` (24204), passed to `add_sticky()`. The rule names
  them literally. Also check whether a third notice is ever typed `'promotion'`; today
  these are the only two
- `class-freemius.php:1580-1581` — the two `add_action( 'admin_init', … )` registrations.
  If either moves to a different hook or priority, the `EARLY_PRIORITY` unhook misses it.
  Debug logging reports "Freemius module 195 not present" only when the module is absent,
  so a moved hook fails **silently** — re-read these lines rather than trusting the log
- `class-freemius.php` — `get_instance_by_id()` and the `'m_' . $id` keying of
  `self::$_instances`. The accessor is the whole reason no `$wp_filter` walk is needed
- `featured_images_in_rss.php` — the `fs_dynamic_init()` config. The **id `195`** keys the
  instance registry and the **slug** forms the filter tag; a change to either kills both
  rules. Dropping `trial` / `has_affiliation` is harmless (the rules become no-ops)
- `featured_images_in_rss.php:351` — if the `add_action( 'admin_notices', … )` closure
  moves out of `firss_settings_page()` into `firss_init()` or an `admin_init` callback,
  the review nag becomes live. It would still be a closure on the vendor's own screen,
  so it would still get no rule, but the analysis above would need updating
- The bundled SDK version, currently 2.13.4

## Verification

Filter names, gate conditions, hook registrations and the instance registry keying were
read from the vault copy of 1.7.3 (Freemius SDK 2.13.4).

**Observed live.** The trial nag was captured on `footballinberkshire.co.uk` (hhw5,
Featured Images in RSS 1.7.3), rendering as:

```html
<div class="fs-notice updated promotion fs-sticky … fs-slug-featured-images-for-rss-feeds"
     data-id="trial_promotion" data-manager-id="featured-images-for-rss-feeds" …>
```

`data-id="trial_promotion"` on a `fs-sticky` element is the direct evidence that the
notice comes from stored state, which is what defeated the 1.22.0 rule.

Both mechanisms were then exercised against the SDK's real logic rather than reasoned
about:

- **Render filter** — driven through Freemius's own `fs_apply_filter()` tag builder,
  copied verbatim, and evaluated against the SDK's `true !== $show_notice` gate.
  `trial_promotion` and `affiliate_program` suppress; `connect_account`,
  `license_expired` and `plan_upgraded` pass through unchanged; an incoming `false`
  stays `false`
- **Unhook** — against a stub keyed the way `Freemius::$_instances` is keyed (`m_195`).
  Both promotional producers come off `admin_init`; a sibling licence-notice producer on
  the same hook stays on. The absent-module path was exercised and does not fatal
- **Double include** — the file was included twice; the two rules register once

Not exercised end-to-end on a live WordPress request. The site the nag was found on is
high-traffic and read-only for this work, so the fix has not yet been confirmed rendering
on it. The remaining check is one authenticated admin request there after deployment,
asserting the `data-id="trial_promotion"` block is gone **and** that the plugin's menu
item carries no `count-1` badge.

## Additions to `headwall-nag-cleanup.php`

Constants, one mechanism 1 filter registration, and one mechanism 2 unhook:

```php
const FIRSS_FREEMIUS_MODULE_ID = 195;
const FIRSS_FREEMIUS_SLUG      = 'featured-images-for-rss-feeds';

const FREEMIUS_PROMO_NOTICE_IDS = [
    'trial_promotion',
    'affiliate_program',
];

// register_vendor_optouts(), file scope
add_filter(
    'fs_show_admin_notice_' . self::FIRSS_FREEMIUS_SLUG,
    [ $this, 'hide_freemius_promo_notice' ],
    self::LATE_PRIORITY,
    2
);

// unhook_early_vendor_notices(), admin_init at EARLY_PRIORITY
remove_action( 'admin_init', [ $freemius_module, '_add_trial_notice' ] );
remove_action( 'admin_init', [ $freemius_module, '_add_affiliate_program_notice' ] );
```

plus `hide_freemius_promo_notice()`, `unhook_freemius_promos()` and the
`get_freemius_module()` accessor.

### Adding a second Freemius plugin later

`FREEMIUS_PROMO_NOTICE_IDS` is SDK-wide and needs no change. A new module needs its own
id and slug, one more `add_filter()` on `fs_show_admin_notice_{slug}`, and one more pair
of `remove_action()` calls. If that reaches three or four modules, turn the id/slug pairs
into a single array and loop — not before.
