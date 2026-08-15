const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function traceLiveScroll() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  console.log('Loading live https://www.piecyfer.com/ ...');
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  // Let's inspect all script files loaded on live site to see which library or script does the project card animations
  const scripts = await page.evaluate(() => {
    const s = Array.from(document.querySelectorAll('script')).map(scr => scr.src || scr.textContent.slice(0, 100));
    return s.filter(src => typeof src === 'string' && (src.includes('vamtam') || src.includes('scroll') || src.includes('sticky') || src.includes('motion') || src.includes('gsap') || src.includes('animation')));
  });

  console.log('LIVE SCRIPTS FOUND:', scripts);

  // Let's check window objects (gsap, ScrollTrigger, Waypoints, etc.)
  const windowLibs = await page.evaluate(() => {
    return {
      hasGSAP: typeof window.gsap !== 'undefined',
      hasScrollTrigger: typeof window.ScrollTrigger !== 'undefined',
      hasWaypoints: typeof window.Waypoint !== 'undefined',
      hasElementorFrontend: typeof window.elementorFrontend !== 'undefined',
      vamtamObjects: Object.keys(window).filter(k => k.toLowerCase().includes('vamtam')),
    };
  });

  console.log('WINDOW LIBS:', windowLibs);

  // Let's inspect the entire section HTML containing "Our Products" / "Innovation is deep-rooted"
  const sectionStructure = await page.evaluate(() => {
    const heading = Array.from(document.querySelectorAll('*')).find(e => e.textContent.includes('Innovation is deep-rooted') && e.children.length === 0);
    const mainSection = heading ? heading.closest('section.elementor-top-section') : null;
    if (!mainSection) return 'Not found';

    // Get all children sections/columns/widgets
    const elements = Array.from(mainSection.querySelectorAll('*')).map(el => ({
      tag: el.tagName,
      classes: el.className,
      id: el.id,
      dataSettings: el.getAttribute('data-settings'),
      dataWidgetType: el.getAttribute('data-widget_type'),
      style: el.getAttribute('style'),
      rect: el.getBoundingClientRect()
    }));

    return {
      mainSectionClass: mainSection.className,
      mainSectionSettings: mainSection.getAttribute('data-settings'),
      elementsCount: elements.length,
      elements: elements.filter(e => e.classes && (e.classes.includes('elementor-element') || e.classes.includes('projects') || e.classes.includes('elementor-section')))
    };
  });

  console.log('LIVE SECTION STRUCTURE:', JSON.stringify(sectionStructure, null, 2));

  await browser.close();
}

traceLiveScroll().catch(console.error);
