const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectC1Structure() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const c1Info = await page.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    function getTree(el) {
      if (!el) return null;
      return Array.from(el.querySelectorAll('*')).map(child => {
        const s = window.getComputedStyle(child);
        return {
          tag: child.tagName,
          className: child.className,
          paddingTop: s.paddingTop,
          marginTop: s.marginTop,
          top: s.top,
          height: child.offsetHeight,
          rectTop: child.getBoundingClientRect().top
        };
      }).filter(x => parseInt(x.paddingTop) > 0 || parseInt(x.marginTop) > 0 || x.className.includes('elementor-element-'));
    }

    return {
      c1Tree: getTree(c1),
      c2Tree: getTree(c2),
      c3Tree: getTree(c3),
    };
  });

  console.log('C1 TREE:\n', JSON.stringify(c1Info.c1Tree, null, 2));
  console.log('C2 TREE:\n', JSON.stringify(c1Info.c2Tree, null, 2));

  await browser.close();
}

inspectC1Structure().catch(console.error);
