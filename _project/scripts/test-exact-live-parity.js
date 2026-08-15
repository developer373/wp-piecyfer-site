const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testExactLiveParity() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);

  // Apply EXACT live parity CSS:
  // Sticky Navbar: top: 0, height ~81px
  // Card 1: top: 90px (right under navbar)
  // Card 2: top: 135px (45px step)
  // Card 3: top: 180px (45px step)
  // Natural margin/padding on container (no giant void)
  await page.addStyleTag({
    content: `
      /* Ensure sticky navbar on scroll */
      .elementor-element-f4ae4d6.ekit-sticky {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 999 !important;
        background: #ffffff !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06) !important;
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
          top: 90px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 135px !important;
          z-index: 3 !important;
          box-shadow: none !important;
        }
        .elementor-element-73afe75 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 180px !important;
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
          top: 70px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 110px !important;
          z-index: 3 !important;
          box-shadow: none !important;
        }
        .elementor-element-73afe75 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 150px !important;
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

  // Scroll to exact position where OnlineDoc is pinned under the 2 tabs and navbar
  // Around baseTop + 1450
  await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1450);
  await page.waitForTimeout(400);

  const shotPath = path.resolve(__dirname, '../snapshots/test_perfect_exact_live_match.png');
  await page.screenshot({ path: shotPath });
  console.log('Saved exact match screenshot to:', shotPath);

  const metrics = await page.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');
    const nav = document.querySelector('.elementor-element-f4ae4d6');
    return {
      navTop: nav?.getBoundingClientRect().top,
      navHeight: nav?.getBoundingClientRect().height,
      c1_top: c1?.getBoundingClientRect().top,
      c2_top: c2?.getBoundingClientRect().top,
      c3_top: c3?.getBoundingClientRect().top,
      tab1_step: c2?.getBoundingClientRect().top - c1?.getBoundingClientRect().top,
      tab2_step: c3?.getBoundingClientRect().top - c2?.getBoundingClientRect().top,
    };
  });

  console.log('METRICS:', JSON.stringify(metrics, null, 2));

  await browser.close();
}

testExactLiveParity().catch(console.error);
