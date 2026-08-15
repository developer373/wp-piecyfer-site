const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectStickyOnLocal() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  // Check what classes and styles exist on card 1, 2, 3 before and after scroll
  const initial = await page.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    return {
      c1: {
        classes: c1?.className,
        style: c1?.getAttribute('style'),
        dataSettings: c1?.getAttribute('data-settings'),
        computedTop: window.getComputedStyle(c1).top,
        computedPos: window.getComputedStyle(c1).position,
        computedZ: window.getComputedStyle(c1).zIndex,
      },
      c2: {
        classes: c2?.className,
        style: c2?.getAttribute('style'),
        dataSettings: c2?.getAttribute('data-settings'),
        computedTop: window.getComputedStyle(c2).top,
        computedPos: window.getComputedStyle(c2).position,
        computedZ: window.getComputedStyle(c2).zIndex,
      },
      c3: {
        classes: c3?.className,
        style: c3?.getAttribute('style'),
        dataSettings: c3?.getAttribute('data-settings'),
        computedTop: window.getComputedStyle(c3).top,
        computedPos: window.getComputedStyle(c3).position,
        computedZ: window.getComputedStyle(c3).zIndex,
      },
    };
  });

  console.log('INITIAL CARDS STATE:\n', JSON.stringify(initial, null, 2));

  // Now scroll to baseTop + 1400
  const baseTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  await page.evaluate((y) => window.scrollTo(0, y), baseTop + 1400);
  await page.waitForTimeout(500);

  const scrolled = await page.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    return {
      c1: {
        classes: c1?.className,
        style: c1?.getAttribute('style'),
        rect: c1?.getBoundingClientRect(),
        computedTop: window.getComputedStyle(c1).top,
        computedPos: window.getComputedStyle(c1).position,
      },
      c2: {
        classes: c2?.className,
        style: c2?.getAttribute('style'),
        rect: c2?.getBoundingClientRect(),
        computedTop: window.getComputedStyle(c2).top,
        computedPos: window.getComputedStyle(c2).position,
      },
      c3: {
        classes: c3?.className,
        style: c3?.getAttribute('style'),
        rect: c3?.getBoundingClientRect(),
        computedTop: window.getComputedStyle(c3).top,
        computedPos: window.getComputedStyle(c3).position,
      },
    };
  });

  console.log('SCROLLED CARDS STATE:\n', JSON.stringify(scrolled, null, 2));

  await browser.close();
}

inspectStickyOnLocal().catch(console.error);
