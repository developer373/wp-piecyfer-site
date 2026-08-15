const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));

const f1 = path.join(__dirname, '../snapshots/ref3-a/shots/this-url-does-not-exist-404-test@desktop.png');
const img1 = PNG.sync.read(fs.readFileSync(f1));

console.log("Analyzing REF 404 image color changes at X=960 (page center):");
let lastC = '';
for (let y = 0; y < img1.height; y++) {
  const idx = (img1.width * y + 960) << 2;
  const c = `rgb(${img1.data[idx]},${img1.data[idx+1]},${img1.data[idx+2]})`;
  if (c !== lastC) {
    console.log(`Y=${y}: ${c}`);
    lastC = c;
  }
}
