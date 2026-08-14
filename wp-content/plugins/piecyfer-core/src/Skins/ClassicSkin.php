<?php
/**
 * Port of `ElementorPro\Modules\Posts\Skins\Skin_Classic`
 * (elementor-pro/modules/posts/skins/skin-classic.php).
 *
 * Not used by any saved instance on this site — all 15 select `vamtam_classic`
 * — but it is registered for three reasons:
 *
 *   1. `VamtamClassicSkin` extends it, and inherits the Box section *and* the
 *      hook that places it (see `_register_controls_actions()` below).
 *   2. 46 dead `classic_*` keys survive in `_elementor_data` from before someone
 *      switched the skin. They are never read, but the control set is what makes
 *      them survive an editor save unchanged.
 *   3. Determinism: `Skins_Manager` keys skins by widget *name*, so leaving a
 *      slot unfilled means someone else's object could occupy it.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Skins;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;

defined( 'ABSPATH' ) || exit;

class ClassicSkin extends SkinBase {

	/**
	 * Note the hook name: `classic_section_design_layout`, hard-coded, **not**
	 * derived from `get_id()`. `VamtamClassicSkin` inherits this method
	 * unchanged, which is why `vamtam_classic_section_design_box` is registered
	 * immediately after the *classic* skin's Layout section closes rather than
	 * after its own. That interleaving is real, it is what the saved data was
	 * built against, and VamTam's own injection hook
	 * `elementor/element/posts/vamtam_classic_section_design_box/before_section_end`
	 * depends on the section existing at that moment.
	 */
	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		parent::_register_controls_actions();

		add_action( 'elementor/element/posts/classic_section_design_layout/after_section_end', [ $this, 'register_additional_design_controls' ] );
	}

	public function get_id() {
		return 'classic';
	}

	public function get_title() {
		return esc_html__( 'Classic', 'piecyfer-core' );
	}

	public function register_additional_design_controls() {
		$this->start_controls_section(
			'section_design_box',
			[
				'label' => esc_html__( 'Box', 'piecyfer-core' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'box_border_width',
			[
				'label' => esc_html__( 'Border Width', 'piecyfer-core' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'border-style: solid; border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
			]
		);

		$this->add_responsive_control(
			'box_border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'max' => 200,
					],
					'em' => [
						'max' => 20,
					],
					'rem' => [
						'max' => 20,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'border-radius: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_responsive_control(
			'box_padding',
			[
				'label' => esc_html__( 'Padding', 'piecyfer-core' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 50,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
			]
		);

		$this->add_responsive_control(
			'content_padding',
			[
				'label' => esc_html__( 'Content Padding', 'piecyfer-core' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'range' => [
					'px' => [
						'max' => 50,
					],
					'em' => [
						'max' => 5,
					],
					'rem' => [
						'max' => 5,
					],
				],
				/*
				 * VamTam merges a second selector into this control at
				 * `…/vamtam_classic_section_design_box/before_section_end`:
				 * `{{WRAPPER}} => --vamtam-content-padding: …`. Both end up in
				 * the generated CSS; the theme's
				 * `.elementor-post > .elementor-post__meta-data` rule reads the
				 * custom property. That merge is VamTam's and is not duplicated
				 * here — it still fires, because the section id is unchanged.
				 */
				'selectors' => [
					'{{WRAPPER}} .elementor-post__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
				'separator' => 'after',
			]
		);

		$this->start_controls_tabs( 'bg_effects_tabs' );

		$this->start_controls_tab( 'classic_style_normal',
			[
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'box_shadow',
				'selector' => '{{WRAPPER}} .elementor-post',
			]
		);

		$this->add_control(
			'box_bg_color',
			[
				'label' => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'box_border_color',
			[
				'label' => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'border-color: {{VALUE}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'classic_style_hover',
			[
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'box_shadow_hover',
				'selector' => '{{WRAPPER}} .elementor-post:hover',
			]
		);

		$this->add_control(
			'box_bg_color_hover',
			[
				'label' => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-post:hover' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'box_border_color_hover',
			[
				'label' => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-post:hover' => 'border-color: {{VALUE}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}
}
