const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testInnerSectionSticky() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Add the CSS to inner sections within section-062c684
  await page.addStyleTag({
    content: `
      .elementor-element-062c684 .elementor-inner-section:nth-of-type(1) {
        position: sticky;
        top: 80px;
        z-index: 1;
      }
      .elementor-element-062c684 .elementor-inner-section:nth-of-type(2) {
        position: sticky;
        top: 120px;
        z-index: 2;
      }
      .elementor-element-062c684 .elementor-inner-section:nth-of-type(3) {
        position: sticky;
        top: 160px;
        z-index: 3;
      }
    `
  });

  const scrollYBase = await page.evaluate(() => {
    const el = document.querySelector('.elementor-element-062c684');
    return el.getBoundingClientRect().top + window.scrollY;
  });

  console.log(`Base scrollY for section-062c684: ${scrollYBase}`);

  for (let offset = 0; offset <= 2000; offset += 300) {
    const sy = scrollYBase - 80 + offset;
    await page.evaluate((y) => window.scrollTo(0, y), sy);
    await page.waitForTimeout(100);

    const positions = await page.evaluate(() => {
      const sections = Array.from(document.querySelectorAll('.elementor-element-062c684 .elementor-inner-section'));
      return sections.map((s, idx) => {
        const title = s.querySelector('h1,h2,h3,h4,h5,h6')?.textContent?.trim();
        const rect = s.getBoundingClientRect();
        return {
          cardIndex: idx + 1,
          title,
          top: rect.top.toFixed(1),
          height: rect.height.toFixed(1)
        };
      });
    });

    console.log(`\nScroll offset +${offset}px (scrollY ${sy.toFixed(0)}):`);
    for (const p of positions) {
      console.log(`  Card ${p.cardIndex} [${p.title}]: top = ${p.top}px`);
    }
  }

  await browser.close();
}

testInnerSectionSticky().catch(console.error);
