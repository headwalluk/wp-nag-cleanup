# Delete Comments & Disable Comments – Ultimate Comment Manager

- slug: `delete-all-comments-of-website`
- version analysed: `7.1` (plugin header now reads *"WP Comment Cleaner – Delete All
  Comments, Disable Comments, Bulk Delete & Remove Comments"*; Freemius still reports the
  older name, which is what the notice shows)
- source: `/vault/backups/wordpress/plugins/delete-all-comments-of-website/delete-all-comments-of-website,7.1.zip`
- licensing: freemium (free on wordpress.org, "Plus" sold through Freemius)
- Freemius bundled: **yes**, SDK `2.11.0`, module id `7346`, menu under `tools.php?page=delete_comment`

## Analysis

Analysed on 22 Sep 2026 by Claude Code (Claude Opus 5).

Reported by Paul from a live site. The notice shown in the global admin notice area is the
Freemius usage-tracking opt-in, sticky id `connect_account`:

> We made a few tweaks to the plugin, **Opt in to make "Delete Comments & Disable Comments –
> Ultimate Comment Manager" better!**

The plugin's own code adds nothing promotional to the notice area or the dashboard. Its
upsell (Premium badges, an "Upgrade Now" box) is confined to its own Tools screen.

**This audit reversed the project's earlier decision on `connect_account`.** The opt-in
had been declined three times, starting with
[`independent-analytics.md`](independent-analytics.md), and the first reason given there
was that "the SDK exposes no filter on this notice". That was wrong. Every Freemius notice
passes through `fs_show_admin_notice_{slug}` at render time
(`FS_Admin_Notice_Manager::_admin_notices_hook`, SDK 2.2.0 and later). This project has
used that filter since 1.22.1 for the trial and affiliate stickies. It reaches
`connect_account` in exactly the same way, with no database write. With that reason gone,
all that was left was proportionality (the notice is added once and one click clears it).
The project's own scope lists usage-tracking opt-in prompts as suppressible, so Paul
decided on 22 Sep 2026 to suppress it for every claimed Freemius slug.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | 4 in the plugin's own code, all closures: `nav_show_admin_notice()` (line 97) and three in `nav_handle_auto_delete_settings()` (1719–1737). All report the result of an action the admin just took. No multiline registrations. No `in_admin_header` or `admin_print_footer_scripts` |
| Vendor opt-out filters | None in the plugin's own code. Freemius: `fs_show_admin_notice_{slug}`, used |
| Vendor opt-out constants | None |
| Dashboard widgets | None |
| Outbound calls from widgets | None. (Its own screen enqueues Bootstrap and SweetAlert2 from jsdelivr, but only on `tools_page_delete_comment`) |
| Freemius | Yes, SDK 2.11.0. `is_org_compliant` defaults to true. No `trial` and no `has_affiliation`, so the trial and affiliate producers never fire. No `unique_affix`, so the filter suffix is the slug |

## Findings

| Item | Hook | Verdict | Reason |
|---|---|---|---|
| Freemius opt-in, "We made a few tweaks…" | `admin_notices` → `FS_Admin_Notice_Manager::_admin_notices_hook`, sticky id `connect_account`, from `Freemius::add_sticky_optin_admin_notice()` | **suppress** (render filter) | Usage-tracking consent prompt. Says nothing about the site |
| Freemius "You are just one step away – Complete activation now" | same renderer, **no id** (non-sticky), from the `is_plugin_new_install() \|\| is_only_premium()` branch | keep | For a premium-only build it is the licence activation step, which is operational. It carries no stable id to match on anyway |
| Action-result notices | `admin_notices` → closures | keep | "Automatic spam cleanup has been scheduled…" and similar. The admin just did that |
| Premium badges, "Upgrade Now – Get Premium Access" box | inline in the plugin's own Tools page | out of scope | The vendor's own screen |

## Deliberately left alone

### Every other Freemius sticky

The SDK's full set of sticky ids was listed from `add_sticky(` in `class-freemius.php`:
`premium_activated`, `activation_pending`, `connect_account`, `trial_started`,
`trial_promotion`, `trial_expired`, `activation_complete`, `license_expired`,
`plan_upgraded`, `ownership_changed`, `affiliate_program`. Only `trial_promotion`,
`affiliate_program` and now `connect_account` are claimed. The rest are licence, trial-state,
activation or ownership information and stay.

### `connect_account` is not the licence-activation prompt

This was checked specifically, because the module sets `is_premium => true`. The branch in
`Freemius::_admin_init_action()` that handles new installs and premium-only builds adds the
"Complete activation now" notice through `_admin_notices->add()` with **no id**. Only the
`else` branch, an existing install that has been moved into activation mode, calls
`add_sticky_optin_admin_notice()`. That function is the sole producer of `connect_account`.
Matching on the id therefore cannot catch the premium activation step.

### Unhooking or `remove_sticky()` — not used

These were rejected in `independent-analytics.md` and the objections still hold.
`_admin_notices_hook` renders every Freemius notice for the module, licence ones included,
so unhooking it would suppress too much. `remove_sticky( 'connect_account' )` writes to the
vendor's storage. The render filter needs neither.

### What suppression does not do

Hiding the prompt does not opt the site in or out. Freemius stays un-opted-in, so no
tracking payload is sent either way. The sticky stays in Freemius storage, and it comes
back if this plugin is removed.

## Mechanism

- tier: 1 (vendor hook), `fs_show_admin_notice_delete-all-comments-of-website`
- phase: file scope, `register_vendor_optouts()`, at `self::LATE_PRIORITY`
- vendor registers at: `Freemius::_admin_init_action()` →
  `add_sticky_optin_admin_notice()` → `FS_Admin_Notice_Manager::add_sticky()`. It is stored
  once, guarded by `$this->_storage->sticky_optin_added`
- instance reachable via: N/A

The callback is the shared `hide_freemius_promo_notice()`. It returns `false` for ids in
`FREEMIUS_PROMO_NOTICE_IDS` and passes everything else through unchanged. Freemius
suppresses any value that is not exactly `true`.

## Drift check

- `freemius/includes/class-freemius.php`: `add_sticky_optin_admin_notice()` must still be
  the only `add_sticky(…, 'connect_account', …)`. If `connect_account` is ever reused for
  licence activation, remove it from `FREEMIUS_PROMO_NOTICE_IDS` at once, since that
  removal covers every claimed slug
- `freemius/includes/managers/class-fs-admin-notice-manager.php`: the
  `fs_apply_filter( $this->_module_unique_affix, 'show_admin_notice', … )` call and its
  `true !== $show_notice` gate
- The plugin's `fs_dynamic_init()` array: if a `unique_affix` key appears, the filter
  suffix stops being the slug and the rule silently stops matching
- The bundled SDK version, currently 2.11.0

## Verification

**Live-confirmed** by Paul on the reporting client site, 22 Sep 2026, running 1.35.0:
the `connect_account` opt-in is gone.

Before that it was source-verified. The filter tag was built with `fs_apply_filter()` in SDK 2.11.0
(`"fs_{$tag}_{$module_unique_affix}"`), and the affix defaults to the slug. The render
filter itself was live-confirmed in 1.22.1 for `trial_promotion`
([`featured-images-for-rss-feeds.md`](featured-images-for-rss-feeds.md)). This change adds
an id and two slugs to that mechanism. It does not add a new mechanism.

To re-check it on a bench, install an older version of the plugin and then update it, so
that `connect_account` is stored. `data-id="connect_account"` should then go from 1 before
to 0 after on `edit.php`. The negative check is that the plugin's
own Tools screen and any action-result notice still render. Note that the opt-in only
appears in the update-into-activation-mode path, so a fresh install shows the unrelated
"one step away" notice instead.

## Additions to `headwall-nag-cleanup.php`: `connect_account` claimed, slug registered

```php
const DACW_FREEMIUS_SLUG = 'delete-all-comments-of-website';

// FREEMIUS_PROMO_NOTICE_IDS gains 'connect_account'.

add_filter(
	'fs_show_admin_notice_' . self::DACW_FREEMIUS_SLUG,
	[ $this, 'hide_freemius_promo_notice' ],
	self::LATE_PRIORITY,
	2
);
```
