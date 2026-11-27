# Custom Thank You for WooCommerce (Artios Media)

- slug: `custom-thank-you-for-woocommerce`
- version analysed: `1.1.6` (newest in the vault; `1.1.4` also present, `class-ctyw-settings.php` identical)
- source: `/vault/backups/wordpress/plugins/custom-thank-you-for-woocommerce/custom-thank-you-for-woocommerce,1.1.6.zip`
- licensing: free
- Freemius bundled: no

## Analysis

Analysed on 27 Nov 2026 by Claude Code (Claude Opus 5).

Paul reported it from a client site: *"How do you like **Custom Thank You for WooCommerce**?
Your feedback assures the continued maintenance of this plugin!"*, with a **Leave Feedback**
button linking to the wordpress.org reviews tab. The HTML carried
`class="notice notice-info is-dismissible" id="ctyw_notice"`.

That is `CTYW_Settings::ctyw_admin_notice()`, a review request on **every** admin screen. It
is the plugin's only promotional surface in the notice area. The other notice is the
WooCommerce-missing error, which is operational.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | Three. `CTYW_Settings::ctyw_admin_notice` on `admin_notices` (`includes/class-ctyw-settings.php:42`). `CTYW_Init::woocommerce_missing_notice` on `admin_notices` and `network_admin_notices` (`custom-thank-you-for-woocommerce.php:198-199`) |
| Multiline `add_action(` form | No hits |
| `in_admin_header` / `admin_print_footer_scripts` | None |
| Vendor opt-out filters | None. The plugin has no `apply_filters()` calls matching the pattern at all |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls from widgets | No widgets, and no `wp_remote_*` / `fetch_feed` anywhere |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Review request, `id="ctyw_notice"` | `admin_notices` 10 → `CTYW_Settings::ctyw_admin_notice` | **suppress** (unhook) | Review begging on every admin screen. Says nothing about the site. This is the reported notice |
| WooCommerce-missing notice | `admin_notices` / `network_admin_notices` → `CTYW_Init::woocommerce_missing_notice` | **keep** | Missing-dependency notice. The plugin also deactivates itself, so the site owner needs to know why |

## Deliberately left alone

### The WooCommerce-missing notice

Missing dependency. This is on the never-suppress list.

### The "Donation for Homeless" plugin-row link

`CTYW_Init::add_description_link` adds a donation link to the plugin's row meta on the
Plugins screen. That is not the notice area, and it is one line in the plugin's own row.
Out of scope.

### `pre_option_ctyw_notice_dismiss`: not used

The notice is gated on the `ctyw_notice_dismiss` option. It returns early on `'1'`
(permanent dismissal), and otherwise prints once the stored date has passed or when the
option is absent. Answering `'1'` through core's `pre_option_` would work and write nothing.
It was not used because the instance is reachable (see Mechanism), and a targeted unhook is
the lower mechanism. Answering the vendor's option is kept for vendors whose callback cannot
be named. Note for anyone revisiting this: the gate is loose. `$last_dismissed == '1' ||
$last_dismissed === ''` returns, and a missing option (`false`) falls through to *show*.

### The dismiss AJAX handler and `ctyw_admin.js`

`wp_ajax_ctyw_dismiss_notice` stays registered. With the notice gone, nothing calls it.
The admin script is shared with the plugin's settings UI, so it is not dequeued.

## Mechanism

- tier: 2 (targeted unhook)
- phase: `admin_init` @ `LATE_PRIORITY`, from `unhook_vendor_notices()`
- vendor registers at: `CTYW_Settings::__construct()`, run by `$ctyw_settings = new CTYW_Settings();`
  at the bottom of `includes/class-ctyw-settings.php`. That file is included at the top level
  of the main plugin file, which `wp-settings.php` includes at global scope, so the instance
  is a true global. `admin_notices`, default priority
- instance reachable via: `global $ctyw_settings`, checked with `is_a( …, 'CTYW_Settings' )`.
  The priority is read back with `has_action()` rather than hard-coded

## Drift check

- `includes/class-ctyw-settings.php`: confirm the last line is still `$ctyw_settings = new CTYW_Settings();`
  and the constructor still adds `[ $this, 'ctyw_admin_notice' ]` to `admin_notices`. If the
  global is renamed, `is_a()` fails and the rule goes silent as "not installed". If the method
  is renamed, the debug log says `CTYW_Settings is loaded but ctyw_admin_notice is not on admin_notices.`

## Verification

- **Review request (unhook)**: **confirmed** on the client site where Paul saw it, 27 Nov 2026.
  It was rendering before 1.53.0 was deployed and absent after. Probe: `id="ctyw_notice"` on any
  admin screen. To produce it on a bench, delete `ctyw_notice_dismiss` (activation sets it 30
  days ahead, so a fresh install shows nothing for a month). Negative check: with WooCommerce
  deactivated, the WooCommerce-missing notice still renders

## Additions to `headwall-nag-cleanup.php` (1.53.0)

```php
// unhook_vendor_notices()
$this->unhook_ctyw_review_notice();

public function unhook_ctyw_review_notice() : void {
	global $ctyw_settings;

	if ( ! is_a( $ctyw_settings, 'CTYW_Settings' ) ) {
		// Not installed.
	} else {
		$review_callback = [ $ctyw_settings, 'ctyw_admin_notice' ];
		$priority        = has_action( 'admin_notices', $review_callback );

		if ( false === $priority ) {
			$this->log( 'custom-thank-you-for-woocommerce', 'CTYW_Settings is loaded but ctyw_admin_notice is not on admin_notices.' );
		} else {
			remove_action( 'admin_notices', $review_callback, $priority );
			$this->log( 'custom-thank-you-for-woocommerce', sprintf( 'Removed CTYW_Settings::ctyw_admin_notice from admin_notices priority %d.', $priority ) );
		}
	}
}
```
