const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testPerfectStacking() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Add the refined CSS
  await page.addStyleTag({
    content: `
      /* Scroll to Top on the Left Side */
      #scroll-to-top {
        left: 25px !important;
        right: auto !important;
        bottom: 40px !important;
        width: 45px !important;
        height: 45px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background-color: #38b6ab !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50% !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15) !important;
        cursor: pointer !important;
        z-index: 9999 !important;
        opacity: 1 !important;
        transform: scale(1) !important;
      }
      #scroll-to-top svg, #scroll-to-top i {
        color: #fff !important;
        fill: #fff !important;
        width: 18px !important;
        height: 18px !important;
      }

      /* 3-Tier Stacking Cards */
      @media (min-width: 768px) {
        .elementor-element-062c684 {
          padding-bottom: 200px !important;
        }
        .elementor-element-9a128b1 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 60px !important;
          z-index: 2 !important;
          box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 100px !important;
          z-index: 3 !important;
          box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .elementor-element-73afe75 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 140px !important;
          z-index: 4 !important;
          box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
      }
    `
  });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  // Scroll to full stack position (Card 3 at top: 140px)
  const fullStackY = baseTop + 1450;
  await page.evaluate((y) => window.scrollTo(0, y), fullStackY);
  await page.waitForTimeout(300);

  const snapshotPath = path.resolve(__dirname, '../snapshots/user_stack_match.png');
  await page.screenshot({ path: snapshotPath });
  console.log('Snapshot taken at:', snapshotPath);

  const positions = await page.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');
    const stt = document.querySelector('#scroll-to-top');

    return {
      card1Top: c1?.getBoundingClientRect().top,
      card2Top: c2?.getBoundingClientRect().top,
      card3Top: c3?.getBoundingClientRect().top,
      sttLeft: stt?.getBoundingClientRect().left,
      sttBottom: window.innerHeight - stt?.getBoundingClientRect().bottom
    };
  });

  console.log('VERIFIED STACKING METRICS:', JSON.stringify(positions, null, 2));

  await browser.close();
}

testPerfectStacking().catch(console.error);
