const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectSections() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  console.log('=== DOM & STYLE COMPARISON: LOCAL vs LIVE ===\n');

  // LOCAL
  const pageLocal = await context.newPage();
  await pageLocal.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // LIVE
  const pageLive = await context.newPage();
  await pageLive.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  async function getDetails(page, label) {
    return await page.evaluate((lbl) => {
      // 1. Search widget
      const searchWidget = document.querySelector('.elementor-widget-search-form, .elementor-widget-elementskit-header-search, .elementor-search-form');
      const searchHtml = searchWidget ? searchWidget.outerHTML.slice(0, 300) : 'none';
      const searchBox = searchWidget ? searchWidget.getBoundingClientRect() : null;

      // 2. Products section
      const productCards = Array.from(document.querySelectorAll('.elementor-widget-call-to-action, [data-widget_type*="call-to-action"], .vamtam-grow-from-left, .vamtam-grow-from-right, [data-settings*="growFrom"]'));
      const productDetails = productCards.map(c => {
        const rect = c.getBoundingClientRect();
        return {
          classes: c.className,
          rect: { width: rect.width, height: rect.height, top: rect.top },
          styles: {
            transform: window.getComputedStyle(c).transform,
            opacity: window.getComputedStyle(c).opacity,
            display: window.getComputedStyle(c).display
          }
        };
      });

      // 3. Blog / Posts section
      const postArticles = Array.from(document.querySelectorAll('.elementor-widget-posts article, .elementor-post'));
      const postDetails = postArticles.slice(0, 3).map(a => {
        const img = a.querySelector('.elementor-post__thumbnail, img');
        const text = a.querySelector('.elementor-post__text, .elementor-post__title');
        const imgRect = img ? img.getBoundingClientRect() : null;
        const textRect = text ? text.getBoundingClientRect() : null;
        const articleRect = a.getBoundingClientRect();
        return {
          articleRect: { width: articleRect.width, height: articleRect.height },
          imgRect: imgRect ? { width: imgRect.width, height: imgRect.height, top: imgRect.top } : null,
          textRect: textRect ? { top: textRect.top } : null,
          gap: (imgRect && textRect) ? (textRect.top - (imgRect.top + imgRect.height)) : null
        };
      });

      return {
        label: lbl,
        search: { html: searchHtml, box: searchBox },
        productCardsCount: productCards.length,
        productDetails: productDetails.slice(0, 4),
        postsCount: postArticles.length,
        postDetails
      };
    }, label);
  }

  const localInfo = await getDetails(pageLocal, 'LOCAL');
  const liveInfo = await getDetails(pageLive, 'LIVE');

  console.log('--- SEARCH WIDGET ---');
  console.log('Local Search:', JSON.stringify(localInfo.search, null, 2));
  console.log('Live Search:', JSON.stringify(liveInfo.search, null, 2));

  console.log('\n--- PRODUCT CARDS ---');
  console.log('Local Product Cards count:', localInfo.productCardsCount, localInfo.productDetails);
  console.log('Live Product Cards count:', liveInfo.productCardsCount, liveInfo.productDetails);

  console.log('\n--- BLOG CARDS & GAPS ---');
  console.log('Local Post Details:', JSON.stringify(localInfo.postDetails, null, 2));
  console.log('Live Post Details:', JSON.stringify(liveInfo.postDetails, null, 2));

  await browser.close();
}

inspectSections().catch(console.error);
