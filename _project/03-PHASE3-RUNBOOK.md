# Phase 3 runbook — plugin consolidation

**Prerequisite:** a completed `baseline` capture in `_project/snapshots/baseline/`.
**Goal:** 27 plugins → 6, with a proof at every step that nothing moved.

Static dependency analysis found **no hard code references** from the theme or the VamTam
plugins to anything on this list, so every removal below is expected to be inert. "Expected" is
not "verified" — that is what the capture is for.

---

## The loop

Every group follows the same four steps. Never batch two groups into one check: if something
breaks you want to know which plugin did it.

```bash
cd _project/pixel-tool

# 1. deactivate (never delete first — deactivation is instantly reversible)
php ../scripts/plugin-toggle.php off <slug> [<slug> …]

# 2. capture the 8-page subset
node capture.js step-<n>-<name> --quick

# 3. prove nothing moved
node compare.js baseline step-<n>-<name>
```

**4. Then branch on the result:**

- **Exit 0 → clean.** Delete the plugin directories, `git commit`, move to the next group.
- **Exit 1 → something changed.** Read `_project/snapshots/_diff-baseline-step-<n>-<name>/report.md`
  and open the `.diff.png` files. Either the change is understood and acceptable (record why in
  the commit message), or reactivate with `plugin-toggle.php on <slug>` and investigate.

At the end of all groups, run the **full** 39-page capture as the phase gate:

```bash
node capture.js phase3-complete
node compare.js baseline phase3-complete
```

**Rollback at any point:** `php ../scripts/plugin-toggle.php on <slug>`, or
`git checkout <commit>` for file-level changes. Every `plugin-toggle` write also snapshots the
previous `active_plugins` value to an `active_plugins_backup_<timestamp>` option.

---

## Group 1 — SEO de-duplication 🔴 highest value

**This is a bug fix, not an optimisation.** AIOSEO and Yoast are both active and both emitting
canonical tags, Open Graph tags, meta descriptions, robots directives and XML sitemaps. Search
engines are receiving duplicated and possibly conflicting metadata from this site right now.

Keep **Yoast free 24.3**. Remove AIOSEO and Yoast Premium 23.6 (unlicensed, and *older* than the
free version already installed, so it adds nothing but risk).

**Do this before removing anything — data migration comes first:**

1. In wp-admin, run Yoast's built-in AIOSEO importer (SEO → Tools → Import and Export).
2. Verify field-by-field on a sample of 10 pages: title, meta description, canonical, robots,
   OG title/description/image, Twitter card.
3. Diff the rendered `<head>` of those pages before and after. The capture harness already
   stores full HTML, so this is a `diff` on the `html/` directory.
4. Only then deactivate.

```bash
php ../scripts/plugin-toggle.php off all-in-one-seo-pack wordpress-seo-premium broken-link-checker-seo
```

`broken-link-checker-seo` goes too: link checking belongs in a periodic external audit, not a
permanent runtime plugin. It is a well-known performance drain.

⚠️ Expect the `<head>` to change here — that is the entire point. This is the one group where a
non-zero diff is the success condition. Read the diff carefully and confirm that what
disappeared is only the *duplicate* set of tags.

Afterwards, check that no orphan `sitemap_index.xml` or AIOSEO redirect table is still being
served, and resubmit the sitemap in Search Console.

## Group 2 — dev tools and one-offs

Zero risk. These have no front-end output.

```bash
php ../scripts/plugin-toggle.php off query-monitor duplicate-page vamtam-importers-e
```

- `query-monitor` — development tool; reinstall on demand
- `duplicate-page` — admin convenience only
- `vamtam-importers-e` — demo-content importer, already used, never needed again

`maintenance` is already deactivated (it was hiding the entire front end). Delete it here.

## Group 3 — image optimisation

Three plugins doing one job; one of them was malware and is already gone.

```bash
php ../scripts/plugin-toggle.php off imagify wp-smush-pro
```

Both are unlicensed. Neither is needed at runtime: Phase 6 replaces them with WebP/AVIF
conversion we own, run at upload time.

Before deleting, confirm no page is serving images from a Smush or Imagify CDN URL — if it is,
those URLs must be rewritten to local paths first or images will 404.

## Group 4 — backup consolidation

```bash
php ../scripts/plugin-toggle.php off all-in-one-wp-migration
```

Keep UpdraftPlus; its free tier is genuinely good. Two backup plugins is one too many, and
`ai1wm-backups/` can be cleared afterwards.

## Group 5 — the performance stack ⚠️ most likely to show a diff

Three overlapping optimisation layers currently fight each other. This is the group most likely
to produce a real visual change, because removing a CSS-stripping or JS-deferring layer changes
what actually reaches the browser.

Remove **one at a time**, capture between each:

```bash
php ../scripts/plugin-toggle.php off wp-meteor      # JS deferral
php ../scripts/plugin-toggle.php off debloat        # unused-CSS removal — highest visual risk
php ../scripts/plugin-toggle.php off object-cache-pro
php ../scripts/plugin-toggle.php off wp-rocket      # last: it is the page cache
```

Notes:

- `debloat` strips "unused" CSS. Removing it should *restore* CSS, not remove it, so any diff
  should show elements gaining style rather than losing it. If something loses styling, stop.
- `object-cache-pro` is unlicensed; free `redis-cache` covers the same ground. Keep `redis-cache`
  installed but note that on localhost there is no Redis, so it is inert here.
- `wp-rocket` is unlicensed. Purge its cache **before** deactivating, and delete
  `wp-content/advanced-cache.php`, `wp-content/wp-rocket-config/` and the leftover
  `advanced-cache-backup.php` / `object-cache-backup.php` afterwards.
- On localhost, caching should end up **off entirely** so we are always testing real output.

## Group 6 — low-value front-end plugins

```bash
php ../scripts/plugin-toggle.php off simple-copy-protection
```

Harmless and written in-house, but right-click blocking stops nobody, breaks keyboard copy for
legitimate users, and interferes with accessibility tools. If you want it back it becomes a
one-line toggle in `piecyfer-core` rather than a plugin.

`click-to-chat-for-whatsapp` and `optinmonster` stay for now — they have real front-end output
and are replaced in Phase 4n.

---

## Not in Phase 3 — deferred to Phase 4

```
elementor-pro
elementskit
elementskit-lite
vamtam-elementor-integration-tecnologia
```

These four are entangled. `vamtam-elementor-integration-tecnologia` declares
`class Vamtam_Widget_Nav_Menu extends \ElementorPro\Modules\NavMenu\Widgets\Nav_Menu` and
similar in `posts-base.php`, `login.php` and `popup.php` — **without `class_exists()` guards**,
and inside functions hooked to widget registration. Deactivating Pro alone therefore produces a
fatal at registration time: a white screen in the editor and on the front end.

They come out together in Phase 4, once `piecyfer-core` can stand in for all of them.
See `02-WIDGET-REBUILD-SPEC.md`.

---

## Expected end state

| Kept | Why |
|---|---|
| `elementor` (free) | GPL, maintained, 94% of all widget instances |
| `wordpress-seo` (free) | The single SEO plugin |
| `updraftplus` | Backups |
| `wp-mail-smtp` | Transactional email deliverability |
| `redis-cache` | Object cache (production only) |
| `piecyfer-core` | Ours |

Plus, until Phase 4 retires them: `elementor-pro`, `elementskit`, `elementskit-lite`,
`vamtam-elementor-integration-tecnologia`, `click-to-chat-for-whatsapp`, `optinmonster`,
`google-analytics-for-wordpress`.

**27 plugins → 13 after Phase 3 → 6 after Phase 4.**

---

## Housekeeping after the phase

- Delete deactivated plugin directories from disk (deactivated ≠ removed; the
  `wp-file-manager` RCE history is the argument for this).
- Clear the `_transient_*` rows left behind by removed plugins.
- Drop any custom tables the removed plugins created — check before dropping.
- Prune old snapshot directories; keep `baseline` and the latest.
- `git commit` after each group, with the diff verdict in the message.
