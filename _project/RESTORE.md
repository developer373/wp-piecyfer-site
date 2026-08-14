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

# Our widgets are genuinely the ones rendering, and so are their skins
C:\xampp\php\php.exe _project/scripts/which-implementation.php ; echo "exit=$?"

# The site still matches the reference
cd _project/pixel-tool && node capture.js restore-check --quick && node compare.js ref2-a restore-check
```

The last one re-captures the screenshots you did not copy, so the first run takes a few minutes.
Expect zero visual changes. Markup differences limited to asset ordering are fine and are reported
as such.

## Different paths

If the site does not land at `C:\xampp\htdocs\piecyfer`, three things need updating and they are
easy to miss:

- `wp-config.php` — database credentials if they differ.
- `_project/pixel-tool/urls.js` — the `BASE` constant.
- `_project/scripts/*.php` — several hard-code `C:/xampp/htdocs/piecyfer/wp-load.php`.

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
