const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectWidget() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/category/erp/', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const w = document.querySelector('[data-widget_type*="archive-posts"], .elementor-widget-archive-posts');
    if (!w) return null;
    const articles = w.querySelectorAll('article');
    const res = [];
    for (const a of articles) {
      const r = a.getBoundingClientRect();
      res.push({
        class: a.className,
        rect: { top: r.top + window.scrollY, height: r.height, bottom: r.bottom + window.scrollY },
        title: a.querySelector('.elementor-post__title')?.innerText
      });
    }
    return {
      widgetRect: w.getBoundingClientRect(),
      articles: res
    };
  });

  console.log("ARCHIVE POSTS DETAILS:", JSON.stringify(data, null, 2));
  await browser.close();
}

inspectWidget().catch(console.error);
