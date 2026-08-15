const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectEmailGenius() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  async function check(url, name) {
    const page = await context.newPage();
    await page.goto(url, { waitUntil: 'networkidle' });

    // Scroll to Email Genius
    await page.evaluate(() => {
      const h = Array.from(document.querySelectorAll('h1,h2,h3,h4,h5,h6,p,span')).find(el => el.textContent.trim() === 'Email Genius');
      if (h) h.scrollIntoView();
    });

    await page.waitForTimeout(500);

    const data = await page.evaluate(() => {
      const h = Array.from(document.querySelectorAll('h1,h2,h3,h4,h5,h6,p,span')).find(el => el.textContent.trim() === 'Email Genius');
      const section = h ? h.closest('.elementor-section, .elementor-top-section, [data-element_type="section"]') : null;
      
      // Let's get the 3 cards in this section (Email Genius, Team Management System, OnlineDoc)
      const subSections = section ? Array.from(section.querySelectorAll('.elementor-inner-section, .elementor-section')) : [];
      
      return {
        sectionClass: section ? section.className : 'null',
        sectionDataSettings: section ? section.getAttribute('data-settings') : null,
        subSectionsCount: subSections.length,
        subSections: subSections.map(s => ({
          classes: s.className,
          dataSettings: s.getAttribute('data-settings'),
          rect: s.getBoundingClientRect(),
          styles: {
            position: window.getComputedStyle(s).position,
            transform: window.getComputedStyle(s).transform,
            top: window.getComputedStyle(s).top,
            zIndex: window.getComputedStyle(s).zIndex
          }
        }))
      };
    });

    console.log(`[${name}] Email Genius Section:`, JSON.stringify(data, null, 2));
    await page.close();
  }

  await check('https://www.piecyfer.com/', 'LIVE');
  await check('http://localhost/piecyfer/', 'LOCAL');

  await browser.close();
}

inspectEmailGenius().catch(console.error);
