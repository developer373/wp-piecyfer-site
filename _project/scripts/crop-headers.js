const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));

const f1 = path.join(__dirname, '../snapshots/ref3-a/shots/this-url-does-not-exist-404-test@desktop.png');
const f2 = path.join(__dirname, '../snapshots/b-cutover-quick3/shots/this-url-does-not-exist-404-test@desktop.png');

const img1 = PNG.sync.read(fs.readFileSync(f1));
const img2 = PNG.sync.read(fs.readFileSync(f2));

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

const header1 = crop(img1, 1920, 160);
const header2 = crop(img2, 1920, 160);

fs.writeFileSync(path.join(__dirname, '../snapshots/header_ref.png'), PNG.sync.write(header1));
fs.writeFileSync(path.join(__dirname, '../snapshots/header_live.png'), PNG.sync.write(header2));
console.log("Saved header_ref.png and header_live.png");
