const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function extractSearchCss() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  const css = await page.evaluate(() => {
    const el = document.querySelector('.top-bar-search');
    if (!el) return 'not found';
    
    // Find all matching CSS rules from all stylesheets
    const rules = [];
    for (const sheet of Array.from(document.styleSheets)) {
      try {
        for (const rule of Array.from(sheet.cssRules || [])) {
          if (rule.selectorText && (
            rule.selectorText.includes('elementor-search-form') ||
            rule.selectorText.includes('top-bar-search')
          )) {
            rules.push({ selector: rule.selectorText, cssText: rule.cssText });
          }
        }
      } catch (e) {}
    }
    return rules;
  });

  console.log('LIVE SEARCH RULES:', JSON.stringify(css, null, 2));
  await browser.close();
}

extractSearchCss().catch(console.error);
