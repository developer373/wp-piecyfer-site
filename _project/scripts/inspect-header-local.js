const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectHeaderLocal() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);

  const headerInfo = await page.evaluate(() => {
    const navSection = document.querySelector('.elementor-element-f4ae4d6');
    const header = document.querySelector('.elementor-location-header, header');
    return {
      headerTag: header ? header.tagName : null,
      headerClass: header ? header.className : null,
      navSectionClass: navSection ? navSection.className : null,
      navSectionParent: navSection ? navSection.parentElement.className : null,
      navSectionDataset: navSection ? navSection.dataset : null
    };
  });

  console.log('LOCAL HEADER INFO:\n', JSON.stringify(headerInfo, null, 2));
  await browser.close();
}

inspectHeaderLocal().catch(console.error);
