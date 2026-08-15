const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectStackingScrollStepByStep() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Get exact bounding rects and parent elements of the 3 cards
  const structure = await page.evaluate(() => {
    const root = document.querySelector('.elementor-element-062c684');
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    return {
      root: {
        tag: root?.tagName,
        classes: root?.className,
        rect: root?.getBoundingClientRect(),
        offsetTop: root?.offsetTop,
        childrenCount: root?.children.length
      },
      c1: {
        parent: c1?.parentElement?.className,
        grandParent: c1?.parentElement?.parentElement?.className,
        offsetTop: c1?.offsetTop,
        rect: c1?.getBoundingClientRect()
      },
      c2: {
        parent: c2?.parentElement?.className,
        grandParent: c2?.parentElement?.parentElement?.className,
        offsetTop: c2?.offsetTop,
        rect: c2?.getBoundingClientRect()
      },
      c3: {
        parent: c3?.parentElement?.className,
        grandParent: c3?.parentElement?.parentElement?.className,
        offsetTop: c3?.offsetTop,
        rect: c3?.getBoundingClientRect()
      }
    };
  });

  console.log('DOM STRUCTURE:\n', JSON.stringify(structure, null, 2));

  // Now let's trace the exact positions of c1, c2, c3 as we scroll every 100px
  const rootTop = await page.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  console.log('\n--- SCROLL TRACE ---');
  for (let offset = 0; offset <= 2000; offset += 150) {
    await page.evaluate((y) => window.scrollTo(0, y), rootTop + offset);
    await page.waitForTimeout(100);

    const positions = await page.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');
      
      const r1 = c1?.getBoundingClientRect();
      const r2 = c2?.getBoundingClientRect();
      const r3 = c3?.getBoundingClientRect();

      return {
        scrollY: window.scrollY,
        c1_top: Math.round(r1?.top || 0),
        c1_bottom: Math.round(r1?.bottom || 0),
        c2_top: Math.round(r2?.top || 0),
        c2_bottom: Math.round(r2?.bottom || 0),
        c3_top: Math.round(r3?.top || 0),
        c3_bottom: Math.round(r3?.bottom || 0),
        // Difference between c1 top and c2 top
        diff_1_2: Math.round((r2?.top || 0) - (r1?.top || 0)),
        // Difference between c2 top and c3 top
        diff_2_3: Math.round((r3?.top || 0) - (r2?.top || 0)),
      };
    });

    console.log(`Scroll offset +${offset} (scrollY=${positions.scrollY}):`, positions);
  }

  await browser.close();
}

inspectStackingScrollStepByStep().catch(console.error);
