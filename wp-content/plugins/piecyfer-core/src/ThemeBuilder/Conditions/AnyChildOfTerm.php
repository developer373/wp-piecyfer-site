<?php
/**
 * "Any Child <Taxonomy> Of" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/any-child-of-term.php`.
 *
 * Template 6126 ("Case Studies Archives") routes solely on
 * `include/archive/any_child_of_category/22`, and term 22 does not exist, so
 * 6126 renders on no URL at all. That is the *current* behaviour and the
 * replacement reproduces it — see 04-THEME-BUILDER-SPEC.md §2.4 before
 * "repairing" anything here.
 */
class AnyChildOfTerm extends ChildOfTerm {

	public function get_name(): string {
		return 'any_child_of_' . $this->taxonomy()->name;
	}

	public function get_label(): string {
		/* translators: %s: Singular taxonomy label. */
		return sprintf( esc_html__( 'Any Child %s Of', 'piecyfer-core' ), $this->taxonomy()->labels->singular_name );
	}

	public function check( array $args ): bool {
		$id = (int) ( $args['id'] ?? 0 );

		/** @var \WP_Term|mixed $current */
		$current = get_queried_object();

		if ( ! $this->is_term() || 0 === $current->parent ) {
			return false;
		}

		while ( $current->parent > 0 ) {
			if ( $id === $current->parent ) {
				return true;
			}
			$current = get_term_by( 'id', $current->parent, $current->taxonomy );

			// Pro has no guard here; a broken term hierarchy would fatal on
			// ->parent. Bail instead — a false is the same routing outcome.
			if ( ! $current instanceof \WP_Term ) {
				return false;
			}
		}

		return $id === $current->parent;
	}
}
