# Moving this project to another machine

Read this once before starting. It takes about twenty minutes.

## Why not UpdraftPlus

UpdraftPlus backs up a *WordPress site* — database, themes, plugins, uploads. That is not what
this project is. The value here is the **git history and `_project/`**: every verified step with
the evidence that verified it, three test harnesses, the reference snapshots everything is
measured against, and the specs. UpdraftPlus does not carry `.git`, and without it a fresh Claude
session has a working site and no idea what was done to it or why.

So: copy the folder, import the database, and the history comes with it.

## What you should have

| File | What it is | Size |
|---|---|---|
| `piecyfer-project.tar.gz` | The whole site folder **including `.git` and `_project/`** | ~350 MB |
| `piecyfer-uploads.tar.gz` | `wp-content/uploads` — the media library | ~315 MB |
| `piecyfer-db-YYYY-MM-DD-handover.sql.gz` | The database | ~35 MB |

Two things are deliberately **not** in there because they regenerate in minutes and would have
added 1.2 GB: the screenshot PNGs under `_project/snapshots/*/shots/`, and
`_project/pixel-tool/node_modules`. The normalised HTML, the metadata and the diff reports — the
actual evidence — **are** included.

## Restore

1. **Install XAMPP** and start Apache and MySQL. Match the paths if you can; if you cannot, see
   *Different paths* below.

2. **Unpack the project** so it lands at `C:\xampp\htdocs\piecyfer`:
   ```bash
   cd /c/xampp/htdocs
   tar -xzf /path/to/piecyfer-project.tar.gz
   cd piecyfer
   tar -xzf /path/to/piecyfer-uploads.tar.gz          # restores wp-content/uploads
   ```

3. **Create the database and import**:
   ```bash
   /c/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE piecyfer DEFAULT CHARACTER SET utf8mb4;"
   gunzip -c piecyfer-db-*-handover.sql.gz | /c/xampp/mysql/bin/mysql.exe -u root piecyfer
   ```

4. **Reinstall the harness dependencies**:
   ```bash
   cd _project/pixel-tool && npm install && npx playwright install chromium
   ```

5. **Check the repo survived**:
   ```bash
   cd /c/xampp/htdocs/piecyfer
   git log --oneline -1        # expect the handover commit
   git status --porcelain      # expect no tracked changes
   git fsck --no-progress      # expect no errors
   ```

## Verify before doing any work

Do not skip these. If any one fails, stop and say so rather than working around it.

```bash
# Site answers
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/piecyfer/
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/piecyfer/contact-us/
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/piecyfer/no-such-page-xyz/   # must be 404

# Our widgets are genuinely the ones rendering, and so are their skins
php _project/scripts/which-implementation.php ; echo "exit=$?"
```

The third curl is not padding. Two 200s prove nothing on their own — a themed error page is also a
200. Check that the miss is a 404 and that the two hits contain real markup, or the check cannot
go red.

### The pixel check in this file used to be a lie — read this before running it

The original third step was:

```bash
cd _project/pixel-tool && node capture.js restore-check --quick && node compare.js ref2-a restore-check
```

It cannot work, for two independent reasons, and it fails *green*:

1. **`ref2-a/shots/` is not in the archive.** It was excluded deliberately (see the table above),
   and the claim that the verify run "re-captures the screenshots you did not copy" is wrong —
   `capture.js <label>` writes to `snapshots/<label>/`, never back into `ref2-a`. So `compare.js`
   iterates an empty baseline shots list, compares **zero** screenshots, and still prints
   `_None — all screenshots identical within tolerance._`. Its `nothingCompared` guard only fires
   when the *second* snapshot is empty, not the first. This is a sixth false-pass mechanism on top
   of the five in `CLAUDE.md`.

2. **`ref2-a` is a stale baseline.** It predates the form and posts widget takeovers. On the
   original machine `ref2-a → p4g-form` and `ref2-a → p4i-posts` both reported DIFFERENCES FOUND on
   all nine pages. "Expect zero visual changes" was already untrue before anyone moved machines.

So on a fresh machine there is **no usable visual reference in the archive at all**. The honest
move is to build one and prove it stable, exactly as `ref-a`/`ref-b` did originally:

```bash
cd _project/pixel-tool
node capture.js ref3-a            # full 43 pages, ~17 min
node capture.js ref3-b            # again, unchanged site
node compare.js ref3-a ref3-b     # must be IDENTICAL, and must report ~129 screenshots compared
```

Read the "Screenshots compared" row every single time. If it says 0, the run proved nothing no
matter what the verdict says.

Markup differences limited to asset ordering are reported as such and do not fail the run — but do
not treat them as meaningless either. See *Asset order is not cosmetic* below.

## Asset order is not cosmetic

`compare.js` downgrades "same set of style/script handles, different order" to a note rather than a
failure. That is a reasonable default, but it is not proof of harmlessness, and on the Laragon move
it hid something real.

Stylesheet order changes *when* a rule applies, and several widgets measure the DOM from JavaScript
before deciding what to render. The posts widget is the clearest case. VamTam's
`vamtam-posts-base.js` decides the thumbnail crop with:

```js
imageParentRatio = $imageParent.outerHeight() / $imageParent.outerWidth();
imageRatio       = image.naturalHeight / image.naturalWidth;
$imageParent.toggleClass('elementor-fit-height', imageRatio < imageParentRatio);
```

Measured on Laragon, home page, desktop: the container is 363×199 (`--item-ratio` 0.55, and the
`:after` probe confirms the CSS *is* applied), so `imageParentRatio` is 0.5482, while the 1024×704
thumbnail gives `imageRatio` 0.6875. `0.6875 < 0.5482` is false, so `elementor-fit-height` is
correctly absent — the image is taller than its box and must be fitted by width.

`p4i-posts`, captured on the XAMPP machine, **has** that class on the same three thumbnails. For it
to be there, the container must have measured taller than the image ratio, which means the widget's
JS ran before the per-post CSS had applied. In other words the reference captured the losing side of
a race, and the "asset order only" reordering on the other eight pages is the same root cause
showing up somewhere less visible.

Consequence: **do not treat `p4i-posts` or `ref2-a` as ground truth for the posts widget.** Rebase
onto a `ref3` proven stable over two runs, and if a future change makes `elementor-fit-height`
appear or disappear, measure the two ratios before assuming it is a regression.

## Different paths

If the site does not land at `C:\xampp\htdocs\piecyfer`, these need updating and they are easy to
miss. For the Laragon layout actually in use now, see `DEPLOY.md`.

- `wp-config.php` — database credentials if they differ.
- `_project/pixel-tool/urls.js` — the `BASE` constant. **Only if the URL changes**; keeping the URL
  identical is strongly preferred (see `DEPLOY.md` on the serialisation trap).
- `_project/scripts/*.php` — several hard-code the absolute path to `wp-load.php`.
- `_project/pixel-tool/capture.js` and `_project/behaviour-tool/run.js` — the `PHP_BIN` default.

## Two things the capture environment must pin, not inherit

Both of these produced differences that looked like site regressions and were not.

- **Touch capability.** Playwright's default is not "no touch", it is "whatever the host reports".
  The machine every snapshot up to `p4i-posts` was captured on had a touchscreen, so
  `navigator.maxTouchPoints > 0`, so Elementor's
  `isTouchDevice: "ontouchstart" in window || navigator.maxTouchPoints > 0` was true and every
  `<body>` carried `e--ua-isTouchDevice`. On a machine without a touchscreen that is a 20-byte
  markup change on all 43 pages. `capture.js` now pins it with an init script that overrides
  `maxTouchPoints` only. Do **not** "fix" this with Playwright's `hasTouch: true` — that also
  defines `ontouchstart`, which flips Swiper from pointer events to touch events and drops
  `swiper-pointer-events` and the `cursor: grab` inline style from every carousel. Verified both
  ways.

- **WordPress's ability to rewrite itself.** UpdraftPlus was replaced on disk, 1.26.6 →
  2.26.6.26, during a restore session that only served front-end page loads — while the plugin was
  *inactive* and with an empty `auto_update_plugins`. `wp-config.php` now sets
  `AUTOMATIC_UPDATER_DISABLED`, `DISALLOW_FILE_MODS` and `DISABLE_WP_CRON`. Check those are still
  in place before trusting any capture; without them site code can change between two runs, or
  inside one.

The database also stores the site URL in `wp_options` (`siteurl`, `home`). If the URL changes, a
search-replace is needed — and it must be **serialisation-aware**, because Elementor stores its
page data as serialised JSON inside those rows. A naive `sed` over the SQL dump will corrupt every
Elementor page on the site. Use WP-CLI's `wp search-replace` or Interconnect/it's tool.

## Safety notes

- The malware is gone from this copy, and the `_project/quarantine` folder is empty — samples were
  kept outside the web root and are not in the archive.
- **The live site is a separate installation and is still infected.** Nothing here has touched it.
  See `_project/PHASES.md` Phase G.
- `wp-content/uploads/elementor/forms` contains 32 real CVs. They are personal data and they are
  currently downloadable without authentication. Treat the archive accordingly, and see Phase A
  defect 3.
