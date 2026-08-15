const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectProducts() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  console.log('=== INSPECTING OUR PRODUCTS SECTION ON LIVE VS LOCAL ===\n');

  async function check(url, name) {
    const page = await context.newPage();
    await page.goto(url, { waitUntil: 'networkidle' });

    // Scroll down through page slowly to trigger scroll animations
    for (let i = 0; i < 10; i++) {
      await page.mouse.wheel(0, 800);
      await page.waitForTimeout(200);
    }

    const data = await page.evaluate(() => {
      // Find section with "Innovation is deep-rooted" or "Email Genius"
      const heading = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, .elementor-heading-title'))
        .find(el => el.textContent.includes('Innovation is deep-rooted') || el.textContent.includes('Email Genius'));
      
      const section = heading ? heading.closest('section.elementor-section, .elementor-top-section, .elementor-element') : null;
      const cards = Array.from(document.querySelectorAll('.elementor-widget-call-to-action, [data-widget_type="call-to-action.default"]'));

      const cardsData = cards.map(c => {
        const title = c.querySelector('.elementor-cta__title')?.textContent?.trim();
        const btn = c.querySelector('.elementor-cta__button');
        const btnRect = btn ? btn.getBoundingClientRect() : null;
        const img = c.querySelector('img');
        const imgRect = img ? img.getBoundingClientRect() : null;
        const cardRect = c.getBoundingClientRect();
        return {
          title,
          cardRect: { width: cardRect.width, height: cardRect.height, top: cardRect.top },
          btnText: btn ? btn.textContent?.trim() : 'none',
          btnRect: btnRect ? { width: btnRect.width, height: btnRect.height } : null,
          imgSrc: img ? img.src : 'none',
          imgRect: imgRect ? { width: imgRect.width, height: imgRect.height } : null,
          transform: window.getComputedStyle(c).transform,
          opacity: window.getComputedStyle(c).opacity
        };
      });

      // Also check any horizontal scroll, sticky, or tab widgets in that section
      const hrWidgets = Array.from(document.querySelectorAll('.vamtam-hr-scrolling, [data-settings*="horizontal"], [data-settings*="sticky"], .elementor-widget-tabs'));

      return {
        sectionHtml: section ? section.className : 'none',
        cardsCount: cards.length,
        cardsData,
        hrWidgetsCount: hrWidgets.length
      };
    });

    console.log(`[${name}] Products Section:`, JSON.stringify(data, null, 2));
    await page.close();
  }

  await check('https://www.piecyfer.com/', 'LIVE');
  await check('http://localhost/piecyfer/', 'LOCAL');

  await browser.close();
}

inspectProducts().catch(console.error);
