<?php
/**
 * "Date Archive" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/date.php`.
 *
 * No template on this site uses it, but `/2026/` is reachable on a stock
 * WordPress install and today resolves through `include/archive` to 8559. The
 * class exists so that the day someone adds a date condition it behaves the way
 * Pro did, rather than being silently unregistered and skipped.
 */
class Date extends ConditionBase {

	public static function get_type(): string {
		return 'archive';
	}

	public static function get_priority(): int {
		return 70;
	}

	public function get_name(): string {
		return 'date';
	}

	public function get_label(): string {
		return esc_html__( 'Date Archive', 'piecyfer-core' );
	}

	public function check( array $args ): bool {
		return is_date();
	}
}
