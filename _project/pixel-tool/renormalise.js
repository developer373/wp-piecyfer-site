#!/usr/bin/env node
/**
 * Re-apply the current normalisation rules to an already-captured snapshot.
 *
 *   node renormalise.js baseline [more-labels…]
 *
 * When a new source of markup noise is found — SmartMenus' per-render ids, the
 * emoji polyfill's floating position — the rule is added to normalise.js and
 * every future capture picks it up. Existing baselines do not, so they would
 * keep reporting the noise forever.
 *
 * Re-capturing the baseline instead would cost ~35 minutes and, worse, would
 * silently fold in any *real* change made since it was taken. Re-normalising
 * touches only the noise.
 *
 * Note this is idempotent: the rules replace variable text with fixed markers,
 * and running them over already-marked text is a no-op.
 */
const fs = require('fs');
const path = require('path');
const { normalise } = require('./normalise');

const labels = process.argv.slice(2);
if (!labels.length) {
  console.error('usage: node renormalise.js <label> [label…]');
  process.exit(1);
}

for (const label of labels) {
  const dir = path.join(__dirname, '..', 'snapshots', label, 'html');
  if (!fs.existsSync(dir)) {
    console.error(`  ! no html/ directory for "${label}"`);
    continue;
  }

  let changed = 0;
  const files = fs.readdirSync(dir).filter(f => f.endsWith('.html'));
  for (const f of files) {
    const p = path.join(dir, f);
    const before = fs.readFileSync(p, 'utf8');
    const after = normalise(before);
    if (after !== before) {
      fs.writeFileSync(p, after);
      changed++;
    }
  }
  console.log(`  ${label}: ${changed}/${files.length} file(s) updated`);
}
