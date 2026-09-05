# CSS Hero

- slug: `css-hero`
- version analysed: `5.1.0`
- source: `/vault/backups/wordpress/plugins/css-hero/css-hero,5.1.0.zip`
- licensing: premium (commercial, licence-activated)
- Freemius bundled: no

## Analysis

Analysed on 5 Sep 2026 by Claude Code (Claude Opus 5), from a nag Paul reported on a live
client site: a news feed dashboard widget.

2 fleet sites. **One rule added**: the "From the CSS Hero world" dashboard widget, which
pulls an RSS feed on every dashboard render. The plugin's only other admin notice is a
licence activation prompt and is deliberately preserved.

### Search checklist

| Pass | Result |
|---|---|
| `admin_notices` registrations | 2, both `csshero_activation_notice` — a **licence** notice |
| Vendor opt-out filters | None |
| Vendor opt-out constants | None. There is an option, `wpcss_hidedashnews`, but setting it would be a database write |
| Dashboard widgets | **1**: `widget_cssheronews`, "From the CSS Hero world", default `normal` context |
| Outbound calls from widgets | An RSS fetch via `feed.php` in `csshero_create_rss_box()` |
| Freemius | Not bundled |

## Findings

| Item | Hook / ID | Verdict | Reason |
|---|---|---|---|
| "From the CSS Hero world" widget | `wp_dashboard_setup` → `widget_cssheronews` | **suppress** | Vendor news RSS, fetched on every dashboard render. No site state |
| Licence activation notice | `admin_notices` → `csshero_activation_notice` | **keep** | *"Welcome to CSS Hero! Let's activate your product… Proceed to Product Activation"* |

## Deliberately left alone

### The activation notice is a licence notice

`css-hero-main.php:441` registers it only when `! wpcss_check_license()`:

```php
if (!wpcss_check_license()) add_action( 'admin_notices', 'csshero_activation_notice' );
```

CSS Hero is a paid product that does nothing until activated, so this notice tells the
site owner something true and immediately actionable. It is first on the never-suppress
list and stays. Verified present on the bench **with our rules active**.

### `wpcss_hidedashnews` was rejected

The widget registration checks `get_option('wpcss_hidedashnews') != 1`, so setting that
option to `1` would suppress it at source. Not used: writing to a vendor's options leaves
permanent residue that removing this plugin would not undo — the approach rejected for
WPB Product Slider and again for CookieYes.

`remove_meta_box()` achieves the same visible result, per request, with no database
write.

### A note on the vendor's own hint

The registration line ends with a comment: `// comment this line to get rid of dashboard
updates`. That is an invitation to edit the plugin's source, which this project does not
do — patching vendor files is exactly the approach the README rules out for WooCommerce.

## Mechanism

- tier: 3 (dashboard widget removal)
- phase: `wp_dashboard_setup`, priority 999
- vendor registers at: `csshero_register_widgets()` on `wp_dashboard_setup` at default
  priority 10, so our 999 runs after it
- instance reachable via: N/A. Worth noting the callback is a **named function**, so
  `remove_action( 'wp_dashboard_setup', 'csshero_register_widgets' )` would also work and
  would be marginally earlier. `remove_meta_box()` was chosen instead for consistency with
  the existing mechanism 3 machinery, and because removing the meta box equally prevents
  the render callback — and therefore the RSS fetch — from ever running
- context: `normal`. `wp_add_dashboard_widget()` is called with only three arguments, so
  the context defaults

## Drift check

Re-check when a new version appears in the vault:

- `css-hero-main.php` — `csshero_register_widgets()`. If the widget ID `widget_cssheronews`
  or the default context changes, the rule silently stops matching
- `csshero_activation_notice` — must remain untouched. If its content ever changes from a
  licence prompt to marketing, revisit
- If the vendor adds a filter around the widget, promote the rule to mechanism 1

## Verification

Tested on `bench2.local` (WP 7.1) with CSS Hero 5.1.0 active and **unlicensed**, A/B with
the rule enabled and disabled, over authenticated admin requests:

| Check | Rule off | Rule on |
|---|---|---|
| `id="widget_cssheronews"` on the dashboard | **1** | **0** |
| "Welcome to CSS Hero!" licence notice | present | **still present** |
| PHP fatals | 0 | 0 |
| Front page | HTTP 200 | HTTP 200 |

The licence notice surviving is the check that matters: the plugin is unlicensed on the
bench, which is precisely the state in which that notice must reach the site owner.

## Additions to `headwall-nag-cleanup.php`: 1 rule, mechanism 3

```php
[
	'widget_id' => 'widget_cssheronews',
	'context'   => 'normal',
	'vendor'    => 'CSS Hero 5.1.0',
	'reason'    => 'From the CSS Hero world; RSS feed fetched on render',
],
```
