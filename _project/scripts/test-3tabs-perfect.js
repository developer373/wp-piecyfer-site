const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testPerfect3Tabs() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);

  // Apply refined CSS
  // Card 1: top: 20px
  // Card 2: top: 65px (45px step)
  // Card 3: top: 110px (45px step)
  // Large padding-bottom on container so cards never collapse or cover each other
  await page.addStyleTag({
    content: `
      @media (min-width: 1025px) {
        .elementor-element-062c684 {
          padding-bottom: 600px !important;
        }
        .elementor-element-9a128b1 {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 20px !important;
          z-index: 2 !important;
          box-shadow: none !important;
        }
        .elementor-element-69babdd {
          position: -webkit-sticky !important;
          position: sticky !important;
          top: 65px !important;
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
    `
  });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  // Test at 3 key scroll positions:
  // Position 1: Only Card 1 pinned
  // Position 2: Card 2 stacked on Card 1 (both tabs visible)
  // Position 3: Card 3 stacked on Card 2 on Card 1 (all 3 tabs clearly visible)
  // Position 4: Scrolled further down inside the section
  
  const testPoints = [
    { name: 'step1_card1_only', offset: 400 },
    { name: 'step2_card2_stacked', offset: 1000 },
    { name: 'step3_card3_all_3_tabs', offset: 1600 },
    { name: 'step4_card3_scrolled_further', offset: 1900 }
  ];

  for (const pt of testPoints) {
    await page.evaluate((y) => window.scrollTo(0, y), baseTop + pt.offset);
    await page.waitForTimeout(200);

    const metrics = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');
      const r1 = c1?.getBoundingClientRect();
      const r2 = c2?.getBoundingClientRect();
      const r3 = c3?.getBoundingClientRect();
      return {
        c1_top: Math.round(r1?.top || 0),
        c2_top: Math.round(r2?.top || 0),
        c3_top: Math.round(r3?.top || 0),
        tab1_visible_height: Math.round((r2?.top || 0) - (r1?.top || 0)),
        tab2_visible_height: Math.round((r3?.top || 0) - (r2?.top || 0)),
      };
    });

    console.log(`[${pt.name}] metrics:`, metrics);
    const shotPath = path.resolve(__dirname, `../snapshots/test_3tabs_${pt.name}.png`);
    await page.screenshot({ path: shotPath });
  }

  await browser.close();
}

testPerfect3Tabs().catch(console.error);
