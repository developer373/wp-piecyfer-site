const path = require('path');
const { chromium } = require(path.resolve(__dirname, '../pixel-tool/node_modules/playwright'));

async function inspectCards() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  const pageLive = await context.newPage();
  await pageLive.goto('https://www.piecyfer.com/', { waitUntil: 'networkidle' });

  const liveStyles = await pageLive.evaluate(() => {
    const card = document.querySelector('.elementor-widget-posts .elementor-post');
    const thumbWrapper = card?.querySelector('.elementor-post__thumbnail__wrapper');
    const thumb = card?.querySelector('.elementor-post__thumbnail');
    const img = card?.querySelector('img');
    const title = card?.querySelector('.elementor-post__title');
    const text = card?.querySelector('.elementor-post__text');

    return {
      cardClass: card?.className,
      thumbWrapperComputed: thumbWrapper ? {
        height: window.getComputedStyle(thumbWrapper).height,
        paddingBottom: window.getComputedStyle(thumbWrapper).paddingBottom,
        position: window.getComputedStyle(thumbWrapper).position,
        overflow: window.getComputedStyle(thumbWrapper).overflow,
      } : null,
      thumbComputed: thumb ? {
        height: window.getComputedStyle(thumb).height,
        position: window.getComputedStyle(thumb).position,
      } : null,
      imgComputed: img ? {
        height: window.getComputedStyle(img).height,
        objectFit: window.getComputedStyle(img).objectFit,
        position: window.getComputedStyle(img).position,
      } : null,
      textComputed: text ? {
        padding: window.getComputedStyle(text).padding,
        margin: window.getComputedStyle(text).margin,
      } : null,
    };
  });

  const pageLocal = await context.newPage();
  await pageLocal.goto('http://localhost/piecyfer/', { waitUntil: 'networkidle' });

  const localStyles = await pageLocal.evaluate(() => {
    const card = document.querySelector('.elementor-widget-posts .elementor-post');
    const thumbWrapper = card?.querySelector('.elementor-post__thumbnail__wrapper');
    const thumb = card?.querySelector('.elementor-post__thumbnail');
    const img = card?.querySelector('img');
    const text = card?.querySelector('.elementor-post__text');

    return {
      cardClass: card?.className,
      thumbWrapperComputed: thumbWrapper ? {
        height: window.getComputedStyle(thumbWrapper).height,
        paddingBottom: window.getComputedStyle(thumbWrapper).paddingBottom,
        position: window.getComputedStyle(thumbWrapper).position,
        overflow: window.getComputedStyle(thumbWrapper).overflow,
      } : null,
      thumbComputed: thumb ? {
        height: window.getComputedStyle(thumb).height,
        position: window.getComputedStyle(thumb).position,
      } : null,
      imgComputed: img ? {
        height: window.getComputedStyle(img).height,
        objectFit: window.getComputedStyle(img).objectFit,
        position: window.getComputedStyle(img).position,
      } : null,
      textComputed: text ? {
        padding: window.getComputedStyle(text).padding,
        margin: window.getComputedStyle(text).margin,
      } : null,
    };
  });

  console.log('LIVE CARDS STYLES:', JSON.stringify(liveStyles, null, 2));
  console.log('LOCAL CARDS STYLES:', JSON.stringify(localStyles, null, 2));

  await browser.close();
}

inspectCards().catch(console.error);
