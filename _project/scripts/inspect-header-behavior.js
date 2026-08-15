const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectHeaderBehavior() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);

  // Check header position on live
  const headerLive = await page.evaluate(() => {
    const h = document.querySelector('header, .elementor-location-header, .main-header, [data-elementor-type="header"]');
    return {
      classes: h?.className,
      position: h ? window.getComputedStyle(h).position : null,
      top: h ? window.getComputedStyle(h).top : null,
      zIndex: h ? window.getComputedStyle(h).zIndex : null,
      height: h ? h.offsetHeight : 0,
      rect: h ? h.getBoundingClientRect() : null
    };
  });

  console.log('LIVE HEADER INFO (scrollY=0):', headerLive);

  // Scroll down 6000px to the projects section on Live
  await page.evaluate(() => window.scrollTo(0, 6000));
  await page.waitForTimeout(300);

  const headerLiveScrolled = await page.evaluate(() => {
    const h = document.querySelector('header, .elementor-location-header, .main-header, [data-elementor-type="header"]');
    return {
      classes: h?.className,
      position: h ? window.getComputedStyle(h).position : null,
      top: h ? window.getComputedStyle(h).top : null,
      zIndex: h ? window.getComputedStyle(h).zIndex : null,
      rect: h ? h.getBoundingClientRect() : null
    };
  });

  console.log('LIVE HEADER INFO (scrollY=6000):', headerLiveScrolled);

  await browser.close();
}

inspectHeaderBehavior().catch(console.error);
