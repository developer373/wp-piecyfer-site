const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

async function main() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

  console.log("Analyzing contact-us...");
  await page.goto('http://localhost/piecyfer/contact-us/', { waitUntil: 'networkidle' });

  // Get bounding rects of main sections
  const sections = await page.evaluate(() => {
    const results = [];
    const elements = document.querySelectorAll('header, footer, main, .elementor-section, .elementor-container, [data-elementor-type]');
    for (const el of elements) {
      const r = el.getBoundingClientRect();
      const id = el.id || el.getAttribute('data-id') || el.className;
      results.push({
        tag: el.tagName,
        id: id.substring(0, 50),
        top: r.top,
        height: r.height,
        bottom: r.bottom
      });
    }
    return results;
  });

  console.log("Total document scrollHeight:", await page.evaluate(() => document.documentElement.scrollHeight));
  console.log("Body scrollHeight:", await page.evaluate(() => document.body.scrollHeight));
  
  await browser.close();
}

main().catch(console.error);
