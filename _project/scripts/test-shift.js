const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));

const f1 = path.join(__dirname, '../snapshots/ref3-a/shots/this-url-does-not-exist-404-test@desktop.png');
const f2 = path.join(__dirname, '../snapshots/b-cutover-quick4/shots/this-url-does-not-exist-404-test@desktop.png');

const img1 = PNG.sync.read(fs.readFileSync(f1));
const img2 = PNG.sync.read(fs.readFileSync(f2));

// Compare Y=200 to Y=2100 with a 7px shift
let matchingRows = 0;
let totalCompared = 0;

for (let y1 = 200; y1 < 2150; y1++) {
  const y2 = y1 - 7; // shift live up by 7px to match ref
  if (y2 >= img2.height) continue;
  totalCompared++;
  let diffInRow = 0;
  for (let x = 0; x < 1920; x++) {
    const idx1 = (img1.width * y1 + x) << 2;
    const idx2 = (img2.width * y2 + x) << 2;
    const d = Math.abs(img1.data[idx1] - img2.data[idx2]) +
              Math.abs(img1.data[idx1+1] - img2.data[idx2+1]) +
              Math.abs(img1.data[idx1+2] - img2.data[idx2+2]);
    if (d > 30) diffInRow++;
  }
  if (diffInRow === 0) matchingRows++;
  else console.log(`Row Y1=${y1} (Y2=${y2}) has ${diffInRow} diff pixels`);
}

console.log(`Matching rows with 7px shift: ${matchingRows} / ${totalCompared} (${((matchingRows/totalCompared)*100).toFixed(2)}%)`);
