const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function compareHeaderStyles() {
  const browser = await chromium.launch();
  
  // Page 1: live
  const pageLive = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await pageLive.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });
  await pageLive.waitForTimeout(1000);

  const liveHeader = await pageLive.evaluate(() => {
    const header = document.querySelector('[data-elementor-id="171"]');
    const all = header.querySelectorAll('*');
    const res = [];
    for (const el of all) {
      const s = window.getComputedStyle(el);
      const r = el.getBoundingClientRect();
      res.push({
        tag: el.tagName,
        cls: el.className,
        rect: { top: r.top, height: r.height, bottom: r.bottom },
        fontFamily: s.fontFamily,
        fontSize: s.fontSize,
        lineHeight: s.lineHeight,
        height: s.height,
        minHeight: s.minHeight,
        padding: `${s.paddingTop} ${s.paddingRight} ${s.paddingBottom} ${s.paddingLeft}`,
        margin: `${s.marginTop} ${s.marginRight} ${s.marginBottom} ${s.marginLeft}`,
      });
    }
    return res;
  });

  // Let's also check body and html
  const liveRoot = await pageLive.evaluate(() => {
    const b = window.getComputedStyle(document.body);
    const h = window.getComputedStyle(document.documentElement);
    return {
      html: { fontSize: h.fontSize, lineHeight: h.lineHeight, fontFamily: h.fontFamily },
      body: { fontSize: b.fontSize, lineHeight: b.lineHeight, fontFamily: b.fontFamily }
    };
  });

  console.log("LIVE ROOT:", liveRoot);
  console.log(`Total elements in header: ${liveHeader.length}`);
  
  for (let i = 0; i < liveHeader.length; i++) {
    const el = liveHeader[i];
    if (el.rect.height > 0) {
      console.log(`[#${i}] <${el.tag} class="${el.cls.substring(0, 40)}"> height=${el.rect.height}, fontSize=${el.fontSize}, lineHeight=${el.lineHeight}, pad=${el.padding}, mar=${el.margin}`);
    }
  }

  await browser.close();
}

compareHeaderStyles().catch(console.error);
