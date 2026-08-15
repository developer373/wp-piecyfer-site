const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

async function main() {
  const browser = await chromium.launch();
  
  // Page 1: current live URL
  const pageLive = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await pageLive.goto('http://localhost/piecyfer/contact-us/', { waitUntil: 'networkidle' });
  await pageLive.waitForTimeout(1000);

  // Extract all elements with their selector, rect, and computed style
  const liveData = await pageLive.evaluate(() => {
    function getPath(el) {
      if (el.id) return '#' + el.id;
      if (el === document.body) return 'body';
      let path = el.tagName.toLowerCase();
      if (el.className && typeof el.className === 'string') {
        path += '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.');
      }
      return getPath(el.parentElement) + ' > ' + path;
    }

    const all = document.querySelectorAll('*');
    const res = [];
    for (const el of all) {
      if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE' || el.tagName === 'LINK' || el.tagName === 'NOSCRIPT') continue;
      const r = el.getBoundingClientRect();
      if (r.height === 0 && r.width === 0) continue;
      res.push({
        tag: el.tagName,
        cls: el.className,
        id: el.id,
        top: Math.round(r.top + window.scrollY),
        height: Math.round(r.height),
        bottom: Math.round(r.bottom + window.scrollY),
        outerHTML: el.outerHTML.substring(0, 80)
      });
    }
    return {
      scrollHeight: document.documentElement.scrollHeight,
      elements: res
    };
  });

  console.log("Live scrollHeight:", liveData.scrollHeight);

  // Now inspect the ref3-a saved HTML rendered locally
  const htmlRef = fs.readFileSync(path.join(__dirname, '../snapshots/ref3-a/html/contact-us.html'), 'utf8');
  const pageRef = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await pageRef.setContent(htmlRef, { waitUntil: 'networkidle' });
  await pageRef.waitForTimeout(1000);

  const refData = await pageRef.evaluate(() => {
    const all = document.querySelectorAll('*');
    const res = [];
    for (const el of all) {
      if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE' || el.tagName === 'LINK' || el.tagName === 'NOSCRIPT') continue;
      const r = el.getBoundingClientRect();
      if (r.height === 0 && r.width === 0) continue;
      res.push({
        tag: el.tagName,
        cls: el.className,
        id: el.id,
        top: Math.round(r.top + window.scrollY),
        height: Math.round(r.height),
        bottom: Math.round(r.bottom + window.scrollY),
        outerHTML: el.outerHTML.substring(0, 80)
      });
    }
    return {
      scrollHeight: document.documentElement.scrollHeight,
      elements: res
    };
  });

  console.log("Ref scrollHeight:", refData.scrollHeight);
  console.log("Diff in scrollHeight:", liveData.scrollHeight - refData.scrollHeight);

  // Find elements where height differs
  console.log("\nComparing element heights:");
  const minLen = Math.min(liveData.elements.length, refData.elements.length);
  for (let i = 0; i < minLen; i++) {
    const elLive = liveData.elements[i];
    const elRef = refData.elements[i];
    if (elLive.height !== elRef.height || elLive.top !== elRef.top) {
      console.log(`Diff at element #${i}:`);
      console.log(`  REF: top=${elRef.top}, height=${elRef.height}, HTML: ${elRef.outerHTML}`);
      console.log(`  LIVE: top=${elLive.top}, height=${elLive.height}, HTML: ${elLive.outerHTML}`);
      if (Math.abs(elLive.top - elRef.top) > 0) {
        // First element where top shifted!
        console.log("  >>> FIRST ELEMENT WHERE POSITION SHIFTED <<<");
        break;
      }
    }
  }

  await browser.close();
}

main().catch(console.error);
