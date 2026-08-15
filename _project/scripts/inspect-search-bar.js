const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectSearch() {
  const browser = await chromium.launch({ headless: true });
  
  for (const vp of [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 375, height: 667 },
  ]) {
    const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });

    async function getSearch(url, label) {
      const page = await context.newPage();
      await page.goto(url, { waitUntil: 'networkidle' });

      const res = await page.evaluate(() => {
        const s = document.querySelector('.top-bar-search, .elementor-widget-search-form, .elementor-search-form, .elementor-widget-elementskit-header-search');
        if (!s) return null;
        const input = s.querySelector('input[type="search"], input[type="text"]');
        const btn = s.querySelector('button, .elementor-search-form__submit');
        const icon = s.querySelector('i, svg');
        const rect = s.getBoundingClientRect();
        return {
          classes: s.className,
          rect: { width: rect.width, height: rect.height, top: rect.top, left: rect.left },
          display: window.getComputedStyle(s).display,
          input: input ? {
            placeholder: input.getAttribute('placeholder'),
            width: window.getComputedStyle(input).width,
            height: window.getComputedStyle(input).height,
            border: window.getComputedStyle(input).border,
            borderRadius: window.getComputedStyle(input).borderRadius,
            padding: window.getComputedStyle(input).padding,
            bg: window.getComputedStyle(input).backgroundColor
          } : null,
          btn: btn ? {
            display: window.getComputedStyle(btn).display,
            width: window.getComputedStyle(btn).width
          } : null
        };
      });

      await page.close();
      return res;
    }

    const live = await getSearch('https://www.piecyfer.com/', 'LIVE');
    const local = await getSearch('http://localhost/piecyfer/', 'LOCAL');

    console.log(`\n=== SEARCH BAR [${vp.name.toUpperCase()} ${vp.width}px] ===`);
    console.log('LIVE:', JSON.stringify(live, null, 2));
    console.log('LOCAL:', JSON.stringify(local, null, 2));

    await context.close();
  }

  await browser.close();
}

inspectSearch().catch(console.error);
