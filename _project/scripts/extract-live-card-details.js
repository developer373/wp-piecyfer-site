const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function extractLiveCardDetails() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  
  const pageLive = await context.newPage();
  await pageLive.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  // Get live styles and matched CSS rules for the cards and their wrapper
  const liveInfo = await pageLive.evaluate(() => {
    // Find all style elements and search for rules affecting .projects, .elementor-element-062c684, sticky, etc.
    const rules = [];
    for (const sheet of Array.from(document.styleSheets)) {
      try {
        for (const rule of Array.from(sheet.cssRules || [])) {
          if (rule.cssText && (
            rule.cssText.includes('projects') ||
            rule.cssText.includes('062c684') ||
            rule.cssText.includes('9a128b1') ||
            rule.cssText.includes('69babdd') ||
            rule.cssText.includes('73afe75') ||
            rule.cssText.includes('sticky') ||
            rule.cssText.includes('23575fc') ||
            rule.cssText.includes('eafec62') ||
            rule.cssText.includes('e40cd70')
          )) {
            rules.push(rule.cssText);
          }
        }
      } catch (e) {}
    }

    // Also get the exact sticky/fixed script or inline style logic if any
    const container = document.querySelector('.elementor-element-062c684');
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    return {
      matchedRules: rules,
      containerData: container ? {
        className: container.className,
        dataset: container.dataset,
        style: container.getAttribute('style')
      } : null,
      c1Data: c1 ? { className: c1.className, dataset: c1.dataset, style: c1.getAttribute('style') } : null,
      c2Data: c2 ? { className: c2.className, dataset: c2.dataset, style: c2.getAttribute('style') } : null,
      c3Data: c3 ? { className: c3.className, dataset: c3.dataset, style: c3.getAttribute('style') } : null,
    };
  });

  console.log('LIVE RULES & DATA:', JSON.stringify(liveInfo, null, 2));

  // Now let's scroll to the OnlineDoc card on both live and local, and take screenshots!
  // Find OnlineDoc on live
  await pageLive.evaluate(() => {
    const onlinedoc = Array.from(document.querySelectorAll('h2, h3, h1')).find(el => el.textContent.includes('OnlineDoc'));
    if (onlinedoc) {
      onlinedoc.closest('.projects')?.scrollIntoView({ block: 'center' });
    }
  });
  await pageLive.waitForTimeout(500);

  const liveScreenshot = path.resolve(__dirname, '../snapshots/live_onlinedoc_card.png');
  await pageLive.screenshot({ path: liveScreenshot });
  console.log('Live OnlineDoc screenshot saved to:', liveScreenshot);

  // Now local
  const pageLocal = await context.newPage();
  await pageLocal.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });
  await pageLocal.evaluate(() => {
    const onlinedoc = Array.from(document.querySelectorAll('h2, h3, h1')).find(el => el.textContent.includes('OnlineDoc'));
    if (onlinedoc) {
      onlinedoc.closest('.projects')?.scrollIntoView({ block: 'center' });
    }
  });
  await pageLocal.waitForTimeout(500);

  const localScreenshot = path.resolve(__dirname, '../snapshots/local_onlinedoc_card.png');
  await pageLocal.screenshot({ path: localScreenshot });
  console.log('Local OnlineDoc screenshot saved to:', localScreenshot);

  await browser.close();
}

extractLiveCardDetails().catch(console.error);
