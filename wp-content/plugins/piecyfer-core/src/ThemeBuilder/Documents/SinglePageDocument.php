<?php
/**
 * The `single-page` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/single-page.php`. Unused on this site; registered so the
 * type never falls back to the `post` document class.
 */
class SinglePageDocument extends SingleBase {

	public static function get_type() {
		return 'single-page';
	}

	public static function get_sub_type(): string {
		return 'page';
	}

	public static function get_title() {
		return esc_html__( 'Single Page', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Single Pages', 'piecyfer-core' );
	}
}
