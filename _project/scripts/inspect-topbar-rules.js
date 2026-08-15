const http = require('http');
const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function main() {
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
  
  await new Promise(r => server.listen(9878, r));

  const browser = await chromium.launch({ args: ['--disable-web-security'] });
  
  const pageRef = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await pageRef.goto('http://localhost:9878/', { waitUntil: 'networkidle' });
  await pageRef.waitForTimeout(1000);

  const pageLive = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await pageLive.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });
  await pageLive.waitForTimeout(1000);

  // Inspect element #0 (.elementor-element-71f63279) and all its ancestors & children
  const result = await pageLive.evaluate(() => {
    function dumpRules(el) {
      const res = [];
      for (const sheet of document.styleSheets) {
        try {
          for (const rule of sheet.cssRules) {
            if (rule.selectorText && el.matches(rule.selectorText)) {
              res.push({
                origin: sheet.href || sheet.ownerNode?.id || 'inline',
                selector: rule.selectorText,
                cssText: rule.cssText
              });
            }
          }
        } catch (e) {}
      }
      return res;
    }
    const el = document.querySelector('.elementor-element-71f63279');
    return {
      classes: el.className,
      rules: dumpRules(el),
      computed: {
        height: window.getComputedStyle(el).height,
        minHeight: window.getComputedStyle(el).minHeight,
        paddingTop: window.getComputedStyle(el).paddingTop,
        paddingBottom: window.getComputedStyle(el).paddingBottom,
      }
    };
  });

  const resultRef = await pageRef.evaluate(() => {
    function dumpRules(el) {
      const res = [];
      for (const sheet of document.styleSheets) {
        try {
          for (const rule of sheet.cssRules) {
            if (rule.selectorText && el.matches(rule.selectorText)) {
              res.push({
                origin: sheet.href || sheet.ownerNode?.id || 'inline',
                selector: rule.selectorText,
                cssText: rule.cssText
              });
            }
          }
        } catch (e) {}
      }
      return res;
    }
    const el = document.querySelector('.elementor-element-71f63279');
    return {
      classes: el.className,
      rules: dumpRules(el),
      computed: {
        height: window.getComputedStyle(el).height,
        minHeight: window.getComputedStyle(el).minHeight,
        paddingTop: window.getComputedStyle(el).paddingTop,
        paddingBottom: window.getComputedStyle(el).paddingBottom,
      }
    };
  });

  console.log("REF RESULT:", JSON.stringify(resultRef, null, 2));
  console.log("LIVE RESULT:", JSON.stringify(result, null, 2));

  await browser.close();
  server.close();
}

main().catch(console.error);
