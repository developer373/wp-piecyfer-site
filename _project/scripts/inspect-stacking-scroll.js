const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectStackingScroll() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  console.log('Navigating to live https://www.piecyfer.com/ ...');
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  // Scroll down step by step and track position and transforms of each .projects section
  const projectSections = await page.$$('.projects');
  console.log(`Found ${projectSections.length} .projects elements on live.`);

  const scrollPositions = [];
  
  // Find top offset of first .projects section
  const firstTop = await page.evaluate(() => {
    const el = document.querySelector('.projects');
    return el ? el.getBoundingClientRect().top + window.scrollY : 0;
  });

  console.log(`First .projects top is at scrollY: ${firstTop}`);

  // Scroll through that region
  for (let sy = firstTop - 200; sy <= firstTop + 2500; sy += 200) {
    await page.evaluate((y) => window.scrollTo(0, y), sy);
    await page.waitForTimeout(100);

    const stepData = await page.evaluate((y) => {
      const items = Array.from(document.querySelectorAll('.projects'));
      return {
        scrollY: y,
        items: items.map(el => {
          const rect = el.getBoundingClientRect();
          const title = el.querySelector('h1,h2,h3,h4,h5,h6')?.textContent?.trim();
          const computed = window.getComputedStyle(el);
          return {
            title,
            top: rect.top,
            bottom: rect.bottom,
            position: computed.position,
            stickyTop: computed.top,
            zIndex: computed.zIndex,
            transform: computed.transform,
            classes: el.className,
            dataSettings: el.getAttribute('data-settings')
          };
        })
      };
    }, sy);

    scrollPositions.push(stepData);
  }

  console.log('\n--- SCROLL POSITION PROGRESSION ON LIVE ---');
  for (const s of scrollPositions.filter((_, idx) => idx % 2 === 0)) {
    console.log(`ScrollY ${s.scrollY}:`);
    for (const it of s.items) {
      console.log(`  [${it.title || 'no-title'}] top: ${it.top.toFixed(1)}px, pos: ${it.position}, stickyTop: ${it.stickyTop}, zIndex: ${it.zIndex}, classes: ${it.classes.slice(0, 60)}`);
    }
  }

  // Also check if there are specific scripts / handlers or CSS attached to .projects
  const attachedScripts = await page.evaluate(() => {
    const el = document.querySelector('.projects');
    return {
      settings: el ? el.getAttribute('data-settings') : null,
      parentSectionSettings: el && el.parentElement ? el.parentElement.getAttribute('data-settings') : null,
      allClasses: Array.from(document.querySelectorAll('.projects')).map(e => e.className)
    };
  });

  console.log('\nAttached Settings / Classes:', JSON.stringify(attachedScripts, null, 2));

  await browser.close();
}

inspectStackingScroll().catch(console.error);
