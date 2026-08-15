const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function captureFinalVerification() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/?ver=' + Date.now(), { waitUntil: 'networkidle' });

  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  // Scroll to full stack position
  await page.evaluate((y) => window.scrollTo(0, y + 1450), baseTop);
  await page.waitForTimeout(300);

  const snapshotPath = path.resolve(__dirname, '../snapshots/final_verification_stack.png');
  await page.screenshot({ path: snapshotPath });
  console.log('Final screenshot saved to:', snapshotPath);

  await browser.close();
}

captureFinalVerification().catch(console.error);
