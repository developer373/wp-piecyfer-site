const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectEkitBackToTop() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const el = document.querySelector('.elementor-widget-elementskit-back-to-top, .backToTopBtn');
    if (!el) return null;
    const btn = el.querySelector('a, button, div, span, i, svg');
    const computed = window.getComputedStyle(el);
    const btnComputed = btn ? window.getComputedStyle(btn) : null;
    return {
      classes: el.className,
      html: el.innerHTML.slice(0, 300),
      elRect: el.getBoundingClientRect(),
      btnRect: btn ? btn.getBoundingClientRect() : null,
      btnComputed: btnComputed ? {
        position: btnComputed.position,
        left: btnComputed.left,
        right: btnComputed.right,
        bottom: btnComputed.bottom,
        display: btnComputed.display,
        bg: btnComputed.backgroundColor
      } : null
    };
  });

  console.log('LIVE ELEMENTS KIT BACK TO TOP:', JSON.stringify(data, null, 2));
  await browser.close();
}

inspectEkitBackToTop().catch(console.error);
