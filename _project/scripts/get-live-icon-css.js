const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function getLiveIconCSS() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

  await page.goto('https://www.piecyfer.com/our-team/', { waitUntil: 'networkidle' });

  const info = await page.evaluate(() => {
    const icon = document.querySelector('.team-icon, .icon-linkedin');
    const matchedRules = [];
    
    for (const sheet of document.styleSheets) {
      try {
        for (const rule of sheet.cssRules) {
          if (rule.selectorText && (
            rule.selectorText.includes('icon-linkedin') ||
            rule.selectorText.includes('.team-icon') ||
            rule.selectorText.includes('.icon')
          )) {
            matchedRules.push({
              href: sheet.href,
              selector: rule.selectorText,
              cssText: rule.cssText
            });
          }
          if (rule.type === CSSRule.FONT_FACE_RULE) {
            matchedRules.push({
              fontFace: rule.cssText
            });
          }
        }
      } catch (e) {}
    }

    return {
      computed: {
        fontFamily: window.getComputedStyle(icon).fontFamily,
        content: window.getComputedStyle(icon, '::before').content,
      },
      rules: matchedRules
    };
  });

  console.log('LIVE ICON RULES:\n', JSON.stringify(info, null, 2));

  await browser.close();
}

getLiveIconCSS().catch(console.error);
