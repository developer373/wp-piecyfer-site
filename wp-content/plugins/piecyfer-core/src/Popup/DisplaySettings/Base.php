<?php
/**
 * Base for the popup's trigger and timing control stacks.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Popup\DisplaySettings;

use Elementor\Controls_Manager;
use Elementor\Controls_Stack;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/popup/display-settings/base.php`.
 *
 * Triggers and timing are **not** document settings. They live in their own
 * `Controls_Stack` objects and are persisted to a separate postmeta key,
 * `_elementor_popup_display_settings`, as
 *
 *     array( 'triggers' => array( ... ), 'timing' => array( ... ) )
 *
 * That separation is why they survive perfectly well without Pro's editor: the
 * meta is a plain serialised array that nothing else touches.
 *
 * The naming scheme these helpers produce is load-bearing and is what the
 * JavaScript reads. Every group is a switcher named after the group, and every
 * control inside it is prefixed with the group name:
 *
 *     page_load          switcher, 'yes' when the trigger is enabled
 *     page_load_delay    the group's own control
 *
 * The frontend `Triggers`/`Timing` JS looks up `settings[ groupName ]` to decide
 * whether a group is active and then `settings[ groupName + '_' + key ]` for each
 * value. Rename a control and the setting is silently ignored at runtime.
 */
abstract class Base extends Controls_Stack {

	private ?string $current_group = null;

	/**
	 * Open a settings group: a heading now, a switcher at the end.
	 */
	protected function start_settings_group( string $group_name, string $group_title ): void {
		$this->current_group = $group_name;

		$this->add_control(
			$group_name . '_heading',
			array(
				'type'  => Controls_Manager::HEADING,
				'label' => $group_title,
			)
		);
	}

	/**
	 * Close a settings group by adding the switcher that turns it on.
	 *
	 * The switcher's control id **is** the group name, with no suffix. That is
	 * what `settings.page_load` is in the JavaScript.
	 */
	protected function end_settings_group(): void {
		$this->add_control(
			(string) $this->current_group,
			array(
				'type'               => Controls_Manager::SWITCHER,
				'classes'            => 'elementor-popup__display-settings__group-toggle',
				'frontend_available' => true,
			)
		);

		$this->current_group = null;
	}

	/**
	 * Add a control to the current group, prefixing its id and its conditions.
	 *
	 * Two things happen here that are easy to miss:
	 *
	 *   1. Every control in a group is `frontend_available`, unconditionally.
	 *      That is how the whole group ends up in `data-elementor-settings`.
	 *   2. Any `condition` the caller supplied is rewritten so its *keys* are
	 *      group-prefixed too, and then a condition on the group switcher is
	 *      added. So `'condition' => array( 'direction' => 'down' )` inside the
	 *      `scrolling` group becomes
	 *      `array( 'scrolling_direction' => 'down', 'scrolling' => 'yes' )`.
	 *
	 * @param array<string,mixed> $args
	 */
	protected function add_settings_group_control( string $id, array $args ): void {
		$id = $this->get_prefixed_control_id( $id );

		$args['frontend_available'] = true;

		if ( ! empty( $args['condition'] ) ) {
			$args['condition'] = array_combine(
				array_map(
					fn( $key ) => $this->current_group . '_' . $key,
					array_keys( $args['condition'] )
				),
				$args['condition']
			);
		}

		$args['condition'][ (string) $this->current_group ] = 'yes';

		$this->add_control( $id, $args );
	}

	protected function get_prefixed_control_id( string $id ): string {
		return $this->current_group . '_' . $id;
	}
}
