const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectTemplateWidgets() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    // Check .projects widgets (eb5cb1c, 3ba3561, 0f1a206)
    const w1 = document.querySelector('.elementor-element-eb5cb1c');
    const w2 = document.querySelector('.elementor-element-3ba3561');
    const w3 = document.querySelector('.elementor-element-0f1a206');

    return {
      w1: w1 ? {
        classes: w1.className,
        settings: w1.getAttribute('data-settings'),
        style: w1.getAttribute('style'),
        computed: {
          position: window.getComputedStyle(w1).position,
          top: window.getComputedStyle(w1).top,
          zIndex: window.getComputedStyle(w1).zIndex
        }
      } : null,
      w2: w2 ? {
        classes: w2.className,
        settings: w2.getAttribute('data-settings'),
        style: w2.getAttribute('style'),
        computed: {
          position: window.getComputedStyle(w2).position,
          top: window.getComputedStyle(w2).top,
          zIndex: window.getComputedStyle(w2).zIndex
        }
      } : null,
      w3: w3 ? {
        classes: w3.className,
        settings: w3.getAttribute('data-settings'),
        style: w3.getAttribute('style'),
        computed: {
          position: window.getComputedStyle(w3).position,
          top: window.getComputedStyle(w3).top,
          zIndex: window.getComputedStyle(w3).zIndex
        }
      } : null,
    };
  });

  console.log('LIVE TEMPLATE WIDGET DATA:', JSON.stringify(data, null, 2));
  await browser.close();
}

inspectTemplateWidgets().catch(console.error);
