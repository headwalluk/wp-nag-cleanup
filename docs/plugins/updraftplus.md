# UpdraftPlus - Backup/Restore

- slug: `updraftplus`
- version analysed: `1.26.7`
- source: `/vault/backups/wordpress/plugins/updraftplus/updraftplus,1.26.7.zip`
- licensing: freemium. The free build on wordpress.org, and UpdraftPlus Premium, which is
  the same plugin plus a `udaddons/` directory. Premium is not in the vault
- Freemius bundled: no. Adverts come from TeamUpdraft's own `Updraft_Notices_1_3` library
  (`vendor/team-updraft/common-libs/src/updraft-notices/`), subclassed as
  `UpdraftPlus_Notices` in `includes/updraftplus-notices.php`

## Analysis

Analysed on 21 Sep 2026 by Claude Code (Claude Opus 5).

Reported by Paul from a live site: *"Automatically back up before updates — With
UpdraftPlus Premium, your site is backed up before every update"*, a
`.updraft-ad-container` advert with a *Back up before updates* link to teamupdraft.com.
He saw it only on `update-core.php`. That is by design. The advert has no admin-notice
registration at all. It is printed by `UpdraftPlus_Admin::core_upgrade_preamble` on core's
`core_upgrade_preamble` action, which only `update-core.php` fires. A jQuery snippet then
moves it into the first `.wrap p`. The same advert is also printed on the single plugin
and theme update screens (`update.php?action=upgrade-plugin` / `upgrade-theme`), by
`admin_action_upgrade_pluginortheme`.

The plugin has a second promotional surface that Paul had not seen: the *"Thank you for
installing UpdraftPlus!"* panel on the dashboard. It is a cross-sell for Premium,
WP-Optimize, AIOS, Internal Link Juicer, WP Overnight, Burst Statistics and Simba Hosting's
WooCommerce shop. It appears only once the backup directory is 28 days old.

Everything else in the notice area is operational. That covers about 40 `all_admin_notices`
warnings: storage misconfiguration, disk space, missing log file, execution time, PHP
extensions, restore in progress and migration status. The rest of UpdraftPlus's advertising
is on its own settings screen or in its backup report emails, and both are out of scope.

Two rules. The advert uses core's `pre_option_` short-circuit on the vendor's own dismissal
timestamp. The panel uses a targeted `remove_action()` on the vendor's global instance.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | ~50, all on `all_admin_notices`. One promotional: `UpdraftPlus_Admin::show_admin_notice_ad` (dashboard panel). The rest are operational warnings, listed below |
| Multiline `add_action(` form | None |
| `in_admin_header` / `admin_print_footer_scripts` | `admin_index_print_footer_scripts` (UpdraftClone line injected into core's PHP nag widget, see below), `print_phpseclib_notice_scripts`, `print_unfinished_restoration_dialog_scripts`, the deactivation dialog, `Updraft_Dashboard_News::admin_print_footer_scripts` (Premium only) |
| Core surfaces outside the notice area | `core_upgrade_preamble` → `core_upgrade_preamble`; `admin_action_upgrade-plugin` / `admin_action_upgrade-theme` → `admin_action_upgrade_pluginortheme`. **This is the reported advert** |
| Vendor opt-out filters | `updraftplus_autobackup_blurb`, `updraft_notices_force_id`, `updraftplus_com_link`. None is an opt-out, see below |
| Vendor opt-out constants | `UPDRAFTPLUS_NOADS_B` (every advert, rejected, see below), `UPDRAFTPLUS_NONEWSFEED` (report-email feed only), `UPDRAFTPLUS_FORCE_DASHNOTICE`, `UPDRAFTPLUS_ENABLE_TOUR` |
| Dashboard widgets | None in the free build. `Updraft_Dashboard_News_Offer` is only built when `udaddons/` exists (Premium) |
| Outbound calls | The report email fetches the TeamUpdraft RSS feed at backup time, in the free build (`class-updraftplus.php`, `get_updraftplus_rssfeed`). That is cron, not an admin render, so it is out of reach and out of scope |
| Freemius | Not bundled |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| "Automatically back up before updates" advert, `update-core.php` | `core_upgrade_preamble` → `UpdraftPlus_Admin::core_upgrade_preamble` | **suppress** | Premium upsell. Tells the owner nothing about the site |
| Same advert, single plugin / theme update screens | `admin_action_upgrade-plugin`, `admin_action_upgrade-theme` → `admin_action_upgrade_pluginortheme` | **suppress** | Same notice id (`autobackup`), same gate |
| "Thank you for installing UpdraftPlus!" dashboard panel | `all_admin_notices` → `UpdraftPlus_Admin::show_admin_notice_ad` | **suppress** | Pure cross-sell: seven products, none of them about this site |
| UpdraftClone line in core's "PHP update required" widget | `admin_print_footer_scripts` → `admin_index_print_footer_scripts` | **keep (ambiguous)** | See below |
| ~40 storage, disk, log, PHP, restore and migration warnings | `all_admin_notices` | **keep** | Operational |

## Deliberately left alone

### `UPDRAFTPLUS_NOADS_B` — not used

This is the vendor's own "no adverts" constant, and it would remove the reported advert.
Every reader was checked. Defined (any value), it also:

- removes the "Premium" link from the top menu of UpdraftPlus's **own settings screen**
  (`templates/wp-admin/settings/header.php`)
- empties the advert pool behind the bottom-of-settings and top-of-settings adverts on that
  same screen (`UpdraftPlus_Notices::notices_init`, when true)
- changes the **backup report email**: no news feed, no advert (`class-updraftplus.php`)

The vendor's settings screens are out of scope by construction, and so is an email. A
constant also cannot be scoped to admin requests the way a filter registration can. There
is a trap too: defined as `false`, it **forces** the dashboard panel on, whatever the age
and dismissal gates say (`admin.php`, `(defined('UPDRAFTPLUS_NOADS_B') && !UPDRAFTPLUS_NOADS_B)`).

### `updraftplus_autobackup_blurb` — not used

It filters the output of `core_upgrade_preamble`. `__return_empty_string` would clear the
advert on `update-core.php`. With Premium installed, though (`UpdraftPlus_Addon_Autobackup`
exists), the free build passes `''` through this filter and the autobackup addon fills it
with its own **"back up before updating" checkbox**, which is operational. The empty-string
filter would erase that. It also does not reach the single plugin and theme update screens.

### `remove_action( 'core_upgrade_preamble', … )` — not used

Same collateral as above: with Premium, the same callback prints the addon's checkbox.
Guarding it on `class_exists( 'UpdraftPlus_Addon_Autobackup' )` would rest on when a
Premium class loads, and that cannot be verified because Premium is not in the vault.
Removing `admin_action_upgrade_pluginortheme` was not attractive either. That callback
`include`s `wp-admin/admin-header.php` itself before printing, so taking it out changes
when the update screen's header is emitted.

### The settings-screen adverts

`templates/wp-admin/settings/form-contents.php` (`do_notice(false, 'bottom')`) and the
`do_notice($advert)` call in `admin.php` rotate a random pool across both positions: support, UpdraftVault,
storage enhancements, Migrator, UpdraftCentral, the review request, social links, AIOS,
WP-Optimize and the seasonal sale. They are all on UpdraftPlus's own page. That is out of
scope, and not touched.

### UpdraftClone line on core's PHP nag widget — ambiguous, kept

On the dashboard, when core shows its `#dashboard_php_nag` "PHP update required" widget,
`admin_index_print_footer_scripts` injects *"You can test running your site on a different
PHP (or WordPress) version using UpdraftClone credits."* above the widget's buttons. It
links to UpdraftPlus's own Migrate tab. UpdraftClone is a paid service, but the line is
attached to a real, operational warning, and it offers a relevant way to act on it. That
makes it ambiguous, so it stays. Its selector is also `.updraft-ad-container`, so a class
grep on the dashboard finds it. Do not mistake it for the autobackup advert.

### Operational notices, kept

Storage (Google Drive, Dropbox, OneDrive, pCloud, Azure, DreamObjects, Google Cloud,
UpdraftVault: missing addon, partial or empty settings, multiple destinations), disk space,
unreadable or missing log files, `show_admin_warning_execution_time`, LiteSpeed, PclZip,
phpseclib, WordPress version, "no settings", debug mode, restore in progress, UpdraftCentral
connection failure, multisite, migration notices (`migrator-lite.php`) and the temporary-clone
notices. All of them tell the owner something true about backups on this site.

### Onboarding tour and deactivation dialog

`includes/updraftplus-tour.php` runs only for a new install, on UpdraftPlus's own screens
and the plugins list. The deactivation dialog asks whether to delete settings, which is an
operational choice. Both left alone.

## Mechanism

### Autobackup advert

- tier: core's `pre_option_{$option}` short-circuit on the vendor's dismissal option. Same
  shape as mechanism 1, filed with it; the hook is core's, and only the option name comes
  from the vendor
- phase: registered in `register_vendor_optouts()`, so on admin page requests only
- vendor gates:
  - `admin.php` `admin_action_upgrade_pluginortheme`:
    `if ($dismissed_until > time()) return;`
  - `includes/updraftplus-notices.php` `check_notice_dismissed('dismissautobackup')`:
    `$time_now < get_updraft_option('updraftplus_dismissedautobackup', 0)`. `do_notice('autobackup', …)`
    returns nothing when this is true, and that covers `core_upgrade_preamble`
- the rule returns `PHP_INT_MAX`. **Not `__return_true`**: `true` compares as `1`, so that
  would show the advert. This is the same trap as Check & Log Email and Simple Custom Post
  Order
- `UpdraftPlus_Options::get_updraft_option()` is a plain `get_option()` followed by the
  `updraftplus_get_option` filter, so `pre_option_` is honoured. The Multisite addon swaps
  in site options. It is Premium, and Premium has the autobackup addon, which does not
  show this advert, so that case does not arise

Nothing is written. The vendor's dismiss handler (`dismissautobackup`, over admin-ajax)
still writes its own value as normal, and our filter is not registered on AJAX requests.
The only other reader of the option is `get_settings_keys()`, which lists it for the debug
modal, settings wipe and migration bundle. On an admin page the debug modal would show
`PHP_INT_MAX`. That is cosmetic, and it is on the vendor's own screen.

**Why this and not the Simple Custom Post Order conclusion.** That document rejected a
future timestamp on `pre_option_` as more code than the unhook. Here the unhook routes
all carry collateral: the Premium checkbox, and the header include. This is the only route
that reaches all three surfaces without touching either.

### Dashboard panel

- tier: 2 (targeted unhook), by name, no `$wp_filter` reader
- phase: `admin_init` at `self::LATE_PRIORITY`, in `unhook_vendor_notices()`
- vendor registers at: `UpdraftPlus::admin_menu` (hooked to `admin_menu` priority 9, and
  to `admin_init` priority 9) includes `admin.php`, which ends
  `$updraftplus_admin = new UpdraftPlus_Admin()`. The constructor calls the private
  `admin_init()`, which adds `show_admin_notice_ad` to `all_admin_notices` at priority 10
  when `$pagenow === 'index.php'`, the user can `update_plugins`, `udaddons/` is absent,
  the backup directory's `index.html` is over 28 days old, and
  `updraftplus_dismisseddashnotice` has passed
- instance reachable via: `global $updraftplus_admin`

## Drift check

```bash
for PLUGIN_ZIP in $(ls -1 /vault/backups/wordpress/plugins/updraftplus/*.zip | sort -V | tail -5); do
  echo "$(basename "${PLUGIN_ZIP}") gate=$(unzip -p "${PLUGIN_ZIP}" updraftplus/admin.php | command grep -c "get_updraft_option('updraftplus_dismissedautobackup'") notices=$(unzip -p "${PLUGIN_ZIP}" updraftplus/includes/updraftplus-notices.php | command grep -c "'dismissautobackup'") panel=$(unzip -p "${PLUGIN_ZIP}" updraftplus/admin.php | command grep -c "add_action('all_admin_notices', array(\$this, 'show_admin_notice_ad'))")"
done   # 1.26.3 to 1.26.7: gate=1 notices=2 panel=1
```

- If the `autobackup` notice's `dismiss_time` changes from `dismissautobackup`, or the
  dismissal option is renamed, the advert comes back silently. The debug line
  `updraftplus: Answered updraftplus_dismissedautobackup as dismissed.` stops appearing
  on `update-core.php`. That is the drift signal
- If the panel moves off `all_admin_notices` or is renamed, `has_action()` finds nothing
  and the rule stays silent. That is indistinguishable from "not 28 days old yet", so
  re-check this source line on each new vault version

## Verification

Bench: `bench2.local`, WP 7.1.1, UpdraftPlus **1.26.7** (already installed and active; the
bench is a copy of the site Paul reported it from), 21 Sep 2026. Before = 1.33.1 (HEAD),
after = working copy (1.34.0). Both were deployed over the bench's
`mu-plugins/headwall-hosting/headwall-nag-cleanup.php` with `HEADWALL_NAG_CLEANUP_DEBUG` on
from a temporary `mu-plugins/00-nag-debug-temp.php`. There were three warm-up dashboard
requests after each deploy, and the screen was asserted on every capture.

Gates: both adverts were already dismissed on the bench (`updraftplus_dismissedautobackup`
= `1796291444`, `updraftplus_dismisseddashnotice` = `1820477716`). Both were backdated to
one hour ago, followed by `wp cache flush`. The panel's 28-day gate reads the mtime of
`wp-content/updraft/index.html`, which is owned by the web user and could not be touched.
So the panel pass used the vendor's own force switch instead: a temporary mu-plugin
defining `UPDRAFTPLUS_NOADS_B` as `false`, which registers the panel regardless of age.

### Autobackup advert, `update-core.php` — **Confirmed** (bench)

| Check | Before | After |
|---|---|---|
| Screen asserted: `update-core-php` body class | 2 | 2 |
| `class="updraft-ad-container` on `update-core.php` | **1** | **0** |
| Debug log | — | `updraftplus: Answered updraftplus_dismissedautobackup as dismissed.` (once) |
| `updraftplus_dismissedautobackup` after | backdated value | **unchanged**. Nothing written |

### Autobackup advert, single plugin / theme update screens — **Source-verified only**

`update.php?action=upgrade-plugin` runs a real update, so it was not exercised. It reads
the same option through the same `get_updraft_option()`.

### Dashboard panel — **Confirmed** (bench, with the vendor's force switch)

| Check | Before | After |
|---|---|---|
| Screen asserted: `id="dashboard-widgets"` | 1 | 1 |
| `id="updraft-dashnotice"` | **1** | **0** |
| Debug log | — | `updraftplus: Removed UpdraftPlus_Admin::show_admin_notice_ad from all_admin_notices priority 10.` (4: three warm-ups and the capture) |

### Negative checks

| Check | Result |
|---|---|
| UpdraftClone line in `#dashboard_php_nag` (the one kept) | 1 before, **1 after** |
| `plugins.php` notice count | 1 before, 1 after |
| PHP warnings | Five `update-core.php` warnings (`Attempt to read property "current" on false`), identical before and after. They come from core, because the bench has no update data from wordpress.org. **0** from this plugin or UpdraftPlus |

Bench left as found: both dismissal options restored to their original values, test user
removed, both temp mu-plugins removed, bench nag-cleanup file restored (1.33.0). The
table, cron and user snapshots match. The option snapshot differed by one row,
`elementor-custom-breakpoints-files`, which Elementor wrote during the run. It was
removed, and Elementor regenerates it. One Quick Draft auto-draft (post `220335`), created
by the test user's dashboard loads and reassigned to user 1, was left for the full bench reset that followed.

### Live — autobackup advert **Confirmed**, 21 Sep 2026

Paul deployed 1.34.0 by hand to a live site and tested it there. The *"Automatically back
up before updates"* advert on `update-core.php` was gone.

The dashboard panel was not part of that check. It remains bench-confirmed only.

## Additions to `headwall-nag-cleanup.php`: 2 rules

```php
// In register_vendor_optouts():
add_filter( 'pre_option_updraftplus_dismissedautobackup', [ $this, 'answer_updraftplus_autobackup_dismissed' ] );

public function answer_updraftplus_autobackup_dismissed() : int {
	$this->log( 'updraftplus', 'Answered updraftplus_dismissedautobackup as dismissed.' );

	return PHP_INT_MAX;
}

// In unhook_vendor_notices():
$this->unhook_updraftplus_dashboard_panel();

public function unhook_updraftplus_dashboard_panel() : void {
	global $updraftplus_admin;

	if ( ! is_a( $updraftplus_admin, 'UpdraftPlus_Admin' ) ) {
		// Not installed.
	} else {
		$panel_callback = [ $updraftplus_admin, 'show_admin_notice_ad' ];
		$priority       = has_action( 'all_admin_notices', $panel_callback );

		if ( false === $priority ) {
			// Not the dashboard, not yet 28 days old, or dismissed by the site owner.
		} else {
			remove_action( 'all_admin_notices', $panel_callback, $priority );
			$this->log( 'updraftplus', sprintf( 'Removed UpdraftPlus_Admin::show_admin_notice_ad from all_admin_notices priority %d.', $priority ) );
		}
	}
}
```
