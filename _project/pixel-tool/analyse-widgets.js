#!/usr/bin/env node
/**
 * Walk every `_elementor_data` document and report, per widget type:
 *   - how many instances exist and which documents they live in
 *   - exactly which setting keys are populated
 *
 * The setting-key list is the build specification for Phase 4: our replacement
 * widget must register a control for every key listed here, under the same id,
 * or Elementor will silently drop that saved value and the page will change.
 *
 * Usage:
 *   node analyse-widgets.js <path-to-eldata.json>   # produced by dump-eldata.php
 */
const fs = require('fs');

const src = process.argv[2];
if (!src) { console.error('usage: node analyse-widgets.js <eldata.json>'); process.exit(1); }

const docs = JSON.parse(fs.readFileSync(src, 'utf8'));

// Widgets shipped by Elementor Pro (verified against the plugin source on disk).
const PRO = new Set([
  'nav-menu', 'template', 'posts', 'form', 'search-form', 'blockquote',
  'call-to-action', 'archive-posts', 'theme-site-logo', 'theme-archive-title',
  'testimonial-carousel', 'post-comments', 'theme-post-title',
  'theme-post-content', 'post-info', 'gallery',
]);
const isEK = t => t.startsWith('elementskit') || t.startsWith('ekit');

const stats = new Map(); // widgetType -> { count, keys:Map<key,count>, docs:Set }

function visit(node, docLabel) {
  if (Array.isArray(node)) { node.forEach(n => visit(n, docLabel)); return; }
  if (!node || typeof node !== 'object') return;

  if (node.elType === 'widget' && node.widgetType) {
    const t = node.widgetType;
    if (!stats.has(t)) stats.set(t, { count: 0, keys: new Map(), docs: new Set() });
    const s = stats.get(t);
    s.count++;
    s.docs.add(docLabel);
    for (const [k, v] of Object.entries(node.settings || {})) {
      // An empty string / empty array is a control that exists but was never
      // set — it does not constrain our implementation, so skip it.
      const empty = v === '' || v === null ||
        (Array.isArray(v) && v.length === 0) ||
        (typeof v === 'object' && !Array.isArray(v) && Object.keys(v).length === 0);
      if (empty) continue;
      s.keys.set(k, (s.keys.get(k) || 0) + 1);
    }
  }
  if (node.elements) visit(node.elements, docLabel);
}

for (const d of docs) {
  let parsed;
  try { parsed = JSON.parse(d.data); } catch (e) { console.error(`  ! unparseable: ${d.title}`); continue; }
  visit(parsed, `${d.type}:${d.title}`);
}

const rows = [...stats.entries()].sort((a, b) => b[1].count - a[1].count);

function section(title, filter) {
  const sel = rows.filter(([t]) => filter(t));
  if (!sel.length) return;
  const total = sel.reduce((n, [, s]) => n + s.count, 0);
  console.log(`\n${'='.repeat(78)}\n${title}  —  ${sel.length} types, ${total} instances\n${'='.repeat(78)}`);
  for (const [t, s] of sel) {
    console.log(`\n### ${t}  (${s.count} instances, ${s.docs.size} documents)`);
    const keys = [...s.keys.entries()].sort((a, b) => b[1] - a[1]);
    console.log(`    ${keys.length} populated setting keys:`);
    // Group responsive variants so the list reads as controls, not permutations.
    const base = new Map();
    for (const [k, n] of keys) {
      const b = k.replace(/_(tablet|mobile|laptop|widescreen|mobile_extra|tablet_extra)$/, '');
      if (!base.has(b)) base.set(b, { n: 0, resp: new Set() });
      base.get(b).n += n;
      const mm = k.match(/_(tablet|mobile|laptop|widescreen|mobile_extra|tablet_extra)$/);
      if (mm) base.get(b).resp.add(mm[1]);
    }
    for (const [k, info] of [...base.entries()].sort((a, b) => b[1].n - a[1].n)) {
      const r = info.resp.size ? `  [responsive: ${[...info.resp].join(',')}]` : '';
      console.log(`      ${String(info.n).padStart(4)}x  ${k}${r}`);
    }
  }
}

section('ELEMENTOR PRO — must be rebuilt in piecyfer-core', t => PRO.has(t));
section('ELEMENTSKIT — must be rebuilt in piecyfer-core', t => isEK(t));
section('ELEMENTOR FREE — no work required', t => !PRO.has(t) && !isEK(t));

const proCount = rows.filter(([t]) => PRO.has(t)).reduce((n, [, s]) => n + s.count, 0);
const ekCount = rows.filter(([t]) => isEK(t)).reduce((n, [, s]) => n + s.count, 0);
const all = rows.reduce((n, [, s]) => n + s.count, 0);
console.log(`\n${'='.repeat(78)}`);
console.log(`TOTAL ${all} widget instances`);
console.log(`  Elementor Pro : ${proCount}  (${(proCount / all * 100).toFixed(1)}%)  <- rebuild`);
console.log(`  ElementsKit   : ${ekCount}  (${(ekCount / all * 100).toFixed(1)}%)  <- rebuild`);
console.log(`  Elementor free: ${all - proCount - ekCount}  (${((all - proCount - ekCount) / all * 100).toFixed(1)}%)  <- keep as-is`);
