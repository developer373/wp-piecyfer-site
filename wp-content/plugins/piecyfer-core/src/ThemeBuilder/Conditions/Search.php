<?php
/**
 * "Search Results" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/search.php`.
 *
 * Has no sub-conditions, so `include/archive/search` earns the -5 bonus and
 * lands on priority 55 — beating the plain `include/archive` (80) that template
 * 8559 carries. That single number is what puts the Search Results template on
 * `/?s=…` and it is verified in the routing table.
 */
class Search extends ConditionBase {

	public static function get_type(): string {
		return 'archive';
	}

	public static function get_priority(): int {
		return 70;
	}

	public function get_name(): string {
		return 'search';
	}

	public function get_label(): string {
		return esc_html__( 'Search Results', 'piecyfer-core' );
	}

	public function check( array $args ): bool {
		return is_search();
	}
}
