const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testOffsets() {
  const browser = await chromium.launch({ headless: true });
  
  // Test different top offset combinations on Local
  // Configuration A: top: 30px, 70px, 110px
  // Configuration B: top: 40px, 85px, 130px
  // Configuration C: top: 50px, 100px, 150px
  // Configuration D: top: 50px, 150px, 200px (Elementor original defaults)

  const configs = [
    { name: 'config_A_compact', top1: '30px', top2: '75px', top3: '120px' },
    { name: 'config_B_balanced', top1: '40px', top2: '90px', top3: '140px' },
    { name: 'config_C_live_match', top1: '50px', top2: '100px', top3: '150px' },
    { name: 'config_D_wider_tabs', top1: '50px', top2: '120px', top3: '190px' }
  ];

  for (const cfg of configs) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);

    // Inject clean CSS with NO box shadow on inner sections
    await page.addStyleTag({
      content: `
        @media (min-width: 768px) {
          .elementor-element-062c684 {
            padding-bottom: 250px !important;
          }
          .elementor-element-9a128b1 {
            position: -webkit-sticky !important;
            position: sticky !important;
            top: ${cfg.top1} !important;
            z-index: 2 !important;
            box-shadow: none !important;
          }
          .elementor-element-69babdd {
            position: -webkit-sticky !important;
            position: sticky !important;
            top: ${cfg.top2} !important;
            z-index: 3 !important;
            box-shadow: none !important;
          }
          .elementor-element-73afe75 {
            position: -webkit-sticky !important;
            position: sticky !important;
            top: ${cfg.top3} !important;
            z-index: 4 !important;
            box-shadow: none !important;
          }
        }
      `
    });

    // Scroll to position where Card 3 is pinned
    const baseTop = await page.evaluate(() => {
      return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
    });

    await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1450);
    await page.waitForTimeout(400);

    const shotPath = path.resolve(__dirname, `../snapshots/test_${cfg.name}.png`);
    await page.screenshot({ path: shotPath });
    console.log(`Saved ${cfg.name} to ${shotPath}`);

    await page.close();
  }

  await browser.close();
}

testOffsets().catch(console.error);
