const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function captureScrollSteps() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/?ver=' + Date.now(), { waitUntil: 'networkidle' });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  const steps = [
    { name: 'step1_card1_pinned', offset: 300 },
    { name: 'step2_card2_stacking', offset: 900 },
    { name: 'step3_card3_all_stacked', offset: 1600 },
  ];

  for (const s of steps) {
    await page.evaluate((y) => window.scrollTo(0, y), baseTop + s.offset);
    await page.waitForTimeout(200);
    const p = path.resolve(__dirname, `../snapshots/${s.name}.png`);
    await page.screenshot({ path: p });
    console.log(`Saved ${s.name} to:`, p);
  }

  await browser.close();
}

captureScrollSteps().catch(console.error);
