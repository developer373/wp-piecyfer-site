// The URL set that defines "the site". Every capture run walks exactly this
// list, so two runs are always comparable.
//
// Regenerate the page/post section with:
//   node urls.js --refresh
// (queries the database and rewrites the PAGES array below)

const BASE = 'http://localhost/piecyfer';

// Published pages and posts, plus the archives, search and error routes that
// Elementor Theme Builder is responsible for.
const PATHS = [
  // --- home & core pages ---
  '/',
  '/home/',
  '/about-piecyfer/',
  '/our-team/',
  '/why-choose-us/',
  '/contact-us/',
  '/hire-an-expert/',
  '/privacy-policy/',
  '/terms-conditions/',

  // --- service pages ---
  '/web-app-development/',
  '/mobile-app-development/',
  '/enterprise-software-development/',
  '/software-quality-testing/',
  '/software-maintenance-and-support/',
  '/software-migration/',
  '/software-re-engineering/',
  '/cloud-services/',
  '/cms-solutions/',
  '/crm-solutions/',
  '/digital-marketing/',
  '/emerging-tech/',

  // --- blog: archive + every post (exercises the single-post template) ---
  '/blogs/',
  '/boosting-sales-with-a-powerful-crm-a-guide-for-us-businesses/',
  '/building-high-performing-web-apps-10-easy-yet-important-tips/',
  '/creating-engaging-mobile-apps-for-the-us-market-best-practices/',
  '/crm-trends-in-2025-what-to-expect-in-the-future/',
  '/mobile-app-development-in-the-age-of-ai-opportunities-and-challenges/',
  '/sales-teams-spend-70-of-their-day-not-selling-heres-the-ai-stack-that-fixes-it/',
  '/streamlining-operations-with-erp-softwares-real-world-case-studies/',
  '/tailored-enterprise-software-solutions-a-guide-for-us-businesses/',
  '/the-88-problem-why-most-ai-agent-pilots-never-reach-production-and-what-nvidias-latest-push-actually-solves/',
  '/the-benefits-of-progressive-web-apps-for-us-businesses/',
  '/the-legacy-integration-wall-why-60-of-ai-projects-stall-before-they-ship/',
  '/we-reviewed-50-enterprise-ai-deployments-the-successful-ones-had-one-thing-in-common/',
  '/why-ai-native-companies-are-outperforming-traditional-software-businesses/',
  '/why-healthcare-companies-are-finally-ditching-legacy-systems-in-2026/',
  '/why-healthcare-software-fails-without-security-first-architecture/',

  // --- theme-builder routes that no page URL would otherwise reach ---
  //
  // The archive template was the one Theme Builder document with NO coverage at
  // all, which matters more than it sounds: it is also the only location Pro
  // takes over through `template_include` rather than through the theme's own
  // `elementor_theme_do_location()` calls, so it exercises a code path nothing
  // else here touches. Replacing Theme Builder without these would have been
  // done blind.
  '/category/erp/',                     // archive template, 9 posts
  '/category/crm/',                     // archive template, 2 posts — a short grid lays out differently
  '/blogs/page/2/',                     // the posts widget's pagination, untested until now
  // author.php and attachment.php render the THEME's own markup and have no
  // Theme Builder template at all, so they are the one place a theme swap can
  // change real output with nothing watching. Author covers the same code path
  // as attachment and is reachable without depending on a specific media id.
  '/author/webdeveloper373/',
  '/?s=software',                       // search-results template
  '/this-url-does-not-exist-404-test/', // error-404 template
];

// A representative subset for fast iteration. A full 39-page capture takes
// ~35 minutes, which is too slow to run after every single plugin removal.
// These eight pages between them exercise every template and every Pro widget
// that has more than a couple of instances:
//
//   /                 home: nav-menu, template, posts, testimonial-carousel
//   /contact-us/      form widget, google map
//   /our-team/        image-box grids, root-relative image URLs
//   /blogs/           posts widget with the vamtam_classic skin
//   a single post     single-post theme-builder template, post-comments
//   /web-app-dev/     a typical service page, icon-box heavy
//   /?s=software      search-results theme-builder template
//   /404 test         error-404 theme-builder template
//
// Use the full set as the gate at the end of each phase.
const QUICK = [
  '/',
  '/contact-us/',
  '/our-team/',
  '/blogs/',
  '/building-high-performing-web-apps-10-easy-yet-important-tips/',
  '/web-app-development/',
  '/category/erp/',
  '/?s=software',
  '/this-url-does-not-exist-404-test/',
];

// Viewport widths. Height is nominal; captures are full-page.
const VIEWPORTS = [
  { name: 'desktop', width: 1920, height: 1080 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 375, height: 812 },
];

function slugify(p) {
  const s = p.replace(/^\//, '').replace(/\/$/, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '');
  return s === '' ? 'home-root' : s.slice(0, 80);
}

module.exports = { BASE, PATHS, QUICK, VIEWPORTS, slugify };
