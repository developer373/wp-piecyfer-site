const http = require('http');
const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function main() {
  // Start simple static server for snapshots/ref3-a/html
  const server = http.createServer((req, res) => {
    let filePath = path.join(__dirname, '../snapshots/ref3-a/html', req.url === '/' ? 'this-url-does-not-exist-404-test.html' : req.url);
    if (fs.existsSync(filePath)) {
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      fs.createReadStream(filePath).pipe(res);
    } else {
      res.writeHead(404);
      res.end();
    }
  });
  
  await new Promise(r => server.listen(9876, r));
  console.log("Static server running on port 9876");

  const browser = await chromium.launch();
  
  const pageRef = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await pageRef.goto('http://localhost:9876/', { waitUntil: 'networkidle' });
  await pageRef.waitForTimeout(1000);

  const pageLive = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await pageLive.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });
  await pageLive.waitForTimeout(1000);

  const refData = await pageRef.evaluate(() => {
    const header = document.querySelector('[data-elementor-id="171"]');
    const all = header.querySelectorAll('*');
    return Array.from(all).map(el => {
      const r = el.getBoundingClientRect();
      const s = window.getComputedStyle(el);
      return {
        tag: el.tagName,
        cls: el.className ? (typeof el.className === 'string' ? el.className : el.className.baseVal) : '',
        top: r.top,
        height: r.height,
        bottom: r.bottom,
        fontSize: s.fontSize,
        lineHeight: s.lineHeight,
        marginTop: s.marginTop,
        marginBottom: s.marginBottom,
        paddingTop: s.paddingTop,
        paddingBottom: s.paddingBottom
      };
    });
  });

  const liveData = await pageLive.evaluate(() => {
    const header = document.querySelector('[data-elementor-id="171"]');
    const all = header.querySelectorAll('*');
    return Array.from(all).map(el => {
      const r = el.getBoundingClientRect();
      const s = window.getComputedStyle(el);
      return {
        tag: el.tagName,
        cls: el.className ? (typeof el.className === 'string' ? el.className : el.className.baseVal) : '',
        top: r.top,
        height: r.height,
        bottom: r.bottom,
        fontSize: s.fontSize,
        lineHeight: s.lineHeight,
        marginTop: s.marginTop,
        marginBottom: s.marginBottom,
        paddingTop: s.paddingTop,
        paddingBottom: s.paddingBottom
      };
    });
  });

  console.log(`Elements: ref=${refData.length}, live=${liveData.length}`);

  for (let i = 0; i < Math.min(refData.length, liveData.length); i++) {
    const r = refData[i];
    const l = liveData[i];
    if (Math.abs(r.height - l.height) > 0.1 || Math.abs(r.top - l.top) > 0.1) {
      console.log(`Diff at [#${i}] <${r.tag} class="${r.cls.substring(0, 40)}">:`);
      console.log(`  REF : top=${r.top}, h=${r.height}, fontSize=${r.fontSize}, lineH=${r.lineHeight}, padTop=${r.paddingTop}, padBtm=${r.paddingBottom}, marTop=${r.marginTop}, marBtm=${r.marginBottom}`);
      console.log(`  LIVE: top=${l.top}, h=${l.height}, fontSize=${l.fontSize}, lineH=${l.lineHeight}, padTop=${l.paddingTop}, padBtm=${l.paddingBottom}, marTop=${l.marginTop}, marBtm=${l.marginBottom}`);
      
      // Let's find out what child causes this
      if (Math.abs(r.height - l.height) > 0.1) {
        console.log(`  >>> HEIGHT DIFF: ${r.height - l.height}px`);
      }
    }
  }

  await browser.close();
  server.close();
}

main().catch(console.error);
