<?php
/**
 * Replacement for Elementor Pro's `blockquote` widget.
 *
 * 4 instances across 3 blog posts. Two of them set hide_desktop/tablet/mobile
 * and so never render, but their settings must still round-trip.
 *
 * The first replacement widget that needs a stylesheet of its own — Pro loads
 * `widget-blockquote` for this one. Ours is written from scratch in
 * assets/css/blockquote.css rather than copied out of the nulled install.
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

final class BlockquoteWidget extends AbstractWidget {

	public function get_name(): string {
		return 'blockquote';
	}

	public function get_title(): string {
		return esc_html__( 'Blockquote', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-blockquote';
	}

	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	public function get_keywords(): array {
		return array( 'blockquote', 'quote', 'paraphrase', 'citation', 'mention' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — Blockquote';
	}

	/**
	 * Pro declares this false; `Element_Base` defaults it to **true**.
	 *
	 * It decides whether the element is baked into the document's element cache
	 * or emitted as an `[elementor-element]` placeholder and re-rendered on
	 * every request. Not overriding it therefore silently changes the caching
	 * behaviour of the widget we are replacing, which is a difference no pixel
	 * comparison would ever show.
	 */
	protected function is_dynamic_content(): bool {
		return false;
	}

	/**
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'piecyfer-blockquote' );
	}

	protected function assets( string $handle ): void {
		// Registered centrally so the handle matches get_style_depends(), which
		// Elementor resolves before render_widget() runs.
	}

	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_content_style_controls();
		$this->register_button_style_controls();
		$this->register_border_skin_controls();
		$this->register_boxed_skin_controls();
		$this->register_quotation_skin_controls();
	}

	// ---------------------------------------------------------------- content

	private function register_content_controls(): void {
		$this->start_controls_section(
			'section_blockquote_content',
			array( 'label' => esc_html__( 'Blockquote', 'piecyfer-core' ) )
		);

		$this->add_control(
			'blockquote_skin',
			array(
				'label'        => esc_html__( 'Skin', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'border'    => esc_html__( 'Border', 'piecyfer-core' ),
					'quotation' => esc_html__( 'Quotation', 'piecyfer-core' ),
					'boxed'     => esc_html__( 'Boxed', 'piecyfer-core' ),
					'clean'     => esc_html__( 'Clean', 'piecyfer-core' ),
				),
				'default'      => 'border',
				// Adds elementor-blockquote--skin-<value> to the wrapper. All
				// four skins on this site rely on it for their styling.
				'prefix_class' => 'elementor-blockquote--skin-',
			)
		);

		$this->add_control(
			'alignment',
			array(
				'label'        => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type'         => Controls_Manager::CHOOSE,
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
				'prefix_class' => 'elementor-blockquote--align-',
				'separator'    => 'after',
				'condition'    => array( 'blockquote_skin!' => 'border' ),
			)
		);

		$this->add_control(
			'blockquote_content',
			array(
				'label'   => esc_html__( 'Content', 'piecyfer-core' ),
				'type'    => Controls_Manager::TEXTAREA,
				'dynamic' => array( 'active' => true ),
				'default' => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'piecyfer-core' )
					. esc_html__( 'Lorem ipsum dolor sit amet consectetur adipiscing elit dolor', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'author_name',
			array(
				'label'     => esc_html__( 'Author', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'default'   => esc_html__( 'John Doe', 'piecyfer-core' ),
				'separator' => 'after',
			)
		);

		$this->add_control(
			'tweet_button',
			array(
				'label'   => esc_html__( 'Tweet Button', 'piecyfer-core' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'tweet_button_view',
			array(
				'label'        => esc_html__( 'View', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'icon-text' => esc_html__( 'Icon & Text', 'piecyfer-core' ),
					'icon'      => esc_html__( 'Icon', 'piecyfer-core' ),
					'text'      => esc_html__( 'Text', 'piecyfer-core' ),
				),
				'prefix_class' => 'elementor-blockquote--button-view-',
				'default'      => 'icon-text',
				'render_type'  => 'template',
				'condition'    => array( 'tweet_button' => 'yes' ),
			)
		);

		$this->add_control(
			'tweet_button_skin',
			array(
				'label'        => esc_html__( 'Skin', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'classic' => esc_html__( 'Classic', 'piecyfer-core' ),
					'bubble'  => esc_html__( 'Bubble', 'piecyfer-core' ),
					'link'    => esc_html__( 'Link', 'piecyfer-core' ),
				),
				'default'      => 'classic',
				'prefix_class' => 'elementor-blockquote--button-skin-',
				'condition'    => array( 'tweet_button' => 'yes' ),
			)
		);

		$this->add_control(
			'tweet_button_label',
			array(
				'label'     => esc_html__( 'Label', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Tweet', 'piecyfer-core' ),
				'condition' => array(
					'tweet_button'       => 'yes',
					'tweet_button_view!' => 'icon',
				),
			)
		);

		$this->add_control(
			'user_name',
			array(
				'label'       => esc_html__( 'Username', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '@username',
				'condition'   => array( 'tweet_button' => 'yes' ),
			)
		);

		$this->add_control(
			'url_type',
			array(
				'label'     => esc_html__( 'Target URL', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'none'         => esc_html__( 'None', 'piecyfer-core' ),
					'current_page' => esc_html__( 'Current Page', 'piecyfer-core' ),
					'custom'       => esc_html__( 'Custom', 'piecyfer-core' ),
				),
				'default'   => 'current_page',
				'condition' => array( 'tweet_button' => 'yes' ),
			)
		);

		$this->add_control(
			'url',
			array(
				'label'     => esc_html__( 'URL', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'condition' => array(
					'tweet_button' => 'yes',
					'url_type'     => 'custom',
				),
			)
		);

		$this->end_controls_section();
	}

	// ------------------------------------------------------------ content style

	private function register_content_style_controls(): void {
		$this->start_controls_section(
			'section_content_style',
			array(
				'label' => esc_html__( 'Content', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'content_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_TEXT ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__content' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'content_typography',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_TEXT ),
				'selector' => '{{WRAPPER}} .elementor-blockquote__content',
			)
		);

		$this->add_responsive_control(
			'content_gap',
			array(
				'label'     => esc_html__( 'Gap', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__content +.e-q-footer' => 'margin-top: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'heading_author_style',
			array(
				'label'     => esc_html__( 'Author', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'author_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__author' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'author_typography',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_SECONDARY ),
				'selector' => '{{WRAPPER}} .elementor-blockquote__author',
			)
		);

		$this->add_responsive_control(
			'author_gap',
			array(
				'label'     => esc_html__( 'Gap', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'condition' => array( 'tweet_button' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__author' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	// ------------------------------------------------------------- button style

	private function register_button_style_controls(): void {
		$this->start_controls_section(
			/*
			 * Pro calls this section `section_button_style`, and the id is not
			 * cosmetic: third-party code injects controls at
			 * `elementor/element/blockquote/section_button_style/before_section_end`,
			 * which is how per-element Custom CSS and Motion FX attach. A
			 * renamed section silently drops those injections.
			 */
			'section_button_style',
			array(
				'label' => esc_html__( 'Button', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
				/*
				 * Deliberately NOT conditioned on `tweet_button`, matching Pro.
				 * Adding that condition looked harmless and was not: Elementor's
				 * get_active_settings() strips every control whose section
				 * condition fails, so `button_color_source` lost its value and
				 * its `prefix_class` never reached the wrapper. Both blockquotes
				 * on the blog post rendered without
				 * `elementor-blockquote--button-color-official`.
				 */
			)
		);

		$this->add_responsive_control(
			'button_size',
			array(
				'label'     => esc_html__( 'Size', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__tweet-button' => 'font-size: calc({{SIZE}}{{UNIT}} * 10);',
				),
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label'     => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__tweet-button' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'button_color_source',
			array(
				'label'        => esc_html__( 'Color', 'piecyfer-core' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'official' => esc_html__( 'Official', 'piecyfer-core' ),
					'custom'   => esc_html__( 'Custom', 'piecyfer-core' ),
				),
				'default'      => 'official',
				'prefix_class' => 'elementor-blockquote--button-color-',
			)
		);

		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'button_color_source' => 'custom',
					'tweet_button_skin!'  => 'link',
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__tweet-button' => 'background-color: {{VALUE}}',
					'body:not(.rtl) {{WRAPPER}} .elementor-blockquote__tweet-button:before, body {{WRAPPER}}.elementor-blockquote--align-left .elementor-blockquote__tweet-button:before' => 'border-right-color: {{VALUE}}; border-left-color: transparent',
					'body.rtl {{WRAPPER}} .elementor-blockquote__tweet-button:before, body {{WRAPPER}}.elementor-blockquote--align-right .elementor-blockquote__tweet-button:before' => 'border-left-color: {{VALUE}}; border-right-color: transparent',
				),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array( 'button_color_source' => 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__tweet-button'     => 'color: {{VALUE}}',
					'{{WRAPPER}} .elementor-blockquote__tweet-button svg' => 'fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'button_background_color_hover',
			array(
				'label'     => esc_html__( 'Background Hover Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'button_color_source' => 'custom',
					'tweet_button_skin!'  => 'link',
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__tweet-button:hover' => 'background-color: {{VALUE}}',
					'body:not(.rtl) {{WRAPPER}} .elementor-blockquote__tweet-button:hover:before, body {{WRAPPER}}.elementor-blockquote--align-left .elementor-blockquote__tweet-button:hover:before' => 'border-right-color: {{VALUE}}; border-left-color: transparent',
					'body.rtl {{WRAPPER}} .elementor-blockquote__tweet-button:hover:before, body {{WRAPPER}}.elementor-blockquote--align-right .elementor-blockquote__tweet-button:hover:before' => 'border-left-color: {{VALUE}}; border-right-color: transparent',
				),
			)
		);

		$this->add_control(
			'button_text_color_hover',
			array(
				'label'     => esc_html__( 'Text Hover Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array( 'button_color_source' => 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__tweet-button:hover'     => 'color: {{VALUE}}',
					'{{WRAPPER}} .elementor-blockquote__tweet-button:hover svg' => 'fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'button_transition_duration',
			array(
				'label'     => esc_html__( 'Transition Duration', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array( 'size' => 0.2 ),
				'range'     => array( 'px' => array( 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__tweet-button' => 'transition-duration: {{SIZE}}{{UNIT}};',
				),
			)
		);

		// Not populated anywhere on this site, so it emits no CSS today — but a
		// missing control is a missing CSS rule the moment anyone touches the
		// button in the editor, which is exactly the failure this project is
		// built to avoid. Reproduced with Pro's split selector: the group styles
		// the span and the i, while font-family alone is applied to the button.
		$default_fonts = \Elementor\Plugin::$instance->kits_manager->get_current_settings( 'default_generic_fonts' );
		$default_fonts = $default_fonts ? ', ' . $default_fonts : '';

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'button_typography',
				'selector'       => '{{WRAPPER}} .elementor-blockquote__tweet-button span, {{WRAPPER}} .elementor-blockquote__tweet-button i',
				'separator'      => 'before',
				'fields_options' => array(
					'font_family' => array(
						'selectors' => array(
							'{{WRAPPER}} .elementor-blockquote__tweet-button' => 'font-family: "{{VALUE}}"' . $default_fonts . ';',
						),
					),
				),
			)
		);

		$this->end_controls_section();
	}

	// ------------------------------------------------------- border-skin style

	private function register_border_skin_controls(): void {
		$this->start_controls_section(
			'section_border_style',
			array(
				'label'     => esc_html__( 'Border', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'blockquote_skin' => 'border' ),
			)
		);

		$this->add_control(
			'border_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .elementor-blockquote' => 'border-color: {{VALUE}}' ),
			)
		);

		$this->add_responsive_control(
			'border_width',
			array(
				'label'     => esc_html__( 'Width', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'body:not(.rtl) {{WRAPPER}} .elementor-blockquote' => 'border-left-width: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .elementor-blockquote'       => 'border-right-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'border_gap',
			array(
				'label'     => esc_html__( 'Gap', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'body:not(.rtl) {{WRAPPER}} .elementor-blockquote' => 'padding-left: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .elementor-blockquote'       => 'padding-right: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'border_color_hover',
			array(
				'label'     => esc_html__( 'Hover Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .elementor-blockquote:hover' => 'border-color: {{VALUE}}' ),
			)
		);

		$this->add_responsive_control(
			'border_width_hover',
			array(
				'label'     => esc_html__( 'Hover Width', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'body:not(.rtl) {{WRAPPER}} .elementor-blockquote:hover' => 'border-left-width: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .elementor-blockquote:hover'       => 'border-right-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'border_gap_hover',
			array(
				'label'     => esc_html__( 'Hover Gap', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'body:not(.rtl) {{WRAPPER}} .elementor-blockquote:hover' => 'padding-left: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .elementor-blockquote:hover'       => 'padding-right: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'border_transition_duration',
			array(
				'label'     => esc_html__( 'Transition Duration', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array( 'size' => 0.3 ),
				'range'     => array( 'px' => array( 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote' => 'transition-duration: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'border_vertical_padding',
			array(
				'label'     => esc_html__( 'Vertical Padding', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'separator' => 'before',
				'condition' => array( 'blockquote_skin' => 'border' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	// -------------------------------------------------------- boxed-skin style

	private function register_boxed_skin_controls(): void {
		$this->start_controls_section(
			'section_box_style',
			array(
				'label'     => esc_html__( 'Box', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'blockquote_skin' => 'boxed' ),
			)
		);

		$this->add_responsive_control(
			'box_padding',
			array(
				'label'     => esc_html__( 'Padding', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array( '{{WRAPPER}} .elementor-blockquote' => 'padding: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_control(
			'box_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .elementor-blockquote' => 'background-color: {{VALUE}}' ),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'box_border',
				'selector' => '{{WRAPPER}} .elementor-blockquote',
			)
		);

		$this->add_responsive_control(
			'box_border_radius',
			array(
				'label'     => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array( '{{WRAPPER}} .elementor-blockquote' => 'border-radius: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_box_shadow',
				'selector' => '{{WRAPPER}} .elementor-blockquote',
			)
		);

		$this->add_control(
			'box_background_color_hover',
			array(
				'label'     => esc_html__( 'Background Hover Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .elementor-blockquote:hover' => 'background-color: {{VALUE}}' ),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'box_border_hover',
				'selector' => '{{WRAPPER}} .elementor-blockquote:hover',
			)
		);

		$this->add_responsive_control(
			'box_border_radius_hover',
			array(
				'label'     => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array( '{{WRAPPER}} .elementor-blockquote:hover' => 'border-radius: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_box_shadow_hover',
				'selector' => '{{WRAPPER}} .elementor-blockquote:hover',
			)
		);

		$this->add_control(
			'box_transition_duration',
			array(
				'label'     => esc_html__( 'Transition Duration', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array( 'size' => 0.2 ),
				'range'     => array( 'px' => array( 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array( '{{WRAPPER}} .elementor-blockquote' => 'transition-duration: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	// ---------------------------------------------------- quotation-skin style

	private function register_quotation_skin_controls(): void {
		$this->start_controls_section(
			'section_quote_style',
			array(
				'label'     => esc_html__( 'Quotation Mark', 'piecyfer-core' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'blockquote_skin' => 'quotation' ),
			)
		);

		$this->add_control(
			'quote_text_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .elementor-blockquote:before' => 'color: {{VALUE}}' ),
			)
		);

		$this->add_responsive_control(
			'quote_size',
			array(
				'label' => esc_html__( 'Size', 'piecyfer-core' ),
				'type'  => Controls_Manager::SLIDER,
				/*
				 * Default size 1, giving calc(1 * 100) = 100px — not 0.5.
				 *
				 * Getting this wrong shortened the quotation mark from 100px to
				 * 50px, which shortened the whole page by exactly 30px on every
				 * viewport. A default is not cosmetic when a control feeds a
				 * calc(): it is the rendered value for every instance that never
				 * set one, which is all four here.
				 */
				'default'   => array( 'size' => 1 ),
				'range'     => array( 'px' => array( 'min' => 0.5, 'max' => 2, 'step' => 0.1 ) ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote:before' => 'font-size: calc({{SIZE}}{{UNIT}} * 100)',
				),
			)
		);

		$this->add_responsive_control(
			'quote_gap',
			array(
				'label'     => esc_html__( 'Gap', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}} .elementor-blockquote__content' => 'margin-top: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	// ----------------------------------------------------------------- render

	protected function render_widget(): void {
		$settings = $this->get_settings_for_display();

		if ( empty( $settings['blockquote_content'] ) && empty( $settings['author_name'] ) && 'yes' !== ( $settings['tweet_button'] ?? '' ) ) {
			return;
		}

		$this->add_render_attribute(
			array(
				'blockquote_content' => array( 'class' => 'elementor-blockquote__content' ),
				'author_name'        => array( 'class' => 'elementor-blockquote__author' ),
				'tweet_button_label' => array( 'class' => 'elementor-blockquote__tweet-label' ),
			)
		);

		$this->add_inline_editing_attributes( 'blockquote_content' );
		$this->add_inline_editing_attributes( 'author_name', 'none' );
		$this->add_inline_editing_attributes( 'tweet_button_label', 'none' );

		$show_button = 'yes' === ( $settings['tweet_button'] ?? '' );
		$view        = $settings['tweet_button_view'] ?? 'icon-text';
		?>
		<blockquote class="elementor-blockquote">
			<p <?php $this->print_render_attribute_string( 'blockquote_content' ); ?>>
				<?php $this->print_unescaped_setting( 'blockquote_content' ); ?>
			</p>
			<?php if ( ! empty( $settings['author_name'] ) || $show_button ) : ?>
				<div class="e-q-footer">
					<?php if ( ! empty( $settings['author_name'] ) ) : ?>
						<cite <?php $this->print_render_attribute_string( 'author_name' ); ?>><?php $this->print_unescaped_setting( 'author_name' ); ?></cite>
					<?php endif; ?>
					<?php if ( $show_button ) : ?>
						<a href="<?php echo esc_attr( $this->get_share_link( $settings ) ); ?>" class="elementor-blockquote__tweet-button" target="_blank">
							<?php if ( 'text' !== $view ) : ?>
								<?php
								$icon = array(
									'value'   => 'fab fa-twitter',
									'library' => 'fa-brands',
								);
								if ( ! Icons_Manager::is_migration_allowed() || ! Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ) ) :
									?>
									<i class="fa fa-twitter" aria-hidden="true"></i>
								<?php endif; ?>
								<?php if ( 'icon-text' !== $view ) : ?>
									<span class="elementor-screen-only"><?php esc_html_e( 'Tweet', 'piecyfer-core' ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( 'icon-text' === $view || 'text' === $view ) : ?>
								<span <?php $this->print_render_attribute_string( 'tweet_button_label' ); ?>><?php $this->print_unescaped_setting( 'tweet_button_label' ); ?></span>
							<?php endif; ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</blockquote>
		<?php
	}

	/**
	 * Build the tweet intent URL exactly as Pro does — same parameter order, so
	 * the rendered href is byte-identical.
	 *
	 * @param array<string,mixed> $settings
	 */
	private function get_share_link( array $settings ): string {
		$link = 'https://twitter.com/intent/tweet';
		$text = rawurlencode( (string) ( $settings['blockquote_content'] ?? '' ) );

		if ( ! empty( $settings['author_name'] ) ) {
			$text .= ' — ' . $settings['author_name'];
		}

		$link = add_query_arg( 'text', $text, $link );

		if ( 'current_page' === ( $settings['url_type'] ?? '' ) ) {
			$link = add_query_arg( 'url', rawurlencode( home_url() . add_query_arg( false, false ) ), $link );
		} elseif ( 'custom' === ( $settings['url_type'] ?? '' ) ) {
			$link = add_query_arg( 'url', rawurlencode( (string) ( $settings['url'] ?? '' ) ), $link );
		}

		if ( ! empty( $settings['user_name'] ) ) {
			$user = ltrim( (string) $settings['user_name'], '@' );
			$link = add_query_arg( 'via', rawurlencode( $user ), $link );
		}

		return $link;
	}
}
