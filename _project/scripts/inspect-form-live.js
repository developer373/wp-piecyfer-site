const { chromium } = require(require('path').join(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectForm() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('http://localhost/piecyfer/contact-us/', { waitUntil: 'networkidle' });

  const data = await page.evaluate(() => {
    const form = document.querySelector('.elementor-widget-form');
    if (!form) return null;
    const fields = form.querySelectorAll('.elementor-field-group');
    return Array.from(fields).map(f => {
      const r = f.getBoundingClientRect();
      const s = window.getComputedStyle(f);
      return {
        cls: f.className,
        rect: { top: r.top + window.scrollY, height: r.height, bottom: r.bottom + window.scrollY },
        html: f.outerHTML.substring(0, 150)
      };
    });
  });

  console.log("FORM FIELDS:", JSON.stringify(data, null, 2));
  await browser.close();
}

inspectForm().catch(console.error);
