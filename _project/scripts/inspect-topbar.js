const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectTopBar() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/contact-us/', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const s = document.querySelector('.elementor-element-71f63279');
    if (!s) return null;
    const widgets = s.querySelectorAll('.elementor-widget');
    const res = [];
    for (const w of widgets) {
      const r = w.getBoundingClientRect();
      const style = window.getComputedStyle(w);
      res.push({
        name: w.className,
        rect: { top: r.top, height: r.height, bottom: r.bottom },
        style: {
          fontSize: style.fontSize,
          lineHeight: style.lineHeight,
          padding: style.padding,
          margin: style.margin
        },
        html: w.outerHTML.substring(0, 150)
      });
    }
    return res;
  });

  console.log("TOP BAR WIDGETS:", JSON.stringify(data, null, 2));
  await browser.close();
}

inspectTopBar().catch(console.error);
