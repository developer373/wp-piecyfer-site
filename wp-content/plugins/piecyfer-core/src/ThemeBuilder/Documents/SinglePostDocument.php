<?php
/**
 * The `single-post` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/single-post.php`. Live instance: template 8502,
 * "Blog Post Template", which routes on `include/singular/post` at priority 30.
 */
class SinglePostDocument extends SingleBase {

	public static function get_type() {
		return 'single-post';
	}

	public static function get_title() {
		return esc_html__( 'Single Post', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Single Posts', 'piecyfer-core' );
	}
}
