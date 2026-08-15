const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectProjectsCards() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  async function check(url, name) {
    const page = await context.newPage();
    await page.goto(url, { waitUntil: 'networkidle' });

    const data = await page.evaluate(() => {
      const projects = Array.from(document.querySelectorAll('.projects'));
      return projects.map((p, i) => {
        const title = p.querySelector('h1,h2,h3,h4,h5,h6')?.textContent?.trim();
        const btn = p.querySelector('a.elementor-button, .elementor-button');
        const img = p.querySelector('img');
        const rect = p.getBoundingClientRect();
        return {
          index: i,
          title,
          rect: { width: rect.width, height: rect.height, top: rect.top },
          btnText: btn ? btn.textContent?.trim() : 'none',
          btnHref: btn ? btn.getAttribute('href') : 'none',
          imgSrc: img ? img.src : 'none',
          classes: p.className
        };
      });
    });

    console.log(`[${name}] Projects Cards:`, JSON.stringify(data, null, 2));
    await page.close();
  }

  await check('https://www.piecyfer.com/', 'LIVE');
  await check('http://localhost/piecyfer/', 'LOCAL');

  await browser.close();
}

inspectProjectsCards().catch(console.error);
