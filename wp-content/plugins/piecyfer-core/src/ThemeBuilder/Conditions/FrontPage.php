<?php
/**
 * "Front Page" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/front-page.php`.
 */
class FrontPage extends ConditionBase {

	public static function get_type(): string {
		return 'singular';
	}

	public static function get_priority(): int {
		return 30;
	}

	public function get_name(): string {
		return 'front_page';
	}

	public function get_label(): string {
		return esc_html__( 'Front Page', 'piecyfer-core' );
	}

	public function check( array $args ): bool {
		return is_front_page();
	}
}
