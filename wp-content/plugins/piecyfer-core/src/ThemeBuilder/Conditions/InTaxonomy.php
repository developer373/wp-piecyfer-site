<?php
/**
 * "In <Taxonomy>" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/in-taxonomy.php`.
 *
 * Template 8502 carries a bare `include/singular/in_category` with no term id,
 * so this runs `has_term( 0, 'category' )`. The spec (§2.4, §6.6) records that
 * as inert-in-practice but *untested* against WP core's is_object_in_term().
 * The code is copied rather than "cleaned up" precisely so that whatever
 * WordPress does with a 0 term id, we do the same thing Pro did.
 */
class InTaxonomy extends ConditionBase {

	protected \WP_Taxonomy $taxonomy;

	public function __construct( \WP_Taxonomy $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	public static function get_type(): string {
		return 'singular';
	}

	public static function get_priority(): int {
		return 40;
	}

	public function get_name(): string {
		return 'in_' . $this->taxonomy->name;
	}

	public function get_label(): string {
		/* translators: %s: Taxonomy label. */
		return sprintf( esc_html__( 'In %s', 'piecyfer-core' ), $this->taxonomy->labels->singular_name );
	}

	public function check( array $args ): bool {
		return is_singular() && has_term( (int) ( $args['id'] ?? 0 ), $this->taxonomy->name );
	}
}
