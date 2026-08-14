# `posts` and `archive-posts` — build specification

**Scope:** the 12 `posts` and 3 `archive-posts` instances in `_elementor_data`, the Elementor Pro
classes behind them, and the VamTam skin layered on top.

**Versions this was read against:** `elementor` 3.25.10, `elementor-pro` 3.25.4,
`vamtam-elementor-integration-tecnologia` 1.0.10, theme `tecnologia`.

**Evidence:** every claim about internals carries a file path and line number. Every claim about
saved data comes from `php _project/scripts/dump-eldata.php` (76 documents, revisions excluded).
Every claim about rendered output comes from either `_project/snapshots/baseline/html/` or a live
`curl` against `http://localhost/piecyfer/` taken while writing this.

---

## 0. The short version

| | |
|---|---|
| Skins actually used | **`vamtam_classic` on all 15 instances. Nothing else.** |
| Live instances (render on a public URL) | 5 `posts` + 3 `archive-posts` = **8** |
| Dormant instances (unreferenced library templates) | **7** |
| Distinct saved keys, `posts` | **191** (91 `vamtam_classic_*`, 46 dead other-skin, 39 widget-level, 12 Elementor-common, 3 ElementsKit) |
| Distinct saved keys, `archive-posts` | **119** (67 `vamtam_classic_*`, 9 dead other-skin, 34 widget-level, 6 common, 3 ElementsKit) |
| Biggest markup difference vs Pro | VamTam moves `.elementor-post__meta-data` **out of** `.elementor-post__text` |
| Biggest hidden dependency | **Pro's JavaScript does not run for `vamtam_classic` at all.** Load-more, image-fit and masonry come entirely from `vamtam-posts-base.js` |
| Biggest trap | Getting the skin id wrong makes all 8 live widgets render **nothing at all** — not a fallback, not a placeholder, an empty `<div>` |

---

## 1. The real class hierarchy

### 1.1 Elementor Pro

```
Elementor\Widget_Base
└── ElementorPro\Base\Base_Widget
    └── ElementorPro\Modules\Posts\Widgets\Posts_Base      (abstract)
        ├── ElementorPro\Modules\Posts\Widgets\Posts               name: posts
        ├── ElementorPro\Modules\Posts\Widgets\Portfolio           name: portfolio    (unused here)
        └── ElementorPro\Modules\ThemeBuilder\Widgets\Archive_Posts name: archive-posts
```

| Class | File | Lines | Notes |
|---|---|---|---|
| `Posts_Base` | `elementor-pro/modules/posts/widgets/posts-base.php` | 924 | `use Button_Widget_Trait, Pagination_Trait`. `render()` is **empty** (L47). All pagination controls live here (L164–657). |
| `Posts` | `elementor-pro/modules/posts/widgets/posts.php` | 134 | `get_name()` L21; `register_skins()` L55–59; `query_posts()` L81–91; `get_posts_per_page_value()` L104–106 reads the value **from the current skin**, not the widget. |
| `Archive_Posts` | `elementor-pro/modules/theme-builder/widgets/archive-posts.php` | 165 | `register_skins()` L59–63; `query_posts()` L141–164 reuses the **global** `$wp_query`; `register_controls()` L65–78 overrides `pagination_type` default to `numbers`. |

Skins:

```
Elementor\Sub_Controls_Stack
└── Elementor\Skin_Base                                   includes/base/skin-base.php
    └── ElementorPro\Modules\Posts\Skins\Skin_Base        (abstract, 1409 lines)
        ├── Skin_Classic                       id: classic                skin-classic.php:19
        │   ├── Skin_Full_Content               id: full_content           skin-full-content.php:11
        │   │   (+ trait Skin_Content_Base)
        │   ├── Posts_Archive_Skin_Classic      id: archive_classic        posts-archive-skin-classic.php:18
        │   │   (+ trait Posts_Archive_Skin_Base)
        │   └── ★ Skin_Vamtam_Posts_Classic     id: vamtam_classic         vamtam .../posts-base.php:311
        │        (+ trait Vamtam_PostsBase_Classic_Skin_Overrides)
        ├── Skin_Cards                          id: cards                  skin-cards.php:24
        │   └── Posts_Archive_Skin_Cards        id: archive_cards          posts-archive-skin-cards.php:19
        └── (Posts_Archive_Skin_Full_Content    id: archive_full_content   posts-archive-skin-full-content.php:15
             extends Skin_Full_Content)

★ Skin_Vamtam_Archive_Posts_Classic  id: vamtam_classic   vamtam .../posts-base.php:315
   extends Posts_Archive_Skin_Classic  (+ the same VamTam trait)
```

Two things in that tree are easy to miss and both matter:

1. **`Posts_Archive_Skin_Classic::get_container_class()`
   (`posts-archive-skin-classic.php:26-29`) hard-codes `elementor-posts--skin-classic`.**
   It does *not* derive from `get_id()`. Because VamTam's archive skin inherits it, the archive
   widget's container is `elementor-posts--skin-classic` while the `posts` widget's container is
   `elementor-posts--skin-vamtam_classic` (from `Skin_Base::get_container_class()`,
   `skin-base.php:946-948`). Verified live:

   | URL | container class |
   |---|---|
   | `/blogs/` | `elementor-posts-container elementor-posts elementor-posts--skin-vamtam_classic elementor-grid elementor-has-item-ratio` |
   | `/category/erp/`, `/?s=software` | `elementor-posts-container elementor-posts elementor-posts--skin-classic elementor-grid` |

   Same skin id, different container class. Reproduce that asymmetry exactly.

2. **`Posts_Archive_Skin_Classic::_register_controls_actions()` (L13-16) does not call
   `parent::` and does not re-add `Skin_Classic`'s
   `.../classic_section_design_layout/after_section_end` hook.** So the archive skins get **no Box
   style section from Pro at all** — which is precisely why VamTam re-implements one
   (`vamtam .../archive-posts.php:17-213`, gated on theme support
   `archive-posts.classic--box-section`).

### 1.2 Which skins this site actually uses

From the dump — all 15 instances, without exception:

```
archive-posts doc=6126   Case Studies Archives   el=471f2f6   _skin='vamtam_classic'
posts         doc=8502   Blog Post Template      el=faf5013   _skin='vamtam_classic'
archive-posts doc=8559   Blog Posts Archives     el=26a6e002  _skin='vamtam_classic'
archive-posts doc=8711   Search Results          el=8115b17   _skin='vamtam_classic'
posts         doc=996210 Homepage Blogs (Export) el=726f8d13  _skin='vamtam_classic'
posts         doc=996219 blog page               el=19a0e2f3  _skin='vamtam_classic'
posts         doc=996219 blog page               el=7978363   _skin='vamtam_classic'
posts         doc=996219 blog page               el=65d16a9   _skin='vamtam_classic'
posts         doc=996222 blogs full page         el=4377c655  _skin='vamtam_classic'
posts         doc=996222 blogs full page         el=29368807  _skin='vamtam_classic'
posts         doc=996222 blogs full page         el=5377f095  _skin='vamtam_classic'
posts         doc=93     Blogs                   el=0debbbe   _skin='vamtam_classic'
posts         doc=93     Blogs                   el=53acb3b   _skin='vamtam_classic'
posts         doc=93     Blogs                   el=77d3dd5   _skin='vamtam_classic'
posts         doc=146    Home                    el=f57a5e5   _skin='vamtam_classic'
```

Corroborated by rendered markup: every posts widget in `_project/snapshots/baseline/html/` emits
`data-widget_type="posts.vamtam_classic"` or `archive-posts.vamtam_classic`. **Zero** instances of
`.classic`, `.cards`, `.full_content` or their archive equivalents anywhere on the site.

**`classic`, `cards`, `full_content`, `archive_cards`, `archive_full_content` are dead weight for
this site.** They are still worth *registering* (see §9), but they need no render path and no
verification.

### 1.3 Live vs dormant instances

`elementor_library` documents 996210 (`Homepage Blogs (Export)`), 996219 (`blog page`) and
996222 (`blogs full page`) are published library templates that **nothing references** — I searched
every other document's `_elementor_data` for their ids and found no `template` widget, no shortcode,
no theme-builder condition. 996219 and 996222 are byte-for-byte duplicates of the three widgets on
page 93. They contain 7 of the 12 `posts` instances.

| Document | Type | Renders at | Instances |
|---|---|---|---|
| 93 `Blogs` | page | `/blogs/`, `/blogs/2/`, `/blogs/page/2/` | 3 × `posts` |
| 146 `Home` | page | `/`, `/home/` | 1 × `posts` |
| 8502 `Blog Post Template` | theme-builder single | every `/post-slug/` | 1 × `posts` (related) |
| 8559 `Blog Posts Archives` | theme-builder archive | `/category/*/`, `/author/*/` | 1 × `archive-posts` |
| 8711 `Search Results` | theme-builder search | `/?s=…` | 1 × `archive-posts` |
| 6126 `Case Studies Archives` | theme-builder archive | a CPT archive; **no harness URL covers it** | 1 × `archive-posts` |
| 996210 / 996219 / 996222 | library | nowhere | 7 × `posts` |

The 7 dormant instances still constrain us: their saved keys must survive, because someone can
re-point a template at them. They just cannot be pixel-verified.

**6126 has no harness coverage.** Its display condition should be checked before sign-off; if it
resolves to a reachable archive, add that URL to `urls.js`.

---

## 2. How Elementor skins work, mechanically

### 2.1 Registration

`Widget_Base::register_skins()` calls `add_skin()` (`widget-base.php:911-913`), which delegates to
`Skins_Manager::add_skin()` (`includes/managers/skins.php:43-53`):

```php
$this->_skins[ $widget_name ][ $skin->get_id() ] = $skin;
```

**Keyed by widget *name*, not by widget instance.** This is the single most important mechanical
fact for this port, and it is what VamTam exploits: `Vamtam_Widget_Posts::register_skins()`
(`vamtam .../posts.php:77-81`) adds *only* the VamTam skin and deliberately does not call
`parent::register_skins()`, with the comment "Skins (and their controls) are already registered in
the parent class". They are — in the shared `$_skins['posts']` bucket, populated when Pro's own
`Posts` widget was constructed at priority 10. Calling `parent::` would re-register Pro's skins and
their controls a second time and corrupt the control stack.

The consequence for us: **once Pro is removed, that bucket is empty and we own all of it.**

### 2.2 The `_skin` control

`Widget_Base::start_controls_section()` (`widget-base.php:276-284`) calls `register_skin_control()`
on the **first** section only. `register_skin_control()` (`widget-base.php:290-330`) builds the
options from `get_skins()` and sets `'default' => array_shift( array_keys( $skin_options ) )` — i.e.
**the first-registered skin's id is the default**. With ≤1 skin the control becomes `HIDDEN`.

Two consequences:

* The first `start_controls_section()` call in the widget determines where `_skin` sits in the
  stack. Pro's is `section_layout`, opened in `Posts_Base::register_controls()` (L858-868).
* Registration *order* inside `register_skins()` sets the default. On this site every instance has
  `_skin` saved explicitly, so the default never applies at render time — but it does apply to any
  new widget dropped in the editor, and it is what a reviewer will compare against.

### 2.3 Where the prefix comes from

`Skin_Base::get_control_id()` (`elementor/includes/base/skin-base.php:111-114`):

```php
protected function get_control_id( $control_base_id ) {
    $skin_id = str_replace( '-', '_', $this->get_id() );
    return $skin_id . '_' . $control_base_id;
}
```

`Sub_Controls_Stack` routes every registration method through it
(`sub-controls-stack.php:85-87, 100-102, 113-115, 130-133, 146-148, 188-190`):

| Skin call | What lands in the widget's stack |
|---|---|
| `$this->add_control( 'meta_data', … )` | `vamtam_classic_meta_data` |
| `$this->add_responsive_control( 'columns', … )` | `vamtam_classic_columns`, `_columns_tablet`, `_columns_mobile` |
| `$this->add_group_control( Typography, [ 'name' => 'title_typography' ] )` | `vamtam_classic_title_typography_typography`, `…_font_family`, `…_font_size`, `…_font_size_tablet`, … |
| `$this->start_controls_section( 'section_design_box' )` | section id `vamtam_classic_section_design_box` |

**The section id is prefixed too.** That is why VamTam's injection hook reads
`elementor/element/posts/vamtam_classic_section_design_box/before_section_end`
(`vamtam .../posts-base.php:362`). Section ids are part of the public API; renaming one silently
drops third-party injections, exactly as recorded for the Phase 4c widgets in `STATUS.md`.

`Skin_Base` also stamps `$args['condition']['_skin'] = $this->get_id()` onto every control, tab,
tabs-group, section and group-control it registers (`skin-base.php:147, 167, 186, 204, 220, 235,
254`). Reading is symmetric: `get_instance_value( 'meta_data' )` (`skin-base.php:130-133`) does
`$this->parent->get_settings( 'vamtam_classic_meta_data' )`.

### 2.4 Selection and render dispatch

```php
// widget-base.php:948-964
public function get_current_skin_id() { return $this->get_settings( '_skin' ); }
public function get_current_skin()    { return $this->get_skin( $this->get_current_skin_id() ); }
```

`get_skin()` (L929-…) returns `false` when the id is not in the bucket. `render_content()`
(`widget-base.php:616-668`) then does:

```php
$skin = $this->get_current_skin();
if ( $skin ) { $skin->set_parent( $this ); $skin->render_by_mode(); }
else         { $this->render_by_mode(); }
$widget_content = ob_get_clean();
if ( empty( $widget_content ) ) { return; }
```

`Posts_Base::render()` is empty (`posts-base.php:47`).

**So: unknown skin id ⇒ `render()` produces nothing ⇒ `$widget_content` is empty ⇒ Elementor
returns before even printing `<div class="elementor-widget-container">`.** The widget vanishes
silently. No error, no placeholder, no console warning. This is the failure mode to design against.

`data-widget_type` is written at `widget-base.php:509` as
`get_name() . '.' . ( $settings['_skin'] ?: 'default' )` — the string the front-end JS handler
registry keys on (§6, §7).

### 2.5 What happens to saved settings when a skin id changes or disappears

Three separate questions, three different answers:

**(a) Rendering.** Values whose control no longer exists are simply never read. Nothing errors.
The widget falls back to whatever the surviving controls' defaults produce — or renders nothing at
all if `_skin` itself no longer resolves (§2.4).

**(b) Persistence across an editor save.** Unknown keys **are preserved**. This is not inference —
this site is the proof. `vamtam_classic_use_read_more_theme_style` (9 instances) and
`classic_title_underline_anim` (3 instances) have **no registering control anywhere in
`wp-content`** (`grep -rn 'use_read_more_theme_style\|title_underline_anim' wp-content/` matches only
`themes/tecnologia/samples/content.xml`, the demo import). They are leftovers from an earlier VamTam
theme, and they have survived every save since. Likewise the 46 orphan `classic_*` keys from before
someone switched the skin. `Controls_Stack::sanitize_settings()`
(`controls-stack.php:2473-2510`) only strips *dynamic-tag* references for tags that no longer exist —
it never touches unknown control keys.

**(c) Global values.** `__globals__` is a separate map keyed by control id
(e.g. `"vamtam_classic_title_typography_typography": "globals/typography?id=vamtam_h5"`). If the
control id changes, the global reference orphans and the typography silently reverts to nothing —
and because the concrete sub-keys are all empty strings (§4.4), **there is no fallback value to
fall back to.** This is the quiet way to break every heading in a blog listing.

Practical rule: **`vamtam_classic` is now a permanent part of this site's data schema.** It is not
a VamTam implementation detail we get to clean up. Renaming it to `piecyfer_classic` would require a
data migration across 15 instances × ~90 keys × the `__globals__` maps, and is not worth it.

---

## 3. What VamTam's skin actually adds over Pro's Classic

All of it is gated on `vamtam_theme_supports()`. The theme's feature list is
`themes/tecnologia/vamtam/classes/framework.php:267-297`; the relevant flags are **all on**:

`archive-posts.classic--box-section`, `posts-base--extra-pagination-controls`,
`posts-base--load-more-masonry-fix`, `posts-base--display-categories`, `posts-base--display-tags`,
`posts-base--horizontal-layout`, `posts-base--404-handling-fix`,
`posts-base--responsive-image-position`.

The master toggle `is_widget_mod_active('posts')` / `('archive-posts')`
(`vamtam .../vamtam-elementor-utils.php:216-234`) reads kit setting `vamtam_theme_posts`. That key is
**not present** in kit 5's saved `_elementor_page_settings`, so the control default applies — and the
default is `'yes'` (`theme-site-settings.php:223, 240`). Both mods are active. Confirmed by the
`vamtam-has-theme-widget-styles` class on every posts widget in the baseline HTML
(`vamtam .../widgets/widget-base.php:10`).

> **Note a real VamTam bug while you are here.** `vamtam_theme_supports( $feature, $relation = 'OR' )`
> (`themes/tecnologia/vamtam/helpers/base.php:126`) takes a *feature* and a *relation*. Several call
> sites pass multiple feature names as separate arguments —
> `vamtam_theme_supports( 'posts-base--display-categories', 'posts-base--404-handling-fix', 'posts-base--load-more-masonry-fix' )`
> at `posts.php:51` and `:87`, and `archive-posts.php:215`. Every argument after the first is
> discarded. Those calls test **only** `posts-base--display-categories`. It happens not to matter
> here because that flag is on, but do not port the intent — port the behaviour.

### 3.1 New controls (things Pro does not have)

| Control id | Where | Type / default | Effect |
|---|---|---|---|
| `vamtam_classic_vamtam_use_hr_layout` | `posts-base.php:464-475` | switcher, `prefix_class: vamtam-has-`, return `hr-layout` | horizontal scroller |
| `vamtam_classic_vamtam_additional_cols_hint` (+`_tablet`/`_mobile`) | `posts-base.php:478-503` | slider, default 20px | `{{WRAPPER}} --vamtam-col-hint` |
| `vamtam_classic_vamtam_has_nav` | `posts-base.php:505-520` | switcher, `prefix_class: vamtam-has-`, return `nav`, default `nav` | scroller arrows |
| `vamtam_classic_vamtam_nav_pos` (+resp.) | `posts-base.php:523-546` | select, `prefix_class: vamtam-nav-pos%s-` | |
| `vamtam_classic_vamtam_nav_prev_x/_y`, `_next_x/_y` (+resp.) | `posts-base.php:568-625` | sliders → `.vamtam-nav --vamtam-nav-*` | |
| `vamtam_classic_vamtam_nav_btns_gap`, `_spacing` (+resp.) | `posts-base.php:647-672` | | |
| `vamtam_classic_vamtam_no_hide_anim` | `posts-base.php:675-691` | switcher, `prefix_class: vamtam-nav-` | |
| `vamtam_classic_vamtam_show_on_mobile` | `posts-base.php:694-709` | switcher, `prefix_class: vamtam-has-` | |
| `show_pagination_border` | `posts-base.php:20-31` | switcher, default `yes`, `prefix_class: elementor-show-pagination-border-` | widget-level, not skin-prefixed |
| `pagination_border_color` | `posts-base.php:32-44` | colour | |
| `pagination_padding` | `posts-base.php:45-62` | slider, `em` only | |
| `pagination_bg_color`, `pagination_hover_bg_color`, `pagination_active_bg_color` | `posts-base.php:91-161` | colours | **Pro has only the three foreground colours** (`posts-base.php:527-577` in Pro). VamTam tears out Pro's `pagination_colors` tabs group and rebuilds it with six controls. |
| `{archive_classic,vamtam_classic}_box_*` on **archive-posts only** | `vamtam .../archive-posts.php:30-199` | border width/radius/padding, content padding, box-shadow ×2, bg/border colour ×2, `_content_hover_color` | Pro's archive skins have no Box section at all |

Only two of the horizontal-layout controls appear in saved data — `vamtam_classic_vamtam_has_nav`
(12 instances, **always `""`**) and `vamtam_classic_vamtam_additional_cols_hint` + its
tablet/mobile siblings (12 instances, `{"unit":"px","size":-30}`). **`vamtam_use_hr_layout` is set
on zero instances**, so the entire horizontal-scroller feature — and `vamtam-hr-scrolling.js` — is
inert on this site. It is still *enqueued* (§7).

### 3.2 Changed controls (same id, different behaviour)

These use `Vamtam_Elementor_Utils::add_control_options()`
(`vamtam-elementor-utils.php:4-35` → `update_control_options()` L319), which **merges** into the
existing control's args rather than replacing (`$replace = false`, L348-359). `replace_control_options()`
(L37-39) is the replacing variant.

| Control | Change | Where |
|---|---|---|
| `vamtam_classic_meta_data` | **adds options** `vamtam-categories` → "Categories", `vamtam-tags` → "Tags" | `posts-base.php:319-336` |
| `vamtam_classic_content_padding` | **adds selector** `{{WRAPPER}} => --vamtam-content-padding: {{TOP}}… ` (Pro's `.elementor-post__text{padding:…}` is kept) | `posts-base.php:338-345` |
| `{classic,vamtam_classic}_img_border_radius` | **adds selector** `{{WRAPPER}} => --vamtam-img-border-radius: …` (Pro's `.elementor-post__thumbnail{border-radius:…}` kept) | `posts-base.php:371-399` |
| `{classic,archive_classic,vamtam_classic}_columns` | **adds selector** `{{WRAPPER}} => --vamtam-cols: {{VALUE}}`, then **replaces** `prefix_class` with `elementor-grid%s-` (a workaround for elementor#12947) | `posts-base.php:450-462` |
| Pro's `pagination_spacing`, `pagination_spacing_top`, `pagination_colors` tabs | **removed and re-added** so the rebuilt tabs sit in the right place | `posts-base.php:67-69, 167-206` |

Both the merged custom property *and* Pro's original declaration end up in the generated CSS. From
`wp-content/uploads/elementor/css/post-93.css`, widget `53acb3b`:

```css
.elementor-93 .elementor-element.elementor-element-53acb3b{
  --vamtam-cols:3; --grid-row-gap:40px;
  --vamtam-content-padding:0px 15px 0px 15px;
  --grid-column-gap:30px;
  --vamtam-img-border-radius:10px 10px 10px 10px;
}
…
.elementor-93 .elementor-element.elementor-element-53acb3b .elementor-post__text{padding:0px 15px 0px 15px;}
.elementor-93 .elementor-element.elementor-element-53acb3b .elementor-post__thumbnail{border-radius:10px 10px 10px 10px;}
```

Both must be emitted. The theme consumes the custom properties
(`themes/tecnologia/vamtam/assets/css/dist/elementor/elementor-all.css`):

```css
.elementor-widget-posts.vamtam-has-theme-widget-styles .elementor-post > .elementor-post__meta-data{
  padding: var(--vamtam-content-padding, 0)
}
.elementor-widget-posts.vamtam-has-theme-widget-styles .elementor-post .elementor-post__thumbnail__link{
  border-radius: var(--vamtam-img-border-radius); mask-image: radial-gradient(white,#000)
}
```

### 3.3 Render overrides

`trait Vamtam_PostsBase_Classic_Skin_Overrides` (`vamtam .../posts-base.php:226-308`), mixed into
both skin classes:

| Method | Line | Change vs `Skin_Base` |
|---|---|---|
| `get_id()` | 228-230 | `'vamtam_classic'` |
| `get_title()` | 232-234 | `'Classic (Vamtam)'` |
| `render_meta_data()` | 236-279 | adds `vamtam-categories` → `render_categories()` and `vamtam-tags` → `render_tags()` after Pro's five |
| `render_categories()` | 281-287 | `<div class="vamtam-post__categories"><?php the_category(', '); ?></div>` |
| `render_tags()` | 289-295 | `<div class="vamtam-post__tags"><?php the_tags('', ', '); ?></div>` |
| **`render_post()`** | 297-307 | **reorders the item** |

The reorder is the single most consequential change in the whole port:

```
Pro  (skin-base.php:1398-1408)          VamTam (posts-base.php:297-307)
  render_post_header                      render_post_header
  render_thumbnail                        render_thumbnail
  render_text_header      <──┐            render_meta_data      ← moved OUT and UP
  render_title               │            render_text_header
  render_meta_data        ───┘            render_title
  render_excerpt                          render_excerpt
  render_read_more                        render_read_more
  render_text_footer                      render_text_footer
  render_post_footer                      render_post_footer
```

Resulting markup, from `_project/snapshots/baseline/html/blogs.html`:

```html
<article class="elementor-post elementor-grid-item post-996336 post type-post …">
  <a class="elementor-post__thumbnail__link" href="…" tabindex="-1">
    <div class="elementor-post__thumbnail"><img …></div>
  </a>
  <div class="elementor-post__meta-data">          <!-- OUTSIDE __text -->
    <div class="vamtam-post__categories"><a href="…/category/erp/" rel="category tag">ERP</a></div>
  </div>
  <div class="elementor-post__text">
    <h3 class="elementor-post__title"><a href="…">Title</a></h3>
    <div class="elementor-post__excerpt"><p>…</p></div>
    <a class="elementor-post__read-more" href="…" aria-label="Read more about …" tabindex="-1">Read full story</a>
  </div>
</article>
```

The theme's `--vamtam-content-padding` rule is `.elementor-post > .elementor-post__meta-data`, a
**direct-child** selector. It only matches because of this reorder. Keep Pro's order and the
category chip loses its padding on every card on the site.

### 3.4 Query- and request-level behaviour

| What | Where | Gated on |
|---|---|---|
| `pre_handle_404` bypass when `?vamtam_posts_fetch=1` and `page` is set | `posts-base.php:738-754` | `posts-base--404-handling-fix` |
| `elementor/query/query_args` filter: force `ignore_sticky_posts = true` when source is `by_id` and `post__in` is set | `posts-base.php:756-769` | **ungated — always registered** |
| `vamtam_posts_base_before_render_content` action + conditional `vamtam-hr-scrolling` script dep | `posts-base.php:418-447` | `posts-base--horizontal-layout` |

The sticky-posts filter reads `$query_args['ignore_sticky_posts']` without an `isset()` guard
(L758) — it emits a PHP notice for any query where the key is absent. Do not port the bug.

### 3.5 Assets added by the widget subclasses

`Vamtam_Widget_Posts::get_script_depends()` (`vamtam .../posts.php:46-60`) returns
`['imagesloaded', 'vamtam-posts-base']` and appends `'vamtam-hr-scrolling'` because
`posts-base--horizontal-layout` is on. `Vamtam_Widget_Archive_Posts::get_script_depends()`
(`archive-posts.php:243-254`) does the same. Neither subclass overrides `get_style_depends()`, so
Pro's `widget-posts` stylesheet still applies.

---

## 4. The real settings in use

### 4.1 Counts

Distinct keys across all instances of each type:

| | `posts` (12 inst.) | `archive-posts` (3 inst.) |
|---|---|---|
| `vamtam_classic_*` (live skin) | **91** | **67** |
| Other-skin prefixes — **dead** | 46 | 9 |
| Widget-level (Pro + VamTam, unprefixed) | 39 | 34 |
| Elementor common (`_skin`, `_margin`, `_css_classes`, `custom_css`, `__globals__`, …) | 12 | 6 |
| ElementsKit noise | 3 | 3 |
| **Total distinct** | **191** | **119** |

The lead's earlier estimate of 176 / 110 was low by 15 and 9 — the gap is mostly responsive
suffixes (`_tablet`, `_mobile`) that only appear on one or two instances.

Per instance:

```
type           doc     el          tot  vam_cl  ekit  other
archive-posts  6126    471f2f6      49    23      3     23
posts          8502    faf5013     117    68      3     46
archive-posts  8559    26a6e002     48    23      3     22
archive-posts  8711    8115b17     117    67      3     47
posts          996210  726f8d13     87    67      3     17
posts          996219  19a0e2f3    125    63      3     59
posts          996219  7978363     123    73      3     47
posts          996219  65d16a9     121    69      3     49
posts          996222  4377c655    125    63      3     59
posts          996222  29368807    123    73      3     47
posts          996222  5377f095    121    69      3     49
posts          93      0debbbe     125    63      3     59
posts          93      53acb3b     123    73      3     47
posts          93      77d3dd5     121    69      3     49
posts          146     f57a5e5      87    67      3     17
```

### 4.2 ElementsKit noise — ignore entirely

Exactly three keys, on all 15 instances, always the same empty defaults:

```
ekit_adv_tooltip_content    ekit_all_conditions_list    ekit_cursor_text_label
```

They come from ElementsKit's global control injection, not from the posts widget. They need no
control on our side; per §2.5(b) they will persist untouched.

### 4.3 Dead other-skin keys — do not chase them

46 `posts` keys and 9 `archive-posts` keys carry a prefix that is **not** the live skin:

```
classic_*  (46)   e.g. classic_columns, classic_posts_per_page, classic_thumbnail,
                       classic_title_typography_* (12 sub-keys), classic_meta_typography_* (11),
                       classic_box_bg_color, classic_column_gap, classic_row_gap,
                       classic_use_read_more_theme_style, classic_title_underline_anim
cards_*            cards_meta_separator, cards_read_more_text
full_content_*     full_content_meta_separator
archive_classic_*  archive_classic_{masonry,thumbnail_size_size,item_ratio,meta_data,
                                   meta_separator,read_more_text}
archive_cards_*    archive_cards_{meta_separator,read_more_text}
archive_full_content_meta_separator
```

These are fossils from before the skin switch. The `classic_*` set on `0debbbe` / `4377c655` /
`19a0e2f3` is a near-complete mirror of the `vamtam_classic_*` set — someone configured the widget
under Pro's Classic skin, then switched. **None of them is read at render time**, because
`get_instance_value()` always prefixes with the *current* skin id. They also include
`__globals__` entries (`classic_box_bg_color`, `classic_title_typography_typography`,
`classic_meta_typography_typography` on `0debbbe` and `53acb3b`) which are equally inert.

They must not be *deleted*, but they need no controls from us beyond whatever the dead skins
register anyway.

### 4.4 The live `vamtam_classic_*` set — what actually changes rendering

91 distinct keys sounds like 91 decisions. It is closer to 30. Breakdown:

**(a) Typography group sub-keys — 46 of the 91, and every single concrete value is empty.**

`vamtam_classic_{title,meta,excerpt,read_more}_typography_*` expand to 11–13 sub-keys each
(`_typography`, `_font_family`, `_font_size`, `_font_size_tablet`, `_font_size_mobile`,
`_font_weight`, `_text_transform`, `_font_style`, `_text_decoration`, `_line_height`,
`_line_height_tablet`, `_line_height_mobile`, `_letter_spacing`, `_word_spacing`). **All of them are
`""`.** The real values come from `__globals__`:

```json
"vamtam_classic_title_typography_typography":    "globals/typography?id=vamtam_h5",
"vamtam_classic_meta_typography_typography":     "globals/typography?id=be24d1a",
"vamtam_classic_excerpt_typography_typography":  "globals/typography?id=vamtam_primary_font",
"vamtam_classic_read_more_typography_typography":"globals/typography?id=c3f4d00"
```

which resolve into `var( --e-global-typography-vamtam_h5-font-size )` etc. in `post-93.css`.

**Implication:** these 46 controls must exist with *exactly* these ids so the `__globals__` map
resolves — but there is no value to migrate and nothing to hand-check. Register the group control
with the right `name` and Elementor generates the whole family for you. Use
`Group_Control_Typography` with the same `global.default` (`TYPOGRAPHY_PRIMARY` for title,
`TYPOGRAPHY_SECONDARY` for meta, `TYPOGRAPHY_TEXT` for excerpt, `TYPOGRAPHY_ACCENT` for read-more —
`skin-base.php:640-652, 728-740, 794-806, 863-876`).

**(b) Always empty on every instance — 6 on `posts`, 4 on `archive-posts`.**
`vamtam_classic_meta_separator` (deliberately `""`, overriding Pro's `///` default),
`vamtam_classic_vamtam_has_nav`, `vamtam_classic_item_ratio_mobile`,
`vamtam_classic_thumbnail_tablet`, `vamtam_classic_thumbnail_mobile`,
`vamtam_classic_apply_to_custom_excerpt`. Only `meta_separator` matters — an empty value must
suppress Pro's `content: "///"` rule, and it does, because the selector emits
`content: ""`.

**(c) Genuinely load-bearing — the ones to verify by eye.** Consolidated across the 8 live
instances:

| Key | Values in use |
|---|---|
| `vamtam_classic_columns` / `_tablet` / `_mobile` | `1`, `3` / `1`, `3` / `1` |
| `vamtam_classic_posts_per_page` | `1` (93/0debbbe, 93/53acb3b), `3` (146, 8502); **unset** on 93/77d3dd5 → Pro default `6`; absent from archive skins by design (`posts-archive-skin-classic.php:32`) |
| `vamtam_classic_thumbnail` | `right` (93/0debbbe), `none` (8711 Search), else default `top` |
| `vamtam_classic_thumbnail_size_size` | `full`, `large`, `1536x1536` |
| `vamtam_classic_item_ratio` | `0.55`, `0.56` |
| `vamtam_classic_image_width` | `55%` on 93/0debbbe |
| `vamtam_classic_title_tag` | `h2` on 146 and 996210; else default `h3` |
| `vamtam_classic_meta_data` | `["vamtam-categories"]` on 6 of 8 live; `[]` on 93/0debbbe |
| `vamtam_classic_read_more_text` | `Read full story`, `Read More`, `Read more`, `Learn more`, `Learn More` |
| `vamtam_classic_read_more_alignment` | `yes` on 5 of 8 → emits `--item-display:flex; --read-more-alignment:1` and wraps the link in `.elementor-post__read-more-wrapper` (`skin-base.php:1045-1055`, `display_read_more_bottom()` L1378-1388) |
| `vamtam_classic_box_*`, `_content_padding`, `_img_border_radius`, `_column_gap`, `_row_gap`, `_excerpt_spacing`, `_title_spacing` + responsive variants | the geometry; all reflected verbatim in `post-*.css` |
| `vamtam_classic_use_read_more_theme_style` = `"read-more-theme-style"` (9 inst.) | **dead.** No control registers it; no class appears in rendered markup. Confirmed against `blogs.html`. |

### 4.5 Widget-level (unprefixed) keys

| Group | Keys | Notes |
|---|---|---|
| Query (Pro `Group_Control_Related`, prefix `posts_`) | `posts_post_type`, `posts_include`, `posts_include_term_ids`, `posts_exclude`, `posts_exclude_term_ids`, `posts_offset`, `posts_related_taxonomies` | §5 |
| Pagination (Pro `Posts_Base`) | `pagination_type`, `pagination_page_limit`, `pagination_prev_label`, `pagination_next_label`, `load_more_spinner`, `load_more_no_posts_custom_message`, `load_more_spacing` + resp. | |
| Pagination style (**VamTam-added**) | `pagination_bg_color`, `pagination_hover_bg_color`, `pagination_active_bg_color` | Pro has no background colours here |
| Pagination style (Pro) | `pagination_color`, `pagination_hover_color`, `pagination_active_color`, `pagination_typography_*` | |
| Load-more button (`Button_Widget_Trait`) | `text` (= "Load More"), `background_color`, `button_text_color`, `button_background_hover_color`, `hover_color`, `typography_typography` | `text` is on all 15 instances and is the button label, not a stray key |
| Archive-only | `nothing_found_message` | `archive-posts.php:88-98` |
| Elementor common | `_skin`, `_css_classes`, `custom_css`, `_margin`/`_padding` + resp., `_element_width`/`_element_custom_width` + resp., `__globals__` | |

**`custom_css` is an Elementor Pro feature, not a widget feature.** Four instances use it
(6126, 8502, 8559, 8711, plus 93/77d3dd5). Two of the five rules are structural:

```css
.post-btn > div > div.elementor-button-wrapper > a:hover { color:#ffffff !important; }
.search-results > div > div > article > div > div > p   { text-align: justify !important; }
```

Both are child-combinator chains that count on `.elementor-widget-container` existing. The
`e_optimized_markup` experiment is **active** on this site (`elementor_experiment-e_optimized_markup
= active`), and it removes that wrapper for widgets that opt in. The posts widget currently keeps
it — verified in `blogs.html`. **Our replacement must keep it too**, or these two rules stop
matching. Pro's `Posts_Base` does not declare `has_widget_inner_wrapper()`, so the default (keep)
applies; do not "modernise" this.

---

## 5. Query behaviour

### 5.1 Two completely different query paths

**`posts`** (`posts.php:81-91`) builds its own `WP_Query`:

```php
$query_args = [
  'posts_per_page'        => $this->get_current_skin()->get_instance_value( 'posts_per_page' ),
  'paged'                 => $this->get_current_page(),
  'has_custom_pagination' => $this->is_allow_to_use_custom_page_option(),
];
$this->query = Module_Query::instance()->get_query( $this, 'posts', $query_args, [] );
```

**`archive-posts`** (`archive-posts.php:141-164`) does **not** query. It takes the global
`$wp_query`, runs its vars through `elementor/theme/posts_archive/query_posts/query_vars`, and only
builds a new `WP_Query` if a filter changed them. Then it feeds every result id into the avoid-list.
`posts_per_page` for archives is therefore WordPress's `posts_per_page` option — **10** on this site
— never a widget setting. That is exactly why `Posts_Archive_Skin_Classic::register_post_count_control()`
is an empty override (`posts-archive-skin-classic.php:32`).

### 5.2 `Module::get_query()` dispatch

`query-control/module.php:954-962`:

```php
$post_type = $widget->get_settings( 'posts_post_type' );
$elementor_query = ( 'related' === $post_type )
    ? new Elementor_Related_Query( $widget, 'posts', $query_args, $fallback_args )
    : new Elementor_Post_Query( $widget, 'posts', $query_args );
```

`Elementor_Post_Query::get_query_args()` (`elementor-post-query.php:101-155`) then runs, in order:
`set_common_args` → `set_order_args` → `set_pagination_args` → `set_post_include_args`, and — only
when the source is **not** `by_id` — `set_post_exclude_args` → `set_avoid_duplicates` →
`set_terms_args` → `set_author_args` → `set_date_args`. Finally
`apply_filters( 'elementor/query/query_args', … )`.

`'current_query'` short-circuits everything at L104-127 and returns the global query vars verbatim
(with `paged` overridden when custom pagination is on).

### 5.3 What the 15 instances actually query

**Eight instances set no query controls at all** — 996219/19a0e2f3, 996219/7978363,
996222/4377c655, 996222/29368807, 93/0debbbe, 93/53acb3b, plus the three `archive-posts`. For the
`posts` ones that means every `posts_*` default applies:
`post_type=post`, `orderby=date`, `order=desc`, `offset=0`, `post_status=publish`
(`elementor-post-query.php:88-99, 163-172`).

**The blog listing (93/77d3dd5, and its two dormant copies):**

```json
"posts_include":          [],
"posts_include_term_ids": ["11"],
"posts_exclude":          ["terms"],
"posts_exclude_term_ids": ["3","57","33","39"],
"posts_offset":           1,
"pagination_type":        "load_more_on_click"
```

Note the asymmetry: `posts_include` is `[]`, so `build_terms_query()` returns at L245 without ever
looking at `posts_include_term_ids` — **the include list is inert**. Only the exclusion applies, as
`tax_query` with `operator: NOT IN` on term_taxonomy_ids 3, 57, 33, 39
(`elementor-post-query.php:242-288`).

`posts_offset = 1` triggers the offset workaround (L58-64): `pre_get_posts` at priority 1 rewrites
`offset` to `$offset + (($paged - 1) * $posts_per_page)` (L411-419) and a `found_posts` filter
subtracts the offset (L427-435) so `max_num_pages` comes out right. With 15 posts, offset 1 and the
skin default of 6 per page that gives `max_num_pages = 3` — matching the live
`data-max-page="3"`.

**Home (146/f57a5e5) and its dormant copy (996210):**

```json
"posts_include": [], "posts_include_term_ids": [],
"posts_exclude": ["terms"], "posts_exclude_term_ids": ["11","8"],
"vamtam_classic_posts_per_page": 3
```

**Related posts on the single-post template (8502/faf5013) — the important one:**

```json
"posts_post_type":          "related",
"posts_include":            ["terms"],
"posts_include_term_ids":   ["23"],
"posts_exclude":            ["terms"],
"posts_exclude_term_ids":   [],
"posts_related_taxonomies": [],
"vamtam_classic_posts_per_page": 3
```

Trace it through `Elementor_Related_Query`:

* `set_common_args()` (L84-89) sets `related_post_id = get_queried_object_id()` when `is_singular()`,
  and forces `post_type` to that post's type.
* `build_terms_query_include()` (L101-124) passes its guard (`related_taxonomies` is `[]`, not
  `null`, and `include` contains `terms`) but then loops over an **empty** taxonomy list, so
  `$terms` stays empty and `insert_tax_query()` returns at L278. **`posts_include_term_ids: ["23"]`
  is dead** — the related query never reads it; it only reads `related_taxonomies`.
* `set_post_exclude_args()` (L91-99) calls the parent, which — because `posts_exclude` is
  `["terms"]` and therefore non-empty — sets `post__not_in = []`, and then unconditionally appends
  `related_post_id`.

Net effect: **"the three most recent published posts, of the same post type, excluding the post
being viewed."** No taxonomy constraint whatsoever. That is exactly what
`_project/snapshots/baseline/html/*.html` shows on every single-post page: one posts widget, three
articles, current post absent.

### 5.4 "Avoid duplicates" and the avoid list

`Module::add_to_avoid_list()` (`query-control/module.php:60-62`) accumulates every rendered post id
into a **static** `Module::$displayed_ids` for the life of the request. It is fed from
`Elementor_Post_Query::get_query()` L72 and from `Archive_Posts::query_posts()` L163.

`set_avoid_duplicates()` (`elementor-post-query.php:217-223`) merges that list into `post__not_in`
**only when `posts_avoid_duplicates === 'yes'`**.

**No instance on this site sets `posts_avoid_duplicates`.** The list is populated on every request
and read by nobody. The three widgets on `/blogs/` therefore *can* repeat a post between them —
and they do: `post-996336` appears in widget 1 (the featured card) and again in widget 3's grid.
That duplication is deliberate-looking in the design and is what `VamtamMasonry.checkDiscardDuplicates()`
half-heartedly patches client-side (§6.3).

**Reproduce the behaviour, not the intent.** Do not switch avoid-duplicates on to "fix" it.

### 5.5 The cache bug that made related posts wrong

`STATUS.md` records the root cause and it lands squarely on this widget. The
`e_element_cache` experiment caches a Theme Builder **document's** rendered HTML in
`_elementor_element_cache` postmeta for 24 h. `Blog Post Template` (8502) is *one* document shared
by all 15 posts. `Elementor_Related_Query::set_common_args()` resolves `get_queried_object_id()` at
render time — so whichever post warmed the cache decided which post got excluded, and which three
"related" posts every other post displayed, for the whole TTL. The same mechanism produced wrong
`comment_post_ID` and wrong nav highlighting.

The experiment is **off** as of 2026-08-14 (`scripts/set-experiment.php`, which also clears the
postmeta). Two consequences for this port:

1. **Never verify this widget with the element cache on.** `capture.js` already clears it and
   treats a failure to clear as fatal — keep that.
2. Any caching we add later must treat `posts_post_type = related` as uncacheable per-document.

---

## 6. Pagination and load-more

### 6.1 `/blogs/2/` vs `/blogs/page/2/` — why both work, and why the link says `/blogs/2/`

`themes/tecnologia/vamtam/classes/overrides.php:22`:

```php
add_filter( 'pre_option_page_for_posts', '__return_zero' );
```

The theme forces `page_for_posts` to 0 even though the option in the database is 93. So `/blogs/`
resolves as an ordinary **page**, not the posts index. Confirmed live — `<body class="wp-singular
page-template-default page page-id-93 …">`, and on page 2 `paged wp-singular … paged-2 page-paged-2`.

`Posts_Base::get_wp_link_page()` (`posts-base.php:682-735`) branches on that:

```php
if ( ( ! is_singular() || is_front_page() ) && ! $this->is_rest_request() && ! $this->is_allow_to_use_custom_page_option() ) {
    return get_pagenum_link( $i );          //  → /blogs/page/2/
}
…
$url = trailingslashit( $url ) . user_trailingslashit( $i, 'single_paged' );   //  → /blogs/2/
```

`is_singular()` is **true**, so the second branch runs and every generated link is `/blogs/2/`.
Verified: `data-next-page="http://localhost/piecyfer/blogs/2/"`.

`get_current_page()` (`posts-base.php:661-672`) returns `1` when `pagination_type` is empty,
otherwise `max( 1, get_query_var('paged'), get_query_var('page'), $_GET['e-page-<id>'] )`. So:

| URL | widget 0debbbe (`pagination_type` = "") | widget 53acb3b ("") | widget 77d3dd5 (`load_more_on_click`) |
|---|---|---|---|
| `/blogs/` | page 1 | page 1 | page 1, `data-page="1"`, next `/blogs/2/` |
| `/blogs/page/2/` (`paged=2`) | page 1 — unchanged | page 1 | page **2**, next `/blogs/3/` |
| `/blogs/2/` (`page=2`) | page 1 | page 1 | page **2**, next `/blogs/3/` |

Verified by post ids: `/blogs/` lists 996259, 996269, 996273, 996318, 996323, 996328, 996336;
`/blogs/page/2/` lists 994407, 994760, 994776, 994789, 994795, 994807, 996336 — the featured widget
repeats 996336 on both, exactly as the model predicts.

### 6.2 What Pro renders

`Skin_Base::render_loop_footer()` (`skin-base.php:1121-1235`), in order:

1. Close the container `</div>`.
2. Bail if `pagination_type` is not set at all (L1128-1130).
3. If ajax pagination **and** `load_more_spinner.value` is non-empty, print
   `<span class="e-load-more-spinner">…icon…</span>` (L1137-1141).
4. Bail if `pagination_type === ''` (L1145-1147).
5. `$page_limit = max_num_pages`, clamped by `pagination_page_limit` **unless** ajax (L1149-1154).
   **Bail if `$page_limit < 2` (L1156-1158).** This is why `/category/erp/` (9 posts, 1 page) has no
   pagination markup at all — verified live.
6. `<div class="e-load-more-anchor" data-page data-max-page data-next-page>` (L1170-1177).
7. Ajax → `set_settings('link', ['url'=>'#'])`, `render_button()`, `render_message()`, return.
   Otherwise → `paginate_links()` + optional prev/next inside
   `<nav class="elementor-pagination" aria-label="Pagination">`.

Verified markup on `/blogs/`:

```html
<span class="e-load-more-spinner"><svg …></svg></span>
<div class="e-load-more-anchor" data-page="1" data-max-page="3"
     data-next-page="http://localhost/piecyfer/blogs/2/"></div>
<div class="elementor-button-wrapper">
  <a href="#" class="elementor-button-link elementor-button" role="button">
    <span class="elementor-button-content-wrapper">
      <span class="elementor-button-text">Load More</span>
    </span>
  </a>
</div>
<div class="e-load-more-message"></div>
```

`.e-load-more-message` is empty because `load_more_no_posts_message_switcher` is unset, so
`render_message()` (`skin-base.php:1114-1119`) echoes an empty string. Note it reads
`$settings['load_more_no_posts_custom_message']` unconditionally, ignoring the switcher — copy that.

The button markup comes from `Button_Widget_Trait::render_button()`
(`button-widget-trait.php:491-530`); the `load-more-align-center` class on the widget wrapper comes
from `register_button_style_controls([ 'prefix_class' => 'load-more-align-', 'alignment_default' => 'center' ])`
(`posts-base.php:61-67`).

### 6.3 Which instances use AJAX load-more

`pagination_type = "load_more_on_click"` on **6** of 15: 93/77d3dd5, 996219/65d16a9,
996222/5377f095, and all three `archive-posts` (6126, 8559, 8711). No instance uses
`numbers`, `prev_next`, `numbers_and_prev_next` or `load_more_infinite_scroll`.

So **no `.elementor-pagination` markup is produced anywhere on this site** — which makes VamTam's
entire `posts-base--extra-pagination-controls` block (`posts-base.php:14-222`, 208 lines, 9
controls) *visually* dead. It is not schema-dead: `pagination_bg_color`,
`pagination_hover_bg_color` and `pagination_active_bg_color` carry saved `__globals__` references on
8 instances. Register the controls; skip the CSS.

### 6.4 `posts-base--load-more-masonry-fix` — what it actually does

The flag appears in the theme's feature list (`framework.php:280`) and in exactly one PHP guard
(`vamtam .../posts.php:18`, an OR-array that also lists two other flags). **It gates no PHP logic of
its own.** The behaviour is entirely in `assets/js/widgets/posts-base/vamtam-posts-base.js`, class
`VamtamMasonry` (L345-…):

* `loadMoreMasonryFix()` (L435-…) — returns immediately unless `pagination_type` is
  `load_more_on_click` / `load_more_infinite_scroll`. Otherwise it hooks the Load More button (or a
  scroll observer for infinite scroll) and starts a 50 ms poll, capped at 10 s, watching
  `.elementor-post:visible` count.
* When the count changes it calls `checkDiscardDuplicates()` (L374-…), which reads each article's
  post id out of `classList[2]` (the `post-<ID>` class WordPress emits via `post_class()`), removes
  any article whose id it has already seen, and additionally removes any article whose id also
  appears inside `.vamtam-blog-featured-post .elementor-post`.
* Then `recalculateMasonry()` (L369-…) re-runs the masonry layout with the corrected count.

Two notes:

* **`.vamtam-blog-featured-post` does not exist on this site** (0 occurrences in `blogs.html`), so
  the featured-post half of the dedupe is dead here. The intra-widget dedupe is live: with
  `posts_offset = 1` and load-more paging, the server can and does hand back a post already on the
  page.
* `classList[2]` is positional and brittle — it assumes `class="elementor-post elementor-grid-item
  post-<ID> …"`. **Our `render_post_header()` must keep `post_class( [ 'elementor-post
  elementor-grid-item' ] )` in exactly that order** (`skin-base.php:1058-1062`) or the dedupe reads
  garbage.

### 6.5 `posts-base--404-handling-fix`

`VamtamLoadMore.handlePostsQuery()` (js L326-…) appends `?vamtam_posts_fetch=1` to `data-next-page`
and fetches the whole page, then `handleSuccessFetch()` (L303) scrapes
`[data-id="<elementId>"] .elementor-posts-container > article` out of the response and appends the
`outerHTML`. Because the target URL is `/blogs/3/`-style (`page` query var on a *page*), WordPress
would 404 it in some configurations; `vamtam_bypass_404_handling_on_posts_base_fetch()`
(`vamtam .../posts-base.php:739-753`) returns `true` from `pre_handle_404` when both
`?vamtam_posts_fetch` and `$wp_query->query['page']` are present.

**This is a server-side contract our replacement must honour**, because the JS that depends on it is
ours to reimplement too. Keep both halves or replace both halves.

---

## 7. Assets

### 7.1 What is on the page today

Measured on a live `GET /blogs/`:

| Handle | Source | Why |
|---|---|---|
| `widget-posts` | `elementor-pro/assets/css/widget-posts.min.css` (14 KB) | `Posts::get_style_depends()` L43-45 and `Archive_Posts::get_style_depends()` L55-57 |
| `imagesloaded` | WP core | `Posts_Base::get_script_depends()` L39-41 |
| `vamtam-posts-base` | `vamtam .../assets/js/widgets/posts-base/vamtam-posts-base.min.js` | VamTam subclass `get_script_depends()` |
| `vamtam-hr-scrolling` | `vamtam .../assets/js/widgets/vamtam-hr-scrolling/vamtam-hr-scrolling.min.js` | ditto, unconditionally, because `posts-base--horizontal-layout` is on |
| `elementor-post-93` | `wp-content/uploads/elementor/css/post-93.css` | generated per-document control CSS |
| `vamtam-front-all` + `vamtam-theme-elementor-*` | `themes/tecnologia/vamtam/assets/css/dist/elementor/…` | the theme's own posts styling |

`Archive_Posts::get_inline_css_depends()` (L41-43) additionally returns `['posts']`, so archive
templates inline the posts CSS when the Improved CSS Loading experiment is on.

### 7.2 The stylesheet split, and what is *not* in it

Pro's `widget-posts.min.css` contains exactly **one** skin-scoped rule for classic:

```css
.elementor-posts--skin-classic .elementor-post{overflow:hidden}
```

Because the `posts` widget's container is `elementor-posts--skin-vamtam_classic`, **that rule does
not apply to the blog listing today** — but it *does* apply to `archive-posts`, whose container is
hard-coded to `…--skin-classic` (§1.1). That asymmetry is live behaviour, not a bug to fix.

The theme carries its own posts styling, compiled into `elementor-all.css` from
`themes/tecnologia/vamtam/assets/css/src/elementor/widgets/{posts,posts-base,archive-posts,hr-scrolling-common}/*.less`.
Notably `vamtam-post__categories` is styled there (1 occurrence in the dist file), as are the four
custom properties. **None of this is ours to replace** — it belongs to the theme, and the theme
decision is Phase 5. Our job is to keep emitting the class names and custom properties it targets.

The kit adds one more coupling: `vamtam .../includes/kits/documents/kit.php:352` retargets the kit's
`image_hover_transition` control at
`.vamtam-has-theme-widget-styles .elementor-posts-container .elementor-post__thumbnail img`.

### 7.3 The JavaScript problem

**Elementor Pro's front-end handlers are registered per skin id, and none of the ids is
`vamtam_classic`.** From `elementor-pro/assets/js/elements-handlers.min.js`:

```js
["archive_classic","archive_full_content","archive_cards"].forEach(e =>
  elementorFrontend.elementsHandler.attachHandler("archive-posts", …))
```

with the sibling list `["classic","cards","full_content"]` for `posts`. `attachHandler` keys on
`widgetType + '.' + skin`, i.e. exactly the `data-widget_type` string.

`vamtam-posts-base.js` (721 lines) fills the gap (L676-719):

```js
elementorFrontend.elementsHandler.attachHandler( 'posts', defaultPosts,  'vamtam_classic' );
elementorFrontend.elementsHandler.attachHandler( 'posts', VamtamLoadMore, 'vamtam_classic' );
elementorFrontend.elementsHandler.attachHandler( 'posts', VamtamMasonry,  'vamtam_classic' );
elementorFrontend.elementsHandler.attachHandler( 'posts', VamtamMasonry,  'classic' );
// …and the same four for 'archive-posts' with 'archive_classic' as the last
```

all guarded by `VAMTAM_FRONT.elementor.widgets.isWidgetModActive('posts')`.

The three handlers:

| Class | Lines | Responsibility |
|---|---|---|
| `defaultPosts` | 2-163 | a fork of Pro's posts handler: `fitImages()` reads `getComputedStyle($element, ':after').content` and toggles `elementor-has-item-ratio` on the container and `elementor-fit-height` per thumbnail; `runMasonry()`; `getSkinPrefix()` hard-coded to `vamtam_classic_` |
| `VamtamLoadMore` | 164-344 | a fork of Pro's load-more: fetch `data-next-page` + `vamtam_posts_fetch=1`, scrape articles, append, update `data-page`/`data-next-page`, add `e-load-more-pagination-end` at the last page |
| `VamtamMasonry` | 345-… | the load-more masonry fix and duplicate discard (§6.4), plus a Safari resize kludge |

**`elementor-has-item-ratio` in the captured HTML is produced by JavaScript, not by PHP.** It
appears on `/blogs/` (where `:after{content:"0.56"}` is generated) and not on `/?s=software`
(`vamtam_classic_thumbnail = none`, so no `item_ratio` rule). The harness captures post-JS DOM, so
this class is in scope for byte-comparison even though no server code emits it.

`vamtam-hr-scrolling.js` (214 lines) bails at its first check —
`if ( ! this.$element.hasClass('vamtam-has-hr-layout') ) return;` — and no instance sets
`vamtam_use_hr_layout`. It is **enqueued but inert**. Dropping it changes zero pixels but does
change the `<script>` list the harness records, so drop it as a deliberate, noted step rather than
by accident.

---

## 8. The build plan

### 8.1 File layout

```
piecyfer-core/src/
  Widgets/
    PostsBaseWidget.php          abstract; ports Pro Posts_Base
    PostsWidget.php              name: posts
    ArchivePostsWidget.php       name: archive-posts
  Skins/
    SkinBase.php                 abstract; ports Pro Posts\Skins\Skin_Base (~1400 lines)
    ClassicSkin.php              id: classic
    CardsSkin.php                id: cards
    FullContentSkin.php          id: full_content
    ArchiveClassicSkin.php       id: archive_classic
    ArchiveCardsSkin.php         id: archive_cards
    ArchiveFullContentSkin.php   id: archive_full_content
    VamtamClassicSkin.php        id: vamtam_classic   (posts)
    VamtamArchiveClassicSkin.php id: vamtam_classic   (archive-posts)
    Concerns/
      VamtamClassicOverrides.php trait — get_id/get_title/render_meta_data/render_categories/
                                 render_tags/render_post  (§3.3)
      ArchiveSkinRender.php      trait — Pro's Posts_Archive_Skin_Base nothing-found path
  Query/
    PostQuery.php                ports Elementor_Post_Query
    RelatedQuery.php             ports Elementor_Related_Query
    QueryRegistry.php            the static avoid-list (Module::$displayed_ids equivalent)
  Controls/
    GroupControlPosts.php        ports Group_Control_Posts / _Related / _Query
  Traits/
    ButtonWidgetTrait.php        ports Pro's Button_Widget_Trait (render_button + button controls)
    PaginationTrait.php          ports Pro's Pagination_Trait (get_base_url, is_posts_page)
assets/
  css/posts.css                  replaces Pro widget-posts.min.css
  js/posts.js                    replaces vamtam-posts-base.min.js (all three handlers)
```

Two deviations from the existing `piecyfer-core` conventions, both deliberate:

* **`AbstractWidget::render()` is `final protected`** (`src/Widgets/AbstractWidget.php:100`). A
  skinned widget never has its `render()` called — `render_content()` dispatches to the skin
  (§2.4). `PostsBaseWidget` should therefore extend `Elementor\Widget_Base` directly, or
  `AbstractWidget` needs a hook so the per-widget try/catch guard and `enqueue_assets()` still fire
  for skinned widgets. **Prefer the latter**: add a `protected function render_skin_guard()` path
  rather than losing the fail-safe on the two riskiest widgets on the site.
* Skins need their own asset ownership. `ProStyleGuard::SUPERSEDED` (`src/ProStyleGuard.php:38-52`)
  gains `'posts' => 'widget-posts'` — **and only after both widgets are ours**, because
  `archive-posts` and `posts` share the handle.

### 8.2 Order of work, with a gate after each step

Each step ends with:

```
php _project/scripts/which-implementation.php posts
node _project/pixel-tool/capture.js <label> --only <urls>
node _project/pixel-tool/compare.js ref-a <label>
```

`which-implementation.php` currently reports **widget** classes only. **Extend it to also print
`Plugin::$instance->skins_manager->get_skins( $widget )` per widget, with the class name behind each
skin id.** Without that, a passing comparison proves nothing: VamTam registers at priority 100 and
our skin could be silently overwritten, or Pro's skin could still be doing the rendering, and the
pixels would be identical either way. This is the same class of false pass that
`which-implementation.php` was written to catch for widgets.

| Step | Deliverable | Gate |
|---|---|---|
| **P1** | `PostsBaseWidget` + `SkinBase` + `ClassicSkin`, registered **without** removing Pro. Every control id and section id byte-identical. No render logic yet — `render()` still delegates. | `which-implementation.php` shows our classes for `posts` and all six Pro skin ids. Zero pixel change. |
| **P2** | `VamtamClassicSkin` with the trait. This is the first step that changes output. | `/blogs/`, `/`, one single post. Markup diff must be empty. |
| **P3** | `ArchivePostsWidget` + `ArchiveClassicSkin` + `VamtamArchiveClassicSkin`, including the Box section VamTam adds (`archive-posts.php:18-199`) and the hard-coded `elementor-posts--skin-classic` container class. | `/category/erp/`, `/category/crm/`, `/?s=software`, `/author/webdeveloper373/`. |
| **P4** | `PostQuery` + `RelatedQuery` + the avoid list + the offset workaround + the `elementor/query/*` filters. | Post ids on `/blogs/`, `/blogs/page/2/`, `/blogs/2/` and every single post must match exactly. Compare the `post-<ID>` classes, not the pixels. |
| **P5** | Pagination and load-more server side: `get_wp_link_page`, `get_current_page`, `render_loop_footer`, `render_button`, the spinner, the `pre_handle_404` bypass. | `data-page` / `data-max-page` / `data-next-page` byte-identical on all six load-more instances. |
| **P6** | `assets/js/posts.js` — all three handlers. Register handlers for `vamtam_classic` **and** `classic`/`archive_classic` (VamTam attaches `VamtamMasonry` to those too). | Re-capture: `elementor-has-item-ratio` present on `/blogs/` and absent on `/?s=software`. Manually click Load More on `/blogs/` and confirm 6 more articles with no duplicates. |
| **P7** | `assets/css/posts.css`, then add `'posts' => 'widget-posts'` to `ProStyleGuard`. | Full 39-URL capture. Only now is the CSS honestly tested (§`ProStyleGuard` docblock). |
| **P8** | Drop `vamtam-hr-scrolling` and the horizontal-layout controls' *rendering* (keep the controls for schema). | Script list changes; zero pixels change. |

### 8.3 Where byte-identical markup will be hard

| # | Problem | Why it is hard | Approach |
|---|---|---|---|
| 1 | `Group_Control_Image_Size::get_attachment_image_html()` (`skin-base.php:962`) | Emits `srcset`/`sizes`/`loading`/`decoding` from WordPress, and the exact attribute order comes from Elementor's own group control, which is core (not Pro). | Call the same core API. Do **not** hand-roll `wp_get_attachment_image()`. |
| 2 | `the_excerpt()` under filtered `excerpt_length`/`excerpt_more` (`skin-base.php:994-1026`) | Pro adds the filters **twice** (L995-996 *and* L1002-1003) and removes them once (L1024-1025) — so on the `show_excerpt = ''` early-return path the filters leak into whatever renders next. | Replicate the double-add and the early return. It is a bug; on this site every live instance has `show_excerpt` unset (→ Pro default `yes`), so the leak path is not exercised — but do not "fix" it without a capture. |
| 3 | The `.elementor-widget-container` wrapper | Two `custom_css` rules depend on it (§4.5) and `e_optimized_markup` is active. | Do not implement `has_widget_inner_wrapper()`. |
| 4 | `post_class()` output order | `VamtamMasonry.checkDiscardDuplicates()` indexes `classList[2]` (§6.4). | `post_class( [ 'elementor-post elementor-grid-item' ] )` — one string, exactly as Pro passes it. |
| 5 | Whitespace | Pro's skins produce a very specific indentation from their `?> … <?php` blocks; the harness normalises some of it but not all. | Copy the templates literally, including the odd leading tabs. Diff the raw HTML before trusting the normalised diff. |
| 6 | Container class asymmetry | `posts` → `--skin-vamtam_classic`, `archive-posts` → `--skin-classic` (§1.1). | Two different `get_container_class()` implementations. It will look like a bug in review; leave a comment pointing at `posts-archive-skin-classic.php:26-29`. |
| 7 | `elementor-has-item-ratio` / `elementor-fit-height` | JS-added, and `fitImage()` depends on the image having *loaded*, which makes it timing-sensitive. | The harness's settle loop already handles this for the baseline; keep the same settle behaviour and re-run any suspicious page twice before believing a diff. |
| 8 | `paginate_links()` | Not exercised today (no instance uses numbered pagination) but ~90 lines of branch logic in `render_loop_footer` L1194-1233. | Port it, do not verify it. Note in the code that it is untested on this site. |

---

## 9. Risks

**1. A wrong skin id makes eight widgets disappear, silently.**
Not degrade — disappear. `get_skin()` returns `false`, `Posts_Base::render()` is empty,
`render_content()` returns before printing anything (§2.4). No PHP error, no console message, no
placeholder. On `/blogs/` that is the entire page content. The pixel harness *would* catch it, but
only if that URL is in the run — which is why P2's gate names the URLs explicitly. **Assert the id
in code**: a unit-style check that `Skins_Manager::get_skins()` for `posts` contains the literal
string `vamtam_classic`, run from `which-implementation.php`.

**2. A wrong control prefix loses ~90 settings per instance, and the loss is invisible until
someone saves.**
`vamtam_classic_meta_data` vs `vamtamclassic_meta_data` vs `vamtam-classic_meta_data` all "work" —
the widget renders, with defaults. `meta_data` would default to `['date','comments']`
(`skin-base.php:380`) and every card would sprout a date and a comment count instead of a category
chip. `read_more_text` would revert to `Read More »`. The failure is *plausible-looking output*,
which is the worst kind. Mitigation: derive the prefix from `get_id()` through the same
`str_replace('-','_',…)` path Elementor uses, never hard-code it in two places, and diff the full
`get_controls()` key list against Pro+VamTam's before P2's capture.

**3. `__globals__` orphaning.**
Nearly all typography and half the colours are global references (§4.4). A control id that is right
for *rendering* but wrong in `__globals__` terms — e.g. registering `title_typography` as a plain
control instead of a `Group_Control_Typography` — leaves
`vamtam_classic_title_typography_typography` pointing at nothing, and because the concrete sub-keys
are all `""` there is no fallback. Result: unstyled headings across the blog, with no error
anywhere. Verify by grepping the regenerated `wp-content/uploads/elementor/css/post-93.css` for
`--e-global-typography-vamtam_h5-font-size` after P2.

**4. Skins are registered globally by widget name.**
`Skins_Manager::$_skins['posts']` is shared. While Pro and VamTam are still installed, three
registrations race: Pro at 10, VamTam at 100, us at 150. Our `vamtam_classic` will overwrite
VamTam's under the same key — which is what we want — but Pro's `classic`/`cards`/`full_content`
objects remain in the bucket, still parented to a widget object that no longer renders anything.
`set_parent()` in `render_content()` papers over it. **Do not rely on that.** Register all six Pro
skin ids ourselves from P1, so the bucket's contents are deterministic before anything else changes.

**5. `posts_per_page` comes from the skin, not the widget.**
`Posts::get_posts_per_page_value()` (`posts.php:104-106`) calls
`get_current_skin()->get_instance_value('posts_per_page')`. If our skin does not register
`posts_per_page` (or registers it on the archive skin, where Pro deliberately does *not* —
`posts-archive-skin-classic.php:32`), the value resolves to `null`, `WP_Query` falls back to the
site's 10, and `/blogs/` shows 10 cards instead of 6 with `max-page` 2 instead of 3. Nothing
crashes.

**6. Load-more is entirely ours to reimplement, and it is not exercised by a static capture.**
Pro's JS never runs for this skin (§7.3). A full-page screenshot of `/blogs/` proves nothing about
the Load More button. **P6 needs a scripted interaction test** — click the button, wait for the
fetch, assert six new `<article>` elements and zero duplicate `post-<ID>` classes — or it ships
untested. The harness has no such test today.

**7. The 404 bypass and the fetch URL are a matched pair.**
`?vamtam_posts_fetch=1` is a VamTam invention. If our JS keeps the parameter but our PHP drops the
`pre_handle_404` filter, page 2 of the blog fetch returns a 404 body and Load More silently appends
nothing. If our JS renames the parameter and our PHP keeps VamTam's, same result. Change both or
neither, in one commit.

**8. Document 6126 has no harness coverage.**
`Case Studies Archives` holds one of the three `archive-posts` instances and no URL in `urls.js`
reaches it. It will be signed off blind unless its display condition is resolved to a real URL
first.

**9. `custom_css` is Pro, not ours.**
Five saved `custom_css` values across these instances (§4.5), two of them structural selectors
through `.elementor-widget-container`. Removing Elementor Pro removes the control **and stops
applying the saved CSS**, independently of anything in this spec. That is a separate work item and
it should not be discovered during the posts port.

**10. `posts-base--responsive-image-position` is declared and unused.**
It is in the theme's feature list (`framework.php:291`) and referenced by **no PHP, JS or CSS**
anywhere in `wp-content`. Either VamTam ships it in a newer build, or it was ported and forgotten.
Do not implement anything for it, and do not assume the flag list is a reliable inventory of
behaviour — it is not.

**11. Two upstream bugs are load-bearing-adjacent.**
`vamtam_theme_supports()` swallows extra arguments (§3, note) and
`vamtam_ignore_sticky_posts_for_manual_selection_fix()` reads an unset array key
(`posts-base.php:758`). Neither changes output today. Port behaviour, not code — but record the
divergence, because a future capture difference on a page with sticky posts will otherwise be
unexplainable.

---

## Appendix A — control-id inventory, live skin

The 91 `vamtam_classic_*` keys present in saved `posts` data. `archive-posts` uses the same set
minus `posts_per_page`, `columns`, `image_spacing`, `image_width`, `meta_color`, `box_bg_color`,
`read_more_alignment`, `title_tag`, `thumbnail`, `use_read_more_theme_style` and the
`read_more_typography_*` family (67 keys).

```
apply_to_custom_excerpt          meta_typography_font_style
box_bg_color                     meta_typography_font_weight
box_border_color                 meta_typography_letter_spacing
box_border_color_hover           meta_typography_line_height
box_border_radius                meta_typography_line_height_mobile
box_border_width                 meta_typography_text_decoration
box_border_width_mobile          meta_typography_text_transform
box_padding                      meta_typography_typography
box_padding_mobile               meta_typography_word_spacing
box_padding_tablet               posts_per_page
column_gap                       read_more_alignment
column_gap_mobile                read_more_text
column_gap_tablet                read_more_typography_font_family
columns                          read_more_typography_font_size
columns_tablet                   read_more_typography_font_style
content_padding                  read_more_typography_font_weight
content_padding_tablet           read_more_typography_letter_spacing
excerpt_color                    read_more_typography_line_height
excerpt_spacing                  read_more_typography_text_transform
excerpt_spacing_tablet           read_more_typography_typography
excerpt_typography_font_family   read_more_typography_word_spacing
excerpt_typography_font_size     row_gap
excerpt_typography_font_size_mobile   row_gap_mobile
excerpt_typography_font_style    row_gap_tablet
excerpt_typography_font_weight   thumbnail
excerpt_typography_letter_spacing     thumbnail_mobile
excerpt_typography_line_height   thumbnail_size_size
excerpt_typography_line_height_mobile thumbnail_tablet
excerpt_typography_text_transform     title_spacing
excerpt_typography_typography    title_spacing_tablet
excerpt_typography_word_spacing  title_tag
image_spacing                    title_typography_font_family
image_width                      title_typography_font_size
img_border_radius                title_typography_font_size_mobile
item_ratio                       title_typography_font_size_tablet
item_ratio_mobile                title_typography_font_style
meta_color                       title_typography_font_weight
meta_data                        title_typography_letter_spacing
meta_separator                   title_typography_line_height
meta_typography_font_family      title_typography_line_height_mobile
meta_typography_font_size        title_typography_line_height_tablet
meta_typography_font_size_mobile title_typography_text_transform
                                 title_typography_typography
use_read_more_theme_style ← DEAD title_typography_word_spacing
vamtam_additional_cols_hint      vamtam_additional_cols_hint_mobile
vamtam_additional_cols_hint_tablet    vamtam_has_nav
```

## Appendix B — commands used to produce this document

```bash
# saved data
C:/xampp/php/php.exe _project/scripts/dump-eldata.php          # 76 documents; JSON starts at first '['

# rendered ground truth
curl -s http://localhost/piecyfer/blogs/
curl -s http://localhost/piecyfer/blogs/page/2/
curl -s http://localhost/piecyfer/blogs/2/
curl -s http://localhost/piecyfer/category/erp/
curl -s "http://localhost/piecyfer/?s=software"

# baseline markup
_project/snapshots/baseline/html/*.html

# generated control CSS (regenerates on first request after a cache clear)
wp-content/uploads/elementor/css/post-93.css
```
