const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');

async function inspectIndustriesSection() {
  const browser = await chromium.launch({ headless: true });

  // 1. Live
  console.log('Inspecting Live Enterprise page...');
  const livePage = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
  await livePage.goto('https://www.piecyfer.com/enterprise-software-development/', { waitUntil: 'domcontentloaded' });
  await livePage.waitForTimeout(500);

  const liveData = await livePage.evaluate(() => {
    const heading = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .elementor-heading-title'))
      .find(h => h.textContent.includes('Industries') || h.textContent.includes('INDUSTRIES'));
    const section = heading ? heading.closest('.elementor-top-section') || heading.closest('.elementor-section') : null;
    
    // Find all images and boxes in this section or adjacent section
    const targetSection = section?.nextElementSibling?.classList.contains('elementor-section') ? section.parentElement : section;

    return {
      headingText: heading?.textContent,
      sectionHTML: section ? section.outerHTML : null,
      nextSectionHTML: section?.nextElementSibling ? section.nextElementSibling.outerHTML : null,
      images: Array.from(document.querySelectorAll('img')).map(i => ({ src: i.src, alt: i.alt })).filter(i => 
        i.src.includes('industr') || i.alt.includes('sales') || i.alt.includes('manufactur') || i.src.includes('sales') || i.src.includes('manufactur') || i.src.includes('icon')
      )
    };
  });

  console.log('LIVE DATA:\n', JSON.stringify({
    heading: liveData.headingText,
    images: liveData.images
  }, null, 2));

  if (liveData.sectionHTML) {
    fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_industries_section.html', liveData.sectionHTML + '\n' + (liveData.nextSectionHTML || ''), 'utf8');
  }

  // 2. Local
  console.log('Inspecting Local Enterprise page...');
  const localPage = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
  await localPage.goto('http://localhost/piecyfer/enterprise-software-development/', { waitUntil: 'domcontentloaded' });
  await localPage.waitForTimeout(500);

  const localData = await localPage.evaluate(() => {
    const heading = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .elementor-heading-title'))
      .find(h => h.textContent.includes('Industries') || h.textContent.includes('INDUSTRIES'));
    const section = heading ? heading.closest('.elementor-top-section') || heading.closest('.elementor-section') : null;

    return {
      headingText: heading?.textContent,
      sectionHTML: section ? section.outerHTML : null,
      nextSectionHTML: section?.nextElementSibling ? section.nextElementSibling.outerHTML : null,
    };
  });

  if (localData.sectionHTML) {
    fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_industries_section.html', localData.sectionHTML + '\n' + (localData.nextSectionHTML || ''), 'utf8');
  }

  // Capture local screenshot
  const headingEl = localPage.locator('text=Industries We Serve');
  if (await headingEl.count() > 0) {
    await headingEl.scrollIntoViewIfNeeded();
    await localPage.waitForTimeout(300);
    await localPage.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_industries_current.png' });
  }

  await browser.close();
}

inspectIndustriesSection().catch(console.error);
