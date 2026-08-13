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
const { chromium } = require('playwright');
const { BASE, PATHS, VIEWPORTS, slugify } = require('./urls');

const label = process.argv[2];
if (!label) {
  console.error('usage: node capture.js <label>   e.g. node capture.js baseline');
  process.exit(1);
}
const arg = n => (process.argv.includes(n) ? process.argv[process.argv.indexOf(n) + 1] : null);
const only = arg('--only');
const force = process.argv.includes('--force');       // re-capture pages already done
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
`;

// Metadata is written after every page, not at the end. A 40-minute run that
// dies at page 30 must not lose the 29 pages it already proved.
const metaPath = path.join(outDir, 'meta.json');
let meta = { label, base: BASE, capturedAt: new Date().toISOString(), pages: [] };
if (fs.existsSync(metaPath)) {
  try { meta = JSON.parse(fs.readFileSync(metaPath, 'utf8')); } catch (e) { /* start fresh */ }
}
const doneSlugs = new Set(
  meta.pages.filter(p => Object.values(p.viewports || {}).every(v => v.status === 200)).map(p => p.slug)
);

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
      const page = await ctx.newPage();
      page.on('console', m => {
        if (m.type() === 'error') record.consoleErrors.push(m.text().slice(0, 200));
      });
      page.on('pageerror', e => record.consoleErrors.push('PAGEERROR: ' + String(e).slice(0, 200)));
      // Track broken assets. A 404 on a stylesheet or font is invisible in a
      // screenshot until it changes the layout, so record them explicitly.
      page.on('response', r => {
        if (r.status() >= 400) {
          const entry = `${r.status()} ${r.url().replace(BASE, '')}`;
          if (!record.failedRequests.includes(entry)) record.failedRequests.push(entry);
        }
      });
      page.on('requestfailed', r => {
        const entry = `FAILED ${r.url().replace(BASE, '')} (${r.failure()?.errorText || '?'})`;
        if (!record.failedRequests.includes(entry)) record.failedRequests.push(entry);
      });

      try {
        const resp = await page.goto(url, { waitUntil: 'load', timeout: 120000 });
        record.viewports[vp.name] = { status: resp ? resp.status() : 0 };

        await page.addStyleTag({ content: FREEZE_CSS });

        // Force every lazy image to load, then let layout settle, so the
        // full-page screenshot is not half-empty placeholders.
        await page.evaluate(async () => {
          document.querySelectorAll('img[loading="lazy"]').forEach(img => (img.loading = 'eager'));
          window.scrollTo(0, document.body.scrollHeight);
          await new Promise(r => setTimeout(r, 600));
          window.scrollTo(0, 0);
          await new Promise(r => setTimeout(r, 300));
        });
        await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});

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
  let targets = only ? PATHS.filter(p => p.includes(only)) : PATHS;

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

  const ok = meta.pages.filter(p => Object.values(p.viewports).every(v => v.status === 200)).length;
  console.log(`\ndone -> ${outDir}`);
  console.log(`${ok}/${meta.pages.length} pages fully captured at all ${VIEWPORTS.length} viewports`);
})();
