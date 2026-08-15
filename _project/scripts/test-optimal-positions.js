const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testOptimalCardPositions() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);

  // Set Card 1: top: 65px, Card 2: top: 105px, Card 3: top: 145px
  await page.addStyleTag({
    content: `
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

  // Scroll to when all 3 cards are stacked
  await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1450);
  await page.waitForTimeout(400);

  const shotPath = path.resolve(__dirname, '../snapshots/test_optimal_deck_stack.png');
  await page.screenshot({ path: shotPath });
  console.log('Saved optimal deck stack screenshot to:', shotPath);

  await browser.close();
}

testOptimalCardPositions().catch(console.error);
