<?php
/**
 * Third-party tags that have to be in the document head.
 *
 * Both of these are site-critical, neither is recoverable from anywhere else,
 * and both were buried in the previous theme's header.php and functions.php.
 * They are carried explicitly, with the identifiers in named constants so a
 * `grep GTM-` or `grep site-verification` finds them.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google Tag Manager container id.
 *
 * Losing this silently ends analytics for the whole site: nothing errors,
 * nothing looks wrong, the numbers just stop. It was on all 39 captured pages
 * and it was NOT in the audit's list of the previous theme's customisations,
 * which is exactly why it is called out here.
 */
const PIECYFER_GTM_ID = 'GTM-WDKS9B2G';

/**
 * Google Search Console site-verification token.
 *
 * Losing this un-verifies the property. Nothing breaks; Search Console just
 * stops reporting, and nobody notices for weeks.
 */
const PIECYFER_GOOGLE_SITE_VERIFICATION = 'VmNgA23F6JsL4JinPIqE7Wc7T57e0IHuPsRgvzC0Blk';

/**
 * The Google Tag Manager loader.
 *
 * Called directly from header.php rather than hooked to `wp_head`, because GTM
 * wants to be as early in the head as it can and `wp_head` fires after the
 * theme's own meta tags.
 *
 * One deliberate change: the previous theme emitted this *before*
 * `<meta charset>`, which is a spec violation - the encoding declaration has to
 * appear in the first 1024 bytes and before any content that could be
 * mis-decoded. The snippet now follows the charset, X-UA-Compatible and
 * viewport metas. That is a one-hunk markup diff on every page, and it is
 * intentional; the container, the snippet and its position relative to
 * `wp_head()` are otherwise byte-for-byte unchanged.
 *
 * @return void
 */
function piecyfer_google_tag_manager_head() {
	if ( '' === PIECYFER_GTM_ID ) {
		return;
	}

	$id = esc_js( PIECYFER_GTM_ID );

	echo "\t<!-- Google Tag Manager -->\n";
	echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
	echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
	echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
	echo "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
	echo "})(window,document,'script','dataLayer','" . $id . "');</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "<!-- End Google Tag Manager -->\n";
}

/**
 * The Google Tag Manager <noscript> fallback.
 *
 * Emitted directly after `<body>` from header.php, ahead of `#top` and
 * `wp_body_open()`, which is where the previous theme put it. Hooking it to
 * `wp_body_open` instead would move it below `#top` - a markup diff for no
 * benefit.
 *
 * @return void
 */
function piecyfer_google_tag_manager_body() {
	if ( '' === PIECYFER_GTM_ID ) {
		return;
	}

	$src = 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode( PIECYFER_GTM_ID );

	echo "\t<!-- Google Tag Manager (noscript) -->\n";
	echo '<noscript><iframe src="' . esc_url( $src ) . "\"\n";
	echo "height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
	echo "<!-- End Google Tag Manager (noscript) -->\n";
}

/**
 * Performance: Resource hints (preconnect and dns-prefetch) for critical third-party origins.
 *
 * @return void
 */
function piecyfer_resource_hints() {
	echo "\t<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
	echo "\t<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
	echo "\t<link rel=\"preconnect\" href=\"https://www.googletagmanager.com\">\n";
	echo "\t<link rel=\"dns-prefetch\" href=\"https://fonts.googleapis.com\">\n";
	echo "\t<link rel=\"dns-prefetch\" href=\"https://fonts.gstatic.com\">\n";
	echo "\t<link rel=\"dns-prefetch\" href=\"https://www.googletagmanager.com\">\n";
}
add_action( 'wp_head', 'piecyfer_resource_hints', 2 );


/**
 * Google Search Console ownership proof.
 *
 * @return void
 */
function piecyfer_google_site_verification() {
	if ( '' === PIECYFER_GOOGLE_SITE_VERIFICATION ) {
		return;
	}

	printf(
		'<meta name="google-site-verification" content="%s" />' . "\n",
		esc_attr( PIECYFER_GOOGLE_SITE_VERIFICATION )
	);
}
add_action( 'wp_head', 'piecyfer_google_site_verification' );
