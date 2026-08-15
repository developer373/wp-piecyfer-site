<?php
/**
 * Replacement for Elementor Pro's `call-to-action` widget.
 *
 * 3 instances, all in the "Emerging Tech" row on the Home page: classic skin,
 * an inline SVG icon as the graphic element, a title and a description, the
 * whole box linked (`link_click = box`) and no button, no ribbon, no bg image.
 *
 * Only a fraction of the control set is populated, but all of it is reproduced.
 * Elementor drops saved values that have no matching control the next time a
 * document is saved, so a widget that renders correctly today can still destroy
 * data the first time someone opens the page in the editor.
 *
 * Pro's own stylesheet (`widget-call-to-action`) is replaced by
 * assets/css/call-to-action.css, written from the rendered structure rather than
 * copied out of the nulled build. The `e-transitions` handle is NOT replaced yet
 * — it carries the `elementor-animated-content` hover animations, is shared with
 * Pro's gallery widget, and is still registered by Pro's CallToAction module. It
 * has to move into this plugin before Pro can actually be removed.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Control_Media;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

final class CallToActionWidget extends AbstractWidget {

	public function get_name(): string {
		return 'call-to-action';
	}

	public function get_title(): string {
		return esc_html__( 'Call to Action', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-image-rollover';
	}

	/**
	 * Pro's `Base_Widget_Trait` puts every Pro widget in `pro-elements`, and the
	 * saved instances reference that category.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	/**
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'call to action', 'cta', 'button' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — CallToAction/Call_To_Action';
	}

	/**
	 * Pro declares this false; `Element_Base` defaults it to **true**.
	 *
	 * It decides whether the element is baked into the document's element cache
	 * or emitted as an `[elementor-element]` placeholder and re-rendered on every
	 * request. Not overriding it silently changes the caching behaviour of the
	 * widget we are replacing — a difference no pixel comparison would show.
	 */
	protected function is_dynamic_content(): bool {
		return false;
	}

	/**
	 * `e-transitions` is Pro's, deliberately kept for now: it holds the
	 * `.elementor-animated-content` / `.elementor-animated-item--*` rules, which
	 * two of the three instances on this site rely on via `elementor-cta--skin-*`
	 * hover animation classes. Pro registers the handle in its CallToAction
	 * module, so it stays a live dependency until that file moves here too.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'piecyfer-call-to-action', 'e-transitions' );
	}

	protected function assets( string $handle ): void {
		// Registered centrally so the handle matches get_style_depends(), which
		// Elementor resolves before render_widget() runs.
	}

	// -------------------------------------------------------------- controls

	protected function register_controls(): void {
		$this->register_image_section();
		$this->register_content_section();
		$this->register_ribbon_section();
		$this->register_box_style_section();
		$this->register_graphic_element_style_section();
		$this->register_content_style_section();
		$this->register_button_style_section();
		$this->register_ribbon_style_section();
		$this->register_hover_effects_section();
	}

	private function register_image_section(): void {
		$this->start_controls_section(
			'section_main_image',
			array(
				'label' => esc_html__( 'Image', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'skin',
			array(
				'label'        => esc_html__( 'Skin', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'classic' => esc_html__( 'Classic', 'piecyfer-core' ),
					'cover'   => esc_html__( 'Cover', 'piecyfer-core' ),
				),
				'render_type'  => 'template',
				'prefix_class' => 'elementor-cta--skin-',
				'default'      => 'classic',
			)
		);

		$this->add_responsive_control(
			'layout',
			array(
				'label'        => esc_html__( 'Position', 'piecyfer-core' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'left'  => array(
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-left',
					),
					'above' => array(
						'title' => esc_html__( 'Above', 'piecyfer-core' ),
						'icon'  => 'eicon-v-align-top',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-right',
					),
					'below' => array(
						'title' => esc_html__( 'Below', 'piecyfer-core' ),
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				// %s is Elementor's responsive placeholder — it becomes
				// `elementor-cta--tablet-layout-image-` and so on. A literal here
				// silently drops every responsive variant.
				'prefix_class' => 'elementor-cta-%s-layout-image-',
				'condition'    => array(
					'skin!' => 'cover',
				),
			)
		);

		$this->add_control(
			'bg_image',
			array(
				'label'   => esc_html__( 'Choose Image', 'piecyfer-core' ),
				'type'    => Controls_Manager::MEDIA,
				'dynamic' => array(
					'active' => true,
				),
				'default' => array(
					'url' => Utils::get_placeholder_image_src(),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'      => 'bg_image', // Actually its `image_size`.
				'label'     => esc_html__( 'Image Resolution', 'piecyfer-core' ),
				'default'   => 'large',
				'condition' => array(
					'bg_image[id]!' => '',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_content_section(): void {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Content', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'graphic_element',
			array(
				'label'   => esc_html__( 'Graphic Element', 'piecyfer-core' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'none'  => array(
						'title' => esc_html__( 'None', 'piecyfer-core' ),
						'icon'  => 'eicon-ban',
					),
					'image' => array(
						'title' => esc_html__( 'Image', 'piecyfer-core' ),
						'icon'  => 'eicon-image-bold',
					),
					'icon'  => array(
						'title' => esc_html__( 'Icon', 'piecyfer-core' ),
						'icon'  => 'eicon-star',
					),
				),
				'default' => 'none',
			)
		);

		$this->add_control(
			'graphic_image',
			array(
				'label'      => esc_html__( 'Choose Image', 'piecyfer-core' ),
				'type'       => Controls_Manager::MEDIA,
				'dynamic'    => array(
					'active' => true,
				),
				'default'    => array(
					'url' => Utils::get_placeholder_image_src(),
				),
				'condition'  => array(
					'graphic_element' => 'image',
				),
				'show_label' => false,
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'      => 'graphic_image', // Actually its `image_size`.
				'default'   => 'thumbnail',
				'condition' => array(
					'graphic_element'      => 'image',
					'graphic_image[id]!'   => '',
				),
			)
		);

		$this->add_control(
			'selected_icon',
			array(
				'label'            => esc_html__( 'Icon', 'piecyfer-core' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'default'          => array(
					'value'   => 'fas fa-star',
					'library' => 'fa-solid',
				),
				'condition'        => array(
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Title', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array(
					'active' => true,
				),
				'default'     => esc_html__( 'This is the heading', 'piecyfer-core' ),
				'placeholder' => esc_html__( 'Enter your title', 'piecyfer-core' ),
				'label_block' => true,
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label'     => esc_html__( 'Title HTML Tag', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
				),
				'default'   => 'h2',
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_control(
			'description',
			array(
				'label'       => esc_html__( 'Description', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXTAREA,
				'dynamic'     => array(
					'active' => true,
				),
				'default'     => esc_html__( 'Lorem ipsum dolor sit amet consectetur adipiscing elit dolor', 'piecyfer-core' ),
				'placeholder' => esc_html__( 'Enter your description', 'piecyfer-core' ),
				'separator'   => 'before',
				'rows'        => 5,
			)
		);

		$this->add_control(
			'description_tag',
			array(
				'label'     => esc_html__( 'Description HTML Tag', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
				),
				'default'   => 'div',
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_control(
			'button',
			array(
				'label'     => esc_html__( 'Button Text', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array(
					'active' => true,
				),
				'default'   => esc_html__( 'Click Here', 'piecyfer-core' ),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'link',
			array(
				'label'   => esc_html__( 'Link', 'piecyfer-core' ),
				'type'    => Controls_Manager::URL,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'link_click',
			array(
				'label'     => esc_html__( 'Apply Link On', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'box'    => esc_html__( 'Whole Box', 'piecyfer-core' ),
					'button' => esc_html__( 'Button Only', 'piecyfer-core' ),
				),
				'default'   => 'button',
				'condition' => array(
					'link[url]!' => '',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_ribbon_section(): void {
		$this->start_controls_section(
			'section_ribbon',
			array(
				'label' => esc_html__( 'Ribbon', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'ribbon_title',
			array(
				'label'   => esc_html__( 'Title', 'piecyfer-core' ),
				'type'    => Controls_Manager::TEXT,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'ribbon_horizontal_position',
			array(
				'label'     => esc_html__( 'Position', 'piecyfer-core' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'  => array(
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-left',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'condition' => array(
					'ribbon_title!' => '',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_box_style_section(): void {
		$this->start_controls_section(
			'box_style',
			array(
				'label' => esc_html__( 'Box', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// The id really is `min-height`, with a hyphen. It is the key in the saved
		// JSON, so it cannot be normalised to an underscore.
		$this->add_responsive_control(
			'min-height',
			array(
				'label'      => esc_html__( 'Height', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
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
						'min' => 10,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-cta__content' => 'min-height: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'alignment',
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
					'{{WRAPPER}} .elementor-cta__content' => 'text-align: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'vertical_position',
			array(
				'label'        => esc_html__( 'Vertical Position', 'piecyfer-core' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
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
				'prefix_class' => 'elementor-cta--valign-',
			)
		);

		$this->add_responsive_control(
			'padding',
			array(
				'label'      => esc_html__( 'Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-cta__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'heading_bg_image_style',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Image', 'piecyfer-core' ),
				'condition' => array(
					'bg_image[url]!' => '',
					'skin'           => 'classic',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'image_min_width',
			array(
				'label'      => esc_html__( 'Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 500,
					),
					'em'  => array(
						'max' => 50,
					),
					'rem' => array(
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-cta__bg-wrapper' => 'min-width: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'skin'    => 'classic',
					'layout!' => 'above',
				),
			)
		);

		$this->add_responsive_control(
			'image_min_height',
			array(
				'label'      => esc_html__( 'Height', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 500,
					),
					'em'  => array(
						'max' => 50,
					),
					'rem' => array(
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-cta__bg-wrapper' => 'min-height: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'skin' => 'classic',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_graphic_element_style_section(): void {
		$this->start_controls_section(
			'graphic_element_style',
			array(
				'label'     => esc_html__( 'Graphic Element', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'graphic_element!' => array(
						'none',
						'',
					),
				),
			)
		);

		$this->add_control(
			'graphic_image_spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'piecyfer-core' ),
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
					'{{WRAPPER}} .elementor-cta__image' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_control(
			'graphic_image_width',
			array(
				'label'      => esc_html__( 'Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array(
					'unit' => '%',
				),
				'range'      => array(
					'%' => array(
						'min' => 5,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-cta__image img' => 'width: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'graphic_image_border',
				'selector'  => '{{WRAPPER}} .elementor-cta__image img',
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_control(
			'graphic_image_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
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
					'{{WRAPPER}} .elementor-cta__image img' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_control(
			'icon_view',
			array(
				'label'     => esc_html__( 'View', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'default' => esc_html__( 'Default', 'piecyfer-core' ),
					'stacked' => esc_html__( 'Stacked', 'piecyfer-core' ),
					'framed'  => esc_html__( 'Framed', 'piecyfer-core' ),
				),
				'default'   => 'default',
				'condition' => array(
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'icon_shape',
			array(
				'label'     => esc_html__( 'Shape', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'circle' => esc_html__( 'Circle', 'piecyfer-core' ),
					'square' => esc_html__( 'Square', 'piecyfer-core' ),
				),
				'default'   => 'circle',
				'condition' => array(
					'icon_view!'      => 'default',
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'icon_spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'piecyfer-core' ),
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
					'{{WRAPPER}} .elementor-icon-wrapper' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'graphic_element' => 'icon',
				),
			)
		);

		// The stacked/framed/default selector split is Pro's, including the
		// asymmetry on the last line (`.elementor-view-framed .elementor-icon`
		// without `svg`, then `.elementor-view-default .elementor-icon svg`).
		// It compiles straight into the page CSS, so it is copied as-is rather
		// than "fixed".
		$this->add_control(
			'icon_primary_color',
			array(
				'label'     => esc_html__( 'Primary Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-view-stacked .elementor-icon' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .elementor-view-stacked .elementor-icon svg' => 'stroke: {{VALUE}}',
					'{{WRAPPER}} .elementor-view-framed .elementor-icon, {{WRAPPER}} .elementor-view-default .elementor-icon' => 'color: {{VALUE}}; border-color: {{VALUE}}',
					'{{WRAPPER}} .elementor-view-framed .elementor-icon, {{WRAPPER}} .elementor-view-default .elementor-icon svg' => 'fill: {{VALUE}};',
				),
				'condition' => array(
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'icon_secondary_color',
			array(
				'label'     => esc_html__( 'Secondary Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'condition' => array(
					'graphic_element' => 'icon',
					'icon_view!'      => 'default',
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-view-framed .elementor-icon' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-view-framed .elementor-icon svg' => 'stroke: {{VALUE}};',
					'{{WRAPPER}} .elementor-view-stacked .elementor-icon' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-view-stacked .elementor-icon svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Icon Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 6,
						'max' => 300,
					),
					'em'  => array(
						'min' => 0.6,
						'max' => 30,
					),
					'rem' => array(
						'min' => 0.6,
						'max' => 30,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-icon' => 'font-size: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'icon_padding',
			array(
				'label'      => esc_html__( 'Icon Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-icon' => 'padding: {{SIZE}}{{UNIT}};',
				),
				'range'      => array(
					'px'  => array(
						'max' => 50,
					),
					'em'  => array(
						'min' => 0,
						'max' => 5,
					),
					'rem' => array(
						'min' => 0,
						'max' => 5,
					),
				),
				'condition'  => array(
					'graphic_element' => 'icon',
					'icon_view!'      => 'default',
				),
			)
		);

		$this->add_control(
			'icon_border_width',
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
					'{{WRAPPER}} .elementor-icon' => 'border-width: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'graphic_element' => 'icon',
					'icon_view'       => 'framed',
				),
			)
		);

		$this->add_control(
			'icon_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'graphic_element' => 'icon',
					'icon_view!'      => 'default',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_content_style_section(): void {
		$this->start_controls_section(
			'section_content_style',
			array(
				'label'      => esc_html__( 'Content', 'piecyfer-core' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'title',
							'operator' => '!==',
							'value'    => '',
						),
						array(
							'name'     => 'description',
							'operator' => '!==',
							'value'    => '',
						),
					),
				),
			)
		);

		$this->add_control(
			'heading_style_title',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Title', 'piecyfer-core' ),
				'condition' => array(
					'title!' => '',
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
				'selector'  => '{{WRAPPER}} .elementor-cta__title',
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name'     => 'text_stroke',
				'selector' => '{{WRAPPER}} .elementor-cta__title',
			)
		);

		$this->add_responsive_control(
			'title_spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-cta__title:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'title!' => '',
				),
			)
		);

		$this->add_control(
			'heading_style_description',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Description', 'piecyfer-core' ),
				'separator' => 'before',
				'condition' => array(
					'description!' => '',
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
				'selector'  => '{{WRAPPER}} .elementor-cta__description',
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'description_spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-cta__description:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'description!' => '',
				),
			)
		);

		$this->add_control(
			'heading_content_colors',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Colors', 'piecyfer-core' ),
				'separator' => 'before',
			)
		);

		$this->start_controls_tabs( 'color_tabs' );

		$this->start_controls_tab(
			'colors_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'content_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__content' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'skin' => 'classic',
				),
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Title Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__title' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_control(
			'description_color',
			array(
				'label'     => esc_html__( 'Description Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__description' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'     => esc_html__( 'Button Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__button' => 'color: {{VALUE}}; border-color: {{VALUE}}',
				),
				'condition' => array(
					'button!' => '',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'colors_hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'content_bg_color_hover',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta:hover .elementor-cta__content' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'skin' => 'classic',
				),
			)
		);

		$this->add_control(
			'title_color_hover',
			array(
				'label'     => esc_html__( 'Title Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta:hover .elementor-cta__title' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_control(
			'description_color_hover',
			array(
				'label'     => esc_html__( 'Description Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta:hover .elementor-cta__description' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_control(
			'button_color_hover',
			array(
				'label'     => esc_html__( 'Button Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta:hover .elementor-cta__button' => 'color: {{VALUE}}; border-color: {{VALUE}}',
				),
				'condition' => array(
					'button!' => '',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function register_button_style_section(): void {
		$this->start_controls_section(
			'button_style',
			array(
				'label'     => esc_html__( 'Button', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'button!' => '',
				),
			)
		);

		$this->add_control(
			'button_size',
			array(
				'label'     => esc_html__( 'Size', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'xs' => esc_html__( 'Extra Small', 'piecyfer-core' ),
					'sm' => esc_html__( 'Small', 'piecyfer-core' ),
					'md' => esc_html__( 'Medium', 'piecyfer-core' ),
					'lg' => esc_html__( 'Large', 'piecyfer-core' ),
					'xl' => esc_html__( 'Extra Large', 'piecyfer-core' ),
				),
				'default'   => 'sm',
				// Pro's own comment: a workaround to hide the control unless it is
				// in use (not default). The side effect is load-bearing —
				// get_active_settings() nulls a control whose condition fails, so
				// at the default the rendered class really is `elementor-size-`
				// with nothing after it. Removing this condition would change the
				// markup of every default-sized button on the site.
				'condition' => array(
					'button_size!' => 'sm',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .elementor-cta__button',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'button_text_shadow',
				'selector' => '{{WRAPPER}} .elementor-cta__button',
			)
		);

		$this->start_controls_tabs( 'button_tabs' );

		$this->start_controls_tab(
			'button_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__button' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__button' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		// Pro spells this tab id with a hyphen while its sibling uses an
		// underscore. Inconsistent, but it is the id saved in the editor state.
		$this->start_controls_tab(
			'button-hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'button_hover_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__button:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__button:hover' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'button_border_width',
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
					'{{WRAPPER}} .elementor-cta__button' => 'border-width: {{SIZE}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
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
					'{{WRAPPER}} .elementor-cta__button' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'button_box_shadow',
				'selector' => '{{WRAPPER}} .elementor-cta__button',
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => esc_html__( 'Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-cta__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();
	}

	private function register_ribbon_style_section(): void {
		$this->start_controls_section(
			'section_ribbon_style',
			array(
				'label'      => esc_html__( 'Ribbon', 'piecyfer-core' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'show_label' => false,
				'condition'  => array(
					'ribbon_title!' => '',
				),
			)
		);

		$this->add_control(
			'ribbon_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-ribbon-inner' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'ribbon_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-ribbon-inner' => 'color: {{VALUE}}',
				),
			)
		);

		// The ribbon sits in a rotated corner, so RTL needs a different transform
		// origin rather than a mirrored value — hence a PHP branch instead of a
		// `body.rtl` selector.
		$ribbon_distance_transform = is_rtl() ? 'translateY(-50%) translateX({{SIZE}}{{UNIT}}) rotate(-45deg)' : 'translateY(-50%) translateX(-50%) translateX({{SIZE}}{{UNIT}}) rotate(-45deg)';

		$this->add_responsive_control(
			'ribbon_distance',
			array(
				'label'      => esc_html__( 'Distance', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				// Pro lists 'em' twice here with identical bounds and no 'rem';
				// PHP keeps the last, so this single entry is the same array.
				'range'      => array(
					'px' => array(
						'max' => 50,
					),
					'em' => array(
						'max' => 5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-ribbon-inner' => 'margin-top: {{SIZE}}{{UNIT}}; transform: ' . $ribbon_distance_transform,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'ribbon_typography',
				'selector' => '{{WRAPPER}} .elementor-ribbon-inner',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
			)
		);

		// Named `box_shadow`, not `ribbon_box_shadow` — it is the ribbon's shadow
		// despite the generic id, and the id is what is saved.
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}} .elementor-ribbon-inner',
			)
		);

		$this->end_controls_section();
	}

	private function register_hover_effects_section(): void {
		$this->start_controls_section(
			'hover_effects',
			array(
				'label' => esc_html__( 'Hover Effects', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'content_hover_heading',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Content', 'piecyfer-core' ),
				'condition' => array(
					'skin' => 'cover',
				),
			)
		);

		$this->add_control(
			'content_animation',
			array(
				'label'     => esc_html__( 'Hover Animation', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'groups'    => array(
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
				'default'   => 'grow',
				'condition' => array(
					'skin' => 'cover',
				),
			)
		);

		/*
		 * A hidden control whose only job is its prefix_class: it puts
		 * `elementor-animated-content` on the wrapper, which is the hook every
		 * `.elementor-animated-item--*` rule in the `e-transitions` stylesheet
		 * keys off. All three instances on this site carry it.
		 */
		$this->add_control(
			'animation_class',
			array(
				'label'        => esc_html__( 'Animation', 'piecyfer-core' ),
				'type'         => Controls_Manager::HIDDEN,
				'default'      => 'animated-content',
				'prefix_class' => 'elementor-',
				'condition'    => array(
					'content_animation!' => '',
				),
			)
		);

		$this->add_control(
			'content_animation_duration',
			array(
				'label'       => esc_html__( 'Animation Duration', 'piecyfer-core' ) . ' (ms)',
				'type'        => Controls_Manager::SLIDER,
				'render_type' => 'template',
				'default'     => array(
					'size' => 1000,
				),
				'range'       => array(
					'px' => array(
						'min'  => 0,
						'max'  => 3000,
						'step' => 100,
					),
				),
				'selectors'   => array(
					'{{WRAPPER}} .elementor-cta__content-item' => 'transition-duration: {{SIZE}}ms',
					'{{WRAPPER}}.elementor-cta--sequenced-animation .elementor-cta__content-item:nth-child(2)' => 'transition-delay: calc( {{SIZE}}ms / 3 )',
					'{{WRAPPER}}.elementor-cta--sequenced-animation .elementor-cta__content-item:nth-child(3)' => 'transition-delay: calc( ( {{SIZE}}ms / 3 ) * 2 )',
					'{{WRAPPER}}.elementor-cta--sequenced-animation .elementor-cta__content-item:nth-child(4)' => 'transition-delay: calc( ( {{SIZE}}ms / 3 ) * 3 )',
				),
				'condition'   => array(
					'content_animation!' => '',
					'skin'               => 'cover',
				),
			)
		);

		// `prefix_class => ''` with a full class name as return_value: the switcher
		// puts `elementor-cta--sequenced-animation` on the wrapper verbatim.
		$this->add_control(
			'sequenced_animation',
			array(
				'label'        => esc_html__( 'Sequenced Animation', 'piecyfer-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'piecyfer-core' ),
				'label_off'    => esc_html__( 'Off', 'piecyfer-core' ),
				'return_value' => 'elementor-cta--sequenced-animation',
				'prefix_class' => '',
				'condition'    => array(
					'content_animation!' => '',
					'skin'               => 'cover',
				),
			)
		);

		$this->add_control(
			'background_hover_heading',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Background', 'piecyfer-core' ),
				'separator' => 'before',
				'condition' => array(
					'skin' => 'cover',
				),
			)
		);

		// Two classes from one prefix_class: the constant `elementor-bg-transform`
		// plus the variant. Both are needed by the stylesheet, and neither is
		// emitted when the value is empty — which is the case for one of the three
		// instances here.
		$this->add_control(
			'transformation',
			array(
				'label'        => esc_html__( 'Hover Animation', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					''           => 'None',
					'zoom-in'    => 'Zoom In',
					'zoom-out'   => 'Zoom Out',
					'move-left'  => 'Move Left',
					'move-right' => 'Move Right',
					'move-up'    => 'Move Up',
					'move-down'  => 'Move Down',
				),
				'default'      => 'zoom-in',
				'prefix_class' => 'elementor-bg-transform elementor-bg-transform-',
			)
		);

		$this->start_controls_tabs( 'bg_effects_tabs' );

		$this->start_controls_tab(
			'normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'overlay_color',
			array(
				'label'     => esc_html__( 'Overlay Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta:not(:hover) .elementor-cta__bg-overlay' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'bg_filters',
				'selector' => '{{WRAPPER}} .elementor-cta__bg',
			)
		);

		$this->add_control(
			'overlay_blend_mode',
			array(
				'label'     => esc_html__( 'Blend Mode', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
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
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta__bg-overlay' => 'mix-blend-mode: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'overlay_color_hover',
			array(
				'label'     => esc_html__( 'Overlay Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-cta:hover .elementor-cta__bg-overlay' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'bg_filters_hover',
				'selector' => '{{WRAPPER}} .elementor-cta:hover .elementor-cta__bg',
			)
		);

		$this->add_control(
			'effect_duration',
			array(
				'label'       => esc_html__( 'Transition Duration', 'piecyfer-core' ) . ' (ms)',
				'type'        => Controls_Manager::SLIDER,
				'render_type' => 'template',
				'default'     => array(
					'size' => 1500,
				),
				'range'       => array(
					'px' => array(
						'min'  => 0,
						'max'  => 3000,
						'step' => 100,
					),
				),
				'selectors'   => array(
					'{{WRAPPER}} .elementor-cta .elementor-cta__bg, {{WRAPPER}} .elementor-cta .elementor-cta__bg-overlay' => 'transition-duration: {{SIZE}}ms',
				),
				'separator'   => 'before',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	// ----------------------------------------------------------------- render

	/**
	 * Byte-for-byte reproduction of Pro's template.
	 *
	 * Everything between `?>` and the next `<?php` is literal output, tabs and
	 * blank lines included, so the indentation below is content rather than
	 * formatting. The awkward-looking cases are all deliberate:
	 *
	 *   - the leading tabs on an `<?php if ... ?>` line belong to the *outer*
	 *     scope and are printed even when the branch is skipped, while the tabs
	 *     in front of the matching `endif` belong to the branch and are not;
	 *   - the closing button tag uses `print_unescaped_internal_string()` where
	 *     the opening tag uses `print_validated_html_tag()` — Pro's asymmetry,
	 *     harmless because `$button_tag` is only ever 'a' or 'span'.
	 */
	protected function render_widget(): void {
		$settings = $this->get_settings_for_display();

		$wrapper_tag = 'div';
		$button_tag = 'a';
		$title_tag = Utils::validate_html_tag( $settings['title_tag'] );
		$description_tag = Utils::validate_html_tag( $settings['description_tag'] );
		$bg_image = '';
		$content_animation = $settings['content_animation'];
		$animation_class = '';
		$print_bg = true;
		$print_content = true;

		if ( ! empty( $settings['bg_image']['id'] ) ) {
			$bg_image = Group_Control_Image_Size::get_attachment_image_src( $settings['bg_image']['id'], 'bg_image', $settings );
		} elseif ( ! empty( $settings['bg_image']['url'] ) ) {
			$bg_image = $settings['bg_image']['url'];
		}

		if ( empty( $bg_image ) && 'classic' == $settings['skin'] ) {
			$print_bg = false;
		}

		if ( empty( $settings['title'] ) && empty( $settings['description'] ) && empty( $settings['button'] ) && 'none' == $settings['graphic_element'] ) {
			$print_content = false;
		}

		$this->add_render_attribute( 'wrapper', 'class', 'elementor-cta' );

		$this->add_render_attribute(
			'background_image',
			array(
				'style'      => 'background-image: url(' . esc_url( $bg_image ) . ');',
				'role'       => 'img',
				'aria-label' => Control_Media::get_image_alt( $settings['bg_image'] ),
			)
		);

		$this->add_render_attribute( 'title', 'class', array(
			'elementor-cta__title',
			'elementor-cta__content-item',
			'elementor-content-item',
		) );

		$this->add_render_attribute( 'description', 'class', array(
			'elementor-cta__description',
			'elementor-cta__content-item',
			'elementor-content-item',
		) );

		// `button_size` is null at its default (see the control's condition), so
		// the class really is `elementor-size-`. Matching Pro means keeping that.
		$this->add_render_attribute( 'button', 'class', array(
			'elementor-cta__button',
			'elementor-button',
			'elementor-size-' . $settings['button_size'],
		) );

		$this->add_render_attribute( 'graphic_element', 'class',
			array(
				'elementor-content-item',
				'elementor-cta__content-item',
			)
		);

		if ( 'icon' === $settings['graphic_element'] ) {
			$this->add_render_attribute( 'graphic_element', 'class',
				array(
					'elementor-icon-wrapper',
					'elementor-cta__icon',
				)
			);
			$this->add_render_attribute( 'graphic_element', 'class', 'elementor-view-' . $settings['icon_view'] );
			if ( 'default' != $settings['icon_view'] ) {
				$this->add_render_attribute( 'graphic_element', 'class', 'elementor-shape-' . $settings['icon_shape'] );
			}

			if ( ! isset( $settings['icon'] ) && ! Icons_Manager::is_migration_allowed() ) {
				// add old default
				$settings['icon'] = 'fa fa-star';
			}

			if ( ! empty( $settings['icon'] ) ) {
				$this->add_render_attribute( 'icon', 'class', $settings['icon'] );
			}
		} elseif ( 'image' === $settings['graphic_element'] && ! empty( $settings['graphic_image']['url'] ) ) {
			$this->add_render_attribute( 'graphic_element', 'class', 'elementor-cta__image' );
		}

		if ( ! empty( $content_animation ) && 'cover' == $settings['skin'] ) {

			$animation_class = 'elementor-animated-item--' . $content_animation;

			$this->add_render_attribute( 'title', 'class', $animation_class );

			$this->add_render_attribute( 'graphic_element', 'class', $animation_class );

			$this->add_render_attribute( 'description', 'class', $animation_class );

		}

		if ( ! empty( $settings['link']['url'] ) ) {
			$link_element = 'button';

			if ( 'box' === $settings['link_click'] ) {
				$wrapper_tag = 'a';
				$button_tag = 'span';
				$link_element = 'wrapper';
			}

			$this->add_link_attributes( $link_element, $settings['link'] );
		}

		$this->add_inline_editing_attributes( 'title' );
		$this->add_inline_editing_attributes( 'description' );
		$this->add_inline_editing_attributes( 'button' );

		$migrated = isset( $settings['__fa4_migrated']['selected_icon'] );
		$is_new = empty( $settings['icon'] ) && Icons_Manager::is_migration_allowed();

		?>
		<<?php Utils::print_validated_html_tag( $wrapper_tag ); ?> <?php $this->print_render_attribute_string( 'wrapper' ); ?>>
		<?php if ( $print_bg ) : ?>
			<div class="elementor-cta__bg-wrapper">
				<div class="elementor-cta__bg elementor-bg" <?php $this->print_render_attribute_string( 'background_image' ); ?>></div>
				<div class="elementor-cta__bg-overlay"></div>
			</div>
		<?php endif; ?>
		<?php if ( $print_content ) : ?>
			<div class="elementor-cta__content">
				<?php if ( 'image' === $settings['graphic_element'] && ! empty( $settings['graphic_image']['url'] ) ) : ?>
					<div <?php $this->print_render_attribute_string( 'graphic_element' ); ?>>
						<?php Group_Control_Image_Size::print_attachment_image_html( $settings, 'graphic_image' ); ?>
					</div>
				<?php elseif ( 'icon' === $settings['graphic_element'] && ( ! empty( $settings['icon'] ) || ! empty( $settings['selected_icon'] ) ) ) : ?>
					<div <?php $this->print_render_attribute_string( 'graphic_element' ); ?>>
						<div class="elementor-icon">
							<?php if ( $is_new || $migrated ) :
								Icons_Manager::render_icon( $settings['selected_icon'], array( 'aria-hidden' => 'true' ) );
							else : ?>
								<i <?php $this->print_render_attribute_string( 'icon' ); ?>></i>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $settings['title'] ) ) : ?>
					<<?php Utils::print_validated_html_tag( $title_tag ); ?> <?php $this->print_render_attribute_string( 'title' ); ?>>
						<?php $this->print_unescaped_setting( 'title' ); ?>
					</<?php Utils::print_validated_html_tag( $title_tag ); ?>>
				<?php endif; ?>

				<?php if ( ! empty( $settings['description'] ) ) : ?>
					<<?php Utils::print_validated_html_tag( $description_tag ); ?> <?php $this->print_render_attribute_string( 'description' ); ?>>
						<?php $this->print_unescaped_setting( 'description' ); ?>
					</<?php Utils::print_validated_html_tag( $description_tag ); ?>>
				<?php endif; ?>

				<?php if ( ! empty( $settings['button'] ) ) : ?>
					<div class="elementor-cta__button-wrapper elementor-cta__content-item elementor-content-item <?php echo esc_attr( $animation_class ); ?>">
					<<?php Utils::print_validated_html_tag( $button_tag ); ?> <?php $this->print_render_attribute_string( 'button' ); ?>>
						<?php $this->print_unescaped_setting( 'button' ); ?>
					</<?php Utils::print_unescaped_internal_string( $button_tag ); ?>>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php
		if ( ! empty( $settings['ribbon_title'] ) ) :
			$this->add_render_attribute( 'ribbon-wrapper', 'class', 'elementor-ribbon' );

			if ( ! empty( $settings['ribbon_horizontal_position'] ) ) {
				$this->add_render_attribute( 'ribbon-wrapper', 'class', 'elementor-ribbon-' . $settings['ribbon_horizontal_position'] );
			}
			?>
			<div <?php $this->print_render_attribute_string( 'ribbon-wrapper' ); ?>>
				<div class="elementor-ribbon-inner"><?php $this->print_unescaped_setting( 'ribbon_title' ); ?></div>
			</div>
		<?php endif; ?>
		</<?php Utils::print_validated_html_tag( $wrapper_tag ); ?>>
		<?php
	}
}
