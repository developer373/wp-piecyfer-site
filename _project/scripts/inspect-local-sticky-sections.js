const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLocalSections() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  const sections = await page.evaluate(() => {
    const parent = document.querySelector('.elementor-element-062c684');
    if (!parent) return 'Parent not found';

    const inners = Array.from(parent.querySelectorAll('.elementor-inner-section'));
    return inners.map(s => {
      const h = s.querySelector('h1,h2,h3,h4,h5,h6')?.textContent?.trim();
      return {
        classes: s.className,
        id: s.id,
        title: h,
        dataSettings: s.getAttribute('data-settings'),
        style: s.getAttribute('style'),
        rect: s.getBoundingClientRect()
      };
    });
  });

  console.log('LOCAL INNER SECTIONS IN 062c684:', JSON.stringify(sections, null, 2));
  await browser.close();
}

inspectLocalSections().catch(console.error);
