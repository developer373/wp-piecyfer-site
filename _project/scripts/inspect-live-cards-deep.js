const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function deepInspect() {
  const browser = await chromium.launch({ headless: true });
  
  // Test both Live and Local
  for (const [name, url] of [['LIVE', 'https://www.piecyfer.com/'], ['LOCAL', 'http://localhost/piecyfer/']]) {
    console.log(`\n================== ${name}: ${url} ==================`);
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto(url, { waitUntil: 'networkidle' });

    const cardInfo = await page.evaluate(() => {
      // Find the container for the 3 project cards (Email Genius, TMS, OnlineDoc)
      // Usually .projects or elementor sections
      const projects = Array.from(document.querySelectorAll('.projects'));
      const sections = Array.from(document.querySelectorAll('.elementor-section'));
      
      const cardsData = projects.map((el, i) => {
        const cs = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        const parent = el.parentElement;
        const parentCs = parent ? window.getComputedStyle(parent) : null;
        
        // Check inner section or parent section
        const sectionParent = el.closest('.elementor-section');
        const sectionCs = sectionParent ? window.getComputedStyle(sectionParent) : null;

        return {
          index: i,
          id: el.id,
          classes: el.className,
          rect: { top: rect.top, left: rect.left, width: rect.width, height: rect.height },
          styles: {
            position: cs.position,
            top: cs.top,
            zIndex: cs.zIndex,
            backgroundColor: cs.backgroundColor,
            backgroundImage: cs.backgroundImage,
            boxShadow: cs.boxShadow,
            borderRadius: cs.borderRadius,
            border: cs.border,
            padding: cs.padding,
            margin: cs.margin,
            overflow: cs.overflow,
          },
          parentStyles: parentCs ? {
            classes: parent.className,
            position: parentCs.position,
            overflow: parentCs.overflow,
            padding: parentCs.padding,
            margin: parentCs.margin,
          } : null,
          sectionStyles: sectionCs ? {
            classes: sectionParent.className,
            position: sectionCs.position,
            top: sectionCs.top,
            zIndex: sectionCs.zIndex,
            overflow: sectionCs.overflow,
            padding: sectionCs.padding,
            margin: sectionCs.margin,
          } : null,
        };
      });

      // Also let's find any custom CSS or inline styles or Elementor element IDs
      const mainProjectsContainer = document.querySelector('.projects')?.closest('.elementor-top-section') || document.querySelector('.projects')?.parentElement;

      return {
        cardsCount: projects.length,
        cardsData,
        mainContainer: mainProjectsContainer ? {
          classes: mainProjectsContainer.className,
          padding: window.getComputedStyle(mainProjectsContainer).padding,
          margin: window.getComputedStyle(mainProjectsContainer).margin,
          height: window.getComputedStyle(mainProjectsContainer).height,
        } : null
      };
    });

    console.log(JSON.stringify(cardInfo, null, 2));

    // Scroll to see sticky behavior and inspect computed styles during sticky
    // Find scroll offset
    await page.evaluate(() => {
      const p = document.querySelector('.projects');
      if (p) {
        p.scrollIntoView();
      }
    });
    await page.waitForTimeout(500);

    // Scroll down in increments and log card top positions
    console.log(`\n--- Scroll behavior on ${name} ---`);
    for (let scrollStep = 0; scrollStep <= 1500; scrollStep += 300) {
      await page.evaluate((s) => window.scrollBy(0, s), 300);
      await page.waitForTimeout(200);
      const pos = await page.evaluate(() => {
        const cards = Array.from(document.querySelectorAll('.projects'));
        return {
          scrollY: window.scrollY,
          cards: cards.map(c => ({
            top: Math.round(c.getBoundingClientRect().top),
            height: Math.round(c.getBoundingClientRect().height)
          }))
        };
      });
      console.log(`ScrollY ${pos.scrollY}:`, pos.cards);
    }

    await page.close();
  }

  await browser.close();
}

deepInspect().catch(console.error);
