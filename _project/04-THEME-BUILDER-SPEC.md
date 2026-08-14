# PieCyfer — Theme Builder Replacement Spec

**Date:** 2026-08-14
**Site:** localhost/piecyfer (WP, theme `tecnologia`, Elementor 3.25.10 + Elementor Pro 3.25.4 nulled)
**Companion to:** `02-WIDGET-REBUILD-SPEC.md` (build step **4i**), `01-REBUILD-PLAN.md`
**Goal:** replace Elementor Pro's Theme Builder inside `piecyfer-core` so that `elementor-pro/` can be deleted without the site losing its header, footer, single-post, archive, search and 404 layouts.

> This document is analysis + design only. No production code was written for it. Every claim about Pro's internals cites `path:line` so the next engineer can verify it against the installed 3.25.4 tree.

---

## 0. One-paragraph summary

Pro's Theme Builder is three separable things: **(a)** a small set of *document types* (`header`, `footer`, `single-post`, `archive`, `search-results`, `error-404`, `section`) that control the wrapper markup and the CSS wrapper selector; **(b)** a *locations manager* that owns `elementor_theme_do_location()`, the `template_include` takeover and the `elementor/theme/*` hooks; **(c)** a *conditions manager* that maps the request to a template ID using a priority number. All the heavy lifting — reading `_elementor_data`, generating CSS, printing elements — is done by **Elementor free**, which we keep. The replacement is therefore mostly plumbing, roughly 8 classes. The two genuine losses are the **Theme Builder admin app / display-conditions editor UI** (Pro's React + editor JS) and Pro's **`[elementor-template]` shortcode**, which this site depends on (see §7).

---

## 1. Templates that exist on this site

Query used:

```sql
-- C:\xampp\mysql\bin\mysql.exe -u root piecyfer -e "..."
SELECT p.ID, p.post_title, p.post_status,
       MAX(CASE WHEN m.meta_key='_elementor_template_type' THEN m.meta_value END) AS tpl_type,
       MAX(CASE WHEN m.meta_key='_elementor_conditions'    THEN m.meta_value END) AS conditions
FROM wp_posts p LEFT JOIN wp_postmeta m ON m.post_id = p.ID
WHERE p.post_type='elementor_library'
GROUP BY p.ID, p.post_title, p.post_status ORDER BY tpl_type, p.ID;
```

28 `elementor_library` posts (26 publish, 2 draft). Ten of them are Theme Builder documents:

| ID | Title | Type | Location | Status | Conditions? |
|---|---|---|---|---|---|
| 171 | Header - H. IT Services | `header` | header | publish | yes |
| 1273 | PieCyfer Footer | `footer` | footer | publish | yes |
| 991509 | Carees Footer | `footer` | footer | publish | yes |
| 8502 | Blog Post Template | `single-post` | single | publish | yes |
| 8716 | Elementor Error 404 | `error-404` | single | publish | yes |
| 8559 | Blog Posts Archives | `archive` | archive | publish | yes |
| 6126 | Case Studies Archives | `archive` | archive | publish | yes (**dead**, §2.4) |
| 8711 | Search Results | `search-results` | archive | publish | yes |
| 7718 | Consultation CTA | `popup` | popup | publish | none (§7.4) |
| 8519 | Blog Post – Comments | `section` | — | **draft** | none (§2.5) |

The remaining 18 are not Theme Builder documents and are out of scope for this spec, but two of their types still need a registered document class or they will render with the wrong wrapper:

| Type | IDs | Note |
|---|---|---|
| `kit` | 5, 7 | Elementor **free** — already registered by core. |
| `section` | 645, 741, 867, 1714, 2642, 2671, 8519(draft), 988052, 989489, 995521(draft), 996210 | **Pro document type.** Must be re-registered (§6.2). |
| `widget` | 607, 767, 3700 | Elementor free (`Widget` library document). |
| `page` | 2661, 996219, 996222 | Elementor free (`Page` library document). |
| `popup` | 7718 | Pro; belongs to the Popup work item (4l), not 4i. |

This matches `00-AUDIT-REPORT.md:282` ("10 templates: 1 header, 2 footers, 1 single-post, 2 archives, 1 search-results, 1 404") and `00-AUDIT-REPORT.md:290` ("26 `elementor_library` documents … 13 reusable `section` templates").

---

## 2. Display conditions — the routing table we must reproduce

Conditions live in postmeta `_elementor_conditions` (a serialised array of `type/name/sub_name/sub_id` strings) **and** in a denormalised cache option `elementor_pro_theme_builder_conditions`, keyed by location (`conditions-cache.php:15`, `:29-39`, `:70-72`). The runtime read path uses **only the option**, never the postmeta (`conditions-manager.php:329` → `$this->cache->get_by_location( $location )`).

Current live value of `wp_options.elementor_pro_theme_builder_conditions` (verified 2026-08-14) — order preserved, because iteration order decides ties:

```php
[
  'footer'  => [ 991509 => [...], 1273 => [...] ],
  'single'  => [ 8716 => [...], 8502 => [...] ],
  'archive' => [ 8711 => [...], 8559 => [...], 6126 => [...] ],
  'header'  => [ 171 => [...] ],
]
```

### 2.1 Every condition in plain English

| Template | Raw condition | Plain English | Priority (§2.2) |
|---|---|---|---|
| 171 header | `include/general` | Include on the entire site | **100** |
| 171 header | `exclude/singular/page/2055` | Exclude on page ID 2055 | — (exclude) |
| 1273 footer | `include/general` | Include on the entire site | **100** |
| 1273 footer | `exclude/singular/page/1298` | Exclude on page 1298 (*Careers (Temp)*, draft) | — |
| 991509 footer | `include/singular/page/1648` | Include on page 1648 (*Privacy Policy*) | **20** |
| 991509 footer | `include/singular/page/1646` | Include on page 1646 (*Terms & Conditions*) | **20** |
| 991509 footer | `include/singular/page/1298` | Include on page 1298 (*Careers (Temp)*, draft) | **20** |
| 991509 footer | `include/singular/child_of/1298` | Include on any direct child of page 1298 | **20** |
| 8502 single-post | `include/singular/post` | Include on every single Post | **30** |
| 8502 single-post | `include/singular/in_category` | Include on singulars in *(no category chosen)* | **25** — never fires, §2.4 |
| 8502 single-post | `include/singular/in_category_children` | Include on singulars in child categories of *(none chosen)* | never fires, §2.4 |
| 8716 error-404 | `include/singular/not_found404` | Include on the 404 page | **5** |
| 8559 archive | `include/archive` | Include on all archives (incl. blog home & search) | **80** |
| 8559 archive | `exclude/archive/search` | Exclude on search results | — |
| 8559 archive | `exclude/archive/any_child_of_category/22` | Exclude on any descendant of category 22 | — never fires, §2.4 |
| 8559 archive | `exclude/archive/post_tag/46` | Exclude on tag archive, term 46 | — never fires, §2.4 |
| 8559 archive | `exclude/archive/post_tag/47` | Exclude on tag archive, term 47 | — never fires, §2.4 |
| 6126 archive | `include/archive/any_child_of_category/22` | Include on any descendant of category 22 | never fires, §2.4 |
| 6126 archive | `include/archive/post_tag/46` | Include on tag archive, term 46 | never fires, §2.4 |
| 6126 archive | `include/archive/post_tag/47` | Include on tag archive, term 47 | never fires, §2.4 |
| 8711 search-results | `include/archive/search` | Include on search results | **55** |

### 2.2 The priority / specificity algorithm

`conditions-manager.php:466-485`:

```php
private function get_condition_priority( $condition_instance, $sub_condition_instance, $sub_id ) {
    $priority = $condition_instance::get_priority();
    if ( $sub_condition_instance ) {
        if ( $sub_condition_instance::get_priority() < $priority ) {
            $priority = $sub_condition_instance::get_priority();
        }
        $priority -= 10;
        if ( $sub_id ) {
            $priority -= 10;
        } elseif ( 0 === count( $sub_condition_instance->get_sub_conditions() ) ) {
            // if no sub conditions - it's more specific.
            $priority -= 5;
        }
    }
    return $priority;
}
```

**Lower number = more specific = wins.** The winner is chosen by `asort()` (`conditions-manager.php:409`) then taking the first entry, because none of the four core locations sets `multiple` (`conditions-manager.php:530-539` breaks out of the loop when `empty( $location_settings['multiple'] )`).

Ties: `asort()` in PHP ≥ 8.0 is **stable**, so ties are broken by the order of templates inside `elementor_pro_theme_builder_conditions[$location]` — which is the order `Conditions_Cache::regenerate()` wrote them (`conditions-cache.php:128-137`, a `WP_Query` with `posts_per_page => -1`, default ordering = `post_date DESC`). *Our replacement must preserve this: read the same option, iterate in the same order, use a stable sort.*

Base priorities (all in `elementor-pro/modules/theme-builder/conditions/`):

| Condition | Class / file | `get_type()` | `get_priority()` | Has sub-conditions? |
|---|---|---|---|---|
| `general` | `general.php:8` | general | **100** (inherited, `condition-base.php:16-18`) | yes (`archive`, `singular`) |
| `archive` | `archive.php:10` | archive | **80** (`:22-24`) | yes (`author`, `date`, `search`, + one `*_archive` per post type) |
| `singular` | `singular.php:10` | singular | **60** (`:24-26`) | yes (`front_page`, one per post type, `child_of`, `any_child_of`, `by_author`, `not_found404`) |
| `author` / `date` / `search` / `<tax>` / `<pt>_archive` | `author.php:16`, `date.php:14`, `search.php:14`, `taxonomy.php:18`, `post-type-archive.php:17` | archive | **70** | `search`/`date`/`author` no; `<pt>_archive` yes (taxonomy children) |
| `<post_type>` (e.g. `post`, `page`) | `post.php:19-21` | singular | **40** | yes (`in_<tax>`, `in_<tax>_children`, `<pt>_by_author`) |
| `child_of` / `any_child_of` | `child-of.php:16-18`, `any-child-of.php:8` | singular | **40** | no |
| `in_<tax>` / `in_<tax>_children` | `in-taxonomy.php:22-24`, `in-sub-term.php:8` | singular | **40** | no |
| `by_author` / `<pt>_by_author` | `by-author.php:16`, `post-type-by-author.php:18` | singular | **40** | no |
| `front_page` | `front-page.php:14-16` | singular | **30** | no |
| `not_found404` | `not-found404.php:14-16` | singular | **20** | no |

Worked examples for this site:

- `include/general` → no sub → `100`.
- `include/archive` → no sub → `80`.
- `include/archive/search` → `min(80, 70) = 70`, `-10 = 60`, no `sub_id`, `Search` has no sub-conditions → `-5` → **`55`**.
- `include/singular/post` → `min(60, 40) = 40`, `-10 = 30`, no `sub_id`, `Post('post')` *does* have sub-conditions → **`30`**.
- `include/singular/page/1648` → `min(60, 40) = 40`, `-10 = 30`, `sub_id` present → `-10` → **`20`**.
- `include/singular/child_of/1298` → `min(60, 40) = 40`, `-10 = 30`, `sub_id` present → `-10` → **`20`**.
- `include/singular/not_found404` → `min(60, 20) = 20`, `-10 = 10`, no `sub_id`, no sub-conditions → `-5` → **`5`**.

### 2.3 Resulting live routing table (verified by HTTP, 2026-08-14)

| Request | header | footer | single / archive body |
|---|---|---|---|
| `/` (front page, page 146) | 171 | 1273 | none (page's own Elementor content) |
| any normal page | 171 | 1273 | none |
| `/privacy-policy/` (1648) | 171 | **991509** (20 beats 100) | none |
| `/terms-conditions/` (1646) | 171 | **991509** | none |
| `/blogs/` (page 93) | 171 | 1273 | none — see §2.6 |
| single post | 171 | 1273 | **8502** (`single`) |
| 404 | 171 | 1273 | **8716** (`single`) |
| `/category/web-apps/` | 171 | 1273 | **8559** (`archive`) |
| `/?s=…` | 171 | 1273 | **8711** (`archive`) |
| tag archive, author archive, date archive | 171 | 1273 | **8559** |

Confirmed with `curl` against the running XAMPP instance — e.g. `/privacy-policy/` emits `data-elementor-id="991509"` for the footer, `/?s=crm` emits `data-elementor-type="search-results" data-elementor-id="8711"`.

### 2.4 Conditions that reference objects that no longer exist

This materially simplifies the replacement, and it must be recorded so nobody "fixes" the replacement to make these fire:

| Condition | Referenced object | Reality |
|---|---|---|
| `exclude/singular/page/2055` (header 171) | post 2055 | **does not exist** in `wp_posts` → header 171 is unconditionally site-wide |
| `.../any_child_of_category/22` (8559 exclude, 6126 include) | term 22 | **does not exist** in `wp_terms` |
| `.../post_tag/46`, `.../post_tag/47` (8559 exclude, 6126 include) | terms 46, 47 | exist but are **`nav_menu`** terms ("Footer - Menu Resources", "Footer - Menu Solutions 01"), not `post_tag` → `is_tag(46)` is always false |
| `include/singular/in_category` (8502) | no term id | `In_Taxonomy::check()` runs `has_term( 0, 'category' )` (`in-taxonomy.php:42`); with id `0` this does not match a specific term. Behaviourally redundant with `include/singular/post`, which already wins at priority 30 for every post. **Treat as inert; do not rely on it.** |
| `include/singular/in_category_children` (8502) | no term id | `In_Sub_Term::check()` returns `false` immediately when `! $id` (`in-sub-term.php:32`). Provably never fires. |

**Template 6126 "Case Studies Archives" is dead** — all three of its include conditions reference objects that cannot match. It renders on no URL. It should be left in the database (harmless) but the replacement does not need `any_child_of_<tax>` support to reach parity. Categories that actually exist: 1 Blog, 15 Uncategorized, 72 ERP, 73 Web Apps, 74 Mob Apps, 75 CRM. There are **no `post_tag` terms at all** and no custom post types.

### 2.5 The `section` document that is not routed by conditions

Template **8519** ("Blog Post – Comments", `section`, **draft**) is rendered inside the single-post template by a shortcode, not by a condition. `wp_postmeta._elementor_data` for post 8502 contains, inside a toggle widget:

```
"tab_content":"<p>[elementor-template id=\"8519\"]<\/p>"
```

That shortcode is **Pro's**, registered at `elementor-pro/modules/library/classes/shortcode.php:71`. See §7.2 — this is a hard dependency outside the theme-builder module.

### 2.6 `/blogs/` is not the posts page

`wp_options.page_for_posts = 93`, but the theme neutralises it:

```php
// wp-content/themes/tecnologia/vamtam/classes/overrides.php:22
add_filter( 'pre_option_page_for_posts', '__return_zero' );
```

So `/blogs/` renders as a plain singular page (`body class="… page page-id-93 … elementor-page-93"`) and the `archive` location does **not** fire there. This is load-bearing: if `piecyfer-theme` drops that filter, `/blogs/` will suddenly start rendering archive template 8559 and the page will change completely.

---

## 3. How Pro renders theme templates

All paths below are relative to `wp-content/plugins/elementor-pro/modules/theme-builder/`.

### 3.1 Components and the hooks they attach

`module.php:433-494` constructs five components and wires the editor/admin filters:

```php
// module.php:436-449
require __DIR__ . '/api.php';
$this->add_component( 'theme_support',   new Classes\Theme_Support() );
$this->add_component( 'conditions',      new Classes\Conditions_Manager() );
$this->add_component( 'templates_types', new Classes\Templates_Types_Manager() );
$this->add_component( 'preview',         new Classes\Preview_Manager() );
$this->add_component( 'locations',       new Classes\Locations_Manager() );

add_action( 'elementor/controls/register',            [ $this, 'register_controls' ] );
add_action( 'elementor/editor/init',                  [ $this, 'on_elementor_editor_init' ] );
add_filter( 'elementor/document/config',              [ $this, 'document_config' ], 10, 2 );
add_filter( 'elementor/document/wrapper_attributes',  [ $this, 'add_document_attributes' ], 10, 2 );
```

Front-end-relevant hooks, exhaustively:

| Hook | Priority | Where | What it does |
|---|---|---|---|
| `the_content` | `9999999` | `locations-manager.php:32` | replaces the content of a header/footer document with the `Content Area` placeholder when that document is itself being viewed (`builder_wrapper()`, `:406-422`) |
| `template_include` | **11** | `locations-manager.php:33` | the takeover — see §3.3 |
| `template_redirect` | 10 | `locations-manager.php:34` | fires `elementor/theme/register_locations` once (`:90-106`) |
| `wp_enqueue_scripts` | 10 | `locations-manager.php:39` | enqueues each matched document's `Post_CSS` file + `_elementor_page_assets` (`:108-151`) |
| `pre_handle_404` | 10, 2 args | `locations-manager.php:42` | keeps `?page=N` pagination on single templates from 404-ing (`:60-88`) |
| `elementor/theme/{location}` | 5 | `locations-manager.php:475-483` | generic per-location entry point (registered for every location; unused by this theme) |
| `init` | 10 | `theme-support.php:15` | registers `elementor/theme/register_locations` @99 (`:30`) |
| `get_header` / `get_footer` | 10 | `theme-support.php:64-65` | **only when the theme did not register the header/footer locations itself.** Not active on this site — see §4.2 |
| `wp_loaded` | 10 | `conditions-manager.php:34` | registers the condition classes (`register_conditions()`, `:284-296`) |
| `elementor/documents/register` | 10 | `templates-types-manager.php:15` | registers the 9 document types (`:34-50`) |

Public API (`api.php`):

```php
// api.php:9-21
function elementor_theme_do_location( $location ) {
    return Theme_Builder_Module::instance()->get_locations_manager()->do_location( $location );
}
function elementor_location_exits( $location, $check_match = false ) {
    return Theme_Builder_Module::instance()->get_locations_manager()->location_exits( $location, $check_match );
}
```

Dynamic actions fired around every location print (`locations-manager.php:334`, `:388`):
`elementor/theme/before_do_{$location}` and `elementor/theme/after_do_{$location}`.
On this site the live ones are `header`, `footer`, `single`, `archive`, `page-title-location`, `popup`. **Nothing in the theme or any non-Elementor plugin hooks them** (verified by grep across `wp-content/themes` and `wp-content/plugins` excluding `elementor-pro`).

### 3.2 Location → template ID resolution at runtime

```
do_location( $location )                              locations-manager.php:306
  └─ Conditions_Manager::get_documents_for_location()  conditions-manager.php:516
       ├─ get_theme_templates_ids( $location )         conditions-manager.php:414
       │    1. ?theme_template_id=N in the URL and its document's location matches → that ID, priority 1   (:422-437)
       │    2. the currently queried post IS a document for this location (editor/preview) → that ID       (:439-451)
       │    3. otherwise get_location_templates( $location )                                                (:453)
       └─ for each ID in priority order, instantiate the document;
          stop after the first unless the location has 'multiple' => true                                   (:530-539)
```

`get_location_templates()` (`conditions-manager.php:326-412`) is the core of it:

1. Read `elementor_pro_theme_builder_conditions[$location]` from the option cache.
2. For each `template_id => conditions[]`, for each condition string, `parse_condition()` splits on `/` into `type/name/sub_name/sub_id` (`:505-509`).
3. `$condition_instance->check([])` for the outer name; if it passes and there is a `sub_name`, `$sub_condition_instance->check([ 'id' => $sub_id ])`.
4. If both pass **and** `get_post_status( $template_id ) === 'publish'` (`:385-394` — non-published templates are skipped), then `include` records `$conditions_priority[$id] = get_condition_priority(...)`, `exclude` pushes onto `$excludes[]`.
5. **All excludes are applied after all includes** (`:405-407`): `unset( $conditions_priority[ $exclude_id ] )`. An exclude therefore always beats an include for the same template, regardless of specificity.
6. `asort( $conditions_priority )` — ascending, so lowest priority number first (`:409`).

Note the per-request memo: `Conditions_Manager::$location_cache` (`:29`, `:517-519`, cleared by `:558-560`). Both `enqueue_styles()` (at `wp_enqueue_scripts`) and `do_location()` (during rendering) call it, so the resolution runs once per location per request.

There is also a **manual queue** independent of conditions — `add_doc_to_location()` / `skip_doc_in_location()` (`locations-manager.php:266-304`). The only users on this site are Pro's Popup module (`elementor-pro/modules/popup/module.php:128`) and Custom Code (`modules/custom-code/module.php:443`). This is how popup 7718 renders even though it has **no** `_elementor_conditions` — see §7.4.

### 3.3 `template_include` — what Pro takes over, and what it leaves alone

`locations-manager.php:153-260`. Mapping (`:176-182`):

```php
} elseif ( function_exists( 'is_shop' ) && is_shop() ) { $location = 'archive';
} elseif ( is_archive() || is_tax() || is_home() || is_search() ) { $location = 'archive';
} elseif ( is_singular() || is_404() ) { $location = 'single'; }
```

The override decision (`:222-239`):

```php
$location_exist         = ! empty( $location_settings );
$is_header_footer       = 'header' === $location || 'footer' === $location;
$need_override_location = ! empty( $location_settings['overwrite'] ) && ! $is_header_footer;
$need_override_location = apply_filters( 'elementor/theme/need_override_location', $need_override_location, $location, $this );

if ( $location && empty( $page_template ) && ( ! $location_exist || $need_override_location ) ) {
    $page_template = $page_templates_module::TEMPLATE_HEADER_FOOTER;
}
```

Core locations and their `overwrite` flag (`locations-manager.php:517-545`): `header` no, `footer` no, **`archive` yes** (`:534`), `single` no.

**Consequence for this site** (because the theme registers all four itself, §4.2):

- `header`, `footer`, `single` → `$location_exist` is true, `overwrite` is unset → **`template_include` returns the theme's own template unchanged.** The theme calls `elementor_theme_do_location()` inline.
- `archive` (and search, and any post-type/tax/date/author archive) → `overwrite => true` → `$page_template = elementor_header_footer` → the template file becomes
  `elementor/modules/page-templates/templates/header-footer.php`, and the print callback is set to `do_location('archive')`:

```php
// locations-manager.php:245-247
$page_templates_module->set_print_callback( function() use ( $location ) {
    Module::instance()->get_locations_manager()->do_location( $location );
} );
```

`header-footer.php` (whole file, 30 lines) adds the body class `elementor-template-full-width`, calls `get_header()`, fires `elementor/page_templates/header-footer/before_content`, calls `PageTemplates\Module::print_content()`, fires `…/after_content`, calls `get_footer()`. That is why `body class` on `/category/web-apps/` contains `elementor-template-full-width` and why `themes/tecnologia/archive.php` line 18's fallback is unreachable dead code.

There is also an earlier branch (`:156-167`): if the current singular post has a `_wp_page_template` other than `default` and its document supports WP page templates, `template_include` returns early and only records `$this->current_page_template`, which later filters out core locations for the canvas/full-width templates (`filter_page_template_locations()`, `:582-605`).

### 3.4 Printing a location — the exact markup

```php
// locations-manager.php:306-391 (abridged)
public function do_location( $location ) {
    $documents_by_conditions = ...->get_documents_for_location( $location );
    foreach ( $documents_by_conditions as $id => $doc ) { $this->add_doc_to_location( $location, $id ); }
    if ( empty( $this->locations_queue[ $location ] ) ) { return false; }     // ← the theme branches on this
    if ( is_singular() ) { Utils::set_global_authordata(); }
    do_action( "elementor/theme/before_do_{$location}", $this );
    while ( ! empty( $this->locations_queue[ $location ] ) ) {
        $document_id = key( $this->locations_queue[ $location ] );
        $document    = Module::instance()->get_document( $document_id );
        if ( ! $document || $this->is_printed( $location, $document_id ) ) { $this->skip_doc_in_location(...); continue; }
        if ( empty( $documents_by_conditions[ $document_id ] ) && 'publish' !== get_post_status( $document_id ) ) {
            $this->skip_doc_in_location( $location, $document_id ); continue;  // manually-added drafts are skipped
        }
        $this->current_location = $location;
        $document->print_content();
        $this->did_locations[] = $this->current_location;
        $this->current_location = null;
        $this->set_is_printed( $location, $document_id );
    }
    do_action( "elementor/theme/after_do_{$location}", $this );
    return true;
}
```

`Utils::set_global_authordata()` is `elementor-pro/core/utils.php:229-235`:

```php
global $authordata;
if ( ! isset( $authordata->ID ) ) { $post = get_post(); $authordata = get_userdata( $post->post_author ); }
```

`print_content()` → `get_content()` → **Elementor free** does the rest:

```php
// theme-document.php:165-175
public function print_content() {
    $plugin = Plugin::elementor();
    if ( $plugin->preview->is_preview_mode( $this->get_main_id() ) ) {
        echo $plugin->preview->builder_wrapper( '' );
    } else {
        echo $this->get_content();
    }
}
```

`Library_Document::get_content()` (`elementor/modules/library/documents/library-document.php:75-77`) wraps `Document::get_content()` in `do_shortcode()`; `Document::get_content()` (`elementor/core/base/document.php:1247-1249`) calls `Frontend::get_builder_content( $post_id, $with_css )` (`elementor/includes/frontend.php:1114-1218`), which:

- bails on `post_password_required()` and on documents not built with Elementor,
- `documents->switch_to_document()` so widgets see the right current document,
- creates and **enqueues** the `Post_CSS` file (`:1150-1167`),
- `ob_start()`, `$document->print_elements_with_wrapper( $data )` (`:1190`), `ob_get_clean()`,
- applies `elementor/frontend/the_content`, then `documents->restore_document()`.

**All of that is free-core and we keep it verbatim.** The only Pro contributions to the markup are the wrapper tag and the wrapper attributes.

#### Wrapper element

`Theme_Document::print_elements_with_wrapper()` (`theme-document.php:395-415`) allows the document's `content_wrapper_html_tag` setting to replace `div`, choosing from `main, article, header, footer, section, aside, nav` (`:417-428`, with `div` prepended at `:363`). **On this site all ten theme documents use the default `div`** — verified from the live HTML.

#### Wrapper attributes, in order

Built by `Document::get_container_attributes()` (`elementor/core/base/document.php:402-428`):

1. `data-elementor-type` = `$document->get_name()`
2. `data-elementor-id` = main post ID
3. `class` = `elementor elementor-{id}` (+ ` elementor-bc-flex-widget` if `_elementor_version < 2.5.0`)
4. `data-elementor-title` (preview only) **or** `data-elementor-settings` (JSON of frontend settings, only when non-empty)

then Pro's two overrides:

5. `Theme_Document::get_container_attributes()` (`theme-document.php:185-195`) appends ` elementor-location-{current_location}` to `class` — **the location, not the document type**. This is why 8716 (`error-404`) and 8711 (`search-results`) carry `elementor-location-single` and `elementor-location-archive` respectively.
6. `Single_Base::get_container_attributes()` (`single-base.php:67-76`) additionally appends `implode(' ', get_post_class('', get_the_ID()))` when `is_singular()` — that is where `post-993554 post type-post status-publish …` comes from on single posts. **Not** applied on 404 (`is_singular()` is false).
7. `Module::add_document_attributes()` (`module.php:427-431`) adds `data-elementor-post-type` = the document post's post type, via the `elementor/document/wrapper_attributes` filter. **This filter is unconditional and applies to every Elementor document on the site, not just theme documents** — including `wp-page`, `wp-post` and `elementskit_content` wrappers.

Exact markup captured from the running site (must be reproduced byte-for-byte):

```html
<div data-elementor-type="header" data-elementor-id="171" class="elementor elementor-171 elementor-location-header" data-elementor-post-type="elementor_library">
<div data-elementor-type="footer" data-elementor-id="1273" class="elementor elementor-1273 elementor-location-footer" data-elementor-post-type="elementor_library">
<div data-elementor-type="footer" data-elementor-id="991509" class="elementor elementor-991509 elementor-location-footer" data-elementor-post-type="elementor_library">
<div data-elementor-type="single-post" data-elementor-id="8502" class="elementor elementor-8502 elementor-location-single post-993554 post type-post status-publish format-standard has-post-thumbnail hentry category-web-apps" data-elementor-post-type="elementor_library">
<div data-elementor-type="error-404" data-elementor-id="8716" class="elementor elementor-8716 elementor-location-single" data-elementor-post-type="elementor_library">
<div data-elementor-type="archive" data-elementor-id="8559" class="elementor elementor-8559 elementor-location-archive" data-elementor-post-type="elementor_library">
<div data-elementor-type="search-results" data-elementor-id="8711" class="elementor elementor-8711 elementor-location-archive" data-elementor-post-type="elementor_library">
<div data-elementor-type="section" data-elementor-id="8519" class="elementor elementor-8519 elementor-location-single" data-elementor-post-type="elementor_library">
<div data-elementor-type="wp-page" data-elementor-id="93" class="elementor elementor-93" data-elementor-post-type="page">
<div data-elementor-type="wp-post" data-elementor-id="991721" class="elementor elementor-991721" data-elementor-post-type="elementskit_content">
```

Attribute-order note: `class` is printed **third**, and `data-elementor-post-type` **last**, because `Utils::print_html_attributes()` preserves insertion order and Pro's filter runs after the array is built.

### 3.5 CSS, body classes and `_elementor_page_assets`

**CSS wrapper selector** — this is per-document-class and it determines what `Post_CSS` writes into `wp-content/uploads/elementor/css/post-{id}.css`:

| Document | `get_css_wrapper_selector()` | Source |
|---|---|---|
| `header`, `footer` | `.elementor-{id}` | `header-footer-base.php:13-15` |
| `single-post`, `single-page`, `single`, `error-404`, `archive`, `search-results` | `body.elementor-page-{id}` | `theme-page-document.php:20-22` |
| `section` | `''` (empty) | inherited from `Document` (`elementor/core/base/document.php:1209-1211`) |
| *(fallback if the type is unregistered)* | `body.elementor-page-{id}` | `elementor/core/document-types/page-base.php:61-63` |

The last row is the trap: with the types unregistered, `Documents_Manager::get_document_type( $type, 'post' )` (`elementor/core/documents-manager.php:305-317`) silently falls back to the `post` document class. Header 171's regenerated CSS would then be scoped to `body.elementor-page-171` instead of `.elementor-171` and the header would lose all of its styling. **This is the single most important reason to register document types.**

**Body classes** — `Theme_Page_Document::filter_body_classes()` (`theme-page-document.php:105-122`), hooked from the constructor (`:124-130`) whenever the document is constructed with data:

```php
$is_archive_template = 'archive' === Source_Local::get_template_type( get_the_ID() );
if ( $this instanceof Archive && ( is_archive() || is_search() || is_home() || $is_archive_template ) ) { $add = true; }
elseif ( $this instanceof Single_Base && ( is_singular() || is_404() ) && ! $is_archive_template ) { $add = true; }
if ( $add ) { $body_classes[] = 'elementor-page-' . $this->get_main_id(); }
```

Observed: `elementor-page-8502` on single posts, `elementor-page-8559` on category archives, `elementor-page-8711` on search. Header/footer documents add **no** body class (they are `Theme_Section_Document`, not `Theme_Page_Document`).

**Asset enqueue** — `Locations_Manager::enqueue_styles()` (`locations-manager.php:108-151`) runs on `wp_enqueue_scripts` and, for every location, for every matched document **other than the currently queried post**:

```php
$css_file = new Post_CSS( $post_id ); $css_files[] = $css_file;
$page_assets = get_post_meta( $post_id, Assets::ASSETS_META_KEY, true );   // '_elementor_page_assets'
if ( ! empty( $page_assets ) ) { Plugin::elementor()->assets_loader->enable_assets( $page_assets ); }
...
if ( ! empty( $css_files ) ) {
    Plugin::elementor()->frontend->enqueue_styles();      // frontend styles even on non-Elementor pages
    foreach ( $css_files as $css_file ) { $css_file->enqueue(); }   // after, so they override
}
```

`Assets::ASSETS_META_KEY = '_elementor_page_assets'` (`elementor/core/base/elements-iteration-actions/assets.php:13`). Ordering matters: the frontend styles are enqueued *before* the per-document CSS files. **Reproduce this order exactly** or the stylesheet order in `<head>` changes and the pixel harness will (correctly) fail.

`filter_page_template_locations()` (`:582-605`) drops core locations when the queried post uses `elementor_canvas` (all four dropped) or `elementor_header_footer` (only `header`/`footer` survive).

### 3.6 What Theme Builder does about the WordPress loop

Short answer: **almost nothing for archives, one `the_post()` for singles.**

- `Single_Base::before_get_content()` (`single-base.php:52-59`) calls the parent (preview-query switch, a no-op on the front end — §3.7) and then:
  ```php
  if ( have_posts() ) { the_post(); }   // "For `loop_start` hook."
  ```
  `Single_Base::after_get_content()` (`:61-65`) calls `wp_reset_postdata()` first, then the parent.
- `Archive` / `Search_Results` documents do **not** touch the loop. They inherit `Theme_Document::before_get_content()` only. The listing is produced entirely by the `archive-posts` widget, which runs its own `WP_Query`.
- `Locations_Manager::do_location()` sets `$authordata` when `is_singular()` (`:319-321`).
- `Locations_Manager::should_allow_pagination_on_single_templates()` on `pre_handle_404` (`:42`, `:60-88`) returns `true` — i.e. "already handled, do not 404" — when `?page=N` is requested on a post whose single template contains enough pagination-capable content. Depends on `Pagination_Trait::is_valid_pagination()` (`elementor-pro/modules/posts/traits/pagination-trait.php`).
- `Single_Base::print_content()` (`single-base.php:78-101`) prints the `Content Area` placeholder when the queried post is *itself* a theme document of a different location — an editor/preview nicety.

### 3.7 Preview manager (editor only)

`preview-manager.php:57-70` — `switch_to_preview_query()` returns immediately unless the **currently queried post** is itself a `Theme_Document`. On a normal front-end request that is never true, so `Theme_Document::before_get_content()`/`after_get_content()` (`theme-document.php:145-153`) are effectively no-ops. **The preview manager can be omitted from the front-end replacement entirely**; it is only needed if we want the editor's "Preview Dynamic Content as" feature back (§7.1).

---

## 4. What the current theme does

Active theme: `wp-content/themes/tecnologia` (VamTam Tecnologia 4.2). **No child theme exists** — `wp-content/themes/` contains only `tecnologia/`, `twentytwentyfive/` and `index.php`.

### 4.1 Every `elementor_theme_do_location()` call site

| File | Line | Location | Fallback if it returns false / Pro absent |
|---|---|---|---|
| `themes/tecnologia/header.php` | 40 | `header` | `get_template_part( 'templates/header' )` |
| `themes/tecnologia/footer.php` | 21 | `footer` | **none** — see below |
| `themes/tecnologia/page.php` | 20 | `single` | `the_content()` + `wp_link_pages()` + share partial |
| `themes/tecnologia/single.php` | 22 | `single` | `get_template_part('templates/post')` + `comments_template()` |
| `themes/tecnologia/404.php` | 14 | `single` | hardcoded "Holy guacamole!" 404 block |
| `themes/tecnologia/archive.php` | 18 | `archive` | `rewind_posts()` + `get_template_part('loop','archive')` — **unreachable in practice**, §3.3 |
| `themes/tecnologia/templates/header/page-title.php` | 5 | `page-title-location` | `div.limit-wrapper > div.meta-header-inside > header.page-header` |

`search.php`, `index.php`, `author.php`, `attachment.php` contain **no** location call — they rely entirely on the `archive` `template_include` takeover.

Header call site — note there is **no wrapper element**:

```php
// themes/tecnologia/header.php:39-43
<?php
    if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'header' ) ) {
        get_template_part( 'templates/header' );
    }
?>
```

Footer call site — note the wrapper, and that there is **no `else`**:

```php
// themes/tecnologia/footer.php:18-24
<?php if ( ( $footer_onepage || is_customize_preview() ) && function_exists( 'elementor_theme_do_location' ) && elementor_location_exits( 'footer' ) ) : ?>
    <div class="footer-wrapper" style="<?php VamtamTemplates::display_none( $footer_onepage, false ) ?>">
        <footer id="main-footer" class="main-footer">
            <?php elementor_theme_do_location( 'footer' ) ?>
        </footer>
    </div>
<?php endif ?>
```

If `elementor_theme_do_location` is undefined, the site has **no footer at all**. Also note `elementor_location_exits('footer')` is called with the default `$check_match = false`, so it only asks "is the location registered?" — the wrapper divs are emitted even when no footer template matches.

### 4.2 The theme registers the locations itself

```php
// themes/tecnologia/vamtam/classes/elementor-bridge.php:50
add_action( 'elementor/theme/register_locations', [ __CLASS__, 'register_locations' ] );

// themes/tecnologia/vamtam/classes/elementor-bridge.php:715-726
public static function register_locations( $elementor_theme_manager ) {
    $elementor_theme_manager->register_all_core_location();
    $elementor_theme_manager->register_location( 'page-title-location', [
        'label'           => esc_html__( 'Page Title', 'tecnologia' ),
        'multiple'        => true,
        'edit_in_content' => true,
    ] );
}
```

Because all four core locations are registered here, `Theme_Support::after_register_locations()` (`theme-support.php:36-68`) finds them present and **never installs its `get_header`/`get_footer` hijack** (`:64-66`). Pro's `views/theme-support-header.php` and `views/theme-support-footer.php` are dead on this site. The parameter is untyped, so our replacement manager can be passed to it without a type error.

### 4.3 Hard references to Pro classes in the theme — the transition hazard

```php
// themes/tecnologia/vamtam/classes/elementor-bridge.php:3
use ElementorPro\Modules\ThemeBuilder\Module as Theme_Builder_Module;

// :734-744
public static function is_location_template_exits( $location ) {
    if ( ! function_exists( 'elementor_theme_do_location' ) ) { return false; }
    $templates_asigned = Theme_Builder_Module::instance()->get_conditions_manager()->get_documents_for_location( $location );
    return ! empty( $templates_asigned );
}

// :751-764
public static function is_elementor_pro_active() {
    if ( is_admin() ) { return function_exists( 'elementor_pro_load_plugin' ); }
    return function_exists( 'elementor_theme_do_location' );
}

// :835-865  is_title_present_in_doc_locations()
foreach ( [ 'header', 'page-title-location', 'archive', 'single' ] as $location ) {
    $documents_by_conditions_for_loc = Theme_Builder_Module::instance()->get_conditions_manager()->get_documents_for_location( $location );
    foreach ( $documents_by_conditions_for_loc as $doc ) { $el_data = $doc->get_elements_data(); ... }
}
```

**This is the sharp edge.** The moment `piecyfer-core` defines `elementor_theme_do_location()`, the `function_exists` guard at line 735 passes and line 738 dereferences `ElementorPro\Modules\ThemeBuilder\Module` — a **fatal "Class not found"** once Pro is deleted. `is_location_template_exits()` is called from `page.php:13`, `page.php:42`, `single.php:17`, `single.php:33`, so it is on the hot path of every page and post. `is_title_present_in_doc_locations()` is on the hot path of every request via `templates/header/sub-header.php:15`.

Callers of `is_elementor_pro_active()` — each is a behaviour switch we inherit:

| File:line | Effect when "Pro active" |
|---|---|
| `vamtam/classes/framework.php:418` | **no sidebars are registered at all** |
| `vamtam/classes/templates.php:24` | layout forced to `full` (no left/right sidebar layouts) |
| `vamtam/classes/overrides.php:199` | `.limit-wrapper` suppressed on Elementor-built pages |
| `vamtam/helpers/template.php:70` | emits the `elementor-pro-active` **body class** |
| `vamtam/helpers/frontend-wrappers.php:7` | changes the content wrapper markup |
| `templates/header/sub-header.php:14` | suppresses the theme sub-header when a title widget is found in a location |
| `page.php:37` | suppresses `get_template_part('sidebar')` |

Note the `is_admin()` branch: it probes `elementor_pro_load_plugin`, which we will **not** define. In wp-admin the theme will therefore behave as "Pro inactive" and `framework.php:418` will register sidebars on `widgets_init`. `widgets_init` fires on the front end too, where the probe returns true, so sidebars stay unregistered there. Net effect: Appearance → Widgets gains sidebars that never render. Cosmetic, admin-only, but it will look wrong to the client — flag it and fix it in `piecyfer-theme`.

---

## 5. Replacement design for `piecyfer-core`

Namespace root `PieCyfer\Core` (`src/`), `declare( strict_types = 1 )`, `defined( 'ABSPATH' ) || exit;`, hand-rolled PSR-4 autoloader — same conventions as the existing widgets.

### 5.1 Minimum file set

```
wp-content/plugins/piecyfer-core/
├─ compat/
│  └─ elementor-pro-theme-builder-shim.php    conditionally required; declares the Pro-namespaced bridge class (§5.6)
└─ src/ThemeBuilder/
   ├─ Module.php                singleton; owns the components; get_locations_manager()/get_conditions_manager()/get_document()
   ├─ LocationsManager.php      locations registry, do_location(), template_include, asset enqueue
   ├─ ConditionsManager.php     option-cache read, condition check + priority, get_documents_for_location()
   ├─ ConditionsCache.php       read/regenerate wp_options.elementor_pro_theme_builder_conditions
   ├─ Conditions/
   │  ├─ ConditionBase.php      abstract: get_name/get_type/get_priority/check/get_sub_conditions
   │  ├─ General.php  Archive.php  Singular.php
   │  ├─ Search.php   Date.php     Author.php   Taxonomy.php   PostTypeArchive.php
   │  ├─ PostType.php ChildOf.php  AnyChildOf.php  InTaxonomy.php  InSubTerm.php
   │  ├─ ByAuthor.php PostTypeByAuthor.php  FrontPage.php  NotFound404.php
   │  └─ ChildOfTerm.php  AnyChildOfTerm.php
   ├─ Documents/
   │  ├─ ThemeDocument.php          extends \Elementor\Modules\Library\Documents\Library_Document
   │  ├─ ThemeSectionDocument.php   ├─ SectionDocument.php
   │  ├─ HeaderFooterBase.php       ├─ HeaderDocument.php  FooterDocument.php
   │  ├─ ThemePageDocument.php      ├─ ArchiveSingleBase.php
   │  ├─ SingleBase.php             ├─ SingleDocument.php  SinglePostDocument.php  SinglePageDocument.php
   │  ├─ ArchiveDocument.php        ├─ SearchResultsDocument.php
   │  └─ Error404Document.php
   └─ api.php                       elementor_theme_do_location() / elementor_location_exits()
```

Conditions we can legitimately skip for parity on this site (no matching content exists): `AnyChildOfTerm`, `ChildOfTerm`, `Date`, `Author`, `PostTypeArchive` beyond `post`. **Build them anyway** — they are 20 lines each and the alternative is a silent behaviour change the day someone adds a tag or a CPT. `Author`/`Date` in particular are reachable on a stock WP install (`/author/x/`, `/2026/`) and today resolve to template 8559 via the `include/archive` condition; omitting them changes nothing today but omitting `Archive` would.

### 5.2 Hooks and priorities — copy Pro's exactly

| Hook | Priority | Handler | Notes |
|---|---|---|---|
| `elementor/documents/register` | 10 | `Module::register_documents()` | register all 9 types; must run before anything calls `documents->get()` |
| `wp_loaded` | 10 | `ConditionsManager::register_conditions()` | after CPTs/taxonomies exist; then `do_action( 'elementor/theme/register_conditions', $this )` |
| `template_redirect` | 10 | `LocationsManager::register_locations()` | fires `elementor/theme/register_locations` **once**; guard with `did_action()` |
| `template_include` | **11** | `LocationsManager::template_include()` | must stay 11 (Pro's comment: "after WooCommerce") |
| `wp_enqueue_scripts` | 10 | `LocationsManager::enqueue_styles()` | skip entirely when in preview mode, as Pro does (`locations-manager.php:38-40`) |
| `pre_handle_404` | 10, 2 args | `LocationsManager::should_allow_pagination_on_single_templates()` | can be deferred; see §8 |
| `the_content` | `9999999` | `LocationsManager::builder_wrapper()` | editor/preview nicety; low priority to build |
| `elementor/document/wrapper_attributes` | 10, 2 args | `Module::add_document_attributes()` | **site-wide, not theme-only** — required for `data-elementor-post-type` on every Elementor wrapper |
| `elementor/theme/{location}` | 5 | per-location closure from `register_location()` | keep for API completeness |

The function definitions in `api.php` must be loaded early enough to be visible to `themes/tecnologia/header.php` (which runs after `template_redirect`) **and** to `is_elementor_pro_active()` calls that happen earlier. Load them from the plugin bootstrap at `plugins_loaded` priority 15 (where `PieCyfer\Core\Plugin` already boots), guarded:

```php
if ( ! function_exists( 'elementor_theme_do_location' ) ) { require __DIR__ . '/src/ThemeBuilder/api.php'; }
```

The guard is mandatory: during the transition Pro is still active and a bare `function elementor_theme_do_location` would be a redeclare fatal.

**Registration-priority note.** `STATUS.md` records the "registration-priority trap" (VamTam's companion plugin re-registers six widgets at priority 100 on the widget hook). That trap does **not** apply here — nothing else registers document types, locations or conditions. But `piecyfer-core`'s own `elementor/widgets/register` priority still needs raising above 100 for `nav-menu`/`posts`/`archive-posts`, and **`archive-posts` is a prerequisite for archive template 8559 to render anything at all**.

### 5.3 Document types — the part that must be exact

`Documents_Manager::get_doc_type_by_id()` (`elementor/core/documents-manager.php:759-776`) reads `_elementor_template_type` and looks it up in the registered types; unknown types fall through to `get_document_type( $type, 'post' )` (`:305-317`) and become the `post` document. So the type strings must match the DB exactly:

`section`, `header`, `footer`, `single`, `single-post`, `single-page`, `archive`, `search-results`, `error-404`.

Per class, only these things actually affect front-end output — everything else in Pro's document classes is editor UI:

| Class | `get_type()` | `get_name()` | `location` property | `get_css_wrapper_selector()` | body class | extra container classes |
|---|---|---|---|---|---|---|
| `HeaderDocument` | `header` | `header` | `header` | `.elementor-{id}` | — | — |
| `FooterDocument` | `footer` | `footer` | `footer` | `.elementor-{id}` | — | — |
| `SinglePostDocument` | `single-post` | `single-post` | `single` | `body.elementor-page-{id}` | `elementor-page-{id}` | `get_post_class()` when `is_singular()` |
| `SinglePageDocument` | `single-page` | `single-page` | `single` | `body.elementor-page-{id}` | same | same |
| `SingleDocument` | `single` | `single` | `single` | `body.elementor-page-{id}` | same | same |
| `Error404Document` | `error-404` | `error-404` | `single` | `body.elementor-page-{id}` | same | none (not `is_singular()`) |
| `ArchiveDocument` | `archive` | `archive` | `archive` | `body.elementor-page-{id}` | `elementor-page-{id}` | — |
| `SearchResultsDocument` | `search-results` | `search-results` | `archive` | `body.elementor-page-{id}` | same | — |
| `SectionDocument` | `section` | `section` | from `_elementor_location` meta | `''` | — | — |

`ThemeDocument::get_location()` must reproduce `theme-document.php:649-656` — the static `location` property first, falling back to the `_elementor_location` postmeta (that fallback is what makes `section` documents placeable).

Properties to set on `ThemeDocument::get_properties()` so the admin templates list and the library keep working: `admin_tab_group => 'theme'` (core reads it at `elementor/includes/template-library/sources/local.php:1704`), `support_kit => true`, `support_conditions => true`. `ThemePageDocument` adds `support_wp_page_templates => true`. `SectionDocument` overrides `admin_tab_group => 'library'` (`section.php:32`). `Library_Document::get_properties()` already supplies `show_in_library`, `register_type` and `cpt => [ elementor_library ]` (`elementor/modules/library/documents/library-document.php:41-50`).

`ThemeDocument::print_content()`, `get_container_attributes()` and `print_elements_with_wrapper()` are near-verbatim ports of `theme-document.php:165-175`, `:185-195` and `:395-415`.

### 5.4 Condition resolution — port, do not reinvent

Port `get_location_templates()` (`conditions-manager.php:326-412`) line-for-line, keeping:

- the option-cache read (do **not** switch to a `WP_Query` over `_elementor_conditions` — the option's key order is the tie-breaker),
- the `'publish' !== get_post_status()` skip,
- **excludes applied after all includes**,
- `asort()` (stable in PHP ≥ 8.0; `piecyfer-core` already requires PHP 8.0),
- `get_condition_priority()` verbatim including the `-10 / -10 / -5` arithmetic,
- the `?theme_template_id=` URL override and the "current post is itself a document for this location" short-circuit (`:414-456`) — the second one is what makes the Elementor editor preview render the right template,
- the per-request `$location_cache` memo.

Keep the filters, because they are cheap and someone may already rely on them:
`elementor/theme/get_location_templates/template_id`, `elementor/theme/get_location_templates/condition_sub_id`, `elementor/theme/need_override_location`.

Also port `ConditionsCache::regenerate()` (`conditions-cache.php:94-142`) and hook `wp_trash_post` / `untrashed_post` (`conditions-manager.php:35-36`) so the cache stays honest, plus a one-shot regenerate on plugin activation.

**Verification harness for this piece:** write a throwaway script that, for a list of URLs, prints `LocationsManager::get_documents_for_location()` for each of `header`/`footer`/`single`/`archive` under Pro and under the replacement, and diff. That is a far stronger check than pixels, and it is cheap.

### 5.5 `elementor_theme_do_location()` shim

```php
// src/ThemeBuilder/api.php  — mirrors elementor-pro/modules/theme-builder/api.php:9-21
function elementor_theme_do_location( $location ) {
    return \PieCyfer\Core\ThemeBuilder\Module::instance()->get_locations_manager()->do_location( $location );
}
function elementor_location_exits( $location, $check_match = false ) {
    return \PieCyfer\Core\ThemeBuilder\Module::instance()->get_locations_manager()->location_exits( $location, $check_match );
}
```

Note the typo `exits` is part of the contract — `themes/tecnologia/footer.php:18` calls it by that name.

`LocationsManager` must expose the full surface the theme touches on the object passed to `elementor/theme/register_locations`, untyped:
`register_all_core_location()`, `register_location( $location, $args )`, `register_core_location( $location, $args )`, `get_core_locations()`, `get_locations( $filter_args )`, `get_location( $location )`, `do_location()`, `location_exits()`, plus the manual queue (`add_doc_to_location`, `remove_doc_from_location`, `skip_doc_in_location`, `is_printed`, `set_is_printed`) that the Popup work item will need.

`register_location()` defaults must match `locations-manager.php:465-471` exactly (`label => $location`, `multiple => false`, `public => true`, `edit_in_content => true`, `hook => 'elementor/theme/' . $location`), and `set_core_locations()` must match `:517-545` including `archive`'s `'overwrite' => true` — that flag is what routes archives and search through `header-footer.php`.

### 5.6 The Pro-namespaced compatibility class (transition only)

Required because `elementor-bridge.php:738` and `:851` dereference `ElementorPro\Modules\ThemeBuilder\Module` behind a `function_exists( 'elementor_theme_do_location' )` guard that our shim satisfies. Load `compat/elementor-pro-theme-builder-shim.php` only when Pro is genuinely gone:

```php
if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module', false )
     && ! file_exists( WP_PLUGIN_DIR . '/elementor-pro/elementor-pro.php' ) ) {
    require_once __DIR__ . '/compat/elementor-pro-theme-builder-shim.php';
}
```

The file declares `namespace ElementorPro\Modules\ThemeBuilder; class Module { public static function instance() … public function get_conditions_manager() … public function get_locations_manager() … public function get_document( $id ) … }`, each method delegating to `PieCyfer\Core\ThemeBuilder\Module`. `get_documents_for_location()` must return real `\Elementor\Core\Base\Document` objects because `is_title_present_in_doc_locations()` calls `$doc->get_elements_data()` on them.

This class is a **wart with an expiry date**: delete it the day `piecyfer-theme` replaces `tecnologia`. Say so in a header comment, and add it to the Phase 5 checklist.

### 5.7 Keeping the Elementor editor working — honest assessment

| Capability | After Pro is deleted | Why |
|---|---|---|
| Open template 171/1273/8502/… in the Elementor editor | ✅ works | `Documents_Manager` finds our registered type; the editor is free-core |
| Correct panel title, correct wrapper in the preview iframe | ✅ works | comes from our document classes |
| Editing widgets, saving, publishing, revisions, autosave | ✅ works | free-core `Document`/`Library_Document` |
| Templates listed under **Templates → Saved Templates** with a *Theme* tab group | ✅ works | `admin_tab_group` is a core concept (`local.php:1704`) |
| Direct edit URL `post.php?post=171&action=elementor` | ✅ works | |
| **Display Conditions panel** (the "Publish → Conditions" popup) | ❌ **gone** | Pro-only: control `Conditions_Repeater` (`classes/conditions-repeater.php`, registered `module.php:146`), panel view `views/panel-template.php` (`module.php:293`), the `theme_builder` block in `elementor/document/config` (`module.php:117-143`), and the AJAX actions `pro_theme_builder_save_conditions` / `…_check_conflicts` (`conditions-manager.php:92-93`). All of it is Pro editor JS we do not have. |
| **Theme Builder app** (`/wp-admin/admin.php?page=elementor-app#/site-editor/…`) | ❌ **gone** | `get_site_editor_config()` / `support_site_editor` exist only in Pro (`theme-document.php:49-68`); grep of Elementor free finds one unrelated hit |
| "Add New Theme Template" dialogs, location/post-type selects, Instances admin column | ❌ gone | `module.php:170-244`, `conditions-manager.php:61-84` |
| "Preview Dynamic Content as" | ❌ gone unless `PreviewManager` is ported | `theme-document.php:280-347`, `preview-manager.php` |

**Practical mitigation for conditions editing:** conditions live in one postmeta key plus one option. Ship a small `piecyfer-core` metabox or a WP-Admin screen that edits `_elementor_conditions` as a repeating `type/name/sub_name/sub_id` row set and calls `ConditionsCache::regenerate()` on save. That is perhaps 150 lines and restores the only genuinely operational capability lost. The Theme Builder *app* is not worth rebuilding — the site has ten templates and they change once a year.

Do **not** promise the client that the editor experience is identical. It is identical for editing template *content*; it is not identical for editing template *routing*.

### 5.8 Things that will silently change unless handled

| Item | Where it comes from today | Action |
|---|---|---|
| `data-elementor-post-type` on **every** Elementor wrapper (`wp-page`, `wp-post`, `elementskit_content`, all theme docs) | `module.php:427-431` | re-add the `elementor/document/wrapper_attributes` filter globally |
| `elementor-pro-active` **body class** | `themes/tecnologia/vamtam/helpers/template.php:70` ← `is_elementor_pro_active()` ← `function_exists('elementor_theme_do_location')` | our shim keeps it **on the front end**; unchanged. Confirm with the pixel harness. |
| `elementor-template-full-width` body class on archives/search | `elementor/modules/page-templates/templates/header-footer.php:7`, reached only via the `overwrite` path | preserve `archive`'s `overwrite => true` |
| `[elementor-template id="8519"]` inside single posts | Pro `modules/library/classes/shortcode.php:71` | must be re-registered — §7.2 |
| Stylesheet ordering in `<head>` | `enqueue_styles()` order at `locations-manager.php:142-150` | port verbatim |
| `_elementor_page_assets` opt-in loading | `locations-manager.php:134-137` | port verbatim |
| Sidebars appearing in wp-admin | `framework.php:418` + the `is_admin()` branch of `is_elementor_pro_active()` | accept for now; fix in `piecyfer-theme` |

---

## 6. What genuinely cannot be reproduced without Pro

Stated plainly, no hedging:

1. **The Theme Builder app and the display-conditions editor UI.** These are Pro React/Backbone bundles. We can rebuild the *data* editing (§5.7) but not the UI. Accept the loss.
2. **`Conditions_Repeater` control and the conditions AJAX endpoints.** Same reason.
3. **`elementor/document/config` `theme_builder` payload.** Pro's editor JS is the only consumer; nothing else breaks by its absence.
4. **The WooCommerce theme-builder conditions** (`elementor-pro/modules/woocommerce/module.php:1401`, `:1411`). WooCommerce is not installed here, so this is moot — but it means the replacement is *not* a drop-in for a Woo site.
5. **`Pagination_Trait::is_valid_pagination()`** used by `should_allow_pagination_on_single_templates`. Portable, but it depends on Pro's Posts module internals; the honest position is that we port it alongside the `archive-posts` widget in step 4j, not in 4i.
6. **Uncertain:** whether `In_Taxonomy::check()` with an empty `sub_id` (`has_term( 0, 'category' )`) can ever return true on some WP version. I did not test this against WP core's `is_object_in_term()`. It does not matter for parity on this site (condition priority 25 vs. the 30 already matched by the same template 8502), but a faithful port should copy the code rather than "clean it up".

---

## 7. Dependencies outside the theme-builder module that this site needs

### 7.1 Preview manager
Editor-only (§3.7). Port last, or not at all.

### 7.2 `[elementor-template]` shortcode — **required, and not part of theme-builder**
`elementor-pro/modules/library/classes/shortcode.php:51-72`. Single posts embed `[elementor-template id="8519"]`. The whole implementation is:

```php
return Plugin::elementor()->frontend->get_builder_content_for_display( $attributes['id'], $include_css );
```

`piecyfer-core` already calls exactly that from `src/Widgets/Template.php:137`, so this is ~20 lines. **Add it to step 4i's scope** — without it a `[elementor-template …]` string prints literally on every single post.

### 7.3 `archive-posts` widget
Template 8559 and 8711 are empty shells without it. This is step **4j** in `02-WIDGET-REBUILD-SPEC.md:364` and it is a hard prerequisite for the archive/search locations to be *useful*, though not for them to *render*.

### 7.4 Popup location
Popup 7718 has **no** `_elementor_conditions` (verified — its meta has `_elementor_popup_display_settings = ['triggers'=>[], 'timing'=>[]]`). It reaches the page because Pro's Popup module injects it into the manual queue:

```php
// elementor-pro/modules/popup/module.php:128
$theme_builder->get_locations_manager()->add_doc_to_location( Document::get_property( 'location' ), $popup_id );
// :148  printed from wp_footer
elementor_theme_do_location( 'popup' );
```

So `LocationsManager` must ship the manual-queue API in 4i even though the Popup work is 4l. Its location args are `[ 'label' => 'Popup', 'multiple' => true, 'public' => false, 'edit_in_content' => false ]` (`popup/module.php:135-146`). Caution: `Module::register_location()` there **type-hints Pro's `Locations_Manager`** — our replacement Popup module must drop that type hint.

---

## 8. Risks and build ordering

### 8.1 Dependency graph

```
Document types  ──┬──> LocationsManager ──> api.php shim ──> theme call sites work
                  │
ConditionsCache ──┴──> ConditionsManager ──> LocationsManager
                                                │
                          elementor-template shortcode (independent, small)
                                                │
                          archive-posts widget (4j) ──> archives/search actually show posts
```

Nothing can be tested end-to-end until document types + conditions + locations are all in, because `do_location()` needs a resolved ID and a correctly-typed document. Build them in one branch, not three.

### 8.2 Safest sequence

| Step | Action | Site still renders? | How to verify |
|---|---|---|---|
| **1** | Register the 9 document types **while Pro is still active**, at a hook priority that loses to Pro (Pro registers on `elementor/documents/register` @10; register at @9 and let Pro overwrite, or gate on `! class_exists( Pro )`). Purpose: prove the classes load and the autoloader path is right. | yes, unchanged | pixel harness = zero diff |
| **2** | Build `ConditionsCache` + `ConditionsManager` + the condition classes. Do **not** hook anything. Add a CLI/dev script that diffs our `get_documents_for_location()` against Pro's for every URL in the pixel baseline **plus** `/category/web-apps/`, `/?s=crm`, `/privacy-policy/`, `/terms-conditions/`, a single post, and a 404. | yes, unchanged | the diff script must be empty |
| **3** | Build `LocationsManager` (registry, `do_location`, `template_include`, `enqueue_styles`) — still not hooked. | yes, unchanged | — |
| **4** | Port the `[elementor-template]` shortcode behind `! shortcode_exists( 'elementor-template' )`. | yes | single-post HTML unchanged |
| **5** | Add the `elementor/document/wrapper_attributes` filter behind `! class_exists( Pro\ThemeBuilder\Module )`. | yes | — |
| **6** | **Cutover, one commit:** deactivate Pro; `piecyfer-core` now registers document types unconditionally, defines `api.php`, hooks `template_redirect` / `template_include` / `wp_enqueue_scripts`, and loads `compat/elementor-pro-theme-builder-shim.php`. | this is the risk point | full pixel harness including the six new URLs |
| **7** | Delete `elementor-pro/` from disk. | yes | re-run harness; grep the whole tree for `ElementorPro\` to catch remaining references |
| **8** | Later, in Phase 5: `piecyfer-theme` replaces `tecnologia`, at which point delete `compat/elementor-pro-theme-builder-shim.php`. | | |

### 8.3 Specific risks

| Risk | Severity | Mitigation |
|---|---|---|
| Theme fatals on `Theme_Builder_Module::instance()` (`elementor-bridge.php:738`, `:851`) the moment our `elementor_theme_do_location()` exists and Pro is gone | **fatal, site-down** | §5.6 compat class; test by renaming `elementor-pro/` before deleting it |
| Document types not registered → header/footer CSS regenerates under `body.elementor-page-{id}` instead of `.elementor-{id}` | **high** — silent, and only visible after a CSS-cache flush | §5.3; after cutover force `Plugin::$instance->files_manager->clear_cache()` and re-run the harness so the regenerated CSS is what gets compared |
| `footer.php:18` has no fallback — any bug that makes `elementor_location_exits('footer')` return false removes the footer entirely | high | keep `location_exits()` semantics identical (registered ≠ matched) |
| Archive/search/404 are barely covered by the pixel baseline | **high** — `_project/snapshots/baseline/html/` has 39 pages, of which only `this-url-does-not-exist-404-test.html` exercises a theme-builder body template; there is **no** archive or search capture | **Extend the harness before step 6**: add `/category/web-apps/`, `/category/erp/`, `/?s=crm`, `/privacy-policy/`, `/terms-conditions/`, and re-baseline while Pro is still active |
| Tie-break order changes if we regenerate the conditions option with a different query | medium | read the option as-is; regenerate only on explicit action, and diff the option before/after |
| Stylesheet order changes in `<head>` | medium | port `enqueue_styles()` verbatim including `frontend->enqueue_styles()` before the per-post CSS |
| `archive-posts` (4j) not yet ported when locations go live | medium | archives will render the template chrome with an empty listing. Either sequence 4j before the cutover, or accept a temporary regression on `/category/*` and `/?s=` only |
| `elementor-pro-active` body class flips off | medium | it will not, on the front end — it keys off `function_exists('elementor_theme_do_location')`. Verify explicitly in the harness diff. |
| Someone "fixes" the dead conditions (6126, term 22/46/47, page 2055) | low but corrupting | §2.4 exists to prevent this |
| Popup 7718 disappears at cutover | medium | it depends on Pro's Popup module (4l), not on theme-builder. Either keep the popup out of scope and accept it vanishing at step 7, or sequence 4l with 4i. **Decide this explicitly before step 6** — the popup is on every page, so the harness will fail loudly. |

### 8.4 Open questions to settle before writing code

1. Is the **popup** in scope for the same cutover as the theme builder? (It appears on every captured page; if not, every baseline comparison fails at step 6.)
2. Do we port **`archive-posts`** before or after the cutover?
3. Do we build the **conditions metabox** (§5.7) now, or accept that template routing becomes a database edit until `piecyfer-theme` lands?
4. Confirm nobody needs the **Theme Builder app**. Once Pro is gone the menu item disappears.
