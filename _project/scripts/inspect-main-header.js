const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectMainHeader() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const header = document.querySelector('.elementor-element-f4ae4d6');
    if (!header) return null;
    const elements = header.querySelectorAll('*');
    return Array.from(elements).map(el => {
      const r = el.getBoundingClientRect();
      const st = window.getComputedStyle(el);
      return {
        tag: el.tagName,
        cls: el.className,
        height: r.height,
        width: r.width,
        top: r.top,
        padding: `${st.paddingTop} ${st.paddingBottom}`,
        margin: `${st.marginTop} ${st.marginBottom}`,
        fontSize: st.fontSize,
        lineHeight: st.lineHeight
      };
    }).filter(e => e.height > 40);
  });

  console.log("MAIN HEADER ELEMENTS > 40px:", JSON.stringify(data, null, 2));
  await browser.close();
}

inspectMainHeader().catch(console.error);
