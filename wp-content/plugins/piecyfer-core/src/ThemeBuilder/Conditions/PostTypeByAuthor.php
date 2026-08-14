<?php
/**
 * "<Post type> By Author" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/post-type-by-author.php`.
 *
 * See ByAuthor for why the author comparison stays a string compare.
 */
class PostTypeByAuthor extends ConditionBase {

	private \WP_Post_Type $post_type;

	public function __construct( \WP_Post_Type $post_type ) {
		$this->post_type = $post_type;
	}

	public static function get_type(): string {
		return 'singular';
	}

	public static function get_priority(): int {
		return 40;
	}

	public function get_name(): string {
		return $this->post_type->name . '_by_author';
	}

	public function get_label(): string {
		/* translators: %s: Post type label. */
		return sprintf( esc_html__( '%s By Author', 'piecyfer-core' ), $this->post_type->label );
	}

	public function check( array $args ): bool {
		return is_singular( $this->post_type->name ) && get_post_field( 'post_author' ) === ( $args['id'] ?? '' );
	}
}
