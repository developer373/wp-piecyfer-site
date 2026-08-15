const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');
const path = require('path');

async function compare404Sections() {
  const browser = await chromium.launch();
  
  // Page 1: live
  const page1 = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page1.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });
  
  const liveBoxes = await page1.evaluate(() => {
    const all = Array.from(document.querySelectorAll('.elementor-section, .elementor-top-section, footer, header, #content, #main, .elementor-location-header, .elementor-location-footer'));
    return all.map(el => {
      const r = el.getBoundingClientRect();
      return {
        tag: el.tagName,
        id: el.id,
        cls: el.className,
        rect: { top: r.top, height: r.height, bottom: r.bottom }
      };
    });
  });

  console.log("LIVE 404 SECTIONS:");
  liveBoxes.forEach(b => console.log(`  - ${b.tag}.${b.cls.split(' ')[0]} top=${b.rect.top} h=${b.rect.height} b=${b.rect.bottom}`));
  
  await browser.close();
}

compare404Sections().catch(console.error);
