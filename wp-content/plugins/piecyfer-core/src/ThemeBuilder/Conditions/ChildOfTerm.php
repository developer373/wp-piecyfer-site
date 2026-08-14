<?php
/**
 * "Direct Child <Taxonomy> Of" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/child-of-term.php`.
 */
class ChildOfTerm extends Taxonomy {

	public function get_name(): string {
		return 'child_of_' . $this->taxonomy()->name;
	}

	public function get_label(): string {
		/* translators: %s: Singular taxonomy label. */
		return sprintf( esc_html__( 'Direct Child %s Of', 'piecyfer-core' ), $this->taxonomy()->labels->singular_name );
	}

	/**
	 * Whether the queried object is a term of this taxonomy.
	 */
	public function is_term(): bool {
		$current = get_queried_object();

		return $current && isset( $current->taxonomy ) && $this->taxonomy()->name === $current->taxonomy;
	}

	public function check( array $args ): bool {
		$id      = (int) ( $args['id'] ?? 0 );
		$current = get_queried_object();

		return $this->is_term() && $id === $current->parent;
	}
}
