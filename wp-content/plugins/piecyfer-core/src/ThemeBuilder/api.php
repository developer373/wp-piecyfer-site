<?php
/**
 * The two global functions the theme calls.
 *
 * Mirrors `elementor-pro/modules/theme-builder/api.php:9-21`.
 *
 * DO NOT require this file directly. Module::boot() loads it, and only after it
 * has made certain that `ElementorPro\Modules\ThemeBuilder\Module` resolves to
 * something — because the theme dereferences that class the instant
 * `elementor_theme_do_location()` starts existing. Requiring this file on its
 * own, with Pro deleted and the compatibility shim not loaded, produces a fatal
 * on every page of the site.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'elementor_theme_do_location' ) ) {
	/**
	 * Print a theme location. Returns false when nothing matched, which several
	 * theme templates use to decide whether to fall back to their own markup.
	 *
	 * @param string $location
	 * @return bool
	 */
	function elementor_theme_do_location( $location ) {
		return \PieCyfer\Core\ThemeBuilder\Module::instance()->get_locations_manager()->do_location( $location );
	}
}

if ( ! function_exists( 'elementor_location_exits' ) ) {
	/**
	 * Whether a location is registered — and, only if asked, whether a template
	 * actually matches it.
	 *
	 * The misspelling is Elementor's and is part of the contract:
	 * `themes/tecnologia/footer.php:18` calls it by this exact name, with the
	 * default `$check_match = false`, and has no else branch. If this ever
	 * starts returning false for `footer`, the site renders with no footer at
	 * all and no error anywhere.
	 *
	 * @param string $location
	 * @param bool   $check_match
	 * @return bool
	 */
	function elementor_location_exits( $location, $check_match = false ) {
		return \PieCyfer\Core\ThemeBuilder\Module::instance()->get_locations_manager()->location_exits( $location, $check_match );
	}
}
