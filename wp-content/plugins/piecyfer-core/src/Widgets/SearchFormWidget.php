<?php
/**
 * Replacement for Elementor Pro's `search-form` widget.
 *
 * 4 instances: two in the site header (so on every page) and two on the search
 * results template. Only the `classic` skin is used.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

defined( 'ABSPATH' ) || exit;

final class SearchFormWidget extends AbstractWidget {

	public function get_name(): string {
		return 'search-form';
	}

	public function get_title(): string {
		return esc_html__( 'Search Form', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-site-search';
	}

	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	public function get_keywords(): array {
		return array( 'search', 'form' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — ThemeElements/Search_Form';
	}

	/**
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'piecyfer-search-form' );
	}

	protected function register_controls(): void {
		$this->register_content_section();
		$this->register_input_style_section();
		$this->register_button_style_section();
		$this->register_toggle_style_section();
	}

	// ---------------------------------------------------------------- content

	private function register_content_section(): void {
		$this->start_controls_section(
			'search_content',
			array( 'label' => esc_html__( 'Search Form', 'piecyfer-core' ) )
		);

		$this->add_control(
			'skin',
			array(
				'label'        => esc_html__( 'Skin', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'classic'     => esc_html__( 'Classic', 'piecyfer-core' ),
					'minimal'     => esc_html__( 'Minimal', 'piecyfer-core' ),
					'full_screen' => esc_html__( 'Full Screen', 'piecyfer-core' ),
				),
				'default'      => 'classic',
				'prefix_class' => 'elementor-search-form--skin-',
				'render_type'  => 'template',
				/*
				 * Not cosmetic. `frontend_available` is what puts the setting into
				 * the wrapper's `data-settings` JSON, and Pro's search-form
				 * handler reads `skin` from there to decide whether to wire up the
				 * full-screen toggle. Without it the attribute vanished from every
				 * page and the widget's JS would have had nothing to read.
				 */
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'placeholder',
			array(
				'label'     => esc_html__( 'Placeholder', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Search', 'piecyfer-core' ) . '...',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'heading_button_content',
			array(
				'label'     => esc_html__( 'Button', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'skin' => 'classic' ),
			)
		);

		$this->add_control(
			'button_type',
			array(
				'label'        => esc_html__( 'Type', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'icon' => esc_html__( 'Icon', 'piecyfer-core' ),
					'text' => esc_html__( 'Text', 'piecyfer-core' ),
				),
				'default'      => 'icon',
				'prefix_class' => 'elementor-search-form--button-type-',
				'condition'    => array( 'skin' => 'classic' ),
				'render_type'  => 'template',
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'     => esc_html__( 'Text', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Search', 'piecyfer-core' ),
				'condition' => array(
					'skin'        => 'classic',
					'button_type' => 'text',
				),
			)
		);

		$this->add_control(
			'icon',
			array(
				'label'        => esc_html__( 'Icon', 'piecyfer-core' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'search' => array(
						'title' => esc_html__( 'Search', 'piecyfer-core' ),
						'icon'  => 'eicon-search',
					),
					'arrow'  => array(
						'title' => esc_html__( 'Arrow', 'piecyfer-core' ),
						'icon'  => 'eicon-arrow-right',
					),
				),
				'default'      => 'search',
				'prefix_class' => 'elementor-search-form--icon-',
				'condition'    => array(
					'skin'        => 'classic',
					'button_type' => 'icon',
				),
				'render_type'  => 'template',
			)
		);

		$this->add_control(
			'size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => array(
					'size' => 50,
					'unit' => 'px',
				),
				'range'      => array(
					'px' => array( 'min' => 30, 'max' => 100 ),
					'em' => array( 'min' => 2, 'max' => 8, 'step' => 0.1 ),
				),
				'size_units' => array( 'px', 'em', 'rem' ),
				'separator'  => 'before',
				'condition'  => array( 'skin!' => 'full_screen' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-search-form__container' => 'min-height: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .elementor-search-form__submit'    => 'min-width: {{SIZE}}{{UNIT}}',
					'body:not(.rtl) {{WRAPPER}} .elementor-search-form__icon' => 'padding-left: calc({{SIZE}}{{UNIT}} / 3)',
					'body.rtl {{WRAPPER}} .elementor-search-form__icon' => 'padding-right: calc({{SIZE}}{{UNIT}} / 3)',
					'{{WRAPPER}} .elementor-search-form__input'     => 'padding-left: calc({{SIZE}}{{UNIT}} / 3); padding-right: calc({{SIZE}}{{UNIT}} / 3)',
				),
			)
		);

		$this->add_control(
			'toggle_button_content',
			array(
				'label'     => esc_html__( 'Toggle Button', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'skin' => 'full_screen' ),
			)
		);

		$this->add_control(
			'toggle_align',
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
				'condition' => array( 'skin' => 'full_screen' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form' => 'text-align: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => array(
					'size' => 33,
					'unit' => 'px',
				),
				'size_units' => array( 'px', 'em', 'rem' ),
				'condition'  => array( 'skin' => 'full_screen' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-search-form__toggle' => '--e-search-form-toggle-size: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'icon_size_minimal',
			array(
				'label'      => esc_html__( 'Icon Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'separator'  => 'before',
				'condition'  => array( 'skin' => 'minimal' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-search-form__icon' => '--e-search-form-icon-size-minimal: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'overlay_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array( 'skin' => 'full_screen' ),
				'selectors' => array(
					'{{WRAPPER}}.elementor-search-form--skin-full_screen .elementor-search-form__container' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_section();
	}

	// ------------------------------------------------------------ input style

	private function register_input_style_section(): void {
		$this->start_controls_section(
			'section_input_style',
			array(
				'label' => esc_html__( 'Input', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'input_typography',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_TEXT ),
				'selector' => '{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__input::placeholder',
			)
		);

		$this->start_controls_tabs( 'tabs_input_colors' );

		$this->start_controls_tab(
			'tab_input_normal',
			array( 'label' => esc_html__( 'Normal', 'piecyfer-core' ) )
		);

		$this->add_control(
			'input_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_TEXT ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__input, {{WRAPPER}} .elementor-search-form__icon, {{WRAPPER}} .elementor-lightbox .dialog-lightbox-close-button, {{WRAPPER}} .elementor-lightbox .dialog-lightbox-close-button:hover, {{WRAPPER}}.elementor-search-form--skin-full_screen input[type="search"].elementor-search-form__input' => 'color: {{VALUE}}; fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'input_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.elementor-search-form--skin-full_screen) .elementor-search-form__container' => 'background-color: {{VALUE}}',
					'{{WRAPPER}}.elementor-search-form--skin-full_screen input[type="search"].elementor-search-form__input' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'input_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.elementor-search-form--skin-full_screen) .elementor-search-form__container' => 'border-color: {{VALUE}}',
					'{{WRAPPER}}.elementor-search-form--skin-full_screen input[type="search"].elementor-search-form__input' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'input_box_shadow',
				'separator' => 'default',
				'selector'  => '{{WRAPPER}}:not(.elementor-search-form--skin-full_screen) .elementor-search-form__container',
				'condition' => array( 'skin!' => 'full_screen' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_input_focus',
			array( 'label' => esc_html__( 'Focus', 'piecyfer-core' ) )
		);

		$this->add_control(
			'input_text_color_focus',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.elementor-search-form--skin-full_screen) .elementor-search-form--focus .elementor-search-form__input, {{WRAPPER}} .elementor-search-form--focus .elementor-search-form__icon, {{WRAPPER}} .elementor-lightbox .dialog-lightbox-close-button:hover, {{WRAPPER}}.elementor-search-form--skin-full_screen input[type="search"].elementor-search-form__input:focus' => 'color: {{VALUE}}; fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'input_background_color_focus',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.elementor-search-form--skin-full_screen) .elementor-search-form--focus .elementor-search-form__container' => 'background-color: {{VALUE}}',
					'{{WRAPPER}}.elementor-search-form--skin-full_screen input[type="search"].elementor-search-form__input:focus' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'input_border_color_focus',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.elementor-search-form--skin-full_screen) .elementor-search-form--focus .elementor-search-form__container' => 'border-color: {{VALUE}}',
					'{{WRAPPER}}.elementor-search-form--skin-full_screen input[type="search"].elementor-search-form__input:focus' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'input_box_shadow_focus',
				'separator' => 'default',
				'selector'  => '{{WRAPPER}}:not(.elementor-search-form--skin-full_screen) .elementor-search-form--focus .elementor-search-form__container',
				'condition' => array( 'skin!' => 'full_screen' ),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_control(
			'button_border_width',
			array(
				'label'      => esc_html__( 'Border Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}}:not(.elementor-search-form--skin-full_screen) .elementor-search-form__container' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}}.elementor-search-form--skin-full_screen input[type="search"].elementor-search-form__input' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'default'    => array( 'size' => 3 ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'selectors'  => array(
					'{{WRAPPER}}:not(.elementor-search-form--skin-full_screen) .elementor-search-form__container' => 'border-radius: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.elementor-search-form--skin-full_screen input[type="search"].elementor-search-form__input' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	// ----------------------------------------------------------- button style

	private function register_button_style_section(): void {
		$this->start_controls_section(
			'section_button_style',
			array(
				'label'     => esc_html__( 'Button', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'skin' => 'classic' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'button_typography',
				'global'    => array( 'default' => Global_Typography::TYPOGRAPHY_TEXT ),
				'selector'  => '{{WRAPPER}} .elementor-search-form__submit',
				'condition' => array( 'button_type' => 'text' ),
			)
		);

		$this->start_controls_tabs( 'tabs_button_colors' );

		$this->start_controls_tab( 'tab_button_normal', array( 'label' => esc_html__( 'Normal', 'piecyfer-core' ) ) );

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__submit' => '--e-search-form-submit-text-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
				/*
				 * A plain background-color, NOT a custom property.
				 *
				 * The neighbouring text-colour control does use
				 * --e-search-form-submit-text-color, and transcribing this one
				 * the same way was wrong: the global default then never reached
				 * the button, which fell back to the stylesheet's #54595f
				 * instead of the site's secondary #6E6F73. Two controls side by
				 * side, two different mechanisms.
				 */
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__submit' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_button_hover', array( 'label' => esc_html__( 'Hover', 'piecyfer-core' ) ) );

		$this->add_control(
			'button_text_color_hover',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__submit:hover' => '--e-search-form-submit-text-color: {{VALUE}}',
					'{{WRAPPER}} .elementor-search-form__submit:focus' => '--e-search-form-submit-text-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'button_background_color_hover',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__submit:hover' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .elementor-search-form__submit:focus' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'     => esc_html__( 'Icon Size', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'separator' => 'before',
				'condition' => array( 'button_type' => 'icon' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__submit' => '--e-search-form-submit-icon-size: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'button_width',
			array(
				'label'     => esc_html__( 'Width', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0.5, 'max' => 3, 'step' => 0.01 ) ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__submit' => 'min-width: calc( {{SIZE}} * {{size.SIZE}}{{size.UNIT}} )',
				),
			)
		);

		$this->end_controls_section();
	}

	// ----------------------------------------------------------- toggle style

	private function register_toggle_style_section(): void {
		$this->start_controls_section(
			'section_toggle_style',
			array(
				'label'     => esc_html__( 'Toggle Button', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'skin' => 'full_screen' ),
			)
		);

		$this->start_controls_tabs( 'tabs_toggle_colors' );

		$this->start_controls_tab( 'tab_toggle_normal', array( 'label' => esc_html__( 'Normal', 'piecyfer-core' ) ) );

		$this->add_control(
			'toggle_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__toggle' => '--e-search-form-toggle-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__toggle' => '--e-search-form-toggle-background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_toggle_hover', array( 'label' => esc_html__( 'Hover', 'piecyfer-core' ) ) );

		$this->add_control(
			'toggle_color_hover',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__toggle:hover' => '--e-search-form-toggle-color: {{VALUE}}',
					'{{WRAPPER}} .elementor-search-form__toggle:focus' => '--e-search-form-toggle-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_background_color_hover',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__toggle:hover' => '--e-search-form-toggle-background-color: {{VALUE}}',
					'{{WRAPPER}} .elementor-search-form__toggle:focus' => '--e-search-form-toggle-background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_control(
			'toggle_icon_size',
			array(
				'label'     => esc_html__( 'Icon Size', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__toggle' => '--e-search-form-toggle-icon-size: calc({{SIZE}}em / 100)',
				),
			)
		);

		$this->add_control(
			'toggle_border_width',
			array(
				'label'     => esc_html__( 'Border Width', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__toggle' => '--e-search-form-toggle-border-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'toggle_border_radius',
			array(
				'label'     => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}} .elementor-search-form__toggle' => '--e-search-form-toggle-border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	// ----------------------------------------------------------------- render

	protected function render_widget(): void {
		$settings = $this->get_settings_for_display();

		$this->add_render_attribute(
			'form',
			array(
				'class'  => 'elementor-search-form',
				'action' => esc_url( home_url() ),
				'method' => 'get',
			)
		);

		$this->add_render_attribute( 'container', array( 'class' => 'elementor-search-form__container' ) );

		$this->add_render_attribute(
			'label',
			array(
				'class' => 'elementor-screen-only',
				'for'   => 'elementor-search-form-' . $this->get_id(),
			)
		);

		$this->add_render_attribute(
			'input',
			array(
				'id'          => 'elementor-search-form-' . $this->get_id(),
				'placeholder' => $settings['placeholder'] ?? '',
				'class'       => 'elementor-search-form__input',
				'type'        => 'search',
				'name'        => 's',
				'value'       => get_search_query(),
			)
		);

		// The arrow variant flips with text direction, so it cannot be a fixed
		// class name.
		$icon_class = 'search';
		if ( 'icon' === ( $settings['button_type'] ?? 'icon' ) && 'arrow' === ( $settings['icon'] ?? '' ) ) {
			$icon_class = is_rtl() ? 'arrow-left' : 'arrow-right';
		}

		$this->add_render_attribute( 'icon', 'class', 'fa fa-' . $icon_class );

		$icon = array(
			'value'   => 'fas fa-' . $icon_class,
			'library' => 'fa-solid',
		);

		$skin = $settings['skin'] ?? 'classic';
		?>
		<search role="search">
			<form <?php $this->print_render_attribute_string( 'form' ); ?>>
				<?php
				/**
				 * Kept for parity with Elementor Pro: any site code hooked here
				 * must keep working after the swap.
				 *
				 * @param SearchFormWidget $this Widget instance.
				 */
				do_action( 'elementor_pro/search_form/before_input', $this );
				?>
				<?php if ( 'full_screen' === $skin ) : ?>
					<div class="elementor-search-form__toggle" tabindex="0" role="button">
						<?php $this->render_search_icon( $icon, array( 'aria-hidden' => 'true' ) ); ?>
						<span class="elementor-screen-only"><?php esc_html_e( 'Search', 'piecyfer-core' ); ?></span>
					</div>
				<?php endif; ?>
				<div <?php $this->print_render_attribute_string( 'container' ); ?>>
					<label <?php $this->print_render_attribute_string( 'label' ); ?>><?php esc_html_e( 'Search', 'piecyfer-core' ); ?></label>

					<?php if ( 'minimal' === $skin ) : ?>
						<div class="elementor-search-form__icon">
							<?php $this->render_search_icon( $icon, array( 'aria-hidden' => 'true' ) ); ?>
							<span class="elementor-screen-only"><?php esc_html_e( 'Search', 'piecyfer-core' ); ?></span>
						</div>
					<?php endif; ?>

					<input <?php $this->print_render_attribute_string( 'input' ); ?>>

					<?php
					/** @param SearchFormWidget $this Widget instance. */
					do_action( 'elementor_pro/search_form/after_input', $this );
					?>

					<?php if ( 'classic' === $skin ) : ?>
						<button class="elementor-search-form__submit" type="submit" aria-label="<?php esc_attr_e( 'Search', 'piecyfer-core' ); ?>">
							<?php if ( 'icon' === ( $settings['button_type'] ?? 'icon' ) ) : ?>
								<?php $this->render_search_icon( $icon, $this->get_render_attributes( 'icon' ) ); ?>
								<span class="elementor-screen-only"><?php esc_html_e( 'Search', 'piecyfer-core' ); ?></span>
							<?php elseif ( ! empty( $settings['button_text'] ) ) : ?>
								<?php $this->print_unescaped_setting( 'button_text' ); ?>
							<?php endif; ?>
						</button>
					<?php endif; ?>

					<?php if ( 'full_screen' === $skin ) : ?>
						<div class="dialog-lightbox-close-button dialog-close-button" role="button" tabindex="0">
							<?php
							Icons_Manager::render_icon(
								array(
									'library' => 'eicons',
									'value'   => 'eicon-close',
								),
								array( 'aria-hidden' => 'true' )
							);
							?>
							<span class="elementor-screen-only"><?php esc_html_e( 'Close this search box.', 'piecyfer-core' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</form>
		</search>
		<?php
	}

	/**
	 * Emit the search icon, honouring Elementor's SVG-icon experiment.
	 *
	 * With `e_font_icon_svg` active the icon renders as an inline SVG that needs
	 * an extra wrapper for its border box; without it, a font icon. Both paths
	 * exist in Pro and both appear on this site depending on the experiment's
	 * state, so both are reproduced.
	 *
	 * The attributes are passed in rather than fixed, because Pro does not use
	 * the same set everywhere: the toggle and the minimal skin pass only
	 * `aria-hidden`, while the classic skin passes the widget's own `icon`
	 * render attributes, which carry the `fa fa-search` classes and no
	 * `aria-hidden`. Hard-coding `aria-hidden` here produced the one markup
	 * difference that survived on every page.
	 *
	 * @param array<string,string>       $icon
	 * @param array<string,string|array> $attributes
	 */
	private function render_search_icon( array $icon, array $attributes = array() ): void {
		$experiments = \Elementor\Plugin::$instance->experiments;

		if ( $experiments->is_feature_active( 'e_font_icon_svg' ) ) {
			$html = Icons_Manager::render_font_icon( $icon, $attributes );
			echo '<div class="e-font-icon-svg-container">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		if ( ! Icons_Manager::is_migration_allowed() || ! Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ) ) {
			printf( '<i %s aria-hidden="true"></i>', $this->get_render_attribute_string( 'icon' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
