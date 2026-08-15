const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testMarginFix() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);

  await page.addStyleTag({
    content: `
      /* Fix Card 1 internal 50px margin offset so all cards start from top: 0 */
      .elementor-element-eb5cb1c > .elementor-widget-container {
        margin-top: 0px !important;
      }
      .elementor-element-3ba3561 > .elementor-widget-container {
        margin-top: 0px !important;
      }

      /* Desktop & Large Screens */
      @media (min-width: 1025px) {
        .elementor-element-062c684 {
          padding-bottom: 0px !important;
          margin-bottom: 64px !important;
        }
        .elementor-element-56ec363 > .elementor-widget-wrap {
          padding-bottom: 0px !important;
        }
        .elementor-element-9a128b1 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 65px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 105px !important;
          z-index: 3 !important;
          box-shadow: none !important;
        }
        .elementor-element-73afe75 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 145px !important;
          z-index: 4 !important;
          box-shadow: none !important;
        }
      }
    `
  });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  // Scroll to stack position
  await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1450);
  await page.waitForTimeout(400);

  const shotPath = path.resolve(__dirname, '../snapshots/test_real_margin_fix.png');
  await page.screenshot({ path: shotPath });
  console.log('Saved real margin fix screenshot to:', shotPath);

  const rects = await page.evaluate(() => {
    const c1_box = document.querySelector('.elementor-element-23575fc').getBoundingClientRect();
    const c2_box = document.querySelector('.elementor-element-eafec62').getBoundingClientRect();
    const c3_box = document.querySelector('.elementor-element-e40cd70').getBoundingClientRect();
    return {
      c1_box_top: c1_box.top,
      c2_box_top: c2_box.top,
      c3_box_top: c3_box.top,
      step1: c2_box.top - c1_box.top,
      step2: c3_box.top - c2_box.top,
    };
  });

  console.log('BOX RECTS:', JSON.stringify(rects, null, 2));

  await browser.close();
}

testMarginFix().catch(console.error);
