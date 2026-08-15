const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function verifyNavbarLinks() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  // Get all links in header / nav
  const headerLinks = await page.evaluate(() => {
    const navLinks = Array.from(document.querySelectorAll('header a, nav a, .elementor-nav-menu a, .ekit-menu-nav-link, .elementskit-navbar-nav a'));
    return navLinks.map(a => ({
      text: a.innerText.trim(),
      href: a.href,
      target: a.target
    })).filter(item => item.text.length > 0 || item.href.length > 0);
  });

  console.log('--- ALL HEADER / NAV LINKS (' + headerLinks.length + ') ---');
  let brokenCount = 0;
  for (const l of headerLinks) {
    const isBad = l.href.startsWith('http://localhost/') && !l.href.startsWith('http://localhost/piecyfer');
    if (isBad) {
      console.log('❌ BROKEN:', l.text, '=>', l.href);
      brokenCount++;
    } else {
      console.log('✅ OK:', l.text, '=>', l.href);
    }
  }

  console.log(`\nTotal broken links in Header Nav: ${brokenCount}`);

  // Test clicking "Services"
  console.log('\nTesting click on Services link...');
  const servicesLink = page.locator('header a, nav a').filter({ hasText: /^Services$/i }).first();
  if (await servicesLink.count() > 0) {
    const servicesHref = await servicesLink.getAttribute('href');
    console.log('Services link href attribute:', servicesHref);

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
      servicesLink.click()
    ]);

    console.log('Navigated URL after clicking Services:', page.url());
  }

  await browser.close();
}

verifyNavbarLinks().catch(console.error);
