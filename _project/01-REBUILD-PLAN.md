# PieCyfer — Clean-up, Rebuild & Future-Proofing Plan

**Companion to:** `00-AUDIT-REPORT.md`
**Goal:** a clean, self-owned, update-proof, fast site that is **100% pixel-identical** to today's.

> **Superseded in two places by `02-WIDGET-REBUILD-SPEC.md`,** written after the full widget
> census. Read that document's first two sections before starting Phase 3 or 4:
> 1. Phase 4's `posts` / `archive-posts` are **Hard**, not Medium — they use VamTam's custom
>    `vamtam_classic` skin on top of Elementor Pro's.
> 2. **Elementor Pro must not be removed in Phase 3.** The VamTam integration plugin subclasses
>    Pro classes without guards; removing Pro alone white-screens the site. Pro comes out in
>    Phase 4, together with the VamTam integration. Phase 3.7 below is corrected accordingly.

---

## The core strategy (read this first)

Everything below hangs on one architectural decision, so it is worth stating plainly.

Elementor stores each page as JSON in `wp_postmeta._elementor_data`. Every widget instance looks
roughly like:

```json
{ "elType": "widget", "widgetType": "call-to-action", "settings": { "title": "...", "..." : "..." } }
```

At render time Elementor asks its widget registry: *"who is registered under the name
`call-to-action`?"* — and hands that class the `settings` object.

**Therefore: if we register our own widget class under the same `widgetType` name, with the same
control IDs, and emit the same HTML/CSS classes — the existing page JSON renders byte-identically,
with zero content migration.**

This is what makes "pixel perfect" achievable rather than aspirational:

- ❌ We do **not** rebuild pages.
- ❌ We do **not** touch `_elementor_data`.
- ❌ We do **not** ask you to re-lay-out anything in the editor.
- ✅ We swap the *implementation* underneath, and the data on top is untouched.

The verification method follows directly: render every page **before** removal and **after**
replacement, and diff the HTML and the screenshots. If they differ by a single pixel, the widget
is not finished. That is the acceptance test for every phase.

**Elementor free stays.** It is GPL, actively maintained, free, and already provides 94% of the
widget instances on this site. Replacing it would be enormous work for zero benefit. We replace
**Elementor Pro**, **ElementsKit**, and the redundant plugin sprawl — not Elementor itself.

---

## Phase order and why

```
Phase 0  Backup & safety net            ← nothing is irreversible after this
Phase 1  Malware eradication            ← URGENT. Visitors are being attacked right now
Phase 2  Pixel baseline capture         ← MUST happen before any removal
Phase 3  Plugin consolidation           ← removes conflicts, shrinks the surface
Phase 4  piecyfer-core plugin           ← the main build: own the widgets
Phase 5  Theme decision & child theme   ← de-null the theme
Phase 6  Performance
Phase 7  Technical SEO
Phase 8  Hardening & future-proofing
```

Phase 1 is urgent and independent — it runs first regardless of everything else.
Phase 2 must precede Phase 3, because once a plugin is gone you can no longer photograph what it
used to render. **Capturing the baseline is the single most important step for the pixel-perfect
guarantee.**

---

## Phase 0 — Backup & safety net

**Deliverable:** a restore point that makes every later step reversible.

1. Full filesystem copy of `C:\xampp\htdocs\piecyfer` → `piecyfer-backup-2026-08-13-preclean`.
2. Full `mysqldump` of the `piecyfer` database → `_project/backups/`.
3. Initialise **git** in the site root with a `.gitignore` for `uploads/`, `cache/`, `updraft/`,
   `node_modules/`, `*.log`. Commit the pre-clean state.
   *This is the real safety net — from here on, every change is diffable and revertible.*
4. Record a `sha256` manifest of `wp-content/plugins` and `wp-content/themes`.

> Note: the existing `wp-content/updraft/` backups are **from 2026-08-13 — after the 2026-08-01
> infection date**. They contain the malware. They are useful as a rollback point, not as a
> clean source. Do not restore from them expecting a clean site.

---

## Phase 1 — Malware eradication 🔴 URGENT

### 1.1 On this local copy

**Order matters** — the PHP recreates the DB user, so files must die first.

1. **Deactivate** the four malicious plugins (so WordPress runs their deactivation path cleanly and
   they stop hooking `init`):
   `shop-mini-tools`, `faq-accordion-lite-b`, `easy-image-optimizer`, `custom-fields-pro-56`
2. **Delete** all four directories from disk. Quarantine copies go to
   `_project/quarantine/` (zipped, outside the web root) for evidence.
3. **Purge the DB artefacts** — only now, once nothing can recreate them:
   - Destroy all sessions for user 3, then delete user ID 3 (`sys_maint`) and all its usermeta.
   - `DELETE FROM wp_options WHERE option_name = '_wp_ip_id';`
   - `DELETE FROM wp_usermeta WHERE meta_key = '_wp_ip';`
   - Remove the four entries from the `active_plugins` option.
4. **Force-logout every user** by rotating the eight salts in `wp-config.php`. This invalidates the
   attacker's live session even if we missed a token.
5. **Re-scan** — confirm zero matches for `_wp_ip`, `BSC_SL_`, `bsc_sl_get_script`, `_0x[0-9a-f]{4}`
   and re-run the core checksum verification.
6. **Delete `wp-file-manager`** — dormant, but the most likely entry vector.

### 1.2 On the LIVE site — you must do this too

Cleaning `localhost` protects nobody. The live site is the one serving malware to real visitors.
The same four plugins and the same `sys_maint` user will be there.

**Credential rotation checklist — assume everything below is compromised:**

- [ ] All WordPress administrator passwords (users 1 and 2)
- [ ] Hosting control panel / cPanel password
- [ ] FTP / SFTP / SSH credentials **and keys**
- [ ] Database user password (and update `wp-config.php`)
- [ ] Any SMTP credentials stored in WP Mail SMTP
- [ ] Any API keys stored in plugin settings
- [ ] The eight `wp-config.php` salts (forces global logout)
- [ ] Enable 2FA on the hosting account and on WordPress admin logins

**Then:**
- [ ] Check for unauthorised scheduled tasks at the *hosting* level (real cron, not WP-Cron)
- [ ] Check `.htaccess` files in **every** directory, not just the root
- [ ] Review hosting access logs around **2026-08-01** for the entry point
- [ ] Check for unexpected SSH keys in `~/.ssh/authorized_keys`
- [ ] Request a malware re-scan from your host once clean
- [ ] Check Google Search Console for a manual action / "Security Issues" flag

I can generate a ready-to-run cleanup script for the live server (WP-CLI or plain PHP) once you
tell me how you access it — SSH, cPanel File Manager, or FTP only.

### 1.3 Acceptance criteria

- Zero signature matches across the entire tree
- Core checksums still 100% clean
- `wp_users` contains exactly 2 users
- Front-end HTML source contains no unexpected `<script>` blocks
- Site renders identically to the Phase 2 baseline *(baseline is captured before this, see below —
  in practice Phase 2's capture runs on the already-cleaned site, since the malware output must
  never be part of the reference)*

---

## Phase 2 — Pixel baseline capture

**This phase is the contract for "100% pixel perfect".** Without it, "pixel perfect" is an opinion.

**Deliverable:** `_project/baseline/` containing, for every URL on the site:

1. **Rendered HTML** (logged-out), normalised — nonces, timestamps, cache-buster query strings and
   inline IDs stripped so diffs are meaningful.
2. **Full-page screenshots** at 3 viewports: `1920` (desktop), `768` (tablet), `375` (mobile).
3. **Computed CSS dumps** for key elements.
4. **The generated Elementor CSS files** (`uploads/elementor/css/`) — these encode every style
   setting and are an excellent diff target.

**URL list:** all 21 published pages, all 15 posts, blog archive, case-studies archive, search
results, a 404, plus the header/footer/popup in isolation. ~45 URLs × 3 viewports.

**Tooling:** Playwright (Chromium) driving the local site, output committed to git.

Then, after **every** subsequent change, re-run the capture and diff. A regression shows up as a
changed pixel, not as a bug report three weeks later. This is how we keep the promise that
*"site aise he rahe, koi break na ho."*

---

## Phase 3 — Plugin consolidation

Removals are staged, one at a time, each followed by a baseline diff.

### 3.1 Delete — malware
`shop-mini-tools` · `faq-accordion-lite-b` · `easy-image-optimizer` · `custom-fields-pro-56`

### 3.2 Delete — redundant SEO (resolve the AIOSEO ↔ Yoast conflict)

**Keep Yoast (free, 24.3). Remove AIOSEO.**
Rationale: Yoast free covers everything this site needs; Yoast Premium 23.6 is *older* than the
free version installed, so it contributes nothing but risk. AIOSEO is the duplicate.

- Delete `all-in-one-seo-pack` — **migrate its stored meta to Yoast first** (titles, descriptions,
  canonicals, robots, OG data). Yoast has a built-in AIOSEO importer; we will verify field-by-field
  and diff the rendered `<head>` before and after.
- Delete `wordpress-seo-premium` (unlicensed, older than free).
- Delete `broken-link-checker-seo` — run link checking as a periodic external audit instead of a
  permanent runtime plugin; it is a known performance drain.

### 3.3 Delete — redundant performance stack

Keep **one** page-cache layer. On the live host that is likely WP Rocket's replacement; locally,
caching should be off entirely so we are always testing real output.

- Delete `wp-meteor` (JS deferral — overlaps WP Rocket and Debloat; frequent breakage source)
- Delete `debloat` (unused-CSS removal — overlaps WP Rocket; highest risk of visual regression)
- Delete `object-cache-pro` (unlicensed) — `redis-cache` (free, GPL) covers this
- Delete `wp-rocket` (unlicensed) → replace with **FlyingPress alternative or self-owned config**;
  see Phase 6 for the recommended free stack
- Remove the `boost-cache/`, `wp-rocket-config/`, `advanced-cache-backup.php`,
  `object-cache-backup.php` leftovers

### 3.4 Delete — redundant image optimisation

Keep **one**. `imagify` and `wp-smush-pro` do the same job; `easy-image-optimizer` is malware.
Recommendation in Phase 6: move to build-time / upload-time WebP+AVIF conversion we own, removing
the runtime plugin entirely.

### 3.5 Delete — redundant backup
Keep `updraftplus` (free tier is genuinely good). Delete `all-in-one-wp-migration`.

### 3.6 Delete — dev tools, one-offs, and low-value
`query-monitor` (dev only — reinstall on demand) · `wp-file-manager` (RCE history) ·
`duplicate-page` · `maintenance` · `vamtam-importers-e` (demo importer, already used) ·
`simple-copy-protection` (harmless, but right-click blocking stops no one and hurts UX —
folded into `piecyfer-core` as an optional toggle if you want to keep it)

### 3.7 Deferred to Phase 4 — do NOT touch these in Phase 3

`elementor-pro` · `elementskit` · `elementskit-lite` · `vamtam-elementor-integration-tecnologia` ·
`click-to-chat-for-whatsapp` · `optinmonster`

These four Elementor-related plugins are mutually entangled — the VamTam integration subclasses
Elementor Pro classes with inconsistent `class_exists()` guards, so deactivating Pro on its own
takes down the editor and the front end. They come out **together**, in Phase 4, once
`piecyfer-core` can stand in for all of them. See `02-WIDGET-REBUILD-SPEC.md`.

### 3.8 Keep

| Plugin | Why |
|---|---|
| `elementor` (free) | GPL, maintained, provides 94% of widgets |
| `wordpress-seo` (free) | Chosen SEO plugin |
| `updraftplus` | Backups |
| `wp-mail-smtp` | Transactional email deliverability |
| `google-analytics-for-wordpress` | Analytics — *candidate for replacement by a 20-line GA4 snippet in `piecyfer-core`* |
| `redis-cache` | Object cache (production only) |

**Result: 32 plugins → ~6 plugins + 1 owned plugin.**

---

## Phase 4 — `piecyfer-core`: the owned plugin 🏗️

The main build. One plugin, owned by you, GPL-compatible, no licence server, no phone-home,
no expiry.

### 4.1 Architecture

```
wp-content/plugins/piecyfer-core/
├── piecyfer-core.php              # bootstrap, version + requirement guards
├── composer.json                  # PSR-4 autoload, dev-only tooling
├── src/
│   ├── Plugin.php                 # container / bootstrap
│   ├── Widgets/
│   │   ├── AbstractWidget.php     # shared control groups + render helpers
│   │   ├── NavMenu.php            # registers as "nav-menu"
│   │   ├── Form/                  # the big one: fields, validation, actions
│   │   ├── Posts.php  · ArchivePosts.php  · PostInfo.php
│   │   ├── Theme/                 # site-logo, post-title, post-content, archive-title
│   │   ├── CallToAction.php · Blockquote.php · SearchForm.php
│   │   ├── TestimonialCarousel.php · Gallery.php · PostComments.php · Template.php
│   │   └── ElementsKit/           # icon-box, client-logo, back-to-top, social-share,
│   │                              # header-search, ekit-nav-menu, + your 2 custom widgets
│   ├── ThemeBuilder/              # locations, conditions, template resolution
│   ├── Popup/                     # trigger engine + display rules
│   ├── Controls/                  # custom-css, motion-fx, sticky control injection
│   ├── DynamicTags/               # the tags actually in use
│   └── Compat/                    # Elementor version-compatibility shims
├── assets/                        # only what we actually need; no jQuery-heavy deps
└── tests/
```

### 4.2 Build order (each step: build → render-diff → commit)

| Step | Scope | Notes |
|---|---|---|
| **4a** | Plugin skeleton + widget registration + `AbstractWidget` | Foundation |
| **4b** | 9 easy widgets: `template`, `search-form`, `blockquote`, `theme-site-logo`, `theme-archive-title`, `theme-post-title`, `theme-post-content`, `post-comments`, `post-info` | Quick wins, ~33 instances |
| **4c** | `nav-menu` (25 uses) + `ekit-nav-menu` | Highest-usage Pro widget; mobile/burger behaviour matters |
| **4d** | `posts` + `archive-posts` (15 uses) + the query-control layer they share | |
| **4e** | `call-to-action`, `testimonial-carousel`, `gallery` (6 uses) | |
| **4f** | 6 ElementsKit widgets + your 2 custom builder widgets (19 uses) | Straight ports |
| **4g** | **Theme Builder** — header, 2 footers, single-post, 2 archives, search, 404 + display conditions | Largest single piece |
| **4h** | **Form widget** (9 uses) — fields, validation, actions-after-submit, email, spam protection | Hardest single widget |
| **4i** | **Popup** — 1 popup, trigger + display conditions | |
| **4j** | Control injections: per-element **Custom CSS** (58), **Motion FX** scale (23), **Sticky** (3) | Applies to *all* widgets, so it lands after the widgets exist |
| **4k** | Global widgets (3) + dynamic tags in use | |
| **4l** | WhatsApp click-to-chat + FAQ accordion + any OptinMonster forms still needed | Replaces 3 more plugins |

### 4.3 Non-negotiable rules for every widget

1. **Same `get_name()`** as the widget it replaces — otherwise the page data orphans.
2. **Same control IDs**, including the responsive suffixes (`_tablet`, `_mobile`) and the
   `__globals__` / `__dynamic__` keys — otherwise saved settings are dropped.
3. **Same rendered markup and CSS class names** — `elementor-widget-nav-menu`,
   `elementor-nav-menu--main`, etc. The theme's stylesheet targets these; changing them breaks
   styling invisibly.
4. **Render-diff passes** before the step is considered done.
5. No calls to any Elementor **Pro** class. Only Elementor free's public API.

### 4.4 Future-proofing built in

- **Version guard:** declare a tested-up-to Elementor version. If Elementor ships a breaking
  change, `Compat/` shims it and an admin notice fires — the site degrades to a warning, never to
  a white screen.
- **Zero Pro dependency:** nothing in the plugin references Elementor Pro, so Pro's absence is
  not an error condition.
- **Own update channel:** plugin updates come from your own git repo (or a private update server),
  never from a nulled redistributor.
- **Fail-safe rendering:** every widget wraps its render in a guard so a PHP notice in one widget
  cannot blank the page.

---

## Phase 5 — Theme decision

Three options. My recommendation is **B**.

| | Approach | Effort | Risk | Future-proof |
|---|---|---|---|---|
| **A** | Buy a legitimate VamTam Tecnologia licence (~$69 on ThemeForest) | Lowest | Lowest | Medium — still a third-party theme |
| **B** ⭐ | **Build `piecyfer-theme`** — a lean own theme that reproduces Tecnologia's output exactly for the parts actually used | Medium | Low (guarded by render-diff) | **Highest** |
| **C** | Keep the nulled theme, add a child theme | Lowest | **High** — no updates, unknown provenance | None |

**Why B is realistic here:** the site's header, footer, single-post, archives, search and 404 are
**all built in Elementor Theme Builder**, not in theme PHP. The theme is therefore doing much less
work than it appears to. Once Phase 4g lands, `piecyfer-theme` needs only:

- the minimal template hierarchy (`index`, `page`, `single`, `archive`, `search`, `404`, `header`,
  `footer`)
- the CSS variables and typography scale from the Elementor kit
- the theme's own stylesheet, audited so only the rules actually matched by live pages survive
- the three legitimate customisations currently in `functions.php`

**Two things to preserve from the current theme:**
1. `<meta name="google-site-verification" content="VmNgA23F6JsL4JinPIqE7Wc7T57e0IHuPsRgvzC0Blk" />`
   — removing this breaks your Search Console verification.
2. The `elementor/widget/button/template_content` filter that strips
   `.elementor-button-content-wrapper`. This affects the markup of **all 30 button instances** —
   it must be replicated or every button's styling shifts.

**Interim step regardless of choice:** create a child theme *now* so the injected licence-bypass
line and any future edits live outside the parent. This is cheap and immediately reduces risk.

---

## Phase 6 — Performance

Only after the site is clean and stable. Measured, not guessed.

1. **Measure first** — Lighthouse + WebPageTest on the top 10 pages; record Core Web Vitals
   (LCP, INP, CLS) as the baseline.
2. **Asset diet** — with ElementsKit and Elementor Pro gone, a large amount of CSS/JS disappears
   for free. Measure the delta before optimising further.
3. **Conditional loading** — `piecyfer-core` enqueues each widget's CSS/JS only on pages where that
   widget is present. Elementor Pro does not do this; we can.
4. **Images** — 555 attachments. Convert to WebP/AVIF with `<picture>` fallbacks, enforce explicit
   `width`/`height` (kills CLS), lazy-load below the fold, eager-load the LCP image.
5. **Fonts** — self-host, `font-display: swap`, subset, preload the LCP font only.
6. **Caching (production)** — page cache at the host/CDN layer where possible; Redis object cache;
   a single, well-understood plugin rather than three fighting ones.
7. **Database** — prune 809 revisions (cap at 5/post via `WP_POST_REVISIONS`), clear expired
   transients, optimise tables.
8. **Critical CSS** — inline above-the-fold, defer the rest.
9. **Re-measure** and record the improvement.

---

## Phase 7 — Technical SEO

1. **Fix the duplicate-metadata defect** (Phase 3.2) — this is the largest single SEO win available
   and it is a bug fix, not an optimisation.
2. **Schema.org** — `Organization`, `WebSite` + `SearchAction`, `BreadcrumbList`, `Article` on posts,
   `Service` on service pages, `FAQPage` where FAQs exist. Emitted as JSON-LD from `piecyfer-core`
   so it survives any plugin change.
3. **Canonicals, robots, hreflang** — audited per template after the SEO consolidation.
4. **XML sitemaps** — exactly one source. Verify no orphan `sitemap_index.xml` from AIOSEO lingers.
5. **`robots.txt`** — reviewed; confirm nothing important is blocked.
6. **Internal linking + crawl depth** — audit with the 213 nav-menu items in mind.
7. **Core Web Vitals** — a ranking factor; covered by Phase 6.
8. **Redirects** — inventory `wpseo-redirects`, ensure no chains or loops after cleanup.
9. **Search Console** — resubmit sitemap, confirm no manual action from the malware period, request
   review if flagged.

> **Important:** the site was serving malicious JS to crawlers as well as users since ~2026-08-01.
> Check Search Console → Security Issues immediately, and request a review once clean.

---

## Phase 8 — Hardening & future-proofing

**File & config**
- `define( 'DISALLOW_FILE_EDIT', true );` — no theme/plugin editor in wp-admin
- `define( 'DISALLOW_FILE_MODS', true );` on production — no installs from the dashboard
- Block PHP execution in `wp-content/uploads` via `.htaccess`/nginx rule
- Disable XML-RPC unless something needs it
- Correct file permissions (dirs 755, files 644, `wp-config.php` 600)

**Access**
- 2FA on all admin accounts
- Rename/limit login attempts; consider restricting `wp-admin` by IP
- Principle of least privilege — do the two accounts both need `administrator`?

**Integrity monitoring**
- A small `piecyfer-core` module that hashes `wp-admin`, `wp-includes`, active theme and plugin
  files daily and emails on any unexpected change. This is exactly what would have caught the
  2026-08-01 infection within 24 hours.
- Weekly core-checksum verification against the WordPress.org API.
- Alert on any new administrator account.

**Update policy — the actual answer to "updates se site pe koi faraq na paray"**
- **Staging first.** Every update lands on staging, gets a render-diff against the baseline, and
  only then goes to production. This is the real guarantee; auto-updates alone are not.
- Auto-update WordPress core (minor) and the handful of remaining GPL plugins.
- `piecyfer-core` and `piecyfer-theme` are versioned in git and updated deliberately.
- **Never install nulled software again.** It is not a licensing lecture — it is the direct cause
  of this incident. The plan above removes the need for it entirely: after Phase 4, the only paid
  plugin left is none.

---

## Effort estimate

| Phase | Scope | Relative effort |
|---|---|---|
| 0 | Backup & git | Small |
| 1 | Malware eradication | Small (local) + your action on live |
| 2 | Baseline harness | Medium |
| 3 | Plugin consolidation | Medium |
| 4 | `piecyfer-core` (22 widgets + theme builder + popup + controls) | **Large — the bulk of the work** |
| 5 | `piecyfer-theme` | Medium |
| 6 | Performance | Medium |
| 7 | Technical SEO | Small–Medium |
| 8 | Hardening | Small |

Within Phase 4, roughly half the effort is concentrated in **4g (Theme Builder)** and
**4h (Form)**. Everything else is comparatively mechanical.

---

## Decisions I need from you

1. **Live site access** — SSH, cPanel, or FTP? Determines the shape of the live cleanup script.
   *(Phase 1.2 is blocked on this; everything else can proceed.)*
2. **Theme** — option A, B, or C from Phase 5?
3. **WP Rocket replacement** — free stack, or will you buy a licence?
4. **Analytics** — keep MonsterInsights, or replace with a lightweight owned GA4 snippet?
5. **Right-click protection** — keep it or drop it?

None of these block Phases 0–2, so I am starting there now.
