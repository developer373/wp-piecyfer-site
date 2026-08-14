<?php
/**
 * Shared base for the `posts` and `archive-posts` widgets.
 *
 * Port of `ElementorPro\Modules\Posts\Widgets\Posts_Base`
 * (elementor-pro/modules/posts/widgets/posts-base.php, 924 lines).
 *
 * Three things about this class are unlike every other widget in this plugin
 * and none of them may be "tidied":
 *
 *   1. **It renders nothing itself.** Pro's `Posts_Base::render()` is empty
 *      (posts-base.php:47) because `Widget_Base::render_content()` dispatches to
 *      the *skin* when `_skin` resolves. `render_widget()` below is therefore
 *      deliberately empty. If the skin id ever fails to resolve, the widget
 *      emits nothing at all — no wrapper, no placeholder — which is the single
 *      most dangerous failure mode on this site (all 15 saved instances select
 *      `vamtam_classic`). See `release_foreign_skins()` and PostsWidget.
 *
 *   2. **`$_has_template_content = false`.** Widget_Base defaults it to true,
 *      which would add an empty "Default" entry at the top of the `_skin`
 *      control's options — and, because the default value is the *first* option,
 *      would change `_skin`'s default from `classic` to `''`.
 *
 *   3. **No `has_widget_inner_wrapper()` override.** The `e_optimized_markup`
 *      experiment is active on this site, and two saved `custom_css` rules are
 *      child-combinator chains that count on `.elementor-widget-container`
 *      existing (07-POSTS-SPEC.md §4.5). Pro does not opt in, so neither do we.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use PieCyfer\Core\Skins\SkinBase;

defined( 'ABSPATH' ) || exit;

abstract class PostsBaseWidget extends AbstractWidget {

	use PostsButtonTrait;
	use PostsPaginationTrait;

	const LOAD_MORE_ON_CLICK = 'load_more_on_click';
	const LOAD_MORE_INFINITE_SCROLL = 'load_more_infinite_scroll';

	/**
	 * @var \WP_Query|null
	 */
	protected $query = null;

	/**
	 * See the class docblock, point 2.
	 *
	 * @var bool
	 */
	protected $_has_template_content = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	public function get_icon() {
		return 'eicon-post-list';
	}

	public function get_script_depends() {
		return [ 'imagesloaded' ];
	}

	public function get_query() {
		return $this->query;
	}

	/**
	 * Deliberately empty — the skin renders. See the class docblock, point 1.
	 */
	protected function render_widget(): void {}

	public function render_plain_content() {}

	/* ---------------------------------------------------------------------
	 * Skins
	 * ------------------------------------------------------------------ */

	/**
	 * The skin classes this widget owns, in registration order.
	 *
	 * Order is load-bearing twice over: `Skins_Manager` keeps insertion order,
	 * and `Widget_Base::register_skin_control()` takes the **first** registered
	 * skin id as the `_skin` control's default.
	 *
	 * @return array<class-string<SkinBase>>
	 */
	abstract protected function skin_classes(): array;

	protected function register_skins() {
		$this->release_foreign_skins();

		foreach ( $this->skin_classes() as $class ) {
			$this->add_skin( new $class( $this ) );
		}
	}

	/**
	 * Detach skins registered for this widget name by anyone else.
	 *
	 * `Skins_Manager::$_skins` is keyed by widget **name**, not by widget
	 * instance (elementor/includes/managers/skins.php:43-53). While Elementor
	 * Pro and the VamTam companion plugin are still installed, three separate
	 * registrations land in the same `$_skins['posts']` bucket: Pro's at
	 * priority 10, VamTam's at 100 and ours at 150.
	 *
	 * Leaving the foreign ones in place breaks the port in two distinct ways:
	 *
	 *   - `get_current_skin()` would return **VamTam's** `vamtam_classic` object
	 *     for our widget (ours overwrites it, but Pro's `classic`/`cards`/
	 *     `full_content` would survive), so a pixel comparison could pass while
	 *     somebody else's code drew the page. That is exactly the class of false
	 *     pass `scripts/which-implementation.php` exists to catch.
	 *   - Every foreign skin object has already hooked
	 *     `elementor/element/<widget>/<section>/before_section_end` from its own
	 *     constructor. Those hooks fire while *our* control stack is being built
	 *     and register `classic_*` / `vamtam_classic_*` a second time, which
	 *     Elementor answers with `_doing_it_wrong` and a dropped control.
	 *
	 * So both halves have to go: the object in the manager and its control
	 * hooks. Only hooks named `elementor/element/<our widget name>/…` are
	 * touched, and only callbacks bound to a foreign `Elementor\Skin_Base`
	 * instance — nothing else on those hooks (VamTam's plain-function control
	 * injections, ElementsKit's, anyone's) is affected.
	 *
	 * Once Pro and VamTam are gone this is a no-op: the bucket is empty.
	 */
	private function release_foreign_skins(): void {
		$manager = \Elementor\Plugin::$instance->skins_manager;

		$existing = $manager->get_skins( $this );

		if ( empty( $existing ) || ! is_array( $existing ) ) {
			return;
		}

		$prefix = 'elementor/element/' . $this->get_name() . '/';

		foreach ( $existing as $skin_id => $skin ) {
			if ( $skin instanceof SkinBase ) {
				continue;
			}

			$this->unhook_skin_callbacks( $skin, $prefix );
			$manager->remove_skin( $this, $skin_id );
		}
	}

	/**
	 * Remove every `elementor/element/<widget>/…` callback bound to $skin.
	 *
	 * Collected first and removed afterwards, so `remove_action()` never mutates
	 * the structure being walked.
	 *
	 * @param object $skin
	 * @param string $prefix
	 */
	private function unhook_skin_callbacks( $skin, string $prefix ): void {
		global $wp_filter;

		$doomed = [];

		foreach ( $wp_filter as $hook_name => $hook ) {
			if ( 0 !== strpos( (string) $hook_name, $prefix ) || ! isset( $hook->callbacks ) ) {
				continue;
			}

			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && isset( $callback['function'][0] ) && $callback['function'][0] === $skin ) {
						$doomed[] = [ $hook_name, $callback['function'], $priority ];
					}
				}
			}
		}

		foreach ( $doomed as $entry ) {
			remove_action( $entry[0], $entry[1], $entry[2] );
		}
	}

	/* ---------------------------------------------------------------------
	 * Controls — Posts_Base
	 * ------------------------------------------------------------------ */

	public function register_load_more_button_style_controls() {
		$this->add_control(
			'heading_load_more_style_button',
			[
				'label' => esc_html__( 'Button', 'piecyfer-core' ),
				'type' => Controls_Manager::HEADING,
				'condition' => [
					'pagination_type' => 'load_more_on_click',
				],
			]
		);

		$this->register_button_style_controls( [
			'section_condition' => [
				'pagination_type' => 'load_more_on_click',
			],
			'prefix_class' => 'load-more-align-',
			'alignment_default' => 'center',
		] );
	}

	public function register_load_more_message_style_controls() {
		$this->add_control(
			'heading_load_more_on_click_no_posts_message',
			[
				'label' => esc_html__( 'No More Posts Message', 'piecyfer-core' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'pagination_type' => 'load_more_on_click',
				],
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'heading_load_more_on_click_infinity_scroll_no_posts_message',
			[
				'label' => esc_html__( 'No More Posts Message', 'piecyfer-core' ),
				'type' => Controls_Manager::HEADING,
				'condition' => [
					'pagination_type' => 'load_more_infinite_scroll',
				],
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'load_more_no_posts_message',
				'selector' => '{{WRAPPER}} .e-load-more-message',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				],
			]
		);

		$this->add_control(
			'load_more_no_posts_message_color',
			[
				'label' => esc_html__( 'Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}}' => '--load-more-message-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'load_more_spinner_color',
			[
				'label' => esc_html__( 'Spinner Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}}' => '--load-more-spinner-color: {{VALUE}};',
				],
				'separator' => 'before',
				'condition' => [
					'load_more_spinner[value]!' => '',
				],
			]
		);

		$this->add_responsive_control(
			'load_more_spacing',
			[
				'label' => esc_html__( 'Spacing', 'piecyfer-core' ),
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
				/*
				 * The em-dash in `--load-more—spacing` is Pro's, not a typo in
				 * this file. The generated CSS custom property carries it, and
				 * the stylesheet has to read the same name back — see
				 * assets/css/posts.css.
				 */
				'selectors' => [
					'{{WRAPPER}}' => '--load-more—spacing: {{SIZE}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);
	}

	public function register_pagination_section_controls() {
		$this->start_controls_section(
			'section_pagination',
			[
				'label' => esc_html__( 'Pagination', 'piecyfer-core' ),
				/*
				 * Pro excludes the two Loop-Builder taxonomy skins here. Neither
				 * module is ported (nothing on this site uses a loop grid), but
				 * the literal ids are kept so the control's `condition` array is
				 * byte-identical to Pro's in the stack diff.
				 */
				'condition' => [
					'_skin!' => [
						'post_taxonomy',
						'product_taxonomy',
					],
				],
			]
		);

		$this->add_control(
			'pagination_type',
			[
				'label' => esc_html__( 'Pagination', 'piecyfer-core' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => $this->get_pagination_type_options(),
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'pagination_page_limit',
			[
				'label' => esc_html__( 'Page Limit', 'piecyfer-core' ),
				'default' => '5',
				'condition' => [
					'pagination_type!' => [
						'load_more_on_click',
						'load_more_infinite_scroll',
						'',
					],
				],
			]
		);

		$this->add_control(
			'pagination_numbers_shorten',
			[
				'label' => esc_html__( 'Shorten', 'piecyfer-core' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'condition' => [
					'pagination_type' => [
						'numbers',
						'numbers_and_prev_next',
					],
				],
			]
		);

		$this->add_control(
			'pagination_prev_label',
			[
				'label' => esc_html__( 'Previous Label', 'piecyfer-core' ),
				'dynamic' => [
					'active' => true,
				],
				'default' => esc_html__( '&laquo; Previous', 'piecyfer-core' ),
				'condition' => [
					'pagination_type' => [
						'prev_next',
						'numbers_and_prev_next',
					],
				],
			]
		);

		$this->add_control(
			'pagination_next_label',
			[
				'label' => esc_html__( 'Next Label', 'piecyfer-core' ),
				'default' => esc_html__( 'Next &raquo;', 'piecyfer-core' ),
				'condition' => [
					'pagination_type' => [
						'prev_next',
						'numbers_and_prev_next',
					],
				],
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'pagination_align',
			[
				'label' => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'default' => 'center',
				'selectors' => [
					'{{WRAPPER}} .elementor-pagination' => 'text-align: {{VALUE}};',
				],
				'condition' => [
					'pagination_type!' => [
						'load_more_on_click',
						'load_more_infinite_scroll',
						'',
					],
				],
			]
		);

		$this->add_control(
			'pagination_individual_divider',
			[
				'type' => Controls_Manager::DIVIDER,
				'condition' => [
					'pagination_type' => [
						'numbers',
						'numbers_and_prev_next',
						'prev_next',
					],
				],
			]
		);

		$this->add_control(
			'pagination_individual_handle',
			[
				'label' => esc_html__( 'Individual Pagination', 'piecyfer-core' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'On', 'piecyfer-core' ),
				'label_off' => esc_html__( 'Off', 'piecyfer-core' ),
				'default' => '',
				'condition' => [
					'pagination_type' => [
						'numbers',
						'numbers_and_prev_next',
						'prev_next',
					],
				],
			]
		);

		$this->add_control(
			'pagination_individual_handle_message',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => esc_html__( 'For multiple Posts Widgets on the same page, toggle this on to control the pagination for each individually. Note: It affects the page\'s URL structure.', 'piecyfer-core' ),
				'content_classes' => 'elementor-control-field-description',
				'condition' => [
					'pagination_type' => [
						'numbers',
						'numbers_and_prev_next',
						'prev_next',
					],
				],
			]
		);

		$this->add_control(
			'load_more_spinner',
			[
				'label' => esc_html__( 'Spinner', 'piecyfer-core' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'default' => [
					'value' => 'fas fa-spinner',
					'library' => 'fa-solid',
				],
				'exclude_inline_options' => [ 'svg' ],
				'recommended' => [
					'fa-solid' => [
						'spinner',
						'cog',
						'sync',
						'sync-alt',
						'asterisk',
						'circle-notch',
					],
				],
				'skin' => 'inline',
				'label_block' => false,
				'condition' => [
					'pagination_type' => [
						'load_more_on_click',
						'load_more_infinite_scroll',
					],
				],
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'heading_load_more_button',
			[
				'label' => esc_html__( 'Button', 'piecyfer-core' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'pagination_type' => 'load_more_on_click',
				],
			]
		);

		$this->register_button_content_controls( [
			'button_text' => esc_html__( 'Load More', 'piecyfer-core' ),
			'control_label_name' => esc_html__( 'Button Text', 'piecyfer-core' ),
			'section_condition' => [
				'pagination_type' => 'load_more_on_click',
			],
			'exclude_inline_options' => [ 'svg' ],
		] );

		$this->remove_control( 'button_type' );
		$this->remove_control( 'link' );
		$this->remove_control( 'size' );

		$this->add_control(
			'heading_load_more_no_posts_message',
			[
				'label' => esc_html__( 'No More Posts Message', 'piecyfer-core' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'pagination_type' => [
						'load_more_on_click',
						'load_more_infinite_scroll',
					],
				],
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_responsive_control(
			'load_more_no_posts_message_align',
			[
				'label' => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left'    => [
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justified', 'piecyfer-core' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--load-more-message-alignment: {{VALUE}};',
				],
				'condition' => [
					'pagination_type' => [
						'load_more_on_click',
						'load_more_infinite_scroll',
					],
				],
			]
		);

		$this->add_control(
			'load_more_no_posts_message_switcher',
			[
				'label' => esc_html__( 'Custom Messages', 'piecyfer-core' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'condition' => [
					'pagination_type' => [
						'load_more_on_click',
						'load_more_infinite_scroll',
					],
				],
			]
		);

		$this->add_control(
			'load_more_no_posts_custom_message',
			[
				'label' => esc_html__( 'No more posts message', 'piecyfer-core' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'No more posts to show', 'piecyfer-core' ),
				'condition' => [
					'pagination_type' => [
						'load_more_on_click',
						'load_more_infinite_scroll',
					],
					'load_more_no_posts_message_switcher' => 'yes',
				],
				'label_block' => true,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->end_controls_section();

		// Pagination style controls for prev/next and numbers pagination.
		$this->start_controls_section(
			'section_pagination_style',
			[
				'label' => esc_html__( 'Pagination', 'piecyfer-core' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'pagination_type!' => [
						'load_more_on_click',
						'load_more_infinite_scroll',
						'',
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'pagination_typography',
				'selector' => '{{WRAPPER}} .elementor-pagination',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				],
			]
		);

		$this->add_control(
			'pagination_color_heading',
			[
				'label' => esc_html__( 'Colors', 'piecyfer-core' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->start_controls_tabs( 'pagination_colors' );

		$this->start_controls_tab(
			'pagination_color_normal',
			[
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			]
		);

		$this->add_control(
			'pagination_color',
			[
				'label' => esc_html__( 'Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-pagination .page-numbers:not(.dots)' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'pagination_color_hover',
			[
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			]
		);

		$this->add_control(
			'pagination_hover_color',
			[
				'label' => esc_html__( 'Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-pagination a.page-numbers:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'pagination_color_active',
			[
				'label' => esc_html__( 'Active', 'piecyfer-core' ),
			]
		);

		$this->add_control(
			'pagination_active_color',
			[
				'label' => esc_html__( 'Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-pagination .page-numbers.current' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'pagination_spacing',
			[
				'label' => esc_html__( 'Space Between', 'piecyfer-core' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'separator' => 'before',
				'default' => [
					'size' => 10,
				],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'body:not(.rtl) {{WRAPPER}} .elementor-pagination .page-numbers:not(:first-child)' => 'margin-left: calc( {{SIZE}}{{UNIT}}/2 );',
					'body:not(.rtl) {{WRAPPER}} .elementor-pagination .page-numbers:not(:last-child)' => 'margin-right: calc( {{SIZE}}{{UNIT}}/2 );',
					'body.rtl {{WRAPPER}} .elementor-pagination .page-numbers:not(:first-child)' => 'margin-right: calc( {{SIZE}}{{UNIT}}/2 );',
					'body.rtl {{WRAPPER}} .elementor-pagination .page-numbers:not(:last-child)' => 'margin-left: calc( {{SIZE}}{{UNIT}}/2 );',
				],
			]
		);

		$this->add_responsive_control(
			'pagination_spacing_top',
			[
				'label' => esc_html__( 'Spacing', 'piecyfer-core' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-pagination' => 'margin-top: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->end_controls_section();

		// Pagination style controls for on-load pagination with type on-click/infinity-scroll.
		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'Pagination', 'piecyfer-core' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'pagination_type' => [
						'load_more_on_click',
						'load_more_infinite_scroll',
					],
				],
			]
		);

		$this->register_load_more_button_style_controls();

		$this->register_load_more_message_style_controls();

		$this->end_controls_section();
	}

	abstract public function query_posts();

	public function get_current_page() {
		if ( '' === $this->get_settings_for_display( 'pagination_type' ) ) {
			return 1;
		}

		return max(
			1,
			get_query_var( 'paged' ),
			get_query_var( 'page' ),
			self::super_global_value( $_GET, 'e-page-' . $this->get_id() ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	public function is_rest_request() {
		$request_uri = self::super_global_value( $_SERVER, 'REQUEST_URI' );

		return false !== wp_get_referer() &&
			isset( $_SERVER['REQUEST_URI'] ) &&
			( false !== strpos( $request_uri, 'wp-json' ) || false !== strpos( $request_uri, 'rest_route' ) );
	}

	public function get_wp_link_page( $i ) {
		if ( ( ! is_singular() || is_front_page() ) && ! $this->is_rest_request() && ! $this->is_allow_to_use_custom_page_option() ) {
			return get_pagenum_link( $i );
		}

		// Based on wp-includes/post-template.php:957 `_wp_link_page`.
		global $wp_rewrite;
		$post = get_post();
		$query_args = [];
		$url = get_permalink();

		if ( $this->is_rest_request() ) {
			$link_unescaped = wp_get_referer();
			$post_id = url_to_postid( $link_unescaped );

			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
			}

			$url = $this->get_base_url_for_rest_request( $post_id, $url );
		}

		if ( $i > 1 ) {
			if ( '' === get_option( 'permalink_structure' ) || in_array( $post->post_status, [ 'draft', 'pending' ] ) ) {
				$url = add_query_arg( $this->get_wp_pagination_query_var(), $i, $url );
			} elseif ( get_option( 'show_on_front' ) === 'page' && (int) get_option( 'page_on_front' ) === $post->ID ) {
				$url = trailingslashit( $url ) . user_trailingslashit( "$wp_rewrite->pagination_base/" . $i, 'single_paged' );
			} else {
				$url = trailingslashit( $url ) . user_trailingslashit( $i, 'single_paged' );
			}
		}

		if ( $i > 1 && $this->is_allow_to_use_custom_page_option() ) {
			$url = $this->get_wp_link_page_url_for_custom_page_option( $url, $i, $post_id ?? 0 );
		}

		if ( 1 === $i && $this->is_allow_to_use_custom_page_option() ) {
			$url = $this->get_base_url();
		}

		if ( is_preview() ) {
			$url = $this->get_wp_link_page_url_for_preview( $post, $query_args, $url );
		}

		if ( $this->is_rest_request() ) {
			$url = $this->get_wp_link_page_url_for_rest_request( $url, $link_unescaped );
		}

		if ( ! $this->is_rest_request() && $this->current_url_contains_taxonomy_filter() && ! is_preview() ) {
			$url = $this->get_wp_link_page_url_for_normal_page_load( $url );
		}

		return esc_url( $url );
	}

	public function is_allow_to_use_custom_page_option() {
		return 'ajax' === $this->get_settings_for_display( 'pagination_load_type' ) || 'yes' === $this->get_settings_for_display( 'pagination_individual_handle' );
	}

	protected function get_base_url_for_rest_request( $post_id, $url ) {
		if ( $post_id > 0 ) {
			return get_permalink( $post_id );
		}

		global $wp_rewrite;

		if ( $wp_rewrite->using_permalinks() && ( $this->current_url_contains_taxonomy_filter() || $this->referer_contains_taxonomy_filter() ) ) {
			$url = $this->is_allow_to_use_custom_page_option() ? get_query_var( 'pagination_base_url' ) : get_query_var( 'pagination_base_url' ) . user_trailingslashit( "$wp_rewrite->pagination_base/", 'single_paged' );
		} else {
			$url = remove_query_arg( 'p', $url );
		}

		return $url;
	}

	protected function get_wp_link_page_url_for_preview( $post, $query_args, $url ) {
		if ( 'draft' === $post->post_status || ! isset( $_GET['preview_id'], $_GET['preview_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $url;
		}

		$query_args['preview_id'] = self::super_global_value( $_GET, 'preview_id' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query_args['preview_nonce'] = self::super_global_value( $_GET, 'preview_nonce' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $this->is_rest_request() || ! $this->current_url_contains_taxonomy_filter() ) {
			return get_preview_post_link( $post, $query_args, $url );
		}

		wp_parse_str( htmlspecialchars_decode( (string) self::super_global_value( $_SERVER, 'QUERY_STRING' ) ), $query_params );

		foreach ( $query_params as $param_key => $param_value ) {
			if ( false !== strpos( $param_key, 'e-filter-' ) ) {
				$query_args[ $param_key ] = $param_value;
			}
		}

		return get_preview_post_link( $post, $query_args, $url );
	}

	protected function get_wp_link_page_url_for_rest_request( $url, $link_unescaped ) {
		$url_components = wp_parse_url( $link_unescaped );
		$query_args = [];

		if ( isset( $url_components['query'] ) ) {
			wp_parse_str( $url_components['query'], $query_args );
		}

		$url = ! empty( $query_args ) ? $url . '&' . http_build_query( $query_args ) : $url;

		return $this->format_query_string_concatenation( $url );
	}

	protected function get_wp_link_page_url_for_normal_page_load( $url ) {
		wp_parse_str( htmlspecialchars_decode( (string) self::super_global_value( $_SERVER, 'QUERY_STRING' ) ), $query_params );

		$e_filters = '';

		foreach ( $query_params as $param_key => $param_value ) {
			if ( false !== strpos( $param_key, 'e-filter' ) ) {
				$e_filters .= '&' . $param_key . '=' . $param_value;
			}
		}

		return $this->format_query_string_concatenation( $url . $e_filters );
	}

	public function current_url_contains_taxonomy_filter() {
		return false !== strpos( (string) self::super_global_value( $_SERVER, 'QUERY_STRING' ), 'e-filter-' );
	}

	public function referer_contains_taxonomy_filter() {
		return false !== strpos( (string) self::super_global_value( $_SERVER, 'HTTP_REFERER' ), 'e-filter-' );
	}

	protected function format_query_string_concatenation( $input ) {
		if ( false === strpos( $input, '?' ) ) {
			// If "?" doesn't exist in the input URL, replace the first "&" with "?"
			$input = preg_replace( '/&/', '?', $input, 1 );
		}

		return $input;
	}

	public function get_posts_nav_link( $page_limit = null ) {
		if ( ! $page_limit ) {
			$page_limit = $this->query->max_num_pages;
		}

		$return = [];

		$paged = $this->get_current_page();

		$link_template = '<a class="page-numbers %s" href="%s">%s</a>';
		$disabled_template = '<span class="page-numbers %s">%s</span>';

		if ( $paged > 1 ) {
			$next_page = intval( $paged ) - 1;
			if ( $next_page < 1 ) {
				$next_page = 1;
			}

			$return['prev'] = sprintf( $link_template, 'prev', $this->get_wp_link_page( $next_page ), $this->get_settings_for_display( 'pagination_prev_label' ) );
		} else {
			$return['prev'] = sprintf( $disabled_template, 'prev', $this->get_settings_for_display( 'pagination_prev_label' ) );
		}

		$next_page = intval( $paged ) + 1;

		if ( $next_page <= $page_limit ) {
			$return['next'] = sprintf( $link_template, 'next', $this->get_wp_link_page( $next_page ), $this->get_settings_for_display( 'pagination_next_label' ) );
		} else {
			$return['next'] = sprintf( $disabled_template, 'next', $this->get_settings_for_display( 'pagination_next_label' ) );
		}

		return $return;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_layout',
			[
				'label' => esc_html__( 'Layout', 'piecyfer-core' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->end_controls_section();
	}

	protected function get_pagination_type_options() {
		return [
			'' => esc_html__( 'None', 'piecyfer-core' ),
			'numbers' => esc_html__( 'Numbers', 'piecyfer-core' ),
			'prev_next' => esc_html__( 'Previous/Next', 'piecyfer-core' ),
			'numbers_and_prev_next' => esc_html__( 'Numbers', 'piecyfer-core' ) . ' + ' . esc_html__( 'Previous/Next', 'piecyfer-core' ),
			self::LOAD_MORE_ON_CLICK => esc_html__( 'Load on Click', 'piecyfer-core' ),
			self::LOAD_MORE_INFINITE_SCROLL => esc_html__( 'Infinite Scroll', 'piecyfer-core' ),
		];
	}

	/**
	 * @param string $url
	 * @param int    $i
	 * @param int    $post_id
	 * @return string
	 */
	private function get_wp_link_page_url_for_custom_page_option( $url, $i, $post_id ) {
		$base_raw_url = $this->is_rest_request() ? $this->get_base_url_for_rest_request( $post_id, $url ) : $this->get_base_url();
		$pagination_key = 'e-page-' . $this->get_id();

		if ( 'yes' === $this->get_settings_for_display( 'pagination_individual_handle' ) ) {
			$base_raw_url .= $this->get_pagination_query_vars_for_others_individually_paginated_widgets( $pagination_key );
		}

		return $this->format_query_string_concatenation( $base_raw_url . '&' . $pagination_key . '=' . $i );
	}

	private function get_pagination_query_vars_for_others_individually_paginated_widgets( string $pagination_key ): string {
		wp_parse_str( htmlspecialchars_decode( (string) self::super_global_value( $_SERVER, 'QUERY_STRING' ) ), $query_params );

		$e_page = '';

		foreach ( $query_params as $param_key => $param_value ) {
			if ( false !== strpos( $param_key, 'e-page' ) && $pagination_key !== $param_key ) {
				$e_page .= '&' . $param_key . '=' . $param_value;
			}
		}

		return $e_page;
	}

	/**
	 * @return string
	 */
	private function get_wp_pagination_query_var() {
		if ( '' === get_option( 'permalink_structure' ) && $this->is_posts_page( $this->is_allow_to_use_custom_page_option() ) ) {
			return 'paged';
		}

		return 'page';
	}

	/**
	 * Stand-in for `ElementorPro\Core\Utils::_unstable_get_super_global_value()`.
	 *
	 * Same behaviour for the two superglobals this class reads ($_GET and
	 * $_SERVER): unslash, then run through `wp_kses_post_deep()`. Pro's version
	 * also has a $_FILES branch, which nothing here can reach.
	 *
	 * @param array  $super_global
	 * @param string $key
	 * @return mixed|null
	 */
	private static function super_global_value( $super_global, $key ) {
		if ( ! isset( $super_global[ $key ] ) ) {
			return null;
		}

		return wp_kses_post_deep( wp_unslash( $super_global[ $key ] ) );
	}
}
