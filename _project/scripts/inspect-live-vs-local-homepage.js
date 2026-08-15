const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');

async function compareLiveAndLocal() {
  const browser = await chromium.launch({ headless: true });
  const outDir = path.resolve(__dirname, '../snapshots/live_comparison');
  if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  // 1. Capture Local
  console.log('Capturing local http://localhost/piecyfer/ ...');
  const pageLocal = await context.newPage();
  await pageLocal.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await pageLocal.screenshot({ path: path.join(outDir, 'local_full.png'), fullPage: true });
  
  // Capture specific sections
  const localHeader = await pageLocal.$('header.main-header, header, [data-elementor-type="header"]');
  if (localHeader) await localHeader.screenshot({ path: path.join(outDir, 'local_header.png') });

  // 2. Capture Live
  console.log('Capturing live https://www.piecyfer.com/ ...');
  const pageLive = await context.newPage();
  try {
    await pageLive.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle', timeout: 30000 });
    await pageLive.screenshot({ path: path.join(outDir, 'live_full.png'), fullPage: true });
    
    const liveHeader = await pageLive.$('header.main-header, header, [data-elementor-type="header"]');
    if (liveHeader) await liveHeader.screenshot({ path: path.join(outDir, 'live_header.png') });
    console.log('Live captured successfully!');
  } catch (e) {
    console.log('Error capturing live site:', e.message);
  }

  await browser.close();
  console.log('Comparison screenshots saved to _project/snapshots/live_comparison/');
}

compareLiveAndLocal().catch(console.error);
