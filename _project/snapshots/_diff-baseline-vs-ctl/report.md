# Pixel & markup comparison

**baseline** → **ctl**

> Subset comparison: `ctl` covers 1 of the 39 pages in `baseline`. 152 artefact(s) in the baseline were not re-captured this run and are not counted as missing. Run the full set before closing out a phase.

| | |
|---|---|
| Screenshots compared | 3 |
| Pages with markup changes | 0 |
| Screenshots with visual changes | 0 |
| Known-flaky artefacts (not failing) | 1 |
| Missing artefacts | 0 |
| New console errors | 0 |
| Newly broken assets | 0 |
| **Verdict** | ✅ **IDENTICAL** |

## Markup differences

_None — all markup identical._

## Visual differences

_None — all screenshots identical within tolerance._

### Known-flaky artefacts (reported, not failing)

- **our-team@desktop.png** — 2,905 px differ (0.040%) → `our-team@desktop.diff.png`
    _known flaky: Elementor motion-fx decorative dot layer renders its background at a slightly different scale between runs. The transform is already pinned by FREEZE_CSS and the markup is byte-identical, so what varies is how the layer's background is scaled against a container whose height settles marginally differently._

_Each of these was proven to differ between two captures of an unchanged site. If one starts differing by a much larger amount than recorded, re-run the control experiment rather than assuming it is still the same flake._

## New console errors

_None._

## Newly broken assets (404 / failed requests)

_None._
