const path = require('path');
const fs = require('fs');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function compareVisuals() {
  const browser = await chromium.launch({ headless: true });
  
  // Create pages for live and local with exact same viewport
  const livePage = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const localPage = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  await livePage.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });
  await localPage.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Let's get the exact scroll position on Live where OnlineDoc is stacked nicely under the first 2 tabs
  // Let's scroll incrementally on Live and find the keyframe where all 3 cards are stacked
  const liveCardMetrics = await livePage.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');
    const header = document.querySelector('.main-header, header, .elementor-location-header');

    return {
      c1: c1 ? c1.getBoundingClientRect() : null,
      c2: c2 ? c2.getBoundingClientRect() : null,
      c3: c3 ? c3.getBoundingClientRect() : null,
      header: header ? header.getBoundingClientRect() : null,
    };
  });

  console.log('Initial Card Metrics Live:', liveCardMetrics);

  // Scroll to when card 3 is pinned on Live
  // Find the exact scroll offset where Card 3 reaches its sticky position on Live
  const livePinnedScrollY = await livePage.evaluate(() => {
    const c3 = document.querySelector('.elementor-element-73afe75');
    // Scroll until c3 is at its minimum top or sticky offset
    let targetY = 0;
    for (let y = 3000; y < 7500; y += 50) {
      window.scrollTo(0, y);
      const rect3 = c3.getBoundingClientRect();
      const rect2 = document.querySelector('.elementor-element-69babdd').getBoundingClientRect();
      const rect1 = document.querySelector('.elementor-element-9a128b1').getBoundingClientRect();
      // On live, c1 top is ~50-80px, c2 is ~90-120px, c3 is ~130-160px
      if (rect3.top <= 200 && rect2.top < rect3.top && rect1.top < rect2.top) {
        targetY = y;
        break;
      }
    }
    return targetY;
  });

  console.log('Live Pinned ScrollY:', livePinnedScrollY);

  // Scroll live to that position and take a screenshot of the top card stack
  await livePage.evaluate((y) => window.scrollTo(0, y), livePinnedScrollY);
  await livePage.waitForTimeout(400);

  const liveState = await livePage.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    const s1 = window.getComputedStyle(c1);
    const s2 = window.getComputedStyle(c2);
    const s3 = window.getComputedStyle(c3);

    // Also check the inner section top section template cards
    const t1 = document.querySelector('.elementor-element-23575fc');
    const t2 = document.querySelector('.elementor-element-eafec62');
    const t3 = document.querySelector('.elementor-element-e40cd70');

    return {
      scrollY: window.scrollY,
      c1: { rect: c1.getBoundingClientRect(), top: s1.top, position: s1.position, zIndex: s1.zIndex, boxShadow: s1.boxShadow, bg: s1.backgroundColor },
      c2: { rect: c2.getBoundingClientRect(), top: s2.top, position: s2.position, zIndex: s2.zIndex, boxShadow: s2.boxShadow, bg: s2.backgroundColor },
      c3: { rect: c3.getBoundingClientRect(), top: s3.top, position: s3.position, zIndex: s3.zIndex, boxShadow: s3.boxShadow, bg: s3.backgroundColor },
      t1: t1 ? { rect: t1.getBoundingClientRect(), bg: window.getComputedStyle(t1).backgroundColor, radius: window.getComputedStyle(t1).borderRadius, shadow: window.getComputedStyle(t1).boxShadow } : null,
      t2: t2 ? { rect: t2.getBoundingClientRect(), bg: window.getComputedStyle(t2).backgroundColor, radius: window.getComputedStyle(t2).borderRadius, shadow: window.getComputedStyle(t2).boxShadow } : null,
      t3: t3 ? { rect: t3.getBoundingClientRect(), bg: window.getComputedStyle(t3).backgroundColor, radius: window.getComputedStyle(t3).borderRadius, shadow: window.getComputedStyle(t3).boxShadow } : null,
    };
  });

  console.log('LIVE FULL STACK STATE:', JSON.stringify(liveState, null, 2));

  await livePage.screenshot({ path: path.resolve(__dirname, '../snapshots/live_stack_perfect.png') });

  // Now let's check Local at the same position or local pinned position
  const localPinnedScrollY = await localPage.evaluate(() => {
    const c3 = document.querySelector('.elementor-element-73afe75');
    let targetY = 0;
    for (let y = 3000; y < 7500; y += 50) {
      window.scrollTo(0, y);
      const rect3 = c3.getBoundingClientRect();
      const rect2 = document.querySelector('.elementor-element-69babdd').getBoundingClientRect();
      const rect1 = document.querySelector('.elementor-element-9a128b1').getBoundingClientRect();
      if (rect3.top <= 220 && rect2.top < rect3.top && rect1.top < rect2.top) {
        targetY = y;
        break;
      }
    }
    return targetY;
  });

  await localPage.evaluate((y) => window.scrollTo(0, y), localPinnedScrollY);
  await localPage.waitForTimeout(400);

  const localState = await localPage.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    const s1 = window.getComputedStyle(c1);
    const s2 = window.getComputedStyle(c2);
    const s3 = window.getComputedStyle(c3);

    const t1 = document.querySelector('.elementor-element-23575fc');
    const t2 = document.querySelector('.elementor-element-eafec62');
    const t3 = document.querySelector('.elementor-element-e40cd70');

    return {
      scrollY: window.scrollY,
      c1: { rect: c1.getBoundingClientRect(), top: s1.top, position: s1.position, zIndex: s1.zIndex, boxShadow: s1.boxShadow, bg: s1.backgroundColor },
      c2: { rect: c2.getBoundingClientRect(), top: s2.top, position: s2.position, zIndex: s2.zIndex, boxShadow: s2.boxShadow, bg: s2.backgroundColor },
      c3: { rect: c3.getBoundingClientRect(), top: s3.top, position: s3.position, zIndex: s3.zIndex, boxShadow: s3.boxShadow, bg: s3.backgroundColor },
      t1: t1 ? { rect: t1.getBoundingClientRect(), bg: window.getComputedStyle(t1).backgroundColor, radius: window.getComputedStyle(t1).borderRadius, shadow: window.getComputedStyle(t1).boxShadow } : null,
      t2: t2 ? { rect: t2.getBoundingClientRect(), bg: window.getComputedStyle(t2).backgroundColor, radius: window.getComputedStyle(t2).borderRadius, shadow: window.getComputedStyle(t2).boxShadow } : null,
      t3: t3 ? { rect: t3.getBoundingClientRect(), bg: window.getComputedStyle(t3).backgroundColor, radius: window.getComputedStyle(t3).borderRadius, shadow: window.getComputedStyle(t3).boxShadow } : null,
    };
  });

  console.log('LOCAL FULL STACK STATE:', JSON.stringify(localState, null, 2));

  await localPage.screenshot({ path: path.resolve(__dirname, '../snapshots/local_stack_perfect.png') });

  await browser.close();
}

compareVisuals().catch(console.error);
