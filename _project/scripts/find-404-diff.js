const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function findGlobalDiff() {
  const browser = await chromium.launch();
  
  // Page 1: live
  const pageLive = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await pageLive.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });

  // Get all top-level sections (Header, Body / 404 template, Footer)
  const liveSections = await pageLive.evaluate(() => {
    const header = document.querySelector('[data-elementor-type="header"]');
    const footer = document.querySelector('[data-elementor-type="footer"]');
    const error404 = document.querySelector('[data-elementor-type="error-404"]');
    const body = document.body;
    
    function getInfo(el, name) {
      if (!el) return { name, exists: false };
      const r = el.getBoundingClientRect();
      const s = window.getComputedStyle(el);
      return {
        name,
        exists: true,
        tag: el.tagName,
        class: el.className,
        rect: { top: r.top + window.scrollY, height: r.height, bottom: r.bottom + window.scrollY },
        margin: `${s.marginTop} ${s.marginRight} ${s.marginBottom} ${s.marginLeft}`,
        padding: `${s.paddingTop} ${s.paddingRight} ${s.paddingBottom} ${s.paddingLeft}`,
        border: `${s.borderTopWidth} ${s.borderRightWidth} ${s.borderBottomWidth} ${s.borderLeftWidth}`,
      };
    }
    
    return {
      scrollHeight: document.documentElement.scrollHeight,
      body: getInfo(body, 'body'),
      header: getInfo(header, 'header'),
      error404: getInfo(error404, 'error404'),
      footer: getInfo(footer, 'footer'),
      headerChildren: Array.from(header ? header.children : []).map(c => getInfo(c, 'headerChild')),
      errorChildren: Array.from(error404 ? error404.children : []).map(c => getInfo(c, 'errorChild')),
      footerChildren: Array.from(footer ? footer.children : []).map(c => getInfo(c, 'footerChild')),
    };
  });

  console.log("LIVE 404 DETAILS:", JSON.stringify(liveSections, null, 2));

  await browser.close();
}

findGlobalDiff().catch(console.error);
