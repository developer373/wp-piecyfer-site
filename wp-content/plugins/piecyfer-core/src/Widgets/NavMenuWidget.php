<?php
/**
 * Replacement for Elementor Pro's `nav-menu` widget.
 *
 * 25 instances with 70 populated settings — the most-used Pro widget on this
 * site. It appears in every header/footer template, so a mistake here is a
 * mistake on every page.
 *
 * Two things make this widget different from the other replacements:
 *
 *   1. The VamTam integration plugin subclasses Pro's Nav_Menu (as
 *      `Vamtam_Widget_Nav_Menu`) purely to append a `vamtam-nav-menu` script to
 *      get_script_depends(). We register at a priority above VamTam's, so that
 *      subclass never renders again and we have to carry the script ourselves —
 *      see the constructor and get_script_depends() below.
 *
 *   2. VamTam also injects controls through four `before_section_end` hooks
 *      keyed on Pro's section ids:
 *          elementor/element/nav-menu/section_layout/before_section_end
 *          elementor/element/nav-menu/section_style_main-menu/before_section_end
 *          elementor/element/nav-menu/section_style_dropdown/before_section_end
 *          elementor/element/nav-menu/style_toggle/before_section_end
 *      Those injections are not reproduced here — VamTam still supplies them —
 *      but they only fire if our section ids are byte-identical to Pro's. Note
 *      `section_style_main-menu` (hyphen) and `style_toggle` (no `section_`
 *      prefix): both look like typos and neither may be "fixed".
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

defined( 'ABSPATH' ) || exit;

class NavMenuWidget extends AbstractWidget {

	/**
	 * Bumped once per rendered `<ul>`, so the main menu gets `menu-1-<id>` and
	 * the dropdown copy gets `menu-2-<id>`. Two menus with the same DOM id would
	 * be invalid HTML and would break SmartMenus' element lookup.
	 */
	protected int $nav_menu_index = 1;

	/**
	 * Carry VamTam's script registration.
	 *
	 * `Vamtam_Widget_Nav_Menu` registered this handle from its own constructor.
	 * Once we win the `nav-menu` slot that class is never instantiated for
	 * rendering, so nothing else would register the handle and
	 * get_script_depends() would name a script WordPress has never heard of —
	 * a silent no-op that leaves the mobile menu without its scroll handler.
	 *
	 * Registering (not enqueuing) is idempotent-safe: if VamTam already claimed
	 * the handle we leave theirs alone, so the URL/version stay whatever that
	 * plugin decided.
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed>|null $args
	 */
	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );

		$this->register_vamtam_script();
	}

	private function register_vamtam_script(): void {
		if ( ! defined( 'VAMTAM_ELEMENTOR_INT_URL' ) || ! class_exists( '\VamtamElementorIntregration' ) ) {
			return;
		}

		if ( wp_script_is( 'vamtam-nav-menu', 'registered' ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		/*
		 * The leading slash after the constant is VamTam's, and the constant is
		 * plugin_dir_url() so it already ends in one. The resulting double slash
		 * is harmless but it is also the exact URL the site serves today, so it
		 * is reproduced rather than tidied.
		 */
		wp_register_script(
			'vamtam-nav-menu',
			VAMTAM_ELEMENTOR_INT_URL . '/assets/js/widgets/nav-menu/vamtam-nav-menu' . $suffix . '.js',
			array( 'elementor-frontend' ),
			\VamtamElementorIntregration::PLUGIN_VERSION,
			true
		);
	}

	public function get_name(): string {
		return 'nav-menu';
	}

	public function get_title(): string {
		return esc_html__( 'WordPress Menu', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-nav-menu';
	}

	public function get_categories(): array {
		return array( 'pro-elements', 'theme-elements' );
	}

	public function get_keywords(): array {
		return array( 'menu', 'nav', 'button', 'nav menu' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — NavMenu/Nav_Menu (+ VamTam subclass)';
	}

	/**
	 * `smartmenus` is Elementor Pro's own registration and stays; `vamtam-nav-menu`
	 * is what the VamTam subclass added and is the reason this method exists at
	 * all. Pro alone returns only `smartmenus`.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'smartmenus', 'vamtam-nav-menu' );
	}

	/**
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'piecyfer-nav-menu' );
	}

	protected function assets( string $handle ): void {
		// Registered centrally so the handle matches get_style_depends(), which
		// Elementor resolves long before render_widget() runs.
	}

	protected function get_nav_menu_index(): int {
		return $this->nav_menu_index++;
	}

	/**
	 * @return array<string,string> menu slug => menu name
	 */
	private function get_available_menus(): array {
		$menus = wp_get_nav_menus();

		$options = array();

		foreach ( $menus as $menu ) {
			$options[ $menu->slug ] = $menu->name;
		}

		return $options;
	}

	// -------------------------------------------------------------- controls

	protected function register_controls(): void {
		$this->register_layout_section();
		$this->register_main_menu_style_section();
		$this->register_dropdown_style_section();
		$this->register_toggle_style_section();
	}

	private function register_layout_section(): void {
		/*
		 * `section_layout` — VamTam injects its "Disable Page Scroll" switcher and
		 * the two mobile max-height controls at the end of this section.
		 */
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_html__( 'Layout', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'menu_name',
			array(
				'label'   => esc_html__( 'Menu Name', 'piecyfer-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Menu', 'piecyfer-core' ),
			)
		);

		$menus = $this->get_available_menus();

		if ( ! empty( $menus ) ) {
			$this->add_control(
				'menu',
				array(
					'label'        => esc_html__( 'Menu', 'piecyfer-core' ),
					'type'         => Controls_Manager::SELECT,
					'options'      => $menus,
					'default'      => array_keys( $menus )[0],
					// Writes the resolved default into the saved settings, so a
					// widget dropped before any menu existed keeps pointing at
					// the same menu once one does.
					'save_default' => true,
					'separator'    => 'after',
					'description'  => sprintf(
						/* translators: 1: Link opening tag, 2: Link closing tag. */
						esc_html__( 'Go to the %1$sMenus screen%2$s to manage your menus.', 'piecyfer-core' ),
						sprintf( '<a href="%s" target="_blank">', admin_url( 'nav-menus.php' ) ),
						'</a>'
					),
				)
			);
		} else {
			// Same control id on purpose: the alert stands in for the select so
			// the saved `menu` value is never orphaned on a site whose menus
			// have all been deleted.
			$this->add_control(
				'menu',
				array(
					'type'       => Controls_Manager::ALERT,
					'alert_type' => 'info',
					'heading'    => esc_html__( 'There are no menus in your site.', 'piecyfer-core' ),
					'content'    => sprintf(
						/* translators: 1: Link opening tag, 2: Link closing tag. */
						esc_html__( 'Go to the %1$sMenus screen%2$s to create one.', 'piecyfer-core' ),
						sprintf( '<a href="%s" target="_blank">', admin_url( 'nav-menus.php?action=edit&menu=0' ) ),
						'</a>'
					),
					'separator'  => 'after',
				)
			);
		}

		$this->add_control(
			'layout',
			array(
				'label'              => esc_html__( 'Layout', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'horizontal',
				'options'            => array(
					'horizontal' => esc_html__( 'Horizontal', 'piecyfer-core' ),
					'vertical'   => esc_html__( 'Vertical', 'piecyfer-core' ),
					'dropdown'   => esc_html__( 'Dropdown', 'piecyfer-core' ),
				),
				// Read from the wrapper's data-settings JSON by Pro's nav-menu
				// handler to decide whether to boot SmartMenus at all.
				'frontend_available' => true,
			)
		);

		$start = is_rtl() ? 'end' : 'start';
		$end   = is_rtl() ? 'start' : 'end';

		$this->add_control(
			'align_items',
			array(
				'label'              => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type'               => Controls_Manager::CHOOSE,
				'options'            => array(
					'start'   => array(
						'title' => esc_html__( 'Start', 'piecyfer-core' ),
						'icon'  => "eicon-align-$start-h",
					),
					'center'  => array(
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon'  => 'eicon-align-center-h',
					),
					'end'     => array(
						'title' => esc_html__( 'End', 'piecyfer-core' ),
						'icon'  => "eicon-align-$end-h",
					),
					'justify' => array(
						'title' => esc_html__( 'Stretch', 'piecyfer-core' ),
						'icon'  => 'eicon-align-stretch-h',
					),
				),
				/*
				 * Backwards compatibility: instances saved before Elementor
				 * renamed left/right to start/end still hold `left`/`right`, and
				 * this map is the only thing that turns them into the classes the
				 * stylesheet knows. Dropping it would silently un-align older
				 * menus. The stylesheet keeps rules for both spellings anyway.
				 */
				'classes_dictionary' => array(
					'left'  => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'prefix_class'       => 'elementor-nav-menu__align-',
				'condition'          => array(
					'layout!' => 'dropdown',
				),
			)
		);

		$this->add_control(
			'pointer',
			array(
				'label'          => esc_html__( 'Pointer', 'piecyfer-core' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => 'underline',
				'options'        => array(
					'none'        => esc_html__( 'None', 'piecyfer-core' ),
					'underline'   => esc_html__( 'Underline', 'piecyfer-core' ),
					'overline'    => esc_html__( 'Overline', 'piecyfer-core' ),
					'double-line' => esc_html__( 'Double Line', 'piecyfer-core' ),
					'framed'      => esc_html__( 'Framed', 'piecyfer-core' ),
					'background'  => esc_html__( 'Background', 'piecyfer-core' ),
					'text'        => esc_html__( 'Text', 'piecyfer-core' ),
				),
				'style_transfer' => true,
				'condition'      => array(
					'layout!' => 'dropdown',
				),
			)
		);

		$this->add_control(
			'animation_line',
			array(
				'label'     => esc_html__( 'Animation', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'     => 'Fade',
					'slide'    => 'Slide',
					'grow'     => 'Grow',
					'drop-in'  => 'Drop In',
					'drop-out' => 'Drop Out',
					'none'     => 'None',
				),
				'condition' => array(
					'layout!' => 'dropdown',
					'pointer' => array( 'underline', 'overline', 'double-line' ),
				),
			)
		);

		$this->add_control(
			'animation_framed',
			array(
				'label'     => esc_html__( 'Animation', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'    => 'Fade',
					'grow'    => 'Grow',
					'shrink'  => 'Shrink',
					'draw'    => 'Draw',
					'corners' => 'Corners',
					'none'    => 'None',
				),
				'condition' => array(
					'layout!' => 'dropdown',
					'pointer' => 'framed',
				),
			)
		);

		$this->add_control(
			'animation_background',
			array(
				'label'     => esc_html__( 'Animation', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'                    => 'Fade',
					'grow'                    => 'Grow',
					'shrink'                  => 'Shrink',
					'sweep-left'              => 'Sweep Left',
					'sweep-right'             => 'Sweep Right',
					'sweep-up'                => 'Sweep Up',
					'sweep-down'              => 'Sweep Down',
					'shutter-in-vertical'     => 'Shutter In Vertical',
					'shutter-out-vertical'    => 'Shutter Out Vertical',
					'shutter-in-horizontal'   => 'Shutter In Horizontal',
					'shutter-out-horizontal'  => 'Shutter Out Horizontal',
					'none'                    => 'None',
				),
				'condition' => array(
					'layout!' => 'dropdown',
					'pointer' => 'background',
				),
			)
		);

		$this->add_control(
			'animation_text',
			array(
				'label'     => esc_html__( 'Animation', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'grow',
				'options'   => array(
					'grow'   => 'Grow',
					'shrink' => 'Shrink',
					'sink'   => 'Sink',
					'float'  => 'Float',
					'skew'   => 'Skew',
					'rotate' => 'Rotate',
					'none'   => 'None',
				),
				'condition' => array(
					'layout!' => 'dropdown',
					'pointer' => 'text',
				),
			)
		);

		$icon_prefix = Icons_Manager::is_migration_allowed() ? 'fas ' : 'fa ';

		$this->add_control(
			'submenu_icon',
			array(
				'label'                  => esc_html__( 'Submenu Indicator', 'piecyfer-core' ),
				'type'                   => Controls_Manager::ICONS,
				'separator'              => 'before',
				'default'                => array(
					'value'   => $icon_prefix . 'fa-caret-down',
					'library' => 'fa-solid',
				),
				'recommended'            => array(
					'fa-solid' => array(
						'chevron-down',
						'angle-down',
						'caret-down',
						'plus',
					),
				),
				'label_block'            => false,
				'skin'                   => 'inline',
				'exclude_inline_options' => array( 'svg' ),
				/*
				 * The submenu arrow is not rendered by PHP at all: SmartMenus
				 * injects it client-side from the data-settings JSON, which is
				 * what frontend_available populates. get_frontend_settings()
				 * below turns the icon into finished markup for it.
				 */
				'frontend_available'     => true,
			)
		);

		$this->add_control(
			'heading_mobile_dropdown',
			array(
				'label'     => esc_html__( 'Mobile Dropdown', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'layout!' => 'dropdown',
				),
			)
		);

		$breakpoints          = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();
		$dropdown_options     = array();
		$excluded_breakpoints = array(
			'laptop',
			'widescreen',
		);

		foreach ( $breakpoints as $breakpoint_key => $breakpoint_instance ) {
			// Laptop and widescreen are desktop-side breakpoints; collapsing a
			// menu to a burger above 1440px is never what anyone means.
			if ( in_array( $breakpoint_key, $excluded_breakpoints, true ) ) {
				continue;
			}

			$dropdown_options[ $breakpoint_key ] = sprintf(
				/* translators: 1: Breakpoint label, 2: `>` character, 3: Breakpoint value. */
				esc_html__( '%1$s (%2$s %3$dpx)', 'piecyfer-core' ),
				$breakpoint_instance->get_label(),
				'>',
				$breakpoint_instance->get_value()
			);
		}

		$dropdown_options['none'] = esc_html__( 'None', 'piecyfer-core' );

		$this->add_control(
			'dropdown',
			array(
				'label'        => esc_html__( 'Breakpoint', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'tablet',
				'options'      => $dropdown_options,
				'prefix_class' => 'elementor-nav-menu--dropdown-',
				'condition'    => array(
					'layout!' => 'dropdown',
				),
			)
		);

		$this->add_control(
			'full_width',
			array(
				'label'              => esc_html__( 'Full Width', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'description'        => esc_html__( 'Stretch the dropdown of the menu to full width.', 'piecyfer-core' ),
				'prefix_class'       => 'elementor-nav-menu--',
				'return_value'       => 'stretch',
				// The stretch width is measured and applied in JS, so this has to
				// reach data-settings as well as the wrapper class.
				'frontend_available' => true,
				'condition'          => array(
					'dropdown!' => 'none',
				),
			)
		);

		$this->add_control(
			'text_align',
			array(
				'label'        => esc_html__( 'Text  Align', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'aside',
				'options'      => array(
					'aside'  => esc_html__( 'Aside', 'piecyfer-core' ),
					'center' => esc_html__( 'Center', 'piecyfer-core' ),
				),
				'prefix_class' => 'elementor-nav-menu__text-align-',
				'condition'    => array(
					'dropdown!' => 'none',
				),
			)
		);

		$this->add_control(
			'toggle',
			array(
				'label'              => esc_html__( 'Toggle Button', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'burger',
				'options'            => array(
					''       => esc_html__( 'None', 'piecyfer-core' ),
					'burger' => esc_html__( 'Hamburger', 'piecyfer-core' ),
				),
				// Two classes from one prefix_class: the literal
				// `elementor-nav-menu--toggle` plus `elementor-nav-menu--burger`.
				'prefix_class'       => 'elementor-nav-menu--toggle elementor-nav-menu--',
				'render_type'        => 'template',
				'frontend_available' => true,
				'condition'          => array(
					'dropdown!' => 'none',
				),
			)
		);

		$this->start_controls_tabs( 'nav_icon_options' );

		$this->start_controls_tab(
			'nav_icon_normal_options',
			array(
				'label'     => esc_html__( 'Normal', 'piecyfer-core' ),
				'condition' => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->add_control(
			'toggle_icon_normal',
			array(
				'label'            => esc_html__( 'Icon', 'piecyfer-core' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin'             => 'inline',
				'label_block'      => false,
				'skin_settings'    => array(
					'inline' => array(
						'none' => array(
							'label' => esc_html__( 'Default', 'piecyfer-core' ),
							'icon'  => 'eicon-menu-bar',
						),
						'icon' => array(
							'icon' => 'eicon-star',
						),
					),
				),
				'recommended'      => array(
					'fa-solid'   => array(
						'plus-square',
						'plus',
						'plus-circle',
						'bars',
					),
					'fa-regular' => array(
						'plus-square',
					),
				),
				'condition'        => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'nav_icon_hover_options',
			array(
				'label'     => esc_html__( 'Hover', 'piecyfer-core' ),
				'condition' => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->add_control(
			'toggle_icon_hover_animation',
			array(
				'label'              => esc_html__( 'Hover Animation', 'piecyfer-core' ),
				'type'               => Controls_Manager::HOVER_ANIMATION,
				'frontend_available' => true,
				'render_type'        => 'template',
				'condition'          => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'nav_icon_active_options',
			array(
				'label'     => esc_html__( 'Active', 'piecyfer-core' ),
				'condition' => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->add_control(
			'toggle_icon_active',
			array(
				'label'            => esc_html__( 'Icon', 'piecyfer-core' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin'             => 'inline',
				'label_block'      => false,
				'skin_settings'    => array(
					'inline' => array(
						'none' => array(
							'label' => esc_html__( 'Default', 'piecyfer-core' ),
							'icon'  => 'eicon-close',
						),
						'icon' => array(
							'icon' => 'eicon-star',
						),
					),
				),
				'recommended'      => array(
					'fa-solid'   => array(
						'window-close',
						'times-circle',
						'times',
						'minus-square',
						'minus-circle',
						'minus',
					),
					'fa-regular' => array(
						'window-close',
						'times-circle',
						'minus-square',
					),
				),
				'condition'        => array(
					'toggle' => 'burger',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'toggle_align',
			array(
				'label'                => esc_html__( 'Toggle Align', 'piecyfer-core' ),
				'type'                 => Controls_Manager::CHOOSE,
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
				'selectors_dictionary' => array(
					'left'   => 'margin-right: auto',
					'center' => 'margin: 0 auto',
					'right'  => 'margin-left: auto',
				),
				'selectors'            => array(
					'{{WRAPPER}} .elementor-menu-toggle' => '{{VALUE}}',
				),
				'condition'            => array(
					'toggle!'   => '',
					'dropdown!' => 'none',
				),
				'separator'            => 'before',
			)
		);

		$this->end_controls_section();
	}

	private function register_main_menu_style_section(): void {
		/*
		 * `section_style_main-menu` — the hyphen is Pro's and VamTam hooks this
		 * exact string to re-point the three menu colour controls at CSS custom
		 * properties. Spelling it `section_style_main_menu` would compile fine
		 * and quietly drop that override.
		 */
		$this->start_controls_section(
			'section_style_main-menu',
			array(
				'label'     => esc_html__( 'Main Menu', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'layout!' => 'dropdown',
				),

			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'menu_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .elementor-nav-menu .elementor-item',
			)
		);

		$this->start_controls_tabs( 'tabs_menu_item_style' );

		$this->start_controls_tab(
			'tab_menu_item_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'color_menu_item',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--main .elementor-item' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_menu_item_hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'color_menu_item_hover',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--main .elementor-item:hover,
					{{WRAPPER}} .elementor-nav-menu--main .elementor-item.elementor-item-active,
					{{WRAPPER}} .elementor-nav-menu--main .elementor-item.highlighted,
					{{WRAPPER}} .elementor-nav-menu--main .elementor-item:focus' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
				'condition' => array(
					'pointer!' => 'background',
				),
			)
		);

		$this->add_control(
			'color_menu_item_hover_pointer_bg',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--main .elementor-item:hover,
					{{WRAPPER}} .elementor-nav-menu--main .elementor-item.elementor-item-active,
					{{WRAPPER}} .elementor-nav-menu--main .elementor-item.highlighted,
					{{WRAPPER}} .elementor-nav-menu--main .elementor-item:focus' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'pointer' => 'background',
				),
			)
		);

		$this->add_control(
			'pointer_color_menu_item_hover',
			array(
				'label'     => esc_html__( 'Pointer Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--main:not(.e--pointer-framed) .elementor-item:before,
					{{WRAPPER}} .elementor-nav-menu--main:not(.e--pointer-framed) .elementor-item:after' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .e--pointer-framed .elementor-item:before,
					{{WRAPPER}} .e--pointer-framed .elementor-item:after' => 'border-color: {{VALUE}}',
				),
				'condition' => array(
					'pointer!' => array( 'none', 'text' ),
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_menu_item_active',
			array(
				'label' => esc_html__( 'Active', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'color_menu_item_active',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--main .elementor-item.elementor-item-active' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'pointer_color_menu_item_active',
			array(
				'label'     => esc_html__( 'Pointer Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--main:not(.e--pointer-framed) .elementor-item.elementor-item-active:before,
					{{WRAPPER}} .elementor-nav-menu--main:not(.e--pointer-framed) .elementor-item.elementor-item-active:after' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .e--pointer-framed .elementor-item.elementor-item-active:before,
					{{WRAPPER}} .e--pointer-framed .elementor-item.elementor-item-active:after' => 'border-color: {{VALUE}}',
				),
				'condition' => array(
					'pointer!' => array( 'none', 'text' ),
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$divider_condition = array(
			'nav_menu_divider' => 'yes',
			'layout'           => 'horizontal',
		);

		$this->add_control(
			'nav_menu_divider',
			array(
				'label'     => esc_html__( 'Divider', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Off', 'piecyfer-core' ),
				'label_on'  => esc_html__( 'On', 'piecyfer-core' ),
				'condition' => array(
					'layout' => 'horizontal',
				),
				// The divider is a pseudo-element whose `content` is a custom
				// property; setting it to "" is what switches the divider on.
				'selectors' => array(
					'{{WRAPPER}}' => '--e-nav-menu-divider-content: "";',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'nav_menu_divider_style',
			array(
				'label'     => esc_html__( 'Style', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'solid'  => esc_html__( 'Solid', 'piecyfer-core' ),
					'double' => esc_html__( 'Double', 'piecyfer-core' ),
					'dotted' => esc_html__( 'Dotted', 'piecyfer-core' ),
					'dashed' => esc_html__( 'Dashed', 'piecyfer-core' ),
				),
				'default'   => 'solid',
				'condition' => $divider_condition,
				'selectors' => array(
					'{{WRAPPER}}' => '--e-nav-menu-divider-style: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'nav_menu_divider_weight',
			array(
				'label'      => esc_html__( 'Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 1,
						'max' => 20,
					),
					'em'  => array(
						'max' => 2,
					),
					'rem' => array(
						'max' => 2,
					),
				),
				'condition'  => $divider_condition,
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-nav-menu-divider-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'nav_menu_divider_height',
			array(
				'label'      => esc_html__( 'Height', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 1,
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
					'%'   => array(
						'min' => 1,
						'max' => 100,
					),
				),
				'condition'  => $divider_condition,
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-nav-menu-divider-height: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'nav_menu_divider_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'condition' => $divider_condition,
				'selectors' => array(
					'{{WRAPPER}}' => '--e-nav-menu-divider-color: {{VALUE}}',
				),
			)
		);

		/* This control is required to handle with complicated conditions */
		$this->add_control(
			'hr',
			array(
				'type' => Controls_Manager::DIVIDER,
			)
		);

		$this->add_responsive_control(
			'pointer_width',
			array(
				'label'      => esc_html__( 'Pointer Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 30,
					),
					'em'  => array(
						'max' => 3,
					),
					'rem' => array(
						'max' => 3,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .e--pointer-framed .elementor-item:before' => 'border-width: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .e--pointer-framed.e--animation-draw .elementor-item:before' => 'border-width: 0 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .e--pointer-framed.e--animation-draw .elementor-item:after' => 'border-width: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0 0',
					'{{WRAPPER}} .e--pointer-framed.e--animation-corners .elementor-item:before' => 'border-width: {{SIZE}}{{UNIT}} 0 0 {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .e--pointer-framed.e--animation-corners .elementor-item:after' => 'border-width: 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0',
					'{{WRAPPER}} .e--pointer-underline .elementor-item:after,
					 {{WRAPPER}} .e--pointer-overline .elementor-item:before,
					 {{WRAPPER}} .e--pointer-double-line .elementor-item:before,
					 {{WRAPPER}} .e--pointer-double-line .elementor-item:after' => 'height: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'pointer' => array( 'underline', 'overline', 'double-line', 'framed' ),
				),
			)
		);

		$this->add_responsive_control(
			'padding_horizontal_menu_item',
			array(
				'label'      => esc_html__( 'Horizontal Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 50,
					),
					'em'  => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-nav-menu--main .elementor-item' => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'padding_vertical_menu_item',
			array(
				'label'      => esc_html__( 'Vertical Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 50,
					),
					'em'  => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-nav-menu--main .elementor-item' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'menu_space_between',
			array(
				'label'      => esc_html__( 'Space Between', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-nav-menu-horizontal-menu-item-margin: calc( {{SIZE}}{{UNIT}} / 2 );',
					'{{WRAPPER}} .elementor-nav-menu--main:not(.elementor-nav-menu--layout-horizontal) .elementor-nav-menu > li:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'border_radius_menu_item',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-item:before' => 'border-radius: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .e--animation-shutter-in-horizontal .elementor-item:before' => 'border-radius: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0 0',
					'{{WRAPPER}} .e--animation-shutter-in-horizontal .elementor-item:after' => 'border-radius: 0 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .e--animation-shutter-in-vertical .elementor-item:before' => 'border-radius: 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0',
					'{{WRAPPER}} .e--animation-shutter-in-vertical .elementor-item:after' => 'border-radius: {{SIZE}}{{UNIT}} 0 0 {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'pointer' => 'background',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_dropdown_style_section(): void {
		/*
		 * `section_style_dropdown` — VamTam replaces `color_dropdown_item_hover`'s
		 * selectors here so the toggle does not keep a stuck :hover colour on
		 * touch devices.
		 */
		$this->start_controls_section(
			'section_style_dropdown',
			array(
				'label' => esc_html__( 'Dropdown', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'dropdown_description',
			array(
				'raw'             => esc_html__( 'On desktop, this will affect the submenu. On mobile, this will affect the entire menu.', 'piecyfer-core' ),
				'type'            => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->start_controls_tabs( 'tabs_dropdown_item_style' );

		$this->start_controls_tab(
			'tab_dropdown_item_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'color_dropdown_item',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown a, {{WRAPPER}} .elementor-menu-toggle' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'background_color_dropdown_item',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_dropdown_item_hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'color_dropdown_item_hover',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown a:hover,
					{{WRAPPER}} .elementor-nav-menu--dropdown a.elementor-item-active,
					{{WRAPPER}} .elementor-nav-menu--dropdown a.highlighted,
					{{WRAPPER}} .elementor-menu-toggle:hover' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'background_color_dropdown_item_hover',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown a:hover,
					{{WRAPPER}} .elementor-nav-menu--dropdown a.elementor-item-active,
					{{WRAPPER}} .elementor-nav-menu--dropdown a.highlighted' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_dropdown_item_active',
			array(
				'label' => esc_html__( 'Active', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'color_dropdown_item_active',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown a.elementor-item-active' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'background_color_dropdown_item_active',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown a.elementor-item-active' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'dropdown_typography',
				'global'    => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
				'exclude'   => array( 'line_height' ),
				// Two spaces before `.elementor-sub-item` are Pro's; a descendant
				// combinator is whitespace-insensitive so this is cosmetic, but
				// the string is copied verbatim to keep the generated CSS
				// byte-comparable against the baseline.
				'selector'  => '{{WRAPPER}} .elementor-nav-menu--dropdown .elementor-item, {{WRAPPER}} .elementor-nav-menu--dropdown  .elementor-sub-item',
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'dropdown_border',
				'selector'  => '{{WRAPPER}} .elementor-nav-menu--dropdown',
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'dropdown_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .elementor-nav-menu--dropdown li:first-child a' => 'border-top-left-radius: {{TOP}}{{UNIT}}; border-top-right-radius: {{RIGHT}}{{UNIT}};',
					'{{WRAPPER}} .elementor-nav-menu--dropdown li:last-child a' => 'border-bottom-right-radius: {{BOTTOM}}{{UNIT}}; border-bottom-left-radius: {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'dropdown_box_shadow',
				'exclude'  => array(
					'box_shadow_position',
				),
				'selector' => '{{WRAPPER}} .elementor-nav-menu--main .elementor-nav-menu--dropdown, {{WRAPPER}} .elementor-nav-menu__container.elementor-nav-menu--dropdown',
			)
		);

		$this->add_responsive_control(
			'padding_horizontal_dropdown_item',
			array(
				'label'      => esc_html__( 'Horizontal Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'vw' => array(
						'min' => 0,
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown a' => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',

			)
		);

		$this->add_responsive_control(
			'padding_vertical_dropdown_item',
			array(
				'label'      => esc_html__( 'Vertical Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 50,
					),
					'em'  => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
					'vh'  => array(
						'min' => 0,
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown a' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'heading_dropdown_divider',
			array(
				'label'     => esc_html__( 'Divider', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'dropdown_divider',
				'selector' => '{{WRAPPER}} .elementor-nav-menu--dropdown li:not(:last-child)',
				// Width is excluded from the group and re-added below as a single
				// slider, because only the bottom edge is ever drawn.
				'exclude'  => array( 'width' ),
			)
		);

		$this->add_control(
			'dropdown_divider_width',
			array(
				'label'      => esc_html__( 'Border Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 50,
					),
					'em'  => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-nav-menu--dropdown li:not(:last-child)' => 'border-bottom-width: {{SIZE}}{{UNIT}}',
				),
				// `dropdown_divider_border` is generated by the border group
				// control above — this condition depends on a control this class
				// never names directly.
				'condition'  => array(
					'dropdown_divider_border!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_top_distance',
			array(
				'label'      => esc_html__( 'Distance', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => -100,
						'max' => 100,
					),
					'em'  => array(
						'min' => -10,
						'max' => 10,
					),
					'rem' => array(
						'min' => -10,
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-nav-menu--main > .elementor-nav-menu > li > .elementor-nav-menu--dropdown, {{WRAPPER}} .elementor-nav-menu__container.elementor-nav-menu--dropdown' => 'margin-top: {{SIZE}}{{UNIT}} !important',
				),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();
	}

	private function register_toggle_style_section(): void {
		/*
		 * `style_toggle` — no `section_` prefix, unlike every other style section
		 * in this widget. VamTam hooks that exact id to rewrite the toggle hover
		 * selectors for touch devices.
		 */
		$this->start_controls_section(
			'style_toggle',
			array(
				'label'     => esc_html__( 'Toggle Button', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'toggle!'   => '',
					'dropdown!' => 'none',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_toggle_style' );

		$this->start_controls_tab(
			'tab_toggle_style_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'toggle_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} div.elementor-menu-toggle' => 'color: {{VALUE}}', // Harder selector to override text color control
					'{{WRAPPER}} div.elementor-menu-toggle svg' => 'fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-menu-toggle' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_toggle_style_hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'toggle_color_hover',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} div.elementor-menu-toggle:hover' => 'color: {{VALUE}}', // Harder selector to override text color control
					'{{WRAPPER}} div.elementor-menu-toggle:hover svg' => 'fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_background_color_hover',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-menu-toggle:hover' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'toggle_size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 15,
					),
					'em'  => array(
						'max' => 1.5,
					),
					'rem' => array(
						'max' => 1.5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--nav-menu-icon-size: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'toggle_border_width',
			array(
				'label'      => esc_html__( 'Border Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 20,
					),
					'em'  => array(
						'max' => 2,
					),
					'rem' => array(
						'max' => 2,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-menu-toggle' => 'border-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'toggle_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-menu-toggle' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	// ------------------------------------------------------ frontend settings

	/**
	 * Hand the submenu arrow to JS as finished markup.
	 *
	 * PHP never prints the arrow: SmartMenus creates the `.sub-arrow` span on
	 * the client and fills it from `data-settings`. The markup can be either
	 * `<i>` or `<svg>` depending on the e_font_icon_svg experiment, so the whole
	 * element is serialised here rather than a class name.
	 */
	public function get_frontend_settings() {
		$frontend_settings = parent::get_frontend_settings();

		if ( empty( $frontend_settings['submenu_icon']['value'] ) || ! is_string( $frontend_settings['submenu_icon']['value'] ) ) {
			return $frontend_settings;
		}

		// If the saved value is FA4, but the user has upgraded to FA5, the value needs to be converted to FA5.
		if ( 'fa ' === substr( $frontend_settings['submenu_icon']['value'], 0, 3 ) && Icons_Manager::is_migration_allowed() ) {
			$frontend_settings['submenu_icon']['value'] = str_replace( 'fa ', 'fas ', $frontend_settings['submenu_icon']['value'] );
		}

		if ( \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' ) ) {
			$icon_classes = array();

			// The chevron glyph is optically larger than the other arrows, so it
			// gets its own class that the stylesheet shrinks to .7em.
			if ( false !== strpos( $frontend_settings['submenu_icon']['value'], 'chevron-down' ) ) {
				$icon_classes['class'] = 'fa-svg-chevron-down';
			}

			$icon_content = Icons_Manager::render_font_icon( $frontend_settings['submenu_icon'], $icon_classes );
		} else {
			$icon_content = sprintf( '<i class="%s"></i>', $frontend_settings['submenu_icon']['value'] );
		}

		$frontend_settings['submenu_icon']['value'] = $icon_content;

		return $frontend_settings;
	}

	// ---------------------------------------------------------------- render

	protected function render_widget(): void {
		$available_menus = $this->get_available_menus();

		if ( ! $available_menus ) {
			return;
		}

		$settings = $this->get_active_settings();

		$args = array(
			'echo'        => false,
			'menu'        => $settings['menu'],
			'menu_class'  => 'elementor-nav-menu',
			'menu_id'     => 'menu-' . $this->get_nav_menu_index() . '-' . $this->get_id(),
			'fallback_cb' => '__return_empty_string',
			'container'   => '',
		);

		if ( 'vertical' === $settings['layout'] ) {
			$args['menu_class'] .= ' sm-vertical';
		}

		// Add custom filter to handle Nav Menu HTML output.
		add_filter( 'nav_menu_link_attributes', array( $this, 'handle_link_classes' ), 10, 4 );
		add_filter( 'nav_menu_link_attributes', array( $this, 'handle_link_tabindex' ), 10, 4 );
		add_filter( 'nav_menu_submenu_css_class', array( $this, 'handle_sub_menu_classes' ) );
		add_filter( 'nav_menu_item_id', '__return_empty_string' );

		// General Menu.
		$menu_html = wp_nav_menu( $args );

		/*
		 * Dropdown Menu — the same menu is rendered a second time, which is why
		 * get_nav_menu_index() is called again: the two <ul>s must not share a
		 * DOM id. `menu_type` is read back in handle_link_tabindex() to take the
		 * hidden copy out of the tab order.
		 */
		$args['menu_id']   = 'menu-' . $this->get_nav_menu_index() . '-' . $this->get_id();
		$args['menu_type'] = 'dropdown';
		$dropdown_menu_html = wp_nav_menu( $args );

		// Remove all our custom filters.
		remove_filter( 'nav_menu_link_attributes', array( $this, 'handle_link_classes' ) );
		remove_filter( 'nav_menu_link_attributes', array( $this, 'handle_link_tabindex' ) );
		remove_filter( 'nav_menu_submenu_css_class', array( $this, 'handle_sub_menu_classes' ) );
		remove_filter( 'nav_menu_item_id', '__return_empty_string' );

		if ( empty( $menu_html ) ) {
			return;
		}

		if ( $settings['menu_name'] ) {
			$this->add_render_attribute( 'main-menu', 'aria-label', $settings['menu_name'] );
		}

		if ( 'dropdown' !== $settings['layout'] ) :
			$this->add_render_attribute( 'main-menu', 'class', array(
				'elementor-nav-menu--main',
				'elementor-nav-menu__container',
				'elementor-nav-menu--layout-' . $settings['layout'],
			) );

			if ( $settings['pointer'] ) :
				$this->add_render_attribute( 'main-menu', 'class', 'e--pointer-' . $settings['pointer'] );

				/*
				 * Only one animation_* control is ever active — which one depends
				 * on the pointer — so the first non-empty one wins and the loop
				 * stops. get_active_settings() has already stripped the ones whose
				 * conditions fail.
				 */
				foreach ( $settings as $key => $value ) :
					if ( 0 === strpos( $key, 'animation' ) && $value ) :
						$this->add_render_attribute( 'main-menu', 'class', 'e--animation-' . $value );

						break;
					endif;
				endforeach;
			endif; ?>
			<nav <?php $this->print_render_attribute_string( 'main-menu' ); ?>>
				<?php
					// PHPCS - escaped by WordPress with "wp_nav_menu"
					echo $menu_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</nav>
			<?php
		endif;
		$this->render_menu_toggle( $settings );
		?>
			<nav class="elementor-nav-menu--dropdown elementor-nav-menu__container" aria-hidden="true">
				<?php
					// PHPCS - escaped by WordPress with "wp_nav_menu"
					echo $dropdown_menu_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</nav>
		<?php
	}

	/**
	 * Tag every link with the class the pointer/dropdown CSS keys off.
	 *
	 * Anchor links are deliberately never marked active: WordPress flags the
	 * page they live on as `current-menu-item`, which would light up every
	 * one-page-scroll link at once.
	 *
	 * @param array<string,string> $atts
	 * @param object               $item
	 * @param object               $args
	 * @param int                  $depth
	 * @return array<string,string>
	 */
	public function handle_link_classes( $atts, $item, $args, $depth ) {
		$classes = $depth ? 'elementor-sub-item' : 'elementor-item';
		$is_anchor = false !== strpos( $atts['href'], '#' );

		if ( ! $is_anchor && in_array( 'current-menu-item', $item->classes ) ) {
			$classes .= ' elementor-item-active';
		}

		if ( $is_anchor ) {
			$classes .= ' elementor-item-anchor';
		}

		if ( empty( $atts['class'] ) ) {
			$atts['class'] = $classes;
		} else {
			$atts['class'] .= ' ' . $classes;
		}

		return $atts;
	}

	/**
	 * @param array<string,string> $atts
	 * @param object               $item
	 * @param object               $args
	 * @return array<string,string>
	 */
	public function handle_link_tabindex( $atts, $item, $args ) {
		$settings = $this->get_active_settings();

		// Add `tabindex = -1` to the links if it's a dropdown, for A11y.
		$is_dropdown = 'dropdown' === $settings['layout'];
		$is_dropdown = $is_dropdown || ( isset( $args->menu_type ) && 'dropdown' === $args->menu_type );

		if ( $is_dropdown ) {
			$atts['tabindex'] = '-1';
		}

		return $atts;
	}

	/**
	 * @param array<int,string> $classes
	 * @return array<int,string>
	 */
	public function handle_sub_menu_classes( $classes ) {
		$classes[] = 'elementor-nav-menu--dropdown';

		return $classes;
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function render_menu_toggle( $settings ) {
		if ( ! isset( $settings['toggle'] ) || 'burger' !== $settings['toggle'] ) {
			return;
		}

		$this->add_render_attribute( 'menu-toggle', array(
			'class' => 'elementor-menu-toggle',
			'role' => 'button',
			'tabindex' => '0',
			'aria-label' => esc_html__( 'Menu Toggle', 'piecyfer-core' ),
			'aria-expanded' => 'false',
		) );

		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			// Without this the editor treats a click as "select the widget"
			// instead of passing it through to the toggle.
			$this->add_render_attribute( 'menu-toggle', array(
				'class' => 'elementor-clickable',
			) );
		}

		?>
		<div <?php $this->print_render_attribute_string( 'menu-toggle' ); ?>>
			<?php
			$toggle_icon_hover_animation = ! empty( $settings['toggle_icon_hover_animation'] )
			? ' elementor-animation-' . $settings['toggle_icon_hover_animation']
			: '';

			$open_class = 'elementor-menu-toggle__icon--open' . $toggle_icon_hover_animation;
			$close_class = 'elementor-menu-toggle__icon--close' . $toggle_icon_hover_animation;

			$normal_icon = ! empty( $settings['toggle_icon_normal']['value'] )
				? $settings['toggle_icon_normal']
				: array(
					'library' => 'eicons',
					'value' => 'eicon-menu-bar',
				);

			// An inline SVG carries no class of its own, so it needs a wrapping
			// span for the open/close display toggle to have something to hide.
			$is_normal_icon_svg = 'svg' === $normal_icon['library'];

			if ( $is_normal_icon_svg ) {
				echo '<span class="' . esc_attr( $open_class ) . '">';
			}

			Icons_Manager::render_icon(
				$normal_icon,
				array(
					'aria-hidden' => 'true',
					'role' => 'presentation',
					'class' => $open_class,
				)
			);

			if ( $is_normal_icon_svg ) {
				echo '</span>';
			}

			$active_icon = ! empty( $settings['toggle_icon_active']['value'] )
				? $settings['toggle_icon_active']
				: array(
					'library' => 'eicons',
					'value' => 'eicon-close',
				);

			$is_active_icon_svg = 'svg' === $active_icon['library'];

			if ( $is_active_icon_svg ) {
				echo '<span class="' . esc_attr( $close_class ) . '">';
			}

			Icons_Manager::render_icon(
				$active_icon,
				array(
					'aria-hidden' => 'true',
					'role' => 'presentation',
					'class' => $close_class,
				)
			);

			if ( $is_active_icon_svg ) {
				echo '</span>';
			}
			?>
			<span class="elementor-screen-only"><?php echo esc_html__( 'Menu', 'piecyfer-core' ); ?></span>
		</div>
		<?php
	}

	/**
	 * Deliberately empty, as in Pro: the plain-text export of a menu is a wall
	 * of link labels with no context, and it would end up in post excerpts and
	 * search indexes.
	 */
	public function render_plain_content() {}

	/**
	 * Menu slugs are not stable across sites, so the export carries the term id
	 * alongside it and on_import_update_dynamic_content() below resolves the
	 * slug again on the destination.
	 *
	 * @param array<string,mixed> $element
	 * @return array<string,mixed>
	 */
	public function on_export( $element ) {
		$slug = $element['settings']['menu'] ?? '';
		$menu_object = wp_get_nav_menu_object( $slug );

		if ( ! $menu_object instanceof \WP_Term ) {
			unset( $element['settings']['menu'] );
			return $element;
		}

		$menu_id = $menu_object->term_id ?? 0;

		if ( ! empty( $menu_id ) ) {
			$element['settings']['menu_id'] = $menu_id;
		}

		return $element;
	}

	/**
	 * When importing a menu, if the menu has a slug that already exists, we add "-duplicate" to the slug of the imported menu.
	 * Upon importing a menu widget, we replace the slug to the correct one by fetching it from the correct ID in the $data array.
	 *
	 * This intentionally does NOT call the parent implementation: Pro's own
	 * override replaces the generic On_Import_Trait behaviour rather than adding
	 * to it, and the generic pass would leave the stale slug in place.
	 *
	 * @param array $element_config
	 * @param array $data
	 * @param $controls
	 *
	 * @return array
	 */
	public static function on_import_update_dynamic_content( array $element_config, array $data, $controls = null ): array {
		$old_menu_id = $element_config['settings']['menu_id'] ?? 0;

		if ( empty( $old_menu_id ) ) {
			return $element_config;
		}

		$new_menu_id = $data['term_ids'][ $old_menu_id ] ?? 0;
		$new_slug = wp_get_nav_menu_object( $new_menu_id )->slug ?? '';

		if ( ! empty( $new_slug ) ) {
			$element_config['settings']['menu'] = $new_slug;
		}

		unset( $element_config['settings']['menu_id'] );

		return $element_config;
	}
}
