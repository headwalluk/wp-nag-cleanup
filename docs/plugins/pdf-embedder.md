# PDF Embedder

- slug: `pdf-embedder`
- version analysed: `5.0.2`
- source: `/vault/backups/wordpress/plugins/pdf-embedder/pdf-embedder,5.0.2.zip`
- licensing: freemium (the premium build is a separate product, not in the vault)
- Freemius bundled: no

## Analysis

Analysed on 11 Nov 2026 by Claude Code (Claude Opus 5.5). Reported by Paul from a client site.

PDF Embedder adds two promotional things outside its own screens' content: a "give it a
5-star rating" review request on every admin screen, starting 7 days after activation, and
a rating line that replaces core's `admin_footer_text` on its own settings screens. Both go.
It adds no dashboard widget. Everything else promotional (the settings top bar, demo
premium settings, the Get Pro and Partners pages, the locked Elementor widget) is inside the
vendor's own UI or the Elementor editor panel, and is out of scope by construction.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` / `network_admin_notices` / `all_admin_notices` registrations | `WPorgReview::display_review` (review request); `requirements.php` closure (PHP < 7.4, operational); three in the bundled Action Scheduler (operational). Multiline pass: only `requirements.php`. No `in_admin_header` or `admin_print_footer_scripts` |
| Vendor opt-out filters | None for the review or footer. `pdfemb_tracking_src` only rewrites a UTM parameter |
| Vendor opt-out constants | None. `PDFEMB_TRACKING_SRC` is the same UTM value |
| Dashboard widgets | None |
| Outbound calls from widgets | No widgets. `SendUsageTask` posts usage data, but only when the `usagetracking` setting is on, and it defaults to `off` |
| Freemius | Not bundled |

## Findings

| Item | Hook / widget ID | Verdict | Reason |
|---|---|---|---|
| Review request | `admin_notices` 10 → `PDFEmbedder\Admin\WPorgReview::display_review` | **suppress** | *"Hey, it looks like you are using PDF Embedder for quite a while… give it a 5-star rating on WordPress.org"*. Review begging, every admin screen, `manage_options` users |
| Admin footer rating text | `admin_footer_text` 1 → `PDFEmbedder\Admin\Admin::render_page_footer` | **suppress** | *"Please rate PDF Embedder ★★★★★ on WordPress.org to help us spread the word."* Replaces core's footer text. Same verdict as WPForms' `admin_footer` (`docs/plugins/wpforms-lite.md`) |
| PHP version notice | `admin_notices` → closure in `requirements.php` | **keep** | PHP < 7.4 warning. Operational, and the boundary rule names it explicitly |
| Action Scheduler notices | `ActionScheduler_*` on `admin_notices` | **keep** | Migration and past-due action notices. Operational. Only loaded when usage tracking is on |

## Deliberately left alone

- **PHP version notice** (`requirements.php`): tells the owner their PHP is unsupported. It
  carries a WPBeginner "recommended hosting" link, but the notice itself is true and
  actionable. Never suppressed
- **`Education\SettingsTopBar`**, **`Education\DemoContent`**, **`Education\GetStarted`**,
  **`Pages\GetPro`**, **`Partners`**: upgrade prompts and cross-sells rendered inside PDF
  Embedder's own settings screens. Vendor's own screens; out of scope by construction
- **`Education\Elementor`** (5.0.2, only when Elementor is loaded): a locked "PDF Embedder"
  promotion card in the Elementor editor's widget panel. A locked upgrade panel inside
  another vendor's editor; out of scope by construction
- **Usage tracking**: off by default (`Options::LITE_DEFAULTS['usagetracking'] = 'off'`) and
  only offered as a checkbox in the vendor's settings. No opt-in notice exists, so there is
  nothing to suppress
- **`Admin::hide_unrelated_notices`**: the vendor strips every other plugin's notices from
  `admin_notices`, `all_admin_notices` and `user_admin_notices` on its own screens. Not a
  nag, and on its own screens only. Recorded because it is a blanket `$wp_filter` sweep and
  will hide operational notices from other plugins there; that is the vendor's call on its
  own screens, not ours to undo
- **`update_footer` → `__return_empty_string`**: registered unconditionally in
  `Admin::hooks()`, so the WordPress version disappears from the footer of every admin
  screen. Removes information rather than adding a nag; left alone

## Mechanism

- tier: 2 (targeted unhook), both rules
- phase: `admin_init` at `LATE_PRIORITY`, in `unhook_vendor_notices()`
- vendor registers at: `Plugin::hook_admin_init()` on `admin_init` 10, which calls
  `Admin::init()` (constructs and discards `WPorgReview`, whose `hooks()` adds the notice
  only when its own gate passes) and then `Admin::hooks()` (adds the footer filter at 1)
- instance reachable via:
  - review: not reachable. `( new WPorgReview() )->hooks();` keeps nothing, so it goes
    through `remove_discarded_instance_callback()`
  - footer: `pdf_embedder()->admin()`. `pdf_embedder()` returns a static-cached `Plugin`,
    built on `plugins_loaded`, and `admin()` returns the `Admin` it holds. Named directly
    with `remove_filter( 'admin_footer_text', [ $admin, 'render_page_footer' ], 1 )`

Not used: `pre_option_wppdf_review` answering `[ 'dismissed' => true ]`. It would work, and
writes nothing, but it needs a bespoke method for a gate the reader already handles, the
same reasoning as UserFeedback's `pre_option_userfeedback_review`.

The vendor's gate, for bench work: `wppdf_emb_activation` must be set, must be after
1708516800 (the 4.7.0 release; older installs never see the nag), and must be over 7 days
old. `wppdf_review['dismissed']` or a future `postponed_until` hides it. When the gate
hides it, nothing is registered and the reader logs "not registered" under debug.

## Drift check

Unchanged across every vault release, 4.8.2 to 5.0.2: `src/Admin/WPorgReview.php` with
`display_review`, and `render_page_footer' ], 1` in `src/Admin/Admin.php`. On a new release,
re-check:

- `src/Admin/Admin.php` `init()`: still `( new WPorgReview() )->hooks();`, and `hooks()`
  still adds `render_page_footer` at priority 1
- `src/Admin/WPorgReview.php`: class name and `display_review`
- `pdf_embedder.php`: `pdf_embedder()` still exists, and `Plugin::admin()` still returns
  `Admin\Admin`
- `src/Admin/Education/`: any new class that renders outside the vendor's own screens

## Verification

Bench: `bench1.local`, 11 Nov 2026, PDF Embedder 5.0.2 from the vault. quick-2fa is active
on bench1, so both captures are **harness**, not HTTP:
`wp --exec='define( "WP_ADMIN", true );' eval-file`, with user 1 set,
`$_REQUEST['page'] = 'pdfemb_list_options'`, `admin_init` fired and `admin_notices`
captured. The 1.51.0 build was copied over the bundled copy in
`mu-plugins/headwall-hosting/`, and its version asserted in the output.

Time gate opened by backdating `wppdf_emb_activation` to 8 days ago.

### Review request — **Confirmed (harness, and live)**

Live: Paul deployed 1.51.0 by hand to the client site where the notice was reported, on
11 Nov 2026, and confirmed it gone.

Probe: `class="notice notice-info is-dismissible wppdf-review-notice"` in the
`admin_notices` output.

| | 1.50.0 | 1.51.0 |
|---|---|---|
| review notice count | 1 | **0** |

Debug log: `pdf-embedder: Removed PDFEmbedder\Admin\WPorgReview::display_review from admin_notices priority 10.`

Nothing written: `wppdf_review` read `{"time":…,"postponed_until":0,"dismissed":false}`
before and after. The `time` write is the vendor's own `is_review_hidden()`, which runs on
every admin request either way.

### Footer rating text — **Confirmed (harness)**

Probe: the callbacks on `admin_footer_text`, not the output. On Headwall fleet sites the
headwall-hosting mu-plugin replaces the footer at priority 10
(`HeadwallHosting\Admin_Hooks::admin_thank_you_footer`), so the vendor's text never
reaches the screen there anyway. The rule is for sites that run this plugin on its own.

| | 1.50.0 | 1.51.0 |
|---|---|---|
| `admin_footer_text` 1 → `PDFEmbedder\Admin\Admin::render_page_footer` | present | **absent** |

Debug log: `pdf-embedder: Removed Admin::render_page_footer from admin_footer_text priority 1.`

### Negative check

The PHP < 7.4 notice cannot render on a PHP 8.5 bench. It is a closure in
`requirements.php`, loaded instead of the plugin, and the rule names neither. Source-verified only.

Bench left as found: the bundled copy restored (sha256 matched), the plugin uninstalled, and
the three options its uninstaller leaves behind (`pdfemb`, `wppdf_emb_activation`,
`wppdf_review`) deleted. Table, option and cron snapshots then matched.

## Additions to `headwall-nag-cleanup.php`: review request and footer rating text

```php
public function unhook_pdf_embedder_review_promos() : void {
	$this->remove_discarded_instance_callback( 'admin_notices', 'PDFEmbedder\\Admin\\WPorgReview', 'display_review', 'pdf-embedder' );

	if ( ! function_exists( 'pdf_embedder' ) || ! class_exists( 'PDFEmbedder\\Admin\\Admin' ) ) {
		// Not installed.
	} else {
		$pdf_embedder_admin = pdf_embedder()->admin();

		if ( ! $pdf_embedder_admin instanceof \PDFEmbedder\Admin\Admin || false === has_filter( 'admin_footer_text', [ $pdf_embedder_admin, 'render_page_footer' ] ) ) {
			$this->log( 'pdf-embedder', 'Installed, but Admin::render_page_footer is not on admin_footer_text; no action taken.' );
		} else {
			remove_filter( 'admin_footer_text', [ $pdf_embedder_admin, 'render_page_footer' ], 1 );
			$this->log( 'pdf-embedder', 'Removed Admin::render_page_footer from admin_footer_text priority 1.' );
		}
	}
}
```

Called from `unhook_vendor_notices()`.
