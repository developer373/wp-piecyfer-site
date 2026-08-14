# PieCyfer Core — frontend JavaScript layer

This directory is the missing half of the widget port.

Every widget in `src/Widgets/` was rebuilt for **markup** and **`data-settings`**,
and both are correct. But an Elementor Pro widget is only half server-side. The
other half is a frontend *handler* that reads that `data-settings` payload and
constructs the behaviour. Pro ships those handlers in `elements-handlers.js`
plus a set of lazy-loaded per-widget bundles. This plugin shipped none of them.

The result is a site that photographs perfectly and does nothing: the mobile
burger is inert, the testimonial carousel is a static column, the gallery has no
layout at all. **The pixel harness cannot see any of this** — which is why it
went unnoticed. Treat every gap listed at the bottom of this file as something
that will ship silently if it is not closed.

---

## The enable switch — default OFF

Nothing here loads unless it is deliberately switched on, and `src/Frontend.php`
is not wired into `Plugin.php` at all. Two independent steps are required:

**1. Wire it in.** Add to the `Plugin` constructor, next to `ProStyleGuard::init();`:

```php
Frontend::init();
```

**2. Switch it on.** Either define the constant in `wp-config.php` —

```php
define( 'PIECYFER_CORE_FRONTEND_JS', true );
```

— or add the filter from a mu-plugin:

```php
add_filter( 'piecyfer_core/frontend_js/enabled', '__return_true' );
```

The constant wins when defined, so a stray filter cannot re-enable a layer that
`wp-config.php` has explicitly switched off. With neither set, `Frontend::init()`
returns immediately: no script is registered, nothing is enqueued, not even the
`smartmenus` handle.

### Do not switch this on while Elementor Pro is still active

Pro registers the *same* handlers against the *same* `frontend/element_ready/*`
hooks. Both sets would run and every widget would initialise twice: two
SmartMenus instances on one `<ul>`, two click bindings on one burger (so it opens
and instantly closes again), two Swiper instances on one container. The switch is
exactly what makes it safe for this code to sit in the tree while Pro is still
doing the work. Order of operations: wire in → switch on → deactivate Pro.

---

## Architecture

### How handlers attach

Elementor's frontend walks the DOM and, for each `[data-element_type]`, fires

```
frontend/element_ready/global
frontend/element_ready/widget
frontend/element_ready/<widget_type>          e.g. nav-menu.default
```

through its own `hooks.doAction()`. A handler is just a callback on the last of
those. Pro registers via `elementorFrontend.elementsHandler.attachHandler()`; we
use the two public calls that method expands to —
`elementorFrontend.hooks.addAction()` + `elementorFrontend.elementsHandler.addHandler()`
— because that lets us wrap construction in a `try`/`catch`. See "Error
isolation" below.

Load order is enforced by `wp_register_script()` dependencies:

```
jquery
  └─ elementor-frontend            defines elementorFrontend + elementorModules
       └─ piecyfer-frontend        this dir: registry + bootstrap
            └─ piecyfer-handler-*  each calls piecyferFrontend.register()
```

Handlers `register()` at parse time but are only **built** on
`elementor/frontend/init`, because the base classes they extend do not exist
until Elementor's bundle has run.

### We reuse Elementor free's module system, deliberately

Every handler extends `elementorModules.frontend.handlers.Base` or
`…handlers.SwiperBase` from Elementor **free**. Nothing here reimplements them.
That base class is what supplies `getElementSettings()` (reading `data-settings`),
`getElementSettings()`'s responsive helpers, `onElementChange()` for live editor
previews, `onDestroy()`, and the `elements` / `selectors` conventions. Rolling
our own would have meant reimplementing the editor integration too, and getting
it subtly wrong.

Two extension styles appear here, and the choice is not stylistic:

| Base | Extend with | Why |
|---|---|---|
| `handlers.Base` | `Base.extend({ … })` | It is a function-constructor built by `Module.extend`. Matches Pro. |
| `handlers.SwiperBase` | `class … extends` | It is a real ES6 class. `Module.extend`'s helper calls the parent **without `new`**, which throws on a class constructor. |

### Error isolation

`hooks.doAction()` runs its callbacks in a plain loop with no error handling.
**One handler that throws silently prevents every handler registered after it
from ever running** — including third-party ones (VamTam's `vamtam-nav-menu`,
ElementsKit's) that have nothing to do with us.

So `piecyfer-frontend.js` wraps both the factory call and the per-element
construction, and logs to `console.warn` with a `[piecyfer-core]` prefix instead
of throwing. Individual handlers additionally decline gracefully when a library
they need is absent (`EGallery`, `$.fn.smartmenus`, `elementorFrontend.utils.swiper`)
rather than exploding on the first dereference.

Each handler is also a no-op when its element is missing: `getDefaultElements()`
returns empty jQuery collections, and `.on()` / `.addClass()` on an empty
collection does nothing.

### No build step

Plain ES5/ES6 that the browser runs as written. No bundler, no npm, no runtime
dependency beyond what Elementor free and WordPress already load. Verify with:

```
node --check assets/js/piecyfer-frontend.js
node --check assets/js/handlers/*.js
```

jQuery is used **only** where Elementor's own handler for that widget uses it —
which is everywhere, because `Base` hands you `this.$element` as a jQuery object
and SmartMenus is a jQuery plugin. This matches Pro exactly; it is not a
preference.

---

## Where the third-party libraries come from once Pro is gone

This is the question with the most dangerous wrong answer, because both failures
are completely silent.

### `smartmenus` — **was Pro's. Now ours.**

SmartMenus is a third-party MIT library. **Elementor free does not ship it.**
Only Pro does, at `elementor-pro/assets/lib/smartmenus/`, registering the
`smartmenus` handle in `elementor-pro/plugin.php`.

`NavMenuWidget::get_script_depends()` already returns
`array( 'smartmenus', 'vamtam-nav-menu' )`. The moment Pro is deactivated that
handle stops existing and `wp_enqueue_script( 'smartmenus' )` becomes a **silent
no-op** — no error, no warning, and every sub-menu in the mobile nav simply stops
opening.

Fix: the library is vendored at `assets/lib/smartmenus/` (v1.2.1, MIT,
byte-identical to Pro's copy) and `Frontend::register_smartmenus()` claims the
same handle — but only if `wp_script_is()` says nobody else has, so Pro keeps
ownership while it is installed and nothing changes until it is not. Same
defensive shape as `NavMenuWidget::register_vamtam_script()`.

> The handle name is not ours to choose. It must be exactly `smartmenus` or the
> widget's declared dependency resolves to nothing.

### `swiper` — **free's, and it always was. Version 8.4.5.**

Elementor free ships two copies and picks between them with the
`e_swiper_latest` experiment:

| Experiment | Path | Version | Container class |
|---|---|---|---|
| off | `assets/lib/swiper/` | 5.3.6 | `swiper-container` |
| **on** | `assets/lib/swiper/v8/` | **8.4.5** | **`swiper`** |

The captured baseline settles which one this site uses:

```html
<div class="elementor-main-swiper swiper swiper-initialized
            swiper-horizontal swiper-pointer-events swiper-backface-hidden">
```

`swiper` with no `-container`, plus `swiper-pointer-events` and
`swiper-backface-hidden`, are v8 markers. **Swiper 8.4.5.**

The handler never touches `window.Swiper` directly. It constructs through
`elementorFrontend.utils.swiper`, free's own async wrapper, which lazy-loads the
script via `assetsLoader` and rewrites the breakpoint map from Elementor's
max-width semantics to Swiper's min-width ones (`handleElementorBreakpoints`).
Going through the wrapper is what keeps *free* owning the version choice, so the
day someone flips the experiment this code follows automatically.

### `e-gallery` — **free's, unaffected**

`EGallery` lives at `elementor/assets/lib/e-gallery/js/` and free registers it as
the `elementor-gallery` script handle. `GalleryWidget::get_script_depends()`
already names it. Nothing to do — see the gallery handler for what Pro adds *on
top* of it.

### `vamtam-nav-menu` — VamTam's, and it self-attaches

VamTam's script registers its own handler on `elementor/frontend/init`. It is not
ours to reproduce and it survives Pro's removal. `NavMenuWidget` already
re-registers the handle.

Ordering note: our handlers are enqueued globally on
`elementor/frontend/after_enqueue_scripts`, VamTam's during widget render, so
ours registers its hook first and therefore runs first — SmartMenus is
initialised before VamTam's handler inspects the menu. That is the same ordering
Pro produces today.

---

## The handlers

| File | Widget | Reproduces |
|---|---|---|
| `handlers/nav-menu.js` | `nav-menu.default` | `nav-menu.1a66dd30011cc2fc8842.bundle.js` + the SmartMenus patches from Pro's nav-menu module constructor |
| `handlers/testimonial-carousel.js` | `testimonial-carousel.default` | `carousel.298f1fc9c115422aad0e.bundle.js` (both `CarouselBase` and the `TestimonialCarousel` subclass, merged) |
| `handlers/search-form.js` | `search-form.default` | `search-form.8941aba5c12cdb05fb7c.bundle.js` |
| `handlers/gallery.js` | `gallery.default` | `gallery.b7d55bc976e04f751975.bundle.js` |

Behaviour was established from three sources, in this order of authority:

1. **Pro's own unminified bundles**, listed above — the definitive source.
2. **The captured baseline HTML** (`_project/snapshots/baseline/html/`) for the
   real `data-settings` payloads and post-JS DOM state, which is how the Swiper
   version and the `skin: "classic"` fact were pinned down.
3. **`_project/behaviour-tool/tests.js`** (the sibling agent's harness) for what
   the observable contract is. Its `results/pro-active.json` **did not exist** at
   the time of writing — see "Known gaps".

### Deliberate divergences from Pro

Only two, both to avoid throwing. Everything else is a faithful port.

1. **`nav-menu.js` — `stretchMenu` is bound.** Pro passes `this.stretchMenu`
   unbound to `addListenerOnce(..., 'resize', ...)`. On the front end that
   reduces to `$window.on('resize', cb)`, so jQuery invokes it with
   `this === window` and the first line, `this.getElementSettings(…)`, throws a
   TypeError on **every window resize**. Elementor's base does not auto-bind
   (`Module.extend` only copies the prototype), so this is a genuine latent bug
   in Pro 3.25.0. Reproducing it would mean shipping a handler that throws inside
   a resize listener. Observable difference on this site: **none** — `full_width`
   is absent from every nav-menu's `data-settings`, so `stretchMenu` takes the
   `reset()` branch, a no-op on an element that was never stretched.

2. **Missing-key guards.** Pro dereferences `.size` and `.value` on settings
   straight from `data-settings`. On the front end `getElementSettings()` returns
   that raw object with **no defaults merged in**, so any absent responsive
   slider is a live TypeError. `|| {}` guards were added in
   `getSpaceBetween()`, `getGallerySettings()` and the nav-menu `submenu_icon`
   read. Every key involved is present on this site today, so these change
   nothing now; they stop a future edit turning into a page-wide handler outage.

---

## Widgets that genuinely need no JavaScript

Stated explicitly rather than assumed, and verified two ways: **(a)** the widget
name does not appear in Pro's complete `attachHandler()` inventory, and **(b)**
the Pro widget class declares no `get_script_depends()`.

Pro's full inventory is 39 element names. Removing the ones this site does not
use, the only ones we own are `nav-menu`, `search-form`, `gallery` and
`testimonial-carousel` — all four are implemented above.

| Widget | Handler? | `get_script_depends()`? | Verdict |
|---|---|---|---|
| `call-to-action` | no | none | **No JS.** Its hover behaviour is entirely CSS — the `elementor-animated-item--*` classes are rendered server-side and animated by the `e-transitions` stylesheet. Confirmed by the baseline HTML, where the widget carries **no `data-settings` attribute at all** — meaning not one of its controls is `frontend_available`, which is conclusive. |
| `post-info` | no | none | **No JS.** Static list markup. |
| `blockquote` | no | none | **No JS.** The tweet button is a plain `<a>` to a share URL, not a scripted popup. (Pro's separate `share-buttons` widget *does* have a handler — this is not that widget.) |
| `post-comments` | no | none | **No JS of Pro's.** Threaded reply comes from WordPress core's own `comment-reply.js`, enqueued by core when `thread_comments` is on. Unaffected by Pro's removal. |
| `template` | no | none | **No JS.** It renders another document inline; that document's own widgets get their own handlers through the normal `element_ready` walk. |
| `theme-post-title`, `theme-archive-title`, `theme-post-content`, `theme-site-logo` | no | none | **No JS.** Pro's entire `theme-elements` frontend module attaches exactly one handler — `search-form` — and its `theme-builder` module attaches only `archive-posts`. |

---

## Known gaps — read this before signing anything off

**1. `pro-active.json` did not exist when this was written.** The sibling
agent's `_project/behaviour-tool/results/` directory was empty. Behaviour was
therefore derived from Pro's source and from the *assertions* in `tests.js`, not
from a recorded run. **Every handler here is unvalidated against real recorded
behaviour.** Once `pro-active.json` lands, diff it against a run with Pro off and
this switch on. That comparison is the actual acceptance test for this layer.

**2. Nothing here has been executed.** JS is `node --check` clean and PHP is
`php -l` clean, but syntax is not behaviour. Everything below can only be
verified with Pro deactivated and the switch on:

- that the burger flips `aria-expanded` / `.elementor-active` / `aria-hidden` and
  gives the dropdown a non-zero height, both ways;
- that SmartMenus loads from *our* vendored copy and creates `a.has-submenu`;
- that Swiper constructs, reports `swiper-initialized`, and autoplays;
- that `EGallery` builds a grid layout with the right column counts per
  breakpoint;
- that no handler double-binds and none throws;
- that VamTam's and ElementsKit's handlers still run after ours.

**3. `Frontend::init()` is not called.** By design — `Plugin.php` was not to be
modified. Until somebody adds that line, this entire directory is dead code.

**4. Handlers are enqueued globally, not per widget.** Pro does the same with
`pro-elements-handlers`, so this matches today's behaviour, but it is not ideal:
naming each handler in the matching widget's `get_script_depends()` would load
them only where they are used. That requires editing `src/Widgets/*`, which this
work was explicitly forbidden from touching. Roughly 30 KB unminified on pages
that need none of it. Worth revisiting once widget ownership settles.

**5. No minified builds.** Files are served unminified. Fine for correctness,
suboptimal for page weight. There is deliberately no build step, so minification
would mean committing generated files.

**6. The editor has not been considered beyond `onElementChange`.** The handlers
implement Pro's editor-side update methods, but nobody has opened Elementor's
editor with Pro off. Editor preview is a separate risk surface from the front end.

**7. Out of scope, but flagged — `posts` and `archive-posts`.** Both appear on
this site as `posts.vamtam_classic` and `archive-posts.vamtam_classic`. Good news,
and worth recording for whoever owns `07-POSTS-SPEC.md`: **every one of Pro's
`posts` / `archive-posts` handler attachments is scoped to Pro's own skins**
(`classic`, `full_content`, `cards`, `archive_classic`, `archive_full_content`,
`archive_cards`). `vamtam_classic` matches none of them, so **no Pro handler
attaches to these widgets on this site today** — there is nothing to reproduce.
Pagination there is server-rendered links, not AJAX load-more. This was verified,
not assumed, but it is another agent's call to confirm.

**8. Out of scope — `form`.** `FormWidget` is ported and Pro's form handler
(`form.3b797cf593ad0ec04b83.bundle.js`) is substantial: validation, AJAX submit,
reCAPTCHA v3 token minting, step navigation. `_project/behaviour-tool/tests.js`
tests all of it. It belongs to the Forms agent per the brief, and **no form
handler is provided here**. If nobody is building it, the contact form will
validate nothing and submit nothing the moment Pro is deactivated. This is the
single largest remaining hole in the frontend layer.
