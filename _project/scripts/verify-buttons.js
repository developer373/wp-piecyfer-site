const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  
  const buttons = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('a.elementor-button, button.elementor-button')).map(b => ({
      text: b.innerText,
      html: b.outerHTML,
      rect: { w: b.getBoundingClientRect().width, h: b.getBoundingClientRect().height }
    }));
  });
  console.log('Live buttons after fix count:', buttons.length);
  buttons.forEach((b, i) => {
    console.log(`[${i}] "${b.text}" (${Math.round(b.rect.w)}x${Math.round(b.rect.h)})`);
  });
  await browser.close();
})();
