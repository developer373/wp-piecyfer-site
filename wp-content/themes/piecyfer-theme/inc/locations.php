<?php
/**
 * Elementor Theme Builder locations.
 *
 * Without this file the Theme Builder header and footer stop rendering: Pro
 * only serves a location the active theme has registered.
 *
 * Two things are load-bearing here.
 *
 * 1. `register_all_core_location()` registers header, footer, single and
 *    archive. Because all four are registered by the theme, Pro's
 *    `Theme_Support::after_register_locations()` finds them present and never
 *    installs its own get_header/get_footer hijack - which is why this theme's
 *    header.php and footer.php are in control of the wrapper markup.
 *
 * 2. `page-title-location` is a VamTam addition
 *    (tecnologia/vamtam/classes/elementor-bridge.php:715-726), consumed by the
 *    `#sub-header` part. It renders empty on every captured page, but it is
 *    registered so the `elementor_theme_do_location()` call inside the part
 *    keeps returning true and the theme's own fallback page header stays
 *    suppressed. Drop the registration and every page grows an `<h1>` it does
 *    not have today.
 *
 * The `$manager` parameter is deliberately untyped in both Pro and here, so
 * piecyfer-core's replacement locations manager can be passed in unchanged.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Theme Builder locations this site uses.
 *
 * @param object $manager Elementor theme locations manager.
 * @return void
 */
function piecyfer_register_elementor_locations( $manager ) {
	if ( ! is_object( $manager ) || ! method_exists( $manager, 'register_all_core_location' ) ) {
		return;
	}

	$manager->register_all_core_location();

	$manager->register_location(
		'page-title-location',
		array(
			'label'           => esc_html__( 'Page Title', 'piecyfer-theme' ),
			'multiple'        => true,
			'edit_in_content' => true,
		)
	);
}
add_action( 'elementor/theme/register_locations', 'piecyfer_register_elementor_locations' );

/**
 * Render a Theme Builder location, or fall back.
 *
 * Wraps the `function_exists()` dance that every template would otherwise
 * repeat, so there is exactly one place that knows what to do when neither
 * Elementor Pro nor piecyfer-core is providing locations.
 *
 * @param string $location Location name.
 * @return bool True when a Theme Builder document rendered.
 */
function piecyfer_do_location( $location ) {
	if ( ! function_exists( 'elementor_theme_do_location' ) ) {
		return false;
	}

	return (bool) elementor_theme_do_location( $location );
}

/**
 * Whether a location is registered at all.
 *
 * Note the deliberate `$check_match = false`: this asks "is the location
 * registered?", not "does a template match this request?". The previous theme's
 * footer.php did the same, which is why the `.footer-wrapper` and
 * `<footer id="main-footer">` elements are emitted even on a page whose footer
 * document does not match.
 *
 * @param string $location Location name.
 * @return bool
 */
function piecyfer_location_exists( $location ) {
	return function_exists( 'elementor_location_exits' ) && elementor_location_exits( $location );
}
