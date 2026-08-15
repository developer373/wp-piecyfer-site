const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

const urls = [
  'http://localhost/piecyfer/',
  'http://localhost/piecyfer/contact-us/',
  'http://localhost/piecyfer/our-team/',
  'http://localhost/piecyfer/blogs/',
  'http://localhost/piecyfer/web-app-development/'
];

async function measure() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  
  console.log('=== PERFORMANCE MEASUREMENTS ===\n');

  for (const url of urls) {
    const page = await context.newPage();
    let totalBytes = 0;
    let requestCount = 0;
    const resourcesByType = { script: 0, stylesheet: 0, image: 0, font: 0, document: 0, other: 0 };

    page.on('response', async (response) => {
      requestCount++;
      const type = response.request().resourceType();
      resourcesByType[type] = (resourcesByType[type] || 0) + 1;
      try {
        const buf = await response.body();
        totalBytes += buf.length;
      } catch (e) {}
    });

    const start = Date.now();
    await page.goto(url, { waitUntil: 'load' });
    const loadTime = Date.now() - start;

    const metrics = await page.evaluate(() => {
      const nav = performance.getEntriesByType('navigation')[0];
      const paint = performance.getEntriesByType('paint');
      const fcp = paint.find(p => p.name === 'first-contentful-paint')?.startTime || 0;
      return {
        domContentLoaded: nav ? Math.round(nav.domContentLoadedEventEnd - nav.startTime) : 0,
        loadEvent: nav ? Math.round(nav.loadEventEnd - nav.startTime) : 0,
        fcp: Math.round(fcp)
      };
    });

    console.log(`URL: ${url}`);
    console.log(`  Requests: ${requestCount} (JS: ${resourcesByType.script || 0}, CSS: ${resourcesByType.stylesheet || 0}, Img: ${resourcesByType.image || 0}, Font: ${resourcesByType.font || 0})`);
    console.log(`  Total Transfer: ${(totalBytes / 1024).toFixed(1)} KB`);
    console.log(`  DOM Content Loaded: ${metrics.domContentLoaded} ms`);
    console.log(`  First Contentful Paint: ${metrics.fcp} ms`);
    console.log(`  Load Event: ${metrics.loadEvent || loadTime} ms\n`);

    await page.close();
  }

  await browser.close();
}

measure().catch(console.error);
