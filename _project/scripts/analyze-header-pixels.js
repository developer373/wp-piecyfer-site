const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));

const img1 = PNG.sync.read(fs.readFileSync(path.join(__dirname, '../snapshots/header_ref.png')));
const img2 = PNG.sync.read(fs.readFileSync(path.join(__dirname, '../snapshots/header_live.png')));

// Find bounding box of differing pixels
let minX = 1920, maxX = 0, minY = 160, maxY = 0;
let totalDiff = 0;

for (let y = 0; y < 160; y++) {
  for (let x = 0; x < 1920; x++) {
    const idx = (1920 * y + x) << 2;
    const d = Math.abs(img1.data[idx] - img2.data[idx]) +
              Math.abs(img1.data[idx+1] - img2.data[idx+1]) +
              Math.abs(img1.data[idx+2] - img2.data[idx+2]);
    if (d > 30) {
      totalDiff++;
      if (x < minX) minX = x;
      if (x > maxX) maxX = x;
      if (y < minY) minY = y;
      if (y > maxY) maxY = y;
    }
  }
}

console.log(`Total diff pixels in header: ${totalDiff}`);
console.log(`Diff Bounding Box: X=[${minX}..${maxX}], Y=[${minY}..${maxY}]`);

// Inspect vertical line colors at center X=960 for both images
console.log("\nVertical pixel sample at X=960 (center of page):");
for (let y = 35; y <= 60; y++) {
  const idx = (1920 * y + 960) << 2;
  const c1 = `rgb(${img1.data[idx]},${img1.data[idx+1]},${img1.data[idx+2]})`;
  const c2 = `rgb(${img2.data[idx]},${img2.data[idx+1]},${img2.data[idx+2]})`;
  if (c1 !== c2) {
    console.log(`  Y=${y}: ref=${c1} vs live=${c2}`);
  }
}

// Inspect bottom of top bar and bottom of main header
console.log("\nTop bar boundary:");
for (let y = 40; y <= 55; y++) {
  const idx = (1920 * y + 100) << 2;
  const c1 = `rgb(${img1.data[idx]},${img1.data[idx+1]},${img1.data[idx+2]})`;
  const c2 = `rgb(${img2.data[idx]},${img2.data[idx+1]},${img2.data[idx+2]})`;
  console.log(`  Y=${y}: ref=${c1} vs live=${c2}`);
}
