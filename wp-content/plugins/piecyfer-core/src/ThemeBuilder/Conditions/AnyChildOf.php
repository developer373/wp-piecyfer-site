<?php
/**
 * "Any Child Of" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/any-child-of.php`.
 */
class AnyChildOf extends ChildOf {

	public function get_name(): string {
		return 'any_child_of';
	}

	public function get_label(): string {
		return esc_html__( 'Any Child Of', 'piecyfer-core' );
	}

	public function check( array $args ): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$id      = (int) ( $args['id'] ?? 0 );
		$parents = get_post_ancestors( get_the_ID() );

		// Pro compares loosely here (`in_array` without strict); get_post_ancestors()
		// returns ints, so a strict compare is equivalent and safer.
		return ( 0 === $id && ! empty( $parents ) ) || in_array( $id, array_map( 'intval', $parents ), true );
	}
}
