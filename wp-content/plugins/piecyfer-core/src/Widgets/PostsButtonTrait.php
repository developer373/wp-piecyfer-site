<?php
/**
 * Port of Elementor Pro's `ElementorPro\Modules\Posts\Traits\Button_Widget_Trait`.
 *
 * Pro mixes this trait into two very different places:
 *
 *   - `Posts_Base` (the widget), which calls register_button_content_controls()
 *     and register_button_style_controls() to build the Load More button's
 *     controls at widget level — unprefixed ids such as `text`,
 *     `button_text_color`, `typography_typography`.
 *   - `Skins\Skin_Base` (the skin), which only ever calls render_button() and
 *     render_text(). The control-registration halves are never reached from a
 *     skin — which matters, because `Elementor\Skin_Base::add_control()` would
 *     prefix every id with the skin id and produce a completely different
 *     control set.
 *
 * Both call sites are reproduced here, in one trait, exactly as Pro has it.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

trait PostsButtonTrait {

	/**
	 * Retained for parity with Pro; `size` is removed from the posts widgets by
	 * `Posts_Base::register_pagination_section_controls()` immediately after the
	 * content controls are registered, so these options never reach the panel.
	 *
	 * @return array<string,string>
	 */
	public static function get_button_sizes() {
		return [
			'xs' => esc_html__( 'Extra Small', 'piecyfer-core' ),
			'sm' => esc_html__( 'Small', 'piecyfer-core' ),
			'md' => esc_html__( 'Medium', 'piecyfer-core' ),
			'lg' => esc_html__( 'Large', 'piecyfer-core' ),
			'xl' => esc_html__( 'Extra Large', 'piecyfer-core' ),
		];
	}

	protected function register_button_content_controls( $args = [] ) {
		$default_args = [
			'section_condition' => [],
			'button_text' => esc_html__( 'Click here', 'piecyfer-core' ),
			'control_label_name' => esc_html__( 'Text', 'piecyfer-core' ),
			'exclude_inline_options' => [],
		];

		$args = wp_parse_args( $args, $default_args );

		$this->add_control(
			'button_type',
			[
				'label' => esc_html__( 'Type', 'piecyfer-core' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( 'Default', 'piecyfer-core' ),
					'info' => esc_html__( 'Info', 'piecyfer-core' ),
					'success' => esc_html__( 'Success', 'piecyfer-core' ),
					'warning' => esc_html__( 'Warning', 'piecyfer-core' ),
					'danger' => esc_html__( 'Danger', 'piecyfer-core' ),
				],
				'prefix_class' => 'elementor-button-',
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'text',
			[
				'label' => $args['control_label_name'],
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'default' => $args['button_text'],
				'placeholder' => $args['button_text'],
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'link',
			[
				'label' => esc_html__( 'Link', 'piecyfer-core' ),
				'type' => Controls_Manager::URL,
				'dynamic' => [
					'active' => true,
				],
				'default' => [
					'url' => '#',
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'size',
			[
				'label' => esc_html__( 'Size', 'piecyfer-core' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'sm',
				'options' => self::get_button_sizes(),
				'style_transfer' => true,
				// A workaround Pro uses to hide the control unless it is in use.
				'condition' => array_merge( $args['section_condition'], [ 'size[value]!' => 'sm' ] ),
			]
		);

		$this->add_control(
			'selected_icon',
			[
				'label' => esc_html__( 'Icon', 'piecyfer-core' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'skin' => 'inline',
				'label_block' => false,
				'condition' => $args['section_condition'],
				'exclude_inline_options' => $args['exclude_inline_options'],
			]
		);

		$start = is_rtl() ? 'right' : 'left';
		$end = is_rtl() ? 'left' : 'right';

		$this->add_control(
			'icon_align',
			[
				'label' => esc_html__( 'Icon Position', 'piecyfer-core' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => is_rtl() ? 'row-reverse' : 'row',
				'options' => [
					'row' => [
						'title' => esc_html__( 'Start', 'piecyfer-core' ),
						'icon' => "eicon-h-align-{$start}",
					],
					'row-reverse' => [
						'title' => esc_html__( 'End', 'piecyfer-core' ),
						'icon' => "eicon-h-align-{$end}",
					],
				],
				'selectors_dictionary' => [
					'left' => is_rtl() ? 'row-reverse' : 'row',
					'right' => is_rtl() ? 'row' : 'row-reverse',
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button-content-wrapper' => 'flex-direction: {{VALUE}};',
				],
				'condition' => array_merge(
					$args['section_condition'],
					[
						'text!' => '',
						'selected_icon[value]!' => '',
					]
				),
			]
		);

		$this->add_control(
			'icon_indent',
			[
				'label' => esc_html__( 'Icon Spacing', 'piecyfer-core' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
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
				'selectors' => [
					'{{WRAPPER}} .elementor-button .elementor-button-content-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
				],
				'condition' => array_merge(
					$args['section_condition'],
					[
						'text!' => '',
						'selected_icon[value]!' => '',
					]
				),
			]
		);

		$this->add_control(
			'button_css_id',
			[
				'label' => esc_html__( 'Button ID', 'piecyfer-core' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => '',
				'title' => esc_html__( 'Add your custom id WITHOUT the Pound key. e.g: my-id', 'piecyfer-core' ),
				'description' => sprintf(
					/* translators: 1: `<code>` opening tag, 2: `</code>` closing tag. */
					esc_html__( 'Please make sure the ID is unique and not used elsewhere on the page. This field allows %1$sA-z 0-9%2$s & underscore chars without spaces.', 'piecyfer-core' ),
					'<code>',
					'</code>'
				),
				'separator' => 'before',
				'condition' => $args['section_condition'],
			]
		);
	}

	protected function register_button_style_controls( $args = [] ) {
		$default_args = [
			'section_condition' => [],
			'prefix_class' => 'elementor%s-align-',
			'alignment_default' => '',
			'content_alignment_default' => '',
		];

		$args = wp_parse_args( $args, $default_args );

		$this->add_responsive_control(
			'align',
			[
				'label' => esc_html__( 'Position', 'piecyfer-core' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left'    => [
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon' => 'eicon-h-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon' => 'eicon-h-align-center',
					],
					'right' => [
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon' => 'eicon-h-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Stretch', 'piecyfer-core' ),
						'icon' => 'eicon-h-align-stretch',
					],
				],
				'prefix_class' => $args['prefix_class'],
				'default' => $args['alignment_default'],
				'condition' => $args['section_condition'],
			]
		);

		$start = is_rtl() ? 'right' : 'left';
		$end = is_rtl() ? 'left' : 'right';

		$this->add_responsive_control(
			'content_align',
			[
				'label' => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start'    => [
						'title' => esc_html__( 'Start', 'piecyfer-core' ),
						'icon' => "eicon-text-align-{$start}",
					],
					'center' => [
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'End', 'piecyfer-core' ),
						'icon' => "eicon-text-align-{$end}",
					],
					'space-between' => [
						'title' => esc_html__( 'Space between', 'piecyfer-core' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'default' => $args['content_alignment_default'],
				'selectors' => [
					'{{WRAPPER}} .elementor-button .elementor-button-content-wrapper' => 'justify-content: {{VALUE}};',
				],
				'condition' => array_merge( $args['section_condition'], [ 'align' => 'justify' ] ),
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				],
				'selector' => '{{WRAPPER}} .elementor-button',
				'condition' => $args['section_condition'],
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'text_shadow',
				'selector' => '{{WRAPPER}} .elementor-button',
				'condition' => $args['section_condition'],
			]
		);

		$this->start_controls_tabs( 'tabs_button_style', [
			'condition' => $args['section_condition'],
		] );

		$this->start_controls_tab(
			'tab_button_normal',
			[
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label' => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'fill: {{VALUE}}; color: {{VALUE}};',
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'background',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => '{{WRAPPER}} .elementor-button',
				'fields_options' => [
					'background' => [
						'default' => 'classic',
					],
					'color' => [
						'global' => [
							'default' => Global_Colors::COLOR_ACCENT,
						],
					],
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			[
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'hover_color',
			[
				'label' => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover, {{WRAPPER}} .elementor-button:focus' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button:hover svg, {{WRAPPER}} .elementor-button:focus svg' => 'fill: {{VALUE}};',
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'button_background_hover',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => '{{WRAPPER}} .elementor-button:hover, {{WRAPPER}} .elementor-button:focus',
				'fields_options' => [
					'background' => [
						'default' => 'classic',
					],
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'button_hover_border_color',
			[
				'label' => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover, {{WRAPPER}} .elementor-button:focus' => 'border-color: {{VALUE}};',
				],
				/*
				 * Pro writes `condition` twice in this array literal — first
				 * `[ 'border_border!' => '' ]`, then `$args['section_condition']`.
				 * PHP keeps the last one, so the `border_border` condition never
				 * reaches the stack. Only the effective value is written here;
				 * reproducing the dead key would change the control.
				 */
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'button_hover_transition_duration',
			[
				'label' => esc_html__( 'Transition Duration', 'piecyfer-core' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'default' => [
					'unit' => 's',
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'transition-duration: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'hover_animation',
			[
				'label' => esc_html__( 'Hover Animation', 'piecyfer-core' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
				'condition' => $args['section_condition'],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'border',
				'selector' => '{{WRAPPER}} .elementor-button',
				'separator' => 'before',
				'condition' => $args['section_condition'],
			]
		);

		$this->add_control(
			'border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'condition' => $args['section_condition'],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'button_box_shadow',
				'selector' => '{{WRAPPER}} .elementor-button',
				'condition' => $args['section_condition'],
			]
		);

		$this->add_responsive_control(
			'text_padding',
			[
				'label' => esc_html__( 'Padding', 'piecyfer-core' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
				'condition' => $args['section_condition'],
			]
		);
	}

	/**
	 * The Load More button.
	 *
	 * Called from the *skin*, with the widget passed in as `$instance` — the
	 * skin has no render attributes of its own.
	 *
	 * @param \Elementor\Widget_Base|null $instance
	 */
	protected function render_button( Widget_Base $instance = null ) {
		if ( empty( $instance ) ) {
			$instance = $this;
		}

		$settings = $instance->get_settings();

		if ( empty( $settings['text'] ) && empty( $settings['selected_icon']['value'] ) ) {
			return;
		}

		$instance->add_render_attribute( 'wrapper', 'class', 'elementor-button-wrapper' );

		if ( ! empty( $settings['link']['url'] ) ) {
			$instance->add_link_attributes( 'button', $settings['link'] );
			$instance->add_render_attribute( 'button', 'class', 'elementor-button-link' );
		}

		$instance->add_render_attribute( 'button', 'class', 'elementor-button' );
		$instance->add_render_attribute( 'button', 'role', 'button' );

		if ( ! empty( $settings['button_css_id'] ) ) {
			$instance->add_render_attribute( 'button', 'id', $settings['button_css_id'] );
		}

		if ( ! empty( $settings['size'] ) ) {
			$instance->add_render_attribute( 'button', 'class', 'elementor-size-' . $settings['size'] );
		}

		if ( $settings['hover_animation'] ) {
			$instance->add_render_attribute( 'button', 'class', 'elementor-animation-' . $settings['hover_animation'] );
		}
		?>
		<div <?php $instance->print_render_attribute_string( 'wrapper' ); ?>>
			<a <?php $instance->print_render_attribute_string( 'button' ); ?>>
				<?php $this->render_text( $instance ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * @param \Elementor\Widget_Base $instance
	 */
	protected function render_text( Widget_Base $instance ) {
		$settings = $instance->get_settings();

		$migrated = isset( $settings['__fa4_migrated']['selected_icon'] );
		$is_new = empty( $settings['icon'] ) && Icons_Manager::is_migration_allowed();

		$instance->add_render_attribute( [
			'content-wrapper' => [
				'class' => 'elementor-button-content-wrapper',
			],
			'icon' => [
				'class' => 'elementor-button-icon',
			],
			'text' => [
				'class' => 'elementor-button-text',
			],
		] );

		?>
		<span <?php $instance->print_render_attribute_string( 'content-wrapper' ); ?>>
			<?php if ( ! empty( $settings['icon'] ) || ! empty( $settings['selected_icon']['value'] ) ) : ?>
				<span <?php $instance->print_render_attribute_string( 'icon' ); ?>>
				<?php if ( $is_new || $migrated ) :
					Icons_Manager::render_icon( $settings['selected_icon'], [ 'aria-hidden' => 'true' ] );
				else : ?>
					<i class="<?php echo esc_attr( $settings['icon'] ); ?>" aria-hidden="true"></i>
				<?php endif; ?>
			</span>
			<?php endif; ?>
			<?php if ( ! empty( $settings['text'] ) ) : ?>
			<span <?php $instance->print_render_attribute_string( 'text' ); ?>><?php $instance->print_unescaped_setting( 'text' ); ?></span>
			<?php endif; ?>
		</span>
		<?php
	}

	public function on_import( $element ) {
		return Icons_Manager::on_import_migration( $element, 'icon', 'selected_icon' );
	}
}
