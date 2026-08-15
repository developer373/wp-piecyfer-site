const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));

const f1 = path.join(__dirname, '../snapshots/ref3-a/shots/category-erp@desktop.png');
const f2 = path.join(__dirname, '../snapshots/b-cutover-quick3/shots/category-erp@desktop.png');

const img1 = PNG.sync.read(fs.readFileSync(f1));
const img2 = PNG.sync.read(fs.readFileSync(f2));

console.log(`Image 1 (ref3-a): ${img1.width}x${img1.height}`);
console.log(`Image 2 (quick3): ${img2.width}x${img2.height}`);

const minH = Math.min(img1.height, img2.height);

const rowDiffs = [];
for (let y = 0; y < minH; y++) {
  let diffCount = 0;
  for (let x = 0; x < 1920; x++) {
    const idx1 = (img1.width * y + x) << 2;
    const idx2 = (img2.width * y + x) << 2;
    const d = Math.abs(img1.data[idx1] - img2.data[idx2]) +
              Math.abs(img1.data[idx1+1] - img2.data[idx2+1]) +
              Math.abs(img1.data[idx1+2] - img2.data[idx2+2]);
    if (d > 30) diffCount++;
  }
  if (diffCount > 5) {
    rowDiffs.push({ y, diffCount });
  }
}

console.log(`Total rows with diffs: ${rowDiffs.length}`);
if (rowDiffs.length > 0) {
  const clusters = [];
  let cur = null;
  for (const r of rowDiffs) {
    if (!cur || r.y > cur.endY + 20) {
      cur = { startY: r.y, endY: r.y, count: r.diffCount };
      clusters.push(cur);
    } else {
      cur.endY = r.y;
      cur.count += r.diffCount;
    }
  }
  console.log("Clusters on category-erp:");
  for (const c of clusters) {
    console.log(`  - Y ${c.startY} to ${c.endY} (height: ${c.endY - c.startY + 1}px, diff pixels: ${c.count})`);
  }
}
