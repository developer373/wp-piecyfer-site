const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectMobileFull() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);

  await page.addStyleTag({
    content: `
      /* Desktop & Large Screens */
      @media (min-width: 1025px) {
        .elementor-element-062c684 {
          padding-bottom: 250px !important;
        }
        .elementor-element-9a128b1 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 35px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 80px !important;
          z-index: 3 !important;
          box-shadow: none !important;
        }
        .elementor-element-73afe75 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 125px !important;
          z-index: 4 !important;
          box-shadow: none !important;
        }
      }

      /* Tablet Screens (768px - 1024px) */
      @media (min-width: 768px) and (max-width: 1024px) {
        .elementor-element-062c684 {
          padding-bottom: 200px !important;
        }
        .elementor-element-9a128b1 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 30px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 70px !important;
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

      /* Mobile Screens (< 768px) */
      @media (max-width: 767px) {
        .elementor-element-062c684 {
          padding-bottom: 20px !important;
        }
        .elementor-element-9a128b1,
        .elementor-element-69babdd,
        .elementor-element-73afe75 {
          position: relative !important;
          top: auto !important;
          z-index: auto !important;
          box-shadow: none !important;
          margin-bottom: 25px !important;
        }
      }
    `
  });

  const cardsContainer = await page.$('.elementor-element-062c684');
  if (cardsContainer) {
    const shotPath = path.resolve(__dirname, '../snapshots/mobile_cards_full.png');
    await cardsContainer.screenshot({ path: shotPath });
    console.log('Saved mobile cards full screenshot to:', shotPath);
  }

  await browser.close();
}

inspectMobileFull().catch(console.error);
