const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');
const path = require('path');
const { PNG } = require(path.join(__dirname, '../pixel-tool/node_modules/pngjs'));

async function inspect404Diff() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });
  const shotBuffer = await page.screenshot({ fullPage: true });
  await browser.close();

  const f1 = path.join(__dirname, '../snapshots/ref3-a/shots/this-url-does-not-exist-404-test@desktop.png');
  const img1 = PNG.sync.read(fs.readFileSync(f1));
  const img2 = PNG.sync.read(shotBuffer);

  const minH = Math.min(img1.height, img2.height);
  const rowDiffs = [];
  for (let y = 0; y < minH; y++) {
    let diffCount = 0;
    for (let x = 0; x < 1920; x++) {
      const idx = (1920 * y + x) << 2;
      const d = Math.abs(img1.data[idx] - img2.data[idx]) +
                Math.abs(img1.data[idx+1] - img2.data[idx+1]) +
                Math.abs(img1.data[idx+2] - img2.data[idx+2]);
      if (d > 30) diffCount++;
    }
    if (diffCount > 5) {
      rowDiffs.push({ y, diffCount });
    }
  }

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
  console.log("Current diff clusters on 404 test page:");
  for (const c of clusters) {
    console.log(`  - Y ${c.startY} to ${c.endY} (height: ${c.endY - c.startY + 1}px, diff pixels: ${c.count})`);
  }
}

inspect404Diff().catch(console.error);
