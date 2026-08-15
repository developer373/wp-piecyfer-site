const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLiveDetails() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  // 1. Inspect scroll to top
  const stt = await page.evaluate(() => {
    const el = document.querySelector('#scroll-to-top, .scroll-to-top, [id*="scroll"]');
    if (!el) return null;
    const computed = window.getComputedStyle(el);
    return {
      id: el.id,
      classes: el.className,
      position: computed.position,
      left: computed.left,
      right: computed.right,
      bottom: computed.bottom,
      top: computed.top,
      width: computed.width,
      height: computed.height,
      backgroundColor: computed.backgroundColor,
      borderRadius: computed.borderRadius,
      zIndex: computed.zIndex,
      transform: computed.transform
    };
  });

  console.log('LIVE #SCROLL-TO-TOP STYLES:', JSON.stringify(stt, null, 2));

  // 2. Inspect the 3 cards and their parent containers
  const cardsInfo = await page.evaluate(() => {
    // Scroll down to the cards
    const card1 = document.querySelector('.elementor-element-9a128b1');
    const card2 = document.querySelector('.elementor-element-69babdd');
    const card3 = document.querySelector('.elementor-element-73afe75');
    const parent = card1 ? card1.closest('.elementor-top-section') : null;

    return {
      parentClass: parent ? parent.className : null,
      parentDataSettings: parent ? parent.getAttribute('data-settings') : null,
      parentComputed: parent ? {
        height: window.getComputedStyle(parent).height,
        padding: window.getComputedStyle(parent).padding,
        margin: window.getComputedStyle(parent).margin
      } : null,
      card1Computed: card1 ? {
        classes: card1.className,
        dataSettings: card1.getAttribute('data-settings'),
        position: window.getComputedStyle(card1).position,
        top: window.getComputedStyle(card1).top,
        zIndex: window.getComputedStyle(card1).zIndex,
        height: window.getComputedStyle(card1).height,
        margin: window.getComputedStyle(card1).margin,
        padding: window.getComputedStyle(card1).padding
      } : null,
      card2Computed: card2 ? {
        classes: card2.className,
        dataSettings: card2.getAttribute('data-settings'),
        position: window.getComputedStyle(card2).position,
        top: window.getComputedStyle(card2).top,
        zIndex: window.getComputedStyle(card2).zIndex,
        height: window.getComputedStyle(card2).height,
        margin: window.getComputedStyle(card2).margin,
        padding: window.getComputedStyle(card2).padding
      } : null,
      card3Computed: card3 ? {
        classes: card3.className,
        dataSettings: card3.getAttribute('data-settings'),
        position: window.getComputedStyle(card3).position,
        top: window.getComputedStyle(card3).top,
        zIndex: window.getComputedStyle(card3).zIndex,
        height: window.getComputedStyle(card3).height,
        margin: window.getComputedStyle(card3).margin,
        padding: window.getComputedStyle(card3).padding
      } : null,
    };
  });

  console.log('\nLIVE CARDS & PARENT INFO:', JSON.stringify(cardsInfo, null, 2));

  await browser.close();
}

inspectLiveDetails().catch(console.error);
