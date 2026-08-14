/**
 * Behaviour test definitions.
 *
 * Every test is an object:
 *
 *   id        stable identifier — the key a comparison against a baseline uses.
 *             NEVER rename one without also renaming it in every stored result,
 *             because `run.js --compare` treats a missing id as a regression.
 *   title     one line of English.
 *   url       path appended to BASE.
 *   viewport  'desktop' | 'tablet' | 'mobile'
 *   asserts   what this test claims, in English. Written into the result file so
 *             a diff months from now explains itself without reading the code.
 *   why       why this behaviour is at risk when Elementor Pro is replaced.
 *   run       async ({ page, t, ... }) => void.  Must record at least one check
 *             via `t`, or the runner fails the test — see run.js.
 *
 * SELECTOR POLICY
 * ---------------
 * Every selector below was read off the live site or off
 * ../snapshots/ref-a/html/*.html. None was guessed. Where a page holds several
 * instances of a widget, the test resolves "the one that is actually visible at
 * this viewport" (t.visibleOne) rather than indexing into a NodeList, because
 * an index silently follows whichever instance happens to come first in the DOM
 * and would keep passing after the real one disappeared.
 *
 * Layout facts this file depends on, all verified against the live site:
 *
 *   - The DESKTOP header menu is the ElementsKit widget (#ekit-megamenu-main-menu,
 *     widget `ekit-nav-menu`), which is FREE and survives Pro's removal.
 *   - The TABLET/MOBILE header menu is the Elementor Pro `nav-menu` widget
 *     (#tabNmobNav). It is 0x0 on desktop. So the Pro nav menu is only ever
 *     reachable below 1024px on this site, and every Pro nav test here runs at
 *     tablet or mobile. Testing it at desktop would test nothing.
 *   - Of the two `search-form` widgets in the header, one is desktop-only and
 *     one is tablet-only; below that the ElementsKit modal search takes over.
 *     Both search-form widgets carry the SAME id="pie-search" (see README —
 *     genuine duplicate-id defect), so tests must never select on `#pie-search`.
 *   - Only ONE `.elementor-menu-toggle` exists in the whole document.
 */

const VIEWPORTS = {
  desktop: { width: 1920, height: 1080 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 375, height: 812 },
};

// Pages walked by the console-cleanliness tests. One test per page, so a new
// error is attributed to a page rather than to "the run".
//
// Mirrors pixel-tool/urls.js QUICK: between them these exercise every Theme
// Builder template and every widget with more than a couple of instances.
const CONSOLE_PAGES = [
  ['home', '/'],
  ['contact-us', '/contact-us/'],
  ['our-team', '/our-team/'],
  ['blogs', '/blogs/'],
  ['single-post', '/why-healthcare-software-fails-without-security-first-architecture/'],
  ['service-page', '/web-app-development/'],
  ['category-archive', '/category/erp/'],
  ['search-results', '/?s=software'],
  ['error-404', '/this-url-does-not-exist-404-test/'],
];

const tests = [];

// ---------------------------------------------------------------------------
// 0. Environment
// ---------------------------------------------------------------------------
tests.push({
  id: 'env-frontend-stack',
  title: 'Frontend JS stack is present and reports which handlers exist',
  url: '/',
  viewport: 'desktop',
  asserts:
    'jQuery, elementorFrontend and the SmartMenus / Magnific plugins are loaded. ' +
    'The presence of elementorProFrontend is RECORDED, not asserted — after ' +
    'de-nulling it is expected to disappear, and that fact alone must not fail ' +
    'the suite; the individual behaviour tests are what decide.',
  why: 'A stack that fails to boot makes every other result meaningless.',
  async run({ page, t }) {
    const stack = await page.evaluate(() => ({
      jQuery: typeof window.jQuery,
      elementorFrontend: typeof window.elementorFrontend,
      elementorProFrontend: typeof window.elementorProFrontend,
      smartmenus: !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.smartmenus),
      magnificPopup: !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.magnificPopup),
      swiperCtor: typeof window.Swiper,
      elementorFrontendConfigBreakpoints: !!(window.elementorFrontendConfig && window.elementorFrontendConfig.responsive),
    }));
    t.observe('stack', stack);
    t.eq('jQuery loaded', stack.jQuery, 'function');
    t.eq('elementorFrontend loaded', stack.elementorFrontend, 'object');
    t.eq('SmartMenus plugin registered on jQuery', stack.smartmenus, true);
    t.eq('Magnific Popup registered on jQuery (ElementsKit modals)', stack.magnificPopup, true);
    t.note('elementorProFrontend', stack.elementorProFrontend,
      'observation only — "object" means Pro is active, "undefined" means it is not');
  },
});

// ---------------------------------------------------------------------------
// 1. Mobile / tablet nav menu  (Elementor Pro nav-menu widget)
// ---------------------------------------------------------------------------
for (const vp of ['mobile', 'tablet']) {
  tests.push({
    id: `nav-toggle-${vp}`,
    title: `Header burger opens and closes the dropdown menu (${vp})`,
    url: '/',
    viewport: vp,
    asserts:
      'Exactly one .elementor-menu-toggle is visible. Clicking it sets ' +
      'aria-expanded=true, adds .elementor-active, flips the dropdown <nav> to ' +
      'aria-hidden=false and gives it a non-zero rendered height. Clicking again ' +
      'reverses all four.',
    why:
      'The burger is bound by Elementor Pro\'s nav-menu frontend handler. With no ' +
      'JS the markup still renders a burger icon that does nothing — visually ' +
      'identical, functionally dead. This is the single most damaging silent ' +
      'regression in the whole migration.',
    async run({ page, t }) {
      await t.visibleOne('#tabNmobNav .elementor-menu-toggle', 'header burger toggle');
      await t.exists('#tabNmobNav nav.elementor-nav-menu--dropdown', 'dropdown nav container');

      const read = () => page.evaluate(() => {
        const tg = document.querySelector('#tabNmobNav .elementor-menu-toggle');
        const nav = document.querySelector('#tabNmobNav nav.elementor-nav-menu--dropdown');
        return {
          ariaExpanded: tg.getAttribute('aria-expanded'),
          toggleActive: tg.classList.contains('elementor-active'),
          navAriaHidden: nav.getAttribute('aria-hidden'),
          navHeight: Math.round(nav.getBoundingClientRect().height),
          itemsVisible: [...nav.querySelectorAll('a.elementor-item')]
            .filter(a => a.getBoundingClientRect().height > 0).length,
        };
      });

      const before = await read();
      t.observe('closed', before);
      t.eq('starts collapsed (aria-expanded)', before.ariaExpanded, 'false');
      t.eq('starts collapsed (rendered height 0)', before.navHeight, 0);

      await page.click('#tabNmobNav .elementor-menu-toggle');
      // Wait on the real condition, not on a clock.
      await t.waitFor(
        () => {
          const tg = document.querySelector('#tabNmobNav .elementor-menu-toggle');
          const nav = document.querySelector('#tabNmobNav nav.elementor-nav-menu--dropdown');
          return tg.getAttribute('aria-expanded') === 'true' &&
            nav.getBoundingClientRect().height > 0;
        },
        'dropdown reports expanded AND has non-zero height'
      );
      const opened = await read();
      t.observe('opened', opened);
      t.eq('aria-expanded flips to true', opened.ariaExpanded, 'true');
      t.eq('toggle gains .elementor-active', opened.toggleActive, true);
      t.eq('dropdown nav aria-hidden flips to false', opened.navAriaHidden, 'false');
      t.gt('dropdown has rendered height', opened.navHeight, 0);
      t.gt('menu links are actually on screen', opened.itemsVisible, 0);

      await page.click('#tabNmobNav .elementor-menu-toggle');
      await t.waitFor(
        () => {
          const tg = document.querySelector('#tabNmobNav .elementor-menu-toggle');
          const nav = document.querySelector('#tabNmobNav nav.elementor-nav-menu--dropdown');
          return tg.getAttribute('aria-expanded') === 'false' &&
            nav.getBoundingClientRect().height === 0;
        },
        'dropdown reports collapsed AND has zero height again'
      );
      const closed = await read();
      t.observe('closedAgain', closed);
      t.eq('aria-expanded returns to false', closed.ariaExpanded, 'false');
      t.eq('toggle loses .elementor-active', closed.toggleActive, false);
      t.eq('dropdown height returns to 0', closed.navHeight, 0);
    },
  });
}

// ---------------------------------------------------------------------------
// 2. Sub-menu inside the Pro dropdown (SmartMenus)
// ---------------------------------------------------------------------------
tests.push({
  id: 'nav-submenu-mobile',
  title: 'A parent item inside the mobile dropdown reveals its sub-menu',
  url: '/',
  viewport: 'mobile',
  asserts:
    'With the dropdown open, the first <a class="has-submenu"> starts with ' +
    'aria-expanded=false and a zero-height sub-menu; clicking it sets ' +
    'aria-expanded=true and gives the sub-menu a non-zero height with visible links.',
  why:
    'Sub-menu expansion is SmartMenus, which Elementor Pro enqueues and ' +
    'initialises for the nav-menu widget. Without it the parent item is an ' +
    'href="#" that goes nowhere and three quarters of the site becomes ' +
    'unreachable from the mobile menu.',
  async run({ page, t }) {
    await t.visibleOne('#tabNmobNav .elementor-menu-toggle', 'header burger toggle');
    await page.click('#tabNmobNav .elementor-menu-toggle');
    await t.waitFor(
      () => document.querySelector('#tabNmobNav nav.elementor-nav-menu--dropdown')
        .getBoundingClientRect().height > 0,
      'dropdown is open'
    );

    const SEL = '#tabNmobNav nav.elementor-nav-menu--dropdown a.has-submenu';
    await t.exists(SEL, 'parent menu item with children');

    const read = () => page.evaluate(sel => {
      const a = document.querySelector(sel);
      const ul = a.parentElement.querySelector('ul.sub-menu');
      return {
        label: a.textContent.trim().slice(0, 30),
        ariaExpanded: a.getAttribute('aria-expanded'),
        subHeight: Math.round(ul.getBoundingClientRect().height),
        subLinksVisible: [...ul.querySelectorAll('a')]
          .filter(x => x.getBoundingClientRect().height > 0).length,
        subAriaHidden: ul.getAttribute('aria-hidden'),
      };
    }, SEL);

    const before = await read();
    t.observe('collapsed', before);
    t.eq('sub-menu starts collapsed', before.ariaExpanded, 'false');
    t.eq('sub-menu starts at zero height', before.subHeight, 0);

    await page.click(SEL);
    await t.waitFor(
      sel => {
        const a = document.querySelector(sel);
        return a.getAttribute('aria-expanded') === 'true' &&
          a.parentElement.querySelector('ul.sub-menu').getBoundingClientRect().height > 0;
      },
      'sub-menu reports expanded AND has height',
      SEL
    );
    const after = await read();
    t.observe('expanded', after);
    t.eq('aria-expanded flips to true', after.ariaExpanded, 'true');
    t.gt('sub-menu gains height', after.subHeight, 0);
    t.gt('child links are on screen', after.subLinksVisible, 0);
  },
});

// ---------------------------------------------------------------------------
// 3. Desktop mega-menu (ElementsKit — free, must survive)
// ---------------------------------------------------------------------------
tests.push({
  id: 'nav-desktop-megamenu-hover',
  title: 'Desktop header mega-menu opens on hover',
  url: '/',
  viewport: 'desktop',
  asserts:
    'EVERY #ekit-megamenu-main-menu li.elementskit-dropdown-has (there are two: ' +
    'Company and Services) has a panel that is visibility:hidden / opacity:0 at ' +
    'rest and becomes visible with opacity 1 while its parent link is hovered. ' +
    'The count of dropdown parents is itself asserted, so a menu item that quietly ' +
    'loses its children fails rather than being skipped.',
  why:
    'This is the ONLY header navigation a desktop visitor sees on this site — ' +
    'the Pro nav-menu widget is 0x0 above 1024px. It is ElementsKit (free) so it ' +
    'should survive Pro\'s removal, but "should" is exactly the word this harness ' +
    'exists to replace.',
  async run({ page, t }) {
    const LI = '#ekit-megamenu-main-menu li.elementskit-dropdown-has';
    const n = await t.exists(LI + ' > a', 'mega-menu parent links', 1);
    t.eq('both dropdown parents are present', n, 2);

    const read = idx => page.evaluate(a => {
      const el = document.querySelectorAll(a.li)[a.idx];
      const panel = el.querySelector('.elementskit-megamenu-panel') ||
        el.querySelector('ul.elementskit-submenu-panel');
      const cs = getComputedStyle(panel);
      return {
        label: el.querySelector('a').textContent.trim().replace(/\s+/g, ' ').slice(0, 20),
        panelClass: panel.className,
        visibility: cs.visibility,
        opacity: cs.opacity,
        height: Math.round(panel.getBoundingClientRect().height),
      };
    }, { li: LI, idx });

    for (let idx = 0; idx < n; idx++) {
      const before = await read(idx);
      t.observe(`atRest[${idx}]`, before);
      t.eq(`[${before.label}] panel hidden at rest`, before.visibility, 'hidden');

      await page.locator(LI + ' > a').nth(idx).hover();
      await t.waitFor(
        a => {
          const el = document.querySelectorAll(a.li)[a.idx];
          const panel = el.querySelector('.elementskit-megamenu-panel') ||
            el.querySelector('ul.elementskit-submenu-panel');
          const cs = getComputedStyle(panel);
          return cs.visibility === 'visible' && Number(cs.opacity) > 0.9;
        },
        `[${before.label}] mega-menu panel is visible and fully opaque`,
        { li: LI, idx }
      );
      const after = await read(idx);
      t.observe(`hovered[${idx}]`, after);
      t.eq(`[${after.label}] panel becomes visible`, after.visibility, 'visible');
      t.eq(`[${after.label}] panel becomes opaque`, after.opacity, '1');
      t.gt(`[${after.label}] panel has height`, after.height, 0);

      // Move the pointer well away so the next iteration starts from rest.
      await page.mouse.move(5, 700);
      await t.waitFor(
        a => {
          const el = document.querySelectorAll(a.li)[a.idx];
          const panel = el.querySelector('.elementskit-megamenu-panel') ||
            el.querySelector('ul.elementskit-submenu-panel');
          return getComputedStyle(panel).visibility === 'hidden';
        },
        `[${after.label}] panel hides again when the pointer leaves`,
        { li: LI, idx }
      );
    }
  },
});

// ---------------------------------------------------------------------------
// 4. Testimonial carousel  (Elementor Pro)
// ---------------------------------------------------------------------------
tests.push({
  id: 'carousel-testimonial-init',
  title: 'Both testimonial carousels initialise a real Swiper instance',
  url: '/',
  viewport: 'desktop',
  asserts:
    'Every .elementor-widget-testimonial-carousel on the home page contains a ' +
    '.swiper that has BOTH the swiper-initialized class and a live `.swiper` ' +
    'property (the Swiper instance object) with a slide count > 0.',
  why:
    'The class alone can be faked by markup. Requiring the instance object proves ' +
    'JS actually ran. Pro registers the testimonial-carousel handler; without it ' +
    'the slides stack vertically or sit frozen at slide one.',
  async run({ page, t }) {
    await t.exists('.elementor-widget-testimonial-carousel', 'testimonial carousel widget', 2);
    const state = await page.evaluate(() =>
      [...document.querySelectorAll('.elementor-widget-testimonial-carousel')].map(w => {
        const el = w.querySelector('.swiper, .swiper-container');
        const sw = el && el.swiper;
        return {
          widgetId: w.dataset.id,
          hasContainer: !!el,
          initialisedClass: !!el && el.classList.contains('swiper-initialized'),
          hasInstance: !!sw,
          slides: sw ? sw.slides.length : 0,
          realIndex: sw ? sw.realIndex : null,
          hasArrows: !!w.querySelector('.elementor-swiper-button-next'),
        };
      })
    );
    t.observe('carousels', state);
    t.eq('two testimonial carousels found', state.length, 2);
    state.forEach(c => {
      t.eq(`[${c.widgetId}] has a .swiper container`, c.hasContainer, true);
      t.eq(`[${c.widgetId}] carries swiper-initialized`, c.initialisedClass, true);
      t.eq(`[${c.widgetId}] exposes a live Swiper instance`, c.hasInstance, true);
      t.gt(`[${c.widgetId}] Swiper knows about its slides`, c.slides, 0);
    });
  },
});

tests.push({
  id: 'carousel-testimonial-arrows',
  title: 'Testimonial carousel arrows advance and rewind the active slide',
  url: '/',
  viewport: 'desktop',
  asserts:
    'On the one testimonial carousel that has navigation arrows, clicking next ' +
    'increments Swiper.realIndex by exactly 1 and moves .swiper-slide-active to ' +
    'a different element; clicking prev returns realIndex to its original value.',
  why:
    'realIndex is the carousel\'s own idea of where it is, so this cannot be ' +
    'satisfied by a CSS transition that merely looks like movement. If Pro\'s ' +
    'handler is missing, the arrows render and do nothing.',
  async run({ page, t }) {
    const W = '.elementor-widget-testimonial-carousel:has(.elementor-swiper-button-next)';
    await t.exists(W, 'testimonial carousel with arrows', 1);
    await t.visibleOne(W + ' .elementor-swiper-button-next', 'next arrow');
    await t.visibleOne(W + ' .elementor-swiper-button-prev', 'prev arrow');

    // Bring it into view so the click is a real user-style click, and stop
    // autoplay if this widget has any — otherwise the index could move under us
    // between reading and clicking. (It does not on this site: this widget has
    // no autoplay setting. Stopping it anyway keeps the test correct if the
    // setting is ever turned on.)
    await page.evaluate(w => {
      const el = document.querySelector(w);
      el.scrollIntoView({ block: 'center' });
      const c = el.querySelector('.swiper, .swiper-container');
      const sw = c && c.swiper;
      if (sw && sw.autoplay && sw.autoplay.running) sw.autoplay.stop();
    }, W);

    // Every read tolerates a missing Swiper instance and reports `null`, so that
    // a carousel with no JS behind it produces a legible failed assertion
    // ("realIndex expected 1, observed null") instead of a TypeError stack from
    // inside page.evaluate. A stack trace is a bug report about the harness; an
    // assertion is a bug report about the site, which is what a reader needs.
    const read = () => page.evaluate(w => {
      const el = document.querySelector(w);
      const c = el && el.querySelector('.swiper, .swiper-container');
      const sw = c && c.swiper;
      const active = el && el.querySelector('.swiper-slide-active');
      return {
        hasInstance: !!sw,
        realIndex: sw ? sw.realIndex : null,
        activeIndex: sw ? sw.activeIndex : null,
        activeLabel: active ? active.getAttribute('aria-label') : null,
        translate: sw ? Math.round(sw.translate) : null,
      };
    }, W);

    const before = await read();
    t.observe('before', before);
    // Assert the instance exists BEFORE asserting anything about its behaviour.
    // Without this the arrow assertions below would all compare null to null and
    // could be made to look coherent; the subject has to be proven present first.
    t.eq('the carousel has a live Swiper instance to drive', before.hasInstance, true);
    if (!before.hasInstance) return;

    await page.click(W + ' .elementor-swiper-button-next');
    await t.waitFor(
      (args) => {
        const c = document.querySelector(args.w).querySelector('.swiper, .swiper-container');
        const sw = c && c.swiper;
        return !!sw && sw.realIndex !== args.i && !sw.animating;
      },
      'Swiper.realIndex changed and the transition finished',
      { w: W, i: before.realIndex }
    );
    const afterNext = await read();
    t.observe('afterNext', afterNext);
    t.eq('next advances realIndex by exactly 1',
      afterNext.realIndex, before.realIndex + 1);
    t.neq('the active slide is a different slide',
      afterNext.activeLabel, before.activeLabel);
    t.neq('the track actually moved (Swiper translate)',
      afterNext.translate, before.translate);

    await page.click(W + ' .elementor-swiper-button-prev');
    await t.waitFor(
      (args) => {
        const c = document.querySelector(args.w).querySelector('.swiper, .swiper-container');
        const sw = c && c.swiper;
        return !!sw && sw.realIndex === args.i && !sw.animating;
      },
      'Swiper.realIndex returned to its starting value',
      { w: W, i: before.realIndex }
    );
    const afterPrev = await read();
    t.observe('afterPrev', afterPrev);
    t.eq('prev rewinds to the original slide', afterPrev.realIndex, before.realIndex);
    t.eq('the active slide label is restored', afterPrev.activeLabel, before.activeLabel);
  },
});

tests.push({
  id: 'carousel-autoplay',
  title: 'Carousels configured to autoplay are actually autoplaying',
  url: '/',
  viewport: 'desktop',
  asserts:
    'For every carousel widget whose data-settings say autoplay:"yes", the live ' +
    'Swiper instance reports autoplay.running === true. In addition, at least ' +
    'one autoplaying carousel is observed to advance realIndex on its own, with ' +
    'no interaction, within 25s.',
  why:
    'autoplay.running is a state, not a race, so it is the deterministic half of ' +
    'this test. The "does it actually move" half is the half a screenshot can ' +
    'never see, and it is why this file exists. Note the pixel harness ' +
    'deliberately FREEZES these carousels; nothing else in the project checks ' +
    'that they move.',
  async run({ page, t }) {
    const SEL = '.elementor-widget-testimonial-carousel, .elementor-widget-image-carousel, ' +
      '.elementor-widget-elementskit-client-logo';
    await t.exists(SEL, 'carousel widgets', 5);

    const snapshot = () => page.evaluate(sel =>
      [...document.querySelectorAll(sel)].map(w => {
        const el = w.querySelector('.swiper, .swiper-container');
        const sw = el && el.swiper;
        let settings = {};
        try { settings = JSON.parse(w.dataset.settings || '{}'); } catch (e) { /* none */ }
        return {
          widgetId: w.dataset.id,
          type: w.dataset.widget_type,
          autoplayConfigured: settings.autoplay === 'yes',
          running: !!(sw && sw.autoplay && sw.autoplay.running),
          realIndex: sw ? sw.realIndex : null,
        };
      }), SEL);

    const before = await snapshot();
    t.observe('carousels', before);

    const configured = before.filter(c => c.autoplayConfigured);
    t.gt('at least one carousel is configured to autoplay', configured.length, 0);
    configured.forEach(c =>
      t.eq(`[${c.type} ${c.widgetId}] autoplay is running`, c.running, true));

    // Now prove movement without touching anything. Waiting on a condition, not
    // on a fixed sleep: the loop resolves the moment any autoplaying carousel
    // has moved on its own.
    const baseline = Object.fromEntries(before.filter(c => c.running).map(c => [c.widgetId, c.realIndex]));
    const moved = await t.waitFor(
      (args) => {
        const els = [...document.querySelectorAll(args.sel)];
        return els.some(w => {
          const sw = (w.querySelector('.swiper, .swiper-container') || {}).swiper;
          return sw && args.base[w.dataset.id] !== undefined && sw.realIndex !== args.base[w.dataset.id];
        });
      },
      'an autoplaying carousel advanced with no interaction',
      { sel: SEL, base: baseline },
      25000
    );
    t.eq('a carousel advanced on its own', moved, true);
    t.observe('after', await snapshot());
  },
});

// ---------------------------------------------------------------------------
// 5. Search
// ---------------------------------------------------------------------------
for (const vp of ['desktop', 'tablet']) {
  tests.push({
    id: `search-classic-${vp}`,
    title: `Header search form submits and reaches the results page (${vp})`,
    url: '/',
    viewport: vp,
    asserts:
      'Exactly one .elementor-widget-search-form is visible at this viewport. ' +
      'Typing "software" and pressing its submit button navigates to a URL ' +
      'matching ?s=software and lands on a document whose <body> carries the ' +
      'search-results class.',
    why:
      'search-form is a Pro widget. Its submission is a plain GET form so it ' +
      'ought to survive without JS — which is exactly why it is worth pinning ' +
      'down: if the replacement widget renders the wrong action or drops the ' +
      'name="s" attribute, nothing visual changes and search silently 404s or ' +
      'returns the front page.',
    async run({ page, t, base }) {
      const W = await t.visibleOne('.elementor-widget-search-form', 'header search widget');
      const scoped = sel => `.elementor-widget-search-form[data-id="${W.widgetId}"] ${sel}`;
      await t.exists(scoped('input[name="s"]'), 'search input');
      await t.exists(scoped('button[type="submit"]'), 'search submit button');

      t.observe('widget', W);
      t.observe('formAction', await page.getAttribute(
        `.elementor-widget-search-form[data-id="${W.widgetId}"] form`, 'action'));

      // PRE-EXISTING SITE DEFECT, recorded rather than asserted.
      //
      // Both header search widgets were given the same custom CSS id in the
      // Elementor editor, so the document contains two elements with
      // id="pie-search". That is invalid HTML; every `#pie-search` selector — in
      // the site's own CSS, in any script, and in any test written the obvious
      // way — silently resolves to whichever comes first in source order, which
      // at tablet width is the INVISIBLE desktop copy. It is why every test in
      // this file scopes by data-id instead.
      const dupes = await page.evaluate(() =>
        [...document.querySelectorAll('[id]')]
          .reduce((acc, el) => { acc[el.id] = (acc[el.id] || 0) + 1; return acc; }, {}));
      const duplicated = Object.entries(dupes).filter(([, n]) => n > 1).map(([id, n]) => `${id} x${n}`);
      t.note('duplicate DOM ids in the document', duplicated,
        'authoring defect that predates the migration; recorded so a --compare run ' +
        'shows if a rebuild adds or removes any');

      await page.fill(scoped('input[name="s"]'), 'software');
      await Promise.all([
        page.waitForURL(/[?&]s=software/, { timeout: 30000 }),
        page.click(scoped('button[type="submit"]')),
      ]);

      const landed = await page.evaluate(() => ({
        url: location.href,
        bodyClass: document.body.className,
        query: new URLSearchParams(location.search).get('s'),
        resultCount: document.querySelectorAll('article, .elementor-post').length,
      }));
      t.observe('landed', landed);
      t.eq('the query survived the round trip', landed.query, 'software');
      t.truthy('landed on a search-results document',
        /\bsearch-results\b|\bsearch\b/.test(landed.bodyClass));
      t.truthy('the results URL is on this site', landed.url.startsWith(base));
    },
  });
}

tests.push({
  id: 'search-ekit-modal-mobile',
  title: 'ElementsKit modal search opens and submits (mobile)',
  url: '/',
  viewport: 'mobile',
  asserts:
    'At 375px neither classic search form is visible; instead exactly one ' +
    '.elementor-widget-elementskit-header-search is. Clicking its button opens a ' +
    'Magnific Popup (.mfp-wrap) containing a visible .ekit_search-field; typing ' +
    'and submitting reaches ?s=software.',
  why:
    'This is the only search a phone visitor can reach. ElementsKit is free so it ' +
    'should survive, but the modal depends on Magnific Popup being enqueued, and ' +
    'script enqueues are exactly what shifts when a plugin is swapped out.',
  async run({ page, t }) {
    const classicVisible = await page.evaluate(() =>
      [...document.querySelectorAll('.elementor-widget-search-form')]
        .filter(w => w.getBoundingClientRect().width > 0).length);
    t.observe('classicSearchWidgetsVisibleAtMobile', classicVisible);
    t.eq('no classic search form is reachable at 375px', classicVisible, 0);

    const W = await t.visibleOne('.elementor-widget-elementskit-header-search',
      'ElementsKit header search widget');
    const scoped = `.elementor-widget-elementskit-header-search[data-id="${W.widgetId}"]`;
    await t.visibleOne(scoped + ' .ekit_navsearch-button', 'search modal trigger');

    t.eq('no modal is open to begin with',
      await page.evaluate(() => document.querySelectorAll('.mfp-wrap').length), 0);

    await page.click(scoped + ' .ekit_navsearch-button');
    await t.waitFor(
      () => {
        const w = document.querySelector('.mfp-wrap');
        if (!w) return false;
        const inp = w.querySelector('.ekit_search-field');
        return !!inp && inp.getBoundingClientRect().width > 0;
      },
      'Magnific Popup opened with a visible search field'
    );
    const open = await page.evaluate(() => {
      const w = document.querySelector('.mfp-wrap');
      const inp = w && w.querySelector('.ekit_search-field');
      const form = w && w.querySelector('form');
      return {
        mfpWraps: document.querySelectorAll('.mfp-wrap').length,
        inputWidth: inp ? Math.round(inp.getBoundingClientRect().width) : 0,
        formAction: form ? form.getAttribute('action') : null,
      };
    });
    t.observe('modalOpen', open);
    t.eq('exactly one modal opened', open.mfpWraps, 1);
    t.gt('the search field is on screen', open.inputWidth, 0);

    await page.fill('.mfp-wrap .ekit_search-field', 'software');
    await Promise.all([
      page.waitForURL(/[?&]s=software/, { timeout: 30000 }),
      page.click('.mfp-wrap .ekit_search-button'),
    ]);
    const landed = await page.evaluate(() => ({
      query: new URLSearchParams(location.search).get('s'),
      bodyClass: document.body.className,
    }));
    t.observe('landed', landed);
    t.eq('the modal search reached the results page', landed.query, 'software');
    t.truthy('landed on a search-results document', /\bsearch\b/.test(landed.bodyClass));
  },
});

// ---------------------------------------------------------------------------
// 6. Contact form  (Elementor Pro form widget)
// ---------------------------------------------------------------------------
tests.push({
  id: 'form-validation-blocks-empty-submit',
  title: 'Contact form refuses an empty submit and does not navigate',
  url: '/contact-us/',
  viewport: 'desktop',
  asserts:
    'The form has required fields; pressing submit with everything empty leaves ' +
    'form.checkValidity() false, produces at least one field failing ' +
    'checkValidity(), sends NO request off the page, and leaves the URL unchanged.',
  why:
    'Elementor Pro renders both the required attributes and the submit handler. ' +
    'A replacement that drops `required` still looks identical and starts ' +
    'accepting blank enquiries.',
  async run({ page, t, blockedRequests }) {
    await t.exists('#pie-contact-form form.elementor-form', 'contact form', 1);
    await t.visibleOne('#pie-contact-form button[type="submit"]', 'submit button');

    const fields = await page.evaluate(() => {
      const f = document.querySelector('#pie-contact-form form.elementor-form');
      return {
        required: [...f.querySelectorAll('[required]')].map(e => e.name || e.id),
        total: f.querySelectorAll('input, select, textarea').length,
      };
    });
    t.observe('fields', fields);
    t.gt('the form declares required fields', fields.required.length, 0);

    // PRE-EXISTING SITE DEFECT, recorded rather than asserted.
    //
    // The "Product" <select> is marked required, but its placeholder option is
    // <option value="Select Service">, i.e. a non-empty value. HTML5 only treats
    // a required select as invalid when the selected option's value is the empty
    // string, so this field passes validation while nothing has been chosen —
    // every enquiry can arrive with Product = "Select Service".
    //
    // This is a content/authoring bug that predates the migration and has
    // nothing to do with Elementor Pro, so failing the baseline over it would be
    // noise. It is recorded here so that (a) it is on the record, and (b) if a
    // rebuild ever fixes it the note changes and someone notices.
    const placeholder = await page.evaluate(() => {
      const s = document.getElementById('form-field-contact_us_products');
      if (!s) return null;
      return { firstOptionValue: s.options[0].value, required: s.required, validWhileUnchosen: s.checkValidity() };
    });
    t.note('required select accepts its own placeholder', placeholder,
      'pre-existing authoring defect: the placeholder option has a non-empty value ' +
      '("Select Service"), so the required attribute never bites. Not caused by, and ' +
      'not fixed by, the Pro replacement.');

    const urlBefore = page.url();
    const requestsBefore = blockedRequests.length;

    await page.evaluate(() => document.querySelector('#pie-contact-form').scrollIntoView({ block: 'center' }));
    await page.click('#pie-contact-form button[type="submit"]');

    // Give any handler a chance to act, then assert on state rather than timing.
    // There is nothing to wait FOR here — the assertion is that nothing happened —
    // so this is the one place a fixed dwell is honest. Kept short and explicit.
    await page.waitForTimeout(2500);

    const after = await page.evaluate(() => {
      const f = document.querySelector('#pie-contact-form form.elementor-form');
      const req = [...f.querySelectorAll('[required]')];
      return {
        formValid: f.checkValidity(),
        invalidFields: req.filter(e => !e.checkValidity()).map(e => e.name || e.id),
        messages: [...f.querySelectorAll('.elementor-message')].map(e => e.textContent.trim().slice(0, 80)),
      };
    });
    t.observe('afterEmptySubmit', after);
    t.observe('urlAfter', page.url());
    t.observe('nonGetRequestsAttempted', blockedRequests.slice(requestsBefore));

    t.eq('the form reports itself invalid', after.formValid, false);
    t.gt('at least one required field is failing validation', after.invalidFields.length, 0);
    t.eq('the browser did not navigate away', page.url(), urlBefore);
    t.eq('nothing was sent to the server',
      blockedRequests.length - requestsBefore, 0);
  },
});

tests.push({
  id: 'form-recaptcha-v3-token',
  title: 'A validly-filled submit produces a reCAPTCHA v3 token (submission blocked)',
  url: '/contact-us/',
  viewport: 'desktop',
  asserts:
    'With every required field filled, pressing submit causes exactly one POST to ' +
    'wp-admin/admin-ajax.php whose multipart body carries a NON-EMPTY ' +
    'g-recaptcha-response value. The runner aborts every non-GET request before ' +
    'it leaves the browser, so the enquiry is never delivered and no mail is sent.',
  why:
    'The reCAPTCHA v3 field is rendered AND executed by Elementor Pro. A ' +
    'replacement that renders the badge but never calls grecaptcha.execute() ' +
    'looks perfect and gets every real submission rejected by the server.',
  network:
    'Depends on www.google.com/recaptcha being reachable. If this test alone ' +
    'fails, check the network before blaming the site.',
  async run({ page, t, blockedRequests }) {
    await t.exists('#pie-contact-form form.elementor-form', 'contact form', 1);
    await t.exists('#pie-contact-form .elementor-g-recaptcha[data-type="v3"]', 'reCAPTCHA v3 field', 1);

    const grecaptcha = await page.evaluate(() => typeof window.grecaptcha);
    t.observe('grecaptcha', grecaptcha);
    t.eq('the Google reCAPTCHA API loaded', grecaptcha, 'object');

    await page.fill('#form-field-contact_us_full_name', 'Harness Probe');
    await page.fill('#form-field-contact_us_email', 'harness@example.invalid');
    await page.fill('#form-field-contact_us_message',
      'Automated behaviour-harness probe. This submission is aborted in the browser and never reaches the server.');
    await page.selectOption('#form-field-contact_us_products', 'Others');

    const requestsBefore = blockedRequests.length;
    const urlBefore = page.url();
    await page.evaluate(() => document.querySelector('#pie-contact-form').scrollIntoView({ block: 'center' }));
    await page.click('#pie-contact-form button[type="submit"]');

    await t.waitForNode(
      () => blockedRequests.length > requestsBefore,
      'the form attempted a POST (which the runner aborted)',
      30000
    );

    const attempts = blockedRequests.slice(requestsBefore);
    const posts = attempts.filter(r => r.method === 'POST' && /admin-ajax\.php/.test(r.url));
    t.observe('attemptedRequests', attempts.map(r => ({ method: r.method, url: r.url, bodyBytes: (r.body || '').length })));
    t.eq('exactly one admin-ajax POST was attempted', posts.length, 1);
    t.eq('the runner aborted it, so nothing reached the server',
      attempts.every(r => r.aborted), true);

    const body = posts[0] ? posts[0].body || '' : '';
    const token = extractMultipartField(body, 'g-recaptcha-response');
    t.observe('recaptchaTokenLength', token ? token.length : 0);
    t.observe('recaptchaTokenPrefix', token ? token.slice(0, 12) : null);
    t.truthy('a g-recaptcha-response field is present in the POST', token !== null);
    t.gt('the reCAPTCHA token is non-empty', token ? token.length : 0, 20);
    t.eq('the page did not navigate', page.url(), urlBefore);
  },
});

/**
 * Pull the last non-empty value for `name` out of a multipart/form-data body.
 *
 * "Last non-empty" on purpose: the page contains TWO fields called
 * g-recaptcha-response — Google's own always-empty <textarea> from the invisible
 * badge, and the one Elementor Pro appends carrying the real v3 token. Taking
 * the first match would read the empty one and report a false failure.
 */
function extractMultipartField(body, name) {
  const re = new RegExp(`name="${name}"\\r?\\n\\r?\\n([\\s\\S]*?)\\r?\\n------`, 'g');
  let best = null;
  for (const m of body.matchAll(re)) {
    const v = m[1];
    if (best === null) best = v;
    if (v && v.length) best = v;
  }
  return best;
}

// ---------------------------------------------------------------------------
// 7. Popup  (Elementor Pro)
// ---------------------------------------------------------------------------
tests.push({
  id: 'popup-consultation-opens',
  title: 'The "Request A Consultation" CTA opens the consultation popup',
  url: '/',
  viewport: 'desktop',
  asserts:
    'No .elementor-popup-modal exists on load. Clicking the CTA whose href is an ' +
    'elementor-action popup:open link causes a popup document to be fetched and ' +
    'inserted, producing exactly one displayed .elementor-popup-modal with ' +
    'non-zero height and data-elementor-type="popup".',
  why:
    'Popups are pure Elementor Pro: the trigger href is an opaque base64 action ' +
    'that only Pro\'s frontend router understands, and the popup body is not in ' +
    'the page at all — it is fetched over admin-ajax on click. Without Pro the ' +
    'CTA becomes a link to a "#" fragment and every conversion path on the site ' +
    'dead-ends. Nothing about the button changes visually.',
  async run({ page, t }) {
    const TRIGGER = 'a[href*="popup%3Aopen"]';
    await t.exists(TRIGGER, 'popup trigger link');

    const triggers = await page.evaluate(sel =>
      [...document.querySelectorAll(sel)].map(a => a.textContent.trim().replace(/\s+/g, ' ').slice(0, 40)), TRIGGER);
    t.observe('triggerLabels', triggers);

    t.eq('no popup modal on load',
      await page.evaluate(() => document.querySelectorAll('.elementor-popup-modal').length), 0);

    const clicked = await page.evaluate(sel => {
      const all = [...document.querySelectorAll(sel)];
      const a = all.find(x => /consultation/i.test(x.textContent)) || all[0];
      if (!a) return null;
      a.scrollIntoView({ block: 'center' });
      a.click();
      return a.textContent.trim().replace(/\s+/g, ' ').slice(0, 40);
    }, TRIGGER);
    t.observe('clickedTrigger', clicked);
    t.truthy('a popup trigger was found and clicked', clicked !== null);

    await t.waitFor(
      () => {
        const m = document.querySelector('.elementor-popup-modal');
        return !!m && getComputedStyle(m).display !== 'none' &&
          m.getBoundingClientRect().height > 0;
      },
      'a popup modal was inserted and is displayed',
      undefined,
      30000
    );

    // Tolerates "no modal at all" and reports it as data, so the failure reads
    // "exactly one modal is open: expected 1, observed 0" rather than a
    // getComputedStyle TypeError from inside the evaluate.
    const open = await page.evaluate(() => {
      const m = document.querySelector('.elementor-popup-modal');
      const doc = document.querySelector('[data-elementor-type="popup"]');
      return {
        modals: document.querySelectorAll('.elementor-popup-modal').length,
        display: m ? getComputedStyle(m).display : null,
        height: m ? Math.round(m.getBoundingClientRect().height) : 0,
        popupDocumentId: doc ? doc.dataset.elementorId : null,
        hasFormInside: m ? !!m.querySelector('form') : false,
        text: m ? m.textContent.trim().replace(/\s+/g, ' ').slice(0, 60) : null,
      };
    });
    t.observe('popup', open);
    t.eq('exactly one modal is open', open.modals, 1);
    // truthy, not neq('none'): with no modal at all `display` is null, and
    // null !== 'none' would have passed. An absent subject must never satisfy an
    // assertion about that subject.
    t.truthy('the modal is displayed', open.display && open.display !== 'none');
    t.gt('the modal has height', open.height, 0);
    t.truthy('the popup document was actually loaded', open.popupDocumentId !== null);
  },
});

// ---------------------------------------------------------------------------
// 8. Free interactive widgets that must survive
// ---------------------------------------------------------------------------
tests.push({
  id: 'toggle-faq-expands',
  title: 'The FAQ toggle (free Elementor toggle widget) opens and closes',
  url: '/why-healthcare-software-fails-without-security-first-architecture/',
  viewport: 'desktop',
  asserts:
    'The visible .elementor-widget-toggle starts with every tab collapsed ' +
    '(aria-expanded=false, content display:none). Clicking the first title sets ' +
    'aria-expanded=true, adds .elementor-active and gives the panel height. ' +
    'Clicking again collapses it.',
  why:
    'The toggle widget is free Elementor, so it should be untouched — but its ' +
    'handler is registered through the same elementor-frontend module registry ' +
    'that Pro extends, and a botched replacement can break registration for ' +
    'everyone. On 15 of the 21 toggle instances site-wide the widget is hidden at ' +
    'every breakpoint (see README), so this is the only page it can be tested on.',
  async run({ page, t }) {
    const W = await t.visibleOne('.elementor-widget-toggle', 'FAQ toggle widget');
    const scope = `.elementor-widget-toggle[data-id="${W.widgetId}"]`;
    await t.exists(scope + ' .elementor-tab-title', 'toggle titles');

    const read = () => page.evaluate(s => {
      const w = document.querySelector(s);
      const title = w.querySelector('.elementor-tab-title');
      const content = w.querySelector('.elementor-tab-content');
      return {
        items: w.querySelectorAll('.elementor-toggle-item').length,
        expandedCount: [...w.querySelectorAll('.elementor-tab-title')]
          .filter(x => x.getAttribute('aria-expanded') === 'true').length,
        firstExpanded: title.getAttribute('aria-expanded'),
        firstActive: title.classList.contains('elementor-active'),
        contentDisplay: getComputedStyle(content).display,
        contentHeight: Math.round(content.getBoundingClientRect().height),
      };
    }, scope);

    const before = await read();
    t.observe('collapsed', before);
    t.gt('the widget has toggle items', before.items, 0);
    t.eq('everything starts collapsed', before.expandedCount, 0);
    t.eq('first panel is display:none', before.contentDisplay, 'none');

    await page.evaluate(s => document.querySelector(s).scrollIntoView({ block: 'center' }), scope);
    await page.click(scope + ' .elementor-tab-title');
    await t.waitFor(
      s => {
        const w = document.querySelector(s);
        return w.querySelector('.elementor-tab-title').getAttribute('aria-expanded') === 'true' &&
          w.querySelector('.elementor-tab-content').getBoundingClientRect().height > 0;
      },
      'first panel reports expanded AND has height',
      scope
    );
    const opened = await read();
    t.observe('expanded', opened);
    t.eq('aria-expanded flips to true', opened.firstExpanded, 'true');
    t.eq('title gains .elementor-active', opened.firstActive, true);
    t.gt('the panel has height', opened.contentHeight, 0);

    await page.click(scope + ' .elementor-tab-title');
    await t.waitFor(
      s => document.querySelector(s).querySelector('.elementor-tab-content')
        .getBoundingClientRect().height === 0,
      'first panel collapsed again',
      scope
    );
    const reclosed = await read();
    t.observe('collapsedAgain', reclosed);
    t.eq('the panel closes again', reclosed.contentHeight, 0);
  },
});

tests.push({
  id: 'back-to-top',
  title: 'ElementsKit back-to-top appears on scroll and returns to the top',
  url: '/why-healthcare-software-fails-without-security-first-architecture/',
  viewport: 'desktop',
  asserts:
    'The .ekit-btt__button is transparent at scrollY 0; after scrolling past its ' +
    'configured show_after threshold it gains .ekit-tt-show and opacity 1; ' +
    'clicking it returns window.scrollY to 0 and it fades out again.',
  why:
    'ElementsKit is free, so this is a canary for "did the plugin swap break ' +
    'script enqueueing for everyone", not a Pro dependency of its own.',
  async run({ page, t }) {
    await t.exists('.ekit-back-to-top-container', 'ElementsKit back-to-top container', 1);
    await t.exists('.ekit-btt__button', 'back-to-top button', 1);

    const settings = await page.evaluate(() => {
      try { return JSON.parse(document.querySelector('.ekit-back-to-top-container').dataset.settings); }
      catch (e) { return null; }
    });
    t.observe('settings', settings);
    t.truthy('the widget carries its settings', settings !== null);

    await page.evaluate(() => window.scrollTo(0, 0));
    await t.waitFor(() => window.scrollY === 0, 'page is scrolled to the top');
    const atTop = await page.evaluate(() => {
      const b = document.querySelector('.ekit-btt__button');
      return { opacity: getComputedStyle(b).opacity, shown: b.classList.contains('ekit-tt-show') };
    });
    t.observe('atTop', atTop);
    t.eq('hidden at the top of the page', atTop.shown, false);

    const threshold = (settings && settings.show_after) || 1500;
    await page.evaluate(y => window.scrollTo(0, y), threshold + 800);
    await t.waitFor(
      () => document.querySelector('.ekit-btt__button').classList.contains('ekit-tt-show'),
      'back-to-top button revealed itself after scrolling past its threshold'
    );
    const scrolled = await page.evaluate(() => {
      const b = document.querySelector('.ekit-btt__button');
      return { opacity: getComputedStyle(b).opacity, shown: b.classList.contains('ekit-tt-show'), scrollY: Math.round(window.scrollY) };
    });
    t.observe('scrolled', scrolled);
    t.eq('button becomes visible', scrolled.shown, true);
    t.eq('button becomes opaque', scrolled.opacity, '1');

    await page.click('.ekit-btt__button');
    await t.waitFor(() => window.scrollY === 0, 'clicking it scrolled the page back to the top');
    const after = await page.evaluate(() => ({
      scrollY: Math.round(window.scrollY),
      shown: document.querySelector('.ekit-btt__button').classList.contains('ekit-tt-show'),
    }));
    t.observe('afterClick', after);
    t.eq('the page is back at the top', after.scrollY, 0);
    t.eq('the button hides itself again', after.shown, false);
  },
});

// ---------------------------------------------------------------------------
// 9. Console cleanliness — one test per page
// ---------------------------------------------------------------------------
for (const [name, url] of CONSOLE_PAGES) {
  tests.push({
    id: `console-clean-${name}`,
    title: `No uncaught JS errors on ${url}`,
    url,
    viewport: 'desktop',
    asserts:
      'After load and settle, the page has produced zero uncaught exceptions ' +
      '(pageerror) and zero console.error messages other than "Failed to load ' +
      'resource" network noise, which is recorded separately as a broken-asset ' +
      'observation and compared against the baseline.',
    why:
      'A missing frontend handler usually announces itself as a TypeError long ' +
      'before anyone notices the widget is dead. This is the cheapest broad net ' +
      'in the suite.',
    async run({ page, t, consoleErrors, pageErrors, brokenAssets }) {
      // The runner has already loaded and settled the page. Give any deferred
      // handler one more settle pass so late errors are caught too.
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      await page.waitForTimeout(1500);
      await page.evaluate(() => window.scrollTo(0, 0));
      await page.waitForTimeout(1000);

      t.observe('pageErrors', pageErrors);
      t.observe('consoleErrors', consoleErrors);
      t.observe('brokenAssets', brokenAssets);
      t.eq('zero uncaught exceptions', pageErrors.length, 0);
      t.eq('zero non-network console errors', consoleErrors.length, 0);
      t.note('brokenAssetCount', brokenAssets.length,
        'network 404s/failures — reported, and a NEW one fails a --compare run');
    },
  });
}

module.exports = { tests, VIEWPORTS, CONSOLE_PAGES };
