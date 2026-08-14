<?php
/**
 * The legacy multipurpose `single` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/single.php`.
 *
 * No template on this site uses the bare `single` type — 8502 is `single-post`
 * and 8716 is `error-404` — but it is registered because an unregistered type
 * silently degrades to the `post` document class and its CSS selector, and
 * because Elementor's importer will happily create one.
 */
class SingleDocument extends SingleBase {

	public static function get_type() {
		return 'single';
	}

	public static function get_title() {
		return esc_html__( 'Single', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Singles', 'piecyfer-core' );
	}
}
