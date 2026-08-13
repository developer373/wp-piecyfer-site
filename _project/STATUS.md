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
| **2 — Pixel baseline capture** | 🟡 In progress |
| 3 — Plugin consolidation | ⬜ Not started |
| 4 — `piecyfer-core` plugin | ⬜ Not started |
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
  normalised HTML + broken-asset and console-error tracking
- `compare.js <a> <b>` — markup diff, per-pixel screenshot diff with anti-aliasing tolerance,
  new-console-error and newly-broken-asset detection. Exits non-zero on any change.
- `analyse-widgets.js` — the widget/settings census behind `02-WIDGET-REBUILD-SPEC.md`

Baseline capture is running now. Every later phase gets gated on
`node compare.js baseline <step>` returning zero.

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
