const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function checkFullOurTeamPage() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

  await page.goto('http://localhost/piecyfer/our-team/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  // Check all images on the page
  const imagesStatus = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('img')).map(img => ({
      src: img.src,
      alt: img.alt,
      complete: img.complete,
      naturalWidth: img.naturalWidth,
      naturalHeight: img.naturalHeight,
    }));
  });

  const broken = imagesStatus.filter(i => i.naturalWidth === 0);
  console.log('Total images on page:', imagesStatus.length);
  console.log('Broken images count:', broken.length);
  if (broken.length > 0) {
    console.log('Broken images:', JSON.stringify(broken, null, 2));
  }

  await page.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_our_team_fullpage.png', fullPage: true });
  console.log('Saved fullpage screenshot to local_our_team_fullpage.png');

  await browser.close();
}

checkFullOurTeamPage().catch(console.error);
