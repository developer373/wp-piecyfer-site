const http = require('http');
const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectCSSRules() {
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
  
  await new Promise(r => server.listen(9877, r));

  const browser = await chromium.launch({ args: ['--disable-web-security'] });
  const pageRef = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await pageRef.goto('http://localhost:9877/', { waitUntil: 'networkidle' });

  // Get matching CSS rules for elementskit-navbar-nav and section 71f63279
  const rules = await pageRef.evaluate(() => {
    const el1 = document.querySelector('.elementskit-menu-container');
    const el2 = document.querySelector('.elementor-element-71f63279');
    const el3 = document.querySelector('.elementor-element-f4ae4d6');
    
    function getMatchedRules(el) {
      if (!el) return [];
      const res = [];
      for (const sheet of document.styleSheets) {
        try {
          for (const rule of sheet.cssRules) {
            if (rule.selectorText && el.matches(rule.selectorText)) {
              res.push({
                sheet: sheet.href || sheet.ownerNode?.id || 'inline',
                selector: rule.selectorText,
                cssText: rule.cssText
              });
            }
          }
        } catch (e) {}
      }
      return res;
    }

    return {
      el1Rules: getMatchedRules(el1),
      el2Rules: getMatchedRules(el2),
      el3Rules: getMatchedRules(el3)
    };
  });

  console.log("RULES FOR .elementskit-menu-container:", JSON.stringify(rules.el1Rules, null, 2));
  console.log("RULES FOR .elementor-element-71f63279 (top bar):", JSON.stringify(rules.el2Rules, null, 2));
  console.log("RULES FOR .elementor-element-f4ae4d6 (main header):", JSON.stringify(rules.el3Rules, null, 2));

  await browser.close();
  server.close();
}

inspectCSSRules().catch(console.error);
