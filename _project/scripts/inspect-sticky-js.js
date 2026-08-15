const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectStickyJS() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const jsState = await page.evaluate(() => {
    return {
      hasElementorFrontend: typeof window.elementorFrontend !== 'undefined',
      hasStickyModule: typeof window.elementorFrontend?.modules?.Sticky !== 'undefined' || typeof window.elementorFrontend?.elementsHandler?.elementsHandlers?.sticky !== 'undefined',
      loadedScripts: Array.from(document.querySelectorAll('script[src]')).map(s => s.src),
    };
  });

  console.log('JS STATE:\n', JSON.stringify({
    hasElementorFrontend: jsState.hasElementorFrontend,
    hasStickyModule: jsState.hasStickyModule,
    stickyScripts: jsState.loadedScripts.filter(s => s.includes('sticky') || s.includes('elementor')),
  }, null, 2));

  await browser.close();
}

inspectStickyJS().catch(console.error);
