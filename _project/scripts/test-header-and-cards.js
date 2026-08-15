const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testHeaderAndCards() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);

  await page.addStyleTag({
    content: `
      /* Sticky Header Navbar */
      .elementor-element-f4ae4d6 {
        position: sticky !important;
        top: 0 !important;
        z-index: 999 !important;
        background-color: #ffffff !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.04) !important;
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
          top: 85px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 130px !important;
          z-index: 3 !important;
          box-shadow: none !important;
        }
        .elementor-element-73afe75 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 175px !important;
          z-index: 4 !important;
          box-shadow: none !important;
        }
      }

      /* Tablet Screens */
      @media (min-width: 768px) and (max-width: 1024px) {
        .elementor-element-062c684 {
          padding-bottom: 0px !important;
          margin-bottom: 40px !important;
        }
        .elementor-element-56ec363 > .elementor-widget-wrap {
          padding-bottom: 0px !important;
        }
        .elementor-element-9a128b1 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 60px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 100px !important;
          z-index: 3 !important;
          box-shadow: none !important;
        }
        .elementor-element-73afe75 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 140px !important;
          z-index: 4 !important;
          box-shadow: none !important;
        }
      }

      /* Mobile Screens */
      @media (max-width: 767px) {
        .elementor-element-062c684 {
          padding-bottom: 0px !important;
          margin-bottom: 30px !important;
        }
        .elementor-element-56ec363 > .elementor-widget-wrap {
          padding-bottom: 0px !important;
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

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  // Scroll to OnlineDoc position
  await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1450);
  await page.waitForTimeout(400);

  const shotPath = path.resolve(__dirname, '../snapshots/test_final_pixel_match.png');
  await page.screenshot({ path: shotPath });
  console.log('Saved final pixel match screenshot to:', shotPath);

  await browser.close();
}

testHeaderAndCards().catch(console.error);
