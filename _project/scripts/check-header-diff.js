const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));

const f1 = path.join(__dirname, '../snapshots/ref3-a/shots/contact-us@desktop.png');
const f2 = path.join(__dirname, '../snapshots/b-cutover-quick2/shots/contact-us@desktop.png');

const img1 = PNG.sync.read(fs.readFileSync(f1));
const img2 = PNG.sync.read(fs.readFileSync(f2));

console.log("Analyzing Top Header (Y = 0..150):");
for (let y = 0; y < 150; y += 5) {
  let diffs = 0;
  for (let x = 0; x < 1920; x++) {
    const idx1 = (img1.width * y + x) << 2;
    const idx2 = (img2.width * y + x) << 2;
    const d = Math.abs(img1.data[idx1] - img2.data[idx2]) +
              Math.abs(img1.data[idx1+1] - img2.data[idx2+1]) +
              Math.abs(img1.data[idx1+2] - img2.data[idx2+2]);
    if (d > 30) diffs++;
  }
  if (diffs > 0) {
    console.log(`  Row Y=${y}: ${diffs} diff pixels`);
  }
}
