#!/usr/bin/env node
/**
 * Behaviour test runner.
 *
 *   node run.js pro-active
 *   node run.js after-phase5 --compare pro-active
 *   node run.js scratch --only nav
 *
 * Writes ./results/<label>.json and prints a summary.
 * Exit code 0 = every test passed (and, with --compare, nothing regressed).
 *
 * WHY THIS EXISTS ALONGSIDE ../pixel-tool
 * ---------------------------------------
 * The pixel harness proves the site still LOOKS right. It is completely blind to
 * whether anything still WORKS, and in fact deliberately freezes the very things
 * this file exercises: it pins every Swiper track to slide zero and kills every
 * animation, because motion is noise to a screenshot. A collapsed mobile menu, a
 * dead carousel arrow, a search box that submits nowhere and a form that stopped
 * validating all produce byte-identical screenshots.
 *
 * Our replacement plugin ships no JavaScript at all. The moment Elementor Pro is
 * deactivated every Pro widget will render correctly and do nothing. This file
 * records what "doing something" looks like while Pro still works, so the
 * difference is measurable rather than a matter of opinion.
 *
 * DESIGN NOTES (mostly lessons paid for by the pixel harness)
 * -----------------------------------------------------------
 *  - Serial. Concurrency is 1 and there is no flag to raise it. The pixel tool
 *    proved that parallel Chromium contexts on this machine starve each other
 *    badly enough to change observable behaviour; a behaviour test that times
 *    out under load is a false regression, which is the failure mode that makes
 *    a harness worthless.
 *
 *  - Wait on conditions, never on clocks. Every wait is a page.waitForFunction
 *    over a DOM predicate, and the predicate it waited for is written into the
 *    result file. The two places a fixed dwell survives are marked in tests.js
 *    and both are cases where the assertion is "nothing happened", for which
 *    there is by definition no condition to wait for.
 *
 *  - A test that cannot find its target FAILS. This is the fourth time this
 *    project has had to say it: the pixel tool shipped a green IDENTICAL for a
 *    --only that matched no urls, a green pass for widgets served from a stale
 *    element cache, and a cached "dead" font that would have silently changed
 *    every capture. So here: t.exists() and t.visibleOne() throw if the target
 *    is missing or ambiguous; a test that records zero checks is a failure; a
 *    run with zero tests is a failure; an --only that matches nothing is an
 *    error exit, not an empty pass.
 *
 *  - Nothing is written to the site. Every non-GET request is aborted in the
 *    browser before it leaves, and the aborted requests are recorded. That is
 *    how the contact-form tests can exercise a real submit without ever
 *    delivering an enquiry or sending mail.
 */
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

// Playwright is not installed here on purpose.
//
// It is a ~300MB dependency with a downloaded browser, already present and
// already pinned at ../pixel-tool/node_modules. Installing a second copy would
// mean a second browser build, and the two harnesses would silently drift onto
// different Chromium versions — at which point "the behaviour changed" and "the
// browser changed" become indistinguishable. Sharing the install keeps both
// tools honest about which Chromium produced a result.
let chromium;
try {
  ({ chromium } = require('../pixel-tool/node_modules/playwright'));
} catch (e) {
  console.error('Cannot load playwright from ../pixel-tool/node_modules.');
  console.error('Run `npm install` inside _project/pixel-tool first. Original error:');
  console.error('  ' + e.message);
  process.exit(2);
}

const { tests, VIEWPORTS } = require('./tests');

const BASE = 'http://localhost/piecyfer';

// ---------------------------------------------------------------------------
// CLI
// ---------------------------------------------------------------------------
const argv = process.argv.slice(2);
const label = argv.find(a => !a.startsWith('--'));
const flag = n => argv.includes(n);
const opt = n => (argv.includes(n) ? argv[argv.indexOf(n) + 1] : null);

if (!label) {
  console.error('usage: node run.js <label> [--compare <baseline>] [--only <substring>]');
  console.error('                           [--simulate-no-pro] [--headed] [--clear-cache]');
  console.error('  e.g. node run.js pro-active');
  console.error('       node run.js after-phase5 --compare pro-active');
  console.error('       node run.js no-pro-preview --simulate-no-pro --compare pro-active');
  process.exit(2);
}
const compareWith = opt('--compare');
const only = opt('--only');
const headed = flag('--headed');

/**
 * --simulate-no-pro
 *
 * Aborts every request for a script that ships inside wp-content/plugins/
 * elementor-pro/. Nothing on the server changes — no plugin is deactivated, no
 * option touched, no row written — the browser simply never receives Pro's
 * JavaScript, which is precisely the state the site will be in once Pro is
 * replaced by a plugin that ships no JS.
 *
 * Two uses:
 *
 *  1. As the harness's OWN negative control. "25/25 passed" means nothing until
 *     you have watched the suite go red for the right reasons. A behaviour
 *     harness that cannot be shown to fail is indistinguishable from one that
 *     always returns true, and this project has already been burned three times
 *     by tests that were green because they never ran.
 *
 *  2. As a preview. Run it against the live Pro-active site and you get the
 *     exact list of what will break, with evidence, before anyone deactivates
 *     anything.
 *
 * It is NOT a substitute for a real post-migration run: it removes Pro's
 * scripts but leaves Pro's server-rendered markup in place, so it under-reports
 * anything that breaks because the markup changed.
 */
const simulateNoPro = flag('--simulate-no-pro');
const PRO_SCRIPTS = /\/plugins\/elementor-pro\/assets\/(js|lib)\/.*\.js/i;

const resultsDir = path.join(__dirname, 'results');
fs.mkdirSync(resultsDir, { recursive: true });
const resultPath = path.join(resultsDir, `${label}.json`);

/**
 * Elementor caches the rendered HTML of a whole document in
 * `_elementor_element_cache` for 24h when the e_element_cache experiment is on —
 * which it is on this site. While that cache is warm Elementor never calls the
 * widgets, so a run can "prove" a widget fine when the widget never executed.
 *
 * The pixel harness clears it on every capture for exactly this reason. This one
 * does NOT, by default: clearing is a database write, and this tool's brief is
 * to touch nothing. Behaviour tests are also far less exposed than pixel
 * captures — they assert on what the JavaScript does to the live DOM, and the
 * JavaScript is not what gets cached.
 *
 * But the markup the handlers bind TO is cached, so after a rebuild that changes
 * a widget's output you must clear it or you are testing yesterday's HTML. Pass
 * --clear-cache (or just run a pixel-tool capture first, which always clears).
 */
function clearElementorCache() {
  const script = path.join(__dirname, '..', 'scripts', 'clear-elementor-cache.php');
  const php = process.env.PHP_BIN || 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
  const out = execFileSync(php, [script], { encoding: 'utf8' });
  console.log('elementor cache cleared: ' +
    out.split('\n').filter(l => /^_elementor|^generated/.test(l)).join(' | '));
}

// ---------------------------------------------------------------------------
// Assertion recorder
//
// Every assertion writes down what it asserted, what it expected and what it
// actually saw, so a later diff explains itself without anybody re-reading this
// file. That is the whole point: `nav-toggle-mobile: fail` is useless;
// `nav-toggle-mobile / "aria-expanded flips to true" expected "true" got "false"`
// is a bug report.
// ---------------------------------------------------------------------------
class TargetMissing extends Error {}

function makeAsserter(page, record) {
  const push = (assertion, name, expected, observed, pass) => {
    record.checks.push({ name, assertion, expected, observed, pass });
    if (!pass) record.status = 'fail';
    return pass;
  };

  const t = {
    /** Strict equality. */
    eq: (name, observed, expected) =>
      push('equals', name, expected, observed, Object.is(observed, expected)),
    /** Strict inequality — "this must have changed". */
    neq: (name, observed, notExpected) =>
      push('differs from', name, notExpected, observed, !Object.is(observed, notExpected)),
    /** Numeric greater-than. */
    gt: (name, observed, floor) =>
      push('greater than', name, floor, observed, typeof observed === 'number' && observed > floor),
    /** Plain truthiness, for composed predicates. */
    truthy: (name, observed) =>
      push('is truthy', name, 'truthy', observed, !!observed),

    /**
     * Record a fact without asserting on it. Appears in the result file and in a
     * comparison report, but never fails a run. Used for things whose value is
     * expected to change (e.g. whether elementorProFrontend exists).
     */
    note: (name, observed, why) => {
      record.notes.push({ name, observed, why });
    },

    /** Attach arbitrary observed state to the result. */
    observe: (key, value) => { record.observed[key] = value; },

    /**
     * Assert a selector matches at least `min` elements. Throws TargetMissing
     * otherwise — a test whose subject is not on the page has not passed, it has
     * not run, and those two must never look alike.
     */
    async exists(selector, description, min = 1) {
      const n = await page.evaluate(s => document.querySelectorAll(s).length, selector);
      push('selector matches at least', `target present: ${description}`, `>=${min} matching "${selector}"`, n, n >= min);
      if (n < min) {
        throw new TargetMissing(
          `target missing: ${description} — selector "${selector}" matched ${n} element(s), needed at least ${min}`);
      }
      return n;
    },

    /**
     * Resolve the ONE instance of a widget that is actually visible at the
     * current viewport, and fail if that is not exactly one.
     *
     * This site renders the same header widget several times over — one copy per
     * breakpoint, the others collapsed to 0x0 — so `querySelector` returns
     * whichever happens to be first in the DOM rather than the one a user can
     * touch. Indexing into the list would keep passing after the real instance
     * disappeared, which is precisely the silent green this harness must not
     * produce.
     *
     * Returns { widgetId, index, box } so the test can scope its later selectors
     * to that instance.
     */
    async visibleOne(selector, description) {
      const found = await page.evaluate(s =>
        [...document.querySelectorAll(s)].map((el, index) => {
          const r = el.getBoundingClientRect();
          const cs = getComputedStyle(el);
          const host = el.closest('[data-id]');
          return {
            index,
            widgetId: host ? host.dataset.id : null,
            box: { w: Math.round(r.width), h: Math.round(r.height) },
            visible: r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && Number(cs.opacity) > 0,
          };
        }), selector);
      const visible = found.filter(f => f.visible);
      push('exactly one visible match', `target visible: ${description}`,
        `1 visible of "${selector}"`,
        `${visible.length} visible of ${found.length} total`,
        visible.length === 1);
      if (visible.length !== 1) {
        throw new TargetMissing(
          `target not uniquely visible: ${description} — "${selector}" had ${found.length} match(es), ` +
          `${visible.length} of them visible at this viewport (need exactly 1). ` +
          `Boxes: ${JSON.stringify(found.map(f => f.box))}`);
      }
      return visible[0];
    },

    /**
     * Wait for a DOM predicate. The predicate's English description is recorded
     * as a check, pass or fail, so the result file says what the test waited for
     * and whether the site ever got there.
     */
    async waitFor(fn, description, arg = undefined, timeout = 15000) {
      const started = Date.now();
      try {
        await page.waitForFunction(fn, arg, { timeout, polling: 100 });
        push('condition reached', `waited for: ${description}`, 'true within ' + timeout + 'ms',
          `true after ${Date.now() - started}ms`, true);
        return true;
      } catch (e) {
        push('condition reached', `waited for: ${description}`, 'true within ' + timeout + 'ms',
          `NEVER TRUE (timed out after ${Date.now() - started}ms)`, false);
        return false;
      }
    },

    /**
     * Wait for a predicate evaluated in NODE, not in the page — for conditions
     * about what the runner observed (e.g. "a request was intercepted").
     */
    async waitForNode(fn, description, timeout = 15000) {
      const started = Date.now();
      while (Date.now() - started < timeout) {
        if (fn()) {
          push('condition reached', `waited for: ${description}`, 'true within ' + timeout + 'ms',
            `true after ${Date.now() - started}ms`, true);
          return true;
        }
        await new Promise(r => setTimeout(r, 100));
      }
      push('condition reached', `waited for: ${description}`, 'true within ' + timeout + 'ms',
        `NEVER TRUE (timed out after ${timeout}ms)`, false);
      return false;
    },
  };
  return t;
}

// ---------------------------------------------------------------------------
// Page settling
//
// Much lighter than the pixel tool's, and deliberately so: we are not
// screenshotting, so half-decoded images and unloaded lazy thumbnails do not
// matter. What DOES matter is that every frontend handler has run, because a
// test that clicks a burger before the handler binds gets a false regression.
//
// So: wait for load, wait for jQuery's ready queue to drain, wait for
// elementorFrontend to report initialised, then poll until the count of
// initialised Swipers and the DOM size stop moving.
// ---------------------------------------------------------------------------
async function settle(page) {
  await page.waitForLoadState('load', { timeout: 120000 }).catch(() => {});

  // Elementor fires `elementor/frontend/init` and then initialises each element
  // handler. `elementorFrontend.elements` exists once the module has booted.
  await page.waitForFunction(
    () => typeof window.jQuery === 'function' &&
      window.elementorFrontend && window.elementorFrontend.elements,
    undefined,
    { timeout: 60000, polling: 100 }
  ).catch(() => { /* recorded by the env test and by the console tests */ });

  // Trip lazy-load observers so late-initialising widgets below the fold (the
  // testimonial carousels, the client-logo strip) actually get initialised
  // before a test tries to touch them.
  await page.evaluate(async () => {
    const step = Math.max(200, window.innerHeight * 0.9);
    for (let y = 0; y < document.body.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise(r => setTimeout(r, 80));
    }
    window.scrollTo(0, 0);
  }).catch(() => {});

  await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});

  // Poll a signature until it stops changing. Two consecutive identical samples
  // is enough here — unlike a screenshot, a DOM assertion does not care about
  // sub-pixel paint state.
  let last = null, stable = 0;
  for (let i = 0; i < 30; i++) {
    const sig = await page.evaluate(() => [
      document.querySelectorAll('.swiper-initialized, .swiper-container-initialized').length,
      document.querySelectorAll('[data-widget_type]').length,
      document.querySelectorAll('*').length,
      document.readyState,
    ].join(':')).catch(() => null);
    if (sig !== null && sig === last) { if (++stable >= 2) break; } else { stable = 0; }
    last = sig;
    await page.waitForTimeout(250);
  }

  await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
}

// ---------------------------------------------------------------------------
// One test
// ---------------------------------------------------------------------------
const NETWORK_NOISE = /^Failed to load resource/i;

async function runTest(browser, def, i, total) {
  const record = {
    id: def.id,
    title: def.title,
    url: def.url,
    viewport: def.viewport,
    asserts: def.asserts,
    why: def.why,
    network: def.network || null,
    status: 'pass',
    ms: 0,
    checks: [],
    notes: [],
    observed: {},
    consoleErrors: [],
    pageErrors: [],
    brokenAssets: [],
    blockedRequests: [],
    error: null,
  };
  const t0 = Date.now();

  const vp = VIEWPORTS[def.viewport];
  const ctx = await browser.newContext({
    viewport: { width: vp.width, height: vp.height },
    deviceScaleFactor: 1,
    // NOT reducedMotion:'reduce' — unlike the pixel tool we WANT the animations,
    // because "does it move" is half of what is being measured. Every wait is on
    // a settled end-state, so the animation costs time, not determinism.
  });

  // Same root-relative rewrite the pixel tool applies: a handful of documents
  // store /wp-content/... URLs that resolve on the production domain root but
  // 404 on this /piecyfer/ subdirectory copy.
  //
  // Fused into the same handler as the write-blocking below, because a URL only
  // gets ONE route in Playwright and two overlapping routes would mean the first
  // to match wins and the other silently never runs.
  await ctx.route(url => url.hostname === 'localhost', route => {
    const req = route.request();
    if (req.method() !== 'GET') {
      // Absolute guarantee that this harness never writes to the site: no
      // enquiry is delivered, no comment posted, no mail sent. The attempt is
      // recorded, which is what the form tests assert on.
      record.blockedRequests.push({
        method: req.method(),
        url: req.url(),
        body: req.postData() || '',
        aborted: true,
      });
      return route.abort();
    }
    const u = req.url();
    if (simulateNoPro && PRO_SCRIPTS.test(u)) {
      record.suppressedProScripts = (record.suppressedProScripts || [])
        .concat(u.replace(/^.*\/plugins\//, 'plugins/').replace(/\?.*$/, ''));
      return route.abort();
    }
    if (u.includes('://localhost/wp-content/')) {
      return route.continue({ url: u.replace('://localhost/wp-content/', '://localhost/piecyfer/wp-content/') });
    }
    return route.continue();
  });

  const page = await ctx.newPage();
  page.on('pageerror', e => record.pageErrors.push(String(e).slice(0, 300)));
  page.on('console', m => {
    if (m.type() !== 'error') return;
    const text = m.text().slice(0, 300);
    // A resource 404 is a browser network message, not a JS fault. Recorded
    // separately: still compared against the baseline (a newly broken asset is a
    // real regression) but it does not masquerade as a scripting error.
    if (NETWORK_NOISE.test(text)) {
      // The bare "Failed to load resource: net::ERR_FAILED" line carries no URL,
      // so under --simulate-no-pro it cannot be attributed to the scripts we
      // suppressed ourselves. Drop it wholesale in that mode; the requestfailed
      // handler above still records every genuine failure, with its URL.
      if (simulateNoPro && /ERR_FAILED/.test(text)) return;
      record.brokenAssets.push(text);
    } else record.consoleErrors.push(text);
  });
  page.on('requestfailed', r => {
    if (!r.url().startsWith(BASE)) return;   // third parties are their own problem
    const f = r.failure();
    // Aborts the runner itself performed are not the site's failures. Both the
    // write-blocking and --simulate-no-pro surface here as ERR_FAILED, and
    // recording them would bury the genuine broken assets under our own noise —
    // in the first --simulate-no-pro run this alone produced a spurious
    // "newly-broken-assets" regression on all 25 tests.
    if (r.method() !== 'GET') return;
    if (simulateNoPro && PRO_SCRIPTS.test(r.url())) return;
    record.brokenAssets.push(`FAILED ${r.url().replace(BASE, '')} (${f ? f.errorText : '?'})`);
  });
  page.on('response', r => {
    if (!r.url().startsWith(BASE)) return;
    if (r.status() >= 400 && r.url() !== BASE + def.url) {
      const e = `${r.status()} ${r.url().replace(BASE, '')}`;
      if (!record.brokenAssets.includes(e)) record.brokenAssets.push(e);
    }
  });

  const t = makeAsserter(page, record);

  try {
    const resp = await page.goto(BASE + def.url, { waitUntil: 'commit', timeout: 120000 });
    record.observed.httpStatus = resp ? resp.status() : null;
    await settle(page);

    await def.run({
      page, t, ctx, base: BASE,
      consoleErrors: record.consoleErrors,
      pageErrors: record.pageErrors,
      brokenAssets: record.brokenAssets,
      blockedRequests: record.blockedRequests,
    });

    // A test that asserted nothing has not passed. This is the "green result for
    // a test that never ran" trap, closed at the last possible moment: even if
    // every helper above were bypassed, a run() that falls through without
    // recording a single check cannot report success.
    if (record.checks.length === 0) {
      record.status = 'error';
      record.error = 'test recorded ZERO assertions — it did not actually test anything';
    }
  } catch (err) {
    record.status = err instanceof TargetMissing ? 'fail' : 'error';
    record.error = String(err && err.message ? err.message : err).slice(0, 500);
  } finally {
    await ctx.close().catch(() => {});
  }

  record.ms = Date.now() - t0;
  const failedChecks = record.checks.filter(c => !c.pass);
  const mark = record.status === 'pass' ? 'PASS' : record.status === 'fail' ? 'FAIL' : 'ERR ';
  console.log(
    `  [${String(i + 1).padStart(2)}/${total}] ${mark}  ${record.id.padEnd(34)} ` +
    `${String(record.ms).padStart(6)}ms  ${record.checks.length} checks` +
    (failedChecks.length ? `, ${failedChecks.length} failed` : '') +
    (record.pageErrors.length ? `, ${record.pageErrors.length} JS errors` : '')
  );
  failedChecks.forEach(c =>
    console.log(`         x ${c.name}: ${c.assertion} ${JSON.stringify(c.expected)}, observed ${JSON.stringify(c.observed)}`));
  if (record.error) console.log(`         ! ${record.error}`);
  return record;
}

// ---------------------------------------------------------------------------
// Baseline comparison
// ---------------------------------------------------------------------------
function compare(current, baselineLabel) {
  const p = path.join(resultsDir, `${baselineLabel}.json`);
  if (!fs.existsSync(p)) {
    console.error(`\nbaseline not found: ${p}`);
    console.error(`Record one first:  node run.js ${baselineLabel}`);
    process.exit(2);
  }
  const base = JSON.parse(fs.readFileSync(p, 'utf8'));

  // A baseline recorded with Pro's scripts blocked is not a reference for
  // anything — it is a record of the site broken on purpose. Comparing against
  // it would invert every verdict, so say so loudly rather than quietly
  // producing a report full of "fixes".
  if (base.simulateNoPro && !current.simulateNoPro) {
    console.error(`\nWARNING: baseline "${baselineLabel}" was recorded with --simulate-no-pro.`);
    console.error('That is a deliberately broken run and must not be used as a reference.');
    process.exit(2);
  }
  const byId = Object.fromEntries(base.tests.map(x => [x.id, x]));
  const curById = Object.fromEntries(current.tests.map(x => [x.id, x]));

  const regressions = [];
  const fixes = [];
  const added = [];

  for (const b of base.tests) {
    const c = curById[b.id];
    if (!c) {
      // A test that vanished cannot be reported as passing. Renaming a test id
      // is a real event and must be acknowledged, not absorbed.
      regressions.push({ id: b.id, kind: 'test-missing', detail: `present in ${baselineLabel}, absent from ${current.label}` });
      continue;
    }
    if (b.status === 'pass' && c.status !== 'pass') {
      const failed = c.checks.filter(x => !x.pass)
        .map(x => `${x.name}: expected ${x.assertion} ${JSON.stringify(x.expected)}, observed ${JSON.stringify(x.observed)}`);
      regressions.push({ id: b.id, kind: 'behaviour-regressed', title: b.title, why: b.why, detail: c.error || failed.join(' | ') || c.status });
      continue;
    }
    // Even when the overall verdict still says pass, an individual check that
    // used to pass and now does not is a regression. Nothing here should ever
    // report "pass" with a failed check, but asserting it costs nothing and
    // closes the gap if a future test forgets to propagate.
    const baseChecks = Object.fromEntries(b.checks.map(x => [x.name, x]));
    for (const cc of c.checks) {
      const bc = baseChecks[cc.name];
      if (bc && bc.pass && !cc.pass) {
        regressions.push({ id: b.id, kind: 'check-regressed', detail: `${cc.name}: expected ${JSON.stringify(cc.expected)}, observed ${JSON.stringify(cc.observed)}` });
      }
    }
    if (b.status !== 'pass' && c.status === 'pass') fixes.push({ id: b.id, title: b.title });

    // New uncaught JS errors and newly broken assets are regressions in their
    // own right, even on a test whose assertions all still hold.
    const wasErr = new Set([...(b.pageErrors || []), ...(b.consoleErrors || [])]);
    const freshErr = [...(c.pageErrors || []), ...(c.consoleErrors || [])].filter(e => !wasErr.has(e));
    if (freshErr.length) regressions.push({ id: b.id, kind: 'new-js-errors', detail: freshErr.join(' | ').slice(0, 300) });

    const wasBroken = new Set(b.brokenAssets || []);
    const freshBroken = (c.brokenAssets || []).filter(e => !wasBroken.has(e));
    if (freshBroken.length) regressions.push({ id: b.id, kind: 'newly-broken-assets', detail: freshBroken.join(' | ').slice(0, 300) });
  }

  for (const c of current.tests) if (!byId[c.id]) added.push({ id: c.id, status: c.status });

  return { baselineLabel, regressions, fixes, added };
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
(async () => {
  if (flag('--clear-cache')) clearElementorCache();

  let targets = tests;
  if (only) {
    targets = tests.filter(x => x.id.includes(only) || x.url.includes(only));
    // An --only that matched nothing must be an error, not a silent empty pass.
    // The pixel tool shipped exactly this bug: Git Bash rewrote a leading slash
    // into a Windows path, --only matched zero urls, and the run reported
    // success having tested nothing.
    if (!targets.length) {
      console.error(`--only "${only}" matched none of the ${tests.length} tests. Nothing run.`);
      console.error('ids: ' + tests.map(x => x.id).join(', '));
      process.exit(2);
    }
  }

  // Duplicate ids would make a comparison silently drop results.
  const ids = targets.map(x => x.id);
  const dupes = ids.filter((x, i) => ids.indexOf(x) !== i);
  if (dupes.length) {
    console.error('duplicate test ids: ' + [...new Set(dupes)].join(', '));
    process.exit(2);
  }

  console.log(`behaviour run "${label}" — ${targets.length} tests, serial, base ${BASE}`);
  if (simulateNoPro) {
    console.log('--simulate-no-pro: Elementor Pro\'s frontend scripts will be blocked in the');
    console.log('  browser. NOTHING on the server is changed. Expect failures — that is the point.');
  }
  if (compareWith) console.log(`will compare against baseline "${compareWith}"`);
  console.log('');

  const browser = await chromium.launch({ headless: !headed });
  const result = {
    label,
    base: BASE,
    startedAt: new Date().toISOString(),
    playwright: require('../pixel-tool/node_modules/playwright/package.json').version,
    chromium: browser.version(),
    simulateNoPro,
    tests: [],
  };

  for (let i = 0; i < targets.length; i++) {
    result.tests.push(await runTest(browser, targets[i], i, targets.length));
  }
  await browser.close();

  result.completedAt = new Date().toISOString();
  const passed = result.tests.filter(x => x.status === 'pass').length;
  const failed = result.tests.filter(x => x.status === 'fail').length;
  const errored = result.tests.filter(x => x.status === 'error').length;
  const totalChecks = result.tests.reduce((n, x) => n + x.checks.length, 0);
  result.summary = { total: result.tests.length, passed, failed, errored, checks: totalChecks };
  fs.writeFileSync(resultPath, JSON.stringify(result, null, 2));

  console.log('');
  console.log(`  ${passed}/${result.tests.length} tests passed  (${totalChecks} individual assertions)`);
  if (failed) console.log(`  ${failed} failed`);
  if (errored) console.log(`  ${errored} errored`);
  console.log(`  -> ${resultPath}`);

  // Running nothing is not a pass. Two empty result files satisfy every count
  // below, and a gate that approves an experiment which never ran is worse than
  // no gate at all.
  if (result.tests.length === 0) {
    console.log('\n  NOTHING RAN — this proves nothing.');
    process.exit(2);
  }

  let exit = failed + errored > 0 ? 1 : 0;

  if (compareWith) {
    const cmp = compare(result, compareWith);
    result.comparison = cmp;
    fs.writeFileSync(resultPath, JSON.stringify(result, null, 2));

    console.log(`\n--- compared with "${compareWith}" ---`);
    if (cmp.regressions.length) {
      console.log(`\n  ${cmp.regressions.length} REGRESSION(S):`);
      for (const r of cmp.regressions) {
        console.log(`\n  * ${r.id}  [${r.kind}]`);
        if (r.title) console.log(`    ${r.title}`);
        console.log(`    ${r.detail}`);
        if (r.why) console.log(`    why it matters: ${r.why.replace(/\s+/g, ' ').slice(0, 220)}`);
      }
      exit = 1;
    } else {
      console.log('  no regressions.');
    }
    if (cmp.fixes.length) console.log(`\n  ${cmp.fixes.length} test(s) newly passing: ${cmp.fixes.map(f => f.id).join(', ')}`);
    if (cmp.added.length) console.log(`  ${cmp.added.length} test(s) not in the baseline: ${cmp.added.map(f => f.id).join(', ')}`);
  }

  process.exit(exit);
})().catch(err => {
  console.error('\nrunner crashed: ' + (err && err.stack ? err.stack : err));
  process.exit(2);
});
