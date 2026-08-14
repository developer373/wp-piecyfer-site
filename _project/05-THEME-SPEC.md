# `piecyfer-theme` — build specification

**Replaces:** `wp-content/themes/tecnologia` — (VamTam) Tecnologia v4.2, nulled — **and**
`wp-content/plugins/vamtam-elementor-integration-tecnologia` v1.0.10.

**Method.** Every claim below is checked against one of four sources, and each is named at the
point of use:

| Evidence | What it proves |
|---|---|
| `_project/snapshots/ref-a/html/*.html` (39 pages) | what the browser actually receives and renders |
| `wp-content/uploads/elementor/css/post-*.css` | what Elementor compiles from saved settings |
| `_project/scripts/dump-eldata.php` (76 live documents, 2,343 elements) | what is saved in the page data |
| the theme / plugin source | what *could* run |

Where the four disagree, the rendered HTML wins. Several conclusions here contradict
`00-AUDIT-REPORT.md`; those are called out explicitly.

> **Headline.** The theme is a shell. Elementor Pro's Theme Builder owns header, footer, single,
> archive, search and 404. What the theme genuinely contributes to a rendered page is
> **four stylesheets (108 KB), two JS files (24 KB), one `:root{}` block of 171 CSS custom
> properties, and about 40 lines of wrapper markup.** Everything else — 10.9 MB, 473 files,
> 25,660 lines of PHP — is either admin-only, WooCommerce/Events-Calendar code for plugins that
> are not installed, demo-import payload, or the "fallback" (non-Elementor) rendering branch that
> can never execute on this site.
>
> **But** the companion plugin is not a shell, and neither is one database option nobody has
> costed yet. See §6 and §5.3.

---

## Contents

1. [Theme file structure — what actually runs](#1)
2. [The licence bypass and other injected code](#2)
3. [Customisations that must survive](#3)
4. [CSS](#4)
5. [JavaScript](#5)
6. [The companion plugin](#6)
7. [The `vamtam_*` settings census](#7)
8. [The proposed `piecyfer-theme`](#8)
9. [Risks](#9)

---

<a id="1"></a>
## 1. Theme file structure — what actually runs

### 1.1 Scale

| Measure | Value |
|---|---|
| On disk | 10,980 KB, 473 files |
| PHP, excluding `vendor/` | 120 files, 25,660 lines |
| PHP that executes on a front-end request | ~12 files of real logic, ~150 KB (of which `vamtam/classes/elementor-bridge.php` alone is 56 KB) |
| CSS shipped in `assets/css/dist` | 486,078 B |
| CSS actually served | **107,864 B (22%)** |
| JS served | `all.min.js` 22,659 B + `low-priority.js` 1,263 B |

### 1.2 Bootstrap graph

`functions.php:12` → `vamtam/classes/framework.php` → `new VamtamFramework(...)` at
`functions.php:14`. The constructor (`framework.php:44-67`) does, in order:

```
framework.php:46-48  spl_autoload_register    → vamtam/classes/*.php  (+ vamtam/admin/classes/*.php only when is_admin(), :86)
framework.php:52     set_constants()          (:142-189)
framework.php:53     load_languages()
framework.php:54     load_functions()         (:344-411)  ← see table below
framework.php:55     load_admin()             (:428)      → returns immediately unless is_admin()
framework.php:57     require class-tgm-plugin-activation.php   128,537 B — parsed on EVERY request, self-gates admin-only at :334
framework.php:58     require samples/dependencies.php          demo-plugin list
framework.php:60-64  after_setup_theme / widgets_init / purchase-code filters
framework.php:66     VamtamElementorBridge::get_instance()
```

`load_functions()` is unconditional — front end included:

| Line | Loads | Front-end verdict |
|---|---|---|
| `framework.php:347` | `vamtam/helpers/fonts.php` | **177,450 B of font metadata `include`d into a global on every single request.** Pure waste |
| `:349` | `helpers/init.php` | option accessors — needed |
| `:393-397` | `helpers/icons.php`, `base.php`, `template.php`, `css.php` | small, partly needed |
| `:399` | `helpers/woocommerce-integration.php` | 40,297 B; whole body gated at its line 9 — **inert, no WooCommerce installed** |
| `:400` | `helpers/the-events-calendar-integration.php` | registers 2 `tribe_*` hooks that never fire — **inert** |
| `:403` | `helpers/frontend-wrappers.php` | Gutenberg block filters, bypassed on Elementor pages |
| `:405-406` | `VamtamOverrides::filters()`, `VamtamEnqueues::actions()` | the real front-end surface |
| `:408-410` | `helpers/migrations.php` | **6-byte file containing only `<?php`** |

Two further unconditional requires sit in `functions.php` itself: `customizer/setup.php:35` and
`customizer/preview.php:37`. The entire 312 KB customizer library with its 12 control classes is
loaded on the front end to serve **two** Customizer controls
(`vamtam/options/core/core.php:13` `header-logo-type`, `:26` `wc-product-gallery-zoom`), **neither
of which has `compiler => true`, so neither contributes a single CSS variable.**

### 1.3 Templates — what Elementor overrides

There are **no custom page templates**: no file in the theme carries a `Template Name:` header.

| File | Verdict |
|---|---|
| `header.php` | **Load-bearing but tiny.** Emits GTM (§3.4), `wp_head()`, `#top`, `do_action('vamtam_body')`, then `elementor_theme_do_location('header')` (`:40`); opens `#page.main-container` → `#main-content` → `templates/header/sub-header` (`:47`) → `#main.vamtam-main.layout-full` (`:55`) |
| `footer.php` | **Load-bearing but tiny.** Closes those wrappers, wraps `elementor_theme_do_location('footer')` in `.footer-wrapper > footer#main-footer.main-footer` (`:18-24`), `wp_footer()` |
| `page.php`, `single.php`, `404.php` | Delegate to `elementor_theme_do_location('single')`; the fallback branches never run — the 404 snapshot renders `data-elementor-type="error-404"` |
| `archive.php` | Delegates to `elementor_theme_do_location('archive')` — both archive templates are TB documents |
| `search.php` | The search snapshot renders `data-elementor-type="search-results"` (TB document 8711) |
| `author.php`, `attachment.php` | **No `elementor_theme_do_location` call at all** — pure theme markup. Not in the pixel harness URL list. See §9 |
| `index.php`, `loop.php`, `templates/post/**` (17 files) | The theme's own blog loop. Unreachable: the blog listing is an Elementor page (id 93) and posts render through TB document 8502 |
| `sidebar.php` | Always empty — `VamtamTemplates::get_layout()` returns `'full'` unconditionally when Elementor Pro is active (`vamtam/classes/templates.php:24-26`) |
| `comments.php` | Replaced by the `post-comments` widget on the TB single template |
| `templates/header/**` (7 files) | **Dead** — the TB header wins. This includes `templates/header/top/main-menu.php`, the *only* consumer of `VamtamMenuWalker` and of the `vamtam-fallback` JS handle |
| `templates/side-buttons.php` | **Live** — printed on every page by `VamtamOverrides::footer_additions()` (`vamtam/classes/overrides.php:171-175`). Emits `#scroll-to-top.vamtam-scroll-to-top` |
| `woocommerce/` (8 files), `tribe-events/` (1) | **Dead** — neither plugin is installed (verified against `active_plugins`) |

Six `get_template_part()` calls point at files that **do not exist** and silently render nothing:
`templates/overlay-search` (`overrides.php:172`), `templates/share` (`page.php:30`, `archive.php:22`,
`search.php:21`, `attachment.php:88`), `templates/post/meta/tax`.

### 1.4 The exact wrapper markup to reproduce

Verified present on **39/39** captured pages:

```html
<div id="top"></div>
<!-- elementor header location -->
<div id="page" class="main-container">
  <div id="main-content">
    <div id="sub-header" class="layout-full elementor-page-title">
      <div class="meta-header">
        <!-- Elementor `page-title` location -->
      </div>
    </div>
    <div id="main" role="main" class="vamtam-main layout-full">
      <!-- content -->
    </div>
  </div>
  <div class="footer-wrapper" style="">
    <footer id="main-footer" class="main-footer"><!-- elementor footer location --></footer>
  </div>
</div>
<div id="scroll-to-top" class="vamtam-scroll-to-top">
  <div id="scroll-to-top-text">top</div>
</div>
```

`limit-wrapper` appears on 1 of 39 pages; `page-wrapper` on 22 (single posts and pages without a
TB `single` template).

### 1.5 `body_class`

The theme adds 12 classes. Only **four** are referenced by any served stylesheet:

| Class | Rules in served CSS | Keep? |
|---|---|---|
| `elementor-active` | 6 | yes |
| `responsive-layout` | 2 (`#scroll-to-top` bottom, `.page-wrapper` direction) | yes |
| `vamtam-font-smoothing` | 1 (antialiasing on 30 elements) | yes |
| `vamtam-is-elementor` | 1, and it is a `body:not(...)` negation on a class that is always present → **the rule can never match** | emit for parity, no effect |
| `full`, `header-layout-logo-menu`, `has-page-header`, `no-middle-header`, `elementor-pro-active`, `vamtam-wc-cart-empty`, `wc-product-gallery-slider-active`, `layout-full` | **0** | markup parity only |

### 1.6 Widget areas, menus, CPTs

- **No sidebar is registered.** `vamtam/classes/sidebars.php` is only reached when Elementor Pro is
  *inactive* (`framework.php:418`). Independently confirmed: `wp_options.sidebars_widgets` has all
  five block widgets in `wp_inactive_widgets` and no other bucket.
- **One nav-menu location** (`framework.php:318-324`, `primary-menu`), consumed only by the dead
  `templates/header/top/main-menu.php`. `theme_mods_tecnologia['nav_menu_locations']` is `[]`.
  Every live menu is selected by ID inside an Elementor or ElementsKit widget.
- **No `register_widget()`, no `register_post_type()`, no `register_taxonomy()` anywhere.**

### 1.7 Dead weight summary

| Item | Size | Why dead |
|---|---|---|
| `samples/content.xml` | 5,139,571 B | demo import payload — **47% of the whole theme** |
| `vamtam/assets/fonts/` | 1,597,312 B | `theme-icons` referenced 7× in served CSS, `icomoon` 1× |
| `assets/css/dist/fallback/**` + `.css.map` | ~378 KB | fallback branch never runs |
| `assets/css/src/**` | ~370 KB | LESS build sources |
| `vamtam/customizer/` | 312,637 B | loaded front-end, drives 2 controls, 0 CSS vars |
| `vamtam/admin/` | 245,914 B | admin only |
| Grunt/npm tooling | 393,481 B | `package-lock.json` alone is 358,782 B |
| `vamtam/helpers/fonts.php` | 177,450 B | loaded into a global every request |
| `class-tgm-plugin-activation.php` | 128,537 B | parsed every request |
| `vendor/` | 21,542 B | **`vendor/autoload.php` is never required by any theme file** |
| Never-referenced files | ~7 KB | `helpers/lessphp-extensions.php`, `classes/video-player.php`, empty `helpers/migrations.php` |

---

<a id="2"></a>
## 2. The licence bypass and other injected code

### 2.1 The bypass

`wp-content/themes/tecnologia/functions.php:2` — the first executable statement in the theme:

```php
add_filter( 'vamtam_purchase_code_import_override', function() { return true; } );
```

Not VamTam code. It short-circuits the Envato purchase-code check so the demo importer and the
update channel behave as though a valid licence were present. Its companion,
`Vamtam_Updates_3` in the plugin (§6.7), POSTs `home_url` plus the purchase code to
`https://updates.vamtam.com/0/envato/check` on every update-transient refresh.

### 2.2 Scan results

Both trees were scanned for `eval`, `base64_decode`, `gzinflate`, `gzuncompress`, `str_rot13`,
`create_function`, `assert()`, the `/e` preg modifier, variable-variables, `curl_init`,
`fsockopen`, remote `file_get_contents`, and stray `wp_head`/`wp_footer` injections.

**Zero hits in either the theme or the plugin.** The plugin tree is byte-identical to the
"as found (infected)" commit `72dc08f2`. The only outbound HTTP in the theme is the VamTam
licence API, all admin-only (`vamtam/admin/helpers/updates/version-checker.php:115,149,193,242,302,326`).

### 2.3 Non-stock edits found (5 in the theme, all deliberate, all site-specific)

| # | Location | What |
|---|---|---|
| 1 | `functions.php:2` | the licence bypass |
| 2 | `header.php:11-17` | Google Tag Manager loader, container `GTM-WDKS9B2G`, injected **before** `<meta charset>` |
| 3 | `header.php:29-32` | GTM `<noscript>` iframe after `<body>` |
| 4 | `functions.php:80-83` | Google site-verification meta |
| 5 | `functions.php:85-88` | the button `template_content` filter — **which is a no-op, see §3.3** |

`00-AUDIT-REPORT.md` §3.1 lists three customisations. **It missed the GTM container**, which is
the one with the most business impact if lost.

### 2.4 Risks worth carrying forward (not malware, but not safe either)

| Severity | Finding |
|---|---|
| **High** | `vamtam_additional_js[head\|body\|footer]` is echoed **raw** inside `<script>` tags by the plugin (`vamtam-elementor-integration.php:226,234,242`) — no escaping, no capability check. On a site that *was* compromised this is a prime persistence vector. It currently holds 6,164 bytes of legitimate site code (§5.3) — audit it before trusting it, then move it into version-controlled files |
| **High** | `download_elementor_pro_translations()` (`vamtam-elementor-integration.php:364`) fetches a zip over **plain HTTP** from `translate.elementor.com` and `unzip_file()`s it into `WP_LANG_DIR/plugins`. No TLS, no checksum. Arbitrary file write on a MITM |
| **Medium** | `Vamtam_Updates_3::check()` merges whatever the vendor endpoint returns straight into `$updates->response` (`class-vamtam-updates.php:33,82-88`) with **no check that the returned slugs belong to this plugin** |
| **Medium** | `includes/kits/documents/kit.php:258` calls `is_plugin_active()` during kit-CSS regeneration, which can run on the front end where `wp-admin/includes/plugin.php` is not loaded — latent fatal |

None of these survive the rewrite, which is most of the argument for doing it.

---

<a id="3"></a>
## 3. Customisations that must survive

### 3.1 Google site verification — **keep**

`functions.php:80-83`:

```php
function add_google_site_verification() {
    echo '<meta name="google-site-verification" content="VmNgA23F6JsL4JinPIqE7Wc7T57e0IHuPsRgvzC0Blk" />';
}
add_action('wp_head', 'add_google_site_verification');
```

Present on 39/39 captured pages. Losing it breaks Search Console ownership. Two lines to port.

### 3.2 One-page menu href fix — **drop**

`functions.php:21-31`:

```php
function vamtam_onepage_menu_hrefs( $atts, $item, $args ) {
    if ( 'custom' === $item->type && 0 === strpos( $atts['href'], '/#' ) ) {
        $atts['href'] = $GLOBALS['vamtam_inner_path'] . $atts['href'];
    }
    return $atts;
}
if ( ( $path = parse_url( get_home_url(), PHP_URL_PATH ) ) !== null ) {
    $GLOBALS['vamtam_inner_path'] = untrailingslashit( $path );
    add_filter( 'nav_menu_link_attributes', 'vamtam_onepage_menu_hrefs', 10, 3 );
}
```

Stock VamTam, not a local edit. It rewrites `/#anchor` menu hrefs to include the subdirectory
path — which matters *only* because this install lives at `http://localhost/piecyfer` rather than
a domain root. On production (`https://piecyfer.com/`) `parse_url(..., PHP_URL_PATH)` returns
`null`, so **the filter is never even added**. It is a localhost-only artefact.

Port it anyway if you want local and production to render identically — it is 8 lines and
harmless. But it is not a site customisation and it does nothing in production.

### 3.3 Elementor button-template filter — **do not port; it does nothing**

`functions.php:85-88`:

```php
add_filter('elementor/widget/button/template_content', function($content) {
    $content = preg_replace('/<span class="elementor-button-content-wrapper">.*?<\/span>/s', '', $content);
    return $content;
});
```

**This hook does not exist.** `grep -rn 'elementor/widget/button/template_content'` across
`wp-content/plugins/elementor/` and `wp-content/plugins/elementor-pro/` returns nothing. The only
dynamic widget filters Elementor defines in `includes/base/widget-base.php` are
`elementor/widget/{$widget_name}/skins_init` (`:175`, an action) and
`elementor/widget/render_content` (`:664`, not name-scoped). The filter is registered against a
hook name that is never fired.

It is also *wrong*: the non-greedy `.*?</span>` would match up to the **first** closing tag,
deleting the wrapper together with the button text. Buttons still show their text on all 39 pages,
which independently proves it never runs.

**The job it looks like it does is actually done client-side**, by the `footer` slot of
`vamtam_additional_js` — see §5.3. That is the code that must be ported, not this.

### 3.4 Google Tag Manager — **keep** (missing from the audit)

`header.php:11-17` and `:29-32`, container **`GTM-WDKS9B2G`**. Present on 39/39 pages.
Note it is emitted *before* `<meta charset>`, a minor spec violation worth fixing in the port.

### 3.5 Theme-mod-scoped data at risk — **must be migrated, see §8.5**

| Where | Value | Lost on theme switch? |
|---|---|---|
| `theme_mods_tecnologia['custom_logo']` | attachment `987718` | **yes** |
| `theme_mods_tecnologia['custom_css_post_id']` | post `2547` | **yes** |
| `wp_posts` id 2547, `post_type=custom_css`, **`post_name='tecnologia'`**, 3,713 bytes | the site's Additional CSS | **yes** — WordPress looks this post up by the active stylesheet slug |
| `theme_mods_tecnologia['vamtam_force_demo_menu']` | `1` | irrelevant, drop |

---

<a id="4"></a>
## 4. CSS

### 4.1 What is actually loaded

Extracted from all 39 captured pages. The theme contributes **exactly four stylesheets on every
page and nothing else**, and the companion plugin contributes **zero CSS**:

| Handle | File | Bytes | Media | Pages |
|---|---|---|---|---|
| `vamtam-front-all` | `vamtam/assets/css/dist/elementor/elementor-all.css` | 75,956 | all | 39/39 |
| `vamtam-theme-elementor-max` | `.../responsive/elementor-max.css` | 5,231 | `(min-width: 1025px)` | 39/39 |
| `vamtam-theme-elementor-below-max` | `.../responsive/elementor-below-max.css` | 9,652 | `(max-width: 1024px)` | 39/39 |
| `vamtam-theme-elementor-small` | `.../responsive/elementor-small.css` | 17,025 | `(max-width: 767px)` | 39/39 |

Plus two inline blocks:

| `<style id=…>` | Bytes | Emitted by | Content |
|---|---|---|---|
| `vamtam-front-all-inline-css` | ~1,050 | `enqueues.php:222` | two `@font-face` rules: `icomoon` and `vamtam-theme` |
| `vamtam-theme-options` | 6,183 | `enqueues.php:558-601`, hooked `wp_print_styles` priority 1 | `:root { … }` — **171 CSS custom properties** |

`elementor-max-low` is registered (`enqueues.php:230`) but the file does not exist, so the
`file_exists()` guard at `:240` skips it. Everything under `dist/fallback/` (≈378 KB, including
`all.css` 79,888 B and 86,636 B of WooCommerce CSS) belongs to the non-Elementor branch and is
**never enqueued on this site**.

There is **no runtime CSS compilation, no concatenation, and nothing written to
`wp-content/uploads`.** `VamtamLessBridge` compiles no LESS despite its name — it only flattens
option values. `vamtam-css-cache-timestamp` is a query-string cache-buster, not a cache.

### 4.2 The CSS custom properties — the load-bearing part

The `<style id="vamtam-theme-options">` block declares **171 distinct `--vamtam-*` properties on
`:root`**, byte-identical on every page checked (`ref-a/home-root.html`, `ref-a/blogs.html`,
`baseline/blogs.html` — 171/171/171).

**Where the values come from** (`enqueues.php:558-601`):

```
Elementor Kit  (post 5, _elementor_page_settings, 170 keys)
   └─ VamtamElementorBridge::get_translated_kit()      elementor-bridge.php:921-1230  (~310 lines)
   └─ merged with 14 hard-coded defaults               assets/css/src/fallback/additional-css-variables.php:11-38
   └─ VamtamLessBridge::prepare_vars_for_export()      less-bridge.php:32
   └─ printed as :root{}                               enqueues.php:584-600
```

The Customizer contributes **nothing** — no option in `vamtam/options/core/core.php` carries
`compiler => true`.

The kit uses **VamTam-specific global ids**, which is what makes the translation possible:

| Kit `system_colors[_id]` | Title | Value | → |
|---|---|---|---|
| `vamtam_accent_1` … `vamtam_accent_8` | Accent 1–8 | `#3469B3`, `#F5F5F5`, `#5F6567`, `#6E6F73`, `#FFFFFF`, `#242627`, `#00000026`, `#00000099` | `--vamtam-accent-color-N`, `-N-hc` (auto-contrast, computed by `VamtamColor`), `-N-rgb` |
| `vamtam_sticky_header_bg_color` | Sticky Header Bg Color | `#3469B3` | `--vamtam-sticky-header-bg-color` |

| Kit `system_typography[_id]` | Family | Desktop size | → |
|---|---|---|---|
| `vamtam_primary_font` | Helvetica | 16px | `--vamtam-primary-font-*` (14 props) |
| `vamtam_h1` … `vamtam_h6` | Inter Tight | 60/48/30/24/20/16px | `--vamtam-h1-*` … `--vamtam-h6-*` (14 props each) |

Category breakdown of the 171:

| Category | Count |
|---|---|
| Typography (7 families × ~13 props: family, weight, style, transform, and size/line-height/letter-spacing × desktop/tablet/phone) | 85 |
| Icon glyph codes `--vamtam-icon-{Web-development,Security,Cloud,…}` | 28 |
| Accents (8 × `{ , -hc, -rgb }`) | 24 |
| Static hard-coded (paddings, radii — all `0px`, overlay colours, loading animation) | 15 |
| Heading + body colours (`--vamtam-h1-color` … `-h6-color` = `#0A0D31`, `--vamtam-primary-font-color` = `#00000099`) | 7 |
| Inputs and buttons (`--vamtam-btn-bg-color` `#010ED0` → hover `#242627`) | 6 |
| Links (`#242627`, hover/active `#010ED0`) | 4 |
| Layout (`--vamtam-site-max-width: 1280px`) | 1 |
| Sticky header | 1 |

**Four more are written from JavaScript at runtime**, not from PHP:
`--vamtam-scrollbar-width` and `--vamtam-scroll-ratio` (`general.js`, `custom-animations.js`) and
`--vamtam-sticky-mleft` / `--vamtam-sticky-mright` (`custom-animations.js`).

**Ten more are emitted per-widget by Elementor**, compiled from control `selectors` in the
companion plugin, and appear only in `uploads/elementor/css/post-*.css`:
`--vamtam-menu-color`, `--vamtam-menu-color-hover`, `--vamtam-mobile-menu-max-height`,
`--vamtam-img-spacing`, `--vamtam-img-border-radius`, `--vamtam-content-padding`,
`--vamtam-cols`, `--vamtam-arrows-size`, `--vamtam-nav-btns-gap`, `--vamtam-nav-btns-spacing`.

### 4.3 Which of the 171 are actually consumed

Cross-referencing declarations against `var()` references in the four served stylesheets:

| | Count |
|---|---|
| Declared in `:root` | 171 |
| Referenced by the served theme CSS | 159 distinct names |
| Declared in `:root` **but never read by anything on this site** | **40** |
| Referenced but **never declared anywhere** (silently fall back to nothing) | **35** |

The 40 never-read declarations include every `--vamtam-btn-*`, `--vamtam-input-*`,
`--vamtam-overlay-*`, `--vamtam-border-radius*`, `--vamtam-site-max-width`,
`--vamtam-primary-font-color`, most `-rgb`/`-hc` variants, and 9 of the 28 icon codes.

Of the 35 referenced-but-undeclared, 10 are the per-widget ones above (legitimately absent until a
widget populates them) and the rest genuinely resolve to nothing —
`--vamtam-h1-decoration` … `-h6-decoration`, `--vamtam-*-letter-spacing-desktop` for h2–h6,
`--vamtam-icon-search`, `--vamtam-icon-close`, `--vamtam-icon-arrow-left/right`,
`--vamtam-col-gap`, `--vamtam-tabs-border-color`.

**Critically: no generated Elementor CSS, no Additional CSS, and no inline block anywhere on the
site reads a `--vamtam-*` variable.** Confirmed by scanning all of
`wp-content/uploads/elementor/css/*.css` plus the `wp-custom-css` and `vamtam-front-all-inline-css`
blocks — 0 `var(--vamtam-…)` references. The variables exist purely to feed the four theme
stylesheets. That makes the boundary clean: **if we reproduce the four stylesheets and the `:root`
block together, nothing outside the theme notices.**

### 4.4 `#main-menu` and navigation styling — **there is nothing to port**

This is the sharpest finding in the CSS section, and it contradicts the framing of the task.

- The theme's `#main-menu` styling exists only in `assets/css/src/fallback/menus.less` and
  `.../fallback/responsive/mobile-header.less`, compiled into `dist/fallback/all.css` (27 hits)
  and `dist/fallback/responsive/mobile-header.css` (23 hits). **Neither file is ever enqueued.**
- `grep '#main-menu' dist/elementor/**` → **0 hits.**
- The one `main-menu` string in the rendered HTML is `<ul id="menu-main-menu">` — WordPress's own
  menu id, emitted by **ElementsKit's** nav widget. Not the theme's `<nav id="main-menu">`, which
  lives in the dead `templates/header/top/main-menu.php`.

The live navigation is 11 Elementor Pro `nav-menu` widget instances in the header document (id 171)
plus one ElementsKit `ekit-nav-menu`. The theme's contribution to nav styling is **twelve rules**
in the served CSS, and of those, nine can never match because they key off classes
(`vamtam-hr-underline`, `vamtam-menu-icon`, `vamtam-menu-indicator`) that no page emits, and a
tenth is a `:not(.vamtam-has-submenu-icon)` negation on a class that every nav-menu widget carries.

**The entire theme navigation CSS reduces to two live rules**, both in
`responsive/elementor-below-max.css`:

```css
.elementor-widget-nav-menu.vamtam-has-theme-widget-styles.vamtam-has-mobile-disable-scroll nav.elementor-nav-menu--dropdown { overflow-y: auto }
.elementor-widget-nav-menu.vamtam-has-theme-widget-styles.vamtam-has-mobile-menu-max-height nav.elementor-nav-menu--dropdown > ul { max-height: calc(var(--vamtam-mobile-menu-max-height,80vh) - var(--wp-admin--admin-bar--height,0px)) }
```

### 4.5 Live vs dead inside the served bundle

The four served files decompose into 23 LESS partials (from the `.css.map` `sources` arrays).
Cross-referencing each partial's root selector against widget presence in the 39 captured pages:

| Partial | Widget instances on site | Verdict |
|---|---|---|
| `elementor-base`, `-mixins`, `-general`, `-general-typography`, `-main`, `-animations` | n/a | **live** — the typography/colour core |
| `widgets/posts-base`, `widgets/posts`, `widgets/archive-posts` | posts on 18 pages, archive-posts on 1 | **live** |
| `widgets/button` | 39 pages | live |
| `widgets/form` | 39 | live |
| `widgets/icon-box` | 39 | live |
| `widgets/search-form` | 39 | live |
| `widgets/social-icons` | 39 | live |
| `widgets/section` | 39 | live |
| `widgets/nav-menu` | 39 | live, but 2 of 12 rules (§4.4) |
| `widgets/post-comments` (**123 rules — the largest single block**) | 15 | live |
| `widgets/toggle` | 15 | live |
| `widgets/image-box` | 10 | live |
| `widgets/gallery` | 2 | live |
| `widgets/testimonial-carousel` (30 rules) | 2 | live |
| `widgets/tabs` (31 rules) | **0** | **dead** |
| `widgets/login` (13 rules) | **0** | **dead** — the plugin's login widget never even registers (§6.1) |
| `widgets/post-navigation` (12 rules) | **0** | **dead** |
| `widgets/hr-scrolling-common` (4,220 B src, the second-largest widget partial) | **0** — `vamtam-has-hr-layout` appears on 0/39 pages | **dead** (but its JS still loads on 19 pages, §6.4) |

A crude rule-level pass over the four files — counting any rule whose selector mentions a dead
widget, a WooCommerce class, or a never-emitted `vamtam-*` class — puts **192 of 563 rules
(34%) and 48,429 of 106,994 bytes (45%) in the dead column.**

Treat that as an upper bound on what can be deleted, not a target. Deleting CSS is the one part
of this project where a mistake is invisible until someone edits a page.

---

<a id="5"></a>
## 5. JavaScript

### 5.1 What loads

| File | Bytes | How |
|---|---|---|
| `vamtam/assets/js/all.min.js` | 22,659 | `wp_enqueue_script('vamtam-all', …, ['jquery'], …, true)` — `enqueues.php:118` |
| `vamtam/assets/js/low-priority.js` | 1,263 | injected imperatively by `general.js` on `DOMContentLoaded` via `VAMTAM.load_script()` |

`all.min.js` is the Grunt concatenation of `lib.js + menu.js + general.js + woocommerce.js +
custom-animations.js` (`utils/grunt/init.js:209-220`). **`woocommerce.js` is 23,242 B of source —
roughly 45% of the bundle — and every one of its handlers binds to WooCommerce selectors or
`window.wc_add_to_cart_params`. It is parsed and executed on every page load and can never do
anything.**

`fallback.js` is registered as `vamtam-fallback` (`enqueues.php:135`) but its only enqueue call is
in the dead `templates/header/top/main-menu.php:5` — never loaded.

### 5.2 The API surface that must survive

The companion plugin's JS and the theme's own `low-priority.js` consume a very small slice of
`window.VAMTAM`:

| Consumer | Uses |
|---|---|
| plugin JS | `VAMTAM.isBelowMaxDeviceWidth` (4×), `VAMTAM.debounce` (3×), `VAMTAM.isMaxDeviceWidth` (1×), `VAMTAM.adminBarHeight` (1×) |
| `low-priority.js` | `VAMTAM.addScrollHandler` |

`all.js` exports 17 members; **five are consumed.** The rest (`getScrollbarWidth`, `offset`,
`isMobileBrowser`, `isMediumDeviceOrWider`, `load_script`, `load_style`, `waitForLoad`,
`blockStickyHeaderAnimation`, `latestKnownScrollY`, `scroll_handlers`, `onScroll`,
`resizeElements`, `CUSTOM_ANIMATIONS`) are internal or unused.

Two localised objects also form part of the contract, both verified in the rendered HTML:

- **`vamtam-all-js-extra`** (834 B) — `VAMTAM_FRONT` = `{ajaxurl, jspath, max_breakpoint:"1025",
  medium_breakpoint:"768", content_width:"1280", enable_ajax_add_to_cart:"", widget_mods_list:{15 entries}}`
  (`enqueues.php:122-133`)
- **`vamtam-all-js-after`** (728 B) — `VAMTAM_FRONT.theme_supports = (feature) => [18 flags].includes(feature)`
  (`framework.php:329-331`). The 18 flags are listed at `framework.php:267-294`; the plugin gates
  almost every one of its behaviours on them (§6)

`VAMTAM_FRONT.widgets_assets_data` (`elementor-bridge.php:1708`) is **not present** in any captured
page — it is conditional on the `e_optimized_css_loading` path, which is at `default`.

### 5.3 Handlers actually needed — and the option nobody has costed

Of the theme's own JS, three things are live:

| Handler | Where | Live? |
|---|---|---|
| Scroll-to-top: fade `#scroll-to-top` past mid-page, capture-phase click → smooth scroll to 0 | `low-priority.js` | **yes** — `.vamtam-scroll-to-top` on 39/39 pages |
| Mobile-nav overlay: `.elementor-menu-toggle` click → `.vamtam-overlay-trigger--overlay`, injected `.vamtam-overlay-element`, `html,body.vamtam-disable-scroll`, hides `#scroll-to-top`, closes on outside click and breakpoint change | `general.js:102-273` | **yes** — this is the mobile menu |
| Sticky header: `--fixed-shown`/`--fixed-hidden` toggling on `.vamtam-sticky-header`, `--vamtam-sticky-mleft/-mright` | `custom-animations.js:34-203` | uncertain — `vamtam-sticky-header` is emitted by the plugin's section controls, and `vamtam-sticky-header--offset-on-sticky` appears on **0/39** pages despite being saved on one section. **Verify before dropping** |
| Smooth in-page scrolling on `.vamtam-animated-page-scroll` | `menu.js` | no such class on any page — **dead**, but the module also closes `#main-menu > .mega-menu-wrap`, also dead |
| iframe 16:9 resizing in `#page .media-inner`, `.vamtam-video-frame` | `general.js:49-82` | no matching markup — dead |
| `.vamtam-trigger-print` → `window.print()` | `general.js:40-47` | dead |
| everything in `woocommerce.js` | | dead |

**Now the part that is much bigger than it looks.**

`STATUS.md` describes the theme's "Additional JS" as *"a `.select-caret-down-wrapper` handler"*.
That is one of three slots. The option `wp_options.vamtam_additional_js` holds **6,164 bytes across
three slots**, and it is echoed raw by the **companion plugin** — not the theme —
(`vamtam-elementor-integration.php:222-244`), so **removing the plugin silently deletes all of it**:

| Slot | Bytes | Contents |
|---|---|---|
| `head` | 806 | the `.select-caret-down-wrapper` SVG replacement (already patched to DOM-ready) |
| `body` | 4,885 | six independent blocks: `.post-btn` click/hover colour cycling; `.bookSlot` click colour cycling; WhatsApp chat-icon restyling on `window.onload`; **search `minlength`/`maxlength` + empty-submit prevention**; menu-heading `pointer-events:none` on 5 specific menu item ids after a 2 s timeout; mobile search padding; **form validation — `pattern`/`title`/`maxlength` on 8 named Elementor form fields plus `input[type=tel]`, and a `checkValidity()` submit guard on `#pie-consult-form`**; **comment-author name validation** |
| `footer` | 473 | **rewrites every `.elementor-widget-button`**: strips `elementor-button-link elementor-size-sm`, adds `elementor-button-minimal`, and replaces the `<a>`'s innerHTML with the plain text of `.elementor-button-text` — destroying the `elementor-button-content-wrapper` / `-text` / `-icon` spans |

That last one is the real explanation for §3.3: **the button markup simplification is done in
JavaScript, in the database, not by the dead PHP filter in `functions.php`.** It is why every
captured page shows `<a class="elementor-button elementor-button-minimal">Request a Meeting</a>`
with no inner spans, and why `elementor-button-minimal` appears nowhere in any file under
`wp-content`.

Two consequences:

1. **This is site-critical behaviour living in an unversioned, unescaped option, printed by a
   plugin we are deleting.** It must be lifted into `piecyfer-theme` as a real, reviewed JS file
   before the plugin is removed, or 39 pages change at once.
2. **Parts of it are already broken.** `elementor_experiment-e_optimized_markup` is `active`
   (verified in `wp_options`), which removes the `<div class="elementor-button-wrapper">` from
   Elementor's output. The `body` slot's two largest handlers select
   `.post-btn > div > div.elementor-button-wrapper > a` — **that element no longer exists**, so the
   `.post-btn` click and hover handlers are dead. `.post-btn-hover` is still styled in Additional
   CSS. Port the behaviour, but fix the selector rather than transcribing it.

---

<a id="6"></a>
## 6. The companion plugin `vamtam-elementor-integration-tecnologia`

24 PHP files, 15 JS files, **zero CSS files** — all of its widget styling lives in the theme's
`elementor-all.css`. v1.0.10.

### 6.0 The two gates that decide what runs

Nothing in the plugin runs unconditionally. Two layers apply throughout:

- **`vamtam_theme_supports($flag)`** — the 18-flag list at `framework.php:267-294`, exported to JS
  as `VAMTAM_FRONT.theme_supports` (§5.2).
- **`Vamtam_Elementor_Utils::is_widget_mod_active($widget)`**
  (`includes/helpers/vamtam-elementor-utils.php:216-234`) — driven by kit controls
  `vamtam_theme_*`, and **returns `false` for any widget absent from
  `VamtamElementorBridge::get_widget_mods_list()`** (`elementor-bridge.php:1364-1381`):
  `button, form, tabs, icon-box, image-box, nav-menu, section, toggle, testimonial-carousel,
  search-form, archive-posts, posts, post-navigation, post-comments, popup`.

Loading order: `plugins_loaded` → helpers, site-settings, hooks, kit → `after_setup_theme` @100 →
theme-overrides → **`elementor/init` @ priority 200** → dynamic tags, then widgets. A kill switch at
`vamtam-elementor-integration.php:192-207` skips the entire widget layer if the kit setting
`vamtam_theme_disable_all_widget_mods` is truthy.

### 6.1 Widget unregister / re-register

**Hook: `elementor/widgets/register`, priority `100`, in every case.** The lead's list is correct
as *the set of files containing re-registration code*, but one of the six never executes:

| Widget | Replacement class | Extends | Gate | Live? |
|---|---|---|---|---|
| `nav-menu` | `Vamtam_Widget_Nav_Menu` (`nav-menu.php:120-185`) | Pro `Nav_Menu` | Pro + mod + `nav-menu--disable-scroll-on-mobile` | **yes** |
| `posts` | `Vamtam_Widget_Posts` (`posts.php:30-114`) | Pro `Posts` | Pro + mod + masonry/404/categories flags | **yes** |
| `archive-posts` | `Vamtam_Widget_Archive_Posts` (`archive-posts.php:227-306`) | Pro `Archive_Posts` | Pro + mod + `posts-base--display-categories` | **yes** |
| `button` | `Vamtam_Widget_Button` (`button.php:93-136`) | core `Widget_Button` | mod + `button--underline-anim` | **yes** |
| `tabs` | `Vamtam_Widget_Tabs` (`tabs.php:34-83`) | core `Widget_Tabs` | mod only | registers, but **0 tabs widgets on the site** |
| `login` | `Vamtam_Widget_Login` (`login.php:20-76`) | Pro `Login` | mod `login` | **NO — `login` is not in the widget-mods list; the file returns at `login.php:15`** |

Every subclass does the same three things and nothing else: `register_assets()`,
`add_extra_script_depends()`, and an overridden `get_script_depends()` that **hardcodes a copy of
the parent's dependency array** rather than merging (`nav-menu.php:168-177`, `posts.php:46-60`,
`archive-posts.php:243-254`, `tabs.php:39-43`). `posts` and `archive-posts` also override
`register_skins()` (`posts.php:77-81`, `archive-posts.php:271-275`) to register **only** the VamTam
skin, deliberately not calling the parent.

The other nine files in `includes/widgets/` register no widgets:

| File | Real role |
|---|---|
| `widget-base.php` (18 L) | `elementor/frontend/widget/before_render` @10 — adds `vamtam-has-theme-widget-styles` to the wrapper of every mod-active widget. **This class is on 39/39 pages and gates most of the theme CSS** |
| `posts-base.php` (**769 L — the largest file**) | the `vamtam_classic` skin + shared control injections + query/404 fixes |
| `section.php` (139 L) | sticky-header controls, plus `elementor/frontend/after_render` @10 that **re-renders the entire section a second time** as a spacer clone |
| `column.php`, `image-box.php`, `page.php`, `popup.php`, `testimonial-carousel.php`, `text-editor.php` | control injections only |

`text-editor` is dead for the same reason as `login` (not in the mods list, returns at
`text-editor.php:9`). `column.php:7-35` is dead (`column--layout-overflow` is not a supported flag).

### 6.2 Control injections — the complete list

**35 `elementor/element/**` actions: 32 `before_section_end`, 3 `after_section_end`, zero
`*_section_start`.**

#### Kit / Site Settings — `includes/kits/documents/kit.php`, ungated

| Section | Position | Line | What |
|---|---|---|---|
| `section_form_fields` | before_end | `:187-190` → `:5-184` | widens the **selectors** of 15 stock form controls onto native `select`, `.select2`, `input[type=checkbox]+label::before`, `textarea`, placeholders. Uses the `{{_RESET_}}` sentinel (`vamtam-elementor-utils.php:304-317`) |
| `section_typography` | before_end | `:213-216` | adds `{{WRAPPER}} .font-h{n}` to `h{n}_color` and `h{n}_typography`, h1–h6 |
| `section_buttons` | before_end | `:345-348` → `:219-342` | widens 15 button controls onto a hardcoded 29-selector list + `:hover` variants |
| `section_images` | **after**_end | `:362-365` | `image_hover_transition` → `transition-duration … !important` on WC images and post thumbnails |
| `woocommerce_info_notices` | before_end | `:378-381` | `--vamtam-info-buttons-hover-bg-color` — **WooCommerce, dead here** |

#### Widgets and elements

| Element | Section | Prio | Line | What |
|---|---|---|---|---|
| `nav-menu` | `section_style_main-menu` | 10 | `nav-menu.php:47` | update ×3 → `--vamtam-menu-color`, `-hover`, `-active` |
| `nav-menu` | `section_layout` | 10 | `nav-menu.php:116` | add ×3 → `vamtam_disable_scroll_on_mobile` (prefix class, default on), `vamtam_mobile_menu_use_max_height`, responsive `vamtam_mobile_menu_max_height` → `--vamtam-mobile-menu-max-height` |
| `nav-menu` | `section_style_dropdown` | 10 | `nav-menu.php:207` | replace `color_dropdown_item_hover` selectors with touch-device-aware variants |
| `nav-menu` | `style_toggle` | 10 | `nav-menu.php:233` | replace ×2, same idea |
| `button` | `section_style` | 10 | `button.php:89` | add ×4 → `vamtam_underline_anim`, `--vamtam-underline-width`, `-spacing`, `-bg-color` |
| `button` | `section_button` | 10 | `button.php:170` | inject `vamtam_icon_size` after `selected_icon` |
| `tabs` | `section_tabs` | 10 | `tabs.php:27` | add `disable_def_anim` — **dead, 0 tabs widgets** |
| `tabs` | `section_tabs_style` | 10 | `tabs.php:98` | update `border_color` → `--vamtam-tabs-border-color` — dead |
| `image-box` | `section_style_image` | 10 | `image-box.php:24` | update `image_space` → `--vamtam-img-spacing` |
| `image-box` | `section_style_content` | 10 | `image-box.php:37` | update `text_align` → `prefix_class: vamtam-text-align%s-` |
| `text-editor` | `section_style` | 10 | `text-editor.php:31` | **dead** |
| `column` | `layout` | 10 | `column.php:34` | **dead** (`column--layout-overflow` unsupported) |
| `column` | `section_style` | 10 | `column.php:51` | **ungated** — `background_hover_image` preload via `::after{content:url(...)}` |
| `column` | `section_advanced` | 10 | `column.php:84` | replace `margin`/`padding` selectors with logical properties |
| `section` | `section_effects` | 10 | `section.php:138` | inject 4 sticky-header controls after `sticky_effects_offset`; `vamtam_sticky_offset` → `--vamtam-sticky-offset` |
| `popup` | `section_advanced` | 10 | `popup.php:89` | `vamtam_abs_pos`, `vamtam_open_on_selector_hover`, `vamtam_close_on_hover_lost`, `vamtam_align_with_selector` |
| `wp-page` | `section_scroll_snap` | 10 | `page.php:25` | **ungated** — re-points `scroll_snap` selectors at `html` |
| `testimonial-carousel` | `section_navigation` | 10 | `testimonial-carousel.php:248` → `:18-241` | remove `arrows_color`, rebuild in Normal/Hover tabs; update `heading_arrows`, `arrows_size` → `--vamtam-arrows-size`; add `vamtam_nav_pos` (8 options, `prefix_class vamtam-nav-pos%s-`) + responsive `vamtam_nav_prev_x/_y`, `vamtam_nav_next_x/_y`, `vamtam_nav_btns_gap`, `vamtam_nav_btns_spacing` |
| `posts`, `archive-posts` | `section_pagination_style` | 10 | `posts-base.php:216,220` | ~11 controls: adds border/padding, removes the `pagination_colors` tab group and rebuilds it as 3 tabs × 2 colours, plus RTL-aware responsive spacing |
| `posts`, `archive-posts` | `section_layout` | **11** | `posts-base.php:361,366` | update `vamtam_classic_meta_data` → adds options `vamtam-categories`, `vamtam-tags`. **Priority 11 because the skin registers its controls at 10** |
| `posts`, `archive-posts` | `vamtam_classic_section_design_box` | **11** | `posts-base.php:362,367` | update content padding → `--vamtam-content-padding` |
| `posts`, `archive-posts` | `classic_section_design_image`, `vamtam_classic_section_design_image` | 10 | `posts-base.php:392,393,397,398` | image radius → `--vamtam-img-border-radius` |
| `posts`, `archive-posts` | `*_section_design_layout` (4 variants) | 10 | `posts-base.php:727,728,732,733` | the 11-control horizontal-layout set + `columns` → `--vamtam-cols` + forced `prefix_class elementor-grid%s-` |
| `archive-posts` | `archive_classic_section_design_layout`, `vamtam_classic_section_design_layout` | 10 | `archive-posts.php:206,212` → `:18-200` | **after**_section_end — builds an entire new **Box style section** (11 controls) that Pro's archive skin does not have |

Every one of these injections is a hard coupling to an Elementor Pro section id. `STATUS.md` already
records what happens when a section id does not match: the injection silently vanishes.

### 6.3 The `vamtam_classic` skin

`includes/widgets/posts-base.php`, trait at `:226-308`, applied to two classes at `:311-317`:

```php
public function get_id()    { return 'vamtam_classic'; }
public function get_title() { return esc_html__( 'Classic (Vamtam)', … ); }
```

- `Skin_Vamtam_Posts_Classic extends ElementorPro\Modules\Posts\Skins\Skin_Classic`
- `Skin_Vamtam_Archive_Posts_Classic extends ElementorPro\…\Posts_Archive_Skin_Classic`

Confirmed live: `class="elementor-posts-container elementor-posts elementor-posts--skin-vamtam_classic elementor-grid elementor-has-item-ratio"` on the blog pages.

**The skin defines no controls of its own.** It inherits Pro's entire Classic set, which Elementor
auto-re-prefixes to `vamtam_classic_*`. Everything extra arrives through the hooks in §6.2.

**What it changes in the markup** (`render_post()`, `:297-307`):

| | Order |
|---|---|
| Pro `Skin_Base::render_post()` | header → thumbnail → **text_header → title → meta_data** → excerpt → read_more → text_footer → footer |
| `vamtam_classic` | header → thumbnail → **meta_data → text_header → title** → excerpt → read_more → text_footer → footer |

The meta block moves **above the title and outside the text wrapper**. `render_meta_data()`
(`:236-279`) reimplements Pro's version and appends two branches emitting
`<div class="vamtam-post__categories">` (`:281-287`) and `<div class="vamtam-post__tags">`
(`:289-295`). `vamtam-post__categories` is on 19/39 pages; `vamtam-post__tags` on **0**.

**Caveat for the rewrite:** Pro's `Skin_Classic` hardcodes
`elementor/element/posts/classic_section_design_layout/after_section_end` for its Box section, so
the Box section for `vamtam_classic` is registered during the *classic* skin's hook but receives the
`vamtam_classic_` prefix — which is why the plugin listens on `vamtam_classic_section_design_box`
and why the archive variant needs a hand-rolled Box section. Reproduce this or the box styling
silently disappears.

### 6.4 Assets

**No CSS.** `VAMTAM_ELEMENTOR_STYLES_URI`/`_DIR` are defined (`vamtam-elementor-integration.php:185-190`)
and referenced nowhere.

Verified against all 39 captured pages:

| Handle | File | Pages | Condition |
|---|---|---|---|
| `vamtam-elementor-frontend` | `assets/js/vamtam-elementor-frontend.min.js` | **39/39** | unconditional, `elementor/frontend/before_enqueue_scripts` |
| `vamtam-nav-menu` | `assets/js/widgets/nav-menu/vamtam-nav-menu.min.js` | **39/39** | widget-conditional |
| `vamtam-button` | `assets/js/widgets/button/vamtam-button.min.js` | **39/39** | widget-conditional |
| `vamtam-posts-base` | `assets/js/widgets/posts-base/vamtam-posts-base.min.js` | **19/39** | widget-conditional (posts/archive-posts) |
| `vamtam-hr-scrolling` | `assets/js/widgets/vamtam-hr-scrolling/vamtam-hr-scrolling.min.js` | **19/39** | loads with `posts`, but its markup class `vamtam-has-hr-layout` is on **0/39** — **dead weight on 19 pages** |
| `vamtam-tabs`, `vamtam-login` | | 0/39 | never |
| `vamtam-elementor` | `assets/js/vamtam-elementor.js` | — | editor only |

Note the URLs contain a double slash (`…-tecnologia//assets/js/…`) because
`VAMTAM_ELEMENTOR_INT_URL` is already trailing-slashed. Harmless, visible in the captures.

`vamtam-elementor-frontend.js` is the substantial one: popup positioning (`vamtam_abs_pos`,
`vamtam_align_with_selector`, hover-open/close), search-form autofocus, mobile-menu max-height,
action-link alignment (decodes the base64 `settings=` payload of `#elementor-action` hrefs, `:553`),
a JS mirror of `is_widget_mod_active` (`:579-603`), and an optimized-CSS-loading fallback (`:605-637`).

### 6.5 Beyond Elementor widgets

**Kit tab.** `includes/site-settings/theme-site-settings.php:299` registers a
`settings-theme-settings` ("Theme Settings") kit tab with two sections: 15 per-widget mod switchers
plus `vamtam_theme_enable_all_widget_mods` / `_disable_all_widget_mods` / `_font_smoothing` /
`_search_form_popups_get_focus`, and a Global Widget Options section holding the underline-animation
defaults. Verified in the kit: **28 `vamtam_theme_*` keys are saved on post 5, and 26 of them are
WooCommerce widget switchers with empty values.** The only two that matter are
`vamtam_theme_enable_all_widget_mods` and `vamtam_theme_underline_anim_default`.

The only CSS the kit tab emits is three properties on `body`
(`theme-site-settings.php:135,153,166`): `--vamtam-global-underline-width`, `-spacing`, `-bg-color`
— **none of which is present in any captured page**, because the controls are unset.

**Dynamic tags** (`elementor/dynamic_tags/register` @100):

| Class | Tag name | Note |
|---|---|---|
| `Vamtam_Popup` (`vamtam-popup.php:24-147`) | **`popup`** | **Overrides Elementor Pro's own `popup` tag** by registering at priority 100. Adds an `align_with_parent` switcher folded into the action hash (`:133-137`). Confirmed live: every button's `#elementor-action…` href decodes to `{"id":"7718","toggle":false,"align_with_parent":""}` — the third key is VamTam's |
| `Author_Profile_Picture` | `vamtam-author-profile-picture` | requests a 1000px avatar |

**Other plugin behaviour:** a Customizer "Additional JS" panel writing `vamtam_additional_js`
(§5.3); removal of WPClever admin submenus; `rocket_cache_wc_empty_cart` → false; a one-shot
overwrite of Elementor experiment states (`vamtam-elementor-hooks.php:22-61`) keyed on option
`vamtam-set-experiments-default-state`; 4 extra scroll animations
(`elementor/controls/animations/additional_animations`); a `no-lazy` class copier
(`elementor/image_size/get_attachment_image_html` @11); `pre_handle_404` bypass for
`?vamtam_posts_fetch=1`; and an `ignore_sticky_posts` forcing filter on
`elementor/query/query_args` @11.

**No custom post types, taxonomies, REST routes, admin pages, cron jobs, or database tables.**

### 6.6 The complete `vamtam-*` markup contract

Across all 39 pages, the entire VamTam layer emits **18 distinct CSS classes**. This is the whole
markup contract:

| Class | Pages | Source |
|---|---|---|
| `vamtam-has-theme-widget-styles` | 39 | `widget-base.php:19` |
| `vamtam-main`, `vamtam-scroll-to-top`, `vamtam-font-smoothing`, `vamtam-wc-cart-empty` | 39 | theme |
| `vamtam-is-elementor` | 38 | theme |
| `vamtam-has-submenu-icon`, `vamtam-has-mobile-disable-scroll`, `vamtam-has-mobile-menu-max-height` | 39 | `nav-menu.php` |
| `vamtam-post__categories` | 19 | `posts-base.php:281-287` |
| `vamtam-center-align-toggle` | 15 | toggle |
| `vamtam-text-align-justify` | 10 | `image-box.php:37` |
| `vamtam-testimonial-carousel-navigation`, `vamtam-nav-pos-mobile-top-right`, `vamtam-nav-pos-bottom-left` | 2 | `testimonial-carousel.php` |
| `vamtam-text-align-tablet-left`, `vamtam-text-align-mobile-justify` | 1 | image-box |
| `vamtam-box-outer-padding` | 1 | theme |

### 6.7 Licence / update code

`vamtam-updates/class-vamtam-updates.php`, instantiated at
`vamtam-elementor-integration.php:107-111`. POSTs `slug`, `main_file`, `version`, `purchase_key`
and a `home_url`-bearing UA to `https://updates.vamtam.com/0/envato/check` on
`pre_set_site_transient_update_plugins` @999. No caching — `$update_cache` is fetched at `:64` and
never used. Response is `array_merge`d into `$updates->response` unvalidated. `plugins_api` is
overridden to return an empty object, blanking the "View details" modal.

---

<a id="7"></a>
## 7. The `vamtam_*` settings census

**This is the section that changes the scope estimate.**

Method: `_project/scripts/dump-eldata.php` → 76 live documents (revisions and auto-drafts
excluded), 2,343 elements walked recursively, every settings key beginning `vamtam_` counted with
its value distribution.

### 7.1 Headline counts

| Measure | Value |
|---|---|
| Documents with Elementor data | 76 |
| Elements walked | 2,343 |
| **Elements carrying any `vamtam_*` setting** | **21** |
| Documents containing those elements | **11** (all `publish`) |
| Distinct `vamtam_*` keys in use | 139 |
| Total populated occurrences | 989 |
| — of which **empty / no-op** (`""`, `[]`, `{size:""}`, all-blank dimensions) | **79** |
| — of which carry a value | **910** |

`02-WIDGET-REBUILD-SPEC.md` reports 928; the 61-value difference is counting method (this pass
includes empty-valued keys in the raw total and walks nested `elements` arrays). The two figures
agree to within 7% and neither changes the conclusion.

### 7.2 Where the settings actually are

| Group | Settings | Share |
|---|---|---|
| `vamtam_classic_*` — the posts / archive-posts skin | **930** | **94.0%** |
| `vamtam_nav_*` — testimonial-carousel arrow positioning | 30 | 3.0% |
| `vamtam_fg_layer_*` — image mask | 22 | 2.2% |
| `vamtam_underline_*` — button | 4 | 0.4% |
| everything else | 3 | 0.3% |

| Widget | Settings | Elements |
|---|---|---|
| `posts` | 817 | 12 |
| `archive-posts` | 113 | 3 |
| `testimonial-carousel` | 30 | 2 |
| `image` | 23 | 1 |
| `button` | 4 | 1 |
| `nav-menu` | 1 | 1 |
| `section` | 1 | 1 |

| Document | Elements |
|---|---|
| page 146 "Home" | 4 |
| library 996219 "blog page", 996222 "blogs full page", page 93 "Blogs" | 3 each |
| library 8502 "Blog Post Template" | 2 |
| libraries 171, 607, 6126, 8559, 8711, 996210 | 1 each |

### 7.3 Which are defaults, and which are inert

Rather than guess at control defaults, three independent checks were run against the rendered
output:

**a) 79 occurrences are literally empty** and emit nothing.

**b) Four whole feature blocks are inert despite being populated:**

| Block | Settings | Evidence it does nothing |
|---|---|---|
| `vamtam_fg_layer_*` on the one `image` widget in doc 8502 | 22 | The master switch `vamtam_use_fg_layer` is `""`. `grep mask uploads/elementor/css/post-8502.css` → **0 hits**. The saved mask URL still points at `https://coiffure.demo.vamtam.com/...` — a leftover from the VamTam demo import |
| `vamtam_offset_on_sticky` on a section in doc 146 | 1 | `vamtam-sticky-header--offset-on-sticky` appears on **0/39** pages |
| `vamtam_classic_use_read_more_theme_style` = `"read-more-theme-style"` × 9 | 9 | that class appears on **0/39** pages |
| `vamtam_classic_vamtam_has_nav` = `""` × 12, `vamtam_use_hr_layout` never set | 12+ | `vamtam-has-hr-layout` and `vamtam-nav` on **0/39** pages — the entire horizontal-scrolling subsystem (11 controls, 4,220 B of LESS, a JS file loaded on 19 pages) is **unused** |

**c) Of the 910 valued occurrences, many are Elementor typography-group artefacts.** Setting
`typography_typography: "custom"` makes the editor write every subkey, including
`text_transform: "none"`, `font_style: "normal"`, `letter_spacing: 0`, `word_spacing: 0`. These are
visually inert but **not** free: Elementor emits `text-transform:none` into the compiled CSS
because the value is non-empty. Reproduce them if you want byte-identical generated CSS; skip them
if you only need visual identity. There are roughly 120 such occurrences.

### 7.4 What this means for scope

**The `vamtam_*` surface is not 928 settings spread across the site. It is 21 elements in 11
documents, and 94% of the work is one skin on two widgets.**

Concretely, the work splits:

| Item | Elements | Real settings | Effort |
|---|---|---|---|
| `vamtam_classic` skin on `posts` + `archive-posts` | 15 | ~870 valued, ~150 distinct controls | **Hard — this is the whole job** |
| `testimonial-carousel` arrow controls | 2 | 30 | Medium — 12 responsive controls → 6 CSS vars |
| `button` underline animation | 1 | 4 | Easy |
| `nav-menu` mobile max-height | 1 | 1 | Easy |
| `image` foreground mask | 1 | 22 | **Skip — proven inert** |
| `section` sticky offset | 1 | 1 | **Skip — proven inert** |

Everything the `posts` skin actually needs is visible in the value distributions: 3 thumbnail sizes
(`large`/`full`/`1536x1536`), 2 item ratios (0.55/0.56), 5 read-more strings, `meta_data` =
`["vamtam-categories"]` (12×) or `[]` (3×), 2 typography sets (Inter Tight titles at 20/30px,
Helvetica excerpts at 16px), and a handful of colours (`#242627`, `#00000099`, `#00000026`,
`#DEE0FF`, `#FFFFFF`, `#4EAEB7`, `#3469B300`).

---

<a id="8"></a>
## 8. The proposed `piecyfer-theme`

### 8.1 Division of labour

The theme takes **only what is theme-shaped**: document wrappers, `wp_head`/`wp_footer`, theme
supports, design tokens, global stylesheets, global JS. Everything Elementor-shaped — widgets,
skins, control injections, dynamic tags, kit controls — stays in `piecyfer-core`, which already
exists and already carries 8 dynamic tags and 13 widget classes.

This matters because a theme that registers Elementor widgets cannot be swapped without swapping
the widgets, and the whole safety property of the current approach (§ *STATUS.md*, "the last
registration wins") depends on being able to move one piece at a time.

### 8.2 File list

```
wp-content/themes/piecyfer-theme/
├── style.css                     Theme header only. Name, Version, Text Domain: piecyfer.
│                                 NOT enqueued (the current theme does not enqueue its style.css either).
├── functions.php                 ~60 lines. Requires inc/*.php. Nothing else.
├── screenshot.png
│
├── inc/
│   ├── setup.php                 after_setup_theme: the 11 add_theme_support calls that matter
│   │                             (post-thumbnails, automatic-feed-links, html5[5], title-tag,
│   │                             custom-logo, align-wide, editor-styles, responsive-embeds,
│   │                             customize-selective-refresh-widgets, post_type_support page/excerpt),
│   │                             $content_width from the Elementor kit container_width,
│   │                             and add_theme_support('piecyfer-elementor-widgets') carrying the
│   │                             feature-flag list that replaces vamtam_theme_supports().
│   │                             NO register_sidebar (§1.6). NO register_nav_menus.
│   ├── locations.php             elementor/theme/register_locations →
│   │                             register_all_core_location() + the extra 'page-title-location'
│   │                             (mirrors elementor-bridge.php:715-726). Without this the TB
│   │                             header/footer stop rendering.
│   ├── tokens.php                THE IMPORTANT FILE. Reproduces get_translated_kit():
│   │                             reads the active kit's system_colors / system_typography /
│   │                             link_* / button_* / form_field_* / container_width, plus the
│   │                             Elementor custom icon set, and prints
│   │                             <style id="piecyfer-tokens"> :root { --vamtam-*: … } </style>
│   │                             on wp_print_styles priority 1.
│   │                             Emits the SAME --vamtam-* names (see §8.4).
│   ├── enqueue.php               4 stylesheets + 2 JS + the two @font-face rules + the
│   │                             VAMTAM_FRONT localisation and theme_supports inline script.
│   ├── head.php                  GTM container GTM-WDKS9B2G (head + noscript, charset first),
│   │                             google-site-verification meta.
│   └── compat.php                The one-page menu href filter (§3.2), if kept.
│
├── assets/
│   ├── css/
│   │   ├── theme.css             ← elementor-all.css, dead rules removed (§4.5)
│   │   ├── theme-max.css         ← elementor-max.css            media (min-width: 1025px)
│   │   ├── theme-below-max.css   ← elementor-below-max.css      media (max-width: 1024px)
│   │   └── theme-small.css       ← elementor-small.css          media (max-width: 767px)
│   ├── js/
│   │   ├── theme.js              ← lib.js + general.js + custom-animations.js.
│   │   │                           DROP woocommerce.js (45% of the bundle) and menu.js.
│   │   ├── scroll-top.js         ← low-priority.js
│   │   └── site.js               ← the three vamtam_additional_js slots, as reviewed code (§5.3)
│   └── fonts/theme-icons/        theme-icons.woff2 + .woff only (drop .ttf, drop icomoon
│                                 unless §4 proves it is used)
└── templates/
    ├── header.php  footer.php    the wrappers in §1.4, verbatim
    ├── index.php   page.php      single.php  archive.php  search.php  404.php
    │                             all four lines long: get_header(); elementor_theme_do_location(…)
    │                             with a minimal the_content() fallback; get_footer();
    ├── author.php  attachment.php  see §9 — these have no TB fallback today
    ├── comments.php              only reached when a page has no post-comments widget
    └── parts/sub-header.php      the #sub-header shell in §1.4
    └── parts/scroll-top.php      the #scroll-to-top button
```

Deliberately **absent**: no options framework, no Customizer library, no TGMPA, no demo importer,
no fonts metadata table, no LESS/Grunt pipeline, no `vendor/`, no WooCommerce or Events Calendar
templates, no `templates/post/**` blog partials, no `dist/fallback/**`, no `samples/`. That removes
roughly 10.8 MB and 460 files.

### 8.3 Theme Builder handoff

Elementor Pro's locations only work if the theme registers them. `inc/locations.php` must call
`$manager->register_all_core_location()` and then re-register `page-title-location` exactly as
`elementor-bridge.php:715-726` does, because `templates/header/page-title.php` and
`header/sub-header.php` both reference it.

Every rendering template then reduces to:

```php
get_header();
if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'single' ) ) {
    /* minimal fallback */
}
get_footer();
```

The conditions in `wp_options.elementor_pro_theme_builder_conditions` are theme-agnostic and
survive the swap untouched:

| Location | Doc | Conditions |
|---|---|---|
| header | 171 Header - H. IT Services | `include/general`, `exclude/singular/page/2055` |
| footer | 1273 PieCyfer Footer | `include/general`, `exclude/singular/page/1298` |
| footer | 991509 Carees Footer | 4 page includes |
| single | 8502 Blog Post Template | post, in_category, in_category_children |
| single | 8716 Elementor Error 404 | `include/singular/not_found404` |
| archive | 8559 Blog Posts Archives | archive minus search/cat-22/tags-46,47 |
| archive | 6126 Case Studies Archives | cat-22 children, tags 46/47 |
| archive | 8711 Search Results | `include/archive/search` |

### 8.4 CSS organisation, and why the variable names do not change

**Keep the `--vamtam-*` prefix.** Renaming to `--piecyfer-*` means editing 159 references across
four stylesheets and getting every one right, for zero functional gain, in the one area where
mistakes are invisible. The prefix is a private implementation detail of files we now own. Rename
it later, mechanically, once the pixel harness is green — or never.

Three CSS layers, in cascade order:

1. **`<style id="piecyfer-tokens">`** — the `:root{}` block, emitted at `wp_print_styles` priority 1
   so it precedes everything. Reproduce all 171 properties initially; prune the 40 dead ones
   (§4.3) as a separate, separately-verified commit.
2. **`theme.css` + the three responsive files** — lifted verbatim from `elementor-all.css` and its
   companions. Do **not** hand-rewrite them. Copy first, verify identical, then delete dead rules
   in small commits (§4.5). The LESS sources exist if a rebuild is ever wanted, but rebuilding
   introduces formatting churn that the harness will flag.
3. Elementor's own generated per-post CSS, unchanged.

The two `@font-face` rules currently inlined at `enqueues.php:222` move into `theme.css` with
relative URLs, which removes a per-request string build.

### 8.5 Migration — switching the active theme without losing settings

Everything Elementor owns is stored in posts and global options and is **theme-agnostic**: the kit
(post 5, `elementor_active_kit`), all 26 `elementor_library` documents, the TB conditions, the
custom icon set in `uploads/elementor/custom-icons/theme-icons/` (self-contained, relative font
URLs), and ElementsKit's settings. None of it needs touching.

What **is** theme-scoped and will be silently lost:

| Item | Current location | Action |
|---|---|---|
| Active theme | `wp_options.template` = `wp_options.stylesheet` = `tecnologia` | set both to `piecyfer-theme` |
| `current_theme` | `(VamTam) Tecnologia` | set to `PieCyfer` |
| Custom logo | `theme_mods_tecnologia['custom_logo']` = **987718** | copy into `theme_mods_piecyfer-theme` |
| Additional CSS | `theme_mods_tecnologia['custom_css_post_id']` = **2547**, and `wp_posts` 2547 has **`post_name = 'tecnologia'`** | copy the theme mod **and** rename `post_name`/`post_title` to `piecyfer-theme` — WordPress resolves this post by the active stylesheet slug, so the theme mod alone is not enough |
| Additional JS | `wp_options.vamtam_additional_js` (6,164 B, three slots), printed by the **plugin** | port into `assets/js/site.js` **before** deactivating the plugin (§5.3) |
| Widget assignments | `sidebars_widgets` — all five blocks inactive | nothing to do |
| Nav menu locations | `theme_mods_tecnologia['nav_menu_locations']` = `[]` | nothing to do |

Do this with a script that writes both `theme_mods_piecyfer-theme` and the `custom_css` post rename
in one transaction, **not** through the wp-admin Themes screen — activating a theme through the UI
fires `after_switch_theme`, which can reset `sidebars_widgets` and re-run Customizer migrations.

**Ordering.** Switch the theme *while the companion plugin is still active*. The plugin's only hard
dependency on the theme is the `Author: VamTam` guard at `vamtam-elementor-integration.php:211-220`
— so either keep `Author: VamTam` in `style.css` for the transition (ugly but effective, and it
lets you swap the theme and the plugin in two separately-verified steps), or accept that both must
come out together. **Recommendation: keep the guard-satisfying author line for exactly one commit**,
prove the theme swap is pixel-identical with the plugin still running, then remove the plugin
separately. Two small verified steps beat one big one.

---

<a id="9"></a>
## 9. Risks

### 9.1 What breaks invisibly

| # | Risk | Why it is invisible | Mitigation |
|---|---|---|---|
| 1 | **`elementor_theme_do_location` stops working** because `register_locations` was not registered or ran too late | The page still renders — just with no header and no footer. Obvious. But the *inverse*, `page-title-location` silently missing, is not: `#sub-header` renders empty on every page today anyway | Assert `elementor_location_exits('header')` in a smoke test, and diff `#sub-header` markup |
| 2 | **The 171-property `:root` block drifts by one value** | A single wrong colour or line-height shifts text by a pixel on some pages and nothing on others. Screenshot diffs catch the big ones; a `letter-spacing` change on h5 may not appear at any captured viewport | **Byte-diff the emitted `<style id="piecyfer-tokens">` against the captured `vamtam-theme-options` block before trusting any screenshot.** This is a cheap, exact test and it should gate the whole phase |
| 3 | **Additional CSS (3,713 B) disappears** because the `custom_css` post was not renamed | The site loses `.blue`, `.post-btn-hover`, the gallery margins, the mobile testimonial rules — visible, but only on the pages that use them | §8.5; also assert `wp-custom-css` is non-empty in the harness |
| 4 | **The custom logo disappears** | `theme_mods` are per-theme | §8.5 |
| 5 | **`vamtam_additional_js` is lost with the plugin** | Form validation, search-length limits, comment validation and the button markup rewrite all stop. **The button rewrite is visual and would be caught; the form validation is not — it only manifests when a human submits a bad value** | Port first, then remove the plugin. Add a non-pixel test that a rendered `<a class="elementor-button">` has no inner `<span>` |
| 6 | **`vamtam-has-theme-widget-styles` stops being emitted** | It is on the wrapper of every widget and gates most of the theme CSS. If the class vanishes, *most of the theme's styling vanishes at once* — which is loud, and therefore the safest failure mode here | Keep `widget-base.php`'s hook in `piecyfer-core`, unchanged, and verify with `which-implementation.php` |
| 7 | **`author.php` and `attachment.php` have no TB template and no harness coverage** | These are the only routes that still render *theme* markup for real content. `_project/pixel-tool/urls.js` does not include an author or attachment URL. A regression there is completely invisible | **Add `/author/<slug>/` and one attachment URL to the harness before starting.** Then decide whether to keep the templates or give them a TB archive template |
| 8 | **The `popup` dynamic tag belongs to VamTam, not Pro** | Every button on the site links through `#elementor-action…popup`. If the VamTam tag disappears and Pro's takes over, the `align_with_parent` key in the base64 payload is unknown to Pro. It may degrade silently rather than error | `piecyfer-core` must own the `popup` tag with all three keys, and register at a priority that wins |
| 9 | **A control injection lands on a section id that no longer exists** | Already proven on this project (`STATUS.md`, phase 4c): five renamed section ids silently dropped ElementsKit injections. The page renders, the control is gone, and the *saved value* is dropped on next save | Reproduce section ids exactly. Assert control presence via `Plugin::$instance->controls_manager`, not by looking at pages |
| 10 | **`e_element_cache` is re-enabled** | It caches rendered HTML per document; the header and single templates are each one document shared by every page. Already documented as corrupting nav highlighting, form `queried_id` and `comment_post_ID` | It is off as of 2026-08-14. Keep `capture.js`'s fatal-on-failure cache clear |
| 11 | **Deleting "dead" CSS that is only dead today** | 45% of the bundle matches a dead selector, but "dead" means *no page currently uses that widget*. Add a Tabs widget in six months and the styling is gone with no error | Prune in small commits, keep a note of what was removed and why, and treat the removed rules as recoverable from git rather than gone |
| 12 | **The theme swap changes `body_class`** | Eight of the twelve current classes are referenced by nothing, but they are in the captured HTML. Dropping them is a markup diff on 39 pages that has to be triaged by hand | Emit all twelve for the swap commit; drop the dead eight in a later, separate commit |

### 9.2 Verification gates

Run the pixel harness at **every** step, never batched:

| Step | Gate |
|---|---|
| 0 | Add `/author/…/` and an attachment URL to `urls.js`; re-baseline |
| 1 | Port `vamtam_additional_js` → `assets/js/site.js`, still on the old theme, plugin still active. **Expect 0 markup diff** |
| 2 | Create `piecyfer-theme` with the four CSS files copied byte-for-byte and `tokens.php` emitting the identical `:root` block. **Byte-diff the `:root` block first, then capture.** Expect 0 diff |
| 3 | Switch `template`/`stylesheet` + migrate theme mods + rename the `custom_css` post. Plugin still active. **Expect 0 diff on 24 screenshots and 39 HTML files** |
| 4 | Remove the `Author: VamTam` line, deactivate the companion plugin only after `piecyfer-core` owns its widgets, skin, injections and dynamic tags (§6). **This is the largest single risk in the project** |
| 5 | Only then prune dead CSS, dead `body_class` entries, and the 40 unread custom properties — one commit each |

### 9.3 Blunt assessment of size

The theme replacement is **small**: four stylesheets to copy, one 310-line kit-translation function
to reimplement, ~40 lines of wrapper markup, two JS files to trim, and a migration script. Call it
two to three days including verification.

The companion plugin replacement is **not small**, and it is the part that the phrase "replace the
theme" hides:

- **35 control injections** against Elementor Pro section ids, each of which fails silently.
- **The `vamtam_classic` skin**, which is 15 widget instances, ~150 controls, a reordered
  `render_post()`, and a Box section that only exists because of a quirk in how Pro registers skin
  control sections.
- **Five widget subclasses** whose `get_script_depends()` methods hardcode copies of Pro's internal
  dependency arrays.
- **A dynamic tag that shadows one of Pro's**, on which every button on the site depends.

`02-WIDGET-REBUILD-SPEC.md` already flags `posts`/`archive-posts` as the second-largest item after
Theme Builder. This analysis supports that and narrows it: **94% of the VamTam customisation on
this site is one skin on two widgets, in 15 elements, across 11 documents.** That is a much better
shaped problem than "928 settings" suggests — but it is still the single largest piece of
transcription in Phase 4, and it cannot be skipped, because the blog listing and both archives
depend on it.

Three things in this document are, in my judgement, under-appreciated elsewhere in `_project/`:

1. **`vamtam_additional_js` is site-critical code in an unversioned database option, printed by the
   plugin we are deleting.** It is 6,164 bytes, it rewrites every button on the site, it validates
   every form, and roughly a quarter of it is already broken by an Elementor experiment.
2. **The GTM container is in `header.php` and is not in the audit's list of customisations.**
3. **`author.php` and `attachment.php` render theme markup and are not in the pixel harness.**
   They are the only blind spot where a theme swap could change real output with zero signal.
