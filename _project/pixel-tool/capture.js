#!/usr/bin/env node
/**
 * Capture a full visual + markup snapshot of the site under a label.
 *
 *   node capture.js baseline
 *   node capture.js after-phase3
 *
 * Writes to ../snapshots/<label>/
 *   html/<slug>.html          normalised markup (see normalise() below)
 *   shots/<slug>@<vp>.png     full-page screenshot per viewport
 *   meta.json                 per-URL status, timing, console errors
 *
 * Then `node compare.js baseline after-phase3` proves whether anything moved.
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const { BASE, PATHS, QUICK, VIEWPORTS, slugify } = require('./urls');
const { normalise } = require('./normalise');

const label = process.argv[2];
if (!label) {
  console.error('usage: node capture.js <label>   e.g. node capture.js baseline');
  process.exit(1);
}
const arg = n => (process.argv.includes(n) ? process.argv[process.argv.indexOf(n) + 1] : null);
const only = arg('--only');
const force = process.argv.includes('--force');       // re-capture pages already done
const quick = process.argv.includes('--quick');       // 8-page subset instead of all 39
/**
 * Pages captured in parallel. Default 1 — deliberately.
 *
 * At concurrency 3 this machine takes 60–90s per page instead of the usual 8s,
 * and under that contention Chromium composites parts of an 11,000px full-page
 * screenshot before their images are decoded. The result was one or two
 * screenshots per run differing by several percent, with byte-identical markup
 * — a pure paint race, and it moved to a different page each time, which is
 * what made it so hard to pin down.
 *
 * Serial capture is barely slower overall (the parallel speedup was mostly
 * eaten by contention) and it is the difference between a harness that reports
 * phantom regressions and one that can be trusted. Two consecutive serial runs
 * of the untouched site compare byte-identical.
 */
const concurrency = Number(arg('--concurrency')) || 1;

const outDir = path.join(__dirname, '..', 'snapshots', label);
const htmlDir = path.join(outDir, 'html');
const shotDir = path.join(outDir, 'shots');
[outDir, htmlDir, shotDir].forEach(d => fs.mkdirSync(d, { recursive: true }));

// Markup normalisation lives in normalise.js so renormalise.js can apply the
// identical rules to already-captured baselines.

/**
 * Freeze anything that would make two screenshots of the same page differ:
 * CSS animations/transitions, carousels, lazy-load fades, caret blink.
 */
const FREEZE_CSS = `
  /* PIXEL-TOOL-FREEZE
   *
   * This block is injected by the harness and lands in page.content(), so it
   * would otherwise show up as a markup change every time these rules are
   * edited — the harness diffing itself. normalise() strips any <style>
   * carrying this marker.
   */
  *, *::before, *::after {
    animation-duration: 0s !important;
    animation-delay: 0s !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0s !important;
    transition-delay: 0s !important;
    caret-color: transparent !important;
    scroll-behavior: auto !important;
  }
  .elementor-invisible { visibility: visible !important; opacity: 1 !important; }

  /*
   * Freeze JS-driven carousels at slide zero.
   *
   * Swiper moves its track with an inline transform on a timer, so an
   * autoplaying carousel — the ElementsKit client-logo marquee on the home
   * page, for instance — sits at a different offset in every capture.
   * Playwright's animations:'disabled' only covers CSS animations, not this.
   *
   * Pinning the track to 0 gives a deterministic state without blinding us:
   * a rebuilt carousel that renders the wrong markup, sizes or styling still
   * differs at slide zero. The only thing we stop detecting is animation
   * timing, which is not something we assert on.
   */
  .swiper-wrapper,
  .swiper-container .swiper-wrapper,
  .elementskit-clients-slider .swiper-wrapper {
    transform: translate3d(0px, 0px, 0px) !important;
    transition-duration: 0s !important;
    animation: none !important;
  }

  /*
   * Neutralise the Google Maps embed.
   *
   * Maps requests a fresh set of tile URLs on every load — different tokens,
   * different tile coordinates — so the response cache never hits and the map
   * renders slightly differently every time. It appears in a global template,
   * so this polluted the diff on most pages.
   *
   * Hiding the iframe's *content* while keeping its box means the geometry is
   * still asserted (a widget that changes size is still caught) while the
   * non-deterministic pixels are not. The map is third-party content we are
   * not migrating, so nothing of ours goes unchecked.
   */
  /*
   * Neutralise scroll-driven motion effects.
   *
   * Elementor's motion-fx layers recompute --translateY and a scale transform
   * from the scroll position, so a decorative parallax layer came out at a
   * different scale in each capture — 0.04% of the page, but enough to fail a
   * comparison. There is no "correct" static value to wait for: the effect is
   * a function of scroll, and a full-page screenshot has no single scroll
   * position.
   *
   * What we give up is small and was never really checkable in a static
   * screenshot. The settings behind the effect are still compared, because the
   * inline style carrying them is part of the markup diff.
   */
  .elementor-motion-effects-layer {
    transform: none !important;
    --translateY: 0px !important;
  }

  .elementor-widget-google_maps,
  .elementor-custom-embed { background: #e5e3df !important; }
  iframe[src*="google.com/maps"],
  iframe[src*="maps.google"],
  .elementor-widget-google_maps iframe { visibility: hidden !important; }
`;

// ---------------------------------------------------------------------------
// Off-site request cache
//
// Shared across every capture run (it lives outside the per-label directory) so
// that a comparison between two runs weeks apart still replays the same bytes.
// Delete _project/snapshots/_extcache/ to refresh it.
// ---------------------------------------------------------------------------
const cacheDir = path.join(__dirname, '..', 'snapshots', '_extcache');
fs.mkdirSync(cacheDir, { recursive: true });

const cacheKey = url => crypto.createHash('sha1').update(url).digest('hex');

/**
 * Requests whose URL changes on every page load, so they can neither be cached
 * nor meaningfully compared. Google Maps mints new tile URLs and tokens each
 * time; leaving them in the report means every run shows "newly broken assets"
 * that are neither new nor our doing. The map itself is neutralised visually by
 * FREEZE_CSS.
 */
const IGNORE_ASSETS = [
  /maps\.google(apis)?\.com/i,
  /maps\.gstatic\.com/i,
  /\/maps\/vt\?/i,
  /StaticMapService/i,
];
const ignoreAsset = url => IGNORE_ASSETS.some(re => re.test(url));

/** After this many consecutive failures a URL is treated as permanently dead. */
const MAX_FETCH_ATTEMPTS = 3;

async function cacheRoute(route) {
  const url = route.request().url();

  // Inherently non-deterministic third parties never enter the cache: their
  // URLs are unique per load, so caching them only fills the disk.
  if (ignoreAsset(url)) return route.abort();

  const key = cacheKey(url);
  const bodyFile = path.join(cacheDir, key + '.body');
  const metaFile = path.join(cacheDir, key + '.json');

  let meta = null;
  if (fs.existsSync(metaFile)) {
    try { meta = JSON.parse(fs.readFileSync(metaFile, 'utf8')); } catch (e) { meta = null; }
  }

  if (meta && !meta.dead && fs.existsSync(bodyFile) && !meta.attempts) {
    return route.fulfill({ status: meta.status, headers: meta.headers, body: fs.readFileSync(bodyFile) });
  }
  if (meta && meta.dead) return route.abort();

  try {
    const resp = await route.fetch({ timeout: 20000 });
    const body = await resp.body();
    fs.writeFileSync(bodyFile, body);
    fs.writeFileSync(metaFile, JSON.stringify({ url, status: resp.status(), headers: resp.headers() }));
    return route.fulfill({ status: resp.status(), headers: resp.headers(), body });
  } catch (e) {
    // Do NOT treat one failure as permanent. A transient network hiccup on a
    // Google Fonts woff2 once got cached as "aborted", which would have
    // silently removed Montserrat from every capture from then on — the
    // baseline would have looked stable while being wrong. Retry across runs,
    // and only give up after MAX_FETCH_ATTEMPTS.
    const attempts = ( meta?.attempts || 0 ) + 1;
    fs.writeFileSync(
      metaFile,
      JSON.stringify({ url, attempts, dead: attempts >= MAX_FETCH_ATTEMPTS, error: String(e).slice(0, 200) })
    );
    return route.abort();
  }
}

/**
 * Wait until the page has genuinely stopped changing, then return.
 *
 * `networkidle` is not enough on this site. Lazy-loading swaps `data-src` into
 * `src` as an IntersectionObserver fires, which happens progressively as you
 * scroll — so a page can be network-idle while half its images have not even
 * been requested yet. A self-test of two runs against an *untouched* site
 * produced five "visual changes" of up to 4.5%, every one of them an image that
 * had loaded in one run and not the other.
 *
 * So: step-scroll the whole page to trip every observer, then poll a signature
 * of (image count, loaded-image count, page height) until it stops moving.
 * Screenshotting a page that is still settling is the single largest source of
 * false positives in a visual-regression harness, and a harness that cries wolf
 * is worse than no harness at all.
 */
async function settle(page) {
  await page.evaluate(async () => {
    document.querySelectorAll('img[loading="lazy"]').forEach(img => { img.loading = 'eager'; });
    // Common lazy-load attribute conventions, in case the observer never fires.
    document.querySelectorAll('img[data-src]').forEach(img => {
      if (!img.src || img.src !== img.dataset.src) img.src = img.dataset.src;
    });
    document.querySelectorAll('img[data-srcset]').forEach(img => { img.srcset = img.dataset.srcset; });

    const step = Math.max(200, window.innerHeight * 0.8);
    for (let y = 0; y < document.body.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise(r => setTimeout(r, 120));
    }
    window.scrollTo(0, document.body.scrollHeight);
    await new Promise(r => setTimeout(r, 300));
    window.scrollTo(0, 0);
  });

  await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});

  // Carousels are tamed inside the polling loop below rather than here, so the
  // fix reapplies as Swiper clones new slides. See the comment there.

  // Warm the browser cache for every image the page references, including ones
  // the layout has not revealed yet.
  //
  // The scroll pass triggers IntersectionObserver-based lazy loading for
  // anything that scrolls into view, but not for images sitting inside a hidden
  // tab panel or an off-screen carousel slide. Those loaded in one run and not
  // the next — the laptop mockup in the Tabs section on the home page was the
  // last false positive standing.
  //
  // Fetching each URL through an off-DOM Image() puts it in the HTTP cache
  // without touching the DOM, so when the real element does paint it paints
  // instantly and identically. CSS background images are collected too; they
  // are invisible to document.images entirely.
  await page.evaluate(async () => {
    const urls = new Set();

    document.querySelectorAll('img').forEach(img => {
      if (img.currentSrc) urls.add(img.currentSrc);
      else if (img.src) urls.add(img.src);
      if (img.dataset.src) urls.add(img.dataset.src);
    });

    document.querySelectorAll('*').forEach(el => {
      const bg = getComputedStyle(el).backgroundImage;
      if (!bg || bg === 'none') return;
      for (const m of bg.matchAll(/url\(["']?([^"')]+)["']?\)/g)) {
        if (m[1] && !m[1].startsWith('data:')) urls.add(m[1]);
      }
    });

    await Promise.all(
      [...urls].map(
        u =>
          new Promise(resolve => {
            const im = new Image();
            im.onload = im.onerror = () => resolve();
            im.src = u;
            setTimeout(resolve, 10000);
          })
      )
    );
  }).catch(() => {});

  // Now that every image is in cache, make layout-dependent handlers run again.
  //
  // Elementor's posts handler compares each thumbnail's aspect ratio to its
  // container and toggles `elementor-fit-height` accordingly. It runs on image
  // load, so if it fired before an image had dimensions the class never
  // appeared — leaving the blog listing bistable: same URL, two possible
  // renderings, each internally stable. Neither the image counters nor
  // screenshot-until-stable could see it, because both states were settled;
  // only one was finished.
  //
  // A resize event is how Elementor itself re-triggers these handlers, so this
  // asks for the same recalculation the browser would do, with everything
  // loaded.
  await page.evaluate(() => {
    window.dispatchEvent(new Event('resize'));
    if (window.jQuery) {
      window.jQuery(window).trigger('resize');
    }
  }).catch(() => {});
  await page.waitForTimeout(500);

  // Poll until three consecutive identical signatures, or give up.
  //
  // The carousel taming runs on *every* iteration, not once. Swiper in loop
  // mode clones slides on the fly, so a single pass promotes the data-src
  // attributes that exist at that instant and then Swiper injects a fresh batch
  // of unloaded clones behind it. That is why the client-logo strip kept
  // showing a different number of logos per capture even after autoplay was
  // stopped: we were fixing a set that Swiper then replaced.
  let last = null;
  let stable = 0;
  for (let i = 0; i < 25; i++) {
    const sig = await page.evaluate(() => {
      document.querySelectorAll('.swiper, .swiper-container').forEach(el => {
        const sw = el.swiper;
        if (!sw) return;
        try {
          sw.autoplay?.stop();
          // update() recomputes slide widths from the current layout. Swiper
          // sizes its slides once at init, which on this site happens before
          // webfonts settle — so the blog listing's featured image came out a
          // few pixels wider in one run than the next, with byte-identical
          // markup. (The markup diff could never have caught it: HTML is
          // captured at the desktop viewport only, and this showed on tablet.)
          sw.update();
          sw.slideTo(0, 0, false);
        } catch (e) { /* CSS pin still holds the track */ }
      });
      document.querySelectorAll('img[data-src]').forEach(img => {
        img.src = img.dataset.src;
        img.removeAttribute('data-src');
        img.classList.remove('swiper-lazy');
      });
      document.querySelectorAll('img[data-srcset]').forEach(img => {
        img.srcset = img.dataset.srcset;
        img.removeAttribute('data-srcset');
      });
      document.querySelectorAll('.swiper-lazy-preloader').forEach(el => el.remove());

      // Re-run the layout-dependent handlers on every pass, not once before the
      // loop.
      //
      // `sw.update()` above recomputes slide widths, which changes the very
      // container each thumbnail's aspect ratio is measured against — so a
      // fit-height decision taken before it is stale, and nothing was asking
      // for it to be taken again. That is why the blog listing stayed bistable
      // after the first fix: the resize fired, and then the swiper update moved
      // the goalposts.
      //
      // Dispatching inside the loop means the signature cannot settle until the
      // class toggling has settled too, because outerHTML length is part of it.
      window.dispatchEvent(new Event('resize'));
      if (window.jQuery) {
        window.jQuery(window).trigger('resize');
      }

      const imgs = [...document.images];
      return [
        imgs.length,
        imgs.filter(im => im.complete && im.naturalWidth > 0).length,
        document.body.scrollHeight,
        // Total markup length catches DOM changes the counters miss — most
        // importantly a class or inline style toggled by JS after load. The
        // blog listing turned out to be bistable: a script adds a class that
        // rounds the card corners and resizes the thumbnail, and a capture
        // taken before it landed was internally stable, so neither the counters
        // nor screenshot-until-stable noticed. Both states were "settled";
        // only one was finished.
        document.documentElement.outerHTML.length,
      ].join(':');
    }).catch(() => null);

    if (sig !== null && sig === last) {
      if (++stable >= 3) break;
    } else {
      stable = 0;
    }
    last = sig;
    await page.waitForTimeout(400);
  }

  // Webfonts: screenshotting before Inter Tight swaps in captures the fallback
  // face and reports a whole-page text diff on the next run.
  await page.evaluate(() => document.fonts.ready).catch(() => {});

  // Force every image to be fully decoded before we screenshot.
  //
  // `complete === true` only means the bytes arrived; the bitmap may not be
  // decoded, and on an 11,000px full-page capture Chromium can composite a
  // region before its images are paintable. That was the last false positive
  // standing: the client-logo strip had identical markup in both runs — same
  // DOM, same inline styles, same src attributes — yet showed three logos in
  // one screenshot and six in the other. Nothing was loading differently; the
  // pixels simply were not ready.
  //
  // decode() resolves only once the frame is ready to paint, which is exactly
  // the guarantee a screenshot needs.
  await page.evaluate(async () => {
    await Promise.all(
      [...document.images].map(img =>
        (img.decode ? img.decode() : Promise.resolve()).catch(() => {})
      )
    );
  }).catch(() => {});

  await page.waitForTimeout(400);
}

/**
 * Screenshot until two consecutive captures are byte-identical.
 *
 * The definitive answer to paint races, and it needs no guesswork about which
 * element is misbehaving. Everything else here — waiting on images, fonts,
 * decode(), carousels — reduces the chance that a screenshot is taken mid-paint;
 * this *detects* it. If two shots taken 500ms apart agree, the page had stopped
 * changing.
 *
 * Necessary because even serially, the home page (11,334px tall) still
 * occasionally disagreed with itself: two captures under an identical
 * configuration differed on one screenshot, which proves the difference was
 * never caused by the change under test.
 *
 * Returns the buffer plus whether stability was actually reached, so a page
 * that never settles is reported rather than silently trusted.
 */
async function stableScreenshot(page, opts, attempts = 4) {
  let prev = await page.screenshot(opts);
  for (let i = 1; i < attempts; i++) {
    await page.waitForTimeout(500);
    const next = await page.screenshot(opts);
    if (next.equals(prev)) return { buffer: next, stable: true };
    prev = next;
  }
  return { buffer: prev, stable: false };
}

// Metadata is written after every page, not at the end. A 40-minute run that
// dies at page 30 must not lose the 29 pages it already proved.
const metaPath = path.join(outDir, 'meta.json');
let meta = { label, base: BASE, capturedAt: new Date().toISOString(), pages: [] };
if (fs.existsSync(metaPath)) {
  try { meta = JSON.parse(fs.readFileSync(metaPath, 'utf8')); } catch (e) { /* start fresh */ }
}
// A page counts as captured when every viewport produced a real HTTP response.
// Not "status === 200": the 404-template URL is *expected* to return 404, and
// treating that as a failure would re-capture it on every run and permanently
// report 38/39.
const captured = p => Object.values(p.viewports || {}).every(v => typeof v.status === 'number' && v.status > 0);
const doneSlugs = new Set(meta.pages.filter(captured).map(p => p.slug));

function saveMeta() {
  fs.writeFileSync(metaPath, JSON.stringify(meta, null, 2));
}

async function capturePage(browser, p, i, total) {
  const slug = slugify(p);
  const url = BASE + p;
  const record = { path: p, slug, url, viewports: {}, consoleErrors: [], failedRequests: [] };
  const t0 = Date.now();

  for (const vp of VIEWPORTS) {
      const ctx = await browser.newContext({
        viewport: { width: vp.width, height: vp.height },
        deviceScaleFactor: 1,
        reducedMotion: 'reduce',
      });

      // Pin touch capability instead of inheriting the host's.
      //
      // Playwright's default is not "no touch" — it is "whatever the machine
      // reports". The machine ref2-a and every snapshot up to p4i-posts were
      // captured on reported a non-zero navigator.maxTouchPoints, so Elementor
      //
      //   isTouchDevice: "ontouchstart" in window || navigator.maxTouchPoints > 0
      //
      // was true and every <body> carried `e--ua-isTouchDevice`. On a machine
      // without a touchscreen that class disappears and all 43 pages differ,
      // which reads as a site regression and is not one.
      //
      // Do NOT reach for Playwright's `hasTouch: true` here. That satisfies
      // Elementor but also defines `ontouchstart`, and Swiper keys off that: it
      // switches from pointer events to touch events, dropping the
      // `swiper-pointer-events` class and the `cursor: grab` inline style from
      // every carousel. The reference machine had maxTouchPoints > 0 *and* no
      // touch events, so only the property is overridden.
      //
      // This reproduces the reference environment rather than widening a
      // tolerance. Changing it is a silent markup change on every page.
      await ctx.addInitScript(() => {
        Object.defineProperty(Navigator.prototype, 'maxTouchPoints', {
          get: () => 10,
          configurable: true,
        });
      });
      // Four documents store root-relative /wp-content/... URLs (3 team photos
      // on Our Team, 3 PDF links). Those resolve correctly on the production
      // domain-root install but 404 on this /piecyfer/ subdirectory copy.
      // Rewrite them so the baseline shows what production shows, instead of
      // baking a local-only 404 into the reference we compare everything to.
      await ctx.route('**://localhost/wp-content/**', route => {
        const fixed = route.request().url().replace('://localhost/wp-content/', '://localhost/piecyfer/wp-content/');
        return route.continue({ url: fixed });
      });

      // Serve every off-site request from a disk cache.
      //
      // The site loads its typefaces from fonts.googleapis.com. During the
      // first baseline that request intermittently failed with
      // ERR_SOCKET_NOT_CONNECTED, the page fell back to a system font, and the
      // comparison reported a whole-page text diff that had nothing to do with
      // any change we made. A self-test run against an untouched site produced
      // four such false positives out of 24 screenshots.
      //
      // Caching fixes it without lying about what production looks like: the
      // real font is fetched once and replayed byte-identically forever after,
      // so captures are both deterministic and representative. Same for the
      // Google Maps embed on /contact-us/.
      await ctx.route(url => !url.hostname.includes('localhost'), cacheRoute);

      const page = await ctx.newPage();
      page.on('console', m => {
        if (m.type() === 'error') record.consoleErrors.push(m.text().slice(0, 200));
      });
      page.on('pageerror', e => record.consoleErrors.push('PAGEERROR: ' + String(e).slice(0, 200)));
      // Track broken assets. A 404 on a stylesheet or font is invisible in a
      // screenshot until it changes the layout, so record them explicitly.
      page.on('response', r => {
        if (r.status() >= 400 && !ignoreAsset(r.url())) {
          const entry = `${r.status()} ${r.url().replace(BASE, '')}`;
          if (!record.failedRequests.includes(entry)) record.failedRequests.push(entry);
        }
      });
      page.on('requestfailed', r => {
        if (ignoreAsset(r.url())) return;
        const entry = `FAILED ${r.url().replace(BASE, '')} (${r.failure()?.errorText || '?'})`;
        if (!record.failedRequests.includes(entry)) record.failedRequests.push(entry);
      });

      try {
        const resp = await page.goto(url, { waitUntil: 'load', timeout: 120000 });
        record.viewports[vp.name] = { status: resp ? resp.status() : 0 };

        await page.addStyleTag({ content: FREEZE_CSS });

        await settle(page);

        const shot = await stableScreenshot(page, { fullPage: true, animations: 'disabled' });
        fs.writeFileSync(path.join(shotDir, `${slug}@${vp.name}.png`), shot.buffer);
        if (!shot.stable) {
          record.unstableShots = (record.unstableShots || []).concat(vp.name);
        }

        // Markup is viewport-independent for our purposes; capture it once.
        if (vp.name === 'desktop') {
          const html = await page.content();
          fs.writeFileSync(path.join(htmlDir, `${slug}.html`), normalise(html));
          record.htmlBytes = html.length;
        }
      } catch (err) {
        record.viewports[vp.name] = { status: 'ERROR', error: String(err).slice(0, 200) };
        console.log(`    ! ${slug}@${vp.name}: ${String(err).slice(0, 90)}`);
      } finally {
        await ctx.close();
      }
    }

  record.ms = Date.now() - t0;
  // Replace any earlier partial record for this slug rather than duplicating it.
  meta.pages = meta.pages.filter(x => x.slug !== slug).concat([record]);
  saveMeta();

  const bad = record.consoleErrors.length;
  const broken = record.failedRequests.length;
  console.log(
    `  [${String(i + 1).padStart(2)}/${total}] ${slug}  ` +
    `${record.viewports.desktop?.status}  ${record.ms}ms` +
    (bad ? `  ${bad} console errors` : '') +
    (broken ? `  ${broken} broken assets` : '')
  );
  return record;
}

/**
 * Clear Elementor's caches before capturing. NOT an optimisation — a
 * correctness requirement.
 *
 * The `e_element_cache` experiment is active on this site, and it caches the
 * *rendered HTML* of a whole document in `_elementor_element_cache` postmeta
 * for 24 hours, together with the list of style and script handles that render
 * enqueued. While that cache is warm, Elementor never calls the widgets at all.
 *
 * So editing a widget and re-capturing shows the OLD markup, and a capture can
 * "prove" a widget identical when the widget never ran. That is the harness
 * lying in the most dangerous direction — a false pass. It cost a full
 * debugging round: three separate, genuinely-correct fixes appeared to have no
 * effect whatsoever.
 *
 * Turning the experiment off instead is not an option: it changes which
 * stylesheets a page enqueues, so the site would no longer match the baseline.
 *
 * A failure here is fatal on purpose. Skipping the clear silently is exactly
 * the failure mode this exists to prevent.
 */
function clearElementorCache() {
  const script = path.join(__dirname, '..', 'scripts', 'clear-elementor-cache.php');
  const php = process.env.PHP_BIN || 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
  const out = execFileSync(php, [script], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  const summary = out.split('\n').filter(l => /^_elementor|^generated/.test(l)).join(' | ');
  console.log(`elementor cache cleared: ${summary}`);
}

(async () => {
  if (!process.argv.includes('--no-cache-clear')) {
    clearElementorCache();
  } else {
    console.log('WARNING: --no-cache-clear — cached widget HTML may be captured instead of live output');
  }

  // --quick: the 8-page representative subset, for checking after each small
  // change. The full 39-page set is the gate at the end of a phase.
  const all = quick ? QUICK : PATHS;
  let targets = only ? all.filter(p => p.includes(only)) : all;

  // A --only that matches nothing must be an error, not an empty run.
  //
  // `--only /blogs` silently matched zero urls: Git Bash rewrites a leading
  // slash into a Windows path before node ever sees it. The run "succeeded",
  // wrote an empty snapshot, and comparing two of those reported IDENTICAL —
  // a green tick for a test that never executed.
  if (only && !targets.length) {
    console.error(`--only "${only}" matched none of the ${all.length} urls. Nothing captured.`);
    console.error('(Under Git Bash, drop the leading slash: --only blogs, not --only /blogs.)');
    process.exit(2);
  }

  if (!force) {
    const before = targets.length;
    targets = targets.filter(p => !doneSlugs.has(slugify(p)));
    const skipped = before - targets.length;
    if (skipped) console.log(`resuming: ${skipped} page(s) already captured, ${targets.length} to go`);
  }
  if (!targets.length) { console.log('nothing to do — all pages already captured (use --force to redo)'); return; }

  console.log(`capture "${label}" — ${targets.length} urls x ${VIEWPORTS.length} viewports, concurrency ${concurrency}`);

  const browser = await chromium.launch();

  // A small worker pool. Local Apache is the bottleneck, so keep this low —
  // pushing it higher makes page loads slower, not the run faster.
  let next = 0;
  const total = targets.length;
  const workers = Array.from({ length: Math.min(concurrency, total) }, async () => {
    while (true) {
      const i = next++;
      if (i >= total) return;
      try {
        await capturePage(browser, targets[i], i, total);
      } catch (err) {
        console.log(`  ! ${slugify(targets[i])}: ${String(err).slice(0, 120)}`);
      }
    }
  });
  await Promise.all(workers);

  await browser.close();
  meta.completedAt = new Date().toISOString();
  saveMeta();

  const ok = meta.pages.filter(captured).length;
  console.log(`\ndone -> ${outDir}`);
  console.log(`${ok}/${meta.pages.length} pages fully captured at all ${VIEWPORTS.length} viewports`);
  const failed = meta.pages.filter(p => !captured(p));
  failed.forEach(p => console.log(`  incomplete: ${p.slug} ${JSON.stringify(p.viewports)}`));
})();
