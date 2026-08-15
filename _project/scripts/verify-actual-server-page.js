const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function verifyNativeServer() {
  const browser = await chromium.launch({ headless: true });
  
  // 1. Desktop Test
  const pageDesktop = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await pageDesktop.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  const baseTop = await pageDesktop.evaluate(() => {
    return document.querySelector('.elementor-element-062c684').getBoundingClientRect().top + window.scrollY;
  });

  // Scroll to full stack
  await pageDesktop.evaluate((y) => window.scrollTo(0, y), baseTop + 1450);
  await pageDesktop.waitForTimeout(400);

  const desktopShot = path.resolve(__dirname, '../snapshots/final_perfect_native_desktop.png');
  await pageDesktop.screenshot({ path: desktopShot });
  console.log('Saved final desktop snapshot to:', desktopShot);

  // 2. Mobile Test
  const pageMobile = await browser.newPage({ viewport: { width: 375, height: 812 } });
  await pageMobile.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  
  await pageMobile.evaluate(() => {
    const onlinedoc = Array.from(document.querySelectorAll('h2, h3, h1')).find(el => el.textContent.includes('OnlineDoc'));
    if (onlinedoc) {
      onlinedoc.closest('.projects')?.scrollIntoView({ block: 'center' });
    }
  });
  await pageMobile.waitForTimeout(400);

  const mobileShot = path.resolve(__dirname, '../snapshots/final_perfect_native_mobile.png');
  await pageMobile.screenshot({ path: mobileShot });
  console.log('Saved final mobile snapshot to:', mobileShot);

  await browser.close();
}

verifyNativeServer().catch(console.error);
