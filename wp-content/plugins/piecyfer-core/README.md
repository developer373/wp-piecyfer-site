# PieCyfer Core

Owned replacements for this site's paid Elementor add-ons.

Built to retire `elementor-pro`, `elementskit`, `elementskit-lite` and
`vamtam-elementor-integration-tecnologia` without changing a single rendered pixel — and
without ever again depending on software that arrives from an unknown redistributor with a
backdoor in it.

- **No licence server.** Nothing to activate, nothing to expire, nothing to phone home.
- **No Composer step.** A hand-rolled autoloader means the plugin works the moment it is copied
  onto a server.
- **No Elementor Pro dependency.** Nothing in `src/` references an `ElementorPro\` class, so
  Pro's absence is a normal state rather than a fatal error.
- **Fail-safe.** Every widget renders inside a guard. A bug in one widget produces a logged
  error and a missing element for editors, never a white page for visitors.

## Status

**0.1.0 — skeleton.** Registers no widgets yet. Activating it is a deliberate no-op, verified by
capturing the site before and after and diffing: see `_project/snapshots/`.

That is intentional. The skeleton has to be provably inert before it is allowed to start
replacing things, because from here on every widget is added one at a time and each addition is
gated on a zero-difference pixel comparison.

## How a replacement widget works

Elementor stores each page as JSON. Every widget instance carries a `widgetType` string, and at
render time Elementor asks its registry which class is registered under that name.

So a replacement widget is not a new widget — it is a class registered under the **same name**,
accepting the **same control ids**, emitting the **same markup**. The saved page data never
changes; only the implementation underneath it does. That is what makes a pixel-identical
migration possible without touching a single page.

Three things are therefore load-bearing, and `Widgets\AbstractWidget` documents them in detail:

1. `get_name()` must return the exact `widgetType` from the saved JSON.
2. Control ids must match one-for-one, including `_tablet` / `_mobile` suffixes and the
   `__globals__` / `__dynamic__` maps — Elementor silently discards saved values that have no
   matching control.
3. Markup and CSS class names must match, because the theme stylesheet targets Elementor's own
   class names.

## Layout

```
piecyfer-core.php          bootstrap, requirement guards, version constants
src/
  Autoloader.php           PSR-4 style loader for PieCyfer\Core
  Plugin.php               registration, categories, admin notices
  Widgets/
    AbstractWidget.php     the contract above + conditional assets + render guard
```

## Build order

See `_project/02-WIDGET-REBUILD-SPEC.md`. Sixteen Elementor Pro widget types (91 instances) and
six ElementsKit types (19 instances) are in scope; the effort concentrates in Theme Builder, the
VamTam `posts` skin, and the Form widget.

## Verifying a change

```
node _project/pixel-tool/capture.js <label>
node _project/pixel-tool/compare.js baseline <label>
```

Exit code 0 means nothing moved. No step is finished until it does.

## Elementor compatibility

Constants in `piecyfer-core.php`:

- `ELEMENTOR_MIN` — below this the plugin refuses to load and says why.
- `ELEMENTOR_TESTED` — above this it still loads, but warns in the admin. Refusing to run would
  take the site down for a routine Elementor update, which is the exact fragility this plugin
  exists to remove.

After any Elementor update, run the pixel comparison before trusting it in production.
