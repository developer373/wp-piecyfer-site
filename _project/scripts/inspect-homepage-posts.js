const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectHomepagePosts() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  const info = await page.evaluate(() => {
    const postsWidget = document.querySelector('.elementor-widget-posts');
    const container = postsWidget ? postsWidget.querySelector('.elementor-posts-container') : null;
    const firstArticle = postsWidget ? postsWidget.querySelector('article') : null;
    const thumbLink = firstArticle ? firstArticle.querySelector('.elementor-post__thumbnail__link') : null;
    const thumb = firstArticle ? firstArticle.querySelector('.elementor-post__thumbnail') : null;
    const img = firstArticle ? firstArticle.querySelector('img') : null;

    return {
      widgetClasses: postsWidget ? postsWidget.className : 'null',
      widgetSettings: postsWidget ? postsWidget.getAttribute('data-settings') : null,
      containerClasses: container ? container.className : 'null',
      firstArticleClasses: firstArticle ? firstArticle.className : 'null',
      thumbLinkHtml: thumbLink ? thumbLink.outerHTML : 'null',
      thumbComputed: thumb ? {
        height: window.getComputedStyle(thumb).height,
        position: window.getComputedStyle(thumb).position,
        paddingBottom: window.getComputedStyle(thumb).paddingBottom
      } : null,
      imgComputed: img ? {
        height: window.getComputedStyle(img).height,
        position: window.getComputedStyle(img).position
      } : null
    };
  });

  console.log('Homepage Posts Info:', JSON.stringify(info, null, 2));
  await browser.close();
}

inspectHomepagePosts().catch(console.error);
