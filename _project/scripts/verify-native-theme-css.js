const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function verifyNativeThemeCss() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  // Navigate directly with cache bypassed
  await page.goto('http://localhost/piecyfer/?ver=' + Date.now(), { waitUntil: 'networkidle' });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  const scrollSteps = [
    { name: 'Card 1 (Email Genius) Sticking', offset: 300 },
    { name: 'Card 2 (TMS) Stacking Over Card 1', offset: 900 },
    { name: 'Card 3 (OnlineDoc) Stacking Over Card 2', offset: 1500 },
    { name: 'Scroll Past All Cards', offset: 2200 },
  ];

  for (const step of scrollSteps) {
    const sy = baseTop - 50 + step.offset;
    await page.evaluate((y) => window.scrollTo(0, y), sy);
    await page.waitForTimeout(150);

    const positions = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');

      return {
        card1: c1 ? { top: c1.getBoundingClientRect().top.toFixed(1), bottom: c1.getBoundingClientRect().bottom.toFixed(1) } : null,
        card2: c2 ? { top: c2.getBoundingClientRect().top.toFixed(1), bottom: c2.getBoundingClientRect().bottom.toFixed(1) } : null,
        card3: c3 ? { top: c3.getBoundingClientRect().top.toFixed(1), bottom: c3.getBoundingClientRect().bottom.toFixed(1) } : null,
      };
    });

    console.log(`[${step.name}] (scrollY: ${sy.toFixed(0)})`);
    console.log('  Card 1 (Email Genius):', positions.card1);
    console.log('  Card 2 (TMS):         ', positions.card2);
    console.log('  Card 3 (OnlineDoc):   ', positions.card3);
  }

  await browser.close();
}

verifyNativeThemeCss().catch(console.error);
