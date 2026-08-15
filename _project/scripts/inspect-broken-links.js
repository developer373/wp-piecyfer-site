const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectBroken() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  const headerLinks = await page.evaluate(() => {
    const navLinks = Array.from(document.querySelectorAll('header a, nav a, .elementor-nav-menu a, .ekit-menu-nav-link, .elementskit-navbar-nav a'));
    return navLinks.map(a => ({
      text: a.innerText.trim(),
      href: a.href,
      target: a.target,
      html: a.outerHTML
    })).filter(item => item.href.startsWith('http://localhost/') && !item.href.startsWith('http://localhost/piecyfer'));
  });

  console.log('BROKEN LINKS:', JSON.stringify(headerLinks, null, 2));

  await browser.close();
}

inspectBroken().catch(console.error);
