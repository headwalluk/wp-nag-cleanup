# All in One SEO (AIOSEO)

- slug: `all-in-one-seo-pack`
- version analysed: `5.0.1.1` (Lite), cross-checked against `all-in-one-seo-pack-pro` `4.3.4.1`
- source: `/vault/backups/wordpress/plugins/all-in-one-seo-pack/all-in-one-seo-pack,5.0.1.1.zip`
- licensing: freemium (free on wordpress.org, AIOSEO Pro sold at aioseo.com)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on live
client sites — the `aioseo-rss-feed` dashboard widget.

**10 fleet sites**, five of which also run AIOSEO Pro. **One rule added**, mechanism 1.

AIOSEO turned out to be the best-behaved vendor audited so far: every one of its four
dashboard widgets is individually gated behind its own documented filter. That made the
rule a single line, and — more usefully — made it possible to remove the news feed while
leaving the three widgets that carry the site's own SEO data completely intact.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 7. Four are version/conflict warnings in the bootstrap; one is bulk-edit results; two are notice systems — see below |
| Vendor opt-out filters | **`aioseo_show_seo_news`**, plus `aioseo_show_seo_setup`, `aioseo_show_seo_checklist`, `aioseo_show_seo_overview`, `aioseo_show_newsroom_callouts`, `aioseo_show_whats_new_modal`, `aioseo_show_in_admin_bar` |
| Vendor opt-out constants | None |
| Dashboard widgets | **4**: `aioseo-seo-setup`, `aioseo-seo-checklist`, `aioseo-overview`, `aioseo-rss-feed` |
| Outbound calls from widgets | `https://plugin-cdn.aioseo.com/newsroom.json`, read by `Newsroom::getItems()` |
| Freemius | Not bundled |

## Findings

| Item | Hook / filter | Verdict | Reason |
|---|---|---|---|
| "What's New in AIOSEO" widget | `aioseo_show_seo_news` → `aioseo-rss-feed` | **suppress** | Vendor newsroom feed. No site state, and an outbound fetch on render |
| "AIOSEO Overview" widget | `aioseo_show_seo_overview` → `aioseo-overview` | keep | TruSEO scores for this site's own content |
| "AIOSEO Setup" widget | `aioseo_show_seo_setup` → `aioseo-seo-setup` | keep | Only shown while the setup wizard is **incomplete**. Configuration state |
| "AIOSEO Checklist" widget | `aioseo_show_seo_checklist` → `aioseo-seo-checklist` | keep | This site's outstanding SEO tasks |
| Notice system | `admin_notices` → `Notices::notices` | keep — **mixed** | See below |
| Block-editor notices | `admin_notices` → `WpNotices::adminNotices` | keep | Per-post notices surfaced into the editor |
| Bulk action results | `admin_notices` → `BulkActions::showAdminNotice` | keep | Reports what a bulk edit did |
| PHP / WordPress version warnings | `admin_notices` → `aioseo_php_notice`, `aioseo_wordpress_notice`, `aioseo_php_notice_deprecated` | keep | **Version warnings. Never suppressed** |
| Lite-alongside-Pro warning | `admin_notices` → `aioseo_lite_notice` | keep | **Plugin conflict notice. Never suppressed** |
| In-plugin feature callouts | `aioseo_show_newsroom_callouts` | keep | Own screens only — see below |
| "What's new" update modal | `aioseo_show_whats_new_modal` | keep — **ambiguous** | See below |

## Deliberately left alone

### The notice system is mixed output, and the mixture is the never-suppress list

`Notices::__construct()` builds its members and then registers one dispatcher:

```php
$this->review              = new Review();
$this->migration           = new Migration();
$this->import              = new Import();
$this->deprecatedWordPress = new DeprecatedWordPress();
$this->conflictingPlugins  = new ConflictingPlugins();

add_action( 'admin_notices', [ $this, 'notices' ] );
```

`Review` is a review nag and would be a fair target. The other four are not: `Migration` is
a **data migration prompt**, `DeprecatedWordPress` is a **WordPress version warning**, and
`ConflictingPlugins` is a **plugin conflict notice**. Three of the five are named
explicitly on the never-suppress list.

Unlike Premium Addons, there is no public method holding the operational half that could be
re-hooked on its own — the split is inside a notification store, not inside the dispatcher.
So the whole thing stays. One surviving review nag is a much better outcome than a
suppressed migration prompt.

### The in-plugin callouts are on AIOSEO's own screens

`ScreenCallout` looks like a candidate — it is newsroom-driven feature promotion, and it
hooks `in_admin_header`, which is not screen-specific. But its own gate is:

```php
private function getScreenSlug() {
    $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

    return 0 === strpos( $page, 'aioseo-' ) ? $page : '';
}
```

An empty slug means nothing renders, so callouts only ever appear on pages whose slug
starts with `aioseo-`. That is the vendor's own settings area, which `CLAUDE.md` puts out
of scope by construction. `aioseo_show_newsroom_callouts` exists and would work; it is not
used, because this project does not tidy other people's settings screens.

### The "what's new" modal is ambiguous, so it stays — but it is a judgement call

This is the one finding worth a second opinion.

`UpdateModal` hooks `admin_footer` with **no screen restriction**, so unlike the callouts it
does render on core screens. It fires once per user per version, listing what changed
between the version that user last saw and the current one, sourced from the same
`newsroom.json` feed.

**The case for suppressing it:** it is a modal, it interrupts a core screen, and its
contents are fetched from a vendor-controlled feed — release notes today, an upsell
whenever the vendor decides otherwise.

**The case for keeping it**, which won: it reports what changed in software the site is
actually running, which is closer to a changelog than to a sale. It is self-limiting —
`get_user_meta( $userId, self::SEEN_META )` is written before rendering, so it shows once
per version even if dismissed by closing the page — and a fresh install is deliberately
shown nothing rather than a backlog.

Ambiguous, so under the boundary rule it does not go in. If the feed is ever observed
carrying upgrade prompts rather than release notes, `aioseo_show_whats_new_modal` is a
one-line rule and this decision should be revisited.

### The three other widgets are the site's own data

`aioseo-overview` (TruSEO scores), `aioseo-seo-checklist` (outstanding tasks) and
`aioseo-seo-setup` (shown only while the setup wizard is incomplete) all report the state
of this site rather than the vendor's news. They are in the same category as Yoast's Posts
Overview, which was also left alone. Each has its own filter, so keeping them cost nothing.

## Mechanism

One rule, tier 1, registered at file scope.

`Dashboard::addDashboardWidgets()` gates each widget on its own filter before registering
it:

```php
// Add the News widget.
if (
    $this->canShowWidget( 'seoNews' ) &&
    apply_filters( 'aioseo_show_seo_news', true ) &&
    aioseo()->access->isAdmin()
) {
    wp_add_dashboard_widget(
        'aioseo-rss-feed',
        esc_html__( "What's New in AIOSEO", 'all-in-one-seo-pack' ),
        [ $this, 'displayRssDashboardWidget' ]
    );
}
```

`__return_false` means `wp_add_dashboard_widget()` is never called at all — cleaner than
removing the meta box afterwards, and it takes the `plugin-cdn.aioseo.com/newsroom.json`
fetch with it.

### One filter covers Lite and Pro

The fleet runs both, on overlapping sites. AIOSEO Pro 4.3.4.1 carries its own copy of
`app/Common/Admin/Dashboard.php` with the same filter name and the same widget ID
(`app/Common/Admin/Dashboard.php:74`), so the single line covers both. Verified against the
Pro package in the vault, not assumed from the shared directory name.

## Drift check

Re-check when a new version appears in the vault:

- `app/Common/Admin/Dashboard.php` — the `aioseo_show_seo_news` filter name and its
  position in the `&&` chain. It is a documented filter, so drift is unlikely, but a rename
  would silently no-op
- The same file **in the Pro package**, which is versioned independently of Lite
- `app/Common/Newsroom/UpdateModal.php` — if the modal's content changes character from
  release notes to promotion, `aioseo_show_whats_new_modal` becomes a rule
- `app/Common/Newsroom/ScreenCallout.php` — `getScreenSlug()`. If the `aioseo-` prefix test
  is dropped, callouts start appearing on core screens and come into scope
- `app/Common/Admin/Notices/Notices.php` — if the dispatcher ever exposes the review notice
  separately from `Migration`, `DeprecatedWordPress` and `ConflictingPlugins`, revisit

## Verification

Tested on `bench2.local` (WP 7.1) with AIOSEO 5.0.1.1 active, over authenticated admin
requests, A/B against v1.19.0 (no rule) and v1.20.0. Counted structurally, by each meta
box's `id` attribute:

| Check | Rules off | Rules on |
|---|---|---|
| `id="aioseo-rss-feed"` on the dashboard | **1** | **0** |
| `plugin-cdn.aioseo.com` referenced on the page | 1 | **0** |
| `id="aioseo-overview"` | 1 | **1** — preserved |
| `id="aioseo-seo-setup"` | 1 | **1** — preserved |
| AIOSEO notice markup still on the page | present | present |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |
| `index.php`, `plugins.php`, `options-general.php`, `edit.php` | HTTP 200 | HTTP 200 |

The two preserved widgets are the important half of that table: the rule removes one of
four AIOSEO widgets and leaves the ones carrying site data alone.

No debug log line, and that is correct — a mechanism 1 rule cannot report a suppression,
because the vendor reads the filter and the callback is core's `__return_false`, not ours.

The Pro-only path was not exercised on the bench; the Pro package was read from the vault
and carries the same filter and widget ID.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 1

```php
// All in One SEO "What's New in AIOSEO" dashboard widget. Same filter in Lite
// and Pro. AIOSEO 5.0.1.1, AIOSEO Pro 4.3.4.1.
// docs/plugins/all-in-one-seo-pack.md
add_filter( 'aioseo_show_seo_news', '__return_false' );
```
