const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLiveIndustries() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

  try {
    await page.goto('https://www.piecyfer.com/enterprise-software-development/', { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(1000);

    const items = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('.elementskit-client-slider-item, .single-client')).map(el => {
        const img = el.querySelector('img');
        return {
          title: el.getAttribute('title') || el.className,
          imgSrc: img ? img.src : null,
          imgAlt: img ? img.alt : null
        };
      });
    });

    console.log('LIVE ITEMS:', JSON.stringify(items, null, 2));

    const salesImg = items.find(i => i.imgSrc && (i.imgSrc.toLowerCase().includes('sale') || (i.title && i.title.toLowerCase().includes('sale'))));
    console.log('LIVE SALES IMG:', salesImg);

    // Also take screenshot of live industries section
    const heading = page.locator('text=Industries We Serve');
    if (await heading.count() > 0) {
      await heading.scrollIntoViewIfNeeded();
      await page.waitForTimeout(500);
      await page.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_industries_section_full.png' });
      console.log('Saved live screenshot');
    }
  } catch (err) {
    console.error('Error fetching live:', err.message);
  } finally {
    await browser.close();
  }
}

inspectLiveIndustries();
