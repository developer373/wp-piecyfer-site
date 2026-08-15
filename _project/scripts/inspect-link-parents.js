const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLinkParents() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  const results = await page.evaluate(() => {
    const broken = Array.from(document.querySelectorAll('a')).filter(a => a.href === 'http://localhost/emerging-tech/' || a.href === 'http://localhost/digital-marketing/' || a.href === 'http://localhost/hire-an-expert/');
    return broken.map(a => {
      let p = a;
      const chain = [];
      while (p && p !== document.body) {
        chain.push(`${p.tagName.toLowerCase()}${p.id ? '#' + p.id : ''}${p.className ? '.' + p.className.split(' ').join('.') : ''}`);
        p = p.parentElement;
      }
      return {
        html: a.outerHTML,
        parentHtml: a.parentElement ? a.parentElement.outerHTML : null,
        chain: chain.join(' > ')
      };
    });
  });

  console.log('LINK LOCATIONS:\n', JSON.stringify(results, null, 2));

  await browser.close();
}

inspectLinkParents().catch(console.error);
