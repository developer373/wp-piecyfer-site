const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectSocial() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const s = document.querySelector('.elementor-element-eec513b');
    const a = s.querySelector('a');
    const svg = s.querySelector('svg');
    
    function getCSS(el) {
      const st = window.getComputedStyle(el);
      return {
        tag: el.tagName,
        class: el.className,
        rect: el.getBoundingClientRect(),
        padding: `${st.paddingTop} ${st.paddingRight} ${st.paddingBottom} ${st.paddingLeft}`,
        margin: `${st.marginTop} ${st.marginRight} ${st.marginBottom} ${st.marginLeft}`,
        fontSize: st.fontSize,
        lineHeight: st.lineHeight,
        display: st.display,
        alignItems: st.alignItems,
      };
    }
    
    return {
      widget: getCSS(s),
      anchor: getCSS(a),
      svg: getCSS(svg)
    };
  });

  console.log("SOCIAL ICONS COMPUTED:", JSON.stringify(data, null, 2));
  await browser.close();
}

inspectSocial().catch(console.error);
