const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectCardStyles() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const styles = await page.evaluate(() => {
    function getDetails(sel) {
      const el = document.querySelector(sel);
      if (!el) return null;
      const s = window.getComputedStyle(el);
      // Also check first child inner section or column
      const inner = el.querySelector('.elementor-section, .elementor-element-populated, .elementor-widget-wrap, .elementor-container');
      const innerStyle = inner ? window.getComputedStyle(inner) : null;
      return {
        bg: s.backgroundColor,
        bgImg: s.backgroundImage,
        borderRadius: s.borderRadius,
        overflow: s.overflow,
        boxShadow: s.boxShadow,
        innerBg: innerStyle?.backgroundColor,
        innerRadius: innerStyle?.borderRadius,
      };
    }

    return {
      c1_outer: getDetails('.elementor-element-9a128b1'),
      c1_inner_template: getDetails('.elementor-element-23575fc'),
      c2_outer: getDetails('.elementor-element-69babdd'),
      c2_inner_template: getDetails('.elementor-element-eafec62'),
      c3_outer: getDetails('.elementor-element-73afe75'),
      c3_inner_template: getDetails('.elementor-element-e40cd70'),
    };
  });

  console.log('STYLES DETAILS:\n', JSON.stringify(styles, null, 2));

  await browser.close();
}

inspectCardStyles().catch(console.error);
