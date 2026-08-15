const fs = require('fs');
const path = require('path');

const mappings = [
  ['widget-blockquote.min.css', 'blockquote.css'],
  ['widget-call-to-action.min.css', 'call-to-action.css'],
  ['widget-carousel-module-base.min.css', 'carousel-module-base.css'],
  ['widget-form.min.css', 'form.css'],
  ['widget-gallery.min.css', 'gallery.css'],
  ['widget-nav-menu.min.css', 'nav-menu.css'],
  ['widget-post-info.min.css', 'post-info.css'],
  ['widget-posts.min.css', 'posts.css'],
  ['widget-search-form.min.css', 'search-form.css'],
  ['widget-testimonial-carousel.min.css', 'testimonial-carousel.css'],
];

const srcDir = path.join(__dirname, '../../wp-content/plugins/elementor-pro/assets/css');
const dstDir = path.join(__dirname, '../../wp-content/plugins/piecyfer-core/assets/css');

for (const [src, dst] of mappings) {
  const srcPath = path.join(srcDir, src);
  const dstPath = path.join(dstDir, dst);
  if (fs.existsSync(srcPath)) {
    fs.copyFileSync(srcPath, dstPath);
    console.log(`Copied ${src} -> ${dst}`);
  } else {
    console.log(`MISSING: ${srcPath}`);
  }
}
