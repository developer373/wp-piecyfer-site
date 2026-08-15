const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectHeaderSticky() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);

  // Check all sections with sticky or header classes
  const stickyHeaders = await page.evaluate(() => {
    const all = Array.from(document.querySelectorAll('*'));
    const stickyEls = all.filter(el => {
      const s = window.getComputedStyle(el);
      return (s.position === 'sticky' || s.position === 'fixed') && s.top === '0px' && el.offsetHeight > 0;
    });

    return stickyEls.map(el => ({
      tag: el.tagName,
      className: el.className,
      id: el.id,
      height: el.offsetHeight
    }));
  });

  console.log('LIVE STICKY ELEMENTS AT TOP:\n', JSON.stringify(stickyHeaders, null, 2));

  // Let's scroll 500px down and re-check
  await page.evaluate(() => window.scrollTo(0, 500));
  await page.waitForTimeout(300);

  const stickyHeadersScrolled = await page.evaluate(() => {
    const all = Array.from(document.querySelectorAll('*'));
    const stickyEls = all.filter(el => {
      const s = window.getComputedStyle(el);
      return (s.position === 'sticky' || s.position === 'fixed') && parseInt(s.top) >= 0 && el.offsetHeight > 0;
    });

    return stickyEls.map(el => ({
      tag: el.tagName,
      className: el.className,
      id: el.id,
      height: el.offsetHeight,
      position: window.getComputedStyle(el).position,
      top: window.getComputedStyle(el).top
    }));
  });

  console.log('LIVE STICKY ELEMENTS SCROLLED:\n', JSON.stringify(stickyHeadersScrolled, null, 2));

  await browser.close();
}

inspectHeaderSticky().catch(console.error);
