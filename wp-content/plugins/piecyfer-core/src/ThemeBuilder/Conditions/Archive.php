<?php
/**
 * "All Archives" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

use PieCyfer\Core\ThemeBuilder\ConditionsManager;
use PieCyfer\Core\ThemeBuilder\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/archive.php`.
 *
 * `is_home()` is part of the test, which is why `/blogs/` would render archive
 * template 8559 if the theme ever stopped filtering `pre_option_page_for_posts`
 * to zero (04-THEME-BUILDER-SPEC.md §2.6). Do not "fix" that filter without
 * re-capturing the baseline.
 */
class Archive extends ConditionBase {

	protected array $sub_conditions = array(
		'author',
		'date',
		'search',
	);

	public static function get_type(): string {
		return 'archive';
	}

	public static function get_priority(): int {
		return 80;
	}

	public function get_name(): string {
		return 'archive';
	}

	public function get_label(): string {
		return esc_html__( 'Archives', 'piecyfer-core' );
	}

	public function get_all_label(): string {
		return esc_html__( 'All Archives', 'piecyfer-core' );
	}

	public function register_sub_conditions( ConditionsManager $manager ): void {
		foreach ( Module::get_public_post_types() as $post_type => $label ) {
			// Pro skips post types with no archive URL; `page` is filtered out
			// here, `post` survives because get_post_type_archive_link( 'post' )
			// always resolves to the blog index.
			if ( ! get_post_type_archive_link( $post_type ) ) {
				continue;
			}

			$this->register_sub_condition( $manager, new PostTypeArchive( $post_type ) );
		}
	}

	public function check( array $args ): bool {
		$is_archive = is_archive() || is_home() || is_search();

		// WooCommerce archives are Pro's `woocommerce` module's business. Woo is
		// not installed here; the branch is kept so the port stays a port.
		if ( $is_archive && class_exists( 'woocommerce' ) && function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			$is_archive = false;
		}

		return $is_archive;
	}
}
