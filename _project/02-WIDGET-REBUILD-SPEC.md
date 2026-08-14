# Phase 4 build specification — `piecyfer-core`

**Derived from:** every live `_elementor_data` document (26 templates, 21 pages, 15 posts,
revisions excluded), analysed with `_project/pixel-tool/analyse-widgets.js`.

This document is the contract for the rebuild. Anything listed here must exist in
`piecyfer-core` under the **same name and same control id**, or saved page settings are
silently dropped and the page changes.

---

## ⚠️ Correction to the effort estimate in `01-REBUILD-PLAN.md`

The initial plan called `posts` and `archive-posts` "Medium" difficulty. That was wrong, and
the correction matters because it moves where most of the work sits.

**These are not stock Elementor Pro widgets.** The `vamtam-elementor-integration-tecnologia`
plugin subclasses Elementor Pro and adds a custom skin — `vamtam_classic` — on top of Pro's
`Skin_Classic` and `Posts_Archive_Skin_Classic`. The site's blog and archive listings use
**that** skin, not Pro's.

Concretely:

| Widget | Distinct `vamtam_*` keys in use | Populated values |
|---|---|---|
| `posts` | 86 | 772 |
| `archive-posts` | 64 | 106 |
| `testimonial-carousel` | 19 | 30 |
| `image` | 14 | 14 |
| `button` | 4 | 4 |
| `nav-menu` | 1 | 1 |
| `section` | 1 | 1 |
| **Total** | | **928** |

**83% of all VamTam customisation on this site lives in `posts` + `archive-posts`.** Rebuilding
those two widgets means reproducing Elementor Pro's Posts widget *and* VamTam's skin on top of
it — roughly 150 custom controls across 15 widget instances. Treat this as the second-largest
item in Phase 4 after Theme Builder, not a medium one.

---

## The Elementor Pro removal is a three-plugin problem, not one

`elementor-pro`, `elementskit`/`elementskit-lite`, and
`vamtam-elementor-integration-tecnologia` are entangled. Removing Pro affects all three.

### What breaks, and how badly

`vamtam-elementor-integration-tecnologia` **subclasses Elementor Pro classes directly**:

```php
// includes/widgets/nav-menu.php:120
class Vamtam_Widget_Nav_Menu extends \ElementorPro\Modules\NavMenu\Widgets\Nav_Menu { … }

// includes/widgets/posts-base.php:4-5
use ElementorPro\Modules\Posts\Skins\Skin_Classic            as Elementor_Posts_Classic_Skin;
use ElementorPro\Modules\ThemeBuilder\Skins\Posts_Archive_Skin_Classic as Elementor_Archive_Posts_Classic_Skin;
```

Guard coverage is **inconsistent**:

| File | Extends Pro | `class_exists()` guard |
|---|---|---|
| `posts.php` | ✅ | ✅ guarded |
| `archive-posts.php` | ✅ | ✅ guarded |
| `posts-base.php` | ✅ (skins) | ❌ **none** |
| `nav-menu.php` | ✅ | ❌ **none** |
| `login.php` | ✅ | ❌ **none** |
| `popup.php` | ✅ | ❌ **none** |

The unguarded subclass declarations sit inside functions hooked to
`elementor/widgets/register`, so they fatal at *widget registration time* rather than at load —
which means a **white screen in the Elementor editor and on any page that renders**, not a
graceful degradation.

**Therefore Elementor Pro cannot simply be deactivated.** Phase 3 must not attempt it. Pro comes
out only in Phase 4, together with the VamTam integration, once `piecyfer-core` provides
replacements for both.

### Verification step before any removal

Deactivate Elementor Pro on a throwaway copy of the database and capture. The pixel harness will
show exactly which pages break and how. Do this *before* writing any widget code — it converts
the table above from a code reading into an observed fact.

---

## What is NOT a dependency (good news)

ElementsKit injects global controls into **every** widget on the site. At first glance this looks
like 2,269 widgets depending on ElementsKit. They are defaults, not usage:

| Injected key | Instances | Value |
|---|---|---|
| `ekit_cursor_text_label` | 2,269 | `"Elementskit Cursor"` — untouched default |
| `ekit_adv_tooltip_content` | 1,246 | `"Tooltip Content."` — untouched default |
| `ekit_all_conditions_list` | 2,230 | `[]` — empty |
| `ekit_section_parallax_multi_items` | 409 | `[]` — empty |
| `ekit_cursor_image_src` | 203 | VamTam demo `placeholder-NNN.png` |

Only **4 instances** carry deliberately-set ElementsKit global settings
(`ekit_sticky: "top"` ×2, plus two condition entries).

**So removing ElementsKit costs 6 widget types and 4 global settings — not 2,269 elements.**

Note also that the 203 `ekit_cursor_image_src` values point at
`https://wdev.piecyfer.com/wp-content/uploads/2024/05/placeholder-*.png` — the staging domain.
These are inert defaults, but they are the only off-site asset references in the entire content
set. Everything real is local. **The site is self-contained.**

---

## Full census

### Elementor FREE — 1,207 instances, keep as-is

`heading` 389 · `text-editor` 249 · `icon-box` 221 · `spacer` 80 · `icon` 79 · `image-box` 47 ·
`icon-list` 42 · `image` 36 · `button` 30 · `divider` 9 · `toggle` 7 · `image-carousel` 3 ·
`html` 3 · `social-icons` 3 · `video` 2 · `star-rating` 2 · `image-gallery` 2 · `google_maps` 2 ·
`counter` 1

⚠️ Caveat: `button`, `text-editor`, `image-box`, `image`, `section` and `column` receive **extra
controls** injected by VamTam via `elementor/element/<type>/…/before_section_end` hooks. Those
are additive and only 20 values are actually populated site-wide — but they must be carried over
when the VamTam plugin is retired in Phase 5, or those 20 elements shift.

### Elementor PRO — 16 types, 91 instances, rebuild required

Ordered by build order rather than by usage.

| # | Widget | Uses | Populated keys | Difficulty | Notes |
|---|---|---|---|---|---|
| 1 | `theme-post-title` | 1 | few | Easy | |
| 2 | `theme-archive-title` | 2 | few | Easy | |
| 3 | `theme-post-content` | 1 | few | Easy | |
| 4 | `theme-site-logo` | 2 | few | Easy | |
| 5 | `post-comments` | 2 | few | Easy | Wraps core comments template |
| 6 | `blockquote` | 4 | few | Easy | |
| 7 | `search-form` | 4 | few | Easy | |
| 8 | `template` | 19 | 19 | Easy | Renders another `elementor_library` doc by id |
| 9 | `post-info` | 1 | — | Medium | Meta list with dynamic sources |
| 10 | `call-to-action` | 3 | — | Medium | |
| 11 | `gallery` | 1 | — | Medium | Pro gallery ≠ free `image-gallery` |
| 12 | `nav-menu` | 25 | **70** | Medium-Hard | Most-used Pro widget; VamTam subclasses it; dropdown + mobile toggle behaviour |
| 13 | `testimonial-carousel` | 2 | +19 vamtam | Medium | Custom nav positioning from VamTam |
| 14 | `archive-posts` | 3 | **64 vamtam** | **Hard** | VamTam `vamtam_classic` archive skin |
| 15 | `posts` | 12 | **86 vamtam** | **Hard** | VamTam `vamtam_classic` skin — biggest widget |
| 16 | `form` | 9 | — | **Hard** | Fields, validation, actions-after-submit, mail, spam |

### ElementsKit — 6 types, 19 instances, rebuild required

| Widget | Uses | Notes |
|---|---|---|
| `elementskit-icon-box` | 12 | Substantial real styling — ~60 populated keys |
| `elementskit-client-logo` | 2 | Carousel of logos, ~30 populated keys |
| `elementskit-back-to-top` | 2 | Trivial |
| `elementskit-social-share` | 1 | Trivial |
| `elementskit-header-search` | 1 | Small |
| `ekit-nav-menu` | 1 | Overlaps our `nav-menu` work |

Plus two widgets you already authored in ElementsKit's widget builder, stored in
`wp-content/uploads/elementskit/custom_widgets/`:

- `ekit_wb_995717` — "Custom Button"
- `ekit_wb_995720` — "My Custom btn"

These are plain `Widget_Base` subclasses with no ElementsKit dependency in their logic. They can
be lifted into `piecyfer-core` almost verbatim — the cheapest win in the whole phase.

### Elementor Pro features beyond widgets

| Feature | Usage | Notes |
|---|---|---|
| **Theme Builder** | 10 templates | 1 header, 2 footers, 1 single-post, 2 archives, 1 search, 1 404 + display conditions. **Largest single item in Phase 4.** |
| **Popup Builder** | 1 | "Consultation CTA" |
| **Per-element Custom CSS** | 58 elements | Control injection across all widgets |
| **Motion FX** | 23 elements | Scale-on-scroll only — no other effect is used |
| **Sticky** | 3 elements | |
| **Global Widgets** | 3 saved | "Button - text", "Small heading with background", "Button with light background" |
| **Dynamic tags** | in templates | Only the tags actually referenced need porting |

Motion FX is worth calling out: of 122 `motion_fx*` keys in the data, only **23 are a real
enabled effect**, and all 23 are the same one (`motion_fx_scale_effect`). We need to implement
one effect, not the whole module.

---

## The migration is reversible at every step — side-by-side replacement

A verified property of Elementor, not a hope. `Widgets_Manager::register()` is:

```php
// elementor/includes/managers/widgets.php:266
$this->_widget_types[ $widget_instance->get_name() ] = $widget_instance;
```

A plain array assignment keyed on the widget name. **The last registration for a name wins,
silently, with no conflict.**

So `piecyfer-core` registers on `elementor/widgets/register` at **priority 20**, after Elementor
Pro's default 10, and can take over widgets **one at a time while Pro is still installed**:

```
implement one widget  →  capture --quick  →  compare against baseline
        ↓ 0 diff                                    ↓ any diff
   keep it, commit                        delete the class file — Pro's
                                          version is instantly back
```

Why this matters: the obvious approach — remove Pro, then rebuild 22 widgets — leaves the site
broken for the entire build, with no working reference to compare against and no way back if a
widget turns out to be harder than expected. This ordering inverts that. The site stays fully
working throughout, every widget is proven equivalent *before* it is relied on, and reverting a
single widget is one `git checkout` of one file.

Pro is only deactivated at the very end, when every widget it owns has already been replaced and
verified. At that point the final comparison should show **zero** difference, because nothing of
Pro's is still rendering anything.

### The loop is only valid with Elementor's caches cleared first

> **Update, 2026-08-14:** `e_element_cache` is now **off** — it was corrupting nav highlighting,
> form metadata and comment targets site-wide (see `STATUS.md`). The rest of this section is kept
> because the failure mode it describes is the single most expensive one this project has hit, and
> because `capture.js` still clears the remaining CSS and asset caches before every run.

`e_element_cache` stored the rendered HTML of an entire document in `_elementor_element_cache`
postmeta for 24 hours, along with the style and script handles that render enqueued. While that
cache was warm, **Elementor did not call the widgets at all** — it echoed the stored HTML and
enqueued from the stored list.

That breaks the verify loop in the worst possible direction: it produces **false passes**. A
capture can report a widget byte-identical when our widget never executed, and an edit to a
widget appears to do nothing. It cost a full debugging round here — three separate and genuinely
correct fixes to `search-form` and `theme-site-logo` looked completely inert, and the temptation
was to go rewrite code that was already right.

Disabling the experiment was initially rejected on the grounds that it changes which stylesheets a
page enqueues, and so moves the reference point. That was the wrong call, and only looked
defensible while the damage seemed limited to rendering: the cache was also serving the wrong post
id, the wrong form metadata and the wrong menu highlight. Correctness outranks a stable diff.

`capture.js` clears Elementor's caches before every run regardless, and treats a failure to do so
as fatal rather than as a warning. Note this also means **`piecyfer-core`'s own automatic cache
clearing is not sufficient** — its signature covers the widget and stylesheet *lists*, so adding a
widget invalidates the cache but editing one does not.

Two symptoms worth recognising, because they look like widget bugs and are not:

- **Stylesheet order differs but the set is identical.** The cached path enqueues from a stored
  list, the live path enqueues as each widget renders. Compare the sorted set of `id="*-css"`
  handles before believing a reordering is a regression.
- **A stylesheet appears that the baseline never had.** `post-8519.css` did. Template 8519 is
  pulled into the published Blog Post Template by an `[elementor-template]` shortcode, and the
  baseline's asset list had simply never recorded it. Clearing the cache made Elementor recompute
  correctly. That is a stale-cache defect in the baseline being fixed, not a regression.

## Dynamic tags are a prerequisite, not a late step

Originally scheduled at 4k. That was wrong, discovered while reading the actual saved settings
of the "easy" widgets rather than Pro's source.

**Every one of the theme title/logo widgets stores its content as a dynamic tag, not as text.**
For example `theme-post-title` in the Blog Post Template stores:

```json
"__dynamic__": { "title": "[elementor-tag id=\"\" name=\"post-title\" settings=\"…\"]" }
"title": "Add Your Heading Text Here"
```

The literal `title` is just Elementor's placeholder. The real content comes from the tag. Without
the `post-title` tag registered, the widget renders the placeholder — or nothing.

Worse: **dynamic tags are also used on free Elementor widgets.** Four of the nine tags below are
attached to a plain `heading.title` or a `column.background_image`. So removing Elementor Pro
degrades widgets we were not otherwise touching.

### The complete set — 9 tags, 31 uses

| Tag | Uses | Attached to |
|---|---|---|
| `post-featured-image` | 11 | `image.image`, `column.background_image` |
| `popup` | 6 | `button.link` — opens the Consultation CTA popup |
| `internal-url` | 3 | `button.link` |
| `archive-title` | 3 | `theme-archive-title.title`, **`heading.title`** |
| `site-logo` | 2 | `theme-site-logo.image` |
| `current-date-time` | 2 | **`heading.title`** |
| `site-title` | 2 | **`heading.title`** |
| `post-terms` | 1 | **`heading.title`** |
| `post-title` | 1 | `theme-post-title.title` |

Nine tags is a small, closed set — this is a scheduling correction, not a scope explosion. They
move to **step 4b**, ahead of the widgets that depend on them.

Note that `popup` is not really a tag in the usual sense: it renders a link that triggers
Elementor Pro's popup system, so those 6 uses are blocked on the popup work rather than on the
tag itself.

## Every Pro widget also ships its own stylesheet

Not in the original plan, and it changes what "replace a widget" means.

Elementor Pro carries **118 widget CSS files** in `assets/css/`, and each widget declares which
it needs:

```php
// blockquote.php
public function get_style_depends(): array { … }   // widget-blockquote, elementor-icons-fa-brands
```

So a replacement widget that emits the right markup still renders unstyled once Pro is gone.
Every widget below needs its stylesheet reproduced as well:

| Widget | Stylesheets it depends on |
|---|---|
| `blockquote` | `widget-blockquote`, FA brands icons |
| `search-form` | `widget-search-form`, FA solid icons |
| `call-to-action` | `widget-call-to-action`, `e-transitions` |
| `post-info` | `widget-post-info`, `widget-icon-list`, FA regular + solid |
| `gallery` | `widget-gallery`, `elementor-gallery`, `e-transitions`, `eicon-gallery-justified` |
| `testimonial-carousel` | `e-swiper`, `widget-testimonial-carousel`, `widget-carousel-module-base` |
| `nav-menu` | `widget-nav-menu` |
| `posts` / `archive-posts` | `widget-posts` |
| `form` | `widget-form` |

The widgets completed so far were unaffected precisely because they have no stylesheet of their
own — `template`, `theme-post-content` and `post-comments` carry no CSS, and the title and
site-logo widgets inherit the free Heading and Image styles. That is part of why they were the
right ones to start with, and it is also why they passed on the first attempt.

**These stylesheets will be written, not copied.** Elementor Pro is GPL, so copying is legally
fine, but taking asset files out of a nulled install is exactly the dependency this project
exists to remove — and it would leave the site carrying code of unknown provenance again. The
pixel harness makes writing them tractable: the target is defined by the baseline, and a rule
that is wrong shows up as changed pixels.

Practically this means the CSS-bearing widgets are larger than their PHP suggests. It does not
change the approach, only the estimate.

## Skins are needed too

`post-comments` stores `_skin: "theme_comments"`. Elementor's skin system is core (free), but the
skin itself is Pro's. Any replacement must register a skin under the same id or the saved value
points at nothing.

Same applies to `posts` and `archive-posts`, which store `_skin` alongside their
`vamtam_classic_*` settings — already flagged as the Hard items.

## Revised build order

Sequenced so that each step is independently verifiable against the baseline, cheapest and
lowest-risk first, and so the two hard items land when the foundations are proven.

| Step | Contents | Instances covered |
|---|---|---|
| **4a** | Plugin skeleton, registration, `AbstractWidget`, control-group helpers | — |
| **4b** | The 8 easy Pro widgets (#1–8 above) | 35 |
| **4c** | Your 2 ElementsKit-builder widgets + `elementskit-back-to-top`, `-social-share`, `-header-search` | 4 |
| **4d** | `elementskit-icon-box`, `elementskit-client-logo` | 14 |
| **4e** | `post-info`, `call-to-action`, `gallery` | 5 |
| **4f** | `nav-menu` + `ekit-nav-menu` (incl. VamTam subclass behaviour) | 26 |
| **4g** | `testimonial-carousel` (+ VamTam nav positioning) | 2 |
| **4h** | Control injections: Custom CSS, Motion FX scale, Sticky | 84 elements |
| **4i** | **Theme Builder** — locations, conditions, 10 templates | site-wide |
| **4j** | **`posts` + `archive-posts`** with the `vamtam_classic` skin | 15 |
| **4k** | **`form`** — fields, validation, actions, mail, spam protection | 9 |
| **4l** | Popup, global widgets, dynamic tags | 4 |
| **4m** | Absorb VamTam's free-widget control injections (Phase 5 prerequisite) | 20 values |
| **4n** | WhatsApp chat, FAQ accordion, OptinMonster replacements | 3 plugins retired |

Effort is concentrated in **4i (Theme Builder)**, **4j (VamTam posts skin)** and **4k (Form)**.
Steps 4a–4h are mechanical and should move quickly.

---

## Rules that make this pixel-perfect

1. **Same `get_name()`.** The `widgetType` string in the saved JSON is the join key. Change it
   and the widget orphans.
2. **Same control ids**, including responsive suffixes (`_tablet`, `_mobile`) and the
   `__globals__` / `__dynamic__` maps. A missing control id means Elementor drops that saved
   value on next save.
3. **Same rendered markup and class names.** The theme stylesheet targets
   `.elementor-widget-nav-menu`, `.elementor-nav-menu--main` and friends. New class names break
   styling invisibly — the HTML diff catches this, the screenshot may not.
4. **Same generated CSS.** Elementor compiles control values into
   `uploads/elementor/css/post-*.css` via each control's `selectors` array. Our controls must
   declare the same selectors, or the CSS file changes even when the HTML does not.
5. **No reference to any `ElementorPro\` class.** Pro's absence must not be an error condition.
6. **Every step gated by `node compare.js baseline <step>` returning 0.**
