<?php
/**
 * "All Singular" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

use PieCyfer\Core\ThemeBuilder\ConditionsManager;
use PieCyfer\Core\ThemeBuilder\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/singular.php`.
 *
 * `is_404()` counts as singular — that is how the 404 template (8716) reaches
 * the `single` location.
 */
class Singular extends ConditionBase {

	protected array $sub_conditions = array(
		'front_page',
	);

	public static function get_type(): string {
		return 'singular';
	}

	public static function get_priority(): int {
		return 60;
	}

	public function get_name(): string {
		return 'singular';
	}

	public function get_label(): string {
		return esc_html__( 'Singular', 'piecyfer-core' );
	}

	public function get_all_label(): string {
		return esc_html__( 'All Singular', 'piecyfer-core' );
	}

	public function register_sub_conditions( ConditionsManager $manager ): void {
		$post_types = Module::get_public_post_types();

		$attachment = get_post_type_object( 'attachment' );
		if ( $attachment ) {
			$post_types['attachment'] = $attachment->label;
		}

		foreach ( $post_types as $post_type => $label ) {
			$this->register_sub_condition( $manager, new PostType( $post_type ) );
		}

		/*
		 * These four are registered by the manager, not here, so only their
		 * names are appended — exactly as Pro does. The order matters for the
		 * editor's dropdown only; routing looks conditions up by name.
		 */
		$this->sub_conditions[] = 'child_of';
		$this->sub_conditions[] = 'any_child_of';
		$this->sub_conditions[] = 'by_author';
		$this->sub_conditions[] = 'not_found404';
	}

	public function check( array $args ): bool {
		return ( is_singular() && ! is_embed() ) || is_404();
	}
}
