const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function verifyLocalTeamPage() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

  await page.goto('http://localhost/piecyfer/our-team/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  // Scroll to Founding Partners section
  const section = page.locator('.elementor-element-1b88ffa');
  await section.scrollIntoViewIfNeeded();
  await page.waitForTimeout(300);

  await page.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_our_team_default.png' });

  // Hover over the third card (Zeeshan Ahmad)
  const card3 = page.locator('.elementor-element-229ea78 .single-item .item');
  await card3.hover();
  await page.waitForTimeout(400);

  await page.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_our_team_hover.png' });

  const status = await page.evaluate(() => {
    const imgs = Array.from(document.querySelectorAll('.thumb-dp')).map(img => ({
      src: img.src,
      naturalWidth: img.naturalWidth,
      naturalHeight: img.naturalHeight,
      complete: img.complete
    }));
    return imgs;
  });

  console.log('PARTNER IMAGES STATUS:\n', JSON.stringify(status, null, 2));

  await browser.close();
}

verifyLocalTeamPage().catch(console.error);
