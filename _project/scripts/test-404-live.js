const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));

async function test404() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });
  const shotBuffer = await page.screenshot({ fullPage: true });
  await browser.close();

  const f1 = path.join(__dirname, '../snapshots/ref3-a/shots/this-url-does-not-exist-404-test@desktop.png');
  const img1 = PNG.sync.read(fs.readFileSync(f1));
  const img2 = PNG.sync.read(shotBuffer);

  console.log(`Ref image: ${img1.width}x${img1.height}`);
  console.log(`Live image: ${img2.width}x${img2.height}`);

  const minH = Math.min(img1.height, img2.height);
  let diffRows = 0;
  let totalDiffPixels = 0;

  for (let y = 0; y < minH; y++) {
    let rowDiff = 0;
    for (let x = 0; x < 1920; x++) {
      const idx = (1920 * y + x) << 2;
      const d = Math.abs(img1.data[idx] - img2.data[idx]) +
                Math.abs(img1.data[idx+1] - img2.data[idx+1]) +
                Math.abs(img1.data[idx+2] - img2.data[idx+2]);
      if (d > 30) {
        rowDiff++;
        totalDiffPixels++;
      }
    }
    if (rowDiff > 5) diffRows++;
  }

  console.log(`Total differing rows: ${diffRows} / ${minH}`);
  console.log(`Total diff pixels: ${totalDiffPixels}`);
}

test404().catch(console.error);
