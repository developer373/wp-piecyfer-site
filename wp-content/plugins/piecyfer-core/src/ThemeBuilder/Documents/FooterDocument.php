<?php
/**
 * The `footer` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/footer.php`. Live instances: templates 1273 and 991509.
 */
class FooterDocument extends HeaderFooterBase {

	public static function get_properties() {
		$properties = parent::get_properties();

		$properties['location'] = 'footer';

		return $properties;
	}

	public static function get_type() {
		return 'footer';
	}

	public static function get_title() {
		return esc_html__( 'Footer', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Footers', 'piecyfer-core' );
	}
}
