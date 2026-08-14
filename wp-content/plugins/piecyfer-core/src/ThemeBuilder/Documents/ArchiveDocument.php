<?php
/**
 * The `archive` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/archive.php`. Live instances: templates 8559 and 6126.
 *
 * Archive documents do **not** touch the WordPress loop — the listing is
 * produced entirely by the `archive-posts` widget running its own WP_Query.
 * Until that widget is rebuilt (step 4j) an archive template renders its chrome
 * and an empty middle.
 */
class ArchiveDocument extends ArchiveSingleBase {

	public static function get_properties() {
		$properties = parent::get_properties();

		$properties['location']       = 'archive';
		$properties['condition_type'] = 'archive';

		return $properties;
	}

	public static function get_type() {
		return 'archive';
	}

	public static function get_title() {
		return esc_html__( 'Archive', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Archives', 'piecyfer-core' );
	}
}
