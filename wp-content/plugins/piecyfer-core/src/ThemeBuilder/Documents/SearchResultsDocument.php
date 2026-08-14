<?php
/**
 * The `search-results` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/search-results.php`. Live instance: template 8711.
 *
 * It extends the archive document, so it renders at the `archive` location and
 * its wrapper carries `elementor-location-archive` even though its type is
 * `search-results`.
 */
class SearchResultsDocument extends ArchiveDocument {

	public static function get_type() {
		return 'search-results';
	}

	public static function get_sub_type(): string {
		return 'search';
	}

	public static function get_title() {
		return esc_html__( 'Search Results', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Search Results', 'piecyfer-core' );
	}
}
