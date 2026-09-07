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
That lights up two promotional notice paths Independent Analytics does not have, and
**both are gated by a vendor filter**. This is a clean mechanism 1 rule, which is what
the Independent Analytics audit said it was waiting for.

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
| Freemius trial promotion | `admin_init` 10 → `Freemius::_add_trial_notice`, sticky id `trial_promotion`, type `promotion` | **suppress** | *"Hey! How do you like **Featured Images in RSS…** so far? Test all our awesome premium features with a 14-day free trial. No credit card required!"* with a **Start free trial ➜** button. Pure upsell, no site state. Re-shows **every 30 days** |
| Freemius affiliate program | `admin_init` 10 → `Freemius::_add_affiliate_program_notice`, sticky id `affiliate_program`, type `promotion` | **suppress** | *"Hey there, did you know that **…** has an affiliate program? If you like the plugin you can become our ambassador and earn some cash!"* Pure promotion, no site state |
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

- tier: **1** (vendor opt-out filter)
- phase: **file scope** — `register_vendor_optouts()`
- vendor registers at: `Freemius::_add_trial_notice` and `Freemius::_add_affiliate_program_notice`,
  both `add_action( 'admin_init', … )` at default priority 10, from
  `class-freemius.php:1580-1581`. An mu-plugin filter registered at file scope is in
  place long before `admin_init`
- instance reachable via: N/A — no unhooking needed

Freemius namespaces its filters per module. `Freemius::apply_filters()`
(`class-freemius.php:19714`) prepends `get_unique_affix()` and calls `fs_apply_filter()`
(`fs-core-functions.php`), which builds the tag as:

```php
"fs_{$tag}_{$module_unique_affix}"
```

For a plugin the affix is the slug, so `apply_filters( 'show_trial', true )` inside
`_add_trial_notice()` reads the WordPress filter
`fs_show_trial_featured-images-for-rss-feeds`. **The rule is necessarily per slug** —
there is no SDK-wide switch, which is what `independent-analytics.md` already recorded.

Both filters gate only their own notice. `show_trial` appears once in the SDK, at
`class-freemius.php:24011`; `show_affiliate_program_notice` once, at `24157`. Neither
touches licence, update, trial-expiry or error notices. The two `add_sticky()` calls
they guard are the **only** two notices in the whole SDK typed `'promotion'`
(`class-freemius.php:24118` and `24204`), so this pair is the complete set of Freemius
promotional stickies, and nothing operational is in range.

Because the filter is read *before* `add_sticky()`, suppression also skips the
`$this->_storage->trial_promotion_shown` / `affiliate_program_notice_shown` writes
entirely — no vendor storage is touched, which was the specific objection that ruled out
the `remove_sticky()` route for Independent Analytics.

**Caveat, stated honestly:** on a site where the sticky notice has *already* been added,
the filter does not retract it. Sticky notices render from stored state, and the filter
only prevents the next add. Such a site keeps the current notice until it is dismissed
once; the 30-day re-show is then blocked permanently.

## Drift check

Re-check when a new version appears in the vault:

- `includes/freemius/includes/class-freemius.php` — the filter names at lines 24011
  (`show_trial`) and 24157 (`show_affiliate_program_notice`). If Freemius renames either,
  or changes `fs_apply_filter()`'s `"fs_{$tag}_{$affix}"` tag format, both filters go
  silently dead
- `featured_images_in_rss.php` — the `fs_dynamic_init()` config. If `trial` or
  `has_affiliation` is dropped, the corresponding filter becomes a no-op (harmless). If
  the **slug** changes, both filters go dead
- `featured_images_in_rss.php:351` — if the `add_action( 'admin_notices', … )` closure
  moves out of `firss_settings_page()` into `firss_init()` or an `admin_init` callback,
  the review nag becomes live. It would still be a closure on the vendor's own screen,
  so it would still get no rule, but the analysis above would need updating
- The bundled SDK version, currently 2.13.4

## Verification

Filter names, gate conditions and hook registrations read from the vault copy of 1.7.3.
The dead-code finding for the review closure verified against WordPress 7.1 core at
`/var/www/bench2.local/web` — `wp-admin/admin.php:244`/`264` and
`wp-admin/admin-header.php:313`.

Not yet exercised on the bench: both notices are time-gated (the trial notice 24h after
Freemius activation, the affiliate notice 30 days after install), and both require the
site to be past `is_activation_mode()` — that is, opted in *or* skipped. Confirming them
live needs a backdated `install_timestamp` in the module's Freemius storage, per the
method in `docs/plugins/wp-mail-bank.md`.

## Additions to `headwall-nag-cleanup.php`: 2 mechanism 1 filters

```php
// Freemius SDK 2.13.4 trial and affiliate promos, bundled in Featured Images in RSS
// 1.7.3. Freemius namespaces filters per module as fs_{tag}_{slug}, so these are
// necessarily per slug. docs/plugins/featured-images-for-rss-feeds.md
add_filter( 'fs_show_trial_featured-images-for-rss-feeds', '__return_false' );
add_filter( 'fs_show_affiliate_program_notice_featured-images-for-rss-feeds', '__return_false' );
```
