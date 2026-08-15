const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectInnerOffsets() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1450);
  await page.waitForTimeout(300);

  const offsets = await page.evaluate(() => {
    const c1_out = document.querySelector('.elementor-element-9a128b1');
    const c1_in = document.querySelector('.elementor-element-23575fc');
    const c2_out = document.querySelector('.elementor-element-69babdd');
    const c2_in = document.querySelector('.elementor-element-eafec62');
    const c3_out = document.querySelector('.elementor-element-73afe75');
    const c3_in = document.querySelector('.elementor-element-e40cd70');

    return {
      c1_out_rect: c1_out?.getBoundingClientRect(),
      c1_in_rect: c1_in?.getBoundingClientRect(),
      c2_out_rect: c2_out?.getBoundingClientRect(),
      c2_in_rect: c2_in?.getBoundingClientRect(),
      c3_out_rect: c3_out?.getBoundingClientRect(),
      c3_in_rect: c3_in?.getBoundingClientRect(),
    };
  });

  console.log('INNER OFFSETS:\n', JSON.stringify(offsets, null, 2));

  await browser.close();
}

inspectInnerOffsets().catch(console.error);
