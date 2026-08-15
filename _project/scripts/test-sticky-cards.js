const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testStickyCards() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Add the stacking sticky CSS
  await page.addStyleTag({
    content: `
      .elementor-widget-template.projects {
        position: sticky;
        transition: transform 0.2s ease;
      }
      .elementor-widget-template.elementor-element-eb5cb1c {
        top: 100px;
        z-index: 1;
      }
      .elementor-widget-template.elementor-element-3ba3561 {
        top: 140px;
        z-index: 2;
      }
      .elementor-widget-template.elementor-element-0f1a206 {
        top: 180px;
        z-index: 3;
      }
    `
  });

  const parentOverflow = await page.evaluate(() => {
    const el = document.querySelector('.elementor-widget-template.projects');
    const parents = [];
    let p = el ? el.parentElement : null;
    while (p && p !== document.body) {
      parents.push({
        tag: p.tagName,
        classes: p.className,
        overflow: window.getComputedStyle(p).overflow,
        overflowX: window.getComputedStyle(p).overflowX,
        overflowY: window.getComputedStyle(p).overflowY,
        height: window.getComputedStyle(p).height,
        position: window.getComputedStyle(p).position,
      });
      p = p.parentElement;
    }
    return parents;
  });

  console.log('PARENT CONTAINERS OVERFLOW CHECK:');
  console.log(JSON.stringify(parentOverflow, null, 2));

  // Now scroll step by step and check the rects of the 3 cards
  const scrollYBase = await page.evaluate(() => {
    const el = document.querySelector('.elementor-widget-template.projects');
    return el.getBoundingClientRect().top + window.scrollY;
  });

  console.log(`\nBase scrollY for projects section: ${scrollYBase}`);

  for (let offset = 0; offset <= 1500; offset += 300) {
    const sy = scrollYBase - 100 + offset;
    await page.evaluate((y) => window.scrollTo(0, y), sy);
    await page.waitForTimeout(100);

    const positions = await page.evaluate(() => {
      const cards = Array.from(document.querySelectorAll('.elementor-widget-template.projects'));
      return cards.map(c => {
        const title = c.querySelector('h1,h2,h3,h4,h5,h6')?.textContent?.trim();
        const rect = c.getBoundingClientRect();
        return {
          title,
          top: rect.top.toFixed(1),
          height: rect.height.toFixed(1)
        };
      });
    });

    console.log(`Scroll offset +${offset}px (scrollY ${sy}):`, positions);
  }

  await browser.close();
}

testStickyCards().catch(console.error);
