<?php
/**
 * Port of VamTam's `Vamtam_PostsBase_Classic_Skin_Overrides`
 * (vamtam-elementor-integration-tecnologia/includes/widgets/posts-base.php:226-308).
 *
 * The `vamtam_classic` skin is the only skin any saved instance on this site
 * selects — all 15 of them. Everything here is therefore live code, not
 * compatibility ballast.
 *
 * ## The reorder (posts-base.php:297-307)
 *
 * `render_post()` moves `render_meta_data()` ahead of `render_text_header()`,
 * so `.elementor-post__meta-data` sits **outside** `.elementor-post__text` and
 * is a direct child of `<article>`. That is not cosmetic: the theme styles the
 * category chip through
 * `.elementor-widget-posts.vamtam-has-theme-widget-styles .elementor-post > .elementor-post__meta-data { padding: var(--vamtam-content-padding, 0) }`
 * — a direct-child selector that only matches because of this reorder. Put the
 * meta data back where Pro has it and every card on the site loses that padding.
 *
 * ## Indentation
 *
 * VamTam declares this trait inside an `if` block, so its methods sit at two
 * tabs and the HTML in its `?>` blocks sits at **three**. Those tabs are literal
 * output. This file is a top-level trait — methods at one tab — so the template
 * blocks below are hand-indented to three tabs to keep the bytes identical.
 * They will look over-indented. That is the point; re-indenting them changes
 * the rendered HTML.
 *
 * Verified against a raw `curl http://localhost/piecyfer/blogs/`: the meta-data
 * `<div>` lands at 5 tabs and the categories `<div>` at 7 after the page-level
 * blank-line collapse, which only works from a 3-tab source.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

trait VamtamClassicOverridesTrait {

	public function get_id() {
		return 'vamtam_classic';
	}

	public function get_title() {
		return esc_html__( 'Classic (Vamtam)', 'piecyfer-core' );
	}

	/**
	 * Pro's five meta types plus VamTam's two.
	 *
	 * `vamtam-categories` and `vamtam-tags` are added as *options* to the
	 * `vamtam_classic_meta_data` control by VamTam's own injection on
	 * `elementor/element/<widget>/section_layout/before_section_end` — that hook
	 * still fires against our widget, so the options are not duplicated here.
	 * Six of the eight live instances save `["vamtam-categories"]`.
	 *
	 * VamTam gates each branch on `vamtam_theme_supports()`; both flags are on
	 * in the tecnologia theme (framework.php:267-297) and the helper is going
	 * away with the theme, so the behaviour — not the gate — is reproduced.
	 */
	protected function render_meta_data() {
		/** @var array $settings e.g. [ 'author', 'date', ... ] */
		$settings = $this->get_instance_value( 'meta_data' );
		if ( empty( $settings ) ) {
			return;
		}
		?>
			<div class="elementor-post__meta-data">
				<?php
				if ( in_array( 'author', $settings ) ) {
					$this->render_author();
				}

				if ( in_array( 'date', $settings ) ) {
					$this->render_date_by_type();
				}

				if ( in_array( 'time', $settings ) ) {
					$this->render_time();
				}

				if ( in_array( 'comments', $settings ) ) {
					$this->render_comments();
				}

				if ( in_array( 'modified', $settings ) ) {
					$this->render_date_by_type( 'modified' );
				}

				if ( in_array( 'vamtam-categories', $settings ) ) {
					$this->render_categories();
				}

				if ( in_array( 'vamtam-tags', $settings ) ) {
					$this->render_tags();
				}
				?>
			</div>
			<?php
	}

	protected function render_categories() {
		?>
			<div class="vamtam-post__categories">
				<?php the_category( ', ' ); ?>
			</div>
			<?php
	}

	protected function render_tags() {
		?>
			<div class="vamtam-post__tags">
				<?php the_tags( '', ', ' ); ?>
			</div>
			<?php
	}

	/**
	 * The reorder. See the class docblock.
	 */
	protected function render_post() {
		$this->render_post_header();
		$this->render_thumbnail();
		$this->render_meta_data();
		$this->render_text_header();
		$this->render_title();
		$this->render_excerpt();
		$this->render_read_more();
		$this->render_text_footer();
		$this->render_post_footer();
	}
}
