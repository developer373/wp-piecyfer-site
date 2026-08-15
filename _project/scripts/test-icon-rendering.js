const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testIconRendering() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

  await page.goto('http://localhost/piecyfer/our-team/', { waitUntil: 'networkidle' });

  // Hover over first card
  const card1 = page.locator('.elementor-element-4655431 .single-item .item');
  await card1.hover();
  await page.waitForTimeout(400);

  await page.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_card1_hover.png' });

  const iconDetails = await page.evaluate(() => {
    const icon = document.querySelector('.team-icon');
    const a = icon.closest('a');
    return {
      iconRect: icon.getBoundingClientRect(),
      aRect: a.getBoundingClientRect(),
      fontLoaded: document.fonts.check('16px elementskit'),
      allFonts: Array.from(document.fonts).map(f => f.family),
    };
  });

  console.log('ICON DETAILS:', JSON.stringify(iconDetails, null, 2));

  await browser.close();
}

testIconRendering().catch(console.error);
