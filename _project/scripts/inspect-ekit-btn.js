const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectEkitButton() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  const info = await page.evaluate(() => {
    const w = document.querySelector('.elementor-widget-elementskit-back-to-top');
    if (!w) return 'widget not found';

    const btn = w.querySelector('.ekit-btt__button, a, button');
    const computedW = window.getComputedStyle(w);
    const computedBtn = btn ? window.getComputedStyle(btn) : null;

    return {
      widgetHTML: w.outerHTML,
      widgetPosition: computedW.position,
      widgetLeft: computedW.left,
      widgetRight: computedW.right,
      btnPosition: computedBtn ? computedBtn.position : null,
      btnLeft: computedBtn ? computedBtn.left : null,
      btnRight: computedBtn ? computedBtn.right : null,
      btnTransform: computedBtn ? computedBtn.transform : null,
    };
  });

  console.log('EKIT WIDGET STRUCTURE & STYLES:', JSON.stringify(info, null, 2));
  await browser.close();
}

inspectEkitButton().catch(console.error);
