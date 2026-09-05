# Converter for Media (WebP & AVIF)

- slug: `webp-converter-for-media`
- version analysed: `6.6.5`
- source: `/vault/backups/wordpress/plugins/webp-converter-for-media/webp-converter-for-media,6.6.5.zip`
- licensing: freemium (free on wordpress.org, PRO sold as a subscription at mattplugins.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a live
client site: *"it pops up in quite a lot of places, and takes up a lot of screen real
estate."*

**82 fleet sites** — hhw1 15, hhw2 20, hhw3 15, hhw4 8, hhw5 4, hhw6 8, hhw7 12. The
widest single-plugin reach of any rule in this project.

**Three notices suppressed**, three left alone. The interesting part of this audit is the
mechanism: the vendor's notice architecture is unusually clean, and that cleanliness is
precisely what makes it hard to target.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | **1 line of code**, `NoticeIntegrator.php:46` — but executed once per notice, so six callbacks on the hook |
| Vendor opt-out filters | **None.** 33 `apply_filters` calls in `src/`, all conversion, path, `.htaccess` or `https_local_ssl_verify`. Not one touches a notice |
| Vendor opt-out constants | None |
| Dashboard widgets | **None** — no `wp_add_dashboard_widget()` anywhere |
| Outbound calls from widgets | N/A. The welcome notice does load `mattplugins.com/images/matt-plugins-logo.png` in the browser |
| Freemius | Not bundled |

## Findings

Six notice classes in `src/Notice/`, each paired with a template in
`templates/components/notices/`.

| Notice class | Template | Where it shows | Verdict | Reason |
|---|---|---|---|---|
| `ThanksNotice` | `thanks.php` | Dashboard, from 2 weeks after install | **suppress** | *"…you can support us in the development of the plugin by adding a plugin review."* Pure review begging |
| `UpgradeNotice` | `upgrade.php` | Dashboard, from 1 week after install | **suppress** | *"New opportunities…"* — PRO upsell carrying a 20% coupon code, `20D4FD7814` |
| `BlackFridayNotice` | `discount-coupon.php` | Dashboard and the plugin's settings page, 23–30 Nov | **suppress** | *"50% discount on all PRO version plans"*, coupon `BF2026`. Seasonal sale |
| `TokenInactiveNotice` | `token-invalid.php` | Everywhere **except** the plugin's own pages | keep — **never-suppress list** | *"your subscription has expired or you have reached the maximum number of image conversions"*. Licence and quota state |
| `CloudflareNotice` | `clear-cache.php` | The plugin's settings page only | keep | Cache-purge instructions after a config change. Operational, and on the vendor's own screen |
| `WelcomeNotice` | `welcome.php` | Everywhere except the settings page, until dismissed | keep — see below | First-run prompt to start Bulk Optimization |

## Deliberately left alone

### The welcome notice is the one that shows everywhere — and it stays

This is the notice most likely to be what prompted the report. `is_available()` returns
true on **every admin screen except the plugin's own settings page**, and `is_active()`
holds until the option `webpc_is_new_installation` is flipped from `1` by dismissal. The
baseline capture below confirms it: it was the only Converter for Media notice on
`plugins.php`, and it sat alongside the two upsells on the dashboard.

It stays anyway, and the boundary rule is why. It says:

> Thank you for installing our Converter for Media plugin! Optimize all your images by
> clicking the "Start Bulk Optimization" button in the plugin settings.

A freshly installed Converter for Media converts nothing until bulk optimization runs. So
the notice is a true and actionable statement about the state of the site — the plugin is
installed and doing no work — not a pitch. It is dismissible, it is `is-dismissible`, and
dismissing it ends it permanently.

Two marks against it were weighed and did not carry:

- it carries a **"Meet the plugin"** marketing link to `url.mattplugins.com`, and
- it renders `<img src="https://mattplugins.com/images/matt-plugins-logo.png">`, an
  outbound request from the site owner's browser to the vendor on every admin page load
  until dismissed

Neither changes what the notice *tells* the owner. If a future version drops the
"Start Bulk Optimization" instruction, or stops being dismissible, revisit it.

### The Cloudflare notice is operational and out of scope anyway

`CloudflareNotice::is_available()` requires `$_GET['page'] === PageIntegrator::SETTINGS_MENU_PAGE`
— the plugin's own settings screen. Even were it promotional, this project does not touch
vendor screens. It is not promotional: it is a list of steps for purging the Cloudflare
cache so rewritten image URLs are actually served.

### The token notice is on the never-suppress list

`TokenInactiveNotice` fires when a PRO access token is configured but the subscription has
lapsed or the monthly conversion quota is exhausted. It is the inverse of the others —
shown on every screen *except* the plugin's own. This is exactly the licence-state notice
`CLAUDE.md` forbids touching, and it is the reason the rule below had to be precise rather
than convenient.

### There is no vendor opt-out to use

33 `apply_filters` calls in `src/` and not one of them is near a notice. The notice system
was built for the vendor's own use and exposes no switch. Mechanism 1 is unavailable, and
mechanism 3 does not apply — there is no dashboard widget.

## Mechanism

One rule, mechanism 2, and it needs a paragraph because the obvious approach does not
work.

### Why the notices cannot be told apart on `admin_notices`

`WebpConverter::__construct()` builds six integrators as **anonymous temporaries**:

```php
( new Notice\NoticeIntegrator( $plugin_info, new Notice\WelcomeNotice() ) )->init_hooks();
( new Notice\NoticeIntegrator( $plugin_info, new Notice\ThanksNotice() ) )->init_hooks();
( new Notice\NoticeIntegrator( $plugin_info, new Notice\CloudflareNotice() ) )->init_hooks();
( new Notice\NoticeIntegrator( $plugin_info, new Notice\TokenInactiveNotice( … ) ) )->init_hooks();
( new Notice\NoticeIntegrator( $plugin_info, new Notice\BlackFridayNotice( $plugin_data ) ) )->init_hooks();
( new Notice\NoticeIntegrator( $plugin_info, new Notice\UpgradeNotice( $plugin_data ) ) )->init_hooks();
```

No singleton, no registry, no property — the objects survive only as hook callbacks. And
every one of them registers the **same class and the same method**:

```php
public function init_notice_hooks(): void {
    if ( ! $this->notice->is_available() || ! $this->notice->is_active() ) {
        return;
    }
    add_action( 'admin_notices', [ $this, 'load_notice' ], 0 );
}
```

So `$wp_filter['admin_notices']` can hold up to six `NoticeIntegrator::load_notice`
entries, and class-plus-method — all `remove_discarded_instance_callback()` can match on —
cannot distinguish a Black Friday coupon from an expired subscription. This is the same
shape as ElementsKit's shared `Oxaim\Libs\Notice` renderer.

The notice each integrator holds is in a **private** `$notice` property, so it cannot be
read either.

### The handle: one `wp_ajax_` hook name per notice

`init_hooks()` registers a second callback, and this one is discriminating:

```php
public function init_hooks(): void {
    add_action( 'admin_init', [ $this, 'init_notice_hooks' ] );

    if ( $ajax_action = $this->notice->get_ajax_action_to_disable() ) {
        add_action( 'wp_ajax_' . $ajax_action, [ $this, 'set_disable_value' ] );
    }
}
```

Every notice returns its own `NOTICE_OPTION` from `get_ajax_action_to_disable()`, and the
six are distinct:

| Notice | Option, and therefore hook name |
|---|---|
| `WelcomeNotice` | `webpc_is_new_installation` |
| `ThanksNotice` | `webpc_notice_thanks` |
| `CloudflareNotice` | `webpc_notice_cloudflare` |
| `TokenInactiveNotice` | `webpc_notice_token_invalid` |
| `BlackFridayNotice` | `webpc_notice_bf2026` |
| `UpgradeNotice` | `webpc_notice_pro_version` |

`wp_ajax_webpc_notice_thanks` therefore holds exactly one callback, and its object is the
integrator wrapping `ThanksNotice` and nothing else. From that object, the notice's own
`load_notice` entry can be named directly and removed with `has_action()` /
`remove_action()` — no further `$wp_filter` reading, and no risk of hitting a sibling.

The dismiss handler itself is **left in place**. Removing it would break the dismiss button
on the notices that remain.

### Phase

`admin_init`, `LATE_PRIORITY` (999). `init_notice_hooks()` runs on `admin_init` at the
default 10, so the `admin_notices` entry exists by 999. `init_hooks()` itself runs during
plugin load, so the `wp_ajax_*` hooks exist far earlier.

### Failure modes are all silent no-ops

- Vendor absent → `class_exists()` guard returns early
- Option renamed in a future version → the `wp_ajax_` hook is not found, `continue`
- Notice not applicable this request (`is_available()`/`is_active()` false, e.g. Black
  Friday outside November) → `has_action()` returns false, `continue`, nothing logged

Nothing is dismissed on the owner's behalf and no option is written.

### One change to shared code

`remove_discarded_instance_callback()` was split. The `$wp_filter` walk moved into a new
private `find_instance_callback()`, which returns the callback and its priority; the
remover now calls it. **There is still exactly one place in the file that touches
`$wp_filter`** — the split exists because this rule needs the *instance* in order to reach
a sibling callback, not merely the entry to delete.

## Drift check

Re-check when a new version appears in the vault:

- `src/Notice/NoticeIntegrator.php` — `init_hooks()` must keep registering
  `set_disable_value` on `wp_ajax_` + the notice's option. That registration **is** the
  rule's handle; if it goes, the rule silently stops working
- `src/Notice/ThanksNotice.php`, `UpgradeNotice.php`, `BlackFridayNotice.php` — the
  `NOTICE_OPTION` constants. `webpc_notice_bf2026` will certainly be renamed for the next
  seasonal campaign, and the rule will silently miss it until the constant is updated
- `src/Notice/WelcomeNotice.php` and its template — if the *"Start Bulk Optimization"*
  instruction is dropped, the keep decision above no longer holds
- `src/WebpConverter.php` — any new `NoticeIntegrator` line, which is a new notice to
  classify
- Any new `apply_filters` around a notice, which would demote this to a mechanism 1 rule

## Verification

Tested on `bench2.local` (WP 7.1) with Converter for Media 6.6.5 active, over
authenticated admin requests, A/B against v1.18.0 (no rule) and v1.19.0.

`webpc_notice_thanks` and `webpc_notice_pro_version` were set to a past timestamp so both
notices were live. Notices were counted structurally, by the
`data-notice-action="webpc_…"` attribute each template emits, rather than by matching copy.

| Check | Rules off | Rules on |
|---|---|---|
| `webpc_notice_thanks` on the dashboard | **1** | **0** |
| `webpc_notice_pro_version` on the dashboard | **1** | **0** |
| `webpc_is_new_installation` on the dashboard | 1 | **1** — preserved |
| `webpc_is_new_installation` on `plugins.php` | 1 | **1** — preserved |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |
| `index.php`, `plugins.php`, `options-general.php`, `upload.php` | HTTP 200 | HTTP 200 |

The important check is the licence notice. An invalid access token was planted on the
bench (`webpc_settings.access_token` plus `webpc_token_data.valid_status = false`) so
`TokenInactiveNotice` would fire, and the dashboard was re-requested **with the rules on**:

| Check, rules on, invalid token | Result |
|---|---|
| `webpc_notice_token_invalid` present | **1** |
| *"your subscription has expired or you have reached the maximum…"* | **present** |
| `converter-notice-thanks-button-review` | **0** |

The subscription notice and the review nag were on the same request; one survived and one
did not. The bench options were removed afterwards.

Debug log with `HEADWALL_NAG_CLEANUP_DEBUG` on, confirming both removals and — correctly —
silence for the Black Friday notice, which is out of season:

```
[headwall-nag-cleanup 1.19.0] webp-converter: Removed webpc_notice_thanks from admin_notices priority 0.
[headwall-nag-cleanup 1.19.0] webp-converter: Removed webpc_notice_pro_version from admin_notices priority 0.
```

`BlackFridayNotice` could not be observed: `is_active()` is gated on `gmdate( 'Ymd' )`
falling between `2026-11-23` and `2026-11-30`. Its removal is structural — it uses the
same `wp_ajax_webpc_notice_bf2026` handle as the two that were observed.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
public function unhook_webp_converter_promos() : void {
	$integrator_class = 'WebpConverter\\Notice\\NoticeIntegrator';

	if ( ! class_exists( $integrator_class ) ) {
		return;
	}

	$promotional_notice_options = [
		'webpc_notice_thanks',      // Review request.
		'webpc_notice_pro_version', // PRO upsell carrying a discount coupon.
		'webpc_notice_bf2026',      // Black Friday sale.
	];

	foreach ( $promotional_notice_options as $notice_option ) {
		$found = $this->find_instance_callback( 'wp_ajax_' . $notice_option, $integrator_class, 'set_disable_value', 'webp-converter' );

		if ( null === $found ) {
			// Notice not built on this request, or the vendor renamed the option.
			continue;
		}

		$notice_callback = [ $found['function'][0], 'load_notice' ];

		foreach ( [ 'admin_notices', 'network_admin_notices' ] as $notice_hook ) {
			$notice_priority = has_action( $notice_hook, $notice_callback );

			if ( false === $notice_priority ) {
				// is_available()/is_active() said no; nothing was hooked.
				continue;
			}

			remove_action( $notice_hook, $notice_callback, $notice_priority );
			$this->log( 'webp-converter', sprintf( 'Removed %s from %s priority %d.', $notice_option, $notice_hook, $notice_priority ) );
		}
	}
}
```
