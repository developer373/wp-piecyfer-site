<?php
/**
 * Asset pipeline.
 *
 * Four stylesheets, two scripts, two @font-face rules and one localisation
 * object. That is the theme's entire contribution to the page's assets; the
 * previous theme served exactly the same four stylesheets and nothing else on
 * all 39 captured pages.
 *
 * Versioning is `filemtime()` per file rather than one theme-wide constant, so
 * editing one stylesheet does not bust the cache of the other three, and so a
 * forgotten version bump cannot serve stale CSS.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * The widgets whose Elementor controls the companion layer may extend.
 *
 * Published to the browser as `VAMTAM_FRONT.widget_mods_list` and read by the
 * companion plugin's JavaScript, which mirrors `is_widget_mod_active()` client
 * side. The order is the order it is displayed in, and a widget absent from
 * this list has its modifications treated as off.
 *
 * @return array<string,array{label:string}>
 */
function piecyfer_widget_mods_list() {
	return array(
		'button'               => array( 'label' => esc_html__( 'Button', 'piecyfer-theme' ) ),
		'form'                 => array( 'label' => esc_html__( 'Form', 'piecyfer-theme' ) ),
		'tabs'                 => array( 'label' => esc_html__( 'Tabs', 'piecyfer-theme' ) ),
		'icon-box'             => array( 'label' => esc_html__( 'Icon Box', 'piecyfer-theme' ) ),
		'image-box'            => array( 'label' => esc_html__( 'Image Box', 'piecyfer-theme' ) ),
		'nav-menu'             => array( 'label' => esc_html__( 'Nav Menu', 'piecyfer-theme' ) ),
		'section'              => array( 'label' => esc_html__( 'Section', 'piecyfer-theme' ) ),
		'toggle'               => array( 'label' => esc_html__( 'Toggle', 'piecyfer-theme' ) ),
		'testimonial-carousel' => array( 'label' => esc_html__( 'Testimonial Carousel', 'piecyfer-theme' ) ),
		'search-form'          => array( 'label' => esc_html__( 'Search Form', 'piecyfer-theme' ) ),
		'archive-posts'        => array( 'label' => esc_html__( 'Archive Posts', 'piecyfer-theme' ) ),
		'posts'                => array( 'label' => esc_html__( 'Posts', 'piecyfer-theme' ) ),
		'post-navigation'      => array( 'label' => esc_html__( 'Post Navigation', 'piecyfer-theme' ) ),
		'post-comments'        => array( 'label' => esc_html__( 'Post Comments', 'piecyfer-theme' ) ),
		'popup'                => array( 'label' => esc_html__( 'Popup', 'piecyfer-theme' ) ),
	);
}

/**
 * Cache-busting version for a theme-relative asset.
 *
 * @param string $relative_path Path below the theme root.
 * @return string
 */
function piecyfer_asset_version( $relative_path ) {
	$file = PIECYFER_THEME_DIR . $relative_path;

	if ( is_readable( $file ) ) {
		$mtime = filemtime( $file );

		if ( $mtime ) {
			return (string) $mtime;
		}
	}

	return PIECYFER_THEME_VERSION;
}

/**
 * One of Elementor's active breakpoints, in the theme's terms.
 *
 * `lg` is where the desktop stylesheet starts, `md` where the phone stylesheet
 * ends. Elementor moved its own defaults down by one pixel at v3.5 (1024/767
 * instead of 1025/768) while the compiled CSS in assets/css/ was built against
 * the older numbers, so those two values are mapped back. Change this and the
 * three responsive stylesheets start overlapping or leaving a one-pixel gap.
 *
 * @param string $device `lg` or `md`.
 * @return int
 */
function piecyfer_breakpoint( $device ) {
	$defaults = array(
		'lg' => 1025,
		'md' => 768,
	);

	if ( ! isset( $defaults[ $device ] ) ) {
		return 0;
	}

	$elementor_key = ( 'lg' === $device ) ? 'tablet' : 'mobile';

	if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->breakpoints ) ) {
		return $defaults[ $device ];
	}

	$breakpoints = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();

	if ( empty( $breakpoints[ $elementor_key ] ) || ! method_exists( $breakpoints[ $elementor_key ], 'get_value' ) ) {
		return $defaults[ $device ];
	}

	$value = (int) $breakpoints[ $elementor_key ]->get_value();

	if ( 767 === $value ) {
		return 768;
	}

	if ( 1024 === $value ) {
		return 1025;
	}

	return $value;
}

/**
 * The two @font-face rules for the theme's own icon fonts.
 *
 * Inlined rather than written into theme.css so the stylesheet stays a
 * byte-for-byte copy of the file it was lifted from, which is what makes it
 * verifiable. The previous theme inlined them too, as
 * `vamtam-front-all-inline-css`.
 *
 * The old `icomoon` rule listed a third source, `icomoon.ttf format('ttf')`.
 * `ttf` is not a valid format keyword - the correct one is `truetype` - so
 * every browser discarded that entry. It is dropped here along with the file,
 * which saves 206 KB from the theme with no rendering consequence.
 *
 * @return string
 */
function piecyfer_icon_font_faces() {
	$fonts = PIECYFER_THEME_URI . 'assets/fonts/';

	return "
@font-face {
	font-family: 'icomoon';
	src: url({$fonts}icons/icomoon.woff2) format('woff2'),
		url({$fonts}icons/icomoon.woff) format('woff');
	font-weight: normal;
	font-style: normal;
	font-display: swap;
}

@font-face {
	font-family: 'vamtam-theme';
	src: url({$fonts}theme-icons/theme-icons.woff2) format('woff2'),
		url({$fonts}theme-icons/theme-icons.woff) format('woff');
	font-weight: normal;
	font-style: normal;
	font-display: swap;
}
";
}

/**
 * Front-end styles.
 *
 * @return void
 */
function piecyfer_enqueue_styles() {
	$large = piecyfer_breakpoint( 'lg' );
	$small = piecyfer_breakpoint( 'md' );

	wp_enqueue_style(
		'elementor-icons-theme-icons',
		content_url( 'uploads/elementor/custom-icons/theme-icons/style.css' ),
		array(),
		'1.0.0'
	);

	wp_enqueue_style(
		'vamtam-front-all',
		PIECYFER_THEME_URI . 'assets/css/theme.css',
		array(),
		piecyfer_asset_version( 'assets/css/theme.css' )
	);

	wp_add_inline_style( 'vamtam-front-all', piecyfer_icon_font_faces() );

	$responsive = array(
		'max'       => array(
			'handle' => 'vamtam-theme-elementor-max',
			'file'   => 'assets/css/theme-max.css',
			'media'  => '(min-width: ' . $large . 'px)',
		),
		'below-max' => array(
			'handle' => 'vamtam-theme-elementor-below-max',
			'file'   => 'assets/css/theme-below-max.css',
			'media'  => '(max-width: ' . ( $large - 1 ) . 'px)',
		),
		'small'     => array(
			'handle' => 'vamtam-theme-elementor-small',
			'file'   => 'assets/css/theme-small.css',
			'media'  => '(max-width: ' . ( $small - 1 ) . 'px)',
		),
	);

	foreach ( $responsive as $item ) {
		wp_enqueue_style(
			$item['handle'],
			PIECYFER_THEME_URI . $item['file'],
			array( 'vamtam-front-all' ),
			piecyfer_asset_version( $item['file'] ),
			$item['media']
		);
	}
}
add_action( 'wp_enqueue_scripts', 'piecyfer_enqueue_styles', 999 );

/**
 * Flush any stylesheet enqueued after `wp_head()` ran.
 *
 * Called from single.php and 404.php, and from nowhere else, because those are
 * the only two routes where the previous theme called
 * `VamtamEnqueues::enqueue_style_and_print()` - which enqueued a handle from
 * the theme's non-Elementor branch (`vamtam-blog`, `vamtam-not-found`, neither
 * of which resolves to a file on this site) and then called
 * `wp_print_styles()`.
 *
 * The handle was irrelevant; the flush was not. Elementor
 * registers the popup documents' stylesheets while the Theme Builder document
 * renders, i.e. after `wp_head()` has already run, and this call is what
 * flushes them. That is why `elementor-post-991721-css` and
 * `elementor-post-992998-css` appear inside `#main` on the 16 single posts and
 * the 404 page, and inside `</head>` on all 23 remaining captured pages.
 *
 * This is a defect, not a feature: it puts two render-blocking stylesheets in
 * the middle of the body. It is reproduced only so the theme swap is a zero-
 * diff change. Delete this function and its two call sites in a separate
 * commit - the stylesheets then print from `wp_footer()`, which is a markup
 * diff on 17 pages that needs its own capture and its own triage.
 *
 * It must be `print_late_styles()` and NOT `wp_print_styles()`. The latter
 * fires the `wp_print_styles` action, which is where `inc/tokens.php` hangs the
 * `:root` block at priority 1 - so it re-emits all 171 custom properties a
 * second time, in the middle of `#main`, on every single post and the 404.
 * `print_late_styles()` only flushes the footer queue, which is what the
 * previous theme's `VamtamEnqueues::enqueue_style_and_print()` called.
 *
 * @return void
 */
function piecyfer_print_pending_styles() {
	print_late_styles();
}

/**
 * Front-end scripts.
 *
 * @return void
 */
function piecyfer_enqueue_scripts() {
	global $content_width;

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	wp_enqueue_script(
		'piecyfer-theme',
		PIECYFER_THEME_URI . 'assets/js/theme.js',
		array( 'jquery' ),
		piecyfer_asset_version( 'assets/js/theme.js' ),
		true
	);

	/*
	 * The object keeps the name `VAMTAM_FRONT`.
	 *
	 * It is not ours to rename: the companion plugin's front-end JavaScript
	 * reads `VAMTAM_FRONT.theme_supports`, `.widget_mods_list` and
	 * `.jspath`, and piecyfer-core inherits those readers. Renaming it is a
	 * separate change that has to land with its consumers, not with the theme.
	 *
	 * `jspath` must keep its trailing slash - theme.js appends a filename to it
	 * to load scroll-top.js.
	 */
	wp_localize_script(
		'piecyfer-theme',
		'VAMTAM_FRONT',
		array(
			'ajaxurl'           => admin_url( 'admin-ajax.php' ),
			'jspath'            => PIECYFER_THEME_URI . 'assets/js/',
			'max_breakpoint'    => piecyfer_breakpoint( 'lg' ),
			'medium_breakpoint' => piecyfer_breakpoint( 'md' ),
			'content_width'     => (int) $content_width,
			'widget_mods_list'  => piecyfer_widget_mods_list(),
		)
	);

	wp_add_inline_script(
		'piecyfer-theme',
		'VAMTAM_FRONT.theme_supports = (feature) => ' . wp_json_encode( PIECYFER_ELEMENTOR_FEATURES ) . '.includes(feature);'
	);

	wp_enqueue_script(
		'piecyfer-site',
		PIECYFER_THEME_URI . 'assets/js/site.js',
		array( 'jquery' ),
		piecyfer_asset_version( 'assets/js/site.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'piecyfer_enqueue_scripts' );

/**
 * Performance: Defer non-critical scripts to eliminate render-blocking JS.
 *
 * @param string $tag    HTML script tag.
 * @param string $handle Script handle.
 * @return string
 */
function piecyfer_defer_scripts( $tag, $handle ) {
	if ( is_admin() ) {
		return $tag;
	}

	$defer_handles = array(
		'piecyfer-theme',
		'piecyfer-site',
		'piecyfer-frontend',
		'piecyfer-handler-nav-menu',
		'piecyfer-handler-testimonial-carousel',
		'piecyfer-handler-search-form',
		'piecyfer-handler-gallery',
		'piecyfer-handler-form',
		'smartmenus',
	);

	if ( in_array( $handle, $defer_handles, true ) ) {
		if ( false === strpos( $tag, ' defer' ) && false === strpos( $tag, ' async' ) ) {
			return str_replace( ' src=', ' defer src=', $tag );
		}
	}

	return $tag;
}
add_filter( 'script_loader_tag', 'piecyfer_defer_scripts', 10, 2 );

