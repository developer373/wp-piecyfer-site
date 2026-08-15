const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testColumnPadding() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);

  // Apply padding to the actual column / widget wrap
  // Offsets:
  // Card 1: top: 15px
  // Card 2: top: 55px (40px step)
  // Card 3: top: 95px (40px step)
  await page.addStyleTag({
    content: `
      @media (min-width: 1025px) {
        .elementor-element-062c684 .elementor-widget-wrap {
          padding-bottom: 600px !important;
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

  for (let offset = 1200; offset <= 2400; offset += 200) {
    await page.evaluate((y) => window.scrollTo(0, y), baseTop + offset);
    await page.waitForTimeout(50);

    const info = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');
      const col = document.querySelector('.elementor-element-56ec363');

      return {
        scrollY: window.scrollY,
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

  // Take screenshot at full stack (offset 1800)
  await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1800);
  await page.waitForTimeout(300);
  const shotPath = path.resolve(__dirname, '../snapshots/test_perfect_3tabs_pinned.png');
  await page.screenshot({ path: shotPath });
  console.log('Saved perfect 3 tabs screenshot to:', shotPath);

  await browser.close();
}

testColumnPadding().catch(console.error);
