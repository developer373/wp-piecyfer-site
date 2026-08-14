<?php
/**
 * The `error-404` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/error-404.php`. Live instance: template 8716.
 *
 * It is a `single`-location document, so on a 404 it competes with the Blog
 * Post Template — and wins, because `include/singular/not_found404` resolves to
 * priority 5 against 8502's 30.
 */
class Error404Document extends SingleBase {

	public static function get_type() {
		return 'error-404';
	}

	public static function get_sub_type(): string {
		return 'not_found404';
	}

	public static function get_title() {
		return esc_html__( 'Error 404', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Error 404', 'piecyfer-core' );
	}
}
