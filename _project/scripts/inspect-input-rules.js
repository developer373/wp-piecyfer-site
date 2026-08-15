const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectRules() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });

  const rules = await page.evaluate(() => {
    const el = document.querySelector('.elementor-search-form__input');
    const matched = [];
    for (const sheet of document.styleSheets) {
      try {
        for (const rule of sheet.cssRules) {
          if (rule.selectorText && el.matches(rule.selectorText)) {
            matched.push({
              sheet: sheet.href ? sheet.href.split('/').pop() : 'inline',
              selector: rule.selectorText,
              cssText: rule.cssText
            });
          }
        }
      } catch (e) {}
    }
    return matched;
  });

  console.log("MATCHED RULES FOR INPUT:", JSON.stringify(rules, null, 2));
  await browser.close();
}

inspectRules().catch(console.error);
