const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));
const fs = require('fs');

async function crawlAllPages() {
  const publicUrls = JSON.parse(fs.readFileSync(path.resolve(__dirname, 'public-urls.json'), 'utf8'));

  console.log(`Starting crawl of ${publicUrls.length} pages...`);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const allPageResults = [];
  const brokenLinksFound = [];

  for (let i = 0; i < publicUrls.length; i++) {
    const item = publicUrls[i];
    console.log(`\n[${i + 1}/${publicUrls.length}] Auditing: ${item.title} (${item.url})`);

    try {
      const response = await page.goto(item.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      const status = response ? response.status() : 0;
      await page.waitForTimeout(500);

      const links = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('a[href]')).map(a => {
          return {
            text: a.innerText.replace(/\s+/g, ' ').trim(),
            href: a.href,
            rawHref: a.getAttribute('href'),
            classes: a.className,
            parentTag: a.parentElement ? a.parentElement.tagName.toLowerCase() : ''
          };
        });
      });

      const pageBroken = [];
      for (const l of links) {
        // Exclude mailto, tel, javascript, anchors on same page (#)
        if (l.rawHref.startsWith('#') || l.rawHref.startsWith('mailto:') || l.rawHref.startsWith('tel:') || l.rawHref.startsWith('javascript:')) {
          continue;
        }

        // Check if link points to localhost without /piecyfer/
        if (l.href.startsWith('http://localhost/') && !l.href.startsWith('http://localhost/piecyfer')) {
          pageBroken.push({
            reason: 'MISSING_PIECYFER_PREFIX',
            ...l
          });
        }
      }

      console.log(`  - Page status: ${status} | Total links: ${links.length} | Broken: ${pageBroken.length}`);
      if (pageBroken.length > 0) {
        pageBroken.forEach(b => console.log(`    ❌ [${b.reason}] "${b.text}" => raw: "${b.rawHref}", resolved: "${b.href}"`));
        brokenLinksFound.push({
          pageId: item.id,
          pageTitle: item.title,
          pageUrl: item.url,
          broken: pageBroken
        });
      }

      allPageResults.push({
        id: item.id,
        title: item.title,
        url: item.url,
        status,
        totalLinks: links.length,
        brokenCount: pageBroken.length
      });

    } catch (err) {
      console.error(`  ⚠️ Failed to audit ${item.url}:`, err.message);
    }
  }

  console.log('\n=======================================================');
  console.log(`AUDIT COMPLETE: Crawled ${allPageResults.length} pages.`);
  console.log(`Pages with broken links: ${brokenLinksFound.length}`);
  console.log('=======================================================');

  fs.writeFileSync(path.resolve(__dirname, 'crawl-report.json'), JSON.stringify({
    allPageResults,
    brokenLinksFound
  }, null, 2));

  await browser.close();
}

crawlAllPages().catch(console.error);
