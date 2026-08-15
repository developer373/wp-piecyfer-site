const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

const testUrls = [
  'http://localhost/piecyfer/',
  'http://localhost/piecyfer/contact-us/',
  'http://localhost/piecyfer/our-team/',
  'http://localhost/piecyfer/blogs/',
  'http://localhost/piecyfer/web-app-development/',
  'http://localhost/piecyfer/building-high-performing-web-apps/',
  'http://localhost/piecyfer/category/erp/',
  'http://localhost/piecyfer/non-existent-404-url-test/'
];

async function auditSEO() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();

  console.log('=== TECHNICAL SEO AUDIT ===\n');

  for (const url of testUrls) {
    const page = await context.newPage();
    const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
    const status = response.status();

    const seoData = await page.evaluate(() => {
      const title = document.title;
      const metaDesc = document.querySelector('meta[name="description"]')?.getAttribute('content');
      const canonical = document.querySelector('link[rel="canonical"]')?.getAttribute('href');
      const ogTitle = document.querySelector('meta[property="og:title"]')?.getAttribute('content');
      const ogDesc = document.querySelector('meta[property="og:description"]')?.getAttribute('content');
      const ogImage = document.querySelector('meta[property="og:image"]')?.getAttribute('content');
      const robots = document.querySelector('meta[name="robots"]')?.getAttribute('content');
      
      const jsonLdScripts = Array.from(document.querySelectorAll('script[type="application/ld+json"]'));
      let validJsonLdCount = 0;
      let jsonLdTypes = [];

      for (const s of jsonLdScripts) {
        try {
          const parsed = JSON.parse(s.textContent);
          validJsonLdCount++;
          if (parsed['@type']) jsonLdTypes.push(parsed['@type']);
          if (parsed['@graph']) {
            parsed['@graph'].forEach(g => { if (g['@type']) jsonLdTypes.push(g['@type']); });
          }
        } catch (e) {}
      }

      return {
        title,
        metaDesc,
        canonical,
        ogTitle,
        ogDesc,
        ogImage,
        robots,
        validJsonLdCount,
        jsonLdTypes
      };
    });

    console.log(`URL: ${url} (HTTP ${status})`);
    console.log(`  Title: "${seoData.title}"`);
    console.log(`  Meta Description: ${seoData.metaDesc ? `"${seoData.metaDesc.slice(0, 80)}..."` : 'MISSING'}`);
    console.log(`  Canonical: ${seoData.canonical || 'MISSING'}`);
    console.log(`  Robots: ${seoData.robots || 'Default (index, follow)'}`);
    console.log(`  OpenGraph: Title=${!!seoData.ogTitle}, Desc=${!!seoData.ogDesc}, Img=${!!seoData.ogImage}`);
    console.log(`  Schema.org JSON-LD: ${seoData.validJsonLdCount} block(s), types: [${seoData.jsonLdTypes.join(', ')}]\n`);

    await page.close();
  }

  await browser.close();
}

auditSEO().catch(console.error);
