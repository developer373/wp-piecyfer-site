const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testFinalLive() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/?ver=' + Date.now(), { waitUntil: 'networkidle' });

  // Scroll to stacking cards
  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  await page.evaluate((y) => window.scrollTo(0, y + 1450), baseTop);
  await page.waitForTimeout(400);

  const shot = path.resolve(__dirname, '../snapshots/final_perfect_cards_and_btt.png');
  await page.screenshot({ path: shot });
  console.log('Saved final screenshot to:', shot);

  await browser.close();
}

testFinalLive().catch(console.error);
