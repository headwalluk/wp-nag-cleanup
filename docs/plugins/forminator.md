# Forminator

- slug: `forminator`
- version analysed: `1.57.2`
- source: `/vault/backups/wordpress/plugins/forminator/forminator,1.57.2.zip`
- licensing: freemium (free on wordpress.org, Forminator Pro sold by WPMU DEV)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a live
client site: *"Pro Form Templates—Now Free for Everyone!"*.

3 fleet sites. **Two rules added**, both mechanism 2. Forminator registers fifteen
`admin_notices` callbacks — the most of any plugin audited — but only two are promotional
*and* rendered outside the vendor's own screens. The rest are operational, or scoped to
Forminator's admin pages, or belong to the bundled Action Scheduler library.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 15: 8 from Forminator's admin class, 3 from Action Scheduler, 1 PHP version check, 3 commented out in a cross-sell library |
| Vendor opt-out filters | None gating the promotional notices |
| Vendor opt-out constants | `FORMINATOR_PRO` is a presence check, not an opt-out |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets |
| Freemius | Not bundled |

## Findings

| Item | Callback | Verdict | Reason |
|---|---|---|---|
| "Pro Form Templates—Now Free for Everyone!" | `promote_free_plan` | **suppress** | Gated on `'dashboard' === $screen->id` — renders on the **WordPress dashboard** |
| Review request | `show_rating_notice` | **suppress** | Review begging, triggered by published-module counts, not screen-scoped |
| "Pro is available" upsell | `show_pro_available_notice` | keep | Gated on `$_GET['page']` starting `forminator` — the vendor's own screens |
| Stripe updated | `show_stripe_updated_notice` | keep | Payment integration state |
| Stripe addon version | `check_stripe_addon_version` | keep | Version mismatch warning |
| Addons update available | `show_addons_update_notice` | keep | Update prompt |
| Encryption key missing | `set_encryption_key_notice` | keep | **Security**: submissions cannot be encrypted without it |
| CF7 importer prompt | `show_cf7_importer_notice` | keep | Offers to import existing Contact Form 7 forms — actionable site state, not a product pitch |
| PHP version notice | `$forminator_php_notice` | keep | **PHP version warning. Never suppressed** |
| Action Scheduler migration | `Controller::display_migration_notice` | keep | **Data migration prompt** |
| Action Scheduler past-due | `maybe_check_pastdue_actions` | keep | Scheduled tasks are failing — a real fault |
| Action Scheduler comment cleaner | `print_admin_notice` | keep | Database cleanup notice |

## Deliberately left alone

**`show_pro_available_notice` is the interesting near-miss.** It is unambiguously an
upsell, but its first line is:

```php
if ( ( isset( $_GET['page'] ) && 'forminator' !== substr( ..., 0, 10 ) ) || FORMINATOR_PRO ) {
    return;
}
```

so it renders only on Forminator's own admin pages. Same conclusion as Yoast, ACF and
Autoptimize earlier the same day: the vendor's own interface is out of scope by
construction. It is left alone despite being the more obviously promotional of the two
Pro notices.

**The Action Scheduler notices** belong to a widely bundled library, not to Forminator.
All three report real conditions — a pending data migration, past-due scheduled actions,
or comment-table cleanup. Suppressing any of them would hide a genuine fault, and would
do so across every plugin that bundles the library.

**`show_cf7_importer_notice`** was considered and kept. It offers to import existing
Contact Form 7 forms, which is a statement about the site's actual content rather than a
product pitch, and it names no paid product.

## Mechanism

- tier: 2 (targeted unhook)
- phase: `admin_init`, priority 999 — Forminator registers on `admin_init` at default
  priority via `Forminator_Admin::__construct`, so ours runs comfortably after
- instance reachable via: **`\Forminator_Core::get_instance()->admin`** — a documented
  singleton with a public `$admin` property holding the `Forminator_Admin` instance. No
  `$wp_filter` needed

`promote_free_plan_scripts` is unhooked from `admin_enqueue_scripts` alongside the notice,
since it exists only to style and dismiss it.

The rule guards on `class_exists`, on `method_exists( 'Forminator_Core', 'get_instance' )`,
and on the `admin` property being an object, logging a no-op if the shape changes.

## Drift check

Re-check when a new version appears in the vault:

- `library/class-core.php` — the `get_instance()` singleton and the public `$admin`
  property. If either goes, the rule no-ops and logs
- `admin/classes/class-admin.php` — `promote_free_plan()` and `show_rating_notice()`
  method names, and the `'dashboard' === $screen->id` gate on the former. If the promo
  widens beyond the dashboard, the rule still catches it; if it is renamed, the rule
  silently stops
- `show_pro_available_notice()` — if its `$_GET['page']` gate is removed it becomes an
  in-scope target

## Verification

Tested on `bench2.local` (WP 7.1) with Forminator 1.57.2 active, A/B with the rules
enabled and disabled, over authenticated admin requests:

| Check | Rules off | Rules on |
|---|---|---|
| "Pro Form Templates" on the dashboard | **1** | **0** |
| Rating notice on the dashboard | **1** | **0** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

## Additions to `headwall-nag-cleanup.php`: 2 rules, mechanism 2

```php
public function unhook_forminator_dashboard_promo() : void {
	if ( ! class_exists( 'Forminator_Core' ) || ! method_exists( '\\Forminator_Core', 'get_instance' ) ) {
		return;
	}

	$forminator_core = \Forminator_Core::get_instance();

	if ( ! is_object( $forminator_core ) || ! isset( $forminator_core->admin ) || ! is_object( $forminator_core->admin ) ) {
		$this->log( 'forminator', 'Installed, but the admin object is not reachable; no action taken.' );
	} else {
		remove_action( 'admin_notices', [ $forminator_core->admin, 'promote_free_plan' ] );
		remove_action( 'admin_enqueue_scripts', [ $forminator_core->admin, 'promote_free_plan_scripts' ] );
		remove_action( 'admin_notices', [ $forminator_core->admin, 'show_rating_notice' ] );
		$this->log( 'forminator', 'Removed promote_free_plan and show_rating_notice from admin_notices.' );
	}
}
```
