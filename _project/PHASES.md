# What remains, in order

Each phase has an **entry** condition (do not start before this is true) and an **exit** condition
(do not call it done before this is true). The exits are deliberately things you can *run*, not
things you can assert.

Nothing here is speculative — every item traces to something already measured. Where a phase
depends on an unknown, the unknown is named.

---

## Phase A — prove the four built-but-inert components

Everything in this phase is already written and switched off. None of it has ever executed.
**Syntax-clean is not working**, and that distinction is the whole phase.

| Component | Where | Switch |
|---|---|---|
| Theme Builder | `plugins/piecyfer-core/src/ThemeBuilder/` | `PIECYFER_THEME_BUILDER` + `Module::boot()` |
| Frontend JS | `plugins/piecyfer-core/assets/js/`, `src/Frontend.php` | `PIECYFER_CORE_FRONTEND_JS` + `Frontend::init()` |
| Popup | `plugins/piecyfer-core/src/Popup/` | `PIECYFER_POPUP` + filter + `Module::boot()` |
| Form back end | `plugins/piecyfer-core/src/Forms/` | not wired into `Plugin.php` |
| `piecyfer-theme` | `wp-content/themes/piecyfer-theme/` | not activated |

**Entry:** all 16 Pro widgets registered and green on both gates.

**The hard part:** the Theme Builder and the JS layer cannot be tested while Pro is active. Pro
double-binds every JS handler, and three Pro modules make our locations manager fatal on sight.
So Phase A splits:

- **A1 — testable now:** the form back end (hook `wp_mail`, never send), and `piecyfer-theme`
  (switch the theme, capture, compare, switch back). Both are independent of Pro.
- **A2 — only testable during the cutover:** Theme Builder, JS layer, popup. Their proof is the
  behaviour harness, run immediately after Pro goes.

**Exit:** A1 verified against `ref2-a` with zero diffs. A2 has a written, rehearsed rollback: one
`git revert` of one commit puts Pro back.

### Known defects to fix in this phase

1. `Plugin::maybe_clear_elementor_cache()` runs on front-end `init` and deletes every
   `_elementor_css` meta, so the request it fires on renders with no header or footer stylesheet.
   Reproduced. On a live site this is the first visitor after every deploy.
2. The form's reCAPTCHA v3 token has no JS handler. The behaviour run proves it: no admin-ajax POST
   is attempted, no `g-recaptcha-response` is produced. Seven of nine forms would have every
   submission rejected server-side, silently.
3. 32 real CVs under `wp-content/uploads/elementor/forms` fetch with HTTP 200 and no
   authentication. Filenames are `uniqid()` — time-derived and guessable. Personal data.
4. `post-7718.css` is in `<head>` and nothing we can find enqueues it. If it is Pro's, the popup
   loses all styling at cutover. **Trace this before Phase B.**
5. The reCAPTCHA v2 and v3 keys share the same `6LcmQX` prefix, which usually means one key was
   pasted into both fields. Verify against the Google console. (The form back end already handles
   this safely — a missing score is treated as a configuration fault and the enquiry is accepted,
   where Pro would reject every submission on all seven protected forms.)
6. **`vamtam-has-theme-widget-styles` — the largest unaddressed risk in the swap.** A live page
   carries it on 27 elements; only 3 come from `piecyfer-core`. It sits on every widget wrapper and
   gates most of the theme's CSS, and it belongs to the *companion plugin*, not the theme. If
   `piecyfer-core` does not emit it on the same elements, most of the site's styling vanishes the
   moment that plugin goes.

### Three one-line decisions the theme work left open, deliberately

- `piecyfer-theme/style.css` says `Author: PieCyfer`, which makes the VamTam companion plugin
  self-disable on activation. If you want the two-step swap — theme first, plugin second, each
  verified separately — that line has to say `VamTam` for exactly one commit.
- The tokens `<style>` id changes from `vamtam-theme-options` to `piecyfer-tokens`: an intentional
  markup difference on 39 pages. Zero-diff discipline argues for keeping the old id through the
  swap commit and renaming afterwards.
- `print_late_styles()` mid-body is reproduced on purpose so the swap stays zero-diff. It is a real
  defect — two render-blocking stylesheets inside `#main` on 17 pages — and should be deleted in a
  separate commit once the swap is verified.

---

## Phase B — the cutover

**This is the only irreversible-feeling step in the project, and the only one that cannot be done
incrementally.** Three Pro modules (`popup`, `custom-code`, `floating-buttons`) hook
`elementor/theme/register_locations` with callbacks type-hinted on Pro's own `Locations_Manager`.
Our manager firing that action while Pro is installed is a `TypeError` on every page. There is no
side-by-side mode and no partial cutover.

**Entry:** Phase A complete, defect 4 traced, and a full `ref` capture taken immediately before.

**The commit does all of this at once:**
1. Deactivate Elementor Pro.
2. Enable the Theme Builder, the JS layer and the popup.
3. Switch the theme to `piecyfer-theme`.
4. Rename `wp_posts` id 2547 (`post_name = 'tecnologia'`) — WordPress resolves Elementor's
   `custom_css` post by the *active* stylesheet slug, so the theme switch orphans it otherwise.
5. Flush Elementor's CSS cache. **Before comparing, not after** — otherwise the comparison runs
   against files Pro generated and the document-type registration goes untested.

**Exit, all three, in this order:**
- `which-implementation.php` exits 0.
- `compare.js` against the pre-cutover reference: 0 visual changes; every markup difference
  explained and attributable to an intended swap.
- `behaviour-tool/run.js after-cutover --compare pro-active`: **25 of 25**. This is the one that
  matters. The pixel harness cannot see a dead burger, a frozen carousel or an inert popup — the
  `--simulate-no-pro` run already proved exactly which nine tests go red without Pro's JS.

**Rollback:** `git revert` of the single cutover commit, then flush caches.

---

## Phase C — remove what is left

**Entry:** Phase B exited green and the site has been left alone for a day.

Delete, in this order, capturing between each: `elementor-pro`, `elementskit` (the nulled Pro one —
**keep `elementskit-lite`**, every ElementsKit widget this site uses ships in the free plugin),
`vamtam-elementor-integration-tecnologia`, `themes/tecnologia`, `object-cache-pro`, `redis-cache`,
`optinmonster`.

**Exit:** no unlicensed package remains on disk; `ref` comparison clean; behaviour 25/25.

---

## Phase D — performance

**Entry:** Phase C done. Not before — measuring a stack you are about to delete is wasted work.

Caching was removed as part of C, deliberately: Object Cache Pro is nulled and WP Rocket was
redundant. Replace with something owned or free. Then the real wins, which are already visible in
the captures: image formats and sizes, the render-blocking chain in `<head>`, and the fact that
`e_optimized_css_loading` is inactive.

**Exit:** measured before/after on the same URLs. No regression in the pixel comparison.

---

## Phase E — technical SEO

**Entry:** Phase D done.

AIOSEO stays; it is the live SEO plugin and it is licensed. This phase is the things a plugin does
not do: the `<head>` ordering, canonical and hreflang correctness, structured data against the real
templates, XML sitemap coverage of the archive routes that had no coverage until recently, and the
404 route.

**Exit:** validated against Google's own tools, not against a plugin's dashboard.

---

## Phase F — hardening

**Entry:** Phase E done.

The audit's §7 list, plus what this work uncovered: file upload storage outside the web root,
authenticated download of CVs and a retention policy, rate limiting on the form endpoint (Pro had
none — no nonce, no capability check, no limit), disabling file editing in wp-admin, and rotating
credentials again at deploy.

**Exit:** a clean pass of the same scan that found the original infection.

---

## Phase G — the live site

The live site is a **separate, still-infected installation**. Nothing in this repo has touched it.
`_project/scripts/piecyfer-malware-cleanup.php` is written and proven against the local copy, and
`01-REBUILD-PLAN.md` §1.2 lists the credential rotation that must go with it.

Deploy is its own decision and should not be folded into any phase above.
