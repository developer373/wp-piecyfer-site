const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectAllCSSRules() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const matchedRules = await page.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    function getRules(el) {
      const sheets = Array.from(document.styleSheets);
      const matching = [];
      for (const sheet of sheets) {
        try {
          const rules = Array.from(sheet.cssRules || []);
          for (const r of rules) {
            if (r.selectorText && el.matches(r.selectorText)) {
              matching.push({
                href: sheet.href || 'inline',
                selector: r.selectorText,
                cssText: r.cssText,
              });
            } else if (r.cssRules) { // media queries
              for (const subR of Array.from(r.cssRules)) {
                if (subR.selectorText && el.matches(subR.selectorText)) {
                  matching.push({
                    href: sheet.href || 'inline',
                    media: r.conditionText || r.media?.mediaText,
                    selector: subR.selectorText,
                    cssText: subR.cssText,
                  });
                }
              }
            }
          }
        } catch (e) {}
      }
      return matching;
    }

    return {
      c1: getRules(c1),
      c2: getRules(c2),
      c3: getRules(c3),
    };
  });

  console.log('MATCHED RULES FOR C1:\n', JSON.stringify(matchedRules.c1, null, 2));
  console.log('MATCHED RULES FOR C2:\n', JSON.stringify(matchedRules.c2, null, 2));
  console.log('MATCHED RULES FOR C3:\n', JSON.stringify(matchedRules.c3, null, 2));

  await browser.close();
}

inspectAllCSSRules().catch(console.error);
