<?php
/**
 * Theme supports and content width.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * The feature flags the companion Elementor plugin gates its behaviour on.
 *
 * This is the list the previous theme published as
 * `add_theme_support( 'vamtam-elementor-widgets' )` plus a
 * `current_theme_supports-vamtam-elementor-widgets` filter
 * (tecnologia/vamtam/classes/framework.php:255-303), and it is also exported to
 * the browser as `VAMTAM_FRONT.theme_supports` (framework.php:329-331), where
 * the plugin's own JavaScript reads it.
 *
 * It is reproduced verbatim, in the same order, because both consumers do an
 * exact membership test: a missing flag silently turns a widget behaviour off
 * with no error anywhere.
 *
 * @var string[]
 */
const PIECYFER_ELEMENTOR_FEATURES = array(
	// Carried over from earlier VamTam themes; these stay enabled across themes.
	'archive-posts.classic--box-section',
	'section--vamtam-sticky-header-controls',
	'posts-base--extra-pagination-controls',
	'column--logical-spacings',
	// Theme-specific.
	'posts-base--load-more-masonry-fix',
	'posts-base--display-categories',
	'posts-base--display-tags',
	'popup--absolute-position',
	'posts-base--horizontal-layout',
	'button--underline-anim',
	'button--icon-size-control',
	'popup--open-on-selector-hover',
	'posts-base--404-handling-fix',
	'nav-menu--disable-scroll-on-mobile',
	'nav-menu--custom-sub-indicators',
	'posts-base--responsive-image-position',
	'testimonial-carousel--custom-nav-arrows-controls',
	'nav-menu--toggle-sticky-hover-state-on-touch-fix',
);

/**
 * Register theme supports.
 *
 * @return void
 */
function piecyfer_setup() {
	load_theme_textdomain( 'piecyfer-theme', PIECYFER_THEME_DIR . 'languages' );

	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption' ) );
	add_theme_support( 'title-tag' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'customize-selective-refresh-widgets' );

	add_post_type_support( 'page', 'excerpt' );

	/*
	 * Markup parity only.
	 *
	 * WooCommerce is not installed, so this support flag changes no behaviour.
	 * It is declared because `vamtam_body_classes()` turned it into the
	 * `wc-product-gallery-slider-active` body class, which is on all 39
	 * captured pages. Dropping it is a markup diff on every page for no gain;
	 * drop it in the same later commit as the rest of the dead body classes.
	 */
	add_theme_support( 'wc-product-gallery-slider' );

	/*
	 * The feature-flag channel the companion plugin reads.
	 *
	 * Declared even though piecyfer-core is taking that plugin's work over,
	 * because the transition runs both for a while and the flags have to keep
	 * answering the same way while it does.
	 */
	add_theme_support( 'vamtam-elementor-widgets' );

	/*
	 * No register_nav_menus() and no register_sidebar().
	 *
	 * The single `primary-menu` location the previous theme registered had
	 * nothing assigned to it (`theme_mods_tecnologia['nav_menu_locations']` is
	 * an empty array) and its only consumer was a template Elementor's Theme
	 * Builder replaces. Every live menu is selected by id inside an Elementor
	 * or ElementsKit widget.
	 *
	 * No sidebar was ever registered on the front end either, and all five
	 * block widgets sit in `wp_inactive_widgets`. The previous theme *did*
	 * register sidebars in wp-admin only - a quirk of probing for Elementor Pro
	 * differently there - which put sidebars in Appearance -> Widgets that
	 * could never render. Not reproduced.
	 */
}
add_action( 'after_setup_theme', 'piecyfer_setup' );

/**
 * Answer the companion plugin's per-feature capability probe.
 *
 * `current_theme_supports( 'vamtam-elementor-widgets', $feature )` is called
 * with one flag at a time. WordPress passes an empty `$args` for the bare
 * check, which must keep returning the plain support value.
 *
 * @param bool   $supports Whether the theme supports the feature.
 * @param array  $args     Optional arguments passed to the check.
 * @param string $feature  The feature being checked.
 * @return bool
 */
function piecyfer_elementor_widgets_support( $supports, $args, $feature ) {
	if ( empty( $args ) ) {
		return $supports;
	}

	return in_array( $args[0], PIECYFER_ELEMENTOR_FEATURES, true );
}
add_filter( 'current_theme_supports-vamtam-elementor-widgets', 'piecyfer_elementor_widgets_support', 10, 3 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

/**
 * Set `$content_width` from the active Elementor kit.
 *
 * Elementor's `container_width` is the real content width on this site
 * (1280px). WordPress uses `$content_width` for oEmbed and large-image sizing,
 * and the previous theme also published it to the browser as
 * `VAMTAM_FRONT.content_width`.
 *
 * Read straight from `_elementor_page_settings` rather than through Elementor's
 * kits manager: this runs on `after_setup_theme`, which is early, and a
 * postmeta read has no load-order requirements.
 *
 * @return void
 */
function piecyfer_content_width() {
	global $content_width;

	if ( ! isset( $content_width ) ) {
		$content_width = 1360; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	$kit_id = (int) get_option( 'elementor_active_kit' );

	if ( ! $kit_id ) {
		return;
	}

	$kit = get_post_meta( $kit_id, '_elementor_page_settings', true );

	if ( ! empty( $kit['container_width']['size'] ) ) {
		$content_width = (int) $kit['container_width']['size']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}
add_action( 'after_setup_theme', 'piecyfer_content_width', 0 );
