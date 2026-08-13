#!/usr/bin/env node
/**
 * Diff two capture runs and report every visual or markup change.
 *
 *   node compare.js baseline after-phase3
 *
 * Exit code 0 = identical, 1 = differences found. That makes it usable as a
 * gate: no phase is "done" until this returns 0 (or until every reported
 * difference has been consciously accepted).
 *
 * Writes ../snapshots/_diff-<a>-vs-<b>/
 *   report.md                 human-readable summary
 *   <slug>@<vp>.diff.png      red-highlighted pixel diff for each changed shot
 */
const fs = require('fs');
const path = require('path');
const { PNG } = require('pngjs');
const pixelmatch = require('pixelmatch');

const [a, b] = process.argv.slice(2);
if (!a || !b) {
  console.error('usage: node compare.js <labelA> <labelB>');
  process.exit(2);
}

const snapRoot = path.join(__dirname, '..', 'snapshots');
const dirA = path.join(snapRoot, a);
const dirB = path.join(snapRoot, b);
for (const d of [dirA, dirB]) {
  if (!fs.existsSync(d)) { console.error(`missing snapshot: ${d}`); process.exit(2); }
}
const outDir = path.join(snapRoot, `_diff-${a}-vs-${b}`);
fs.mkdirSync(outDir, { recursive: true });

// Tolerance for anti-aliasing / sub-pixel text rendering. Below this a shot is
// treated as unchanged; a genuine layout shift is orders of magnitude larger.
const PIXEL_THRESHOLD = 0.1;   // per-pixel colour distance
const RATIO_TOLERANCE = 0.0002; // 0.02% of pixels may differ

// A --quick capture holds 8 pages; the baseline holds 39. Comparing them is
// legitimate and common, so absence from B is only a failure when B is not a
// subset of A. Otherwise it is just "we did not check that page this time".
const slugsIn = d => new Set(
  fs.existsSync(path.join(d, 'html')) ? fs.readdirSync(path.join(d, 'html')).map(f => f.replace(/\.html$/, '')) : []
);
const slugsA = slugsIn(dirA);
const slugsB = slugsIn(dirB);
const isSubset = slugsB.size < slugsA.size && [...slugsB].every(s => slugsA.has(s));

const lines = [];
let htmlChanged = 0, shotsChanged = 0, missing = 0, compared = 0, skipped = 0;

// ------------------------------------------------------------------ markup
lines.push('## Markup differences\n');
const htmlA = fs.existsSync(path.join(dirA, 'html')) ? fs.readdirSync(path.join(dirA, 'html')) : [];
for (const f of htmlA) {
  const pa = path.join(dirA, 'html', f);
  const pb = path.join(dirB, 'html', f);
  if (!fs.existsSync(pb)) {
    if (isSubset) { skipped++; continue; }
    lines.push(`- **MISSING in ${b}**: \`${f}\``); missing++; continue;
  }
  const sa = fs.readFileSync(pa, 'utf8');
  const sb = fs.readFileSync(pb, 'utf8');
  if (sa === sb) continue;

  htmlChanged++;
  const la = sa.split('\n'), lb = sb.split('\n');
  const changes = [];
  for (let i = 0; i < Math.max(la.length, lb.length) && changes.length < 6; i++) {
    if (la[i] !== lb[i]) {
      changes.push(
        `    line ${i + 1}\n` +
        `      - ${String(la[i] ?? '(absent)').trim().slice(0, 150)}\n` +
        `      + ${String(lb[i] ?? '(absent)').trim().slice(0, 150)}`
      );
    }
  }
  lines.push(`- **${f}** — ${Math.abs(sa.length - sb.length)} byte delta, first differences:\n${changes.join('\n')}`);
}
if (htmlChanged === 0 && missing === 0) lines.push('_None — all markup identical._');

// ----------------------------------------------------------------- pixels
lines.push('\n## Visual differences\n');
const shotsA = fs.existsSync(path.join(dirA, 'shots')) ? fs.readdirSync(path.join(dirA, 'shots')) : [];
for (const f of shotsA) {
  const pa = path.join(dirA, 'shots', f);
  const pb = path.join(dirB, 'shots', f);
  if (!fs.existsSync(pb)) {
    if (isSubset) { skipped++; continue; }
    lines.push(`- **MISSING in ${b}**: \`${f}\``); missing++; continue;
  }

  const ia = PNG.sync.read(fs.readFileSync(pa));
  const ib = PNG.sync.read(fs.readFileSync(pb));
  compared++;

  // A height change is itself the finding — content grew or shrank.
  if (ia.width !== ib.width || ia.height !== ib.height) {
    shotsChanged++;
    lines.push(`- **${f}** — SIZE CHANGED ${ia.width}x${ia.height} -> ${ib.width}x${ib.height} (page height moved by ${ib.height - ia.height}px)`);
    continue;
  }

  const diff = new PNG({ width: ia.width, height: ia.height });
  const n = pixelmatch(ia.data, ib.data, diff.data, ia.width, ia.height, {
    threshold: PIXEL_THRESHOLD,
    includeAA: false,
  });
  const ratio = n / (ia.width * ia.height);
  if (ratio > RATIO_TOLERANCE) {
    shotsChanged++;
    fs.writeFileSync(path.join(outDir, f.replace(/\.png$/, '.diff.png')), PNG.sync.write(diff));
    lines.push(`- **${f}** — ${n.toLocaleString()} px differ (${(ratio * 100).toFixed(3)}%) → \`${f.replace(/\.png$/, '.diff.png')}\``);
  }
}
if (shotsChanged === 0) lines.push('_None — all screenshots identical within tolerance._');

// ------------------------------------------------- console & broken assets
lines.push('\n## New console errors\n');
let newErrors = 0, newBroken = 0;
const brokenLines = [];
try {
  const ma = JSON.parse(fs.readFileSync(path.join(dirA, 'meta.json'), 'utf8'));
  const mb = JSON.parse(fs.readFileSync(path.join(dirB, 'meta.json'), 'utf8'));
  const byslugA = Object.fromEntries(ma.pages.map(p => [p.slug, p]));
  for (const p of mb.pages) {
    const before = new Set((byslugA[p.slug]?.consoleErrors) || []);
    const fresh = (p.consoleErrors || []).filter(e => !before.has(e));
    if (fresh.length) {
      newErrors += fresh.length;
      lines.push(`- **${p.slug}**\n${fresh.map(e => `    - ${e}`).join('\n')}`);
    }
    // A newly-broken asset is a regression even when the screenshot survives it.
    const hadBroken = new Set((byslugA[p.slug]?.failedRequests) || []);
    const freshBroken = (p.failedRequests || []).filter(e => !hadBroken.has(e));
    if (freshBroken.length) {
      newBroken += freshBroken.length;
      brokenLines.push(`- **${p.slug}**\n${freshBroken.map(e => `    - ${e}`).join('\n')}`);
    }
  }
} catch (e) { lines.push(`_could not read meta.json: ${e.message}_`); }
if (newErrors === 0) lines.push('_None._');

lines.push('\n## Newly broken assets (404 / failed requests)\n');
lines.push(brokenLines.length ? brokenLines.join('\n') : '_None._');

// ----------------------------------------------------------------- report
const clean = htmlChanged === 0 && shotsChanged === 0 && missing === 0 && newErrors === 0 && newBroken === 0;
const header =
  `# Pixel & markup comparison\n\n` +
  `**${a}** → **${b}**\n\n` +
  (isSubset
    ? `> Subset comparison: \`${b}\` covers ${slugsB.size} of the ${slugsA.size} pages in ` +
      `\`${a}\`. ${skipped} artefact(s) in the baseline were not re-captured this run and are ` +
      `not counted as missing. Run the full set before closing out a phase.\n\n`
    : '') +
  `| | |\n|---|---|\n` +
  `| Screenshots compared | ${compared} |\n` +
  `| Pages with markup changes | ${htmlChanged} |\n` +
  `| Screenshots with visual changes | ${shotsChanged} |\n` +
  `| Missing artefacts | ${missing} |\n` +
  `| New console errors | ${newErrors} |\n` +
  `| Newly broken assets | ${newBroken} |\n` +
  `| **Verdict** | ${clean ? '✅ **IDENTICAL**' : '⚠️ **DIFFERENCES FOUND**'} |\n\n`;

fs.writeFileSync(path.join(outDir, 'report.md'), header + lines.join('\n') + '\n');
console.log(header);
if (!clean) console.log(`Full report: ${path.join(outDir, 'report.md')}`);
process.exit(clean ? 0 : 1);
