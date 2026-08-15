const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));

async function checkHeaderNow() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/this-url-does-not-exist-404-test', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const topBar = document.querySelector('.elementor-element-71f63279');
    const mainHeader = document.querySelector('.elementor-element-f4ae4d6');
    const search = document.querySelector('.elementor-element-4c5df4ea');
    const social = document.querySelector('.elementor-element-eec513b');

    return {
      topBar: topBar?.getBoundingClientRect(),
      mainHeader: mainHeader?.getBoundingClientRect(),
      search: search?.getBoundingClientRect(),
      social: social?.getBoundingClientRect(),
      totalHeaderHeight: (topBar?.getBoundingClientRect().height || 0) + (mainHeader?.getBoundingClientRect().height || 0)
    };
  });

  console.log("LIVE HEADER GEOMETRY NOW:", JSON.stringify(data, null, 2));
  await browser.close();
}

checkHeaderNow().catch(console.error);
