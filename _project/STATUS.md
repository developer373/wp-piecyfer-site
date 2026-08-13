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
| **4b — dynamic tags + first widgets** | 🟡 Tags + Template **verified**; 3 more widgets written |
| 4c–4n — remaining widgets, theme builder, form, popup | ⬜ The bulk of the work |
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

## Open decisions

1. **Theme** — buy a VamTam licence (~$69), or build `piecyfer-theme`? My recommendation is to
   build our own: the header, footer, single, archive, search and 404 are all Elementor Theme
   Builder documents, so the theme does far less than it appears to. Nothing is blocked on this
   until Phase 5.
2. **Live site** — currently down. When it comes back,
   `_project/scripts/piecyfer-malware-cleanup.php` is ready and proven; it needs the same
   credential rotation listed in `01-REBUILD-PLAN.md` §1.2.
