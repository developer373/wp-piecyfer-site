const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function extractProjectsCss() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  const css = await page.evaluate(() => {
    const rules = [];
    for (const sheet of Array.from(document.styleSheets)) {
      try {
        for (const rule of Array.from(sheet.cssRules || [])) {
          if (rule.selectorText && (
            rule.selectorText.includes('projects') ||
            rule.selectorText.includes('23575fc') ||
            rule.selectorText.includes('eafec62') ||
            rule.selectorText.includes('e40cd70') ||
            rule.selectorText.includes('eb5cb1c') ||
            rule.selectorText.includes('3ba3561') ||
            rule.selectorText.includes('0f1a206') ||
            rule.selectorText.includes('sticky')
          )) {
            rules.push({ selector: rule.selectorText, cssText: rule.cssText });
          }
        }
      } catch (e) {}
    }
    return rules;
  });

  console.log('LIVE PROJECTS CSS RULES:\n', JSON.stringify(css, null, 2));
  await browser.close();
}

extractProjectsCss().catch(console.error);
