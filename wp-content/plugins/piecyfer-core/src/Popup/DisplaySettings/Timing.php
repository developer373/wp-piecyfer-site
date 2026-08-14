<?php
/**
 * Popup timing — the frequency, audience and schedule rules.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Popup\DisplaySettings;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/popup/display-settings/timing.php`.
 *
 * Like Triggers, **none of this is used on this site** — popup 7718's timing
 * settings are an empty array — and like Triggers, every control id is registered
 * anyway so that saving the stack cannot drop data that a future configuration
 * puts there.
 *
 * Timing is evaluated entirely in the browser. Nothing here is a server-side
 * check, so none of it is a security control: "hide for logged-in users" hides a
 * popup, it does not protect anything. The state it reads lives in the visitor's
 * own storage — see popup.js for exactly which keys and which storage.
 */
class Timing extends Base {

	public function get_name() {
		return 'popup_timing';
	}

	protected function register_controls() {
		$this->start_controls_section( 'timing' );

		// Show only after the visitor has seen N pages in this browser.
		$this->start_settings_group( 'page_views', esc_html__( 'Show after X page views', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'views',
			array(
				'type'    => Controls_Manager::NUMBER,
				'label'   => esc_html__( 'Page Views', 'piecyfer-core' ),
				'default' => 3,
				'min'     => 1,
			)
		);

		$this->end_settings_group();

		// Show only after N distinct browsing sessions.
		$this->start_settings_group( 'sessions', esc_html__( 'Show after X sessions', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'sessions',
			array(
				'type'    => Controls_Manager::NUMBER,
				'label'   => esc_html__( 'Sessions', 'piecyfer-core' ),
				'default' => 2,
				'min'     => 1,
			)
		);

		$this->end_settings_group();

		// Show at most N times, optionally per session / day / week / month.
		$this->start_settings_group( 'times', esc_html__( 'Show up to X times', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'times',
			array(
				'type'    => Controls_Manager::NUMBER,
				'label'   => esc_html__( 'Times', 'piecyfer-core' ),
				'default' => 3,
				'min'     => 1,
			)
		);

		$this->add_settings_group_control(
			'period',
			array(
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Per', 'piecyfer-core' ),
				/*
				 * Empty is not "unset", it is the *persisting* mode, and it is
				 * the backward-compatible default: popups saved before the period
				 * option existed have no value here and must keep counting
				 * forever rather than per session. Do not "tidy" this to
				 * 'session'.
				 */
				'default' => '',
				'options' => array(
					''        => esc_html__( 'Persisting', 'piecyfer-core' ),
					'session' => esc_html__( 'Session', 'piecyfer-core' ),
					'day'     => esc_html__( 'Day', 'piecyfer-core' ),
					'week'    => esc_html__( 'Week', 'piecyfer-core' ),
					'month'   => esc_html__( 'Month', 'piecyfer-core' ),
				),
			)
		);

		$this->add_settings_group_control(
			'count',
			array(
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Count', 'piecyfer-core' ),
				'options' => array(
					''      => esc_html__( 'On Open', 'piecyfer-core' ),
					'close' => esc_html__( 'On Close', 'piecyfer-core' ),
				),
			)
		);

		$this->end_settings_group();

		// Show or hide depending on the referring URL.
		$this->start_settings_group( 'url', esc_html__( 'When arriving from specific URL', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'action',
			array(
				'type'    => Controls_Manager::SELECT,
				'default' => 'show',
				'options' => array(
					'show'  => esc_html__( 'Show', 'piecyfer-core' ),
					'hide'  => esc_html__( 'Hide', 'piecyfer-core' ),
					'regex' => esc_html__( 'Regex', 'piecyfer-core' ),
				),
			)
		);

		$this->add_settings_group_control(
			'url',
			array(
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'URL', 'piecyfer-core' ),
			)
		);

		$this->end_settings_group();

		// Show only for visitors arriving from these kinds of referrer.
		$this->start_settings_group( 'sources', esc_html__( 'Show when arriving from', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'sources',
			array(
				'type'     => Controls_Manager::SELECT2,
				'multiple' => true,
				'default'  => array( 'search', 'external', 'internal' ),
				'options'  => array(
					'search'   => esc_html__( 'Search Engines', 'piecyfer-core' ),
					'external' => esc_html__( 'External Links', 'piecyfer-core' ),
					'internal' => esc_html__( 'Internal Links', 'piecyfer-core' ),
				),
			)
		);

		$this->end_settings_group();

		// Hide for logged-in users, either all of them or specific roles.
		$this->start_settings_group( 'logged_in', esc_html__( 'Hide for logged in users', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'users',
			array(
				'type'    => Controls_Manager::SELECT,
				'default' => 'all',
				'options' => array(
					'all'    => esc_html__( 'All Users', 'piecyfer-core' ),
					'custom' => esc_html__( 'Custom', 'piecyfer-core' ),
				),
			)
		);

		$this->add_settings_group_control(
			'roles',
			array(
				'type'           => Controls_Manager::SELECT2,
				'multiple'       => true,
				'default'        => array(),
				'options'        => $this->get_role_options(),
				'select2options' => array(
					'placeholder' => esc_html__( 'Select Roles', 'piecyfer-core' ),
				),
				'condition'      => array(
					'users' => 'custom',
				),
			)
		);

		$this->end_settings_group();

		// Show only on these device modes. The option list is built from the
		// site's *active* breakpoints, so it follows the kit rather than a fixed
		// desktop/tablet/mobile list.
		$this->start_settings_group( 'devices', esc_html__( 'Show on devices', 'piecyfer-core' ) );

		list( $available_devices, $default_devices ) = $this->get_device_options();

		$this->add_settings_group_control(
			'devices',
			array(
				'type'     => Controls_Manager::SELECT2,
				'multiple' => true,
				'default'  => $default_devices,
				'options'  => $available_devices,
			)
		);

		$this->end_settings_group();

		// Show only in these browsers.
		$this->start_settings_group( 'browsers', esc_html__( 'Show on browsers', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'browsers',
			array(
				'type'    => Controls_Manager::SELECT,
				'default' => 'all',
				'options' => array(
					'all'    => esc_html__( 'All Browsers', 'piecyfer-core' ),
					'custom' => esc_html__( 'Custom', 'piecyfer-core' ),
				),
			)
		);

		$this->add_settings_group_control(
			'browsers_options',
			array(
				'type'      => Controls_Manager::SELECT2,
				'multiple'  => true,
				'default'   => array(),
				'options'   => array(
					'ie'      => esc_html__( 'Internet Explorer', 'piecyfer-core' ),
					'chrome'  => esc_html__( 'Chrome', 'piecyfer-core' ),
					'edge'    => esc_html__( 'Edge', 'piecyfer-core' ),
					'firefox' => esc_html__( 'Firefox', 'piecyfer-core' ),
					'safari'  => esc_html__( 'Safari', 'piecyfer-core' ),
				),
				'condition' => array(
					'browsers' => 'custom',
				),
			)
		);

		$this->end_settings_group();

		// Show only between two dates.
		$this->start_settings_group( 'schedule', esc_html__( 'Schedule date and time', 'piecyfer-core' ) );

		$this->add_settings_group_control(
			'timezone',
			array(
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Timezone', 'piecyfer-core' ),
				'default' => 'site',
				'options' => array(
					'site'    => esc_html__( 'Site', 'piecyfer-core' ),
					'visitor' => esc_html__( 'Visitor', 'piecyfer-core' ),
				),
			)
		);

		$this->add_settings_group_control(
			'start_date',
			array(
				'label'          => esc_html__( 'Start', 'piecyfer-core' ),
				'type'           => Controls_Manager::DATE_TIME,
				'picker_options' => array(
					'enableTime' => true,
					'minDate'    => 'today',
				),
				'validation'     => array(
					'date_time' => array(
						'control_name' => $this->get_prefixed_control_id( 'end_date' ),
						'operator'     => '<=',
					),
				),
			)
		);

		$this->add_settings_group_control(
			'end_date',
			array(
				'label'          => esc_html__( 'End', 'piecyfer-core' ),
				'type'           => Controls_Manager::DATE_TIME,
				'picker_options' => array(
					'enableTime' => true,
					'minDate'    => 'today',
				),
				'validation'     => array(
					'date_time' => array(
						'control_name' => $this->get_prefixed_control_id( 'start_date' ),
						'operator'     => '>=',
					),
				),
			)
		);

		/*
		 * The server's wall-clock time, shipped to the browser as a hidden
		 * control. It is what makes `timezone => site` mean anything at all: the
		 * JavaScript has no idea what the site's timezone is, so it compares
		 * against this value instead of the visitor's clock.
		 */
		$this->add_settings_group_control(
			'server_datetime',
			array(
				'type'    => Controls_Manager::HIDDEN,
				'default' => $this->get_server_datetime(),
			)
		);

		$this->end_settings_group();

		$this->end_controls_section();
	}

	/**
	 * @return array<string,string> role slug => role name
	 */
	private function get_role_options(): array {
		global $wp_roles;

		if ( ! isset( $wp_roles ) || ! is_object( $wp_roles ) ) {
			return array();
		}

		return array_map(
			static fn( $role ) => $role['name'],
			$wp_roles->roles
		);
	}

	/**
	 * Device options follow the kit's active breakpoints, as Pro's do.
	 *
	 * @return array{0:array<string,string>,1:string[]}
	 */
	private function get_device_options(): array {
		$available = array(
			'desktop' => esc_html__( 'Desktop', 'piecyfer-core' ),
		);

		$defaults = array( 'desktop' );

		$breakpoints = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();

		foreach ( $breakpoints as $key => $breakpoint ) {
			$available[ $key ] = $breakpoint->get_label();
			$defaults[]        = $key;
		}

		return array( $available, $defaults );
	}

	private function get_server_datetime(): string {
		$datetime = new \DateTime( 'now', new \DateTimeZone( wp_timezone_string() ) );

		return $datetime->format( 'Y-m-d H:i:s' );
	}
}
