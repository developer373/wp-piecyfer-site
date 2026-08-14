<?php
/**
 * "404 Page" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/not-found404.php`.
 *
 * Priority 20 base, no sub-conditions, so `include/singular/not_found404`
 * resolves to 5 — the most specific condition on the site. That is what lets
 * the 404 template (8716) beat the Blog Post Template (8502, priority 30) on
 * the `single` location for a 404 request.
 */
class NotFound404 extends ConditionBase {

	public static function get_type(): string {
		return 'singular';
	}

	public static function get_priority(): int {
		return 20;
	}

	public function get_name(): string {
		return 'not_found404';
	}

	public function get_label(): string {
		return esc_html__( '404 Page', 'piecyfer-core' );
	}

	public function check( array $args ): bool {
		return is_404();
	}
}
