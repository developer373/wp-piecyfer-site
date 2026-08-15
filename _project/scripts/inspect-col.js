const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));

const f1 = path.join(__dirname, '../snapshots/ref3-a/shots/this-url-does-not-exist-404-test@desktop.png');
const f2 = path.join(__dirname, '../snapshots/b-cutover-quick4/shots/this-url-does-not-exist-404-test@desktop.png');

const img1 = PNG.sync.read(fs.readFileSync(f1));
const img2 = PNG.sync.read(fs.readFileSync(f2));

console.log("Analyzing pixel transitions from Y=0 to Y=200 at X=100 (left margin area):");
function inspectCol(x) {
  console.log(`\n--- COLUMN X=${x} ---`);
  let last1 = '', last2 = '';
  for (let y = 0; y < 200; y++) {
    const idx1 = (img1.width * y + x) << 2;
    const idx2 = (img2.width * y + x) << 2;
    const c1 = `rgb(${img1.data[idx1]},${img1.data[idx1+1]},${img1.data[idx1+2]})`;
    const c2 = `rgb(${img2.data[idx2]},${img2.data[idx2+1]},${img2.data[idx2+2]})`;
    if (c1 !== last1 || c2 !== last2) {
      console.log(`Y=${y}: REF=${c1} | LIVE=${c2}`);
      last1 = c1;
      last2 = c2;
    }
  }
}

inspectCol(100);
inspectCol(960);
