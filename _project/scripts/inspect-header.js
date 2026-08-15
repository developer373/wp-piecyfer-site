const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectHeader() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/contact-us/', { waitUntil: 'networkidle' });

  const headerDetails = await page.evaluate(() => {
    const header = document.querySelector('[data-elementor-id="171"]');
    if (!header) return null;
    
    const sections = header.querySelectorAll('.elementor-section');
    const res = [];
    for (const s of sections) {
      const r = s.getBoundingClientRect();
      const style = window.getComputedStyle(s);
      res.push({
        class: s.className,
        rect: { top: r.top, height: r.height, bottom: r.bottom },
        marginTop: style.marginTop,
        marginBottom: style.marginBottom,
        paddingTop: style.paddingTop,
        paddingBottom: style.paddingBottom,
      });
    }
    return {
      headerRect: header.getBoundingClientRect(),
      sections: res
    };
  });

  console.log("LIVE HEADER DETAILS:", JSON.stringify(headerDetails, null, 2));
  await browser.close();
}

inspectHeader().catch(console.error);
