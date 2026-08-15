const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLiveStackingDetails() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  // Let's scroll to the exact stacking area and inspect every single DOM node inside .elementor-element-062c684
  const details = await page.evaluate(() => {
    const root = document.querySelector('.elementor-element-062c684');
    if (!root) return null;

    const sections = Array.from(root.querySelectorAll('.elementor-inner-section, .elementor-section, .projects, .elementor-widget-template'));
    
    return sections.map(el => {
      const cs = window.getComputedStyle(el);
      return {
        tag: el.tagName,
        classes: el.className,
        id: el.id,
        dataset: el.dataset,
        bg: cs.backgroundColor,
        bgImage: cs.backgroundImage,
        boxShadow: cs.boxShadow,
        borderRadius: cs.borderRadius,
        position: cs.position,
        top: cs.top,
        zIndex: cs.zIndex,
        padding: cs.padding,
        margin: cs.margin,
        overflow: cs.overflow
      };
    });
  });

  console.log('LIVE DOM HIERARCHY DETAILS:\n', JSON.stringify(details, null, 2));

  // Now let's scroll through the section in small steps on Live and capture the exact sticky visual
  // Find top of .elementor-element-062c684
  const topY = await page.evaluate(() => {
    const r = document.querySelector('.elementor-element-062c684').getBoundingClientRect();
    return r.top + window.scrollY;
  });

  console.log('Section top on live:', topY);

  // Scroll down step by step:
  // Step 1: When card 1 (Email Genius) pins
  // Step 2: When card 2 (TMS) stacks on top of card 1
  // Step 3: When card 3 (OnlineDoc) stacks on top of card 2
  for (let offset of [0, 600, 1200, 1500, 1800, 2100]) {
    await page.evaluate((y) => window.scrollTo(0, y), topY + offset);
    await page.waitForTimeout(300);
    
    const cardTops = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');
      return {
        scrollY: window.scrollY,
        c1: c1?.getBoundingClientRect().top,
        c2: c2?.getBoundingClientRect().top,
        c3: c3?.getBoundingClientRect().top,
      };
    });

    console.log(`Live scroll offset ${offset}:`, cardTops);
    await page.screenshot({ path: path.resolve(__dirname, `../snapshots/live_step_${offset}.png`) });
  }

  await browser.close();
}

inspectLiveStackingDetails().catch(console.error);
