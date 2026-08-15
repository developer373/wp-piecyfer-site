<?php
/**
 * Filters carried from the previous theme that shape the request rather than
 * the markup.
 *
 * Both of these look like trivia and neither is. The first is inert in
 * production and load-bearing locally; the second decides which template the
 * blog listing renders.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * VamTam compatibility constants while companion plugin is transitionally active.
 */
if ( ! defined( 'VAMTAM_THEME_NAME' ) ) {
	define( 'VAMTAM_THEME_NAME', 'Tecnologia' );
}
if ( ! defined( 'VAMTAM_THEME_SLUG' ) ) {
	define( 'VAMTAM_THEME_SLUG', 'tecnologia' );
}
if ( ! defined( 'VAMTAM_THEME_DIR' ) ) {
	define( 'VAMTAM_THEME_DIR', PIECYFER_THEME_DIR );
}
if ( ! defined( 'VAMTAM_THEME_URI' ) ) {
	define( 'VAMTAM_THEME_URI', PIECYFER_THEME_URI );
}
if ( ! defined( 'VAMTAM_DIR' ) ) {
	define( 'VAMTAM_DIR', PIECYFER_THEME_DIR );
}
if ( ! defined( 'VAMTAM_URI' ) ) {
	define( 'VAMTAM_URI', PIECYFER_THEME_URI );
}
if ( ! defined( 'VAMTAM_ASSETS_DIR' ) ) {
	define( 'VAMTAM_ASSETS_DIR', PIECYFER_THEME_DIR . 'assets/' );
}
if ( ! defined( 'VAMTAM_ASSETS_URI' ) ) {
	define( 'VAMTAM_ASSETS_URI', PIECYFER_THEME_URI . 'assets/' );
}
if ( ! defined( 'VAMTAM_CSS' ) ) {
	define( 'VAMTAM_CSS', PIECYFER_THEME_URI . 'assets/css/' );
}
if ( ! defined( 'VAMTAM_CSS_DIR' ) ) {
	define( 'VAMTAM_CSS_DIR', PIECYFER_THEME_DIR . 'assets/css/' );
}

if ( ! class_exists( 'VamtamElementorBridge' ) ) {
	class VamtamElementorBridge {
		public static function get_widget_mods_list() {
			if ( function_exists( 'piecyfer_widget_mods_list' ) ) {
				return piecyfer_widget_mods_list();
			}
			return array();
		}

		public static function get_wc_mods_list() {
			return array();
		}

		public static function get_instance() {
			static $instance = null;
			return $instance ??= new self();
		}
	}
}




/**
 * Force `page_for_posts` to zero.
 *
 * THIS IS NOT OPTIONAL AND IT IS NOT COSMETIC. Carried from
 * `tecnologia/vamtam/classes/overrides.php:22`, which registered exactly this,
 * unconditionally, on every request including wp-admin.
 *
 * `wp_options.page_for_posts` is `93` - the same post as the Elementor page
 * "Blogs" at `/blogs/`. With the filter absent WordPress treats that URL as the
 * posts page, and three things change at once:
 *
 *   1. `/blogs/` becomes `is_home()`, so Elementor's `archive` location claims
 *      it and it renders Theme Builder document 8559 instead of page 93's own
 *      Elementor content. Measured: `data-elementor-type` flips from `wp-page`
 *      to `archive` and `.page-wrapper` disappears.
 *   2. The body classes change from `wp-singular page page-id-93
 *      page-template-default` to `blog`.
 *   3. `_wp_menu_item_classes_by_context()` adds `current_page_parent` to the
 *      "Blogs" item of all three rendered menus on every non-page request -
 *      author archives, category archives, single posts, search and the 404.
 *
 * `piecyfer-core` already documents the dependency in
 * `src/ThemeBuilder/Conditions/Archive.php:19-23`: "`/blogs/` would render
 * archive template 8559 if the theme ever stopped filtering
 * `pre_option_page_for_posts` to zero. Do not 'fix' that filter without
 * re-capturing the baseline."
 *
 * The side effect is that Settings -> Reading cannot show or set a posts page.
 * That was true of the previous theme too, and narrowing the filter to the
 * front end would change how Theme Builder conditions evaluate in the editor,
 * so the scope is left exactly as it was.
 */
add_filter( 'pre_option_page_for_posts', '__return_zero' );

/**
 * Prefix one-page menu anchors with the install's subdirectory.
 *
 * Stock VamTam code, not a site customisation, and a no-op in production: it is
 * only registered when `parse_url( home_url(), PHP_URL_PATH )` is non-null,
 * which is true for the local install at `/piecyfer` and false for
 * `https://piecyfer.com/`. It is carried so that local and production render
 * identically, which is the whole basis of the pixel harness - not because the
 * live site needs it.
 *
 * @param array    $atts The `<a>` attributes.
 * @param WP_Post  $item The menu item.
 * @param stdClass $args The menu arguments.
 * @return array
 */
function piecyfer_onepage_menu_hrefs( $atts, $item, $args ) {
	if ( ! isset( $atts['href'] ) || ! isset( $item->type ) ) {
		return $atts;
	}

	if ( 'custom' === $item->type && 0 === strpos( $atts['href'], '/#' ) ) {
		$atts['href'] = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) ) . $atts['href'];
	}

	return $atts;
}

if ( null !== wp_parse_url( home_url(), PHP_URL_PATH ) ) {
	add_filter( 'nav_menu_link_attributes', 'piecyfer_onepage_menu_hrefs', 10, 3 );
}
