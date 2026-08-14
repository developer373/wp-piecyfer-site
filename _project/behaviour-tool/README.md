# behaviour-tool

A Playwright interaction harness that records **what the site does**, so that
replacing the nulled Elementor Pro can be proven not to have broken anything a
screenshot cannot see.

Companion to `../pixel-tool`, not a replacement for it. Run both.

---

## Why this exists

`../pixel-tool` is a good harness and it proves something real: the site still
*looks* right, down to the pixel, with the markup diffed line by line. It is also
completely blind to behaviour, and in a way that gets worse the harder you look:
it *deliberately* freezes the very things this tool exercises. Read
`capture.js`'s `FREEZE_CSS` — every animation is zeroed, every Swiper track is
pinned to slide zero, autoplay is stopped on every polling pass. It has to be:
motion is noise to a screenshot. But it means the pixel harness would report
`IDENTICAL` for a site whose carousels had stopped moving entirely.

The same is true of everything else that matters:

| Regression | What a screenshot sees |
|---|---|
| Mobile burger no longer opens the menu | identical |
| Carousel arrows render but do nothing | identical |
| Search box submits to nowhere | identical |
| Contact form stops validating and accepts blanks | identical |
| Consultation popup CTA becomes a dead `#` link | identical |
| Accordion/toggle panels never expand | identical |

Our replacement plugin ships **no JavaScript at all**. Elementor Pro's frontend
handlers are what construct all of the above. The moment Pro is deactivated,
every Pro widget will render perfectly and do nothing. This tool records what
"doing something" looks like **while Pro still works**, so the difference after
the swap is a measurement rather than an opinion.

---

## Running it

```bash
cd _project/behaviour-tool

# record a run
node run.js pro-active

# record a run and fail if anything regressed against a named baseline
node run.js after-phase5 --compare pro-active

# just the nav tests, while iterating
node run.js scratch --only nav

# watch it happen
node run.js scratch --only popup --headed
```

Results land in `results/<label>.json`. Exit code `0` means every test passed
and (with `--compare`) nothing regressed; `1` means a failure or a regression;
`2` means the run itself was invalid — bad arguments, missing baseline, or
nothing executed.

### Flags

| Flag | Effect |
|---|---|
| `--compare <label>` | fail on any behaviour that regressed against that stored run |
| `--only <substring>` | run tests whose id or url contains the substring; **errors** if it matches nothing |
| `--simulate-no-pro` | block Elementor Pro's frontend scripts in the browser (see below) |
| `--headed` | show the browser |
| `--clear-cache` | clear Elementor's rendered-HTML cache first (see below) |

### Dependencies

There is no `npm install` step. `run.js` requires Playwright by relative path
from `../pixel-tool/node_modules/playwright`, which is already installed and
already has its Chromium downloaded.

This is not about disk space. Two independent Playwright installs drift onto two
different Chromium builds, and once that happens "the behaviour changed" and
"the browser changed" become impossible to tell apart. Sharing one install means
both harnesses always report on the same browser, and the result file records
its version. If `../pixel-tool/node_modules` is ever missing, `run.js` exits `2`
telling you to `npm install` there.

---

## Proving the harness can fail: `--simulate-no-pro`

**"25/25 passed" means nothing until you have watched the suite go red for the
right reasons.** A test that cannot be shown to fail is indistinguishable from
one that always returns true, and this project has already been bitten three
separate times by results that were green because the test never ran.

`--simulate-no-pro` aborts every request for a script under
`wp-content/plugins/elementor-pro/assets/`. **Nothing on the server changes** —
no plugin is deactivated, no option touched, no row written. The browser simply
never receives Pro's JavaScript, which is exactly the state the site will be in
once Pro is replaced by a plugin that ships none.

```bash
node run.js no-pro-preview --simulate-no-pro --compare pro-active
```

Its second use is as a **preview**: run it against the live Pro-active site and
you get the precise list of what will break, with evidence, before anybody
deactivates anything.

It is *not* a substitute for a real post-migration run — it removes Pro's
scripts but leaves Pro's server-rendered markup in place, so it under-reports
anything that breaks because the markup changed.

### What it shows today

Against the current site, with Pro's scripts blocked:

**Breaks** — mobile/tablet burger menu (never opens), mobile sub-menus
(SmartMenus gone, so `a.has-submenu` is never even created), both testimonial
carousels (no Swiper instance at all), carousel autoplay, the reCAPTCHA v3
token, and the consultation popup (no modal is ever inserted).

**Survives** — the ElementsKit desktop mega-menu, the ElementsKit mobile modal
search, the ElementsKit back-to-top, the free Elementor toggle/accordion, both
classic search forms (they are plain GET forms and need no JS), and empty-form
validation (native HTML5 `required` still bites). No page gains an uncaught JS
error.

That split is the actual scope of work for the JS side of the replacement.

---

## Design decisions

Most of these are lessons the pixel harness paid for; a few are this tool's own.

### Serial, always

Concurrency is 1 and there is no flag to raise it. `capture.js` documents what
happens on this machine at concurrency 3: per-page time goes from 8s to 60–90s
and Chromium starts compositing before it has finished painting. For a
behaviour test the equivalent failure is a `waitForFunction` timing out under
contention, i.e. a false regression — the exact failure mode that makes a
harness worthless, because a harness that cries wolf gets ignored and then it
protects nothing.

The pixel harness may be capturing at the same time; keeping this at 1 is also
how the two stay out of each other's way.

### Wait on conditions, never on clocks

Every wait is a `page.waitForFunction` over a DOM predicate, and **the
predicate's English description is written into the result file** whether it
succeeded or timed out. So a failure reads

```
waited for: dropdown reports expanded AND has non-zero height
  expected true within 15000ms, observed NEVER TRUE (timed out after 15018ms)
```

rather than "nav-toggle-mobile: fail".

Two fixed dwells survive, both marked in `tests.js`, and both are cases where
the assertion is *"nothing happened"* — an empty form submit that must not
navigate. There is by definition no condition to wait for when you are asserting
the absence of an event, so a short explicit dwell is the honest construct.

### A test that cannot find its target FAILS

This is the fourth time this project has had to write that sentence down. The
pixel tool shipped a green `IDENTICAL` for an `--only` that matched no URLs, a
green pass for widgets served from a stale element cache, and nearly baked a
"permanently dead" font into every capture. All three were the same bug: a
result that looked like success but described a test that never executed.

So, at five separate levels:

1. **`t.exists(selector, description, min)`** throws if fewer than `min`
   elements match, and records the count it saw either way.
2. **`t.visibleOne(selector, description)`** throws unless *exactly one* match
   is actually visible at the current viewport. This site renders the same
   header widget several times, one per breakpoint, with the others collapsed to
   0×0 — so `querySelector` silently returns whichever is first in source order.
   Indexing into the list would keep passing after the real instance
   disappeared. It also caught a genuine authoring mistake on its first run (see
   *Defects found* below).
3. **Existence is asserted before behaviour.** `carousel-testimonial-arrows`
   asserts a live Swiper instance exists and returns early if not, so the arrow
   assertions can never compare `null` to `null` and call it agreement.
4. **A test that records zero assertions is marked `error`**, in `run.js`, after
   `run()` returns. Even if every helper above were bypassed, a test that falls
   through without asserting cannot report success.
5. **A run with zero tests exits 2**, and an `--only` that matches nothing exits
   2 with the list of valid ids.

`--compare` closes the same gap from the other side: a test present in the
baseline and absent from the current run is reported as a regression, not
skipped. Renaming a test id is a real event and has to be acknowledged.

### Every assertion records what it asserted and what it saw

Each check stores `{name, assertion, expected, observed, pass}`, and each test
additionally carries its `asserts` and `why` strings straight from `tests.js`
into the result file. The point is that a diff months from now explains itself
without anyone re-reading the code, and that a regression report tells you what
the behaviour was *for*:

```
* nav-toggle-mobile  [behaviour-regressed]
  Header burger opens and closes the dropdown menu (mobile)
  aria-expanded flips to true: expected equals "true", observed "false" | ...
  why it matters: The burger is bound by Elementor Pro's nav-menu frontend
  handler. With no JS the markup still renders a burger icon that does nothing —
  visually identical, functionally dead.
```

`t.note()` records a fact **without** asserting on it, for things whose value is
expected to change — whether `elementorProFrontend` exists, for instance. After
de-nulling it is *supposed* to disappear, and failing the suite over that would
tell us nothing we did not already know. The individual behaviour tests are what
decide.

### Nothing is written to the site

Every non-GET request to `localhost` is aborted in the browser before it leaves,
and the attempt is recorded. No enquiry is ever delivered, no comment posted, no
mail sent, no row written.

This is what lets `form-recaptcha-v3-token` exercise a **real** submit: it fills
the form validly, clicks submit, lets Elementor Pro run its handler and call
`grecaptcha.execute()`, then catches the resulting `admin-ajax.php` POST at the
network layer and asserts that its multipart body carries a non-empty
`g-recaptcha-response`. The token is real (≈558 characters in the reference
run); the enquiry never exists.

The blocking is not a special case inside the form tests — it is unconditional
for the whole run, so no future test can accidentally send one either. Note that
`form-validation-blocks-empty-submit` asserts that **zero** non-GET requests were
attempted, which is a stronger claim than "the request was blocked".

### Elementor's rendered-HTML cache

`capture.js` clears `_elementor_element_cache` before every capture, because the
`e_element_cache` experiment is active on this site and caches a document's
rendered HTML for 24h — while it is warm, Elementor never calls the widgets, so
a capture can "prove" a widget correct when the widget never ran.

This tool does **not** clear it by default, because clearing is a database write
and this tool's brief is to touch nothing. Behaviour tests are also much less
exposed: they assert on what the JavaScript does to the live DOM, and the
JavaScript is not what gets cached.

But the markup those handlers bind *to* is cached. After a rebuild that changes
a widget's output, either pass `--clear-cache` or run a `pixel-tool` capture
first (which always clears), or you are testing yesterday's HTML.

### Not `reducedMotion: 'reduce'`

The pixel tool sets it; this one deliberately does not. "Does it actually move"
is half of what is being measured here. Every wait is on a settled end state, so
the animations cost time, not determinism.

---

## The tests

25 tests, 168 individual assertions.

| id | viewport | what it proves |
|---|---|---|
| `env-frontend-stack` | desktop | jQuery, elementorFrontend, SmartMenus and Magnific Popup are loaded. Pro's presence is *recorded*, not asserted. |
| `nav-toggle-mobile` | 375 | Burger opens the dropdown (aria-expanded, `.elementor-active`, aria-hidden, non-zero height, links on screen) and closes it again — all four reversed. |
| `nav-toggle-tablet` | 768 | Same, at the breakpoint where dropdown mode begins. |
| `nav-submenu-mobile` | 375 | A parent item inside the dropdown expands its sub-menu (SmartMenus). |
| `nav-desktop-megamenu-hover` | 1920 | **Both** ElementsKit mega-menu parents open on hover and close when the pointer leaves. The count of parents is itself asserted. |
| `carousel-testimonial-init` | 1920 | Both testimonial carousels have a live `Swiper` **instance object**, not merely the `swiper-initialized` class — the class can be faked by markup, the object cannot. |
| `carousel-testimonial-arrows` | 1920 | Next advances `Swiper.realIndex` by exactly 1 and moves the track; prev returns it exactly. |
| `carousel-autoplay` | 1920 | Every carousel configured `autoplay:"yes"` reports `autoplay.running`, **and** one is observed advancing on its own with no interaction. The pixel harness freezes exactly this; nothing else in the project checks it. |
| `search-classic-desktop` | 1920 | The visible header search form reaches `?s=software` and lands on a search-results document. |
| `search-classic-tablet` | 768 | Same, for the other instance — the one `#pie-search` does *not* select. |
| `search-ekit-modal-mobile` | 375 | At 375px **no** classic search form is reachable; the ElementsKit modal opens and submits instead. |
| `form-validation-blocks-empty-submit` | 1920 | Empty submit leaves the form invalid, does not navigate, and sends **nothing** to the server. |
| `form-recaptcha-v3-token` | 1920 | A valid submit produces exactly one admin-ajax POST carrying a non-empty reCAPTCHA v3 token — intercepted and aborted. |
| `popup-consultation-opens` | 1920 | The CTA opens the consultation popup: the document (id 7718) is fetched over ajax and one modal is displayed. |
| `toggle-faq-expands` | 1920 | The free Elementor toggle opens and closes. |
| `back-to-top` | 1920 | ElementsKit back-to-top hides at scrollY 0, reveals past its configured threshold, and returns the page to the top. |
| `console-clean-*` (9) | 1920 | Zero uncaught exceptions and zero non-network console errors on home, contact, our-team, blogs, a post, a service page, a category archive, search results and 404. |

Console messages are classified: `pageerror` and non-network `console.error` are
JS faults and fail. `Failed to load resource` is a *network* message and is
recorded separately as a broken-asset observation — still compared, so a newly
broken asset fails a `--compare` run, but it does not masquerade as a scripting
error.

### Layout facts the selectors depend on

All verified against the live site and `../snapshots/ref-a/html/`. No selector
was guessed.

- The **desktop** header menu is the ElementsKit `ekit-nav-menu` widget
  (`#ekit-megamenu-main-menu`), which is **free** and survives Pro's removal.
- The **tablet/mobile** header menu is the Elementor Pro `nav-menu` widget
  (`#tabNmobNav`), which is 0×0 above 1024px. **The Pro nav menu is therefore
  only ever reachable below 1024px on this site**, which is why every Pro nav
  test runs at tablet or mobile. Testing it at desktop would test nothing — it
  was the first thing this harness discovered, when a desktop hover test could
  not find a visible element.
- Only **one** `.elementor-menu-toggle` exists in the whole document, despite 25
  nav-menu instances site-wide (11 on the home page alone). The rest are footer
  and mega-menu-panel menus with `dropdown-none`.
- Of the two header `search-form` widgets, one is desktop-only and one is
  tablet-only; below that the ElementsKit modal takes over.
- All 80 `search-form` instances use the **classic** skin. There is no
  full-screen-skin instance anywhere on the site, so there is no full-screen
  toggle to test.

---

## Defects found in the site itself

Neither is caused by the migration and neither fails the baseline — both are
recorded via `t.note()` so they are on the record and so a `--compare` run
notices if a rebuild changes them.

**1. Duplicate DOM id `pie-search`.** Both header search widgets were given the
same custom CSS id in the Elementor editor, so the document contains two
elements with `id="pie-search"`. That is invalid HTML, and every `#pie-search`
selector — in the site's own CSS, in any script, and in any test written the
obvious way — silently resolves to whichever comes first in source order, which
at tablet width is the **invisible desktop copy**. This is why every test here
scopes by `data-id` instead. Recorded by `search-classic-*` along with any other
duplicate ids in the document — the reference run records `["pie-search x2",
"Layer_1 x3"]`, the second being three inline SVGs that share a generator's
default id, which is harmless but is on the record for the same reason.

**2. The required "Product" select accepts its own placeholder.** On
`/contact-us/`, `form_fields[contact_us_products]` is marked `required`, but its
placeholder option is `<option value="Select Service">` — a *non-empty* value.
HTML5 only treats a required `<select>` as invalid when the selected option's
value is the empty string, so the field passes validation while nothing has been
chosen, and every enquiry can arrive with Product = "Select Service".
Confirmed directly: `select.checkValidity() === true` on a freshly loaded page.
Fixing it is a one-character content change (`value=""` on the placeholder), but
it is an authoring change to live page content, which is outside this tool's
remit. Recorded by `form-validation-blocks-empty-submit`.

A third observation, not a defect: **15 of the 21 `toggle` widget instances
site-wide carry `elementor-hidden-desktop elementor-hidden-tablet
elementor-hidden-mobile`** — hidden at every breakpoint, i.e. dead weight in the
markup. They are the "Show comments / Leave a comment" toggles on blog posts. The
6 live instances are the FAQ accordions on newer posts, which is why
`toggle-faq-expands` runs against
`/why-healthcare-software-fails-without-security-first-architecture/` and not
against an arbitrary post.

---

## Coverage this does not achieve

Stated plainly, because a harness's blind spots are more dangerous than its
failures.

- **One popup, one trigger.** All 154 popup-trigger links across the site point
  at the same popup (id 7718) via the same base64 action, so testing one trigger
  tests them all *for that popup*. If other popups exist that are opened by
  scroll/exit-intent/timing display conditions rather than by a link, this suite
  does not know about them and does not test them.
- **`form-recaptcha-v3-token` depends on the live internet.** It needs
  `www.google.com/recaptcha` to be reachable. It is the one test that can fail
  for a reason that is neither the site nor the harness; its result file carries
  a `network` field saying so. If it alone goes red, check the network before
  blaming anything else.
- **The form's success path is never exercised**, by design. We assert
  validation blocks a bad submit and that a good submit produces the right POST,
  then abort it. Whether the server would have accepted it, stored the entry and
  sent the mail is untested and deliberately untestable here.
- **Only the home page's carousels are tested.** There are 4
  testimonial-carousel and 4 image-carousel instances site-wide; the home page
  holds 2 of each plus the client-logo strip. The others are assumed to behave
  the same because they are the same widget with the same handler.
- **No keyboard or screen-reader testing.** The nav toggle has
  `role="button" tabindex="0"`; whether Enter/Space activate it is untested.
- **No touch-gesture testing.** Swiper's drag-to-advance is not exercised, only
  the arrow buttons. Playwright can synthesise touch, but a swipe that "works"
  under synthetic events is weak evidence about a real finger.
- **Sticky header behaviour is untested.** Pro ships `jquery.sticky.min.js` and
  it is enqueued on every page, but I found no widget on the tested pages whose
  behaviour depends on it, so there is no assertion for it. If a sticky header
  or sticky sidebar exists on a page outside the tested set, its loss would go
  unnoticed. **This is the largest known gap.**
- **`toggle`/accordion coverage rests on one widget instance on one page**,
  because it is the only place the widget is visible at any breakpoint.
- **Nothing verifies that a behaviour is *correct*, only that it changed the
  DOM in the way it does today.** If the current site's carousel advances in the
  wrong direction, this suite will faithfully require the replacement to do the
  same. That is the right trade for a migration — bug-for-bug fidelity is the
  goal — but it is worth being explicit that "passes" means "unchanged", not
  "good".

## Files

```
run.js        the runner: browser lifecycle, settling, request policy,
              assertion recorder, baseline comparison
tests.js      the test definitions and every selector, with the evidence for
              each one
results/      one JSON per run; results/pro-active.json is the reference
package.json  intentionally dependency-free; explains the shared Playwright
```
