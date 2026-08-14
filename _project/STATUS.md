# PieCyfer — project status

Updated: **2026-08-14**

| Doc | What it covers |
|---|---|
| `00-AUDIT-REPORT.md` | Security audit findings, malware analysis, plugin/licence assessment |
| `01-REBUILD-PLAN.md` | The 9-phase plan and effort estimates |
| `02-WIDGET-REBUILD-SPEC.md` | Phase 4 build contract — every widget, every setting, build order |
| `03-PHASE3-RUNBOOK.md` | Plugin-removal loop, group by group |
| `STATUS.md` | ← you are here |

---

## Progress

| Phase | Status |
|---|---|
| **0 — Backup & safety net** | ✅ Done |
| **1 — Malware eradication (local)** | ✅ Done |
| **1b — Malware eradication (LIVE site)** | ⏸️ Live site is down; deferred |
| **2 — Pixel baseline & harness** | ✅ Done |
| **3 — Plugin consolidation** | 🟡 Partial — 9 removed; caching/SEO left to you |
| **4a — `piecyfer-core` skeleton** | ✅ Done, verified no-op |
| **4b — dynamic tags + first widgets** | ⚠️ Re-verification needed — signed off under a cache that faked passes |
| **4c — blockquote + search-form** | ✅ Verified against the fixed harness |
| 4d–4n — remaining widgets, theme builder, form, popup | ⬜ The bulk of the work |
| 5 — Theme decision | ⬜ Blocked on your decision |
| 6 — Performance | ⬜ Not started |
| 7 — Technical SEO | ⬜ Not started |
| 8 — Hardening | ⬜ Not started |

---

## What is done

### Phase 0 — safety net
Database dumped (34 MB gzipped, 56 tables). Git repository in the site root, 21k files tracked.
Every change since is a separate commit with the verdict in the message.

### Phase 1 — malware eradication
Four malicious plugins removed and quarantined outside the web root with every `.php` renamed so
it cannot execute. The backdoor administrator `sys_maint` deleted with its usermeta and marker
option. All eight `wp-config` salts rotated. `wp-file-manager` (the likely entry vector) deleted.

Verified afterwards: WordPress core checksums 100% clean, zero signature hits site-wide, two
users remain, every page HTTP 200 with no PHP errors.

### Phase 2 — the verification harness

`_project/pixel-tool/` captures 39 URLs × 3 viewports (full-page screenshots + normalised HTML +
console errors + broken assets) and diffs two runs, exiting non-zero on any change.

**Making it trustworthy took eight rounds of debugging**, because the first version disagreed
with itself on 5 of 24 screenshots when nothing had changed. Full table in the commit history;
the two that mattered most were capturing serially (parallel capture starved the machine and
Chromium composited screenshots before images decoded) and screenshotting until two consecutive
captures are byte-identical.

A harness that cries wolf is worse than no harness: every later phase is gated on it, and a real
regression would have been invisible in the noise.

### Phase 3 — plugin consolidation (partial, by agreement)

Removed and verified: `query-monitor`, `duplicate-page`, `vamtam-importers-e`, `maintenance`,
`wp-smush-pro`, `imagify`, `all-in-one-wp-migration`, `simple-copy-protection`, `wp-meteor`.

**`wp-meteor`'s removal fixed two real, pre-existing bugs** that its blanket JS deferral had been
hiding:

1. **450px of horizontal overflow on every page.** Full-page captures were 2370px wide at a
   1920px viewport, with the click-to-chat button parked off-canvas. Visitors got a horizontal
   scrollbar and a layout shift until their first mouse move released the deferred scripts.
2. **A JavaScript exception on every page.** The theme's Additional JS ran
   `document.querySelector('.select-caret-down-wrapper').innerHTML = …` in `<head>`, before
   `<body>` exists, so it always threw — and the throw aborted the rest of that inline block.
   Fixed to run on DOM ready and to apply to every matching element rather than the first;
   the previous value is kept as `vamtam_additional_js_backup_<ts>`.

Caching (WP Rocket, Debloat, Object Cache Pro) and the SEO plugins are **left untouched at your
request**.

### Phase 4a — plugin skeleton, verified inert

`piecyfer-core` activates and changes nothing: 0 markup changes, 0 visual changes across 24
screenshots. That single test validated both the plugin and the method end to end.

### Phase 4b — dynamic tags and the first widgets

**Verified byte-identical markup** with Elementor Pro still installed:

- 8 dynamic tags — `site-title`, `site-logo`, `post-title`, `post-terms`,
  `post-featured-image`, `archive-title`, `current-date-time`, `internal-url`
- the `template` widget (19 instances)

`_project/scripts/which-implementation.php` confirms the takeover is real rather than assumed —
it prints which class actually serves each widget and tag. That matters because a page renders
the same either way, so a replacement could silently fail to take over and the pixel comparison
would still pass.

Written, not yet verified: `theme-post-title`, `theme-archive-title`, `theme-site-logo`.

**Every one of these sign-offs predates the harness fix below and has to be re-checked.** Two of
them were already proven wrong.

### Phase 4c — `blockquote`, `search-form`, and a harness that was lying

`e_element_cache` is active on this site. It caches an entire document's **rendered HTML** in
`_elementor_element_cache` postmeta for 24 hours, together with the style and script handles that
render enqueued. While that cache is warm Elementor never calls the widgets at all.

The verify loop was therefore producing **false passes**: a capture could report a widget
byte-identical when our widget had never executed. Three genuinely correct fixes appeared to have
no effect at all, which is what finally exposed it.

`capture.js` now clears the cache before every run and treats a failure to do so as fatal.
`piecyfer-core`'s own automatic clearing is *not* sufficient — its signature covers the widget and
stylesheet **lists**, so adding a widget invalidates the cache but editing one does not.

Four real bugs were hiding behind it:

| Widget | Bug |
|---|---|
| `search-form` | `skin` lacked `frontend_available`, so `data-settings` was missing from every page |
| `search-form` | the icon hard-coded `aria-hidden`; Pro passes the widget's `icon` attributes (`fa fa-search`) |
| `theme-site-logo` | `home_url( '/' )` added a trailing slash to the logo link everywhere — **was recorded as verified** |
| `blockquote` | a `tweet_button` condition Pro does not have on `section_button_style` stripped `button_color_source`, losing `elementor-blockquote--button-color-official` — **was recorded as verified** |

Five section ids also did not match Pro. That is not cosmetic: third-party code injects controls
at `elementor/element/<widget>/<section_id>/before_section_end`, and ElementsKit does exactly
this, so a renamed section silently drops the injection.

Result: 24 screenshots, **0 visual changes**, and every remaining markup difference accounted for.

### The baseline is contaminated, and the contamination is informative

Clearing the cache revealed three defects **in the baseline itself**, all caused by cached HTML
imported from production along with the database:

1. An ElementsKit nav logo still pointing at `https://piecyfer.com/...` — the local site was
   fetching that image from the live domain. Zero production URLs remain in `postmeta` now.
2. A missing `post-8519.css`. Template 8519 is pulled into the Blog Post Template by an
   `[elementor-template]` shortcode and the cached asset list had never recorded it.
3. **Comment forms carrying the wrong post id** — see Open decisions.

Because of these, a comparison against `baseline` can never legitimately reach *IDENTICAL*.

### `e_element_cache` turned off — it was corrupting three things at once

Chasing the last markup difference on the blog post turned up something much larger than the
comment form. Elementor caches rendered HTML **per document**, and the header and single-post
Theme Builder templates are each *one* document shared by every page that uses them. So whichever
page warms the cache decides what every other page's header says, for the full 24-hour TTL.

Proven by request, same URL both times:

| Cache warmed by | `queried_id` | `comment_post_ID` | nav `current-menu-item` |
|---|---|---|---|
| the blog post itself | 993554 ✅ | 993554 ✅ | none ✅ |
| Home, then the blog post | **146** ❌ | **993554→wrong post** ❌ | **Home, on a blog post** ❌ |

Three live defects from one cause:

1. **Nav highlighting** — the wrong menu item marked current on every page. On `/our-team/` the
   baseline marks **Home** active; it is now **Our Team**. Worth being precise about how visible
   this is: the `elementor-item-active` rule on this site styles
   `.elementor-nav-menu--dropdown a.elementor-item-active`, i.e. the *mobile dropdown*, which is
   collapsed in every screenshot. So this is wrong markup, wrong semantics for assistive tech and
   a wrong highlight for anyone who opens the mobile menu — but not a change the pixel comparison
   sees, which is exactly why it survived until now.
2. **Elementor forms** — `queried_id` and `referer_title` hidden fields carry the warming page, so
   every submission is attributed to the wrong page.
3. **Comment forms** — comments filed against whichever post warmed the cache.

A per-element opt-out would have to be applied to the nav menu, all nine forms and the comments —
most of the dynamic surface of the site — and would break again the next time a dynamic widget is
added. The experiment is **off** as of 2026-08-14 (`scripts/set-experiment.php`, which also clears
the postmeta: stale entries stay valid-looking for 24h and are read before anything else, so
without that the change appears to do nothing for a day).

Verified after: `queried_id`, `comment_post_ID` and `current-menu-item` are correct on every page,
independent of visit order.

This also removes the order-dependence from the harness — captures no longer depend on which page
was visited first.

---

## The approach that makes this safe

Elementor's widget registry is a plain array keyed on widget name, so **the last registration
wins**. `piecyfer-core` registers at priority 20, after Pro's default 10, and takes widgets over
**one at a time while Pro is still installed**:

```
implement one  →  capture --quick  →  compare against baseline
     0 diff → keep it            any diff → delete the file, Pro's version is instantly back
```

The site stays fully working throughout, each widget is proven equivalent before anything depends
on it, and reverting one widget is one `git checkout` of one file. Pro comes out only at the end,
when nothing of its is rendering anymore.

Two of the widgets so far turned out to be free Elementor widgets in disguise —
`theme-post-title` is `Widget_Heading` with three changes, `theme-site-logo` is `Widget_Image`
with two. Extending those keeps every one of their style control ids, and therefore every
generated CSS rule, identical for free.

---

## Honest view of what remains

Phase 4 is the bulk of the project and it is mostly transcription, not invention. Each widget
needs its control **ids and `selectors` arrays** reproduced exactly, because Elementor compiles
those into the per-page CSS — a missing control means a missing CSS rule, which is a visual
change even when the markup matches.

Some are large: Pro's `blockquote` is 1,019 lines and `search-form` 978, though this site only
populates ~20 settings on each.

Remaining, roughly in order of effort:

| Item | Notes |
|---|---|
| **Theme Builder** | 10 templates + display conditions. Largest single item. |
| **`posts` / `archive-posts`** | VamTam's `vamtam_classic` skin on top of Pro's — 150 custom controls, 83% of all VamTam settings |
| **`form`** | Fields, validation, actions-after-submit, mail, spam protection |
| **`nav-menu`** | 25 instances, 70 populated settings, and VamTam subclasses it |
| 11 more Pro widgets | `blockquote`, `search-form`, `call-to-action`, `post-info`, `gallery`, `testimonial-carousel`, `post-comments`, `theme-post-content`, `archive-posts`… |
| 6 ElementsKit widgets | Plus your two custom builder widgets, which lift over almost verbatim |
| Control injections | Custom CSS (58 elements), Motion FX scale (23), Sticky (3) |
| Popup | 1 popup, plus the `popup` dynamic tag — which turns out to belong to **VamTam**, not Pro |

---

## Decisions taken

**2026-08-14 — the objective is that no nulled code remains.** Not "the site works"; every
unlicensed package leaves. That is what makes this future-proof, because unlicensed packages are
why the site was compromised in the first place: they receive no security updates and the backdoor
arrives inside the download.

### Target end state

| Keep | Build | Delete |
|---|---|---|
| `elementor` (free) | `piecyfer-core` — widgets, dynamic tags, Theme Builder, Popup | `elementor-pro` 🔴 nulled |
| `all-in-one-seo-pack` | `piecyfer-theme` — replaces the nulled theme | `elementskit` (Pro) 🔴 nulled |
| `broken-link-checker-seo` | | `themes/tecnologia` 🔴 nulled |
| `click-to-chat-for-whatsapp` | | `vamtam-elementor-integration-tecnologia` |
| `google-analytics-for-wordpress` | | `object-cache-pro` 🔴 nulled |
| `updraftplus` | | `redis-cache`, `wp-rocket` 🔴, `debloat` |
| `wp-mail-smtp` | | `optinmonster` 🔴 nulled |
| | | `wordpress-seo` + `wordpress-seo-premium` 🔴 — **inactive**; AIOSEO is the live SEO |

**SEO stays.** AIOSEO and the broken-link checker are not touched. The two Yoast installs are a
different matter: they are inactive, so removing them cannot affect SEO, and Yoast Premium is
nulled. Leaving dormant nulled code on disk is exactly the mistake that let `wp-file-manager` in.

**Caching goes.** Object Cache Pro is nulled; Redis, WP Rocket and Debloat are redundant with it
gone. Performance is Phase 6 and will be done with code we own.

### 1. Theme — decided: build `piecyfer-theme`

A licence would legitimise the theme but not the problem. The header, footer, single, archive,
search and 404 are all Elementor Theme Builder documents, so the theme does far less than it
appears to — see `05-THEME-SPEC.md`. The companion plugin is the harder half: it subclasses six
Elementor widgets at priority 100 and injects controls into others.

### 2. Live site

Up again as of 2026-08-14, but **we are not touching it**: the work is completed locally and
deployed once it is finished. `_project/scripts/piecyfer-malware-cleanup.php` is ready and proven
for that deployment, and needs the credential rotation in `01-REBUILD-PLAN.md` §1.2.

---

## The Theme Builder cutover is atomic — this changes the endgame

Everything in this project so far has been reversible one widget at a time. The Theme Builder
cannot be. It is not a preference, it is a guaranteed fatal:

Pro's `popup`, `floating-buttons` and `custom-code` modules each hook
`elementor/theme/register_locations` with a callback **type-hinted on Pro's own
`Locations_Manager`**. The moment our locations manager fires that action while Pro is installed,
PHP throws a `TypeError` and every page dies. There is no side-by-side mode for this component,
so `Module::boot()` refuses outright while `ELEMENTOR_PRO_VERSION` is defined.

**Consequence:** Pro comes out and our Theme Builder switches on in the *same commit*. That single
commit is the highest-risk moment in the project, and it can only be attempted once everything
else is finished — `posts`, `archive-posts`, the form back end, the popup, and the JavaScript
layer. If any of those is missing at that point, the page renders chrome around a hole.

What de-risks it, and why it is worth the care:

- **Routing is already proven offline.** `scripts/theme-builder-routing.php` resolves every
  location through our resolver and compares against Pro's, without changing any output:
  **44 of 44 identical**, including the specificity cases — `/privacy-policy/` correctly picks
  footer 991509 (priority 20) over the site-wide 1273 (priority 100).
- **The wrapper markup is proven byte-for-byte** for all eight document types, and the control-id
  diff against Pro is empty in both directions, so the generated CSS keeps its selectors.
- **Flush Elementor's CSS cache before comparing after cutover.** Otherwise the comparison runs
  against files Pro generated, and the document-type registration — the whole reason those
  selectors stay `.elementor-171` rather than `body.elementor-page-171` — goes untested.

Two things will fail at cutover unless built first: **archive-posts**, or `/category/*` and `/?s=`
render an empty middle; and **popup 7718**, which has no display conditions and currently rides
Pro's popup module into the manual queue. The popup appears on every captured page, so every
baseline comparison fails until it is ported.

---

## The registration-priority trap (found before it bit us)

`piecyfer-core` registers widgets at priority **20**, chosen to beat Pro's default 10. But VamTam's
companion plugin registers at **100**, and for six widget names it does:

```php
$widgets_manager->unregister( 'nav-menu' );
$widgets_manager->register( new Vamtam_Widget_Nav_Menu );
```

`nav-menu`, `posts`, `archive-posts`, `login`, `button`, `tabs`. Three of those are ours to
replace and are the largest ones left. At priority 20 our replacements would have been silently
unregistered — and the pixel comparison would have passed, because VamTam's widget was still
rendering. Another false pass, and the most expensive one yet: it would have shipped.

Fixed by registering above VamTam and by making `scripts/which-implementation.php` a gate rather
than a report — it must confirm our class actually serves every name in `Plugin::WIDGETS`.
