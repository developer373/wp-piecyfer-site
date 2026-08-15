const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLocalIcon() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

  await page.goto('http://localhost/piecyfer/our-team/', { waitUntil: 'networkidle' });

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
      display: s.display,
      parentClass: icon.parentElement.className,
      parentStyles: {
        width: window.getComputedStyle(icon.parentElement).width,
        height: window.getComputedStyle(icon.parentElement).height,
        background: window.getComputedStyle(icon.parentElement).background,
      }
    };
  });

  console.log('LOCAL ICON INFO:\n', JSON.stringify(iconInfo, null, 2));

  // Check social container position
  const socialInfo = await page.evaluate(() => {
    const social = document.querySelector('.team-area .thumb .social');
    const li = document.querySelector('.team-area .thumb .social ul li');
    const a = document.querySelector('.team-area .thumb .social ul li a');
    return {
      social: social ? window.getComputedStyle(social).cssText : null,
      li: li ? window.getComputedStyle(li).cssText : null,
      a: a ? window.getComputedStyle(a).cssText : null,
    };
  });

  console.log('SOCIAL STYLES ON LIVE VS LOCAL:', JSON.stringify(socialInfo, null, 2));

  await browser.close();
}

inspectLocalIcon().catch(console.error);
