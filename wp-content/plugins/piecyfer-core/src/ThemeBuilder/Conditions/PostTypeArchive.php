<?php
/**
 * "<Post type> Archive" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

use PieCyfer\Core\ThemeBuilder\ConditionsManager;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/post-type-archive.php`.
 *
 * Registering this is what brings the per-taxonomy conditions (`category`,
 * `post_tag`, `child_of_category`, `any_child_of_category`) into existence —
 * they are its children, not top-level conditions.
 */
class PostTypeArchive extends ConditionBase {

	private \WP_Post_Type $post_type;

	/**
	 * @var \WP_Taxonomy[]
	 */
	private array $post_taxonomies;

	public function __construct( string $post_type ) {
		$this->post_type = get_post_type_object( $post_type );

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		$this->post_taxonomies = wp_filter_object_list(
			$taxonomies,
			array(
				'public'            => true,
				'show_in_nav_menus' => true,
			)
		);
	}

	public static function get_type(): string {
		return 'archive';
	}

	public static function get_priority(): int {
		return 70;
	}

	public function get_name(): string {
		return $this->post_type->name . '_archive';
	}

	public function get_label(): string {
		/* translators: %s: Post type label. */
		return sprintf( esc_html__( '%s Archive', 'piecyfer-core' ), $this->post_type->label );
	}

	public function get_all_label(): string {
		return $this->get_label();
	}

	public function register_sub_conditions( ConditionsManager $manager ): void {
		foreach ( $this->post_taxonomies as $object ) {
			$this->register_sub_condition( $manager, new Taxonomy( $object ) );

			if ( ! $object->hierarchical ) {
				continue;
			}

			$this->register_sub_condition( $manager, new ChildOfTerm( $object ) );
			$this->register_sub_condition( $manager, new AnyChildOfTerm( $object ) );
		}
	}

	public function check( array $args ): bool {
		return is_post_type_archive( $this->post_type->name )
			|| ( 'post' === $this->post_type->name && is_home() );
	}
}
