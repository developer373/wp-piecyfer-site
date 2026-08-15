const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function verifyAllDevices() {
  const browser = await chromium.launch({ headless: true });
  
  const devices = [
    { name: '1_desktop_1920', width: 1920, height: 1080, isMobile: false },
    { name: '2_desktop_1440', width: 1440, height: 900, isMobile: false },
    { name: '3_laptop_1024', width: 1024, height: 768, isMobile: false },
    { name: '4_tablet_768', width: 768, height: 1024, isMobile: false },
    { name: '5_mobile_375', width: 375, height: 812, isMobile: true }
  ];

  for (const dev of devices) {
    const page = await browser.newPage({ viewport: { width: dev.width, height: dev.height } });
    await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(400);

    // Add updated CSS
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
            padding-bottom: 40px !important;
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

    if (!dev.isMobile) {
      // Scroll to stacked OnlineDoc card
      const baseTop = await page.evaluate(() => {
        return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
      });
      await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1450);
      await page.waitForTimeout(300);
    } else {
      // On mobile, scroll to the OnlineDoc card so we can verify its clean layout
      await page.evaluate(() => {
        const onlinedoc = Array.from(document.querySelectorAll('h2, h3, h1')).find(el => el.textContent.includes('OnlineDoc'));
        if (onlinedoc) {
          onlinedoc.closest('.projects')?.scrollIntoView({ block: 'start' });
        }
      });
      await page.waitForTimeout(300);
    }

    const shotPath = path.resolve(__dirname, `../snapshots/verify_${dev.name}.png`);
    await page.screenshot({ path: shotPath });
    console.log(`Verified ${dev.name} -> ${shotPath}`);

    await page.close();
  }

  await browser.close();
}

verifyAllDevices().catch(console.error);
