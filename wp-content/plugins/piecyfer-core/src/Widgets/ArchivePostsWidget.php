<?php
/**
 * Replacement for Elementor Pro's `archive-posts` widget.
 *
 * Port of `ElementorPro\Modules\ThemeBuilder\Widgets\Archive_Posts`
 * (elementor-pro/modules/theme-builder/widgets/archive-posts.php) plus the
 * surviving parts of VamTam's `Vamtam_Widget_Archive_Posts`.
 *
 * Three saved instances, all on `_skin = vamtam_classic`:
 *
 *   | doc  | template             | renders at                       |
 *   |------|----------------------|----------------------------------|
 *   | 8559 | Blog Posts Archives  | category and author archives     |
 *   | 8711 | Search Results       | /?s=…                            |
 *   | 6126 | Case Studies Archives| a CPT archive — **no harness URL reaches it** |
 *
 * Doc 6126 will be signed off blind unless its display condition is resolved to
 * a real URL first (07-POSTS-SPEC.md §9.8).
 *
 * ## It does not run a query
 *
 * `query_posts()` reuses the **global** `$wp_query` and only builds a new one if
 * a filter changed the query vars. `posts_per_page` for archives is therefore
 * WordPress's own option — 10 on this site — never a widget setting, which is
 * exactly why `ArchiveClassicSkin::register_post_count_control()` is an empty
 * override.
 *
 * ## The container-class asymmetry
 *
 * This widget renders `elementor-posts--skin-classic` while `posts` renders
 * `elementor-posts--skin-vamtam_classic`, from the same skin id. See
 * VamtamArchiveClassicSkin.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

final class ArchivePostsWidget extends PostsBaseWidget {

	/**
	 * @param array<string,mixed>      $data
	 * @param array<string,mixed>|null $args
	 */
	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );

		// Shared with the posts widget; see PostsWidget for why we carry it.
		PostsWidget::register_vamtam_script();
	}

	public function get_name(): string {
		return 'archive-posts';
	}

	public function get_title(): string {
		return esc_html__( 'Archive Posts', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-archive-posts';
	}

	/**
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'theme-elements-archive' );
	}

	/**
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'posts', 'cpt', 'archive', 'loop', 'query', 'cards', 'custom post type' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — ThemeBuilder/Archive_Posts + VamTam Vamtam_Widget_Archive_Posts';
	}

	/**
	 * DEVIATION, deliberate: Pro returns `[ 'posts' ]` here so the Improved CSS
	 * Loading experiment inlines its own `widget-posts.min.css`. That key points
	 * at a Pro asset we are replacing, and resolving it through Elementor core
	 * would look for a `widget-posts.min.css` that does not exist in core's
	 * assets directory. The experiment is inactive on this site
	 * (`elementor_experiment-e_optimized_css_loading` = default = inactive), so
	 * this method is never called today; returning an empty array is the safe
	 * behaviour if it is ever switched on.
	 *
	 * @return array
	 */
	public function get_inline_css_depends() {
		return array();
	}

	/**
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( 'piecyfer-posts' );
	}

	/**
	 * VamTam's list (`vamtam .../includes/widgets/archive-posts.php:243-254`).
	 * See PostsWidget::get_script_depends() for why hr-scrolling stays for now.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'imagesloaded', 'vamtam-posts-base', 'vamtam-hr-scrolling' );
	}

	/**
	 * @return array<class-string<Skins\SkinBase>>
	 */
	protected function skin_classes(): array {
		return array(
			Skins\ArchiveClassicSkin::class,
			Skins\ArchiveCardsSkin::class,
			Skins\ArchiveFullContentSkin::class,
			Skins\VamtamArchiveClassicSkin::class,
		);
	}

	protected function register_controls() {
		parent::register_controls();

		$this->register_pagination_section_controls();

		$this->register_advanced_section_controls();

		$this->update_control(
			'pagination_type',
			[
				'default' => 'numbers',
			]
		);
	}

	public function register_advanced_section_controls() {
		$this->start_controls_section(
			'section_advanced',
			[
				'label' => esc_html__( 'Advanced', 'piecyfer-core' ),
			]
		);

		$this->add_control(
			'nothing_found_message',
			[
				'label' => esc_html__( 'Nothing Found Message', 'piecyfer-core' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => esc_html__( 'It seems we can\'t find what you\'re looking for.', 'piecyfer-core' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_nothing_found_style',
			[
				'tab' => Controls_Manager::TAB_STYLE,
				'label' => esc_html__( 'Nothing Found Message', 'piecyfer-core' ),
				'condition' => [
					'nothing_found_message!' => '',
				],
			]
		);

		$this->add_control(
			'nothing_found_color',
			[
				'label' => esc_html__( 'Color', 'piecyfer-core' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-posts-nothing-found' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'nothing_found_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
				'selector' => '{{WRAPPER}} .elementor-posts-nothing-found',
			]
		);

		$this->end_controls_section();
	}

	public function query_posts() {
		global $wp_query;

		$query_vars = $wp_query->query_vars;

		/**
		 * Posts archive query vars.
		 *
		 * Filters the post query variables when the theme loads the posts
		 * archive page.
		 *
		 * @param array $query_vars The query variables for the `WP_Query`.
		 */
		$query_vars = apply_filters( 'elementor/theme/posts_archive/query_posts/query_vars', $query_vars );

		if ( $query_vars !== $wp_query->query_vars ) {
			$this->query = new \WP_Query( $query_vars ); // SQL_CALC_FOUND_ROWS is used.
		} else {
			$this->query = $wp_query;
		}

		/*
		 * The avoid-duplicates list. Pro accumulates every rendered post id into
		 * a static for the life of the request; `set_avoid_duplicates()` merges
		 * it into `post__not_in` only when a widget sets
		 * `posts_avoid_duplicates = yes`. **No instance on this site does**, so
		 * the list is written and never read — /blogs/ genuinely repeats
		 * post-996336 between its featured card and its grid, by design.
		 * Feeding Pro's list while Pro is still here keeps that machinery
		 * behaving identically; it becomes a no-op afterwards, and the P4 query
		 * port owns the replacement.
		 */
		if ( class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ) {
			\ElementorPro\Modules\QueryControl\Module::add_to_avoid_list( wp_list_pluck( $this->query->posts, 'ID' ) );
		}
	}
}
