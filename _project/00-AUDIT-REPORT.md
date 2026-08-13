# PieCyfer — Security Audit & Site Assessment

**Date:** 2026-08-13
**Site:** PieCyfer – Software Development Company
**Install:** `C:\xampp\htdocs\piecyfer` (local XAMPP copy) · `http://localhost/piecyfer`
**WordPress:** 7.0.2 · **PHP:** 8.2.12 · **DB:** MariaDB 10.4.32 · prefix `wp_`

> ⚠️ This folder (`_project/`) is internal documentation. Exclude it from any production deploy.

---

## 1. Executive summary

| Area | Verdict |
|---|---|
| WordPress core | ✅ **Clean** — 0 modified, 0 missing, 0 unknown files vs official checksums |
| `wp-config.php` / `.htaccess` | ✅ Clean, no injected code |
| mu-plugins / drop-ins | ✅ None present / clean |
| Uploads directory | ✅ Clean — no PHP shells |
| Post & postmeta content | ✅ Clean — no injected scripts |
| WP-Cron | ✅ Clean — no malicious hooks |
| **Plugins** | 🔴 **4 malicious plugins found** |
| **Database users** | 🔴 **1 hidden backdoor administrator** |
| Theme licence | 🟠 Nulled (Envato bypass patch) |
| Commercial plugins | 🟠 8 unlicensed / nulled |
| Plugin stack | 🟠 Severe redundancy — 3 SEO, 6 caching, 3 image optimisers, 2 backup |

**The infection is fully contained in `wp-content/plugins` plus three database rows.** Core, theme
files, uploads and page content were not tampered with. Remediation is therefore surgical — no
reinstall of WordPress is required.

---

## 2. Malware findings

### 2.1 Backdoor family "`_wp_ip`" — 3 cloned plugins

Three plugins carry a **byte-for-byte identical PHP backdoor** with three different JavaScript
payloads. Each masquerades as an innocuous utility with a fabricated author.

| Directory | Claimed name | Claimed author | Size |
|---|---|---|---|
| `shop-mini-tools/` | Shop Mini Tools 2.4.1 | "BlueLeaf Media" | 127 KB |
| `faq-accordion-lite-b/` | FAQ Accordion Lite 3.0.9 | "CodeCraft Studio" | 117 KB |
| `easy-image-optimizer/` | Easy Image Optimizer 3.6.1 | "SoftGrove" | 120 KB |

`easy-image-optimizer` is **impersonating a real plugin** (the genuine Easy Image Optimizer is by
Exactly WWW) — a deliberate choice so it survives a casual glance at the plugin list.

**What the PHP does:**

1. **Creates and self-heals a hidden administrator.** Credentials are hex-escaped to defeat
   plain-text grepping:
   ```php
   WP_IP_LOGIN = "\x73\x79\x73\x5f\x6d\x61\x69\x6e\x74"   // sys_maint
   WP_IP_EMAIL = "\x73\x79\x73\x40\x6c\x6f\x63..."         // sys@localhost.local
   WP_IP_PASS  = "\x43\x68\x61\x6e\x67\x65..."             // ChangeMe_Str0ng!
   ```
   Hooked on both `admin_init` (priority 1) and `init` (priority 20) via `_wp_ip_boot_admin_user()`.
   If you delete the user, it is **recreated on the next page load**. It also re-promotes the account
   to `administrator` if you demote it, and recovers it by login *or* by email *or* by the `_wp_ip`
   usermeta marker — three independent lookup paths.

2. **Hides the account from you.** A `pre_user_query` filter appends
   `AND wp_users.ID NOT IN (SELECT user_id FROM wp_usermeta WHERE meta_key='_wp_ip' AND meta_value='1')`
   whenever the Users screen renders, and a `rest_user_query` filter does the same for the REST API.
   *The account is invisible in wp-admin — the user count and the user list both exclude it.*

3. **Injects obfuscated JavaScript** into `wp_head` and `wp_footer` at priority 999, via
   `_wp_ip_inject()`. The injection is deliberately **skipped for logged-in administrators and on
   `wp-login.php`** — so the site owner never sees it while browsing their own site. Only anonymous
   visitors (and search-engine crawlers) receive it.

**The JavaScript payload** is ~120 KB of obfuscator.io output (hex-named identifiers, RC4-encrypted
string table, arithmetic-wrapped index lookups). All three copies share the delivery mechanism but
carry **different payload bodies** (distinct MD5s), which is consistent with rotating/staged
payloads. Deep deobfuscation was attempted and abandoned as unnecessary — the delivery vector,
the audience targeting (anonymous visitors only), and the pairing with a hidden admin account are
unambiguous. The payload is being removed wholesale, so its exact behaviour does not change the fix.

### 2.2 `custom-fields-pro-56` — blockchain-hosted script loader ("EtherHiding")

Claimed as *"Custom Fields Pro 2.2.8" by "Web Innovators"*. This one is more sophisticated and is
**not** part of the `_wp_ip` family.

```php
define('BSC_SL_CONTRACT', '0xD4E68441519d4dDFd06a556D0A9e86c0c33c68D5');
```

Flow:

1. Enqueues `js/bsc-loader.js` on every **front-end** page (skipped in admin).
2. That script POSTs to `admin-ajax.php?action=bsc_sl_get_script`, registered for both
   `wp_ajax_` and **`wp_ajax_nopriv_`** — i.e. reachable by anonymous visitors.
3. The PHP handler performs an `eth_call` against a **Binance Smart Chain testnet** smart contract
   (method selector `0x620b7303`), trying four RPC endpoints in turn:
   `bsc-testnet-rpc.publicnode.com`, `bsc-testnet.bnbchain.org`,
   `data-seed-prebsc-1-s1.bnbchain.org:8545`, `bsc-testnet.drpc.org`.
4. It ABI-decodes the returned string (up to **512 KB**) and returns it as JSON.
5. `bsc-loader.js` takes that string and does:
   ```js
   var el = document.createElement('script');
   el.text = source;
   document.body.appendChild(el);
   ```
   — **arbitrary attacker-controlled JavaScript, executed in every visitor's browser.**

This is the *EtherHiding* technique. The command-and-control payload lives on a public blockchain,
so it cannot be taken down, blocklisted, or seized. The attacker updates the contract's stored
string and every infected site serves new code on the next page view — no further access to your
server required.

**Implication:** this plugin alone gives the attacker permanent, un-revokable remote code execution
in your visitors' browsers for as long as the file exists.

### 2.3 Database artefacts

| Location | Value | Meaning |
|---|---|---|
| `wp_users` ID 3 | `sys_maint` / `sys@localhost.local`, registered **2026-08-01 10:33:12** | Backdoor administrator |
| `wp_usermeta` (user 3) | `wp_capabilities` = `administrator`, `wp_user_level` = 10 | Full privileges |
| `wp_usermeta` (user 3) | `_wp_ip` = `1` | Hide-from-list marker |
| `wp_usermeta` (user 3) | `session_tokens` — **1 live session** | Attacker was logged in |
| `wp_options` | `_wp_ip_id` = `3` | Backdoor's cached user ID |

**The live session originated from `69.118.41.219`** (expiry timestamp 1787572226 ≈ 2026-08-22),
with a Windows/Chrome user-agent. Treat this as a confirmed successful login, not just an attempt.

For reference, the two legitimate administrators are:

| ID | Login | Email | Registered | Last session IP |
|---|---|---|---|---|
| 1 | `webdeveloper373` | webdeveloper373@gmail.com | 2024-08-27 | 68.7.25.238 |
| 2 | `rehmansaleem` | rehmansaleem.piecyfer@gmail.com | 2024-11-06 | 72.26.34.213 |

### 2.4 Likely entry vector

`wp-file-manager` **8.0.4** is installed (currently deactivated, but present on disk). This plugin
has a long history of critical unauthenticated file-upload RCE vulnerabilities — the most famous
being CVE-2020-25213, which was mass-exploited. Combined with a nulled theme and eight nulled
commercial plugins from unknown redistributors, there are several plausible paths in. Nulled
packages are the single most common WordPress infection vector precisely because the backdoor
arrives *inside* the download.

The `_wp_ip` plugins are dated in the DB to around **2026-08-01**, which matches the `sys_maint`
registration timestamp. That is the infection date.

---

## 3. Licensing status

### 3.1 Theme — nulled

`wp-content/themes/tecnologia/` — **(VamTam) Tecnologia v4.2**, `License: Envato`.
Line 2 of `functions.php` is an injected licence bypass:

```php
add_filter( 'vamtam_purchase_code_import_override', function() { return true; } );
```

This is not VamTam code. It short-circuits the Envato purchase-code check so the theme's demo
importer and update channel behave as though a valid purchase code were present.

There is **no child theme** — `template` and `stylesheet` are both `tecnologia`. Any theme update
would wipe the customisations in `functions.php` (Google site verification meta, the Elementor
button-template filter, the one-page menu href fix).

### 3.2 Unlicensed commercial plugins

| Plugin | Version | Notes |
|---|---|---|
| Elementor Pro | 3.25.4 | ~9 months stale; current free Elementor is 3.25.10 |
| ElementsKit (Pro) | 3.7.5 | Installed **alongside** ElementsKit Lite 3.3.6 |
| WP Rocket | 3.20.1.2 | |
| WP Smush Pro | 3.16.11 | |
| Yoast SEO Premium | 23.6 | Older than the free Yoast 24.3 also installed |
| Imagify | 2.2.5 | |
| OptinMonster | 2.16.13 | |
| Object Cache Pro | 1.22.0 | |

Elementor Pro's own `license/api.php` is **unmodified** — the bypass is not in the file we checked,
which means activation is being faked elsewhere (typically a patched `admin.php`, a stored fake
licence option, or a redirected update host). Either way, **these plugins receive no security
updates**, which is how the site got into this state.

The path out of this is the one you already proposed: replace the paid functionality with code you
own. Section 5 of the plan document shows this is far more tractable than it looks.

---

## 4. Plugin stack — redundancy analysis

**32 plugins on disk, 25 active.** There is severe functional overlap:

| Function | Plugins installed | Of those, **active** | Should be |
|---|---|---|---|
| **SEO** | All in One SEO 4.7.8, Yoast 24.3, Yoast Premium 23.6, AIOSEO Broken Link Checker | AIOSEO + Broken Link Checker | **1** |
| **Page cache / optimisation** | WP Rocket, Debloat, WP Meteor, `boost-cache` dir | WP Meteor only | **1** |
| **Object cache** | Object Cache Pro, Redis Object Cache | both | **1 (or 0 locally)** |
| **Image optimisation** | Imagify, WP Smush Pro, Easy Image Optimizer *(malware)* | Smush + the malware | **1** |
| **Backup** | UpdraftPlus, All-in-One WP Migration | both | **1** |
| **Elementor addons** | ElementsKit, ElementsKit Lite | both | **1** |

> ### ⚠️ Correction — this section originally overstated two things
>
> The table above counts plugins **installed on disk**. Checking `active_plugins` shows that
> several of the apparent conflicts were not actually running:
>
> - **Yoast (free 24.3 and Premium 23.6) were installed but NOT active.** Only AIOSEO was
>   active. There was therefore **no live duplicate-metadata conflict** — I claimed there was,
>   and that was wrong. The duplication risk is latent (activating Yoast alongside AIOSEO would
>   cause it), not present.
> - **WP Rocket and Debloat were installed but NOT active.** Of the optimisation layers, only
>   **WP Meteor** was actually running, so they were not fighting each other either.
>
> The genuine finding in this area turned out to be different and is documented in
> `STATUS.md`: WP Meteor's blanket JS deferral was masking a 450px horizontal overflow and a
> JavaScript exception on every page.

🟠 **Unused-but-installed plugins are still a liability.** Yoast ×2, WP Rocket and Debloat sit on
disk receiving no updates. Dormant code is still reachable code — `wp-file-manager`, the likely
entry vector for this compromise, was dormant too.

🟠 `wp-file-manager` (dormant but on disk) should be deleted, not just deactivated.

Also present and safe to delete: `query-monitor` (dev tool), `duplicate-page`,
`simple-copy-protection` (harmless — authored in-house — but it only blocks right-click, which stops
nobody and hurts usability), `maintenance`, `vamtam-importers-e` (demo importer, one-time use).

---

## 5. What the site actually uses — Elementor widget census

Extracted from every `_elementor_data` record across all published and draft posts, pages and
templates (revisions excluded). **This is the definitive scope for the rebuild.**

### 5.1 Elementor FREE (core) — ~1,207 instances, **no work required**

`heading` 389 · `text-editor` 249 · `icon-box` 221 · `spacer` 80 · `icon` 79 · `image-box` 47 ·
`icon-list` 42 · `image` 36 · `button` 30 · `divider` 9 · `toggle` 7 · `image-carousel` 3 ·
`html` 3 · `social-icons` 3 · `video` 2 · `star-rating` 2 · `image-gallery` 2 · `google_maps` 2 ·
`counter` 1

**94% of all widget instances on the site are free Elementor core widgets.**

### 5.2 Elementor PRO — 16 types, **91 instances** — must be replaced

| Widget | Uses | Difficulty |
|---|---|---|
| `nav-menu` | 25 | Medium |
| `template` | 19 | Easy |
| `posts` | 12 | Medium |
| `form` | 9 | **Hard** (fields, validation, actions-after-submit, email) |
| `search-form` | 4 | Easy |
| `blockquote` | 4 | Easy |
| `call-to-action` | 3 | Medium |
| `archive-posts` | 3 | Medium |
| `theme-site-logo` | 2 | Easy |
| `theme-archive-title` | 2 | Easy |
| `testimonial-carousel` | 2 | Medium |
| `post-comments` | 2 | Easy |
| `theme-post-title` | 1 | Easy |
| `theme-post-content` | 1 | Easy |
| `post-info` | 1 | Medium |
| `gallery` | 1 | Medium |

### 5.3 ElementsKit — 6 types, **19 instances**

`elementskit-icon-box` 12 · `elementskit-client-logo` 2 · `elementskit-back-to-top` 2 ·
`elementskit-social-share` 1 · `elementskit-header-search` 1 · `ekit-nav-menu` 1

Two additional widgets were hand-built through ElementsKit's widget builder and live in
`wp-content/uploads/elementskit/custom_widgets/`: `ekit_wb_995717` ("Custom Button") and
`ekit_wb_995720` ("My Custom btn"). These are yours already — they just need re-homing.

### 5.4 Elementor Pro features beyond widgets

These are the genuinely load-bearing parts and drive most of the effort:

| Feature | Usage |
|---|---|
| **Theme Builder** | 10 templates: 1 header, 2 footers, 1 single-post, 2 archives, 1 search-results, 1 404, + display conditions |
| **Popup Builder** | 1 popup — "Consultation CTA" |
| **Per-element Custom CSS** | 58 elements |
| **Motion FX** (scale on scroll) | 23 elements |
| **Sticky** | 3 elements |
| **Global Widgets** | 3 saved: "Button - text", "Small heading with background", "Button with light background" |
| **Dynamic tags** | present in template documents |

Plus 26 `elementor_library` documents in total and 13 reusable `section` templates.

---

## 6. Content inventory

| Type | Published | Draft |
|---|---|---|
| Pages | 21 | 10 |
| Posts | 15 | — |
| Elementor library | 26 | 2 |
| ElementsKit content | 4 | — |
| Nav menu items | 213 | — |
| Attachments | 555 | — |
| Revisions | 809 | — |

809 revisions and 555 attachments are worth pruning during the performance phase.

---

## 7. Immediate risk statement

Until the four malicious plugins are deleted and the `sys_maint` account is destroyed:

- Every anonymous visitor to the site is executing attacker-supplied JavaScript.
- The attacker holds a live admin session and can restore access at will.
- The blockchain loader means the attacker can change the payload at any moment without touching
  your server again.

Because this local copy mirrors the live site, **the live site must be cleaned too** — cleaning only
`localhost` fixes nothing for real visitors. See `01-REBUILD-PLAN.md` §Phase 1 for the live-site
procedure and credential-rotation checklist.
