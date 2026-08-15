const fs = require('fs');
const path = require('path');

const f1 = path.join(__dirname, '../snapshots/ref3-a/html/this-url-does-not-exist-404-test.html');
const f2 = path.join(__dirname, '../snapshots/b-cutover-quick4/html/this-url-does-not-exist-404-test.html');

const h1 = fs.readFileSync(f1, 'utf8');
const h2 = fs.readFileSync(f2, 'utf8');

function getStyles(html) {
  const links = [];
  const linkRegex = /<link[^>]+rel=['"]stylesheet['"][^>]*>/gi;
  let m;
  while ((m = linkRegex.exec(html)) !== null) {
    const id = m[0].match(/id=['"]([^'"]+)['"]/i)?.[1] || '';
    const href = m[0].match(/href=['"]([^'"]+)['"]/i)?.[1] || '';
    const media = m[0].match(/media=['"]([^'"]+)['"]/i)?.[1] || 'all';
    links.push({ id, href: href.replace(/ver=[^&]+/, 'ver=X'), media });
  }
  return links;
}

const s1 = getStyles(h1);
const s2 = getStyles(h2);

console.log("STYLES IN REF3-A (" + s1.length + "):");
s1.forEach(s => console.log(`  - id="${s.id}" media="${s.media}" href="${s.href}"`));

console.log("\nSTYLES IN QUICK4 (" + s2.length + "):");
s2.forEach(s => console.log(`  - id="${s.id}" media="${s.media}" href="${s.href}"`));
