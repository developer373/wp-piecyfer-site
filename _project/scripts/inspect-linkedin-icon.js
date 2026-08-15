const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLinkedInIcon() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
  
  await page.goto('https://www.piecyfer.com/our-team/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const iconInfo = await page.evaluate(() => {
    const icon = document.querySelector('.team-icon, .icon-linkedin');
    if (!icon) return null;
    const s = window.getComputedStyle(icon);
    const before = window.getComputedStyle(icon, '::before');
    return {
      fontFamily: s.fontFamily,
      fontSize: s.fontSize,
      color: s.color,
      content: before.content,
      className: icon.className,
      rect: icon.getBoundingClientRect(),
    };
  });

  console.log('LINKEDIN ICON ON LIVE:\n', JSON.stringify(iconInfo, null, 2));
  await browser.close();
}

inspectLinkedInIcon().catch(console.error);
