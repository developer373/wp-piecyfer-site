const { chromium } = require('../pixel-tool/node_modules/playwright');

async function runFormTests() {
  console.log("=================================================================");
  console.log("STARTING COMPREHENSIVE END-TO-END FORM AUDIT & INTERACTION TESTS");
  console.log("=================================================================\n");

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
  });

  let testCount = 0;
  let passedCount = 0;
  let failedCount = 0;

  function assert(name, condition, extraInfo = '') {
    testCount++;
    if (condition) {
      passedCount++;
      console.log(`  [PASS] ${name}${extraInfo ? ' -> ' + extraInfo : ''}`);
    } else {
      failedCount++;
      console.error(`  [FAIL] ${name}${extraInfo ? ' -> ' + extraInfo : ''}`);
    }
  }

  // ===========================================================================
  // TEST SUITE 1: CONTACT US PAGE FORM (/contact-us/)
  // ===========================================================================
  console.log("--- TEST SUITE 1: Contact Us Form (/contact-us/) ---");
  const page = await context.newPage();

  await page.goto('http://localhost/piecyfer/contact-us/', { waitUntil: 'networkidle' });

  // 1. Verify form and all elements
  const contactForm = await page.$('.elementor-form[name="Contact Us Form"], .elementor-element-d998c99 form');
  assert("Contact Us form element exists in DOM", contactForm !== null);

  const nameField = await page.$('input[name="form_fields[contact_us_full_name]"]');
  const phoneField = await page.$('input[name="form_fields[contact_us_phone_number]"]');
  const emailField = await page.$('input[name="form_fields[contact_us_email]"]');
  const productSelect = await page.$('select[name="form_fields[contact_us_products]"]');
  const messageField = await page.$('textarea[name="form_fields[contact_us_message]"]');
  const submitBtn = await page.$('.elementor-element-d998c99 button[type="submit"]');

  assert("Full Name field exists & visible", nameField && await nameField.isVisible());
  assert("Phone Number field exists & visible", phoneField && await phoneField.isVisible());
  assert("Email field exists & visible", emailField && await emailField.isVisible());
  assert("Product Select dropdown exists & visible", productSelect && await productSelect.isVisible());
  assert("Message field exists & visible", messageField && await messageField.isVisible());
  assert("Submit Button exists & visible", submitBtn && await submitBtn.isVisible());

  // 2. Client-side empty validation check
  let ajaxSentOnEmpty = false;
  const emptyListener = req => {
    if (req.url().includes('admin-ajax.php') && req.postData()?.includes('elementor_pro_forms_send_form')) {
      ajaxSentOnEmpty = true;
    }
  };
  page.on('request', emptyListener);
  await submitBtn.click();
  await page.waitForTimeout(500);
  page.off('request', emptyListener);
  assert("Client-side validation blocks empty submit without AJAX request", !ajaxSentOnEmpty);

  // 3. Fill valid details
  console.log("  Filling valid contact enquiry details...");
  await nameField.fill("Johnathan Doe");
  await phoneField.fill("+15551234567");
  await emailField.fill("johndoe@piecyfer.com");
  await productSelect.selectOption({ index: 1 });
  await messageField.fill("Inquiring about Enterprise Software Development and ERP Solutions.");

  let ajaxResponse = null;
  page.on('response', async resp => {
    if (resp.url().includes('admin-ajax.php') && resp.request().method() === 'POST') {
      ajaxResponse = {
        status: resp.status(),
        body: await resp.json().catch(() => null)
      };
    }
  });

  await submitBtn.click();
  await page.waitForTimeout(4000);

  assert("Contact Us form AJAX request sent and received HTTP 200", ajaxResponse !== null && ajaxResponse.status === 200);
  assert("Contact Us form returned JSON payload", ajaxResponse && ajaxResponse.body !== null);

  // Check UI feedback rendered
  const hasFeedback = await page.$('.elementor-message-success, .elementor-message-danger');
  assert("Feedback message box rendered in UI after submission", hasFeedback !== null);


  // ===========================================================================
  // TEST SUITE 2: CONSULTATION POPUP FORM
  // ===========================================================================
  console.log("\n--- TEST SUITE 2: Consultation Popup Form (Homepage Modal) ---");
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Trigger popup
  const popupTrigger = await page.$('.elementor-button:has-text("Request A Consultation"), a[href*="consultation-cta"], .elementor-button:has-text("Request a Meeting")');
  assert("Consultation trigger button found on page", popupTrigger !== null);

  if (popupTrigger) {
    await popupTrigger.click();
    await page.waitForTimeout(1000);

    const popupModal = await page.$('.elementor-popup-modal:not([style*="display: none"]), div[data-elementor-type="popup"]');
    assert("Consultation popup opened and is visible", popupModal !== null && await popupModal.isVisible());

    // Check popup fields
    const pFirst = await page.$('.elementor-popup-modal input[placeholder="First Name"], input[name="form_fields[first_name_consultation_form]"]');
    const pLast = await page.$('.elementor-popup-modal input[placeholder="Last Name"], input[name="form_fields[last_name_consultation_form]"]');
    const pCompany = await page.$('.elementor-popup-modal input[placeholder*="Company"], input[name="form_fields[company_organization_consultation_form]"]');
    const pEmail = await page.$('.elementor-popup-modal input[placeholder="Email"], input[name="form_fields[email_consultation_form]"]');
    const pPhone = await page.$('.elementor-popup-modal input[placeholder*="Phone"], input[name="form_fields[phone_consultation_form]"]');
    const pService = await page.$('.elementor-popup-modal select, select[name="form_fields[select_service_consultation_form]"]');
    const pMessage = await page.$('.elementor-popup-modal textarea, textarea[name="form_fields[text_area_consultation_form]"]');
    const pSubmit = await page.$('.elementor-popup-modal button[type="submit"], div[data-elementor-type="popup"] button[type="submit"]');

    assert("Popup First Name field visible", pFirst && await pFirst.isVisible());
    assert("Popup Last Name field visible", pLast && await pLast.isVisible());
    assert("Popup Company field visible", pCompany && await pCompany.isVisible());
    assert("Popup Email field visible", pEmail && await pEmail.isVisible());
    assert("Popup Service select visible", pService && await pService.isVisible());
    assert("Popup Message textarea visible", pMessage && await pMessage.isVisible());
    assert("Popup Submit button visible", pSubmit && await pSubmit.isVisible());

    console.log("  Filling valid consultation request details...");
    await pFirst.fill("Alice");
    await pLast.fill("Smith");
    await pCompany.fill("Nexus Corp");
    await pEmail.fill("alice.smith@nexuscorp.com");
    if (pPhone) await pPhone.fill("+15559876543");
    if (pService) await pService.selectOption({ index: 1 });
    await pMessage.fill("Looking for cloud architecture consulting and custom AI integration.");

    let pAjaxResponse = null;
    const pListener = async resp => {
      if (resp.url().includes('admin-ajax.php') && resp.request().method() === 'POST') {
        pAjaxResponse = {
          status: resp.status(),
          body: await resp.json().catch(() => null)
        };
      }
    };
    page.on('response', pListener);
    await pSubmit.click();
    await page.waitForTimeout(4000);
    page.off('response', pListener);

    assert("Popup form AJAX request returned HTTP 200", pAjaxResponse !== null && pAjaxResponse.status === 200);
    assert("Popup form returned valid JSON response", pAjaxResponse && pAjaxResponse.body !== null);
  }


  // ===========================================================================
  // TEST SUITE 3: FOOTER NEWSLETTER / UPDATES FORM
  // ===========================================================================
  console.log("\n--- TEST SUITE 3: Footer Updates Form (Site-wide Footer) ---");
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  const footerEmail = await page.$('#form-field-future_email_updates');
  const footerCheckbox = await page.$('#form-field-future_email_check_updates');
  const footerSubmit = await page.$('.elementor-widget-form[data-id="46162aa"] button[type="submit"]');

  assert("Footer Email input exists & visible", footerEmail && await footerEmail.isVisible());
  assert("Footer Acceptance checkbox exists", footerCheckbox !== null);
  assert("Footer Submit button exists & visible", footerSubmit && await footerSubmit.isVisible());

  console.log("  Submitting newsletter email...");
  await footerEmail.fill("newsletter-subscriber@piecyfer.com");
  if (footerCheckbox) {
    await footerCheckbox.check();
  }

  const [fResp] = await Promise.all([
    page.waitForResponse(resp => resp.url().includes('admin-ajax.php') && resp.request().method() === 'POST', { timeout: 15000 }),
    footerSubmit.click()
  ]);

  const fStatus = fResp.status();
  const fBody = await fResp.json().catch(() => null);

  assert("Footer form AJAX request returned HTTP 200", fStatus === 200);
  assert("Footer form returned valid JSON response", fBody !== null, JSON.stringify(fBody));
  assert("Footer form submission was successful (success: true)", fBody && fBody.success === true);

  await page.waitForSelector('.elementor-widget-form[data-id="46162aa"] .elementor-message-success', { timeout: 5000 }).catch(() => null);
  const fSuccessEl = await page.$('.elementor-widget-form[data-id="46162aa"] .elementor-message-success');
  const fMsg = fSuccessEl ? (await fSuccessEl.textContent()).trim() : '';
  assert("Footer form success message displayed in UI", fSuccessEl && await fSuccessEl.isVisible(), `"${fMsg}"`);


  // ===========================================================================
  // SUMMARY
  // ===========================================================================
  console.log("\n=================================================================");
  console.log(`ALL FORMS AUDIT SUMMARY: ${passedCount} / ${testCount} checks passed (${failedCount} failures)`);
  console.log("=================================================================\n");

  await browser.close();
  process.exit(failedCount > 0 ? 1 : 0);
}

runFormTests().catch(err => {
  console.error("Fatal test error:", err);
  process.exit(1);
});
