<?php
/**
 * The `[elementor-template]` shortcode.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/library/classes/shortcode.php:51-72`.
 *
 * Strictly speaking this belongs to Pro's Library module rather than its Theme
 * Builder, but it ships here because the single-post template depends on it:
 * template 8502's toggle widget contains
 *
 *     "tab_content":"<p>[elementor-template id=\"8519\"]<\/p>"
 *
 * so without this class every blog post would print that string literally.
 * Template 8519 is a *draft* `section` document, which is why it reaches the
 * page through a shortcode and not through a display condition.
 */
final class Shortcode {

	public const SHORTCODE = 'elementor-template';

	/**
	 * Register the shortcode, unless something already owns the tag.
	 *
	 * The guard is what makes this safe to load while Elementor Pro is still
	 * active: `add_shortcode()` silently replaces an existing handler, so
	 * without it we would take the tag over invisibly rather than deliberately.
	 */
	public static function register(): void {
		if ( shortcode_exists( self::SHORTCODE ) ) {
			return;
		}

		add_shortcode( self::SHORTCODE, array( self::class, 'render' ) );
	}

	/**
	 * @param array<string,string>|string $attributes
	 */
	public static function render( $attributes = array() ): string {
		if ( empty( $attributes['id'] ) ) {
			return '';
		}

		$include_css = false;

		// Pro's own logic, oddity included: any `css` attribute other than the
		// literal string "false" is cast to bool, so css="0" disables it but
		// css="no" enables it. Copied rather than corrected.
		if ( isset( $attributes['css'] ) && 'false' !== $attributes['css'] ) {
			$include_css = (bool) $attributes['css'];
		}

		return \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( (int) $attributes['id'], $include_css );
	}
}
