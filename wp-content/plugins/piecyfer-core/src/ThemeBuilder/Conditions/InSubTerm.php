<?php
/**
 * "In Child <Taxonomy>" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/in-sub-term.php`.
 *
 * Returns false immediately without a term id, which is why template 8502's
 * `include/singular/in_category_children` provably never fires.
 */
class InSubTerm extends InTaxonomy {

	public function get_name(): string {
		return 'in_' . $this->taxonomy->name . '_children';
	}

	public function get_label(): string {
		/* translators: %s: Taxonomy label. */
		return sprintf( esc_html__( 'In Child %s', 'piecyfer-core' ), $this->taxonomy->labels->name );
	}

	public function check( array $args ): bool {
		$id = (int) ( $args['id'] ?? 0 );

		if ( ! is_singular() || ! $id ) {
			return false;
		}

		$child_terms = get_term_children( $id, $this->taxonomy->name );

		return ! empty( $child_terms ) && has_term( $child_terms, $this->taxonomy->name );
	}
}
