# Novamira

- slug: `novamira`
- version analysed: `1.12.7` (vault also holds 1.12.3 to 1.12.6; the welcome notice is identical in all five)
- source: `/vault/backups/wordpress/plugins/novamira/novamira,1.12.7.zip`
- licensing: freemium (Novamira Pro is a separate plugin, detected by `NOVAMIRA_PRO_VERSION`)
- Freemius bundled: no

## Analysis

Analysed on 7 Oct 2026 by Claude Code (Claude Opus 5).

Novamira is an MCP server that gives AI agents PHP-execution and filesystem access to
WordPress. Its upsell module, `includes/pro-upsell.php`, does four things: a "Get Pro"
submenu entry, a "Get Pro" plugin-row link, a Connect-page card, and a dismissible
**"Novamira Pro is here."** welcome notice. Only the notice reaches outside the vendor's
own UI. It renders on the **Dashboard** and the **Plugins** screen as well as on
Novamira's screens. Every other notice the plugin emits is operational.

The rule is the simplest kind: a plain named function at the default priority.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 14 single-line hits across 8 files (table below). No `all_admin_notices`, `in_admin_header` or `admin_print_footer_scripts` hits. The multiline pass found only `admin_menu`, `plugins_loaded` and two internal hooks |
| Vendor opt-out filters | **None**. No `apply_filters()` call matches the opt-out pattern |
| Vendor opt-out constants | **None**. `NOVAMIRA_PRO_VERSION` short-circuits the upsell, but it is the Pro plugin's own constant. Defining it would make Novamira believe Pro is installed |
| Dashboard widgets | **None** |
| Outbound calls from widgets | No widgets. See "Specializations fetch" below for the one promotional outbound call |
| Freemius | Not bundled |

## Findings

| Item | Hook / callback | Verdict | Reason |
|---|---|---|---|
| "Novamira Pro is here." welcome notice | `admin_notices` 10 → `novamira_render_pro_welcome_notice` (`includes/pro-upsell.php:177`) | **suppress** | Pure upsell with a "Discover more" button to novamira.ai/pro. Renders on `dashboard`, `plugins` and every `novamira` screen until dismissed |
| MCP dependency error | `admin_notices` / `network_admin_notices` → `novamira_render_mcp_dependency_notice` | keep | Bundled MCP adapter failed to load. Operational |
| WordPress too old for Abilities API | → `novamira_render_wordpress_compatibility_notice` (`includes/compatibility.php:92`) | keep | WordPress version warning. Never suppressed by rule |
| AI Abilities disabled after domain change | closure on `admin_notices` (`novamira.php:679`) | keep | True and actionable site state |
| Sandbox safe mode active | closure on `admin_notices` (`includes/sandbox-loader.php:132`) | keep | A sandbox file crashed. Operational, and the error type says so |
| Ghost Mode bypass | `Novamira\GhostMode\render_bypass_notice` | keep | A wp-config constant is overriding a visibility policy. Operational |
| Ghost Mode pending update | `Novamira\GhostMode\render_pending_update_notice` | keep | An update is available. Operational |
| Connection regressions | `Novamira\Troubleshoot\Notice\maybe_render` | keep | Application Passwords disabled, or HTTPS lost while OAuth clients exist. Operational |
| Design / Skills action results and reload prompts | `Novamira\Design\Notices\render`, `Novamira\Skills\Notices\render` | keep | One-shot results of the admin's own actions, on Novamira's own screens |

## Deliberately left alone

- **"Get Pro" submenu entry** (closure on `admin_menu` priority 99, pushed into
  `$submenu['novamira-connect']`). This is the vendor's own menu, so it is out of scope by
  construction. Same call as MonsterInsights' rotating promo submenu and 404 to 301's
  `PromoMenu`. It is also a closure, so it could not be unhooked anyway
- **"Get Pro" plugin-row action link** (closure on `plugin_action_links_novamira/novamira.php`).
  This is the vendor's own row on the Plugins screen. The link is inert until clicked, and
  the closure is unreachable
- **Connect-page Pro card** (`novamira_render_pro_upsell_card()`). This is on the vendor's
  own screen, so it is out of scope. It is also **dead code in 1.12.7**: nothing calls it,
  and the bench Connect page rendered no `novamira-pro-card`
- **The custom "Deactivate" link to an uninstall-review page** (`includes/uninstall-review.php`).
  This is not a nag and not a survey. It asks what data to remove on delete, and it sends
  nothing out
- **Specializations fetch.** A cron (`novamira_specializations_refresh`, every three days)
  `GET`s `https://license.dynamic.ooo/changelogs/novamira-pro/specializations.json`. Its
  user-agent carries `home_url()`. It exists only to personalise the upsell blurb. It is
  skipped when Pro is active. This is outbound-call policy, not a notice, and cron is
  outside this plugin's admin-request gate. If it is to be blocked, that belongs in the
  `headwall-hosting` mu-plugin's outbound blocking, not here
- **All the operational notices** in the table above

## Mechanism

- tier: 2 (targeted unhook, **by name**)
- phase: `admin_init` at `self::LATE_PRIORITY`
- vendor registers at: `includes/pro-upsell.php:177`, at file scope. `novamira.php:255`
  `require_once`s it unconditionally while the plugin loads, so the callback exists long
  before `admin_init`
- instance reachable via: N/A. It is a plain global function

```php
remove_action( 'admin_notices', 'novamira_render_pro_welcome_notice' );
```

Default priority 10. The vendor registers it with no explicit priority.

No vendor gate was answered instead, for example by pre-filling the
`novamira_pro_dismissed_welcome` user meta. Unhooking writes nothing and covers every user.

## Drift check

- `includes/pro-upsell.php`: the `add_action('admin_notices', callback: 'novamira_render_pro_welcome_notice')`
  line. If the function is renamed or given a priority, the `has_action()` guard stops the
  debug line appearing on a site that has the plugin. That is the signal
- `novamira_render_pro_welcome_notice()`: if it ever starts carrying anything operational,
  the rule becomes mixed-output and must be withdrawn
- `novamira_render_pro_upsell_card()`: if a later release calls it from somewhere other
  than the Connect page, re-assess it

## Verification

Bench: `bench2.local`, WP 7.1.3, PHP 8.5, Novamira 1.12.7 freshly installed, 7 Oct 2026.

**No time gate.** `novamira_pro_upsell_installed_at` is recorded on activation but never
read by the renderer, so the notice shows immediately.

Bench access note: the avahi (mDNS) resolver on this host resolved `bench2.local` to
`192.168.122.1` (virbr0), which answers 404. Requests went through `curl --resolve bench2.local:80:192.168.0.107`.
`wp-login.php` also 404s, so the session used an auth cookie minted with
`wp_generate_auth_cookie()`.

### Welcome notice — **Confirmed (bench)**

The probe is the `novamira-pro-notice` class that the vendor passes to `wp_admin_notice()`.
Each screen was asserted structurally.

| Screen | Assertion | Before (1.36.0) | After (1.37.0) |
|---|---|---|---|
| `index.php` | `id="dashboard-widgets"` | 1 | **0** |
| `plugins.php` | `id="the-list"` | 1 | **0** |

The debug log after deploy shows `novamira: Removed novamira_render_pro_welcome_notice from admin_notices.`.
On `admin.php?page=novamira-connect` the class string still appears twice. Both are
selectors in the vendor's own inline CSS (`.notice:not(.novamira-pro-notice)`), not
notices. The notice text "Novamira Pro is here" counts 0 on all three screens.

Nothing was written: `novamira_pro_dismissed_welcome` user meta stayed absent for the test
user.

### Negative checks

| Check | Result |
|---|---|
| Dashboard, Plugins and Connect screens render | 200, screen assertions pass |
| "Get Pro" submenu entry still present | yes, out of scope |
| PHP fatals / warnings | **0** |
| Operational Novamira notices | None was triggered on the bench. They are separate callbacks and are not named by the rule |

The bench was restored afterwards. Novamira was uninstalled through its own uninstaller,
and the test user and debug mu-plugin were removed. The uninstaller left
`novamira_pro_upsell_installed_at` behind, which was deleted by hand. The table, option,
cron and user snapshots then diffed clean.

### Live — welcome notice **Confirmed**, 7 Oct 2026

Paul deployed the rule to production and confirmed that the "Novamira Pro is here." notice
was gone.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 2

```php
// In unhook_vendor_notices():
$this->unhook_novamira_pro_welcome_notice();
```
