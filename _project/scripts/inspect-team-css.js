const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');

async function inspectTeamCSS() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
  
  await page.goto('https://www.piecyfer.com/our-team/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  // Extract all CSS rules from all stylesheets on live that mention team-area, team-items, single-item, thumb, info-box, etc.
  const cssRules = await page.evaluate(() => {
    const matched = [];
    const sheets = Array.from(document.styleSheets);

    for (const sheet of sheets) {
      try {
        const rules = Array.from(sheet.cssRules || []);
        for (const r of rules) {
          const text = r.cssText || '';
          if (
            text.includes('.team-') ||
            text.includes('.single-item') ||
            text.includes('.info-box') ||
            text.includes('.thumb-dp') ||
            text.includes('.thumb') ||
            text.includes('.social') ||
            text.includes('FOUNDING') ||
            text.includes('1b88ffa')
          ) {
            matched.push({
              href: sheet.href || 'inline',
              cssText: r.cssText
            });
          }
        }
      } catch (e) {}
    }
    return matched;
  });

  console.log(`Found ${cssRules.length} matching CSS rules on live.`);
  fs.writeFileSync(
    'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_team_css_rules.json',
    JSON.stringify(cssRules, null, 2),
    'utf8'
  );

  // Hover on first partner card to capture hover state
  const firstCard = page.locator('.single-item .item').first();
  await firstCard.hover();
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_first_card_hover.png' });

  await browser.close();
}

inspectTeamCSS().catch(console.error);
