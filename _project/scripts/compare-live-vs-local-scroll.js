const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function compareScroll() {
  const browser = await chromium.launch({ headless: true });
  
  // 1. Live site
  const livePage = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await livePage.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  const liveBase = await livePage.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  console.log('=== LIVE SITE SCROLL BEHAVIOR ===');
  const scrollDeltas = [0, 200, 400, 600, 800, 1000, 1200, 1400, 1600, 1800, 2000];

  for (const d of scrollDeltas) {
    await livePage.evaluate((y) => window.scrollTo(0, y), liveBase + d);
    await livePage.waitForTimeout(50);

    const metrics = await livePage.evaluate(() => {
      const heading = document.querySelector('.elementor-element-1c18f69');
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');

      return {
        headingTop: heading ? heading.getBoundingClientRect().top.toFixed(0) : null,
        c1Top: c1 ? c1.getBoundingClientRect().top.toFixed(0) : null,
        c2Top: c2 ? c2.getBoundingClientRect().top.toFixed(0) : null,
        c3Top: c3 ? c3.getBoundingClientRect().top.toFixed(0) : null,
      };
    });

    console.log(`Delta +${d}px | Heading: ${metrics.headingTop}px | C1: ${metrics.c1Top}px | C2: ${metrics.c2Top}px | C3: ${metrics.c3Top}px`);
  }

  // 2. Local site
  const localPage = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await localPage.goto('http://localhost/piecyfer/?ver=' + Date.now(), { waitUntil: 'networkidle' });

  const localBase = await localPage.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  console.log('\n=== LOCAL SITE SCROLL BEHAVIOR ===');
  for (const d of scrollDeltas) {
    await localPage.evaluate((y) => window.scrollTo(0, y), localBase + d);
    await localPage.waitForTimeout(50);

    const metrics = await localPage.evaluate(() => {
      const heading = document.querySelector('.elementor-element-1c18f69');
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');

      return {
        headingTop: heading ? heading.getBoundingClientRect().top.toFixed(0) : null,
        c1Top: c1 ? c1.getBoundingClientRect().top.toFixed(0) : null,
        c2Top: c2 ? c2.getBoundingClientRect().top.toFixed(0) : null,
        c3Top: c3 ? c3.getBoundingClientRect().top.toFixed(0) : null,
      };
    });

    console.log(`Delta +${d}px | Heading: ${metrics.headingTop}px | C1: ${metrics.c1Top}px | C2: ${metrics.c2Top}px | C3: ${metrics.c3Top}px`);
  }

  await browser.close();
}

compareScroll().catch(console.error);
