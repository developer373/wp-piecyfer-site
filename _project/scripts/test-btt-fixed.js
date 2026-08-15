const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testBttFixed() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  await page.addStyleTag({
    content: `
      #scroll-to-top { display: none !important; }
      .elementor-widget-elementskit-back-to-top.elementor-fixed,
      .elementor-element-9d03438 {
        width: auto !important;
        left: 30px !important;
        right: auto !important;
        bottom: 30px !important;
        top: auto !important;
        z-index: 9999 !important;
      }
      .elementor-widget-elementskit-back-to-top .ekit-btt__button {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 45px !important;
        height: 45px !important;
        background-color: #38b6ab !important;
        border-radius: 50% !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15) !important;
      }
      .elementor-widget-elementskit-back-to-top .ekit-btt__button svg {
        fill: #ffffff !important;
        width: 18px !important;
        height: 18px !important;
      }
    `
  });

  await page.evaluate(() => window.scrollTo(0, 3000));
  await page.waitForTimeout(300);

  const shot = path.resolve(__dirname, '../snapshots/fixed_btt_left_perfect.png');
  await page.screenshot({ path: shot });
  console.log('Saved fixed BTT screenshot to:', shot);

  const rect = await page.evaluate(() => {
    const btn = document.querySelector('.ekit-btt__button');
    return btn ? btn.getBoundingClientRect() : null;
  });

  console.log('Button Bounding Rect:', rect);
  await browser.close();
}

testBttFixed().catch(console.error);
