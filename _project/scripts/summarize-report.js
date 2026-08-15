const fs = require('fs');
const path = require('path');

const report = JSON.parse(fs.readFileSync(path.resolve(__dirname, 'crawl-report.json'), 'utf8'));

console.log('TOTAL BROKEN ITEMS SUMMARY:');
const map = {};
for (const p of report.brokenLinksFound) {
  for (const b of p.broken) {
    const key = `${b.rawHref} || ${b.text}`;
    if (!map[key]) {
      map[key] = {
        raw: b.rawHref,
        text: b.text,
        count: 0,
        pages: []
      };
    }
    map[key].count++;
    map[key].pages.push(p.pageTitle);
  }
}

console.log(JSON.stringify(Object.values(map), null, 2));
