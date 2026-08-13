/**
 * Strip everything that legitimately changes between two runs of an unchanged
 * site. Without this every diff is 100% noise: WordPress regenerates nonces per
 * request, Elementor appends ?ver= cache-busters, and several plugins emit
 * timestamps and random element ids.
 *
 * Anything NOT stripped here is content we are asserting must stay identical,
 * so each rule below is a deliberate decision to stop checking something.
 *
 * Lives in its own module so `renormalise.js` can re-apply the exact same rules
 * to already-captured baselines. Duplicating the function would let the two
 * drift apart, and a baseline normalised by different rules than the run it is
 * compared against is worse than no baseline.
 */
function normalise(html) {
  return html
    // nonces and one-time tokens
    .replace(/(nonce["':=\s]+)[a-f0-9]{8,12}/gi, '$1__NONCE__')
    .replace(/(_wpnonce=)[a-f0-9]{8,12}/gi, '$1__NONCE__')
    .replace(/("(?:nonce|_?ajax_nonce|rest_nonce)"\s*:\s*")[^"]+/gi, '$1__NONCE__')
    // asset cache-busters
    .replace(/([?&]ver=)[^"'&\s]+/gi, '$1__VER__')
    // elementor / plugin generated ids that are random per render
    .replace(/(elementor-element-)[a-f0-9]{6,9}\b/gi, '$1__EID__')
    .replace(/(id="[a-z-]*?)[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/gi, '$1__UUID__')
    // SmartMenus stamps every <ul> with timestamp+random on init, and derives
    // sub-menu ids and aria-labelledby references from the same number
    // (id="sm-17866606281748918-2"). 17-19 digits, so the generic timestamp
    // rule below does not reach it.
    .replace(/(data-smartmenus-id=")\d+/gi, '$1__SMID__')
    .replace(/\bsm-\d{13,}-/g, 'sm-__SMID__-')
    // WordPress injects the emoji polyfill from JS, so it lands before or after
    // the neighbouring <style> depending on timing. Its presence is not
    // something we assert on, and its position is pure noise.
    .replace(/<script[^>]*wp-emoji-release[^>]*><\/script>/gi, '')
    // Swiper assigns each track a random id on init.
    .replace(/(swiper-wrapper-)[0-9a-f]{8,}/gi, '$1__SWID__')
    // reCAPTCHA's iframe name and callback token are regenerated per render.
    // Third-party, and not something this project changes.
    .replace(/(name="a-)[a-z0-9]+(")/gi, '$1__RC__$2')
    // Note `\bcb=` rather than `[?&]cb=`: in the rendered iframe src the
    // separator is the HTML entity `&amp;`, so the character before `cb` is a
    // semicolon.
    .replace(/\bcb=[a-z0-9]{6,}/gi, 'cb=__RC__')
    // timestamps and dates-of-render
    .replace(/\b\d{10,13}\b/g, '__TS__')
    .replace(/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[^"'<\s]*/g, '__ISODATE__')
    // whitespace noise
    .replace(/\r\n/g, '\n')
    .replace(/[ \t]+$/gm, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

module.exports = { normalise };
