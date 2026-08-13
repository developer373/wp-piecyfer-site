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
const { chromium } = require('playwright');
const { BASE, PATHS, QUICK, VIEWPORTS, slugify } = require('./urls');

const label = process.argv[2];
if (!label) {
  console.error('usage: node capture.js <label>   e.g. node capture.js baseline');
  process.exit(1);
}
const arg = n => (process.argv.includes(n) ? process.argv[process.argv.indexOf(n) + 1] : null);
const only = arg('--only');
const force = process.argv.includes('--force');       // re-capture pages already done
const quick = process.argv.includes('--quick');       // 8-page subset instead of all 39
const concurrency = Number(arg('--concurrency')) || 3;

const outDir = path.join(__dirname, '..', 'snapshots', label);
const htmlDir = path.join(outDir, 'html');
const shotDir = path.join(outDir, 'shots');
[outDir, htmlDir, shotDir].forEach(d => fs.mkdirSync(d, { recursive: true }));

/**
 * Strip everything that legitimately changes between two runs of an unchanged
 * site. Without this every diff is 100% noise: WordPress regenerates nonces per
 * request, Elementor appends ?ver= cache-busters, and several plugins emit
 * timestamps and random element ids.
 *
 * Anything NOT stripped here is content we are asserting must stay identical.
 */
function normalise(html) {
  return html
    // nonces and one-time tokens
    .replace(/(nonce["':=\s]+)[a-f0-9]{8,12}/gi, '$1__NONCE__')
    .replace(/(_wpnonce=)[a-f0-9]{8,12}/gi, '$1__NONCE__')
    .replace(/("(?:nonce|_?ajax_nonce|rest_nonce)"\s*:\s*")[^"]+/gi, '$1__NONCE__')
    // asset cache-busters
    .replace(/([?&]ver=)[^"'&\s]+/gi, '$1__VER__')
    // elementor / plugin generated ids that are random per render
    .replace(/(elementor-element-)[a-f0-9]{6,9}\b/gi, '$1__EID__')
    .replace(/(id="[a-z-]*?)[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/gi, '$1__UUID__')
    // timestamps and dates-of-render
    .replace(/\b\d{10,13}\b/g, '__TS__')
    .replace(/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[^"'<\s]*/g, '__ISODATE__')
    // whitespace noise
    .replace(/\r\n/g, '\n')
    .replace(/[ \t]+$/gm, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

/**
 * Freeze anything that would make two screenshots of the same page differ:
 * CSS animations/transitions, carousels, lazy-load fades, caret blink.
 */
const FREEZE_CSS = `
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

async function cacheRoute(route) {
  const url = route.request().url();
  const key = cacheKey(url);
  const bodyFile = path.join(cacheDir, key + '.body');
  const metaFile = path.join(cacheDir, key + '.json');

  if (fs.existsSync(bodyFile) && fs.existsSync(metaFile)) {
    const m = JSON.parse(fs.readFileSync(metaFile, 'utf8'));
    if (m.aborted) return route.abort();
    return route.fulfill({ status: m.status, headers: m.headers, body: fs.readFileSync(bodyFile) });
  }

  try {
    const resp = await route.fetch({ timeout: 20000 });
    const body = await resp.body();
    fs.writeFileSync(bodyFile, body);
    fs.writeFileSync(metaFile, JSON.stringify({ url, status: resp.status(), headers: resp.headers() }));
    return route.fulfill({ status: resp.status(), headers: resp.headers(), body });
  } catch (e) {
    // Record the failure too. A resource that is genuinely unreachable should
    // fail the same way on every run rather than flapping.
    fs.writeFileSync(bodyFile, '');
    fs.writeFileSync(metaFile, JSON.stringify({ url, aborted: true, error: String(e).slice(0, 200) }));
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

  // Stop carousels, then force their lazy slides to load — in that order.
  //
  // The site's carousels use Swiper with swiper-lazy, which only loads a
  // slide's image as that slide approaches the active position. Autoplay
  // therefore decides *how many* logos have loaded by the time we screenshot,
  // and the answer differed on every run: the client-logo strip on the home
  // page showed three logos in one capture and six in the next.
  //
  // Pinning the track with CSS is not enough, because that hides the movement
  // without stopping Swiper's internal index from advancing and pulling in more
  // images. So: stop autoplay, reset to slide zero, and only then promote every
  // remaining data-src to a real src so all slides are populated regardless of
  // position.
  await page.evaluate(() => {
    document.querySelectorAll('.swiper, .swiper-container').forEach(el => {
      const sw = el.swiper;
      if (!sw) return;
      try {
        sw.autoplay?.stop();
        sw.slideTo(0, 0, false);
      } catch (e) { /* older Swiper builds differ; the CSS pin still applies */ }
    });

    document.querySelectorAll('img[data-src]').forEach(img => {
      img.src = img.dataset.src;
      img.classList.remove('swiper-lazy');
    });
    document.querySelectorAll('img[data-srcset]').forEach(img => {
      img.srcset = img.dataset.srcset;
    });
    document.querySelectorAll('.swiper-lazy-preloader').forEach(el => el.remove());
  }).catch(() => {});

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

  // Poll until three consecutive identical signatures, or give up.
  let last = null;
  let stable = 0;
  for (let i = 0; i < 25; i++) {
    const sig = await page.evaluate(() => {
      const imgs = [...document.images];
      return [
        imgs.length,
        imgs.filter(im => im.complete && im.naturalWidth > 0).length,
        document.body.scrollHeight,
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

  // Webfonts last: screenshotting before Inter Tight swaps in captures the
  // fallback face and reports a whole-page text diff on the next run.
  await page.evaluate(() => document.fonts.ready).catch(() => {});
  await page.waitForTimeout(300);
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

        await page.screenshot({
          path: path.join(shotDir, `${slug}@${vp.name}.png`),
          fullPage: true,
          animations: 'disabled',
        });

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

(async () => {
  // --quick: the 8-page representative subset, for checking after each small
  // change. The full 39-page set is the gate at the end of a phase.
  const all = quick ? QUICK : PATHS;
  let targets = only ? all.filter(p => p.includes(only)) : all;

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
