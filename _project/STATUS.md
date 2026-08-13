# PieCyfer — project status

Updated: **2026-08-13**

| Doc | What it covers |
|---|---|
| `00-AUDIT-REPORT.md` | Security audit findings, malware analysis, plugin/licence assessment |
| `01-REBUILD-PLAN.md` | The 9-phase plan, decisions needed, effort estimates |
| `02-WIDGET-REBUILD-SPEC.md` | Phase 4 build contract — every widget, every setting, build order |
| `STATUS.md` | ← you are here |

---

## Progress

| Phase | Status |
|---|---|
| **0 — Backup & safety net** | ✅ **Done** |
| **1 — Malware eradication (local)** | ✅ **Done** |
| **1b — Malware eradication (LIVE site)** | 🔴 **Blocked — needs your input** |
| **2 — Pixel baseline capture** | 🟡 Harness done; final baseline capturing |
| 3 — Plugin consolidation | ⬜ Ready to start — dependency check clean |
| **4a — `piecyfer-core` skeleton** | ✅ **Done** (registers nothing yet, by design) |
| 4b–4n — widgets, theme builder, form | ⬜ Not started |
| 5 — Theme decision | ⬜ Blocked on your decision |
| 6 — Performance | ⬜ Not started |
| 7 — Technical SEO | ⬜ Not started |
| 8 — Hardening | ⬜ Not started |

---

## Phase 0 — done

- Database dumped: `_project/backups/piecyfer-db-2026-08-13-preclean.sql.gz` (34 MB, 56 tables)
- `wp-config.php` backed up before salt rotation
- **Git repository initialised** in the site root — 21,438 files tracked, `uploads/`,
  `updraft/` and `wp-config.php` excluded. Two commits so far:
  - `72dc08f2` pre-cleanup snapshot (site as found, malware included, so it is revertible)
  - `87a21968` Phase 1 cleanup

Git is the real safety net: every change from here is diffable and revertible with
`git checkout`.

## Phase 1 — done (local copy only)

**Removed 4 malicious plugins**, quarantined to `C:\xampp\_piecyfer-quarantine\` (outside the
web root, every `.php` renamed to `.php.quarantined`, `.htaccess` deny-all):

- `shop-mini-tools`, `faq-accordion-lite-b`, `easy-image-optimizer` — one shared backdoor that
  created a hidden self-healing administrator and injected obfuscated JavaScript into every
  anonymous page view
- `custom-fields-pro-56` — pulled arbitrary JavaScript from a Binance Smart Chain contract and
  executed it in visitors' browsers

**Database cleaned:** backdoor administrator `sys_maint` (ID 3) deleted along with its 15
usermeta rows, the `_wp_ip` hide-marker and the `_wp_ip_id` option.

**Also:**
- All 8 `wp-config.php` salts rotated — invalidates any session the attacker still held
- `wp-file-manager` 8.0.4 deleted (likely entry vector)
- `maintenance` plugin deactivated — it was hiding the entire real front end behind an
  "under maintenance" page

**Verified clean:**
- WordPress core checksums: 0 modified, 0 missing, 0 unknown
- Zero malware signatures anywhere in the tree
- 2 users remain (the two legitimate admins)
- All pages HTTP 200, no PHP errors, no injected scripts

## Phase 2 — in progress

Built `_project/pixel-tool/` — the harness that makes "pixel perfect" checkable rather than
claimed:

- `capture.js <label>` — 39 URLs × 3 viewports (1920/768/375), full-page screenshots +
  normalised HTML + broken-asset and console-error tracking. `--quick` captures an 8-page
  representative subset for fast checks between steps.
- `compare.js <a> <b>` — markup diff, per-pixel screenshot diff with anti-aliasing tolerance,
  new-console-error and newly-broken-asset detection. Exits non-zero on any change, so it can
  gate a phase.
- `analyse-widgets.js` — the widget/settings census behind `02-WIDGET-REBUILD-SPEC.md`

### The harness had to be debugged before it could be trusted

Capturing the same untouched site twice initially disagreed on **5 of 24 screenshots, by up to
5.2% of pixels**. Every one of those was a false positive. Had that gone unnoticed, Phase 3 and
Phase 4 would have been spent chasing regressions that did not exist — and, worse, a real
regression would have been invisible in the noise.

Six separate causes, each found by cropping the differing bands and looking at them:

| # | Cause | Fix |
|---|---|---|
| 1 | Webfonts fetched from `fonts.googleapis.com`, intermittently failing → page fell back to a system face | On-disk cache of off-site responses, replayed byte-identically; wait for `document.fonts.ready` |
| 2 | Google Maps mints new tile URLs and tokens per load, and sits in a global template | Iframe content hidden, box preserved; its requests excluded from the report |
| 3 | Lazy images below the fold — `networkidle` is not enough, lazy-load fires on scroll | Step-scroll pass, then poll until image count / loaded count / page height are stable 3× |
| 4 | Images inside hidden tab panels — never scrolled into view | Pre-warm every image URL via off-DOM `Image()`, including CSS background images |
| 5 | Swiper autoplay left carousels at different offsets | Stop autoplay, reset to slide 0, pin the track with CSS |
| 6 | `swiper-lazy` loads a slide's image only as it nears the active position, so autoplay decided how many client logos appeared — 3 in one run, 6 in the next | Promote `data-src` → `src` **after** stopping the carousel; the ordering is what makes it work |
| 7 | Capturing 3 pages in parallel pushed this machine from ~8s to 60–90s per page, and under that contention Chromium composited parts of an 11,000px screenshot before its images had decoded | Capture **serially** |
| 8 | **The definitive fix.** Even serially, two runs of an *identical configuration* still disagreed on one screenshot of the home page — at 11,334px the tallest on the site | **Screenshot until two consecutive captures are byte-identical** |

Causes 7 and 8 are worth dwelling on, because the first six were partly symptom-chasing. The
tell was that markup stayed **byte-identical** — same DOM, same inline styles, same `src`
attributes — while pixels differed, and the affected page moved around between runs. Nothing was
loading differently; the pixels simply were not ready. Each targeted fix reduced the count
without reaching zero, which is the signature of treating symptoms rather than the cause.

Fix 8 is the one that needed no guesswork about which element was misbehaving. Everything before
it *reduces the chance* of catching a page mid-paint; taking a second screenshot 500ms later and
requiring the two to match *detects* it. If the page had stopped changing, they agree.

The decisive experiment was comparing two runs that differed in nothing at all. They still
disagreed on one screenshot — which proves the difference could not have been caused by the
change under test, because there was no change under test. Without that check, the very first
real comparison would have been read as "activating the plugin altered the home page."

Per-page stability is now recorded (`unstableShots`), so a page that never settles within the
retry budget is surfaced rather than silently trusted. Nothing has hit that limit.

**Self-test passes cleanly:** two consecutive captures of the untouched site produce
0 markup changes, 0 visual changes, 0 console errors, 0 broken assets — exit code 0.

---

## 🔴 Blocked on you

### 1. The live site is still infected — this is the urgent one

Everything above was done to the **local copy**. The live site
(`wdev.piecyfer.com`, and whatever the production domain is) still has all four malicious
plugins and the `sys_maint` administrator. Real visitors are still being served attacker code.

`_project/scripts/piecyfer-malware-cleanup.php` is ready and proven — it ran cleanly here. It
verifies a malware signature before deleting anything, quarantines rather than destroys, and is
safe to run twice. Start with `--dry-run`.

```
php piecyfer-malware-cleanup.php --path=/path/to/public_html --dry-run
php piecyfer-malware-cleanup.php --path=/path/to/public_html
```

**Tell me how you reach the live server — SSH, cPanel, or FTP only — and I will adapt it.**

Regardless of method, these must happen by hand (see `01-REBUILD-PLAN.md` §1.2 for the full
list): rotate every admin password, the hosting password, FTP/SSH credentials **and keys**, the
database password, and the wp-config salts. Then check Google Search Console → Security Issues.

### 2. Decisions that shape Phases 3–6

| # | Question | My recommendation |
|---|---|---|
| 1 | Theme: buy a VamTam licence (~$69), build `piecyfer-theme`, or keep the nulled one? | **Build our own** — the header, footer, single, archive, search and 404 are all Elementor Theme Builder already, so the theme does less than it appears |
| 2 | WP Rocket replacement — free stack, or buy a licence? | Free stack; measure first |
| 3 | Analytics — keep MonsterInsights, or a lightweight GA4 snippet in `piecyfer-core`? | Own snippet |
| 4 | Right-click / copy protection — keep? | Drop it; it blocks nobody and hurts UX |

None of these block Phases 2–4, so work continues either way.

---

## Phase 4a — done

`wp-content/plugins/piecyfer-core/` exists and lints clean. It **registers no widgets** — the
skeleton has to be provably inert before it starts replacing anything, and the next step is to
activate it and prove a zero-difference capture against the baseline. That single test validates
both the plugin and the whole verification method at once.

What it already provides:

- Requirement guards — refuses to load below Elementor 3.20 with a clear reason; above the tested
  3.25.10 it still loads but warns. Refusing outright would take the site down for a routine
  Elementor update, which is precisely the fragility we are removing.
- A hand-rolled autoloader, so there is no `composer install` step to forget on deploy.
- Per-widget `try`/`catch` at registration and at render, so one broken widget produces a logged
  error and a visible marker for editors — never a white page for a visitor.
- `AbstractWidget`, which encodes the three rules that make the migration pixel-identical
  (same `get_name()`, same control ids, same markup) and gives each widget per-page conditional
  asset loading, which Elementor Pro does not do.

---

## Notable findings since the plan was written

**The Elementor Pro removal is a three-plugin problem.** `vamtam-elementor-integration-tecnologia`
subclasses Elementor Pro classes directly, and several of those files have **no
`class_exists()` guard**. Deactivating Pro on its own would white-screen the editor and the front
end. Pro must come out in Phase 4 alongside the VamTam integration, never in Phase 3.
Full detail in `02-WIDGET-REBUILD-SPEC.md`.

**`posts` and `archive-posts` are harder than first estimated.** They use VamTam's custom
`vamtam_classic` skin layered on Elementor Pro's — 150 custom controls between them, holding 83%
of all VamTam customisation on the site. Reclassified from Medium to Hard.

**ElementsKit is easier than first estimated.** It injects controls into all 2,269 widgets, but
those are untouched defaults. Only 6 widget types and 4 deliberate settings are real.

**The site is self-contained.** The only off-site asset references are 203 inert ElementsKit
placeholder defaults pointing at `wdev.piecyfer.com`. All real content is local.

**The site was in maintenance mode.** Worth confirming whether that is also true on live — if
so, real visitors currently see a holding page rather than the site.

**Phase 3 is de-risked.** Static dependency check across the theme and the VamTam plugins found
**no hard references** to any plugin on the removal list — no AIOSEO, Imagify, Smush, Debloat,
WP Meteor, Object Cache Pro, OptinMonster, MonsterInsights, All-in-One WP Migration or
Click-to-Chat calls anywhere. The only two WP Rocket hits are harmless: an
`add_filter( 'rocket_cache_wc_empty_cart', … )` that simply does nothing when WP Rocket is
absent, and `BeRocket_AAPF_…` options, which belong to an unrelated plugin. Phase 3 can proceed
as planned — minus the Elementor cluster, which is deferred to Phase 4.

**Fonts are loaded from Google, not self-hosted.** `fonts.googleapis.com` / `fonts.gstatic.com`
serve Inter Tight and others. Two consequences: a Phase 6 self-hosting task, and a capture
harness that must wait for `document.fonts.ready` before screenshotting — without it a run
occasionally photographs the fallback face and reports a whole-page diff that means nothing.

**Four documents store root-relative `/wp-content/…` URLs** (3 team photos on Our Team, 3 PDF
links on the App Development tabs). These resolve correctly on the production domain root but
404 on this `/piecyfer/` subdirectory copy. The capture harness rewrites them so the baseline
reflects production rather than a local-only artefact. Worth fixing properly in the content at
some point, but it is not a production bug today.

**Domains in play:** `piecyfer.com` (production), `careers.piecyfer.com` (a separate careers
site, linked 15 times), `wdev.piecyfer.com` (staging — only referenced by inert ElementsKit
placeholder defaults).
