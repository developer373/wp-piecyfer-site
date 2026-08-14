<?php
/**
 * "Author Archive" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/author.php`.
 */
class Author extends ConditionBase {

	public static function get_type(): string {
		return 'archive';
	}

	public static function get_priority(): int {
		return 70;
	}

	public function get_name(): string {
		return 'author';
	}

	public function get_label(): string {
		return esc_html__( 'Author Archive', 'piecyfer-core' );
	}

	public function check( array $args ): bool {
		// An empty sub_id means "any author"; is_author( '' ) is is_author().
		return is_author( $args['id'] ?? '' );
	}
}
