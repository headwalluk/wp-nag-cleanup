# Enable Media Replace

- slug: `enable-media-replace`
- version analysed: `4.2.2`, with EMR001 present unchanged in every vault release from `4.1.5` to `4.2.2`
- source: `/vault/backups/wordpress/plugins/enable-media-replace/enable-media-replace,4.2.2.zip`
- licensing: free (by ShortPixel; the Remove Background feature calls ShortPixel's API)
- Freemius bundled: no

## Analysis

Analysed on 8 Oct 2026 by Claude Code (Claude Opus 5), after Paul reported the notice on
`bench1.local`.

**One rule added, mechanism 4.** EMR ships a namespaced copy of ShortPixel's notice library
(`EnableMediaReplace\Notices\NoticeController`). It stores notices as `NoticeModel` objects
in the `EnableMediaReplace-notices` option and renders them on `admin_notices`, but only
on the Media Library (`upload`), the attachment editor (`attachment`) and EMR's own
Replace media screen. The one promotional item is **EMR001**, the "New Beta Feature!"
announcement for Remove Background. It is a feature advert with nothing to say about the
site. The vendor's own source marks it `// @todo Remove in 2023.`, and it is still there
in 4.2.2.

This is the second stored-notice rule, after Rank Math. It differs from Rank Math in two
ways that shaped the code:

1. **It dismisses rather than removes.** The producer, `UIHelper::featureNotice()`, is
   called inline from `EnableMediaReplacePlugin::route()`, which is the Replace media
   submenu page callback. It runs on *every* visit to that screen. `makePersistent()`
   skips an ID that is already in the store, so a dismissed entry blocks re-queuing. A
   removed entry does not: `removeNoticeByID()` would be undone the next time anyone
   replaced a file. `NoticeModel::dismiss()` is exactly what EMR's own close button does
   (`ajax_action()`, `dismisstype` `dismiss`). It suppresses the notice for
   `suppress_period`, set to `2 * YEAR_IN_SECONDS` by the producer. After that,
   `isDone()` drops the entry, the producer re-queues it, and the rule dismisses it again
2. **It runs on `admin_notices`, not `all_admin_notices`.** Core fires `admin_notices`
   first and `all_admin_notices` second (`wp-admin/admin-header.php`). The existing
   mechanism 4 dispatcher, `remove_stored_vendor_notifications()`, sits on
   `all_admin_notices` because that is where Rank Math renders. It would run after EMR had
   already printed. So the EMR rule is registered on its own at `admin_notices`,
   `EARLY_PRIORITY`. EMR's renderer is added there at the default priority 10, from a
   `current_screen` handler (`setScreen()`)

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | **1**: `admin_notices` → `NoticeController::admin_notices` (the store renderer), added in `EnableMediaReplacePlugin::setScreen()` on `current_screen`, for screen IDs `attachment`, `upload` and `media_page_enable-media-replace/enable-media-replace`. Multiline pass: none. `admin_print_footer_scripts` → `printNoticeStyle` (CSS only) |
| Vendor opt-out filters | `emr/feature/remote_notice`: **wrong scope**, see below. `emr/feature/background` turns off the Remove Background *feature*, not the announcement. `emr/upsell` gates an upsell box on EMR's own Replace and Success screens |
| Vendor opt-out constants | None. `EMR_CAPABILITY` is a capability override, not a switch |
| Dashboard widgets | None |
| Outbound calls from widgets | No widgets. There is one notice-related outbound call: `RemoteNoticeController` fetches `https://api.shortpixel.com/v2/notices.php?version=…&plugin=enable-media-replace&target=4` when one of the three screens loads, cached in transient `emr_remote_notice` for `DAY_IN_SECONDS`. The other remote calls (`ApiKeyManager`, `api.php`, `FileSystemController`) belong to the Remove Background feature, which the user triggers |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| "New Beta Feature!" Remove Background announcement | stored id `EMR001`, rendered by `NoticeController::admin_notices` on `admin_notices` | **suppress** | Feature advert for a ShortPixel API-backed feature. Says nothing about the state of the site |
| Store renderer | `admin_notices` → `NoticeController::admin_notices` | keep | Mixed. It also renders the operational notices below |
| S3-Offload incompatibility warning | `Externals` → `wp-offload.php`, `Notices::addWarning()` | keep | A real plugin conflict on this site |
| "File successfully replaced" | `views/do-replace-background.php`, `Notices::addSuccess()` | keep | Result of the site owner's own action |
| Replace-failure error | `build/shortpixel/replacer/src/Replacer.php`, `Notice::addError()` | keep | Operational error |
| Remote notices | `RemoteNoticeController`, stored under IDs chosen by ShortPixel's server | keep, **ambiguous** | The content is decided remotely and cannot be read from source. It could be a promotion or a security advisory |

## Deliberately left alone

- **`emr/feature/remote_notice` is not used.** It looks like the opt-out switch, but it
  gates the whole `setScreen()` branch. That branch is where `admin_notices` gets the
  store renderer, not only where the remote fetch happens. Returning false would hide the
  S3-Offload conflict warning, replace errors and the success notice along with any remote
  notices. It would also do nothing about EMR001 on its own terms: EMR001 would stay
  stored and undismissed
- **The store renderer is not unhooked.** It is mixed, as above
- **Remote notices are not touched.** Unknowable content is ambiguous, and ambiguous
  stays. The daily outbound fetch to `api.shortpixel.com` is an outbound-call matter for
  the `headwall-hosting` mu-plugin, not this project. (Aside: `get_remote_notices()` caches
  the string `'true'` rather than the decoded notices, so it only decodes them on the
  request that fetches)
- **`removeNoticeByID()` is not used**, even though it is a public vendor API. It is not
  durable here, because the producer re-queues on every Replace screen visit
- **`emr/upsell`** and the `emr_upsell` script are not touched. They are on EMR's own
  Replace and Success screens, out of scope by construction
- **`emr/feature/background`** is not touched. It disables a feature, not a promotion

## Mechanism

- tier: 4 (stored notification), using the vendor's dismiss path rather than removal
- phase: `admin_notices` at `EARLY_PRIORITY` (1). Not the `all_admin_notices` dispatcher,
  which fires too late for this store
- vendor registers at: `EnableMediaReplacePlugin::plugin_actions()` (on `init`, for users
  with `upload_files` or `EMR_CAPABILITY`) adds `setScreen` to `current_screen`. That adds
  `NoticeController::admin_notices` to `admin_notices` at priority 10. The producer runs
  inside the Replace media page callback, which comes after `admin_notices`. A notice
  queued there renders on the *next* request to one of the three screens, and the rule is
  what dismisses it there
- instance reachable via: `\EnableMediaReplace\Notices\NoticeController::getInstance()`,
  a public static singleton. `getNoticeByID( 'EMR001' )` returns the `NoticeModel`, then
  `dismiss()` and `update()`. All of them are public

## Drift check

Re-check when a new version appears in the vault:

- `classes/uihelper.php`: `const NOTICE_NEW_FEATURE = 'EMR001'` and `featureNotice()`.
  If the method is gone (as its `@todo` intends), the rule becomes a harmless no-op on
  sites that never banked it. Sites that did bank it keep the dismissed entry until it
  expires
- A new `NOTICE_*` constant or another `makePersistent()` call in `classes/` means a new
  ID to classify
- `build/shortpixel/notices/src/NoticeController.php`: `getInstance`, `getNoticeByID`,
  `update`. The rule logs "Notice store not reachable" if the first two disappear
- `build/shortpixel/notices/src/NoticeModel.php`: `dismiss`, `isDismissed`
- `classes/emr-plugin.php` `setScreen()`: if the renderer moves off `admin_notices`
  (for example to `all_admin_notices`), the phase must move with it
- The library names its option after the first segment of its own namespace + `-notices`,
  so any other plugin bundling it gets a separate store. This rule matches EMR's namespace
  only. Other ShortPixel plugins were not checked: none of them are in the vault

## Verification

**EMR001 — Confirmed** on `bench1.local`, 8 Oct 2026, EMR 4.2.2, as admin user
`headwall`, over authenticated HTTP:

- Before, on 1.36.0: `upload.php?mode=list`, screen asserted by `class="wp-list-table`.
  `id="EMR001"` count **1**. The store held `EMR001` with `isDismissed()` false
- After deploying 1.39.0: the same screen, `id="EMR001"` count **0**, on two separate
  captures. The store holds `EMR001` with `isDismissed()` true
- Negative check: a non-persistent warning was seeded through the vendor's own
  `NoticeController::addWarning()` before the first "after" capture. It rendered (count
  1), which proves the store renderer still runs. It was gone on the second capture,
  which is EMR's normal one-shot behaviour for non-persistent notices
- Re-queue check: loaded the Replace media screen for an image attachment (it rendered;
  EMR's own form markup was present), which runs `featureNotice()` again. Then reloaded
  the Media Library. `id="EMR001"` count **0**, and the store still holds a single `EMR001`,
  still dismissed
- No PHP fatals in the captures. No new lines in `error.log`

Surface: the notice area on the three EMR notice screens only. EMR has no dashboard
widget, no REST-rendered surface and no JavaScript-drawn promo.

No time gate: the notice is queued on the first visit to the Replace media screen.

## Additions to `headwall-nag-cleanup.php`: one stored-notice dismissal

```php
const ENABLE_MEDIA_REPLACE_PROMO_NOTICE_IDS = [
	'EMR001',
];

add_action( 'admin_notices', [ $this, 'dismiss_enable_media_replace_stored_promos' ], self::EARLY_PRIORITY );

public function dismiss_enable_media_replace_stored_promos() : void {
	$controller_class = 'EnableMediaReplace\\Notices\\NoticeController';
	// class_exists / method_exists guards, then for each named ID:
	//   getNoticeByID() → skip if absent or isDismissed() → dismiss()
	// and one update() if anything changed.
}
```
