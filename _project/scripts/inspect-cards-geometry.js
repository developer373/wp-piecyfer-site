const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectCardsGeometry() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const geometry = await page.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    const c1_inner = c1?.querySelector('.elementor-element');
    const c2_inner = c2?.querySelector('.elementor-element');
    const c3_inner = c3?.querySelector('.elementor-element');

    return {
      c1: {
        outerHeight: c1?.offsetHeight,
        innerHeight: c1_inner?.offsetHeight,
        style: c1?.getAttribute('style'),
        className: c1?.className,
        children: Array.from(c1?.children || []).map(ch => ({ tag: ch.tagName, class: ch.className, h: ch.offsetHeight }))
      },
      c2: {
        outerHeight: c2?.offsetHeight,
        innerHeight: c2_inner?.offsetHeight,
        style: c2?.getAttribute('style'),
        className: c2?.className,
        children: Array.from(c2?.children || []).map(ch => ({ tag: ch.tagName, class: ch.className, h: ch.offsetHeight }))
      },
      c3: {
        outerHeight: c3?.offsetHeight,
        innerHeight: c3_inner?.offsetHeight,
        style: c3?.getAttribute('style'),
        className: c3?.className,
        children: Array.from(c3?.children || []).map(ch => ({ tag: ch.tagName, class: ch.className, h: ch.offsetHeight }))
      },
    };
  });

  console.log('CARDS GEOMETRY AT 1920x1080:\n', JSON.stringify(geometry, null, 2));

  // Let's scroll step by step and log bounding client rects at 1920x1080
  const sectionTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  console.log('Section Top at 1920x1080:', sectionTop);

  for (let offset = 0; offset <= 2500; offset += 300) {
    await page.evaluate((y) => window.scrollTo(0, y), sectionTop + offset);
    await page.waitForTimeout(100);
    const rects = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');
      return {
        scrollY: window.scrollY,
        c1_top: c1?.getBoundingClientRect().top,
        c2_top: c2?.getBoundingClientRect().top,
        c3_top: c3?.getBoundingClientRect().top,
        c1_bottom: c1?.getBoundingClientRect().bottom,
        c2_bottom: c2?.getBoundingClientRect().bottom,
        c3_bottom: c3?.getBoundingClientRect().bottom,
      };
    });
    console.log(`Scroll +${offset}px:`, JSON.stringify(rects));
  }

  await browser.close();
}

inspectCardsGeometry().catch(console.error);
