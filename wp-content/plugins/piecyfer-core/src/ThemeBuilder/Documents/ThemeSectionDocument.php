<?php
/**
 * Base for documents that are a fragment of a page rather than a page.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/theme-section-document.php`.
 *
 * Header, footer and `section` documents descend from here. The practical
 * difference from ThemePageDocument is that these add **no** body class and
 * their CSS is scoped to the wrapper rather than to the body.
 */
abstract class ThemeSectionDocument extends ThemeDocument {

	public static function get_properties() {
		$properties = parent::get_properties();

		$properties['condition_type'] = 'general';

		return $properties;
	}
}
