const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function traceLive() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);

  const rootInfo = await page.evaluate(() => {
    const root = document.querySelector('.elementor-element-062c684');
    const header = document.querySelector('header, .main-header');
    return {
      rootTop: root.getBoundingClientRect().top + window.scrollY,
      rootHeight: root.offsetHeight,
      rootPaddingBottom: window.getComputedStyle(root).paddingBottom,
      headerHeight: header ? header.offsetHeight : 0,
      headerSticky: header ? window.getComputedStyle(header).position : 'none'
    };
  });

  console.log('LIVE ROOT INFO:\n', JSON.stringify(rootInfo, null, 2));

  // Trace live scroll
  console.log('\n--- LIVE SCROLL TRACE ---');
  for (let offset = 0; offset <= 2600; offset += 200) {
    await page.evaluate((y) => window.scrollTo(0, y), rootInfo.rootTop + offset);
    await page.waitForTimeout(100);

    const positions = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');
      
      const r1 = c1?.getBoundingClientRect();
      const r2 = c2?.getBoundingClientRect();
      const r3 = c3?.getBoundingClientRect();

      return {
        scrollY: window.scrollY,
        c1_top: Math.round(r1?.top || 0),
        c2_top: Math.round(r2?.top || 0),
        c3_top: Math.round(r3?.top || 0),
        diff_1_2: Math.round((r2?.top || 0) - (r1?.top || 0)),
        diff_2_3: Math.round((r3?.top || 0) - (r2?.top || 0)),
      };
    });

    console.log(`Live offset +${offset} (scrollY=${positions.scrollY}):`, positions);
  }

  await browser.close();
}

traceLive().catch(console.error);
