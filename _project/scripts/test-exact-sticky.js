const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testExactSticky() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Inject exact CSS for the 3 product cards
  await page.addStyleTag({
    content: `
      .elementor-element-9a128b1 {
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 80px !important;
        z-index: 2 !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
      }
      .elementor-element-69babdd {
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 130px !important;
        z-index: 3 !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      }
      .elementor-element-73afe75 {
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 180px !important;
        z-index: 4 !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.10);
      }
    `
  });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  console.log(`Base scrollY: ${baseTop}`);

  // Test scrolling progression
  const scrollSteps = [
    { name: 'Approach', offset: 0 },
    { name: 'Card 1 Sticking (Email Genius)', offset: 300 },
    { name: 'Card 2 Stacking Over Card 1 (TMS)', offset: 900 },
    { name: 'Card 3 Stacking Over Card 2 (OnlineDoc)', offset: 1500 },
    { name: 'Scroll Past', offset: 2200 },
  ];

  for (const step of scrollSteps) {
    const sy = baseTop - 50 + step.offset;
    await page.evaluate((y) => window.scrollTo(0, y), sy);
    await page.waitForTimeout(100);

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

    console.log(`\n[${step.name}] scrollY: ${sy.toFixed(0)}`);
    console.log('  Card 1 (Email Genius):', positions.card1);
    console.log('  Card 2 (TMS):         ', positions.card2);
    console.log('  Card 3 (OnlineDoc):   ', positions.card3);
  }

  await browser.close();
}

testExactSticky().catch(console.error);
