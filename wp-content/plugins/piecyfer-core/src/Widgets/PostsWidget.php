<?php
/**
 * Replacement for Elementor Pro's `posts` widget.
 *
 * Port of `ElementorPro\Modules\Posts\Widgets\Posts`
 * (elementor-pro/modules/posts/widgets/posts.php) plus the parts of
 * `VamtamElementor\Widgets\Posts\Vamtam_Widget_Posts` that survive — VamTam
 * unregisters Pro's widget and re-registers a subclass at priority 100, and we
 * register above that, so its script list and its `vamtam_classic` skin are
 * ours to carry now.
 *
 * 12 saved instances, every one of them on `_skin = vamtam_classic`, 191
 * distinct saved keys. Five render on a public URL (three on /blogs/, one on
 * the home page, one — the "related posts" block — on every single post);
 * the other seven live in unreferenced library templates and cannot be
 * pixel-verified, but their settings still have to survive an editor save.
 *
 * ## What this file does NOT own yet
 *
 *   - **The query.** `query_posts()` still goes through Pro's
 *     `QueryControl\Module`, and the Query section's controls still come from
 *     Pro's `related-query` group control. Both are guarded, and both are step
 *     P4 in _project/07-POSTS-SPEC.md §8.2. Using Pro's here is what keeps the
 *     `posts_*` control ids byte-identical while the two implementations sit
 *     side by side.
 *   - **The JavaScript.** `vamtam-posts-base` still supplies load-more, image
 *     fit and masonry — Pro's own posts handler never runs for this skin,
 *     because its handlers are keyed on `classic`/`cards`/`full_content` and
 *     none of them is `vamtam_classic`. Step P6.
 *   - **`pre_handle_404` for `/blogs/2/`.** Pro's `Posts\Module` hooks it; a
 *     module-level equivalent is still missing. See PostsPaginationTrait.
 *
 * ## Registration
 *
 * `get_style_depends()` names `piecyfer-posts`, so `Plugin::STYLES` needs
 * `'piecyfer-posts' => 'posts.css'`, and `ProStyleGuard::SUPERSEDED` needs
 * `'posts' => 'widget-posts'` — but only once **both** this widget and
 * ArchivePostsWidget are registered, because they share the Pro handle.
 *
 * `assets/css/posts.css` exists and covers 112 of 112 non-portfolio selectors
 * in Pro's `widget-posts.min.css`, declaration for declaration. Until the
 * handle is registered, `get_style_depends()` names something WordPress has
 * never heard of and the widget silently loads no CSS of its own — which is
 * only safe while Pro's stylesheet is still on the page.
 *
 * Neither this widget nor ArchivePostsWidget is in `Plugin::WIDGETS` yet.
 * Verified in that state by `_project/scripts/posts-parity.php` (control-stack
 * parity) and `_project/scripts/posts-markup-diff.php` (byte-identical render
 * of all 15 saved instances against the live Pro + VamTam implementation).
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Base\Document;
use PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

final class PostsWidget extends PostsBaseWidget {

	/**
	 * Carry VamTam's script registration, exactly as NavMenuWidget does.
	 *
	 * `Vamtam_Widget_Posts` registered `vamtam-posts-base` from its own
	 * constructor. Once we win the `posts` slot that class still exists but is
	 * no longer the widget Elementor renders — and if VamTam is ever removed
	 * before the JS is ported, `get_script_depends()` would name a handle
	 * WordPress has never heard of: a silent no-op that leaves the blog listing
	 * with no Load More, no image fit and no masonry.
	 *
	 * Registering is idempotent-safe: if the handle already exists we leave it
	 * alone, so the URL and version stay whatever VamTam decided.
	 *
	 * @param array<string,mixed>      $data
	 * @param array<string,mixed>|null $args
	 */
	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );

		self::register_vamtam_script();
	}

	public static function register_vamtam_script(): void {
		if ( ! defined( 'VAMTAM_ELEMENTOR_INT_URL' ) || ! class_exists( '\VamtamElementorIntregration' ) ) {
			return;
		}

		if ( wp_script_is( 'vamtam-posts-base', 'registered' ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		/*
		 * The leading slash after the constant is VamTam's, and the constant is
		 * plugin_dir_url() so it already ends in one. The resulting double slash
		 * is the exact URL the site serves today, so it is reproduced rather
		 * than tidied.
		 */
		wp_register_script(
			'vamtam-posts-base',
			VAMTAM_ELEMENTOR_INT_URL . '/assets/js/widgets/posts-base/vamtam-posts-base' . $suffix . '.js',
			array( 'elementor-frontend' ),
			\VamtamElementorIntregration::PLUGIN_VERSION,
			true
		);
	}

	public function get_name(): string {
		return 'posts';
	}

	public function get_title(): string {
		return esc_html__( 'Posts', 'piecyfer-core' );
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
		return array( 'posts', 'cpt', 'item', 'loop', 'query', 'cards', 'custom post type' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — Posts/Posts + VamTam Vamtam_Widget_Posts';
	}

	/**
	 * `widget-posts` swapped for our own handle. Everything else is what the
	 * page loads today.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( 'piecyfer-posts' );
	}

	/**
	 * VamTam's list, verbatim (`vamtam .../includes/widgets/posts.php:46-60`).
	 *
	 * `vamtam-hr-scrolling` is enqueued but inert — it bails on its first line
	 * unless the widget carries `vamtam-has-hr-layout`, and no instance on this
	 * site sets `vamtam_use_hr_layout`. Dropping it changes zero pixels but does
	 * change the `<script>` list the harness records, so it is dropped as its
	 * own deliberate step (P8), not silently here.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'imagesloaded', 'vamtam-posts-base', 'vamtam-hr-scrolling' );
	}

	public function on_import( $element ) {
		if ( isset( $element['settings']['posts_post_type'] ) && ! get_post_type_object( $element['settings']['posts_post_type'] ) ) {
			$element['settings']['posts_post_type'] = 'post';
		}

		return $element;
	}

	/**
	 * Order matters: `classic` is registered first and therefore becomes the
	 * `_skin` control's default, exactly as with Pro + VamTam today.
	 * `vamtam_classic` is last, which is also where VamTam's own registration
	 * (priority 100, after Pro's 10) put it.
	 *
	 * @return array<class-string<Skins\SkinBase>>
	 */
	protected function skin_classes(): array {
		return array(
			Skins\ClassicSkin::class,
			Skins\CardsSkin::class,
			Skins\FullContentSkin::class,
			Skins\VamtamClassicSkin::class,
		);
	}

	protected function register_controls() {
		parent::register_controls();

		$this->register_query_section_controls();
		$this->register_pagination_section_controls();
	}

	/**
	 * The query control name used in the widget's main query.
	 */
	public function get_query_name() {
		return $this->get_name();
	}

	public function query_posts() {
		$query_args = [
			'posts_per_page' => $this->get_posts_per_page_value(),
			'paged' => $this->get_current_page(),
			'has_custom_pagination' => $this->is_allow_to_use_custom_page_option(),
		];

		if ( class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ) {
			$elementor_query = \ElementorPro\Modules\QueryControl\Module::instance();
			$this->query = $elementor_query->get_query( $this, $this->get_query_name(), $query_args, [] );

			return;
		}

		/*
		 * P4 placeholder. Pro's query builder is what applies `posts_post_type`,
		 * the include/exclude term lists, the `posts_offset` workaround (a
		 * pre_get_posts rewrite plus a found_posts correction) and the "related"
		 * path that excludes the post being viewed. None of that is reproduced
		 * here — this is a plain recent-posts query so the widget renders
		 * something rather than disappearing, and it is loud about it.
		 */
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-core] posts: query module missing — falling back to a plain WP_Query. Port the query layer (07-POSTS-SPEC.md §5) before removing Elementor Pro.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$this->query = new \WP_Query( [
			'post_type' => 'post',
			'post_status' => 'publish',
			'posts_per_page' => $query_args['posts_per_page'],
			'paged' => $query_args['paged'],
		] );
	}

	/**
	 * Posts Per Page lives on the **skin**, not the widget.
	 *
	 * If the current skin does not register `posts_per_page` this resolves to
	 * null, WP_Query falls back to the site's 10, and /blogs/ quietly shows ten
	 * cards instead of six with `data-max-page="2"` instead of `3`. Nothing
	 * crashes; the page is simply wrong. See 07-POSTS-SPEC.md §9.5.
	 */
	protected function get_posts_per_page_value() {
		return $this->get_current_skin()->get_instance_value( 'posts_per_page' );
	}

	protected function register_query_section_controls() {
		$this->start_controls_section(
			'section_query',
			[
				'label' => esc_html__( 'Query', 'piecyfer-core' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		/*
		 * `related-query` is `Group_Control_Related::get_type()`. It builds the
		 * whole `posts_*` family — posts_post_type, posts_include,
		 * posts_include_term_ids, posts_exclude, posts_exclude_term_ids,
		 * posts_offset, posts_related_taxonomies and the rest — and those ids
		 * are in the saved data on this site. Registering our own group control
		 * with the same ids is step P4; until then we use Pro's, which is what
		 * guarantees the ids match rather than merely resembling.
		 *
		 * `Controls_Stack::add_group_control()` calls wp_die() on an unknown
		 * group name, so the existence check is not optional.
		 */
		if ( \Elementor\Plugin::$instance->controls_manager->get_control_groups( 'related-query' ) ) {
			$this->add_group_control(
				'related-query',
				[
					'name' => $this->get_name(),
					'presets' => [ 'full' ],
					'exclude' => [
						'posts_per_page', //use the one from Layout section
					],
				]
			);
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-core] posts: the `related-query` group control is not registered — the Query section will be empty. Port it (07-POSTS-SPEC.md §8.1, Controls/GroupControlPosts) before removing Elementor Pro.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		if ( $this->is_editing_archive_template() ) {
			$this->inject_archive_query_note( 'posts_query_id', 'posts_post_type', $this );
		}

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Port of Pro's Query_Note_Trait — only `posts` uses it.
	 * ------------------------------------------------------------------ */

	public function is_editing_archive_template() {
		if ( \Elementor\Plugin::$instance->documents->get_current() ) {
			$id = self::get_current_post_id();
		} else {
			$id = get_the_ID();
		}

		return 'archive' === get_post_meta( $id, Document::TYPE_META_KEY, true );
	}

	public function inject_archive_query_note( $placement_id, $condition_id, $widget ) {
		$archive_setting_url = admin_url( 'options-reading.php' );

		$widget->start_injection( [
			'of' => $placement_id,
			'at' => 'before',
		] );

		$widget->add_control(
			'archive_query_note',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => sprintf(
					/* translators: %s: "Take me there" link to the Reading settings screen. */
					esc_html__( 'The amount of items displayed in your Archive is set in your WordPress settings. %s', 'piecyfer-core' ),
					'<a target="_blank" href="' . esc_url( $archive_setting_url ) . '">' . esc_html__( 'Take me there', 'piecyfer-core' ) . '</a>'
				),
				'content_classes' => 'elementor-descriptor',
				'condition' => [
					$condition_id => 'current_query',
				],
			]
		);

		$widget->end_injection();
	}

	/**
	 * Stand-in for `ElementorPro\Core\Utils::get_current_post_id()`.
	 */
	private static function get_current_post_id() {
		if ( isset( \Elementor\Plugin::$instance->documents ) ) {
			$current_document = \Elementor\Plugin::$instance->documents->get_current();

			if ( $current_document ) {
				return $current_document->get_main_id();
			}
		}

		return get_the_ID();
	}
}
