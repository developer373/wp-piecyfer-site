const path = require('path');
const { chromium } = require(path.join(__dirname, '../pixel-tool/node_modules/playwright'));

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  
  page.on('console', msg => console.log('[BROWSER CONSOLE]', msg.type(), msg.text()));
  page.on('pageerror', err => console.log('[BROWSER ERROR]', err.message));
  page.on('request', req => {
    if (req.method() === 'POST') {
      console.log('[POST REQUEST]', req.url(), req.postData() ? req.postData().slice(0, 200) : 'no body');
    }
  });

  await page.goto('http://localhost/piecyfer/contact-us/', { waitUntil: 'networkidle' });
  
  const formInfo = await page.evaluate(() => {
    const f = document.querySelector('#pie-contact-form form');
    const btn = document.querySelector('#pie-contact-form button[type="submit"]');
    const v3 = document.querySelector('#pie-contact-form .elementor-g-recaptcha[data-type="v3"]');
    return {
      hasForm: !!f,
      hasBtn: !!btn,
      btnHtml: btn ? btn.outerHTML : null,
      v3Data: v3 ? { sitekey: v3.getAttribute('data-sitekey'), action: v3.getAttribute('data-action') } : null,
      hasGrecaptcha: typeof window.grecaptcha !== 'undefined',
      hasPiecyferFrontend: typeof window.piecyferFrontend !== 'undefined'
    };
  });
  console.log('Form info:', JSON.stringify(formInfo, null, 2));

  await page.fill('#form-field-contact_us_full_name', 'Harness Probe');
  await page.fill('#form-field-contact_us_email', 'harness@example.invalid');
  await page.fill('#form-field-contact_us_message', 'Automated probe');
  await page.selectOption('#form-field-contact_us_products', 'Others');

  console.log('Clicking submit button...');
  await page.click('#pie-contact-form button[type="submit"]');
  await page.waitForTimeout(3000);

  await browser.close();
})();
