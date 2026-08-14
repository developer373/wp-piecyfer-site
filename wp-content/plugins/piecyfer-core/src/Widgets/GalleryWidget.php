<?php
/**
 * Replacement for Elementor Pro's `gallery` widget.
 *
 * 1 instance, on the Home page ("tech-frameworks"): a `multiple` gallery with
 * seven filter tabs, 22 populated setting keys, and hide_desktop/tablet/mobile
 * all set — so it never renders visually today. That makes control parity, not
 * pixels, the thing that matters here: the moment anyone saves the Home page in
 * the editor, every setting without a matching control is dropped.
 *
 * Unlike the widgets before it, this one is JS-driven: Elementor FREE ships the
 * e-gallery library (script + stylesheet, both under the handle
 * `elementor-gallery`) which does the actual grid/justified/masonry layout math
 * from the wrapper's `data-settings` JSON. Everything that reaches that JSON is
 * marked `frontend_available` — dropping one of those flags does not break the
 * PHP, it silently leaves the layout engine with a default.
 *
 * Only Pro's own `widget-gallery` layer is replaced, by assets/css/gallery.css.
 * `elementor-gallery` is free's and stays; `e-transitions` is Pro's and is kept
 * under its own handle until that module is transcribed too.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Breakpoints\Manager as Breakpoints_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

final class GalleryWidget extends AbstractWidget {

	public function get_name(): string {
		return 'gallery';
	}

	public function get_title(): string {
		return esc_html__( 'Gallery', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-gallery-justified';
	}

	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — Gallery';
	}

	/**
	 * Pro declares this false; `Element_Base` defaults it to **true**.
	 *
	 * It decides whether the element is baked into the document's element cache
	 * or re-rendered on every request. Leaving the default in place would change
	 * the caching behaviour of the widget we are replacing — invisible to any
	 * pixel comparison, visible in the query count.
	 */
	protected function is_dynamic_content(): bool {
		return false;
	}

	/**
	 * `elementor-gallery` is Elementor FREE's e-gallery library — the script that
	 * actually lays the grid out. Without it the markup renders as an unstyled
	 * pile of divs.
	 *
	 * @return string[]
	 */
	public function get_script_depends(): array {
		return array( 'elementor-gallery' );
	}

	/**
	 * Same list as Pro with `widget-gallery` swapped for ours. The other two are
	 * not ours to replace: `elementor-gallery` is free's e-gallery stylesheet
	 * (the layout primitives our CSS builds on), `e-transitions` is Pro's shared
	 * hover-animation layer, shared with call-to-action.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( 'piecyfer-gallery', 'elementor-gallery', 'e-transitions' );
	}

	/**
	 * Mirrors Pro exactly, including the fact that it only applies to `multiple`.
	 *
	 * The filter bar reuses the nav-menu widget's pointer styles (`e--pointer-*`
	 * on `.elementor-item`), which Pro's own `widget-gallery` stylesheet only
	 * *colours* — the geometry lives in nav-menu's CSS. This hook is how that
	 * dependency is declared under the `e_optimized_css_loading` experiment.
	 *
	 * Known gap, flagged rather than hidden: Pro resolved 'nav-menu' through its
	 * own `get_widget_css_config()` to a Pro asset. Ours falls through to
	 * Elementor free's, which has no `widget-nav-menu.min.css`, so under that
	 * experiment the inlined bundle comes back empty. The non-optimised path —
	 * which is what this site runs — is unaffected, and Pro never enqueued that
	 * stylesheet there either.
	 *
	 * @return string[]
	 */
	public function get_inline_css_depends() {
		if ( 'multiple' === $this->get_settings_for_display( 'gallery_type' ) ) {
			return array( 'nav-menu' );
		}

		return array();
	}

	protected function assets( string $handle ): void {
		// Registered centrally so the handle matches get_style_depends(), which
		// Elementor resolves before render_widget() runs.
	}

	// -------------------------------------------------------------- controls

	protected function register_controls(): void {
		$this->register_settings_section();
		$this->register_filter_bar_content_section();
		$this->register_overlay_section();
		$this->register_image_style_section();
		$this->register_overlay_style_section();
		$this->register_overlay_content_style_section();
		$this->register_filter_bar_style_section();
	}

	private function register_settings_section(): void {
		$this->start_controls_section( 'settings', array( 'label' => esc_html__( 'Settings', 'piecyfer-core' ) ) );

		$this->add_control(
			'gallery_type',
			array(
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Type', 'piecyfer-core' ),
				'default' => 'single',
				'options' => array(
					'single'   => esc_html__( 'Single', 'piecyfer-core' ),
					'multiple' => esc_html__( 'Multiple', 'piecyfer-core' ),
				),
			)
		);

		$this->add_control(
			'gallery',
			array(
				'type'      => Controls_Manager::GALLERY,
				'condition' => array(
					'gallery_type' => 'single',
				),
				'dynamic'   => array(
					'active' => true,
				),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'gallery_title',
			array(
				'type'    => Controls_Manager::TEXT,
				'label'   => esc_html__( 'Title', 'piecyfer-core' ),
				'default' => esc_html__( 'New Gallery', 'piecyfer-core' ),
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'multiple_gallery',
			array(
				'type'    => Controls_Manager::GALLERY,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'galleries',
			array(
				'type'        => Controls_Manager::REPEATER,
				'label'       => esc_html__( 'Galleries', 'piecyfer-core' ),
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ gallery_title }}}',
				'default'     => array(
					array(
						'gallery_title' => esc_html__( 'New Gallery', 'piecyfer-core' ),
					),
				),
				'condition'   => array(
					'gallery_type' => 'multiple',
				),
			)
		);

		$this->add_control(
			'order_by',
			array(
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Order By', 'piecyfer-core' ),
				'options' => array(
					''       => esc_html__( 'Default', 'piecyfer-core' ),
					'random' => esc_html__( 'Random', 'piecyfer-core' ),
				),
				'default' => '',
			)
		);

		$this->add_control(
			'lazyload',
			array(
				'type'               => Controls_Manager::SWITCHER,
				'label'              => esc_html__( 'Lazy Load', 'piecyfer-core' ),
				'return_value'       => 'yes',
				'default'            => 'yes',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'gallery_layout',
			array(
				'type'               => Controls_Manager::SELECT,
				'label'              => esc_html__( 'Layout', 'piecyfer-core' ),
				'default'            => 'grid',
				'options'            => array(
					'grid'      => esc_html__( 'Grid', 'piecyfer-core' ),
					'justified' => esc_html__( 'Justified', 'piecyfer-core' ),
					'masonry'   => esc_html__( 'Masonry', 'piecyfer-core' ),
				),
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'              => esc_html__( 'Columns', 'piecyfer-core' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => 4,
				'tablet_default'     => 2,
				'mobile_default'     => 1,
				'min'                => 1,
				'max'                => 24,
				'condition'          => array(
					'gallery_layout!' => 'justified',
				),
				// The layout is computed in JS, so nothing here needs Elementor to
				// re-emit CSS — but the value must still reach the script.
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		/*
		 * Pro seeds a per-breakpoint default for every active breakpoint except
		 * widescreen, because a row height or gap of 0 on tablet/mobile would
		 * collapse the gallery. The set of breakpoints is site configuration, so
		 * this has to be built at runtime rather than written out.
		 */
		$active_breakpoints           = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();
		$ideal_row_height_device_args = array();
		$gap_device_args              = array();

		foreach ( $active_breakpoints as $breakpoint_name => $breakpoint_instance ) {
			if ( 'widescreen' !== $breakpoint_name ) {
				$ideal_row_height_device_args[ $breakpoint_name ] = array(
					'default' => array(
						'size' => 150,
					),
				);

				$gap_device_args[ $breakpoint_name ] = array(
					'default' => array(
						'size' => 10,
					),
				);
			}
		}

		$this->add_responsive_control(
			'ideal_row_height',
			array(
				'label'              => esc_html__( 'Row Height', 'piecyfer-core' ),
				'type'               => Controls_Manager::SLIDER,
				'range'              => array(
					'px' => array(
						'min' => 50,
						'max' => 500,
					),
				),
				'default'            => array(
					'size' => 200,
				),
				'device_args'        => $ideal_row_height_device_args,
				'condition'          => array(
					'gallery_layout' => 'justified',
				),
				'required'           => true,
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'              => esc_html__( 'Spacing', 'piecyfer-core' ),
				'type'               => Controls_Manager::SLIDER,
				'default'            => array(
					'size' => 10,
				),
				'device_args'        => $gap_device_args,
				'required'           => true,
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'link_to',
			array(
				'label'              => esc_html__( 'Link', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'file',
				'options'            => array(
					''       => esc_html__( 'None', 'piecyfer-core' ),
					'file'   => esc_html__( 'Media File', 'piecyfer-core' ),
					'custom' => esc_html__( 'Custom URL', 'piecyfer-core' ),
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'url',
			array(
				'label'              => esc_html__( 'URL', 'piecyfer-core' ),
				'type'               => Controls_Manager::URL,
				'condition'          => array(
					'link_to' => 'custom',
				),
				'frontend_available' => true,
				'dynamic'            => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'open_lightbox',
			array(
				'label'       => esc_html__( 'Lightbox', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT,
				'description' => sprintf(
					/* translators: 1: Link open tag, 2: Link close tag. */
					esc_html__( 'Manage your site’s lightbox settings in the %1$sLightbox panel%2$s.', 'piecyfer-core' ),
					'<a href="javascript: $e.run( \'panel/global/open\' ).then( () => $e.route( \'panel/global/settings-lightbox\' ) )">',
					'</a>'
				),
				'default'     => 'default',
				'options'     => array(
					'default' => esc_html__( 'Default', 'piecyfer-core' ),
					'yes'     => esc_html__( 'Yes', 'piecyfer-core' ),
					'no'      => esc_html__( 'No', 'piecyfer-core' ),
				),
				'condition'   => array(
					'link_to' => 'file',
				),
			)
		);

		$this->add_control(
			'aspect_ratio',
			array(
				'type'               => Controls_Manager::SELECT,
				'label'              => esc_html__( 'Aspect Ratio', 'piecyfer-core' ),
				'default'            => '3:2',
				'options'            => array(
					'1:1'  => '1:1',
					'3:2'  => '3:2',
					'4:3'  => '4:3',
					'9:16' => '9:16',
					'16:9' => '16:9',
					'21:9' => '21:9',
				),
				'condition'          => array(
					'gallery_layout' => 'grid',
				),
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'    => 'thumbnail_image',
				'default' => 'medium',
			)
		);

		$this->end_controls_section(); // settings
	}

	private function register_filter_bar_content_section(): void {
		$this->start_controls_section(
			'section_filter_bar_content',
			array(
				'label'     => esc_html__( 'Filter Bar', 'piecyfer-core' ),
				'condition' => array(
					'gallery_type' => 'multiple',
				),
			)
		);

		$this->add_control(
			'show_all_galleries',
			array(
				'type'               => Controls_Manager::SWITCHER,
				'label'              => esc_html__( '"All" Filter', 'piecyfer-core' ),
				'default'            => 'yes',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'show_all_galleries_label',
			array(
				'type'      => Controls_Manager::TEXT,
				'label'     => esc_html__( '"All" Filter Label', 'piecyfer-core' ),
				'default'   => esc_html__( 'All', 'piecyfer-core' ),
				'condition' => array(
					'show_all_galleries' => 'yes',
				),
				'dynamic'   => array(
					'active' => true,
				),
				'ai'        => array(
					'active' => false,
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
			)
		);

		/*
		 * Four mutually exclusive animation controls, one per pointer family.
		 * render() picks whichever one has a value by scanning for the first
		 * setting key starting with "animation", so their ids are load-bearing
		 * beyond simple round-tripping.
		 */
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
					'fade'                   => 'Fade',
					'grow'                   => 'Grow',
					'shrink'                 => 'Shrink',
					'sweep-left'             => 'Sweep Left',
					'sweep-right'            => 'Sweep Right',
					'sweep-up'               => 'Sweep Up',
					'sweep-down'             => 'Sweep Down',
					'shutter-in-vertical'    => 'Shutter In Vertical',
					'shutter-out-vertical'   => 'Shutter Out Vertical',
					'shutter-in-horizontal'  => 'Shutter In Horizontal',
					'shutter-out-horizontal' => 'Shutter Out Horizontal',
					'none'                   => 'None',
				),
				'condition' => array(
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
					'pointer' => 'text',
				),
			)
		);

		$this->end_controls_section(); // section_filter_bar_content
	}

	private function register_overlay_section(): void {
		$this->start_controls_section( 'overlay', array( 'label' => esc_html__( 'Overlay', 'piecyfer-core' ) ) );

		/*
		 * NOTE the id collision, which is Pro's and must be preserved: this
		 * switcher is `overlay_background`, and the Background group control in
		 * the Overlay *style* section is also named `overlay_background` (it
		 * generates overlay_background_background, overlay_background_color, …).
		 * They coexist because the group control never registers a bare control
		 * under its own name. Renaming either one orphans saved data — this site
		 * has overlay_background_background and overlay_background_color set.
		 */
		$this->add_control(
			'overlay_background',
			array(
				'label'              => esc_html__( 'Background', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'overlay_title',
			array(
				'label'              => esc_html__( 'Title', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => '',
				'options'            => array(
					''            => esc_html__( 'None', 'piecyfer-core' ),
					'title'       => esc_html__( 'Title', 'piecyfer-core' ),
					'caption'     => esc_html__( 'Caption', 'piecyfer-core' ),
					'alt'         => esc_html__( 'Alt', 'piecyfer-core' ),
					'description' => esc_html__( 'Description', 'piecyfer-core' ),
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'overlay_description',
			array(
				'label'              => esc_html__( 'Description', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => '',
				'options'            => array(
					''            => esc_html__( 'None', 'piecyfer-core' ),
					'title'       => esc_html__( 'Title', 'piecyfer-core' ),
					'caption'     => esc_html__( 'Caption', 'piecyfer-core' ),
					'alt'         => esc_html__( 'Alt', 'piecyfer-core' ),
					'description' => esc_html__( 'Description', 'piecyfer-core' ),
				),
				'frontend_available' => true,
			)
		);

		$this->end_controls_section(); // overlay
	}

	private function register_image_style_section(): void {
		$this->start_controls_section(
			'image_style',
			array(
				'label' => esc_html__( 'Image', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->start_controls_tabs( 'image_tabs' );

		$this->start_controls_tab(
			'image_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'image_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--image-border-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'image_border_width',
			array(
				'label'      => esc_html__( 'Border Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				// Pro writes the 'em' key twice here; the second literally
				// overwrites the first, so one entry is the same array.
				'range'      => array(
					'px' => array(
						'max' => 20,
					),
					'em' => array(
						'max' => 2,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--image-border-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'image_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--image-border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'image_css_filters',
				'selector' => '{{WRAPPER}} .e-gallery-image',
			)
		);

		$this->end_controls_tab(); // image_normal

		$this->start_controls_tab(
			'image_hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'image_border_color_hover',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-gallery-item:hover' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'image_border_radius_hover',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-gallery-item:hover' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'image_css_filters_hover',
				'selector' => '{{WRAPPER}} .e-gallery-item:hover .e-gallery-image',
			)
		);

		$this->end_controls_tab(); // image_hover

		$this->end_controls_tabs(); // image_tabs

		$this->add_control(
			'image_hover_animation',
			array(
				'label'              => esc_html__( 'Hover Animation', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					''                      => 'None',
					'grow'                  => 'Zoom In',
					'shrink-contained'      => 'Zoom Out',
					'move-contained-left'   => 'Move Left',
					'move-contained-right'  => 'Move Right',
					'move-contained-top'    => 'Move Up',
					'move-contained-bottom' => 'Move Down',
				),
				'separator'          => 'before',
				'default'            => '',
				'frontend_available' => true,
				'render_type'        => 'ui',
			)
		);

		$this->add_control(
			'image_animation_duration',
			array(
				'label'     => esc_html__( 'Animation Duration', 'piecyfer-core' ) . ' (ms)',
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => 800,
				),
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 3000,
						'step' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--image-transition-duration: {{SIZE}}ms',
				),
			)
		);

		$this->end_controls_section(); // image_style
	}

	private function register_overlay_style_section(): void {
		$this->start_controls_section(
			'overlay_style',
			array(
				'label'     => esc_html__( 'Overlay', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'overlay_background' => 'yes',
				),
			)
		);

		$this->start_controls_tabs( 'overlay_background_tabs' );

		$this->start_controls_tab(
			'overlay_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'overlay_background',
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => '{{WRAPPER}} .elementor-gallery-item__overlay',
				'fields_options' => array(
					'background' => array(
						'label' => esc_html__( 'Overlay', 'piecyfer-core' ),
					),
				),
			)
		);

		$this->end_controls_tab(); // overlay_normal

		$this->start_controls_tab(
			'overlay_hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'overlay_background_hover',
				'types'          => array( 'classic', 'gradient' ),
				'selector'       => '{{WRAPPER}} .e-gallery-item:hover .elementor-gallery-item__overlay, {{WRAPPER}} .e-gallery-item:focus .elementor-gallery-item__overlay',
				'exclude'        => array( 'image' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
					),
					'color'      => array(
						'default' => 'rgba(0,0,0,0.5)',
					),
				),
			)
		);

		$this->end_controls_tab(); // overlay_hover

		$this->end_controls_tabs(); // overlay_background_tabs

		$this->add_control(
			'image_blend_mode',
			array(
				'label'       => esc_html__( 'Blend Mode', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''            => esc_html__( 'Normal', 'piecyfer-core' ),
					'multiply'    => 'Multiply',
					'screen'      => 'Screen',
					'overlay'     => 'Overlay',
					'darken'      => 'Darken',
					'lighten'     => 'Lighten',
					'color-dodge' => 'Color Dodge',
					'color-burn'  => 'Color Burn',
					'hue'         => 'Hue',
					'saturation'  => 'Saturation',
					'color'       => 'Color',
					'exclusion'   => 'Exclusion',
					'luminosity'  => 'Luminosity',
				),
				'selectors'   => array(
					'{{WRAPPER}}' => '--overlay-mix-blend-mode: {{VALUE}}',
				),
				'separator'   => 'before',
				'render_type' => 'ui',
			)
		);

		$this->add_control(
			'background_overlay_hover_animation',
			array(
				'label'              => esc_html__( 'Hover Animation', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'groups'             => array(
					array(
						'label'   => esc_html__( 'None', 'piecyfer-core' ),
						'options' => array(
							'' => esc_html__( 'None', 'piecyfer-core' ),
						),
					),
					array(
						'label'   => esc_html__( 'Entrance', 'piecyfer-core' ),
						'options' => array(
							'enter-from-right'  => 'Slide In Right',
							'enter-from-left'   => 'Slide In Left',
							'enter-from-top'    => 'Slide In Up',
							'enter-from-bottom' => 'Slide In Down',
							'enter-zoom-in'     => 'Zoom In',
							'enter-zoom-out'    => 'Zoom Out',
							'fade-in'           => 'Fade In',
						),
					),
					array(
						'label'   => esc_html__( 'Exit', 'piecyfer-core' ),
						'options' => array(
							'exit-to-right'  => 'Slide Out Right',
							'exit-to-left'   => 'Slide Out Left',
							'exit-to-top'    => 'Slide Out Up',
							'exit-to-bottom' => 'Slide Out Down',
							'exit-zoom-in'   => 'Zoom In',
							'exit-zoom-out'  => 'Zoom Out',
							'fade-out'       => 'Fade Out',
						),
					),
				),
				'separator'          => 'before',
				'default'            => '',
				'frontend_available' => true,
				'render_type'        => 'ui',
			)
		);

		$this->add_control(
			'background_overlay_animation_duration',
			array(
				'label'     => esc_html__( 'Animation Duration', 'piecyfer-core' ) . ' (ms)',
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => 800,
				),
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 3000,
						'step' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--overlay-transition-duration: {{SIZE}}ms',
				),
			)
		);

		$this->end_controls_section(); // overlay_style
	}

	private function register_overlay_content_style_section(): void {
		$this->start_controls_section(
			'overlay_content_style',
			array(
				'label' => esc_html__( 'Content', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
				// Pro leaves this section unconditioned (with a TODO to add
				// conditions). Adding them would strip the controls below from
				// get_active_settings() and drop their generated CSS.
			)
		);

		$this->add_control(
			'content_alignment',
			array(
				'label'     => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}}' => '--content-text-align: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'content_vertical_position',
			array(
				'label'                => esc_html__( 'Vertical Position', 'piecyfer-core' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'top'    => array(
						'title' => esc_html__( 'Top', 'piecyfer-core' ),
						'icon'  => 'eicon-v-align-top',
					),
					'middle' => array(
						'title' => esc_html__( 'Middle', 'piecyfer-core' ),
						'icon'  => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => esc_html__( 'Bottom', 'piecyfer-core' ),
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				'selectors_dictionary' => array(
					'top'    => 'flex-start',
					'middle' => 'center',
					'bottom' => 'flex-end',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '--content-justify-content: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'content_padding',
			array(
				'label'      => esc_html__( 'Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array(
					'size' => 20,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--content-padding: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'heading_title',
			array(
				'label'     => esc_html__( 'Title', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'overlay_title!' => '',
				),
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--title-text-color: {{VALUE}}',
				),
				'condition' => array(
					'overlay_title!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'title_typography',
				'global'    => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector'  => '{{WRAPPER}} .elementor-gallery-item__title',
				'condition' => array(
					'overlay_title!' => '',
				),
			)
		);

		$this->add_control(
			'title_spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--description-margin-top: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'overlay_title!' => '',
				),
			)
		);

		$this->add_control(
			'heading_description',
			array(
				'label'     => esc_html__( 'Description', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'overlay_description!' => '',
				),
			)
		);

		$this->add_control(
			'description_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--description-text-color: {{VALUE}}',
				),
				'condition' => array(
					'overlay_description!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'description_typography',
				'global'    => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector'  => '{{WRAPPER}} .elementor-gallery-item__description',
				'condition' => array(
					'overlay_description!' => '',
				),
			)
		);

		$this->add_control(
			'content_hover_animation',
			array(
				'label'              => esc_html__( 'Hover Animation', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'groups'             => array(
					array(
						'label'   => esc_html__( 'None', 'piecyfer-core' ),
						'options' => array(
							'' => esc_html__( 'None', 'piecyfer-core' ),
						),
					),
					array(
						'label'   => esc_html__( 'Entrance', 'piecyfer-core' ),
						'options' => array(
							'enter-from-right'  => 'Slide In Right',
							'enter-from-left'   => 'Slide In Left',
							'enter-from-top'    => 'Slide In Up',
							'enter-from-bottom' => 'Slide In Down',
							'enter-zoom-in'     => 'Zoom In',
							'enter-zoom-out'    => 'Zoom Out',
							'fade-in'           => 'Fade In',
						),
					),
					array(
						'label'   => esc_html__( 'Reaction', 'piecyfer-core' ),
						'options' => array(
							'grow'       => 'Grow',
							'shrink'     => 'Shrink',
							'move-right' => 'Move Right',
							'move-left'  => 'Move Left',
							'move-up'    => 'Move Up',
							'move-down'  => 'Move Down',
						),
					),
					array(
						'label'   => esc_html__( 'Exit', 'piecyfer-core' ),
						'options' => array(
							'exit-to-right'  => 'Slide Out Right',
							'exit-to-left'   => 'Slide Out Left',
							'exit-to-top'    => 'Slide Out Up',
							'exit-to-bottom' => 'Slide Out Down',
							'exit-zoom-in'   => 'Zoom In',
							'exit-zoom-out'  => 'Zoom Out',
							'fade-out'       => 'Fade Out',
						),
					),
				),
				'default'            => 'fade-in',
				'separator'          => 'before',
				'render_type'        => 'ui',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'content_animation_duration',
			array(
				'label'     => esc_html__( 'Animation Duration', 'piecyfer-core' ) . ' (ms)',
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => 800,
				),
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 3000,
						'step' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--content-transition-duration: {{SIZE}}ms; --content-transition-delay: {{SIZE}}ms;',
				),
				'condition' => array(
					'content_hover_animation!' => '',
				),
			)
		);

		$this->add_control(
			'content_sequenced_animation',
			array(
				'label'              => esc_html__( 'Sequenced Animation', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'condition'          => array(
					'content_hover_animation!' => '',
				),
				'frontend_available' => true,
				'render_type'        => 'ui',
			)
		);

		$this->end_controls_section(); // overlay_content_style
	}

	private function register_filter_bar_style_section(): void {
		$this->start_controls_section(
			'filter_bar_style',
			array(
				'label'     => esc_html__( 'Filter Bar', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'gallery_type' => 'multiple',
				),
			)
		);

		$this->add_control(
			'align_filter_bar_items',
			array(
				'label'                => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				// Not responsive in Pro, so no '%s' placeholder here — writing one
				// would change the class name for every saved instance.
				'prefix_class'         => 'elementor-gallery--filter-align-',
				'selectors_dictionary' => array(
					'left'  => 'flex-start',
					'right' => 'flex-end',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '--titles-container-justify-content: {{VALUE}}',
				),
			)
		);

		$this->start_controls_tabs( 'filter_bar_colors' );

		$this->start_controls_tab(
			'filter_bar_colors_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'galleries_title_color_normal',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--galleries-title-color-normal: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'galleries_titles_typography',
				'selector' => '{{WRAPPER}} .elementor-gallery-title',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
			)
		);

		$this->end_controls_tab(); // filter_bar_colors_normal

		$this->start_controls_tab(
			'filter_bar_colors_hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'galleries_title_color_hover',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--galleries-title-color-hover: {{VALUE}}',
				),
				'condition' => array(
					'pointer!' => 'background',
				),
			)
		);

		/*
		 * When the pointer style = background, users could need a different text
		 * color. The control handles the title color in hover state, only when
		 * the pointer style is background. Both controls write the same CSS
		 * variable, which is why they are mutually exclusive rather than merged.
		 */
		$this->add_control(
			'galleries_title_color_hover_pointer_bg',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => array(
					'{{WRAPPER}}' => '--galleries-title-color-hover: {{VALUE}}',
				),
				'condition' => array(
					'pointer' => 'background',
				),
			)
		);

		$this->add_control(
			'galleries_pointer_color_hover',
			array(
				'label'     => esc_html__( 'Pointer Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--galleries-pointer-bg-color-hover: {{VALUE}}',
				),
				'condition' => array(
					'pointer!' => array( 'none', 'text' ),
				),
			)
		);

		$this->end_controls_tab(); // filter_bar_colors_hover

		$this->start_controls_tab(
			'filter_bar_colors_active',
			array(
				'label' => esc_html__( 'Active', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'galleries_title_color_active',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--gallery-title-color-active: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'galleries_pointer_color_active',
			array(
				'label'     => esc_html__( 'Pointer Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--galleries-pointer-bg-color-active: {{VALUE}}',
				),
				'condition' => array(
					'pointer!' => array( 'none', 'text' ),
				),
			)
		);

		$this->end_controls_tab(); // filter_bar_colors_active

		$this->end_controls_tabs(); // filter_bar_colors

		$this->add_control(
			'pointer_width',
			array(
				'label'      => esc_html__( 'Pointer Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				// Desktop and tablet only — Pro does not emit a mobile variant, so
				// registering one would create a control id that Pro never had.
				'devices'    => array( Breakpoints_Manager::BREAKPOINT_KEY_DESKTOP, Breakpoints_Manager::BREAKPOINT_KEY_TABLET ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px' => array(
						'max' => 30,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--galleries-pointer-border-width: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
				'condition'  => array(
					'pointer' => array( 'underline', 'overline', 'double-line', 'framed' ),
				),
			)
		);

		$this->add_control(
			'galleries_titles_space_between',
			array(
				'label'      => esc_html__( 'Space Between', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-gallery-title' => '--space-between: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'galleries_titles_gap',
			array(
				'label'      => esc_html__( 'Gap', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-gallery__titles-container' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section(); // filter_bar_style
	}

	// ----------------------------------------------------------------- render

	/**
	 * Static render mode (no JS): the e-gallery script never runs, so the layout
	 * variables it would normally compute are written as inline styles instead,
	 * and each image gets a background-image rather than waiting for lazyload.
	 *
	 * Elementor calls this instead of render() only when
	 * `is_static_render_mode()` is on — think static-HTML export and the
	 * screenshot service. It then delegates to the normal template.
	 */
	protected function render_static(): void {
		$settings = $this->get_settings_for_display();

		$is_multiple = 'multiple' === $settings['gallery_type'] && ! empty( $settings['galleries'] );

		$is_single = 'single' === $settings['gallery_type'] && ! empty( $settings['gallery'] );

		$gap              = $settings['gap']['size'] . $settings['gap']['unit'];
		$ratio_percentage = '75';
		$columns          = 4;

		if ( $settings['columns'] ) {
			$columns = $settings['columns'];
		}

		if ( $settings['aspect_ratio'] ) {
			$ratio_array = explode( ':', $settings['aspect_ratio'] );

			$ratio_percentage = ( $ratio_array[1] / $ratio_array[0] ) * 100;
		}

		$this->add_render_attribute(
			'gallery_container',
			array(
				'style' => "--columns: {$columns}; --aspect-ratio: {$ratio_percentage}%; --hgap: {$gap}; --vgap: {$gap};",
				'class' => 'e-gallery-grid',
			)
		);

		$galleries = array();

		if ( $is_multiple ) {
			foreach ( array_values( $settings['galleries'] ) as $multi_gallery ) {
				if ( ! $multi_gallery['multiple_gallery'] ) {
					continue;
				}

				$galleries[] = $multi_gallery['multiple_gallery'];
			}
		} elseif ( $is_single ) {
			$galleries[0] = $settings['gallery'];
		}

		foreach ( $galleries as $gallery ) {
			foreach ( $gallery as $item ) {
				$image_src = wp_get_attachment_image_src( $item['id'] );

				$this->add_render_attribute(
					'gallery_item_image_' . $item['id'],
					array(
						'style' => "background-image: url('{$image_src[0]}');",
					)
				);
			}
		}

		$this->render();
	}

	/**
	 * Transcribed from Pro line for line, whitespace included: every literal
	 * character between `?>` and `<?php` below is output, so re-indenting this
	 * method changes the rendered HTML.
	 */
	protected function render_widget(): void {
		$settings = $this->get_settings_for_display();

		$is_multiple = 'multiple' === $settings['gallery_type'] && ! empty( $settings['galleries'] );

		$is_single = 'single' === $settings['gallery_type'] && ! empty( $settings['gallery'] );

		$has_description = ! empty( $settings['overlay_description'] );

		$has_title = ! empty( $settings['overlay_title'] );

		$has_animation = ! empty( $settings['image_hover_animation'] ) || ! empty( $settings['content_hover_animation'] ) || ! empty( $settings['background_overlay_hover_animation'] );

		$gallery_item_tag = ! empty( $settings['link_to'] ) ? 'a' : 'div';

		$galleries = array();

		if ( $is_multiple ) {
			$this->add_render_attribute(
				'titles-container',
				array(
					'class' => 'elementor-gallery__titles-container',
					'aria-label' => esc_html__( 'Gallery filter', 'piecyfer-core' ),
				)
			);

			if ( $settings['pointer'] ) {
				$this->add_render_attribute( 'titles-container', 'class', 'e--pointer-' . $settings['pointer'] );

				// The four animation_* controls are mutually exclusive by
				// condition, so the first non-empty one is the active pointer's.
				foreach ( $settings as $key => $value ) {
					if ( 0 === strpos( $key, 'animation' ) && $value ) {
						$this->add_render_attribute( 'titles-container', 'class', 'e--animation-' . $value );
						break;
					}
				}
			} ?>
			<div <?php $this->print_render_attribute_string( 'titles-container' ); ?>>
				<?php if ( $settings['show_all_galleries'] ) { ?>
					<a class="elementor-item elementor-gallery-title" role="button" tabindex="0" data-gallery-index="all">
						<?php $this->print_unescaped_setting( 'show_all_galleries_label' ); ?>
					</a>
				<?php } ?>

				<?php foreach ( $settings['galleries'] as $index => $gallery ) :
					if ( ! $gallery['multiple_gallery'] ) {
						continue;
					}

					$galleries[ $index ] = $gallery['multiple_gallery'];
					?>
					<a class="elementor-item elementor-gallery-title" role="button" tabindex="0" data-gallery-index="<?php echo esc_attr( $index ); ?>">
						<?php $this->print_unescaped_setting( 'gallery_title', 'galleries', $index ); ?>
					</a>
					<?php
				endforeach; ?>
			</div>
			<?php
		} elseif ( $is_single ) {
			$galleries[0] = $settings['gallery'];
		} elseif ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) { ?>
			<i class="elementor-widget-empty-icon eicon-gallery-justified"></i>
		<?php }

		$this->add_render_attribute( 'gallery_container', 'class', 'elementor-gallery__container' );

		if ( $has_title || $has_description ) {
			$this->add_render_attribute( 'gallery_item_content', 'class', 'elementor-gallery-item__content' );

			if ( $has_title ) {
				$this->add_render_attribute( 'gallery_item_title', 'class', 'elementor-gallery-item__title' );
			}

			if ( $has_description ) {
				$this->add_render_attribute( 'gallery_item_description', 'class', 'elementor-gallery-item__description' );
			}
		}

		$this->add_render_attribute( 'gallery_item_background_overlay', array( 'class' => 'elementor-gallery-item__overlay' ) );

		// Keyed by attachment id, so an image that appears in several galleries
		// is rendered once and carries every tag it belongs to.
		$gallery_items = array();
		$thumbnail_size = $settings['thumbnail_image_size'];
		foreach ( $galleries as $gallery_index => $gallery ) {
			foreach ( $gallery as $index => $item ) {
				if ( in_array( $item['id'], array_keys( $gallery_items ), true ) ) {
					$gallery_items[ $item['id'] ][] = $gallery_index;
				} else {
					$gallery_items[ $item['id'] ] = array( $gallery_index );
				}
			}
		}

		if ( 'random' === $settings['order_by'] ) {
			$shuffled_items = array();
			$keys = array_keys( $gallery_items );
			shuffle( $keys );
			foreach ( $keys as $key ) {
				$shuffled_items[ $key ] = $gallery_items[ $key ];
			}
			$gallery_items = $shuffled_items;
		}

		if ( ! empty( $galleries ) ) { ?>
		<div <?php $this->print_render_attribute_string( 'gallery_container' ); ?>>
			<?php
			foreach ( $gallery_items as $id => $tags ) :
				$unique_index = $id; //$gallery_index . '_' . $index;
				$image_src = wp_get_attachment_image_src( $id, $thumbnail_size );
				if ( ! $image_src ) {
					continue;
				}
				$attachment = get_post( $id );
				$image_data = array(
					'alt' => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
					'media' => wp_get_attachment_image_src( $id, 'full' )['0'],
					'src' => $image_src['0'],
					'width' => $image_src['1'],
					'height' => $image_src['2'],
					'caption' => $attachment->post_excerpt,
					'description' => $attachment->post_content,
					'title' => $attachment->post_title,
				);

				$this->add_render_attribute( 'gallery_item_' . $unique_index, array(
					'class' => array(
						'e-gallery-item',
						'elementor-gallery-item',
					),
				) );

				if ( $has_animation ) {
					$this->add_render_attribute( 'gallery_item_' . $unique_index, array( 'class' => 'elementor-animated-content' ) );
				}

				if ( $is_multiple ) {
					$this->add_render_attribute( 'gallery_item_' . $unique_index, array( 'data-e-gallery-tags' => implode( ',', $tags ) ) );
				}

				if ( $has_title && 'div' === $gallery_item_tag ) {
					$this->add_render_attribute( 'gallery_item_' . $unique_index, array( 'tabindex' => '0' ) );
				}

				if ( 'a' === $gallery_item_tag ) {
					if ( 'file' === $settings['link_to'] ) {
						$href = $image_data['media'];

						$this->add_render_attribute( 'gallery_item_' . $unique_index, array(
							'href' => esc_url( $href ),
						) );

						if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
							$this->add_render_attribute( 'gallery_item_' . $unique_index, 'class', 'elementor-clickable' );
						}

						$this->add_lightbox_data_attributes( 'gallery_item_' . $unique_index, $id, $settings['open_lightbox'], $this->get_id() );
					} elseif ( 'custom' === $settings['link_to'] ) {
						$this->add_link_attributes( 'gallery_item_' . $unique_index, $settings['url'] );
					}
				}

				$this->add_render_attribute( 'gallery_item_image_' . $unique_index,
					array(
						'class' => array(
							'e-gallery-image',
							'elementor-gallery-item__image',
						),
						'data-thumbnail' => $image_data['src'],
						'data-width' => $image_data['width'],
						'data-height' => $image_data['height'],
						'aria-label' => $image_data['alt'],
						'role' => 'img',
					)
				);?>
				<<?php Utils::print_validated_html_tag( $gallery_item_tag ); ?> <?php $this->print_render_attribute_string( 'gallery_item_' . $unique_index ); ?>>
					<div <?php $this->print_render_attribute_string( 'gallery_item_image_' . $unique_index ); ?> ></div>
					<?php if ( ! empty( $settings['overlay_background'] ) ) : ?>
						<div <?php $this->print_render_attribute_string( 'gallery_item_background_overlay' ); ?>></div>
					<?php endif; ?>
					<?php if ( $has_title || $has_description ) : ?>
					<div <?php $this->print_render_attribute_string( 'gallery_item_content' ); ?>>
						<?php if ( $has_title ) :
							$title = $image_data[ $settings['overlay_title'] ];
							if ( ! empty( $title ) ) : ?>
								<div <?php $this->print_render_attribute_string( 'gallery_item_title' ); ?>>
									<?php // PHPCS - the main text of a widget should not be escaped. ?>
									<?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							<?php endif;
						endif;
						if ( $has_description ) :
							$description = $image_data[ $settings['overlay_description'] ];
							if ( ! empty( $description ) ) :?>
								<div <?php $this->print_render_attribute_string( 'gallery_item_description' ); ?>>
									<?php // PHPCS - the main text of a widget should not be escaped. ?>
									<?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							<?php endif;
						endif; ?>
					</div>
					<?php endif; ?>
				</<?php Utils::print_validated_html_tag( $gallery_item_tag ); ?>>
			<?php endforeach;
			//endforeach; ?>
		</div>
	<?php }
	}
}
