<?php
/**
 * The `popup` document type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Popup;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use PieCyfer\Core\Popup\DisplaySettings\Base;
use PieCyfer\Core\Popup\DisplaySettings\Timing;
use PieCyfer\Core\Popup\DisplaySettings\Triggers;
use PieCyfer\Core\ThemeBuilder\Documents\ThemeSectionDocument;
use PieCyfer\Core\ThemeBuilder\Module as ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/popup/document.php`.
 *
 * A popup is a Theme Builder *section* document with three things bolted on:
 *
 *   1. a CSS wrapper selector of `#elementor-popup-modal-{id}` — the id of the
 *      dialog element the JavaScript builds at runtime, **not** of anything in
 *      the printed markup. Get this wrong and every style rule the popup has
 *      lands on a selector that never matches, which is invisible until the CSS
 *      cache is regenerated.
 *   2. trigger and timing settings, stored in their own postmeta key rather than
 *      in page settings (see DisplaySettings\Base).
 *   3. a large control set whose *ids* are the contract with the saved data.
 *
 * Every control below is registered even though popup 7718 populates only about
 * a third of them, because Elementor drops saved settings that have no matching
 * control the next time the document is saved. A missing control here is not a
 * missing feature, it is silent data loss on the next Update click.
 *
 * Two section ids are load-bearing beyond this file. VamTam's companion plugin
 * injects into `elementor/element/popup/section_advanced/before_section_end`
 * (`vamtam-elementor-integration-tecnologia/includes/widgets/popup.php:87`) and
 * anchors its injections on the `avoid_multiple_popups` and `open_selector`
 * control ids. Rename either and VamTam's popup controls silently disappear.
 */
class Document extends ThemeSectionDocument {

	/**
	 * Where triggers and timing live. Separate from `_elementor_page_settings`.
	 */
	public const DISPLAY_SETTINGS_META_KEY = '_elementor_popup_display_settings';

	/**
	 * @var array<string,Base>|null
	 */
	private ?array $display_settings = null;

	public static function get_type() {
		return 'popup';
	}

	public function get_name() {
		return 'popup';
	}

	public static function get_title() {
		return esc_html__( 'Popup', 'piecyfer-core' );
	}

	public static function get_plural_title() {
		return esc_html__( 'Popups', 'piecyfer-core' );
	}

	public static function get_properties() {
		$properties = parent::get_properties();

		$properties['admin_tab_group'] = 'popup';
		$properties['location']        = Module::LOCATION;
		$properties['support_kit']     = true;

		// The Theme Builder app is Pro React we do not have; Pro sets this false
		// for popups anyway.
		$properties['support_site_editor'] = false;

		/*
		 * Lazy-loading a popup would defer the very markup the open trigger needs
		 * to find. Pro disables it explicitly (`document.php:39`) and so do we.
		 */
		$properties['support_lazyload'] = false;

		return $properties;
	}

	/**
	 * The dialog element's id, not the printed wrapper's.
	 *
	 * `Post_CSS` writes every rule this document generates under this selector
	 * into `uploads/elementor/css/post-{id}.css`. The element it names is created
	 * by DialogsManager at runtime (`popup.js`, `initModal()`), which is why the
	 * printed `<div data-elementor-type="popup" …>` carries no matching id.
	 */
	public function get_css_wrapper_selector() {
		return '#elementor-popup-modal-' . $this->get_main_id();
	}

	/* ---------------------------------------------------------------------
	 * Display settings (triggers + timing)
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<string,Base>
	 */
	public function get_display_settings(): array {
		if ( null === $this->display_settings ) {
			$settings = $this->get_display_settings_data();

			if ( ! $settings ) {
				$settings = array(
					'triggers' => array(),
					'timing'   => array(),
				);
			}

			$id = $this->get_main_id();

			$this->display_settings = array(
				'triggers' => new Triggers(
					array(
						'id'       => $id,
						'settings' => $settings['triggers'] ?? array(),
					)
				),
				'timing'   => new Timing(
					array(
						'id'       => $id,
						'settings' => $settings['timing'] ?? array(),
					)
				),
			);
		}

		return $this->display_settings;
	}

	/**
	 * Editor bootstrap config.
	 *
	 * Port of `elementor-pro/modules/popup/document.php:80-99`. Two additions on
	 * top of the base document config, both consumed only by the editor:
	 *
	 *   - `displaySettings` carries the *controls* and the *saved values* for the
	 *     trigger and timing stacks. Without it the editor has no schema for them,
	 *     and a panel that cannot see a control is a panel that will happily save
	 *     the document without it — which is the data-loss path this whole port
	 *     exists to avoid.
	 *   - `container` is the selector the editor scopes its live style preview to.
	 *     It must name the runtime dialog, not the printed wrapper, for the same
	 *     reason get_css_wrapper_selector() does.
	 *
	 * @return array<string,mixed>
	 */
	public function get_initial_config() {
		$config = parent::get_initial_config();

		$display_settings = $this->get_display_settings();

		$config['displaySettings'] = array(
			'triggers' => array(
				'controls' => $display_settings['triggers']->get_controls(),
				'settings' => $display_settings['triggers']->get_settings(),
			),
			'timing'   => array(
				'controls' => $display_settings['timing']->get_controls(),
				'settings' => $display_settings['timing']->get_settings(),
			),
		);

		$config['container'] = '.elementor-popup-modal .dialog-widget-content';

		return $config;
	}

	/**
	 * @return array<string,mixed>|string
	 */
	public function get_display_settings_data() {
		return $this->get_main_meta( self::DISPLAY_SETTINGS_META_KEY );
	}

	/**
	 * @param array<string,mixed> $display_settings_data
	 */
	public function save_display_settings_data( $display_settings_data ) {
		return $this->update_main_meta( self::DISPLAY_SETTINGS_META_KEY, $display_settings_data );
	}

	/**
	 * Frontend settings — this is what becomes `data-elementor-settings`.
	 *
	 * The `triggers` branch is the subtle one and it is worth understanding
	 * before changing anything here.
	 *
	 * A popup can reach a page two ways: matched by display conditions, or pushed
	 * into the location queue by something that links to it. Triggers are only
	 * emitted for the first case. Otherwise a popup that a visitor has to click a
	 * button to open would *also* open itself on page load, in front of the page
	 * they were reading — Pro's comment is "avoid auto show the popup if it's
	 * enqueued by a dynamic tag".
	 *
	 * On this site that branch is always false: popup 7718 has no conditions at
	 * all, so its `data-elementor-settings` contains no `triggers` key. Verified
	 * against the live output, which is exactly:
	 *
	 *     {"entrance_animation":"fadeIn","exit_animation":"fadeIn",
	 *      "entrance_animation_duration":{"unit":"px","size":0.3,"sizes":[]},
	 *      "prevent_scroll":"yes","a11y_navigation":"yes","timing":[]}
	 *
	 * `timing`, by contrast, is always emitted — frequency capping and audience
	 * rules apply however the popup was opened.
	 *
	 * @return array<string,mixed>
	 */
	public function get_frontend_settings() {
		$settings = parent::get_frontend_settings();

		$display_settings = $this->get_display_settings();

		$popups_by_condition = ThemeBuilder::instance()
			->get_conditions_manager()
			->get_documents_for_location( Module::LOCATION );

		if ( $popups_by_condition && isset( $popups_by_condition[ $this->get_main_id() ] ) ) {
			$settings['triggers'] = $display_settings['triggers']->get_frontend_settings();
		}

		$settings['timing'] = $display_settings['timing']->get_frontend_settings();

		return $settings;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_export_data() {
		$data = parent::get_export_data();

		$display_settings = $this->get_display_settings();

		$data['display_settings'] = array(
			'triggers' => $display_settings['triggers']->get_frontend_settings(),
			'timing'   => $display_settings['timing']->get_frontend_settings(),
		);

		return $data;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function import( array $data ) {
		parent::import( $data );

		if ( isset( $data['display_settings'] ) ) {
			$this->save_display_settings_data( $data['display_settings'] );
		}
	}

	/* ---------------------------------------------------------------------
	 * Controls
	 * ------------------------------------------------------------------ */

	protected function register_controls() {
		$this->register_layout_section();

		/*
		 * parent::register_controls() comes *after* the layout section, exactly
		 * where Pro puts it (`document.php:427`). Order matters twice: it is the
		 * order the editor panel shows, and `post_status` — which ThemeDocument
		 * injects the HTML-tag control next to — is registered by the parent, so
		 * moving this call above the layout section changes where that control
		 * lands.
		 */
		parent::register_controls();

		$this->register_popup_style_section();
		$this->register_overlay_section();
		$this->register_close_button_section();
		$this->register_advanced_section_controls();

		/*
		 * Free Elementor's implementation of this is a "go Pro" placeholder that
		 * registers `section_custom_css_pro` / `custom_css_pro`. Pro's Custom CSS
		 * module replaces it with the real `custom_css` control. That module is a
		 * separate work item (58 elements use Custom CSS site-wide); popup 7718
		 * has no `custom_css` in its page settings, so nothing is at risk here
		 * today. Call it the way Pro does and let the Custom CSS port change what
		 * it resolves to.
		 */
		\Elementor\Plugin::$instance->controls_manager->add_custom_css_controls( $this );
	}

	/**
	 * Layout: size, position, overlay/close toggles, entrance and exit animation.
	 *
	 * Populated on popup 7718: width (+tablet/mobile), height_type, height
	 * (+tablet/mobile), vertical_position_mobile, entrance_animation,
	 * exit_animation, entrance_animation_duration.
	 */
	private function register_layout_section(): void {
		$this->start_controls_section(
			'popup_layout',
			array(
				'label' => esc_html__( 'Layout', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_SETTINGS,
			)
		);

		$this->add_responsive_control(
			'width',
			array(
				'label'      => esc_html__( 'Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				// Only CSS `<length>` is allowed here, not `<percentage>` — the
				// dialog is not laid out inside a sized parent.
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 100,
						'max' => 1000,
					),
					'em'  => array(
						'min' => 10,
						'max' => 100,
					),
					'rem' => array(
						'min' => 10,
						'max' => 100,
					),
					'vh'  => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'default'    => array(
					'size' => 640,
				),
				'selectors'  => array(
					'{{WRAPPER}} .dialog-message' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'height_type',
			array(
				'label'                => esc_html__( 'Height', 'piecyfer-core' ),
				'type'                 => Controls_Manager::SELECT,
				'default'              => 'auto',
				'options'              => array(
					'auto'          => esc_html__( 'Fit To Content', 'piecyfer-core' ),
					'fit_to_screen' => esc_html__( 'Fit To Screen', 'piecyfer-core' ),
					'custom'        => esc_html__( 'Custom', 'piecyfer-core' ),
				),
				'selectors_dictionary' => array(
					'fit_to_screen' => '100vh',
				),
				'selectors'            => array(
					'{{WRAPPER}} .dialog-message' => 'height: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'height',
			array(
				'label'      => esc_html__( 'Custom Height', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px' => array(
						'min' => 100,
						'max' => 1000,
					),
					'vh' => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'condition'  => array(
					'height_type' => 'custom',
				),
				'default'    => array(
					'size' => 380,
				),
				'selectors'  => array(
					'{{WRAPPER}} .dialog-message' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'content_position',
			array(
				'label'                => esc_html__( 'Content Position', 'piecyfer-core' ),
				'type'                 => Controls_Manager::SELECT,
				'default'              => 'top',
				'options'              => array(
					'top'    => esc_html__( 'Top', 'piecyfer-core' ),
					'center' => esc_html__( 'Center', 'piecyfer-core' ),
					'bottom' => esc_html__( 'Bottom', 'piecyfer-core' ),
				),
				'condition'            => array(
					'height_type!' => 'auto',
				),
				'selectors_dictionary' => array(
					'top'    => 'flex-start',
					'bottom' => 'flex-end',
				),
				'selectors'            => array(
					'{{WRAPPER}} .dialog-message' => 'align-items: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'position_heading',
			array(
				'label'     => esc_html__( 'Position', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'horizontal_position',
			array(
				'label'                => esc_html__( 'Horizontal', 'piecyfer-core' ),
				'type'                 => Controls_Manager::CHOOSE,
				'toggle'               => false,
				'default'              => 'center',
				'options'              => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'selectors'            => array(
					'{{WRAPPER}}' => 'justify-content: {{VALUE}}',
				),
				'selectors_dictionary' => array(
					'left'  => 'flex-start',
					'right' => 'flex-end',
				),
			)
		);

		$this->add_responsive_control(
			'vertical_position',
			array(
				'label'                => esc_html__( 'Vertical', 'piecyfer-core' ),
				'type'                 => Controls_Manager::CHOOSE,
				'toggle'               => false,
				'default'              => 'center',
				'options'              => array(
					'top'    => array(
						'title' => esc_html__( 'Top', 'piecyfer-core' ),
						'icon'  => 'eicon-v-align-top',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon'  => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => esc_html__( 'Bottom', 'piecyfer-core' ),
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				'selectors'            => array(
					'{{WRAPPER}}' => 'align-items: {{VALUE}}',
				),
				'selectors_dictionary' => array(
					'top'    => 'flex-start',
					'bottom' => 'flex-end',
				),
			)
		);

		$this->add_control(
			'overlay',
			array(
				'label'     => esc_html__( 'Overlay', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Hide', 'piecyfer-core' ),
				'label_on'  => esc_html__( 'Show', 'piecyfer-core' ),
				'default'   => 'yes',
				/*
				 * The modal root has `pointer-events: none` in the stylesheet so
				 * that a popup with no overlay does not swallow clicks on the page
				 * behind it. Turning the overlay on is what re-enables them.
				 */
				'selectors' => array(
					'{{WRAPPER}}' => 'pointer-events: all',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'close_button',
			array(
				'label'     => esc_html__( 'Close Button', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Hide', 'piecyfer-core' ),
				'label_on'  => esc_html__( 'Show', 'piecyfer-core' ),
				'default'   => 'yes',
				'selectors' => array(
					// flex, not block: it vertically centres both icon types, the
					// <i> font icon and the SVG.
					'{{WRAPPER}} .dialog-close-button' => 'display: flex',
				),
			)
		);

		$this->add_responsive_control(
			'entrance_animation',
			array(
				'label'              => esc_html__( 'Entrance Animation', 'piecyfer-core' ),
				'type'               => Controls_Manager::ANIMATION,
				'frontend_available' => true,
				'separator'          => 'before',
			)
		);

		$this->add_responsive_control(
			'exit_animation',
			array(
				'label'              => esc_html__( 'Exit Animation', 'piecyfer-core' ),
				'type'               => Controls_Manager::EXIT_ANIMATION,
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'entrance_animation_duration',
			array(
				'label'              => esc_html__( 'Animation Duration', 'piecyfer-core' ) . ' (s)',
				'type'               => Controls_Manager::SLIDER,
				'frontend_available' => true,
				'default'            => array(
					'size' => 1.2,
				),
				'range'              => array(
					'px' => array(
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					),
				),
				'selectors'          => array(
					'{{WRAPPER}} .dialog-widget-content' => 'animation-duration: {{SIZE}}s',
				),
				'conditions'         => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'entrance_animation',
							'operator' => '!==',
							'value'    => '',
						),
						array(
							'name'     => 'exit_animation',
							'operator' => '!==',
							'value'    => '',
						),
					),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Popup: the box itself.
	 *
	 * Populated on 7718: background_background, background_color (globals-linked),
	 * background_image, background_position, background_repeat, background_size,
	 * border_radius.
	 */
	private function register_popup_style_section(): void {
		$this->start_controls_section(
			'section_page_style',
			array(
				'label' => esc_html__( 'Popup', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'background',
				'selector' => '{{WRAPPER}} .dialog-widget-content',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'border',
				'selector' => '{{WRAPPER}} .dialog-widget-content',
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .dialog-widget-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'           => 'box_shadow',
				'selector'       => '{{WRAPPER}} .dialog-widget-content',
				/*
				 * A popup ships with a shadow *on* by default, unlike every other
				 * box-shadow group in Elementor. Popup 7718 saves no box_shadow
				 * keys at all, so this default is what actually renders — drop it
				 * and the popup loses its shadow with nothing in the database to
				 * show why.
				 */
				'fields_options' => array(
					'box_shadow_type' => array(
						'default' => 'yes',
					),
					'box_shadow'      => array(
						'default' => array(
							'horizontal' => 2,
							'vertical'   => 8,
							'blur'       => 23,
							'spread'     => 3,
							'color'      => 'rgba(0,0,0,0.2)',
						),
					),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Overlay.
	 *
	 * Populated on 7718: overlay_background_color = #00000099.
	 */
	private function register_overlay_section(): void {
		$this->start_controls_section(
			'section_overlay',
			array(
				'label'     => esc_html__( 'Overlay', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'overlay' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'overlay_background',
				'types'          => array( 'classic', 'gradient' ),
				'selector'       => '{{WRAPPER}}',
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
					),
					'color'      => array(
						'default' => 'rgba(0,0,0,.8)',
					),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Close Button.
	 *
	 * Populated on 7718: close_button_vertical/horizontal (+ empty tablet and
	 * mobile entries, which still need their controls to exist), icon_size.
	 */
	private function register_close_button_section(): void {
		$this->start_controls_section(
			'section_close_button',
			array(
				'label'     => esc_html__( 'Close Button', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'close_button!' => '',
				),
			)
		);

		$this->add_control(
			'close_button_position',
			array(
				'label'              => esc_html__( 'Position', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					''        => esc_html__( 'Inside', 'piecyfer-core' ),
					'outside' => esc_html__( 'Outside', 'piecyfer-core' ),
				),
				// Read by popup.js to decide which element the close button is
				// prepended to — the widget, or the widget content.
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'close_button_vertical',
			array(
				'label'          => esc_html__( 'Vertical Position', 'piecyfer-core' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'          => array(
					'px'  => array(
						'min' => -500,
						'max' => 500,
					),
					'em'  => array(
						'min' => -50,
						'max' => 50,
					),
					'rem' => array(
						'min' => -50,
						'max' => 50,
					),
				),
				'default'        => array(
					'unit' => '%',
				),
				'tablet_default' => array(
					'unit' => '%',
				),
				'mobile_default' => array(
					'unit' => '%',
				),
				'selectors'      => array(
					'{{WRAPPER}} .dialog-close-button' => 'top: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'close_button_horizontal',
			array(
				'label'          => esc_html__( 'Horizontal Position', 'piecyfer-core' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'          => array(
					'px'  => array(
						'min' => -500,
						'max' => 500,
					),
					'em'  => array(
						'min' => -50,
						'max' => 50,
					),
					'rem' => array(
						'min' => -50,
						'max' => 50,
					),
				),
				'default'        => array(
					'unit' => '%',
				),
				'tablet_default' => array(
					'unit' => '%',
				),
				'mobile_default' => array(
					'unit' => '%',
				),
				/*
				 * Two selectors, not one logical property. This predates
				 * inset-inline-end in Elementor's generated CSS and the pair is
				 * what the existing post-7718.css was generated from; collapsing
				 * them would change the generated file.
				 */
				'selectors'      => array(
					'body:not(.rtl) {{WRAPPER}} .dialog-close-button' => 'right: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .dialog-close-button' => 'left: {{SIZE}}{{UNIT}}',
				),
				'separator'      => 'after',
			)
		);

		$this->start_controls_tabs( 'close_button_style_tabs' );

		$this->start_controls_tab(
			'tab_x_button_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'close_button_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					// Both, because the `e_font_icon_svg` experiment decides at
					// runtime whether the close icon is an <i> or an <svg>. That
					// experiment is **active** on this site.
					'{{WRAPPER}} .dialog-close-button i'   => 'color: {{VALUE}}',
					'{{WRAPPER}} .dialog-close-button svg' => 'fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'close_button_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dialog-close-button' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_x_button_hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'close_button_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dialog-close-button:hover i' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'close_button_hover_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dialog-close-button:hover' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .dialog-close-button' => 'font-size: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Advanced: behaviour, spacing and the extension points VamTam injects into.
	 *
	 * The section id `section_advanced` and the control ids `avoid_multiple_popups`
	 * and `open_selector` are anchors for
	 * `elementor/element/popup/section_advanced/before_section_end`, which VamTam
	 * uses. Do not rename them.
	 *
	 * Populated on 7718: prevent_scroll, margin_mobile, padding (+tablet/mobile).
	 */
	private function register_advanced_section_controls(): void {
		$this->start_controls_section(
			'section_advanced',
			array(
				'label' => esc_html__( 'Advanced', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$this->add_control(
			'close_button_delay',
			array(
				'label'              => esc_html__( 'Show Close Button After', 'piecyfer-core' ) . ' (sec)',
				'type'               => Controls_Manager::NUMBER,
				'min'                => 0.1,
				'max'                => 60,
				'step'               => 0.1,
				'condition'          => array(
					'close_button' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'close_automatically',
			array(
				'label'              => esc_html__( 'Automatically Close After', 'piecyfer-core' ) . ' (sec)',
				'type'               => Controls_Manager::NUMBER,
				'min'                => 0.1,
				'max'                => 60,
				'step'               => 0.1,
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'prevent_close_on_background_click',
			array(
				'label'              => esc_html__( 'Prevent Closing on Overlay', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'prevent_close_on_esc_key',
			array(
				'label'              => esc_html__( 'Prevent Closing on ESC key', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'prevent_scroll',
			array(
				'label'              => esc_html__( 'Disable Page Scrolling', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'avoid_multiple_popups',
			array(
				'label'              => esc_html__( 'Avoid Multiple Popups', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'description'        => esc_html__( 'If the user has seen another popup on the page hide this popup', 'piecyfer-core' ),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'a11y_navigation',
			array(
				'label'              => esc_html__( 'Accessible navigation', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				// Default yes, and popup 7718 relies on the default — it has no
				// saved value, yet the live markup carries `"a11y_navigation":"yes"`.
				'default'            => 'yes',
				'description'        => esc_html__( 'Allow keyboard tab navigation for accessibility', 'piecyfer-core' ),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'open_selector',
			array(
				'label'              => esc_html__( 'Open By Selector', 'piecyfer-core' ),
				'type'               => Controls_Manager::TEXT,
				'placeholder'        => esc_html__( '#id, .class', 'piecyfer-core' ),
				'description'        => esc_html__( 'In order to open a popup on selector click, please set your Popup Conditions', 'piecyfer-core' ),
				'frontend_available' => true,
				'dynamic'            => array(
					'active' => true,
				),
				'ai'                 => array(
					'active' => false,
				),
			)
		);

		$this->add_responsive_control(
			'margin',
			array(
				'label'      => esc_html__( 'Margin', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .dialog-widget-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'padding',
			array(
				'label'      => esc_html__( 'Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .dialog-message' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'classes',
			array(
				'label'              => esc_html__( 'CSS Classes', 'piecyfer-core' ),
				'type'               => Controls_Manager::TEXT,
				'title'              => esc_html__( 'Add your custom class WITHOUT the dot. e.g: my-class', 'piecyfer-core' ),
				'ai'                 => array(
					'active' => false,
				),
				// Appended to the modal's className by popup.js, not printed on
				// the server — the wrapper markup never carries it.
				'frontend_available' => true,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function get_remote_library_config() {
		$config = parent::get_remote_library_config();

		$config['type']               = 'popup';
		$config['default_route']      = 'templates/popups';
		$config['autoImportSettings'] = true;

		return $config;
	}
}
