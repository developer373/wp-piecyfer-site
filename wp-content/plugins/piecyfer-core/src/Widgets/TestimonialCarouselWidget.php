<?php
/**
 * Replacement for Elementor Pro's `testimonial-carousel` widget.
 *
 * 2 instances, both on the Home page: one carries three `[elementor-template]`
 * shortcodes as slide content, the other ten real testimonials. Between them 77
 * setting keys are populated, 19 of which are VamTam's (`vamtam_nav_*`,
 * `arrows_hover_color`) and are registered by
 * `vamtam-elementor-integration-tecnologia` at
 * `elementor/element/testimonial-carousel/section_navigation/before_section_end`.
 * That hook is the reason `section_navigation` must keep its exact id — rename
 * it and 19 saved values lose their controls on the next save.
 *
 * Pro splits this widget across two classes: `Modules\Carousel\Widgets\Base`
 * (slides repeater, swiper options, slide + navigation styling) and
 * `Testimonial_Carousel` (skin, layout, alignment, content and image styling).
 * There is only one subclass worth having here, so the two are flattened into
 * this file — but in Pro's order, and with Pro's injections and removals
 * reproduced rather than pre-resolved. `effect`, `height` and
 * `pagination_position` are therefore registered and then removed exactly as Pro
 * does: the end state is identical, and the intermediate state is what the
 * `update_responsive_control()` calls below depend on.
 *
 * Two stylesheets replace Pro's: `widget-testimonial-carousel` becomes
 * assets/css/testimonial-carousel.css and `widget-carousel-module-base` becomes
 * assets/css/carousel-module-base.css. `e-swiper` is Elementor FREE's and stays.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

class TestimonialCarouselWidget extends AbstractWidget {

	/**
	 * Pro's Base keeps this counter so that two carousels on one page cannot
	 * generate the same render-attribute keys. Ours must too: the keys end up in
	 * `id`-less attribute buckets that are global to the widget instance, and a
	 * collision would make the second carousel reuse the first one's `<img>`
	 * attributes.
	 */
	private int $slide_prints_count = 0;

	public function get_name(): string {
		return 'testimonial-carousel';
	}

	public function get_title(): string {
		return esc_html__( 'Testimonial Carousel', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-testimonial-carousel';
	}

	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	public function get_keywords(): array {
		return array( 'testimonial', 'carousel', 'image' );
	}

	/**
	 * Pro's Testimonial_Carousel declares this; it groups the widget with the
	 * other carousels for the editor's widget-CSS loading.
	 */
	public function get_group_name(): string {
		return 'carousel';
	}

	protected function replaces(): string {
		return 'Elementor Pro — Carousel/Testimonial_Carousel';
	}

	/**
	 * Declared by Pro's carousel Base; `Element_Base` defaults it to **true**.
	 *
	 * It decides whether the element is baked into the document's element cache
	 * or re-rendered on every request behind an `[elementor-element]` placeholder.
	 * Not overriding it silently changes the caching behaviour of the widget we
	 * are replacing — a difference no pixel comparison would ever show.
	 */
	protected function is_dynamic_content(): bool {
		return false;
	}

	public function get_html_wrapper_class() {
		$classes  = parent::get_html_wrapper_class();
		$settings = $this->get_settings_for_display();

		$classes .= ' vamtam-has-theme-widget-styles';

		if ( ! empty( $settings['vamtam_nav_pos'] ) ) {
			$classes .= ' vamtam-nav-pos-' . sanitize_html_class( (string) $settings['vamtam_nav_pos'] );
		}
		if ( ! empty( $settings['vamtam_nav_pos_tablet'] ) ) {
			$classes .= ' vamtam-nav-pos-tablet-' . sanitize_html_class( (string) $settings['vamtam_nav_pos_tablet'] );
		}
		if ( ! empty( $settings['vamtam_nav_pos_mobile'] ) ) {
			$classes .= ' vamtam-nav-pos-mobile-' . sanitize_html_class( (string) $settings['vamtam_nav_pos_mobile'] );
		}

		return $classes;
	}

	/**
	 * `imagesloaded` comes from Pro's carousel Base. Swiper needs slide heights
	 * to settle before it measures them, and this is how Pro gets that.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'imagesloaded' );
	}

	/**
	 * `e-swiper` is Elementor FREE's and must be kept as-is; only Pro's two
	 * handles are replaced.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'e-swiper', 'piecyfer-testimonial-carousel', 'piecyfer-carousel-module-base' );
	}

	protected function assets( string $handle ): void {
		// Registered centrally so the handles match get_style_depends(), which
		// Elementor resolves before render_widget() runs.
	}

	// -------------------------------------------------------------- controls

	protected function register_controls(): void {
		$this->register_carousel_base_controls();
		$this->register_testimonial_controls();
	}

	/**
	 * Everything Pro's `Modules\Carousel\Widgets\Base::register_controls()`
	 * registers, in its order. Order matters here beyond cosmetics: the
	 * injections in register_testimonial_controls() address `slides` and
	 * `section_navigation` by id and would land in the wrong place — or throw —
	 * if these were registered later.
	 */
	private function register_carousel_base_controls(): void {
		$this->start_controls_section(
			'section_slides',
			array(
				'label' => esc_html__( 'Slides', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$repeater = new Repeater();

		$this->add_repeater_controls( $repeater );

		$this->add_control(
			'slides',
			array(
				'label'     => esc_html__( 'Slides', 'piecyfer-core' ),
				'type'      => Controls_Manager::REPEATER,
				'fields'    => $repeater->get_controls(),
				'default'   => $this->get_repeater_defaults(),
				'separator' => 'after',
			)
		);

		/*
		 * Registered even though the widget removes it again at the end of
		 * register_controls(). Between here and there, `slides_per_view` and
		 * `slides_to_scroll` are conditioned on it, and their conditions are
		 * cleared by update_responsive_control() rather than never written —
		 * which is what keeps the saved `slides_per_view: "3"` alive.
		 */
		$this->add_control(
			'effect',
			array(
				'type'               => Controls_Manager::SELECT,
				'label'              => esc_html__( 'Effect', 'piecyfer-core' ),
				'default'            => 'slide',
				'options'            => array(
					'slide' => esc_html__( 'Slide', 'piecyfer-core' ),
					'fade'  => esc_html__( 'Fade', 'piecyfer-core' ),
					'cube'  => esc_html__( 'Cube', 'piecyfer-core' ),
				),
				'frontend_available' => true,
			)
		);

		$slides_per_view = range( 1, 10 );
		$slides_per_view = array_combine( $slides_per_view, $slides_per_view );

		$this->add_responsive_control(
			'slides_per_view',
			array(
				'type'                 => Controls_Manager::SELECT,
				'label'                => esc_html__( 'Slides Per View', 'piecyfer-core' ),
				'options'              => array( '' => esc_html__( 'Default', 'piecyfer-core' ) ) + $slides_per_view,
				'inherit_placeholders' => false,
				'condition'            => array(
					'effect' => 'slide',
				),
				'frontend_available'   => true,
			)
		);

		$this->add_responsive_control(
			'slides_to_scroll',
			array(
				'type'                 => Controls_Manager::SELECT,
				'label'                => esc_html__( 'Slides to Scroll', 'piecyfer-core' ),
				'description'          => esc_html__( 'Set how many slides are scrolled per swipe.', 'piecyfer-core' ),
				'options'              => array( '' => esc_html__( 'Default', 'piecyfer-core' ) ) + $slides_per_view,
				'inherit_placeholders' => false,
				'condition'            => array(
					'effect' => 'slide',
				),
				'frontend_available'   => true,
			)
		);

		$this->add_responsive_control(
			'height',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_html__( 'Height', 'piecyfer-core' ),
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
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
						'min' => 20,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-main-swiper' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'width',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_html__( 'Width', 'piecyfer-core' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px' => array(
						'min' => 100,
						'max' => 1140,
					),
					'%'  => array(
						'min' => 50,
					),
				),
				'default'    => array(
					'unit' => '%',
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-main-swiper' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_additional_options',
			array(
				'label' => esc_html__( 'Additional Options', 'piecyfer-core' ),
			)
		);

		/*
		 * Every `frontend_available` below is load-bearing. Elementor collects
		 * those controls into the widget's `data-settings` attribute, which is
		 * the only channel the Swiper handler has for its options. A missing one
		 * does not error — the carousel just quietly ignores the setting.
		 */
		$this->add_control(
			'show_arrows',
			array(
				'type'               => Controls_Manager::SWITCHER,
				'label'              => esc_html__( 'Arrows', 'piecyfer-core' ),
				'default'            => 'yes',
				'label_off'          => esc_html__( 'Hide', 'piecyfer-core' ),
				'label_on'           => esc_html__( 'Show', 'piecyfer-core' ),
				'prefix_class'       => 'elementor-arrows-',
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pagination',
			array(
				'label'              => esc_html__( 'Pagination', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'bullets',
				'options'            => array(
					''            => esc_html__( 'None', 'piecyfer-core' ),
					'bullets'     => esc_html__( 'Dots', 'piecyfer-core' ),
					'fraction'    => esc_html__( 'Fraction', 'piecyfer-core' ),
					'progressbar' => esc_html__( 'Progress', 'piecyfer-core' ),
				),
				'prefix_class'       => 'elementor-pagination-type-',
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'speed',
			array(
				'label'              => esc_html__( 'Transition Duration', 'piecyfer-core' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => 500,
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'              => esc_html__( 'Autoplay', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'separator'          => 'before',
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'autoplay_speed',
			array(
				'label'              => esc_html__( 'Autoplay Speed', 'piecyfer-core' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => 5000,
				'condition'          => array(
					'autoplay' => 'yes',
				),
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'loop',
			array(
				'label'              => esc_html__( 'Infinite Loop', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pause_on_hover',
			array(
				'label'              => esc_html__( 'Pause on Hover', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'condition'          => array(
					'autoplay' => 'yes',
				),
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pause_on_interaction',
			array(
				'label'              => esc_html__( 'Pause on Interaction', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'condition'          => array(
					'autoplay' => 'yes',
				),
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		/*
		 * Group name `image_size`, which prefixes its fields to
		 * `image_size_size` / `image_size_custom_dimension`. It does not collide
		 * with the slider control literally named `image_size` in
		 * section_image_style — Pro has both, and instance #2 populates the
		 * slider.
		 */
		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'      => 'image_size',
				'default'   => 'full',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'lazyload',
			array(
				'label'              => esc_html__( 'Lazyload', 'piecyfer-core' ),
				'type'               => Controls_Manager::SWITCHER,
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_slides_style',
			array(
				'label' => esc_html__( 'Slides', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$space_between_config = array(
			'label'              => esc_html__( 'Space Between', 'piecyfer-core' ),
			'type'               => Controls_Manager::SLIDER,
			'range'              => array(
				'px' => array(
					'max' => 50,
				),
			),
			'render_type'        => 'none',
			'frontend_available' => true,
		);

		/*
		 * `space_between` has no plain `default`; it has one *per device*, and
		 * the device list is whatever the kit has active. Hard-coding
		 * desktop/tablet/mobile would silently drop the 10px default on any
		 * breakpoint someone enables later.
		 */
		$active_breakpoint_instances = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();
		// Devices need to be ordered from largest to smallest.
		$active_devices = array_reverse( array_keys( $active_breakpoint_instances ) );

		// Add desktop in the correct position.
		if ( in_array( 'widescreen', $active_devices, true ) ) {
			$active_devices = array_merge( array_slice( $active_devices, 0, 1 ), array( 'desktop' ), array_slice( $active_devices, 1 ) );
		} else {
			$active_devices = array_merge( array( 'desktop' ), $active_devices );
		}

		foreach ( $active_devices as $active_device ) {
			$space_between_config[ $active_device . '_default' ] = array(
				'size' => 10,
			);
		}

		$this->add_responsive_control(
			'space_between',
			$space_between_config
		);

		$this->add_control(
			'slide_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-main-swiper .swiper-slide' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'slide_border_size',
			array(
				'label'      => esc_html__( 'Border Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-main-swiper .swiper-slide' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'slide_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => array(
					'%' => array(
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-main-swiper .swiper-slide' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'slide_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-main-swiper .swiper-slide' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'slide_padding',
			array(
				'label'      => esc_html__( 'Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-main-swiper .swiper-slide' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			/*
			 * VamTam's testimonial-carousel integration hooks
			 * `elementor/element/testimonial-carousel/section_navigation/before_section_end`
			 * to remove `arrows_color`, re-add it inside a normal/hover tab pair,
			 * add `arrows_hover_color`, and add the nine `vamtam_nav_*` responsive
			 * controls. That is 19 of this site's 77 populated keys. The id, and
			 * the presence of `heading_arrows`, `arrows_size` and `arrows_color`
			 * inside it, are all part of that contract.
			 */
			'section_navigation',
			array(
				'label' => esc_html__( 'Navigation', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_arrows',
			array(
				'label' => esc_html__( 'Arrows', 'piecyfer-core' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_responsive_control(
			'arrows_size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'size' => 20,
				),
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
					'{{WRAPPER}} .elementor-swiper-button' => 'font-size: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'arrows_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-swiper-button'     => 'color: {{VALUE}}',
					'{{WRAPPER}} .elementor-swiper-button svg' => 'fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'heading_pagination',
			array(
				'label'     => esc_html__( 'Pagination', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => array(
					'pagination!' => '',
				),
			)
		);

		$this->add_control(
			'pagination_position',
			array(
				'label'        => esc_html__( 'Position', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'outside',
				'options'      => array(
					'outside' => esc_html__( 'Outside', 'piecyfer-core' ),
					'inside'  => esc_html__( 'Inside', 'piecyfer-core' ),
				),
				'prefix_class' => 'elementor-pagination-position-',
				'condition'    => array(
					'pagination!' => '',
				),
			)
		);

		$swiper_class = $this->get_swiper_class();

		$this->add_responsive_control(
			'pagination_size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
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
					'{{WRAPPER}} .swiper-pagination-bullet' => 'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .' . $swiper_class . '-horizontal .swiper-pagination-progressbar' => 'height: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .swiper-pagination-fraction' => 'font-size: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'pagination!' => '',
				),
			)
		);

		$this->add_control(
			'pagination_color_inactive',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					// The opacity property will override the default inactive dot color which is opacity 0.2.
					'{{WRAPPER}} .swiper-pagination-bullet:not(.swiper-pagination-bullet-active)' => 'background-color: {{VALUE}}; opacity: 1;',
				),
				'condition' => array(
					'pagination!' => '',
				),
			)
		);

		$this->add_control(
			'pagination_color',
			array(
				'label'     => esc_html__( 'Active Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .swiper-pagination-bullet-active, {{WRAPPER}} .swiper-pagination-progressbar-fill' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .swiper-pagination-fraction' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'pagination!' => '',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Everything Pro's `Testimonial_Carousel::register_controls()` adds on top,
	 * including its injections and its three removals.
	 */
	private function register_testimonial_controls(): void {
		$this->start_injection(
			array(
				'of' => 'slides',
			)
		);

		$this->add_control(
			'skin',
			array(
				'label'        => esc_html__( 'Skin', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'default',
				'options'      => array(
					'default' => esc_html__( 'Default', 'piecyfer-core' ),
					'bubble'  => esc_html__( 'Bubble', 'piecyfer-core' ),
				),
				'prefix_class' => 'elementor-testimonial--skin-',
				'render_type'  => 'template',
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'        => esc_html__( 'Layout', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'image_inline',
				'options'      => array(
					'image_inline'  => esc_html__( 'Image Inline', 'piecyfer-core' ),
					'image_stacked' => esc_html__( 'Image Stacked', 'piecyfer-core' ),
					'image_above'   => esc_html__( 'Image Above', 'piecyfer-core' ),
					'image_left'    => esc_html__( 'Image Left', 'piecyfer-core' ),
					'image_right'   => esc_html__( 'Image Right', 'piecyfer-core' ),
				),
				'prefix_class' => 'elementor-testimonial--layout-',
				'render_type'  => 'template',
			)
		);

		$this->add_responsive_control(
			'alignment',
			array(
				'label'        => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type'         => Controls_Manager::CHOOSE,
				'default'      => 'center',
				'options'      => array(
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
				// Elementor's responsive placeholder: `%s` becomes '' on desktop
				// and '-tablet' / '-mobile' elsewhere, giving
				// `elementor-testimonial--align-` and
				// `elementor-testimonial--tablet-align-`. A literal here would
				// silently drop every responsive variant.
				'prefix_class' => 'elementor-testimonial-%s-align-',
			)
		);

		$this->end_injection();

		$this->start_controls_section(
			'section_skin_style',
			array(
				'label'     => esc_html__( 'Bubble', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'skin' => 'bubble',
				),
			)
		);

		$this->add_control(
			'background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => false,
				'selectors' => array(
					'{{WRAPPER}} .elementor-testimonial__content, {{WRAPPER}} .elementor-testimonial__content:after' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'text_padding',
			array(
				'label'      => esc_html__( 'Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array(
					'top'    => '20',
					'bottom' => '20',
					'left'   => '20',
					'right'  => '20',
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-testimonial__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
					'{{WRAPPER}}.elementor-testimonial--layout-image_left .elementor-testimonial__footer,
					{{WRAPPER}}.elementor-testimonial--layout-image_right .elementor-testimonial__footer' => 'padding-top: {{TOP}}{{UNIT}}',
					'{{WRAPPER}}.elementor-testimonial--layout-image_above .elementor-testimonial__footer,
					{{WRAPPER}}.elementor-testimonial--layout-image_inline .elementor-testimonial__footer,
					{{WRAPPER}}.elementor-testimonial--layout-image_stacked .elementor-testimonial__footer' => 'padding: 0 {{RIGHT}}{{UNIT}} 0 {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-testimonial__content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'border',
			array(
				'label'     => esc_html__( 'Border', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'selectors' => array(
					'{{WRAPPER}} .elementor-testimonial__content, {{WRAPPER}} .elementor-testimonial__content:after' => 'border-style: solid',
				),
			)
		);

		$this->add_control(
			'border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#000',
				'selectors' => array(
					'{{WRAPPER}} .elementor-testimonial__content' => 'border-color: {{VALUE}}',
					'{{WRAPPER}} .elementor-testimonial__content:after' => 'border-color: transparent {{VALUE}} {{VALUE}} transparent',
				),
				'condition' => array(
					'border' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'border_width',
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
					'{{WRAPPER}} .elementor-testimonial__content, {{WRAPPER}} .elementor-testimonial__content:after' => 'border-width: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.elementor-testimonial--layout-image_stacked .elementor-testimonial__content:after,
					{{WRAPPER}}.elementor-testimonial--layout-image_inline .elementor-testimonial__content:after' => 'margin-top: -{{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.elementor-testimonial--layout-image_above .elementor-testimonial__content:after' => 'margin-bottom: -{{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'border' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		$this->start_injection(
			array(
				'at' => 'before',
				'of' => 'section_navigation',
			)
		);

		$this->start_controls_section(
			'section_content_style',
			array(
				'label' => esc_html__( 'Content', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'content_gap',
			array(
				'label'      => esc_html__( 'Gap', 'piecyfer-core' ),
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
					'{{WRAPPER}}.elementor-testimonial--layout-image_inline .elementor-testimonial__footer,
					{{WRAPPER}}.elementor-testimonial--layout-image_stacked .elementor-testimonial__footer' => 'margin-top: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.elementor-testimonial--layout-image_above .elementor-testimonial__footer' => 'margin-bottom: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.elementor-testimonial--layout-image_left .elementor-testimonial__footer' => 'padding-right: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.elementor-testimonial--layout-image_right .elementor-testimonial__footer' => 'padding-left: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'content_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-testimonial__text' => 'color: {{VALUE}}',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'content_typography',
				'selector' => '{{WRAPPER}} .elementor-testimonial__text',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name'     => 'text_stroke',
				'selector' => '{{WRAPPER}} .elementor-testimonial__text',
			)
		);

		$this->add_control(
			'name_title_style',
			array(
				'label'     => esc_html__( 'Name', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-testimonial__name' => 'color: {{VALUE}}',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'name_typography',
				'selector' => '{{WRAPPER}} .elementor-testimonial__name',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
			)
		);

		$this->add_control(
			'heading_title_style',
			array(
				'label'     => esc_html__( 'Title', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-testimonial__title' => 'color: {{VALUE}}',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .elementor-testimonial__title',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_image_style',
			array(
				'label' => esc_html__( 'Image', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		/*
		 * The bubble tail is positioned from `text_padding` *and* this size, via
		 * cross-control references (`{{text_padding.LEFT}}`). Those references
		 * only resolve while both controls exist, which is another reason the
		 * bubble section is registered unconditionally rather than skipped
		 * because this site uses the default skin.
		 */
		$this->add_responsive_control(
			'image_size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 200,
					),
					'em'  => array(
						'max' => 20,
					),
					'rem' => array(
						'max' => 20,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-testimonial__image img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.elementor-testimonial--layout-image_left .elementor-testimonial__content:after,
					 {{WRAPPER}}.elementor-testimonial--layout-image_right .elementor-testimonial__content:after' => 'top: calc( {{text_padding.TOP}}{{text_padding.UNIT}} + ({{SIZE}}{{UNIT}} / 2) - 8px );',

					'body:not(.rtl) {{WRAPPER}}.elementor-testimonial--layout-image_stacked:not(.elementor-testimonial--align-center):not(.elementor-testimonial--align-right) .elementor-testimonial__content:after,
					 body:not(.rtl) {{WRAPPER}}.elementor-testimonial--layout-image_inline:not(.elementor-testimonial--align-center):not(.elementor-testimonial--align-right) .elementor-testimonial__content:after,
					 {{WRAPPER}}.elementor-testimonial--layout-image_stacked.elementor-testimonial--align-left .elementor-testimonial__content:after,
					 {{WRAPPER}}.elementor-testimonial--layout-image_inline.elementor-testimonial--align-left .elementor-testimonial__content:after' => 'left: calc( {{text_padding.LEFT}}{{text_padding.UNIT}} + ({{SIZE}}{{UNIT}} / 2) - 8px ); right:auto;',

					'body.rtl {{WRAPPER}}.elementor-testimonial--layout-image_stacked:not(.elementor-testimonial--align-center):not(.elementor-testimonial--align-left) .elementor-testimonial__content:after,
					 body.rtl {{WRAPPER}}.elementor-testimonial--layout-image_inline:not(.elementor-testimonial--align-center):not(.elementor-testimonial--align-left) .elementor-testimonial__content:after,
					 {{WRAPPER}}.elementor-testimonial--layout-image_stacked.elementor-testimonial--align-right .elementor-testimonial__content:after,
					 {{WRAPPER}}.elementor-testimonial--layout-image_inline.elementor-testimonial--align-right .elementor-testimonial__content:after' => 'right: calc( {{text_padding.RIGHT}}{{text_padding.UNIT}} + ({{SIZE}}{{UNIT}} / 2) - 8px ); left:auto;',

					'body:not(.rtl) {{WRAPPER}}.elementor-testimonial--layout-image_above:not(.elementor-testimonial--align-center):not(.elementor-testimonial--align-right) .elementor-testimonial__content:after,
					 {{WRAPPER}}.elementor-testimonial--layout-image_above.elementor-testimonial--align-left .elementor-testimonial__content:after' => 'left: calc( {{text_padding.LEFT}}{{text_padding.UNIT}} + ({{SIZE}}{{UNIT}} / 2) - 8px ); right:auto;',

					'body.rtl {{WRAPPER}}.elementor-testimonial--layout-image_above:not(.elementor-testimonial--align-center):not(.elementor-testimonial--align-left) .elementor-testimonial__content:after,
					 {{WRAPPER}}.elementor-testimonial--layout-image_above.elementor-testimonial--align-right .elementor-testimonial__content:after' => 'right: calc( {{text_padding.RIGHT}}{{text_padding.UNIT}} + ({{SIZE}}{{UNIT}} / 2) - 8px ); left:auto;',
				),
			)
		);

		$this->add_responsive_control(
			'image_gap',
			array(
				'label'      => esc_html__( 'Gap', 'piecyfer-core' ),
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
					'body.rtl {{WRAPPER}}.elementor-testimonial--layout-image_inline.elementor-testimonial--align-left .elementor-testimonial__image + cite,
					 body.rtl {{WRAPPER}}.elementor-testimonial--layout-image_above.elementor-testimonial--align-left .elementor-testimonial__image + cite,
					 body:not(.rtl) {{WRAPPER}}.elementor-testimonial--layout-image_inline .elementor-testimonial__image + cite,
					 body:not(.rtl) {{WRAPPER}}.elementor-testimonial--layout-image_above .elementor-testimonial__image + cite' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: 0;',

					'body:not(.rtl) {{WRAPPER}}.elementor-testimonial--layout-image_inline.elementor-testimonial--align-right .elementor-testimonial__image + cite,
					 body:not(.rtl) {{WRAPPER}}.elementor-testimonial--layout-image_above.elementor-testimonial--align-right .elementor-testimonial__image + cite,
					 body.rtl {{WRAPPER}}.elementor-testimonial--layout-image_inline .elementor-testimonial__image + cite,
					 body.rtl {{WRAPPER}}.elementor-testimonial--layout-image_above .elementor-testimonial__image + cite' => 'margin-right: {{SIZE}}{{UNIT}}; margin-left:0;',

					'{{WRAPPER}}.elementor-testimonial--layout-image_stacked .elementor-testimonial__image + cite,
					 {{WRAPPER}}.elementor-testimonial--layout-image_left .elementor-testimonial__image + cite,
					 {{WRAPPER}}.elementor-testimonial--layout-image_right .elementor-testimonial__image + cite' => 'margin-top: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'image_border',
			array(
				'label'     => esc_html__( 'Border', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'selectors' => array(
					'{{WRAPPER}} .elementor-testimonial__image img' => 'border-style: solid',
				),
			)
		);

		$this->add_control(
			'image_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#000',
				'selectors' => array(
					'{{WRAPPER}} .elementor-testimonial__image img' => 'border-color: {{VALUE}}',
				),
				'condition' => array(
					'image_border' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'image_border_width',
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
					'{{WRAPPER}} .elementor-testimonial__image img' => 'border-width: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'image_border' => 'yes',
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
					'{{WRAPPER}} .elementor-testimonial__image img' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();

		$this->end_injection();

		/*
		 * `width` is narrowed by 40px when arrows are on, so the arrows sit in
		 * the gutter rather than over the slides. Instance #1 stores width 100%
		 * and instance #2 stores 105%, so this override is visible on both.
		 */
		$this->update_responsive_control(
			'width',
			array(
				'selectors' => array(
					'{{WRAPPER}}.elementor-arrows-yes .elementor-main-swiper' => 'width: calc( {{SIZE}}{{UNIT}} - 40px )',
					'{{WRAPPER}} .elementor-main-swiper' => 'width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		/*
		 * Both conditions point at `effect`, which is removed three lines below.
		 * Leaving them in place would make get_active_settings() strip
		 * `slides_per_view` — the saved "3" on instance #1 — and the carousel
		 * would fall back to one slide per view.
		 */
		$this->update_responsive_control(
			'slides_per_view',
			array(
				'condition' => null,
			)
		);

		$this->update_responsive_control(
			'slides_to_scroll',
			array(
				'condition' => null,
			)
		);

		$this->remove_control( 'effect' );
		$this->remove_responsive_control( 'height' );
		$this->remove_control( 'pagination_position' );
	}

	/**
	 * The slide repeater's fields. Ids are `content`, `image`, `name` and
	 * `title`, and all four are stored inside every saved slide row.
	 */
	private function add_repeater_controls( Repeater $repeater ): void {
		$repeater->add_control(
			'content',
			array(
				'label'   => esc_html__( 'Content', 'piecyfer-core' ),
				'type'    => Controls_Manager::TEXTAREA,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'image',
			array(
				'label'   => esc_html__( 'Image', 'piecyfer-core' ),
				'type'    => Controls_Manager::MEDIA,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'name',
			array(
				'label'   => esc_html__( 'Name', 'piecyfer-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'John Doe', 'piecyfer-core' ),
				'dynamic' => array(
					'active' => true,
				),
				'ai'      => array(
					'active' => false,
				),
			)
		);

		$repeater->add_control(
			'title',
			array(
				'label'   => esc_html__( 'Title', 'piecyfer-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'CEO', 'piecyfer-core' ),
				'dynamic' => array(
					'active' => true,
				),
				'ai'      => array(
					'active' => false,
				),
			)
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function get_repeater_defaults(): array {
		$placeholder_image_src = Utils::get_placeholder_image_src();

		return array(
			array(
				'content' => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'piecyfer-core' ),
				'name'    => esc_html__( 'John Doe', 'piecyfer-core' ),
				'title'   => esc_html__( 'CEO', 'piecyfer-core' ),
				'image'   => array(
					'url' => $placeholder_image_src,
				),
			),
			array(
				'content' => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'piecyfer-core' ),
				'name'    => esc_html__( 'John Doe', 'piecyfer-core' ),
				'title'   => esc_html__( 'CEO', 'piecyfer-core' ),
				'image'   => array(
					'url' => $placeholder_image_src,
				),
			),
			array(
				'content' => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'piecyfer-core' ),
				'name'    => esc_html__( 'John Doe', 'piecyfer-core' ),
				'title'   => esc_html__( 'CEO', 'piecyfer-core' ),
				'image'   => array(
					'url' => $placeholder_image_src,
				),
			),
		);
	}

	// ---------------------------------------------------------------- render

	protected function render_widget(): void {
		$this->print_slider();
	}

	/**
	 * Elementor's `e_swiper_latest` experiment renames the container class from
	 * `swiper-container` to `swiper`. It is read in two places — the
	 * `pagination_size` selector and the rendered markup — and they must agree,
	 * or the progress bar loses its height rule.
	 */
	private function get_swiper_class(): string {
		return \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_swiper_latest' ) ? 'swiper' : 'swiper-container';
	}

	/**
	 * @param array<string,mixed>|null $settings
	 */
	private function print_slider( ?array $settings = null ): void {
		if ( null === $settings ) {
			$settings = $this->get_settings_for_display();
		}

		$default_settings = array(
			'container_class' => 'elementor-main-swiper',
			'video_play_icon' => true,
		);

		$settings = array_merge( $default_settings, $settings );

		$slides_count = count( $settings['slides'] );
		$swiper_class = $this->get_swiper_class();
		?>
		<div class="elementor-swiper">
			<div class="<?php echo esc_attr( $settings['container_class'] ); ?> <?php echo esc_attr( $swiper_class ); ?>">
				<div class="swiper-wrapper">
					<?php
					foreach ( $settings['slides'] as $index => $slide ) :
						$this->slide_prints_count++;
						?>
						<div class="swiper-slide">
							<?php $this->print_slide( $slide, $settings, 'slide-' . $index . '-' . $this->slide_prints_count ); ?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( 1 < $slides_count ) : ?>
					<?php if ( $settings['pagination'] ) : ?>
						<div class="swiper-pagination"></div>
					<?php endif; ?>
					<?php if ( $settings['show_arrows'] ) : ?>
						<div class="elementor-swiper-button elementor-swiper-button-prev" role="button" tabindex="0">
							<?php $this->render_swiper_button( 'previous' ); ?>
							<span class="elementor-screen-only"><?php echo esc_html__( 'Previous', 'piecyfer-core' ); ?></span>
						</div>
						<div class="elementor-swiper-button elementor-swiper-button-next" role="button" tabindex="0">
							<?php $this->render_swiper_button( 'next' ); ?>
							<span class="elementor-screen-only"><?php echo esc_html__( 'Next', 'piecyfer-core' ); ?></span>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $slide
	 * @param array<string,mixed> $settings
	 */
	private function print_slide( array $slide, array $settings, string $element_key ): void {
		$lazyload = 'yes' === $this->get_settings( 'lazyload' );

		$this->add_render_attribute(
			$element_key . '-testimonial',
			array(
				'class' => 'elementor-testimonial',
			)
		);

		if ( ! empty( $slide['image']['url'] ) ) {
			$img_src = $this->get_slide_image_url( $slide, $settings );

			$img_attribute['src'] = $img_src;

			if ( $lazyload ) {
				$img_attribute['class']    = 'swiper-lazy';
				$img_attribute['data-src'] = $img_src;
			}

			$img_attribute['alt'] = ! empty( $slide['image']['alt'] ) ? $slide['image']['alt'] : $slide['name'];

			if ( ! empty( $slide['image']['id'] ) ) {
				$img_meta = wp_get_attachment_image_src( (int) $slide['image']['id'], 'full' );
				if ( is_array( $img_meta ) && ! empty( $img_meta[1] ) && ! empty( $img_meta[2] ) ) {
					$img_attribute['width']  = (string) $img_meta[1];
					$img_attribute['height'] = (string) $img_meta[2];
				}
			}

			$img_attribute['loading']  = 'lazy';
			$img_attribute['decoding'] = 'async';

			$this->add_render_attribute( $element_key . '-image', $img_attribute );
		}

		?>
		<div <?php $this->print_render_attribute_string( $element_key . '-testimonial' ); ?>>
			<?php if ( $slide['content'] ) : ?>
				<div class="elementor-testimonial__content">
					<div class="elementor-testimonial__text">
						<?php // PHPCS - the main text of a widget should not be escaped.
						echo $slide['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<?php $this->print_cite( $slide, 'outside' ); ?>
				</div>
			<?php endif; ?>
			<div class="elementor-testimonial__footer">
				<?php if ( $slide['image']['url'] ) : ?>
					<div class="elementor-testimonial__image">
						<img <?php $this->print_render_attribute_string( $element_key . '-image' ); ?>>
						<?php if ( $lazyload ) : ?>
							<div class="swiper-lazy-preloader"></div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php $this->print_cite( $slide, 'inside' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * The name/title block moves between the content bubble and the footer
	 * depending on layout, and the bubble skin forces `image_inline` regardless
	 * of what `layout` says. Both instances here use the default skin, so the
	 * saved `layout: image_above` on instance #2 is what decides it.
	 *
	 * @param array<string,mixed> $slide
	 */
	private function print_cite( array $slide, string $location ): void {
		if ( empty( $slide['name'] ) && empty( $slide['title'] ) ) {
			return;
		}

		$skin              = $this->get_settings( 'skin' );
		$layout            = 'bubble' === $skin ? 'image_inline' : $this->get_settings( 'layout' );
		$locations_outside = array( 'image_above', 'image_right', 'image_left' );
		$locations_inside  = array( 'image_inline', 'image_stacked' );

		$print_outside = ( 'outside' === $location && in_array( $layout, $locations_outside, true ) );
		$print_inside  = ( 'inside' === $location && in_array( $layout, $locations_inside, true ) );

		$html = '';
		if ( $print_outside || $print_inside ) {
			$html = '<cite class="elementor-testimonial__cite">';
			if ( ! empty( $slide['name'] ) ) {
				$html .= '<span class="elementor-testimonial__name">' . $slide['name'] . '</span>';
			}
			if ( ! empty( $slide['title'] ) ) {
				$html .= '<span class="elementor-testimonial__title">' . $slide['title'] . '</span>';
			}
			$html .= '</cite>';
		}

		// PHPCS - the main text of a widget should not be escaped.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<string,mixed> $slide
	 * @param array<string,mixed> $settings
	 */
	private function get_slide_image_url( array $slide, array $settings ): string {
		$image_url = Group_Control_Image_Size::get_attachment_image_src( $slide['image']['id'], 'image_size', $settings );

		if ( ! $image_url ) {
			$image_url = $slide['image']['url'];
		}

		return (string) $image_url;
	}

	/**
	 * The arrow direction is flipped for RTL by picking a different eicon, not
	 * by transforming the rendered one — so the markup itself differs between
	 * LTR and RTL, and a CSS-only mirror would not reproduce it.
	 */
	private function render_swiper_button( string $type ): void {
		$direction = 'next' === $type ? 'right' : 'left';

		if ( is_rtl() ) {
			$direction = 'right' === $direction ? 'left' : 'right';
		}

		$icon_value = 'eicon-chevron-' . $direction;

		Icons_Manager::render_icon(
			array(
				'library' => 'eicons',
				'value'   => $icon_value,
			),
			array( 'aria-hidden' => 'true' )
		);
	}
}
