const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function captureStackingDemo() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Add the CSS
  await page.addStyleTag({
    content: `
      .elementor-element-062c684 .elementor-element-9a128b1 {
        position: -webkit-sticky;
        position: sticky;
        top: 100px;
        z-index: 2;
      }
      .elementor-element-062c684 .elementor-element-449e7cf {
        position: -webkit-sticky;
        position: sticky;
        top: 130px;
        z-index: 3;
      }
      .elementor-element-062c684 .elementor-element-d31e9c2 {
        position: -webkit-sticky;
        position: sticky;
        top: 160px;
        z-index: 4;
      }
    `
  });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  // 1. Scroll to Card 1
  await page.evaluate((y) => window.scrollTo(0, y + 200), baseTop);
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.resolve(__dirname, '../snapshots/stacking_1_email_genius.png') });

  // 2. Scroll to Card 2 overlap
  await page.evaluate((y) => window.scrollTo(0, y + 800), baseTop);
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.resolve(__dirname, '../snapshots/stacking_2_tms_overlap.png') });

  // 3. Scroll to Card 3 overlap
  await page.evaluate((y) => window.scrollTo(0, y + 1400), baseTop);
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.resolve(__dirname, '../snapshots/stacking_3_onlinedoc_overlap.png') });

  console.log('Screenshots captured to _project/snapshots/stacking_*.png');
  await browser.close();
}

captureStackingDemo().catch(console.error);
