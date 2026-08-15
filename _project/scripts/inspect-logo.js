const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLogo() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const logoWidget = document.querySelector('.elementor-widget-theme-site-logo');
    const logoImg = logoWidget ? logoWidget.querySelector('img') : null;
    const logoWrap = logoWidget ? logoWidget.closest('.elementor-widget-wrap') : null;
    const logoCol = logoWidget ? logoWidget.closest('.elementor-column') : null;

    function getCSS(el) {
      if (!el) return null;
      const st = window.getComputedStyle(el);
      return {
        tag: el.tagName,
        cls: el.className,
        rect: el.getBoundingClientRect(),
        padding: `${st.paddingTop} ${st.paddingRight} ${st.paddingBottom} ${st.paddingLeft}`,
        margin: `${st.marginTop} ${st.marginRight} ${st.marginBottom} ${st.marginLeft}`
      };
    }

    return {
      logoWidget: getCSS(logoWidget),
      logoImg: getCSS(logoImg),
      logoWrap: getCSS(logoWrap),
      logoCol: getCSS(logoCol)
    };
  });

  console.log("LOGO DATA:", JSON.stringify(data, null, 2));
  await browser.close();
}

inspectLogo().catch(console.error);
