const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectHoverEffects() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  async function check(url, name) {
    const page = await context.newPage();
    await page.goto(url, { waitUntil: 'networkidle' });

    // Scroll to Our Products / Projects
    await page.evaluate(() => {
      const el = document.querySelector('.projects');
      if (el) el.scrollIntoView();
    });

    await page.waitForTimeout(500);

    // Hover over the first project card
    const card = await page.$('.projects');
    if (card) {
      await card.hover();
      await page.waitForTimeout(300);
    }

    const hoverData = await page.evaluate(() => {
      const card = document.querySelector('.projects');
      const btn = card?.querySelector('.elementor-button');
      const img = card?.querySelector('img');
      return {
        cardTransform: card ? window.getComputedStyle(card).transform : null,
        btnBg: btn ? window.getComputedStyle(btn).backgroundColor : null,
        btnColor: btn ? window.getComputedStyle(btn).color : null,
        imgTransform: img ? window.getComputedStyle(img).transform : null,
      };
    });

    console.log(`[${name}] Hover Data:`, JSON.stringify(hoverData, null, 2));
    await page.close();
  }

  await check('https://www.piecyfer.com/', 'LIVE');
  await check('http://localhost/piecyfer/', 'LOCAL');

  await browser.close();
}

inspectHoverEffects().catch(console.error);
