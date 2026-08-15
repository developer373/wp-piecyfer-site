const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));
const pixelmatch = require(path.join(__dirname, '../pixel-tool/node_modules/pixelmatch'));

function crop(src, w, h) {
  const dst = new PNG({ width: w, height: h });
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const srcIdx = (src.width * y + x) << 2;
      const dstIdx = (w * y + x) << 2;
      dst.data[dstIdx] = src.data[srcIdx];
      dst.data[dstIdx + 1] = src.data[srcIdx + 1];
      dst.data[dstIdx + 2] = src.data[srcIdx + 2];
      dst.data[dstIdx + 3] = src.data[srcIdx + 3];
    }
  }
  return dst;
}

function generateDiff(file) {
  const f1 = path.join(__dirname, '../snapshots/ref3-a/shots', file);
  const f2 = path.join(__dirname, '../snapshots/b-cutover-quick2/shots', file);
  
  const img1 = PNG.sync.read(fs.readFileSync(f1));
  const img2 = PNG.sync.read(fs.readFileSync(f2));
  
  const minW = Math.min(img1.width, img2.width);
  const minH = Math.min(img1.height, img2.height);
  
  const c1 = crop(img1, minW, minH);
  const c2 = crop(img2, minW, minH);
  
  const diff = new PNG({ width: minW, height: minH });
  pixelmatch(c1.data, c2.data, diff.data, minW, minH, {
    threshold: 0.1,
    includeAA: false
  });
  
  const outPath = path.join(__dirname, '../snapshots', 'diff_' + file);
  fs.writeFileSync(outPath, PNG.sync.write(diff));
  console.log("Saved diff image to:", outPath);
}

generateDiff('category-erp@desktop.png');
