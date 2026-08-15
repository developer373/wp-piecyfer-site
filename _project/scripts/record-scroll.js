const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function recordScrollStepByStep() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);

  // Find the top position of the cards section
  const sectionTop = await page.evaluate(() => {
    const s = document.querySelector('.elementor-element-062c684');
    return s.getBoundingClientRect().top + window.scrollY;
  });

  console.log('Cards Section starts at scrollY =', sectionTop);

  // Take snapshots at:
  // 1. When Card 1 first pins
  // 2. When Card 2 comes and pins over Card 1
  // 3. When Card 3 comes and pins over Card 2

  const scrollPositions = [
    { name: 'step1_card1_approaching', scrollY: sectionTop - 100 },
    { name: 'step2_card1_pinned', scrollY: sectionTop + 200 },
    { name: 'step3_card2_coming_up', scrollY: sectionTop + 500 },
    { name: 'step4_card2_pinned_over_card1', scrollY: sectionTop + 900 },
    { name: 'step5_card3_coming_up', scrollY: sectionTop + 1300 },
    { name: 'step6_card3_pinned_over_card2', scrollY: sectionTop + 1600 },
    { name: 'step7_all_stacked', scrollY: sectionTop + 1900 },
  ];

  for (const pos of scrollPositions) {
    await page.evaluate((y) => window.scrollTo(0, y), pos.scrollY);
    await page.waitForTimeout(200);
    const shotPath = path.resolve(__dirname, `../snapshots/scroll_${pos.name}.png`);
    await page.screenshot({ path: shotPath });

    const cardPositions = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');
      return {
        scrollY: window.scrollY,
        c1: { top: c1?.getBoundingClientRect().top, bottom: c1?.getBoundingClientRect().bottom },
        c2: { top: c2?.getBoundingClientRect().top, bottom: c2?.getBoundingClientRect().bottom },
        c3: { top: c3?.getBoundingClientRect().top, bottom: c3?.getBoundingClientRect().bottom },
      };
    });

    console.log(`Scroll: ${pos.name} (${pos.scrollY}px):`, JSON.stringify(cardPositions));
  }

  await browser.close();
}

recordScrollStepByStep().catch(console.error);
