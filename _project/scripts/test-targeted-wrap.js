const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testTargetedWrap() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);

  // Target ONLY the direct column wrap:
  // Card 1: top: 15px
  // Card 2: top: 55px (40px visible tab of Card 1)
  // Card 3: top: 95px (40px visible tab of Card 2)
  await page.addStyleTag({
    content: `
      @media (min-width: 1025px) {
        .elementor-element-56ec363 > .elementor-widget-wrap {
          padding-bottom: 500px !important;
        }
        .elementor-element-9a128b1 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 15px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 55px !important;
          z-index: 3 !important;
          box-shadow: none !important;
        }
        .elementor-element-73afe75 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 95px !important;
          z-index: 4 !important;
          box-shadow: none !important;
        }
      }
    `
  });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  // Trace when c1, c2, c3 are in view
  for (let offset = 400; offset <= 2000; offset += 300) {
    await page.evaluate((y) => window.scrollTo(0, y), baseTop + offset);
    await page.waitForTimeout(100);

    const info = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');

      return {
        scrollY: window.scrollY,
        c1_top: Math.round(c1.getBoundingClientRect().top),
        c2_top: Math.round(c2.getBoundingClientRect().top),
        c3_top: Math.round(c3.getBoundingClientRect().top),
        tab1_vis: Math.round(c2.getBoundingClientRect().top - c1.getBoundingClientRect().top),
        tab2_vis: Math.round(c3.getBoundingClientRect().top - c2.getBoundingClientRect().top)
      };
    });

    console.log(`offset +${offset}:`, info);
  }

  // Scroll to exact position when Card 3 is pinned and take screenshot
  await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1550);
  await page.waitForTimeout(300);
  const shotPath = path.resolve(__dirname, '../snapshots/test_3tabs_targeted.png');
  await page.screenshot({ path: shotPath });
  console.log('Saved targeted 3 tabs screenshot to:', shotPath);

  await browser.close();
}

testTargetedWrap().catch(console.error);
