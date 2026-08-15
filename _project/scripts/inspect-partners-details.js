const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');

async function inspectPartnersDetails() {
  const browser = await chromium.launch({ headless: true });
  
  // 1. Live
  const livePage = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
  await livePage.goto('https://www.piecyfer.com/our-team/', { waitUntil: 'networkidle' });
  await livePage.waitForTimeout(500);

  const liveData = await livePage.evaluate(() => {
    const heading = Array.from(document.querySelectorAll('h2')).find(h => h.textContent.includes('FOUNDING'));
    const headingSection = heading?.closest('.elementor-top-section');
    const cardsSection = headingSection?.nextElementSibling;

    // Get all custom HTML or CSS or scripts inside cardsSection
    const html = cardsSection ? cardsSection.outerHTML : '';
    
    // Check all styles on cards
    const cards = Array.from(cardsSection?.querySelectorAll('.elementor-column, .team-card, .founder-card, .elementor-widget, .elementor-widget-html, [class*="card"], [class*="founder"]') || []);
    
    const cardStyles = cards.map(c => ({
      className: c.className,
      tag: c.tagName,
      html: c.innerHTML.length < 500 ? c.innerHTML : c.innerHTML.substring(0, 500) + '...',
      computed: {
        transform: window.getComputedStyle(c).transform,
        transition: window.getComputedStyle(c).transition,
        borderRadius: window.getComputedStyle(c).borderRadius,
        overflow: window.getComputedStyle(c).overflow,
        position: window.getComputedStyle(c).position,
      }
    }));

    return {
      cardsSectionHTML: html,
      cardStyles,
      scripts: Array.from(document.querySelectorAll('script')).map(s => s.src || s.innerHTML).filter(s => s.includes('founder') || s.includes('team') || s.includes('scroll') || s.includes('card'))
    };
  });

  fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\live_cards_section.html', liveData.cardsSectionHTML, 'utf8');
  console.log('LIVE CARDS STYLES:\n', JSON.stringify(liveData.cardStyles.slice(0, 5), null, 2));

  // 2. Local
  const localPage = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
  await localPage.goto('http://localhost/piecyfer/our-team/', { waitUntil: 'networkidle' });
  await localPage.waitForTimeout(500);

  const localData = await localPage.evaluate(() => {
    const heading = Array.from(document.querySelectorAll('h2')).find(h => h.textContent.includes('FOUNDING'));
    const headingSection = heading?.closest('.elementor-top-section');
    const cardsSection = headingSection?.nextElementSibling;
    return {
      cardsSectionHTML: cardsSection ? cardsSection.outerHTML : '',
    };
  });

  fs.writeFileSync('D:\\laragon\\www\\piecyfer\\_project\\snapshots\\local_cards_section.html', localData.cardsSectionHTML, 'utf8');

  await browser.close();
}

inspectPartnersDetails().catch(console.error);
