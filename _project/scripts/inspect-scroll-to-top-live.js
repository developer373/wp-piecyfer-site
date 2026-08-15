const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectScrollToTopLive() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  // Scroll down so scroll-to-top appears
  await page.evaluate(() => window.scrollTo(0, 1000));
  await page.waitForTimeout(300);

  const data = await page.evaluate(() => {
    const stt = document.querySelector('#scroll-to-top');
    const wa = document.querySelector('.ht-ctc, [class*="whatsapp"], [class*="click-to-chat"]');
    return {
      stt: stt ? {
        rect: stt.getBoundingClientRect(),
        computed: {
          left: window.getComputedStyle(stt).left,
          right: window.getComputedStyle(stt).right,
          bottom: window.getComputedStyle(stt).bottom,
          display: window.getComputedStyle(stt).display,
          opacity: window.getComputedStyle(stt).opacity,
          transform: window.getComputedStyle(stt).transform,
          bg: window.getComputedStyle(stt).backgroundColor,
          color: window.getComputedStyle(stt).color,
          border: window.getComputedStyle(stt).border,
        }
      } : null,
      wa: wa ? {
        rect: wa.getBoundingClientRect(),
        computed: {
          left: window.getComputedStyle(wa).left,
          right: window.getComputedStyle(wa).right,
          bottom: window.getComputedStyle(wa).bottom,
        }
      } : null
    };
  });

  console.log('SCROLL TO TOP & WHATSAPP DATA ON LIVE:', JSON.stringify(data, null, 2));
  await browser.close();
}

inspectScrollToTopLive().catch(console.error);
