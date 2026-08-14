<?php
/**
 * Per-post-type singular condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

use PieCyfer\Core\ThemeBuilder\ConditionsManager;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/post.php` (Pro calls the class `Post`; renamed here so it
 * is never mistaken for `WP_Post` or for Elementor's `Post` document type).
 *
 * Two live conditions depend on this class:
 *   `include/singular/post`      → priority 30, the Blog Post Template (8502)
 *   `include/singular/page/1648` → priority 20, the Privacy Policy footer
 *
 * The 30 comes from having sub-conditions (`in_category`, `in_category_children`,
 * `post_by_author`) — an empty sub_conditions list would earn a -5 bonus and
 * make it 25, which would change the footer/single race. register_sub_conditions()
 * running before any priority is computed is therefore load-bearing.
 */
class PostType extends ConditionBase {

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
		return 'singular';
	}

	public static function get_priority(): int {
		return 40;
	}

	public function get_name(): string {
		return $this->post_type->name;
	}

	public function get_label(): string {
		return (string) $this->post_type->labels->singular_name;
	}

	public function get_all_label(): string {
		return (string) $this->post_type->label;
	}

	public function check( array $args ): bool {
		if ( isset( $args['id'] ) ) {
			$id = (int) $args['id'];
			if ( $id ) {
				return is_singular() && get_queried_object_id() === $id;
			}
		}

		return is_singular( $this->post_type->name );
	}

	public function register_sub_conditions( ConditionsManager $manager ): void {
		foreach ( $this->post_taxonomies as $object ) {
			$this->register_sub_condition( $manager, new InTaxonomy( $object ) );

			if ( $object->hierarchical ) {
				$this->register_sub_condition( $manager, new InSubTerm( $object ) );
			}
		}

		$this->register_sub_condition( $manager, new PostTypeByAuthor( $this->post_type ) );
	}
}
