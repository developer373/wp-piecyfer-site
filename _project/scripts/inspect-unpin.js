const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectUnpin() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);

  await page.addStyleTag({
    content: `
      @media (min-width: 1025px) {
        .elementor-element-062c684 {
          padding-bottom: 800px !important;
        }
        .elementor-element-9a128b1 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 20px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 65px !important;
          z-index: 3 !important;
          box-shadow: none !important;
        }
        .elementor-element-73afe75 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 110px !important;
          z-index: 4 !important;
          box-shadow: none !important;
        }
      }
    `
  });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  // Let's check from offset 1200 to 2200 every 50px
  for (let offset = 1200; offset <= 2200; offset += 100) {
    await page.evaluate((y) => window.scrollTo(0, y), baseTop + offset);
    await page.waitForTimeout(50);

    const info = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');
      const root = document.querySelector('.elementor-element-062c684');
      const col = document.querySelector('.elementor-element-56ec363');

      return {
        scrollY: window.scrollY,
        rootBottom: Math.round(root.getBoundingClientRect().bottom),
        colBottom: Math.round(col.getBoundingClientRect().bottom),
        c1_top: Math.round(c1.getBoundingClientRect().top),
        c2_top: Math.round(c2.getBoundingClientRect().top),
        c3_top: Math.round(c3.getBoundingClientRect().top),
        tab1_vis: Math.round(c2.getBoundingClientRect().top - c1.getBoundingClientRect().top),
        tab2_vis: Math.round(c3.getBoundingClientRect().top - c2.getBoundingClientRect().top)
      };
    });

    console.log(`offset +${offset}:`, info);
  }

  await browser.close();
}

inspectUnpin().catch(console.error);
