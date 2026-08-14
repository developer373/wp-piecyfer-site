<?php
/**
 * Replacement for Elementor Pro's `post-info` widget.
 *
 * 1 instance, in the Blog Post Template: a single repeater row showing the
 * publish date with a custom `j F Y` format and no icon.
 *
 * Only two of its keys are populated on this site, but the whole control set is
 * reproduced anyway: Elementor drops saved values that have no matching control
 * the next time a document is saved, so a widget that renders correctly today
 * can still destroy data the first time someone opens it in the editor.
 *
 * The list layout is Elementor FREE's `widget-icon-list` — post-info reuses it
 * rather than styling itself, so we keep depending on that handle and only
 * replace Pro's small `widget-post-info` layer.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

final class PostInfoWidget extends AbstractWidget {

	public function get_name(): string {
		return 'post-info';
	}

	public function get_title(): string {
		return esc_html__( 'Post Info', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-post-info';
	}

	public function get_categories(): array {
		return array( 'theme-elements-single' );
	}

	public function get_keywords(): array {
		return array( 'post', 'info', 'date', 'time', 'author', 'taxonomy', 'comments', 'terms', 'avatar' );
	}

	public function get_group_name(): string {
		return 'theme-elements';
	}

	protected function replaces(): string {
		return 'Elementor Pro — ThemeElements/Post_Info';
	}

	/**
	 * `widget-icon-list` is Elementor free's and stays; only Pro's
	 * `widget-post-info` is replaced. The Font Awesome handles are conditional
	 * in Pro too — without migration the icons render as `<i>` from a different
	 * sprite and these stylesheets are not wanted.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		$depends = array( 'piecyfer-post-info', 'widget-icon-list' );

		if ( Icons_Manager::is_migration_allowed() ) {
			$depends[] = 'elementor-icons-fa-regular';
			$depends[] = 'elementor-icons-fa-solid';
		}

		return $depends;
	}

	/**
	 * Mirrors Pro. Elementor inlines core widget CSS under the optimised-CSS
	 * experiment, and it needs telling that icon-list is a core dependency of
	 * this widget or the inlined bundle omits it.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_inline_css_depends(): array {
		return array(
			array(
				'name'               => 'icon-list',
				'is_core_dependency' => true,
			),
		);
	}

	protected function assets( string $handle ): void {
		// Registered centrally so the handle matches get_style_depends().
	}

	// -------------------------------------------------------------- controls

	protected function register_controls(): void {
		$this->register_meta_section();
		$this->register_list_style_section();
		$this->register_icon_style_section();
		$this->register_text_style_section();
	}

	private function register_meta_section(): void {
		$this->start_controls_section(
			'section_icon',
			array( 'label' => esc_html__( 'Meta Data', 'piecyfer-core' ) )
		);

		$this->add_control(
			'view',
			array(
				'label'       => esc_html__( 'Layout', 'piecyfer-core' ),
				'type'        => Controls_Manager::CHOOSE,
				'default'     => 'inline',
				'options'     => array(
					'traditional' => array(
						'title' => esc_html__( 'Default', 'piecyfer-core' ),
						'icon'  => 'eicon-editor-list-ul',
					),
					'inline'      => array(
						'title' => esc_html__( 'Inline', 'piecyfer-core' ),
						'icon'  => 'eicon-ellipsis-h',
					),
				),
				'render_type' => 'template',
				'classes'     => 'elementor-control-start-end',
			)
		);

		$this->add_control(
			'icon_list',
			array(
				'label'       => '',
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $this->build_repeater()->get_controls(),
				'default'     => array(
					array(
						'type'          => 'author',
						'selected_icon' => array(
							'value'   => 'far fa-user-circle',
							'library' => 'fa-regular',
						),
					),
					array(
						'type'          => 'date',
						'selected_icon' => array(
							'value'   => 'fas fa-calendar',
							'library' => 'fa-solid',
						),
					),
					array(
						'type'          => 'time',
						'selected_icon' => array(
							'value'   => 'far fa-clock',
							'library' => 'fa-regular',
						),
					),
					array(
						'type'          => 'comments',
						'selected_icon' => array(
							'value'   => 'far fa-comment-dots',
							'library' => 'fa-regular',
						),
					),
				),
				'title_field' => '{{{ elementor.helpers.renderIcon( this, selected_icon, {}, "i", "panel" ) || \'<i class="{{ icon }}" aria-hidden="true"></i>\' }}} <span style="text-transform: capitalize;">{{{ type }}}</span>',
			)
		);

		$this->end_controls_section();
	}

	private function build_repeater(): Repeater {
		$repeater = new Repeater();

		$repeater->add_control(
			'type',
			array(
				'label'   => esc_html__( 'Type', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'author'   => esc_html__( 'Author', 'piecyfer-core' ),
					'date'     => esc_html__( 'Date', 'piecyfer-core' ),
					'time'     => esc_html__( 'Time', 'piecyfer-core' ),
					'comments' => esc_html__( 'Comments', 'piecyfer-core' ),
					'terms'    => esc_html__( 'Terms', 'piecyfer-core' ),
					'custom'   => esc_html__( 'Custom', 'piecyfer-core' ),
				),
			)
		);

		$repeater->add_control(
			'date_format',
			array(
				'label'     => esc_html__( 'Date Format', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'default',
				'options'   => array(
					'default' => 'Default',
					'0'       => _x( 'March 6, 2018 (F j, Y)', 'Date Format', 'piecyfer-core' ),
					'1'       => '2018-03-06 (Y-m-d)',
					'2'       => '03/06/2018 (m/d/Y)',
					'3'       => '06/03/2018 (d/m/Y)',
					'custom'  => esc_html__( 'Custom', 'piecyfer-core' ),
				),
				'condition' => array( 'type' => 'date' ),
			)
		);

		$repeater->add_control(
			'custom_date_format',
			array(
				'label'       => esc_html__( 'Custom Date Format', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'F j, Y',
				'condition'   => array(
					'type'        => 'date',
					'date_format' => 'custom',
				),
				'description' => sprintf(
					/* translators: %s: Allowed date letters (see: http://php.net/manual/en/function.date.php). */
					esc_html__( 'Use the letters: %s', 'piecyfer-core' ),
					'l D d j S F m M n Y y'
				),
				'ai'          => array( 'active' => false ),
			)
		);

		$repeater->add_control(
			'time_format',
			array(
				'label'     => esc_html__( 'Time Format', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'default',
				'options'   => array(
					'default' => 'Default',
					'0'       => '3:31 pm (g:i a)',
					'1'       => '3:31 PM (g:i A)',
					'2'       => '15:31 (H:i)',
					'custom'  => esc_html__( 'Custom', 'piecyfer-core' ),
				),
				'condition' => array( 'type' => 'time' ),
			)
		);

		$repeater->add_control(
			'custom_time_format',
			array(
				'label'       => esc_html__( 'Custom Time Format', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'g:i a',
				'placeholder' => 'g:i a',
				'condition'   => array(
					'type'        => 'time',
					'time_format' => 'custom',
				),
				'description' => sprintf(
					/* translators: %s: Allowed time letters. */
					esc_html__( 'Use the letters: %s', 'piecyfer-core' ),
					'g G H i a A'
				),
				'ai'          => array( 'active' => false ),
			)
		);

		$repeater->add_control(
			'taxonomy',
			array(
				'label'       => esc_html__( 'Taxonomy', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'default'     => array(),
				'options'     => $this->get_taxonomy_options(),
				'condition'   => array( 'type' => 'terms' ),
			)
		);

		$repeater->add_control(
			'text_prefix',
			array(
				'label'     => esc_html__( 'Before', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXT,
				'condition' => array( 'type!' => 'custom' ),
				'dynamic'   => array( 'active' => true ),
				'ai'        => array( 'active' => false ),
			)
		);

		$repeater->add_control(
			'show_avatar',
			array(
				'label'     => esc_html__( 'Avatar', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array( 'type' => 'author' ),
			)
		);

		$repeater->add_responsive_control(
			'avatar_size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} {{CURRENT_ITEM}} .elementor-icon-list-icon' => 'width: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array( 'show_avatar' => 'yes' ),
			)
		);

		$repeater->add_control(
			'comments_custom_strings',
			array(
				'label'     => esc_html__( 'Custom Format', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => false,
				'condition' => array( 'type' => 'comments' ),
			)
		);

		/*
		 * Written out one by one rather than looped. A loop registers the same
		 * three controls, but it hides them from the control-parity check that
		 * compares our ids against Pro's — and that check is the thing standing
		 * between a transcription slip and silent data loss.
		 */
		$repeater->add_control(
			'string_no_comments',
			array(
				'label'       => esc_html__( 'No Comments', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'No Comments', 'piecyfer-core' ),
				'condition'   => array(
					'comments_custom_strings' => 'yes',
					'type'                    => 'comments',
				),
			)
		);

		$repeater->add_control(
			'string_one_comment',
			array(
				'label'       => esc_html__( 'One Comment', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'One Comment', 'piecyfer-core' ),
				'condition'   => array(
					'comments_custom_strings' => 'yes',
					'type'                    => 'comments',
				),
			)
		);

		$repeater->add_control(
			'string_comments',
			array(
				'label'       => esc_html__( 'Comments', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				/* translators: %s: Number of comments. */
				'placeholder' => esc_html__( '%s Comments', 'piecyfer-core' ),
				'condition'   => array(
					'comments_custom_strings' => 'yes',
					'type'                    => 'comments',
				),
			)
		);

		$repeater->add_control(
			'custom_text',
			array(
				'label'       => esc_html__( 'Custom', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'label_block' => true,
				'condition'   => array( 'type' => 'custom' ),
			)
		);

		$repeater->add_control(
			'link',
			array(
				'label'     => esc_html__( 'Link', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => array( 'type!' => 'time' ),
			)
		);

		$repeater->add_control(
			'custom_url',
			array(
				'label'     => esc_html__( 'Custom URL', 'piecyfer-core' ),
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'type' => 'custom' ),
			)
		);

		$repeater->add_control(
			'show_icon',
			array(
				'label'     => esc_html__( 'Icon', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'none'    => esc_html__( 'None', 'piecyfer-core' ),
					'default' => esc_html__( 'Default', 'piecyfer-core' ),
					'custom'  => esc_html__( 'Custom', 'piecyfer-core' ),
				),
				'default'   => 'default',
				'condition' => array( 'show_avatar!' => 'yes' ),
			)
		);

		$repeater->add_control(
			'selected_icon',
			array(
				'label'            => esc_html__( 'Choose Icon', 'piecyfer-core' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'condition'        => array(
					'show_icon'    => 'custom',
					'show_avatar!' => 'yes',
				),
			)
		);

		return $repeater;
	}

	private function register_list_style_section(): void {
		$this->start_controls_section(
			'section_icon_list',
			array(
				'label' => esc_html__( 'List', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'space_between',
			array(
				'label'      => esc_html__( 'Space Between', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:last-child)' => 'padding-bottom: calc({{SIZE}}{{UNIT}}/2)',
					'{{WRAPPER}} .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:first-child)' => 'margin-top: calc({{SIZE}}{{UNIT}}/2)',
					'{{WRAPPER}} .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item' => 'margin-right: calc({{SIZE}}{{UNIT}}/2); margin-left: calc({{SIZE}}{{UNIT}}/2)',
					'{{WRAPPER}} .elementor-icon-list-items.elementor-inline-items' => 'margin-right: calc(-{{SIZE}}{{UNIT}}/2); margin-left: calc(-{{SIZE}}{{UNIT}}/2)',
					'body.rtl {{WRAPPER}} .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item:after' => 'left: calc(-{{SIZE}}{{UNIT}}/2)',
					'body:not(.rtl) {{WRAPPER}} .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item:after' => 'right: calc(-{{SIZE}}{{UNIT}}/2)',
				),
			)
		);

		$this->add_responsive_control(
			'icon_align',
			array(
				'label'        => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'left'   => array(
						'title' => esc_html__( 'Start', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'End', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				// The %s is Elementor's responsive placeholder: it becomes
				// `elementor-tablet-align-` and so on. Writing a literal here
				// would silently drop the responsive variants.
				'prefix_class' => 'elementor%s-align-',
			)
		);

		$this->add_control(
			'divider',
			array(
				'label'     => esc_html__( 'Divider', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Off', 'piecyfer-core' ),
				'label_on'  => esc_html__( 'On', 'piecyfer-core' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon-list-item:not(:last-child):after' => 'content: ""',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'divider_style',
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
				'condition' => array( 'divider' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:last-child):after' => 'border-top-style: {{VALUE}};',
					'{{WRAPPER}} .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item:not(:last-child):after' => 'border-left-style: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'divider_weight',
			array(
				'label'     => esc_html__( 'Weight', 'piecyfer-core' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array( 'size' => 1 ),
				'range'     => array(
					'px'  => array( 'min' => 1, 'max' => 20 ),
					'em'  => array( 'max' => 2 ),
					'rem' => array( 'max' => 2 ),
				),
				'condition' => array( 'divider' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:last-child):after' => 'border-top-width: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .elementor-inline-items .elementor-icon-list-item:not(:last-child):after' => 'border-left-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'divider_width',
			array(
				'label'      => esc_html__( 'Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array( 'unit' => '%' ),
				'range'      => array(
					'px'  => array( 'min' => 1, 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
					'%'   => array( 'min' => 1, 'max' => 100 ),
				),
				'condition'  => array(
					'divider' => 'yes',
					'view!'   => 'inline',
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-icon-list-item:not(:last-child):after' => 'width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'divider_height',
			array(
				'label'      => esc_html__( 'Height', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'default'    => array( 'unit' => '%' ),
				'range'      => array(
					'px'  => array( 'min' => 1, 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
					'%'   => array( 'min' => 1, 'max' => 100 ),
				),
				'condition'  => array(
					'divider' => 'yes',
					'view'    => 'inline',
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-icon-list-item:not(:last-child):after' => 'height: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'divider_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ddd',
				'global'    => array( 'default' => Global_Colors::COLOR_TEXT ),
				'condition' => array( 'divider' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon-list-item:not(:last-child):after' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_icon_style_section(): void {
		$this->start_controls_section(
			'section_icon_style',
			array(
				'label' => esc_html__( 'Icon', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon-list-icon i'   => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-icon-list-icon svg' => 'fill: {{VALUE}};',
				),
				'global'    => array( 'default' => Global_Colors::COLOR_PRIMARY ),
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 14 ),
				'range'      => array(
					'px'  => array( 'min' => 6 ),
					'em'  => array( 'max' => 0.6 ),
					'rem' => array( 'max' => 0.6 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-icon-list-icon'     => 'width: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-icon-list-icon i'   => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-icon-list-icon svg' => '--e-icon-list-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_text_style_section(): void {
		$this->start_controls_section(
			'section_text_style',
			array(
				'label' => esc_html__( 'Text', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_indent',
			array(
				'label'      => esc_html__( 'Indent', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
				),
				'selectors'  => array(
					'body:not(.rtl) {{WRAPPER}} .elementor-icon-list-text' => 'padding-left: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .elementor-icon-list-text'       => 'padding-right: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon-list-text, {{WRAPPER}} .elementor-icon-list-text a' => 'color: {{VALUE}}',
				),
				'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'icon_typography',
				'selector' => '{{WRAPPER}} .elementor-icon-list-item',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_TEXT ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * @return array<string,string>
	 */
	private function get_taxonomy_options(): array {
		$options = array( '' => esc_html__( 'Choose', 'piecyfer-core' ) );

		foreach ( get_taxonomies( array( 'show_in_nav_menus' => true ), 'objects' ) as $taxonomy ) {
			$options[ $taxonomy->name ] = $taxonomy->label;
		}

		return $options;
	}

	// ---------------------------------------------------------------- render

	protected function render_widget(): void {
		$settings = $this->get_settings_for_display();

		ob_start();
		foreach ( (array) ( $settings['icon_list'] ?? array() ) as $item ) {
			$this->render_item( $item );
		}
		$items_html = ob_get_clean();

		// Every row can legitimately produce nothing — comments closed, an
		// empty taxonomy — and Pro emits no <ul> at all in that case rather than
		// an empty one that the list stylesheet would still give margins to.
		if ( '' === trim( (string) $items_html ) ) {
			return;
		}

		if ( 'inline' === ( $settings['view'] ?? 'inline' ) ) {
			$this->add_render_attribute( 'icon_list', 'class', 'elementor-inline-items' );
		}

		$this->add_render_attribute( 'icon_list', 'class', array( 'elementor-icon-list-items', 'elementor-post-info' ) );
		?>
		<ul <?php $this->print_render_attribute_string( 'icon_list' ); ?>>
			<?php // Whitespace-significant, not decorative. Pro carries a PHPCS annotation on its own line here; the tag emits nothing, but the indentation in front of it is literal output, so removing this line pulls every <li> three tabs to the left. ?>
			<?php echo $items_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</ul>
		<?php
	}

	/**
	 * @param array<string,mixed> $item
	 */
	private function render_item( array $item ): void {
		$data  = $this->get_meta_data( $item );
		$index = $item['_id'] ?? '';

		if ( empty( $data['text'] ) && empty( $data['terms_list'] ) ) {
			return;
		}

		$item_key = 'item_' . $index;
		$link_key = 'link_' . $index;

		$this->add_render_attribute(
			$item_key,
			'class',
			array( 'elementor-icon-list-item', 'elementor-repeater-item-' . $index )
		);

		$active = $this->get_active_settings();
		if ( 'inline' === ( $active['view'] ?? '' ) ) {
			$this->add_render_attribute( $item_key, 'class', 'elementor-inline-item' );
		}

		$has_link = ! empty( $data['url']['url'] );
		if ( $has_link ) {
			$this->add_link_attributes( $link_key, $data['url'] );
		}

		if ( ! empty( $data['itemprop'] ) ) {
			$this->add_render_attribute( $item_key, 'itemprop', $data['itemprop'] );
		}

		?>
		<li <?php $this->print_render_attribute_string( $item_key ); ?>>
			<?php if ( $has_link ) : ?>
			<a <?php $this->print_render_attribute_string( $link_key ); ?>>
				<?php endif; ?>
				<?php $this->render_item_icon_or_image( $data, $item, $index ); ?>
				<?php $this->render_item_text( $data, $index ); ?>
				<?php if ( $has_link ) : ?>
			</a>
		<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * Resolve one repeater row into the text, link, icon and schema data it
	 * stands for.
	 *
	 * @param array<string,mixed> $item
	 * @return array<string,mixed>
	 */
	private function get_meta_data( array $item ): array {
		$data = array();
		$type = $item['type'] ?? 'date';
		$link = 'yes' === ( $item['link'] ?? '' );

		switch ( $type ) {
			case 'author':
				$data['text']          = get_the_author_meta( 'display_name' );
				$data['icon']          = 'fa fa-user-circle-o';
				$data['selected_icon'] = array(
					'value'   => 'far fa-user-circle',
					'library' => 'fa-regular',
				);
				$data['itemprop']      = 'author';

				if ( $link ) {
					$data['url'] = array( 'url' => get_author_posts_url( (int) get_the_author_meta( 'ID' ) ) );
				}
				if ( 'yes' === ( $item['show_avatar'] ?? '' ) ) {
					$data['image'] = get_avatar_url( get_the_author_meta( 'ID' ), 96 );
				}
				break;

			case 'date':
				$custom  = empty( $item['custom_date_format'] ) ? 'F j, Y' : $item['custom_date_format'];
				$formats = array(
					'default' => 'F j, Y',
					'0'       => 'F j, Y',
					'1'       => 'Y-m-d',
					'2'       => 'm/d/Y',
					'3'       => 'd/m/Y',
					'custom'  => $custom,
				);

				$data['text']          = get_the_time( $formats[ $item['date_format'] ?? 'default' ] ?? 'F j, Y' );
				$data['icon']          = 'fa fa-calendar';
				$data['selected_icon'] = array(
					'value'   => 'fas fa-calendar',
					'library' => 'fa-solid',
				);
				$data['itemprop']      = 'datePublished';

				if ( $link ) {
					$data['url'] = array(
						'url' => get_day_link( (int) get_post_time( 'Y' ), (int) get_post_time( 'm' ), (int) get_post_time( 'j' ) ),
					);
				}
				break;

			case 'time':
				$custom  = empty( $item['custom_time_format'] ) ? 'g:i a' : $item['custom_time_format'];
				$formats = array(
					'default' => 'g:i a',
					'0'       => 'g:i a',
					'1'       => 'g:i A',
					'2'       => 'H:i',
					'custom'  => $custom,
				);

				$data['text']          = get_the_time( $formats[ $item['time_format'] ?? 'default' ] ?? 'g:i a' );
				$data['icon']          = 'fa fa-clock-o';
				$data['selected_icon'] = array(
					'value'   => 'far fa-clock',
					'library' => 'fa-regular',
				);
				break;

			case 'comments':
				if ( comments_open() ) {
					$strings = array(
						'string_no_comments' => esc_html__( 'No Comments', 'piecyfer-core' ),
						'string_one_comment' => esc_html__( 'One Comment', 'piecyfer-core' ),
						/* translators: %s: Number of comments. */
						'string_comments'    => esc_html__( '%s Comments', 'piecyfer-core' ),
					);

					if ( 'yes' === ( $item['comments_custom_strings'] ?? '' ) ) {
						foreach ( array_keys( $strings ) as $key ) {
							if ( ! empty( $item[ $key ] ) ) {
								$strings[ $key ] = $item[ $key ];
							}
						}
					}

					$count = (int) get_comments_number();

					if ( 0 === $count ) {
						$data['text'] = $strings['string_no_comments'];
					} else {
						$data['text'] = sprintf(
							// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingular,WordPress.WP.I18n.NonSingularStringLiteralPlural
							_n( $strings['string_one_comment'], $strings['string_comments'], $count, 'piecyfer-core' ),
							$count
						);
					}

					if ( $link ) {
						$data['url'] = array( 'url' => get_comments_link() );
					}

					$data['icon']          = 'fa fa-commenting-o';
					$data['selected_icon'] = array(
						'value'   => 'far fa-comment-dots',
						'library' => 'fa-regular',
					);
					$data['itemprop']      = 'commentCount';
				}
				break;

			case 'terms':
				$data['icon']          = 'fa fa-tags';
				$data['selected_icon'] = array(
					'value'   => 'fas fa-tags',
					'library' => 'fa-solid',
				);
				$data['itemprop']      = 'about';

				foreach ( (array) wp_get_post_terms( (int) get_the_ID(), (string) ( $item['taxonomy'] ?? '' ) ) as $term ) {
					if ( ! $term instanceof \WP_Term ) {
						continue;
					}
					$data['terms_list'][ $term->term_id ]['text'] = $term->name;
					if ( $link ) {
						$data['terms_list'][ $term->term_id ]['url'] = get_term_link( $term );
					}
				}
				break;

			case 'custom':
				$data['text']          = $item['custom_text'] ?? '';
				$data['icon']          = 'fa fa-info-circle';
				$data['selected_icon'] = array(
					'value'   => 'far fa-tags',
					'library' => 'fa-regular',
				);

				if ( $link && ! empty( $item['custom_url'] ) ) {
					$data['url'] = $item['custom_url'];
				}
				break;
		}

		$data['type'] = $type;

		if ( ! empty( $item['text_prefix'] ) ) {
			$data['text_prefix'] = esc_html( $item['text_prefix'] );
		}

		return $data;
	}

	/**
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $item
	 */
	private function render_item_icon_or_image( array $data, array $item, string $index ): void {
		$migration_allowed = Icons_Manager::is_migration_allowed();
		$show_icon_setting = $item['show_icon'] ?? 'default';

		if ( ! $migration_allowed ) {
			if ( 'custom' === $show_icon_setting && ! empty( $item['icon'] ) ) {
				$data['icon'] = $item['icon'];
			} elseif ( 'none' === $show_icon_setting ) {
				$data['icon'] = '';
			}
		} elseif ( 'custom' === $show_icon_setting && ! empty( $item['selected_icon'] ) ) {
			$data['selected_icon'] = $item['selected_icon'];
		} elseif ( 'none' === $show_icon_setting ) {
			$data['selected_icon'] = array();
		}

		if ( empty( $data['icon'] ) && empty( $data['selected_icon'] ) && empty( $data['image'] ) ) {
			return;
		}

		$migrated  = isset( $item['__fa4_migrated']['selected_icon'] );
		$is_new    = empty( $item['icon'] ) && $migration_allowed;
		$show_icon = 'none' !== $show_icon_setting;

		if ( empty( $data['image'] ) && ! $show_icon ) {
			return;
		}
		?>
		<span class="elementor-icon-list-icon">
		<?php
		if ( ! empty( $data['image'] ) ) :
			$image_key = 'image_' . $index;
			$this->add_render_attribute(
				$image_key,
				array(
					'class'   => 'elementor-avatar',
					'src'     => $data['image'],
					'alt'     => sprintf(
						/* translators: %s: Author name. */
						esc_attr__( 'Picture of %s', 'piecyfer-core' ),
						$data['text']
					),
					'loading' => 'lazy',
				)
			);
			?>
				<img <?php $this->print_render_attribute_string( $image_key ); ?>>
			<?php elseif ( $show_icon ) : ?>
				<?php if ( $is_new || $migrated ) : ?>
					<?php Icons_Manager::render_icon( $data['selected_icon'], array( 'aria-hidden' => 'true' ) ); ?>
				<?php else : ?>
					<i class="<?php echo esc_attr( (string) $data['icon'] ); ?>" aria-hidden="true"></i>
				<?php endif; ?>
			<?php endif; ?>
		</span>
		<?php
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function render_item_text( array $data, string $index ): void {
		$key = $this->get_repeater_setting_key( 'text', 'icon_list', $index );

		$this->add_render_attribute(
			$key,
			'class',
			array(
				'elementor-icon-list-text',
				'elementor-post-info__item',
				'elementor-post-info__item--type-' . $data['type'],
			)
		);
		?>
		<span <?php $this->print_render_attribute_string( $key ); ?>>
			<?php if ( ! empty( $data['text_prefix'] ) ) : ?>
				<span class="elementor-post-info__item-prefix"><?php echo esc_html( $data['text_prefix'] ); ?></span>
			<?php endif; ?>
			<?php
			if ( ! empty( $data['terms_list'] ) ) :
				$terms = array();
				?>
				<span class="elementor-post-info__terms-list">
				<?php
				foreach ( $data['terms_list'] as $term ) {
					$terms[] = empty( $term['url'] )
						? sprintf( '<span class="%1$s">%2$s</span>', 'elementor-post-info__terms-list-item', esc_html( $term['text'] ) )
						: sprintf( '<a href="%1$s" class="%2$s">%3$s</a>', esc_url( $term['url'] ), 'elementor-post-info__terms-list-item', esc_html( $term['text'] ) );
				}
				echo implode( ', ', $terms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				</span>
			<?php else : ?>
				<?php
				// Dates and times are wrapped in <time> for the schema markup;
				// everything else is emitted as-is.
				$content = ( 'date' === $data['type'] || 'time' === $data['type'] )
					? sprintf( '<time>%s</time>', $data['text'] )
					: $data['text'];

				echo wp_kses(
					(string) $content,
					array(
						'a'    => array(
							'href'  => array(),
							'title' => array(),
							'rel'   => array(),
						),
						'time' => array(),
					)
				);
				?>
			<?php endif; ?>
		</span>
		<?php
	}
}
