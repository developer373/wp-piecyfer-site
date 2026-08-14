<?php
/**
 * Popup triggers — what makes a popup open by itself.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Popup\DisplaySettings;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/popup/display-settings/triggers.php`.
 *
 * **Nothing on this site uses any of these.** Popup 7718's
 * `_elementor_popup_display_settings` is
 *
 *     array( 'triggers' => array(), 'timing' => array() )
 *
 * — both empty. The popup never opens on its own; it opens only when one of the
 * six buttons carrying the `popup` dynamic tag is clicked.
 *
 * Every control is registered anyway, and that is not busywork. Elementor drops
 * any saved setting that has no matching control the next time the stack is
 * saved. The day someone adds a trigger through a future admin screen, or the day
 * this popup is edited on a site that was set up differently, a missing control
 * id is silent data loss rather than a visible error.
 */
class Triggers extends Base {

	public function get_name() {
		return 'popup_triggers';
	}

	protected function register_controls() {
		$this->start_controls_section( 'triggers' );

		// On page load: open N seconds after the page is ready.
		$this->start_settings_group( 'page_load', esc_html__( 'On Page Load', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'delay',
			array(
				'type'    => Controls_Manager::NUMBER,
				'label'   => esc_html__( 'Within', 'piecyfer-core' ) . ' (sec)',
				'default' => 0,
				'min'     => 0,
				'step'    => 0.1,
			)
		);

		$this->end_settings_group();

		// On scroll: open once the visitor has scrolled N% down, or on any
		// upward scroll.
		$this->start_settings_group( 'scrolling', esc_html__( 'On Scroll', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'direction',
			array(
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Direction', 'piecyfer-core' ),
				'default' => 'down',
				'options' => array(
					'down' => esc_html__( 'Down', 'piecyfer-core' ),
					'up'   => esc_html__( 'Up', 'piecyfer-core' ),
				),
			)
		);

		$this->add_settings_group_control(
			'offset',
			array(
				'type'      => Controls_Manager::NUMBER,
				'label'     => esc_html__( 'Within', 'piecyfer-core' ) . ' (%)',
				'default'   => 50,
				'min'       => 1,
				'max'       => 100,
				'condition' => array(
					'direction' => 'down',
				),
			)
		);

		$this->end_settings_group();

		// On scroll to element: open when a selector enters the viewport.
		$this->start_settings_group( 'scrolling_to', esc_html__( 'On Scroll To Element', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'selector',
			array(
				'type'        => Controls_Manager::TEXT,
				'label'       => esc_html__( 'Selector', 'piecyfer-core' ),
				'placeholder' => '.my-class',
				'ai'          => array(
					'active' => false,
				),
			)
		);

		$this->end_settings_group();

		// On click: open after N clicks anywhere in the page.
		$this->start_settings_group( 'click', esc_html__( 'On Click', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'times',
			array(
				'label'   => esc_html__( 'Clicks', 'piecyfer-core' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 1,
				'min'     => 1,
			)
		);

		$this->end_settings_group();

		// After inactivity: open after N seconds with no keypress or mousemove.
		$this->start_settings_group( 'inactivity', esc_html__( 'After Inactivity', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'time',
			array(
				'type'    => Controls_Manager::NUMBER,
				'label'   => esc_html__( 'Within', 'piecyfer-core' ) . ' (sec)',
				'default' => 30,
				'min'     => 1,
				'step'    => 0.1,
			)
		);

		$this->end_settings_group();

		// On page exit intent: open when the pointer leaves through the top of
		// the viewport. No settings of its own — the switcher is the whole
		// control, which is why this group is empty between start and end.
		$this->start_settings_group( 'exit_intent', esc_html__( 'On Page Exit Intent', 'piecyfer-core' ) );

		$this->end_settings_group();

		$this->end_controls_section();
	}
}
