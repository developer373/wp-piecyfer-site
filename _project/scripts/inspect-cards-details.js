const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectCardsDetails() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // 1. Check all parents of card 1 for overflow
  const overflowCheck = await page.evaluate(() => {
    const card = document.querySelector('.elementor-element-9a128b1');
    if (!card) return 'card not found';

    const chain = [];
    let curr = card.parentElement;
    while (curr && curr !== document.body) {
      const style = window.getComputedStyle(curr);
      chain.push({
        tag: curr.tagName,
        classes: curr.className,
        overflow: style.overflow,
        overflowX: style.overflowX,
        overflowY: style.overflowY,
        height: style.height
      });
      curr = curr.parentElement;
    }
    return chain;
  });

  console.log('PARENT OVERFLOW CHAIN:', JSON.stringify(overflowCheck, null, 2));

  // 2. Check inner elements inside card 1, 2, 3
  const cardsDOM = await page.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    return {
      c1: c1 ? { outerHTML: c1.className, style: window.getComputedStyle(c1).position } : null,
      c2: c2 ? { outerHTML: c2.className, style: window.getComputedStyle(c2).position } : null,
      c3: c3 ? { outerHTML: c3.className, style: window.getComputedStyle(c3).position } : null,
    };
  });

  console.log('CARDS DOM COMPUTED:', JSON.stringify(cardsDOM, null, 2));

  await browser.close();
}

inspectCardsDetails().catch(console.error);
