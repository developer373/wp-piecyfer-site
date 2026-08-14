<?php
/**
 * Term-archive condition, one instance per public taxonomy.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/taxonomy.php`.
 *
 * Its name is the taxonomy slug — `category`, `post_tag` — which is what makes
 * template 8559's `exclude/archive/post_tag/46` resolvable. That particular
 * condition can never fire because term 46 is a `nav_menu` term, not a
 * `post_tag` (04-THEME-BUILDER-SPEC.md §2.4). Registering the class anyway keeps
 * the "not registered" and "registered but false" paths from being confused.
 */
class Taxonomy extends ConditionBase {

	protected \WP_Taxonomy $taxonomy;

	public function __construct( \WP_Taxonomy $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	public static function get_type(): string {
		return 'archive';
	}

	public static function get_priority(): int {
		return 70;
	}

	public function get_name(): string {
		return $this->taxonomy->name;
	}

	public function get_label(): string {
		return (string) $this->taxonomy->label;
	}

	public function check( array $args ): bool {
		$taxonomy = $this->taxonomy->name;
		$id       = (int) ( $args['id'] ?? 0 );

		if ( 'category' === $taxonomy ) {
			return is_category( $id );
		}

		if ( 'post_tag' === $taxonomy ) {
			return is_tag( $id );
		}

		return is_tax( $taxonomy, $id );
	}

	/**
	 * Exposed so the Child_Of_Term subclasses can read it without re-declaring
	 * their own copy. Pro re-declares a private `$taxonomy` in each subclass,
	 * which works only because each constructor sets it again.
	 */
	protected function taxonomy(): \WP_Taxonomy {
		return $this->taxonomy;
	}
}
