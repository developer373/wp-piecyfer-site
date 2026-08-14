<?php
/**
 * "Direct Child Of" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/child-of.php`.
 *
 * Used live by footer 991509 (`include/singular/child_of/1298`). Page 1298 is a
 * draft with no children today, so the condition matches nothing — but it is
 * the only condition on this site that would start matching the moment content
 * changes, so it must be right rather than merely inert.
 */
class ChildOf extends ConditionBase {

	public static function get_type(): string {
		return 'singular';
	}

	public static function get_priority(): int {
		return 40;
	}

	public function get_name(): string {
		return 'child_of';
	}

	public function get_label(): string {
		return esc_html__( 'Direct Child Of', 'piecyfer-core' );
	}

	public function check( array $args ): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$id        = (int) ( $args['id'] ?? 0 );
		$parent_id = wp_get_post_parent_id( get_the_ID() );

		// id 0 means "child of anything".
		return ( 0 === $id && 0 < $parent_id ) || ( $parent_id === $id );
	}
}
