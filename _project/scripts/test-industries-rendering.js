const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testIndustriesRendering() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

  await page.goto('http://localhost/piecyfer/enterprise-software-development/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);

  const headingEl = page.locator('text=Industries We Serve');
  await headingEl.scrollIntoViewIfNeeded();
  await page.waitForTimeout(500);

  const shotPath = path.resolve(__dirname, '../snapshots/local_industries_fixed.png');
  await page.screenshot({ path: shotPath });
  console.log('Saved industries fixed screenshot to:', shotPath);

  // Inspect sizes of all 10 items
  const items = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('.elementskit-client-slider-item')).map(item => {
      const title = item.querySelector('.single-client')?.getAttribute('title');
      const img = item.querySelector('img');
      const rect = item.getBoundingClientRect();
      return {
        title,
        src: img?.src,
        naturalWidth: img?.naturalWidth,
        naturalHeight: img?.naturalHeight,
        boxWidth: rect.width,
        boxHeight: rect.height,
      };
    });
  });

  console.log('ALL 10 ITEMS:\n', JSON.stringify(items, null, 2));

  await browser.close();
}

testIndustriesRendering().catch(console.error);
