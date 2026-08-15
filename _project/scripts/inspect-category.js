const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectCategory() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/category/erp/', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const widgets = document.querySelectorAll('.elementor-widget');
    const res = [];
    for (const w of widgets) {
      const r = w.getBoundingClientRect();
      res.push({
        class: w.className,
        rect: { top: r.top + window.scrollY, height: r.height, bottom: r.bottom + window.scrollY }
      });
    }
    return {
      scrollHeight: document.documentElement.scrollHeight,
      widgets: res
    };
  });

  console.log("CATEGORY ERP SCROLL HEIGHT:", data.scrollHeight);
  console.log("WIDGETS COUNT:", data.widgets.length);
  for (const w of data.widgets) {
    console.log(`  - top=${w.rect.top}, height=${w.rect.height}, class=${w.class.substring(0, 70)}`);
  }

  await browser.close();
}

inspectCategory().catch(console.error);
