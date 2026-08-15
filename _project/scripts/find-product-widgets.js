const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function findProductWidgets() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const el = Array.from(document.querySelectorAll('*')).find(e => e.textContent.includes('Email Genius') && e.children.length === 0);
    const container = el ? el.closest('.elementor-element, section, div') : null;
    const parentSection = el ? el.closest('section.elementor-top-section, .elementor-element-wrap, .elementor-widget') : null;
    
    // Find all widgets inside the Innovation is deep rooted section
    const innovHeading = Array.from(document.querySelectorAll('*')).find(e => e.textContent.includes('Innovation is deep-rooted') && e.children.length === 0);
    const mainSection = innovHeading ? innovHeading.closest('section.elementor-top-section') : null;

    return {
      elTag: el ? el.tagName : null,
      parentSectionClass: parentSection ? parentSection.className : null,
      mainSectionHtml: mainSection ? mainSection.innerHTML.slice(0, 1500) : null
    };
  });

  console.log('Product Widgets in DOM:', JSON.stringify(data, null, 2));
  await browser.close();
}

findProductWidgets().catch(console.error);
