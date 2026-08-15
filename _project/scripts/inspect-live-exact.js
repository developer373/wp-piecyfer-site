const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectLiveExact() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  
  await page.goto('https://www.piecyfer.com/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);

  // Let's inspect the entire DOM of the projects section and its surrounding sections
  const data = await page.evaluate(() => {
    const root = document.querySelector('.elementor-element-062c684');
    const sections = Array.from(document.querySelectorAll('.elementor-section'));
    const rootIdx = sections.indexOf(root);
    const nextSection = sections[rootIdx + 1] || root.nextElementSibling;

    // Get all inner sections inside root
    const innerSections = Array.from(root.querySelectorAll('.elementor-inner-section'));

    return {
      root: {
        classes: root.className,
        style: root.getAttribute('style'),
        dataset: root.dataset,
        computed: {
          padding: window.getComputedStyle(root).padding,
          margin: window.getComputedStyle(root).margin,
          height: window.getComputedStyle(root).height,
          position: window.getComputedStyle(root).position,
          overflow: window.getComputedStyle(root).overflow,
        }
      },
      nextSection: nextSection ? {
        classes: nextSection.className,
        computed: {
          margin: window.getComputedStyle(nextSection).margin,
          padding: window.getComputedStyle(nextSection).padding,
        }
      } : null,
      innerSections: innerSections.map(s => ({
        id: s.dataset.id,
        classes: s.className,
        settings: s.dataset.settings,
        style: s.getAttribute('style'),
        computed: {
          position: window.getComputedStyle(s).position,
          top: window.getComputedStyle(s).top,
          zIndex: window.getComputedStyle(s).zIndex,
          padding: window.getComputedStyle(s).padding,
          margin: window.getComputedStyle(s).margin,
          height: window.getComputedStyle(s).height,
          boxShadow: window.getComputedStyle(s).boxShadow,
          borderRadius: window.getComputedStyle(s).borderRadius,
        }
      }))
    };
  });

  console.log('LIVE DETAILED INSPECTION:\n', JSON.stringify(data, null, 2));

  // Now let's scroll live to the EXACT frame matching user's screenshot
  // User's screenshot shows:
  // - Top navbar is visible
  // - Tab 1 (blue) is visible below navbar
  // - Tab 2 (mint) is visible below Tab 1
  // - Tab 3 (OnlineDoc) is visible below Tab 2
  // - View More button is near bottom
  
  // Find the exact scroll position on Live
  const liveTargetY = await page.evaluate(() => {
    const c1 = document.querySelector('.elementor-element-9a128b1');
    const c2 = document.querySelector('.elementor-element-69babdd');
    const c3 = document.querySelector('.elementor-element-73afe75');

    // Scroll until c3 top is at ~200px (when it becomes sticky on live)
    for (let y = 5000; y < 8000; y += 10) {
      window.scrollTo(0, y);
      const r1 = c1.getBoundingClientRect();
      const r2 = c2.getBoundingClientRect();
      const r3 = c3.getBoundingClientRect();
      // On live, c1 top is 50px, c2 top is 150px, c3 top is 200px (or c1 is ~120px under navbar)
      if (Math.round(r3.top) <= 200 && r2.top < r3.top && r1.top < r2.top) {
        return y;
      }
    }
    return 7000;
  });

  console.log('Found Live Target Scroll Y:', liveTargetY);
  await page.evaluate((y) => window.scrollTo(0, y), liveTargetY);
  await page.waitForTimeout(300);

  const shotPath = path.resolve(__dirname, '../snapshots/live_user_screenshot_match.png');
  await page.screenshot({ path: shotPath });
  console.log('Saved Live User Screenshot Match to:', shotPath);

  await browser.close();
}

inspectLiveExact().catch(console.error);
