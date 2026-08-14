# Running this locally on Laragon, and putting it live on Cloudways

## First: should this go live *now*?

It can, and there is a real argument for it — but be clear about what you would be shipping.

**What deploying now fixes.** The live site is still infected. It has four malicious plugins, a
hidden administrator (`sys_maint`) that recreates itself on every page load, and a blockchain-hosted
script loader executing attacker-controlled JavaScript in every anonymous visitor's browser. This
local copy has none of that: the plugins are gone, the account is destroyed, all eight salts are
rotated, and `wp-file-manager` — the likely entry vector — is deleted. That is a large, immediate
security win and it is a legitimate reason to deploy before the rebuild finishes.

**What deploying now does *not* fix.** Elementor Pro, ElementsKit Pro and the Tecnologia theme are
still nulled and still active. They still receive no security updates, and that is precisely how the
site was compromised in the first place. A signature scan came back clean, but a clean scan means
"no *known* backdoor", not "no backdoor" — nulled packages are redistributed by people whose whole
business is putting something in them.

So: deploying now is **damage control, not the destination**. If you do it, treat it as buying time
and finish Phase B and Phase C soon after.

**Three things to do at the same time, not later:**

1. Rotate every credential again — WordPress admin passwords, database password, SFTP/SSH, and the
   salts. The attacker had a live session from `69.118.41.219`; assume everything they could read,
   they read.
2. Decide what happens to the 32 CVs in `wp-content/uploads/elementor/forms`. They are real personal
   data and they currently download with no authentication. Either move them out of the web root
   before deploying, or accept that you are re-publishing them.
3. Take a full backup of the live site *before* overwriting it. Not because this copy is bad, but
   because the live database has moved on since the snapshot — any comment, form submission or
   content edit made there since is not in this copy and will be lost.

---

## Local on Laragon

Laragon and XAMPP differ in two ways that matter here: where the web root lives, and what URL the
site is served on. The second one is the dangerous one.

### Keep the URL identical — this is the important part

Elementor stores its page data as **serialised** JSON inside `wp_options` and `wp_postmeta`. A
serialised string carries the byte length of every value it contains. Change
`http://localhost/piecyfer` to `http://piecyfer.test` with a naive find-and-replace and every one of
those lengths becomes wrong, and **every Elementor page on the site silently stops rendering**.

The simplest way to avoid the whole problem is to not change the URL:

```
C:\laragon\www\piecyfer          →  http://localhost/piecyfer/
```

Laragon serves `C:\laragon\www` at `localhost` out of the box, so putting the folder there gives you
the same URL this project was built and captured against. No search-replace, no risk, and the pixel
harness keeps working against its existing reference.

Laragon will also offer `piecyfer.test` via its auto virtual hosts. **Ignore it** unless you have a
reason, and if you do use it, see *Changing the URL properly* below.

### Steps

```bash
# 1. Unpack
cd /c/laragon/www
tar -xzf /path/to/piecyfer-project.tar.gz
cd piecyfer
tar -xzf /path/to/piecyfer-uploads.tar.gz

# 2. Database (Laragon's MySQL is on 3306, user root, empty password by default)
"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root -e "CREATE DATABASE piecyfer DEFAULT CHARACTER SET utf8mb4;"
gunzip -c piecyfer-db-*-handover.sql.gz | "C:\laragon\bin\mysql\...\bin\mysql.exe" -u root piecyfer

# 3. Harness dependencies
cd _project/pixel-tool && npm install && npx playwright install chromium
```

Your MySQL path will differ by version — check `C:\laragon\bin\mysql\`.

### Then fix the hard-coded paths

Several files assume XAMPP. They are few and the list is complete:

| File | What to change |
|---|---|
| `_project/scripts/*.php` | `require_once 'C:/xampp/htdocs/piecyfer/wp-load.php'` → your path. Affects `which-implementation.php`, `clear-elementor-cache.php`, `set-experiment.php`, `dump-eldata.php`, `theme-builder-routing.php`, `posts-*.php` |
| `_project/pixel-tool/capture.js` | `PHP_BIN` default (`C:/xampp/php/php.exe`) — or set the `PHP_BIN` environment variable instead of editing |
| `_project/pixel-tool/urls.js` | `BASE` — only if you changed the URL |
| `CLAUDE.md` | the Environment section, so the next session is told the truth |

`wp-config.php` needs no change if the database name, user and password are the same.

### Verify before doing any work

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/piecyfer/
php _project/scripts/which-implementation.php ; echo "exit=$?"
cd _project/pixel-tool && node capture.js laragon-check --quick && node compare.js ref2-a laragon-check
```

Expect exit 0 and zero visual changes. Fonts can differ between machines and shift text rendering by
a pixel or two; if you see small differences everywhere rather than in one place, that is the cause,
and the honest fix is to re-baseline on the new machine rather than to widen the tolerance.

---

## Live on Cloudways

### Before you touch anything

Take Cloudways' own backup of the live application. It is one click and it is the thing that lets
you undo a bad deploy.

### The URL *will* change, so do the search-replace properly

Going live means `http://localhost/piecyfer` → `https://www.piecyfer.com`. That is the serialised-data
problem described above, and it is the single most likely way to destroy this site.

**Never** do it with `sed` on the SQL dump, and never with a plain SQL `REPLACE()`. Use a
serialisation-aware tool. Cloudways ships WP-CLI:

```bash
# on the Cloudways server, in the application root
wp search-replace 'http://localhost/piecyfer' 'https://www.piecyfer.com' --all-tables --precise --dry-run
# read the summary, then run it for real without --dry-run
```

`--dry-run` first, every time. `--all-tables` matters because Elementor writes to `wp_postmeta` and
`wp_options`, and some plugins use their own tables.

### Deploy

1. **Upload the files.** SFTP or `rsync` the whole folder into the application's `public_html`.
   Exclude `_project/` — it is the development history and has no business on a public server. Also
   exclude `.git` unless you have a reason to want it there, and if you do, make sure it is not
   web-readable.

2. **Import the database.** Cloudways gives you phpMyAdmin and MySQL credentials. Drop the existing
   tables first, or import into a fresh database and repoint `wp-config.php`.

3. **Rewrite `wp-config.php`** for the live database name, user, password and host. Generate **new**
   salts (https://api.wordpress.org/secret-key/1.1/salt/) — do not carry the local ones across.

4. **Run the search-replace** as above.

5. **Fix permissions.** Cloudways runs PHP as the application user; files 644, directories 755, and
   `wp-config.php` 600.

6. **Flush Elementor's caches** — Elementor → Tools → Regenerate CSS & Data. The generated CSS files
   contain absolute URLs and will be wrong until regenerated.

7. **Check `.htaccess`** exists and carries the WordPress rewrite block, or permalinks 404.

### After deploying

```
/                      200
/contact-us/           200 and the form renders
/blogs/                200
/category/erp/         200
/?s=software           200
a single blog post     200, and the comment form names the right post
```

Submit the contact form once with a real address you control and confirm the mail arrives. That is
the check that matters most and it is the one people skip.

Then: change every password again, and confirm `sys_maint` does not exist in the live database:

```sql
SELECT ID, user_login, user_email, user_registered FROM wp_users;
SELECT * FROM wp_usermeta WHERE meta_key = '_wp_ip';
SELECT * FROM wp_options WHERE option_name = '_wp_ip_id';
```

All three should come back empty of anything suspicious. If `sys_maint` reappears after you delete
it, a malicious plugin is still on the server recreating it — stop and re-run the audit.

---

## Changing the URL properly, if you must

If you do end up on `piecyfer.test` or any other host locally:

```bash
wp search-replace 'http://localhost/piecyfer' 'http://piecyfer.test' --all-tables --precise --dry-run
```

Then update `_project/pixel-tool/urls.js` `BASE`, and **re-capture the reference** — `ref2-a` was
taken against the old URL and every page in it contains absolute links, so it is no longer a valid
comparison target. Capturing a new reference is cheap; comparing against a stale one is how you get
39 pages of meaningless differences and stop trusting the harness.
