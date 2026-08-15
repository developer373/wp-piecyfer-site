const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testResponsive() {
  const browser = await chromium.launch({ headless: true });
  
  const viewports = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'laptop', width: 1024, height: 768 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 375, height: 667 }
  ];

  for (const vp of viewports) {
    console.log(`\nTesting ${vp.name} (${vp.width}x${vp.height})...`);
    
    // Live
    const pageLive = await browser.newPage({ viewport: { width: vp.width, height: vp.height } });
    await pageLive.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

    const liveData = await pageLive.evaluate(() => {
      const c1 = document.querySelector('.elementor-element-9a128b1');
      const c2 = document.querySelector('.elementor-element-69babdd');
      const c3 = document.querySelector('.elementor-element-73afe75');
      const s1 = c1 ? window.getComputedStyle(c1) : null;
      const s2 = c2 ? window.getComputedStyle(c2) : null;
      const s3 = c3 ? window.getComputedStyle(c3) : null;
      
      const t1 = document.querySelector('.elementor-element-23575fc');
      const t2 = document.querySelector('.elementor-element-eafec62');
      const t3 = document.querySelector('.elementor-element-e40cd70');

      return {
        c1: s1 ? { position: s1.position, top: s1.top, zIndex: s1.zIndex, margin: s1.margin, padding: s1.padding } : null,
        c2: s2 ? { position: s2.position, top: s2.top, zIndex: s2.zIndex, margin: s2.margin, padding: s2.padding } : null,
        c3: s3 ? { position: s3.position, top: s3.top, zIndex: s3.zIndex, margin: s3.margin, padding: s3.padding } : null,
        t3Padding: t3 ? window.getComputedStyle(t3).padding : null,
        t3Radius: t3 ? window.getComputedStyle(t3).borderRadius : null,
      };
    });

    console.log(`[LIVE ${vp.name}]`, JSON.stringify(liveData, null, 2));

    // Scroll to OnlineDoc card and take screenshot on live
    await pageLive.evaluate(() => {
      const onlinedoc = Array.from(document.querySelectorAll('h2, h3, h1')).find(el => el.textContent.includes('OnlineDoc'));
      if (onlinedoc) {
        onlinedoc.closest('.projects')?.scrollIntoView({ block: 'center' });
      }
    });
    await pageLive.waitForTimeout(300);
    await pageLive.screenshot({ path: path.resolve(__dirname, `../snapshots/live_${vp.name}_card.png`) });

    await pageLive.close();
  }

  await browser.close();
}

testResponsive().catch(console.error);
