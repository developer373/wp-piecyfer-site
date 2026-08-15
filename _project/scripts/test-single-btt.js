const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testSingleBtt() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Add the CSS
  await page.addStyleTag({
    content: `
      #scroll-to-top {
        display: none !important;
      }
      .ekit-back-to-top-container.ekit-btt {
        left: 30px !important;
        right: auto !important;
        bottom: 30px !important;
      }
    `
  });

  await page.evaluate(() => window.scrollTo(0, 3000));
  await page.waitForTimeout(300);

  const shot = path.resolve(__dirname, '../snapshots/clean_btt_left.png');
  await page.screenshot({ path: shot });
  console.log('Saved clean BTT screenshot to:', shot);

  const bttInfo = await page.evaluate(() => {
    const ekitBtt = document.querySelector('.ekit-back-to-top-container, .ekit-btt__button');
    const wa = document.querySelector('.ht-ctc-chat');
    return {
      bttRect: ekitBtt ? ekitBtt.getBoundingClientRect() : null,
      waRect: wa ? wa.getBoundingClientRect() : null
    };
  });

  console.log('BTT and WhatsApp Positions:', JSON.stringify(bttInfo, null, 2));
  await browser.close();
}

testSingleBtt().catch(console.error);
