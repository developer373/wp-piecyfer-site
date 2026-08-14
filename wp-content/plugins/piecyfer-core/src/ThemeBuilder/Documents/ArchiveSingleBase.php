<?php
/**
 * Shared base for archive-like and single-like documents.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `documents/archive-single-base.php`.
 *
 * Pro also uses this layer to write a default display condition when a template
 * is created from the "Add New Theme Template" dialog. That dialog is Pro admin
 * JS which is not being reproduced, so only the sub-type accessor survives.
 */
abstract class ArchiveSingleBase extends ThemePageDocument {

	public static function get_sub_type(): string {
		return '';
	}
}
