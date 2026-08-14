<?php
/**
 * Base class for every display condition.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Conditions;

use PieCyfer\Core\ThemeBuilder\ConditionsManager;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/theme-builder/conditions/condition-base.php`.
 *
 * Pro's version extends `Elementor\Controls_Stack` because each condition also
 * describes a control for the Display Conditions panel. That panel is Pro editor
 * JS which we cannot reproduce (04-THEME-BUILDER-SPEC.md §5.7), so the controls
 * are dead weight here and the base is a plain object instead. Everything the
 * *routing* depends on is kept:
 *
 *   - get_name()      the string a saved condition refers to, e.g. `not_found404`
 *   - get_priority()  static, because ConditionsManager reads it off the class
 *                     exactly as Pro does (`$instance::get_priority()`)
 *   - check()         the runtime test
 *   - get_sub_conditions()  its *count* feeds the -5 specificity bonus, so it
 *                     must be populated before any priority is computed
 *
 * get_name() is the join key between `wp_options.elementor_pro_theme_builder_conditions`
 * and this class. Rename one and the template it routes silently stops matching:
 * ConditionsManager skips conditions whose name is not registered, so the failure
 * is a template that quietly renders nowhere, not an error.
 */
abstract class ConditionBase {

	/**
	 * Names of the conditions that can be nested under this one.
	 *
	 * Populated partly by the subclass declaration and partly at registration
	 * time by register_sub_conditions().
	 *
	 * @var string[]
	 */
	protected array $sub_conditions = array();

	/**
	 * Lower number = more specific = wins. See ConditionsManager::condition_priority().
	 */
	public static function get_priority(): int {
		return 100;
	}

	/**
	 * `general`, `archive` or `singular` — the group the condition belongs to.
	 */
	abstract public static function get_type(): string;

	abstract public function get_name(): string;

	abstract public function get_label(): string;

	/**
	 * Human label for "all of them", used by the admin Instances column.
	 */
	public function get_all_label(): string {
		return $this->get_label();
	}

	/**
	 * @param array<string,mixed> $args Always `array()` for an outer condition,
	 *                                  `array( 'id' => string )` for a sub-condition.
	 */
	public function check( array $args ): bool {
		return false;
	}

	/**
	 * @return string[]
	 */
	public function get_sub_conditions(): array {
		return $this->sub_conditions;
	}

	/**
	 * Create and register the conditions that nest under this one.
	 *
	 * Pro reaches the manager through its module singleton from inside the
	 * constructor; the manager is passed in here instead so a condition can be
	 * constructed and inspected without side effects — which is what makes the
	 * offline routing comparison in _project/scripts/theme-builder-routing.php
	 * possible.
	 */
	public function register_sub_conditions( ConditionsManager $manager ): void {
	}

	/**
	 * Register a child and record its name as one of ours.
	 */
	final protected function register_sub_condition( ConditionsManager $manager, ConditionBase $condition ): void {
		$manager->register_condition_instance( $condition );
		$this->sub_conditions[] = $condition->get_name();
	}
}
