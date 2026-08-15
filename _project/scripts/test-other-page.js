const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testOtherPage() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

  await page.goto('http://localhost/piecyfer/cloud-services/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const headingEl = page.locator('text=Industries We Serve');
  if (await headingEl.count() > 0) {
    await headingEl.scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);
    await page.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_cloud_services_industries.png' });
    console.log('Saved cloud services screenshot successfully.');
  }

  await browser.close();
}

testOtherPage().catch(console.error);
