const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');

async function inspectOurTeam() {
  const browser = await chromium.launch({ headless: true });
  
  // 1. Inspect Live
  const livePage = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
  console.log('Navigating to Live Our Team page...');
  await livePage.goto('https://www.piecyfer.com/our-team/', { waitUntil: 'domcontentloaded' });
  await livePage.waitForTimeout(500);

  const liveFounding = await livePage.evaluate(() => {
    // Find the section with "THE FOUNDING PARTNERS"
    const headings = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .elementor-heading-title'));
    const foundingHeading = headings.find(h => h.textContent.includes('FOUNDING PARTNERS') || h.textContent.includes('Founding Partners'));
    
    // Find section containing it
    const section = foundingHeading ? foundingHeading.closest('.elementor-section, .elementor-element') : null;
    const parentSection = section ? section.closest('.elementor-top-section') || section : null;

    // Get all images and cards inside this section or next sections
    const cardsSection = parentSection ? parentSection.nextElementSibling || parentSection : null;

    const images = Array.from(document.querySelectorAll('img')).map(img => ({
      src: img.src,
      alt: img.alt,
      className: img.className,
      rect: img.getBoundingClientRect()
    })).filter(img => img.alt.includes('kamran') || img.alt.includes('noman') || img.alt.includes('zeeshan') || img.src.includes('kamran') || img.src.includes('noman') || img.src.includes('zeeshan') || img.src.includes('ceo') || img.src.includes('cto') || img.src.includes('coo'));

    return {
      headingText: foundingHeading?.textContent,
      parentSectionHTML: parentSection ? parentSection.outerHTML : null,
      parentSectionClass: parentSection ? parentSection.className : null,
      images: images,
    };
  });

  console.log('LIVE FOUNDING PARTNERS INSPECTION:\n', JSON.stringify({
    heading: liveFounding.headingText,
    images: liveFounding.images,
  }, null, 2));

  // Save live section HTML to file
  if (liveFounding.parentSectionHTML) {
    fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_founding_partners.html', liveFounding.parentSectionHTML, 'utf8');
  }

  // Take live screenshot
  await livePage.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_our_team.png' });

  // 2. Inspect Local
  const localPage = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
  console.log('Navigating to Local Our Team page...');
  await localPage.goto('http://localhost/piecyfer/our-team/', { waitUntil: 'domcontentloaded' });
  await localPage.waitForTimeout(500);

  const localFounding = await localPage.evaluate(() => {
    const headings = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .elementor-heading-title'));
    const foundingHeading = headings.find(h => h.textContent.includes('FOUNDING PARTNERS') || h.textContent.includes('Founding Partners'));
    const parentSection = foundingHeading ? foundingHeading.closest('.elementor-top-section') || foundingHeading.closest('.elementor-section') : null;

    const images = Array.from(document.querySelectorAll('img')).map(img => ({
      src: img.src,
      alt: img.alt,
      className: img.className,
      naturalWidth: img.naturalWidth,
      naturalHeight: img.naturalHeight,
    })).filter(img => img.alt.includes('kamran') || img.alt.includes('noman') || img.alt.includes('zeeshan') || img.src.includes('kamran') || img.src.includes('noman') || img.src.includes('zeeshan'));

    return {
      headingText: foundingHeading?.textContent,
      parentSectionHTML: parentSection ? parentSection.outerHTML : null,
      images: images,
    };
  });

  console.log('LOCAL FOUNDING PARTNERS INSPECTION:\n', JSON.stringify({
    heading: localFounding.headingText,
    images: localFounding.images,
  }, null, 2));

  if (localFounding.parentSectionHTML) {
    fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_founding_partners.html', localFounding.parentSectionHTML, 'utf8');
  }

  await localPage.screenshot({ path: 'D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_our_team.png' });

  await browser.close();
}

inspectOurTeam().catch(console.error);
