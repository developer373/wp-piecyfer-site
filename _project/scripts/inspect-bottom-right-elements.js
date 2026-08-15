const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectBottomRight() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  await page.evaluate(() => window.scrollTo(0, 5000));
  await page.waitForTimeout(300);

  const elements = await page.evaluate(() => {
    const all = Array.from(document.querySelectorAll('*'));
    const fixed = [];
    for (const el of all) {
      const pos = window.getComputedStyle(el).position;
      if (pos === 'fixed') {
        const rect = el.getBoundingClientRect();
        fixed.push({
          tag: el.tagName,
          id: el.id,
          classes: el.className,
          rect: {
            left: rect.left,
            right: rect.right,
            top: rect.top,
            bottom: rect.bottom,
            width: rect.width,
            height: rect.height
          },
          display: window.getComputedStyle(el).display,
          bg: window.getComputedStyle(el).backgroundColor
        });
      }
    }
    return fixed;
  });

  console.log('FIXED ELEMENTS ON PAGE:', JSON.stringify(elements, null, 2));
  await browser.close();
}

inspectBottomRight().catch(console.error);
