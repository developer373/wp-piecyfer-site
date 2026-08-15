const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLiveSticky() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  const stickyElements = await page.evaluate(() => {
    const all = Array.from(document.querySelectorAll('*'));
    const sticky = [];
    for (const el of all) {
      const ds = el.getAttribute('data-settings');
      if (ds && (ds.includes('sticky') || ds.includes('sticky_parent'))) {
        sticky.push({
          tag: el.tagName,
          classes: el.className,
          id: el.id,
          dataSettings: JSON.parse(ds)
        });
      }
    }
    return sticky;
  });

  console.log('LIVE STICKY ELEMENTS FOUND:', JSON.stringify(stickyElements, null, 2));
  await browser.close();
}

inspectLiveSticky().catch(console.error);
