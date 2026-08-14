<?php
/**
 * The `header` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/header.php`. Live instance: template 171.
 */
class HeaderDocument extends HeaderFooterBase {

	public static function get_properties() {
		$properties = parent::get_properties();

		$properties['location']        = 'header';
		$properties['support_lazyload'] = false;

		return $properties;
	}

	public static function get_type() {
		return 'header';
	}

	public static function get_title() {
		return esc_html__( 'Header', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Headers', 'piecyfer-core' );
	}
}
