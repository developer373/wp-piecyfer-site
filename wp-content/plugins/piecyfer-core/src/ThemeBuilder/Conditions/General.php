<?php
/**
 * "Entire Site" condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `conditions/general.php`.
 *
 * Priority 100 — the least specific condition there is, which is why the
 * site-wide footer (1273) loses to the Privacy Policy footer (991509, priority
 * 20) on `/privacy-policy/`.
 */
class General extends ConditionBase {

	protected array $sub_conditions = array(
		'archive',
		'singular',
	);

	public static function get_type(): string {
		return 'general';
	}

	public function get_name(): string {
		return 'general';
	}

	public function get_label(): string {
		return esc_html__( 'General', 'piecyfer-core' );
	}

	public function get_all_label(): string {
		return esc_html__( 'Entire Site', 'piecyfer-core' );
	}

	public function check( array $args ): bool {
		return true;
	}
}
