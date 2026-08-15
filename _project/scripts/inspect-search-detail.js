const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectSearchDetail() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const s = document.querySelector('.elementor-element-4c5df4ea');
    function getCSS(el) {
      if (!el) return null;
      const st = window.getComputedStyle(el);
      return {
        tag: el.tagName,
        cls: el.className,
        rect: el.getBoundingClientRect(),
        padding: `${st.paddingTop} ${st.paddingRight} ${st.paddingBottom} ${st.paddingLeft}`,
        margin: `${st.marginTop} ${st.marginRight} ${st.marginBottom} ${st.marginLeft}`,
        border: `${st.borderTopWidth} ${st.borderRightWidth} ${st.borderBottomWidth} ${st.borderLeftWidth}`,
        height: st.height,
        minHeight: st.minHeight,
        fontSize: st.fontSize,
        lineHeight: st.lineHeight,
        boxSizing: st.boxSizing
      };
    }

    return {
      widget: getCSS(s),
      container: getCSS(s.querySelector('.elementor-search-form__container')),
      input: getCSS(s.querySelector('input')),
      btn: getCSS(s.querySelector('button')),
      svg: getCSS(s.querySelector('svg'))
    };
  });

  console.log("SEARCH DETAIL:", JSON.stringify(data, null, 2));
  await browser.close();
}

inspectSearchDetail().catch(console.error);
