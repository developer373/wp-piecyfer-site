<?php
/**
 * "By Author" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/by-author.php`.
 *
 * Pro compares `get_post_field( 'post_author' )` (a numeric *string*) against
 * `$args['id']` (also a string, straight off the condition) with `===`. That
 * only ever matches because both sides are strings. Casting either side to int
 * here would look tidier and would still work, but string-vs-string is what Pro
 * does and the point of this file is parity, not tidiness.
 */
class ByAuthor extends ConditionBase {

	public static function get_type(): string {
		return 'singular';
	}

	public static function get_priority(): int {
		return 40;
	}

	public function get_name(): string {
		return 'by_author';
	}

	public function get_label(): string {
		return esc_html__( 'By Author', 'piecyfer-core' );
	}

	public function check( array $args ): bool {
		return is_singular() && get_post_field( 'post_author' ) === ( $args['id'] ?? '' );
	}
}
