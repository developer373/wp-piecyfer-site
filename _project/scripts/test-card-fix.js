const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function testFix() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  // Add the CSS
  await page.addStyleTag({
    content: `
      .elementor-posts-container .elementor-post__thumbnail {
        position: relative;
        overflow: hidden;
      }
      .elementor-posts-container .elementor-post__thumbnail img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
    `
  });

  const postDetails = await page.evaluate(() => {
    const a = document.querySelector('.elementor-widget-posts article');
    const img = a.querySelector('.elementor-post__thumbnail');
    const text = a.querySelector('.elementor-post__text, .elementor-post__title');
    const imgRect = img.getBoundingClientRect();
    const textRect = text.getBoundingClientRect();
    const articleRect = a.getBoundingClientRect();
    return {
      articleHeight: articleRect.height,
      imgHeight: imgRect.height,
      gap: textRect.top - (imgRect.top + imgRect.height)
    };
  });

  console.log('Post details with fix:', JSON.stringify(postDetails, null, 2));
  await page.screenshot({ path: path.resolve(__dirname, '../snapshots/fixed_blog_card.png') });
  await browser.close();
}

testFix().catch(console.error);
