<?php
/**
 * Replacement for Elementor Pro's "Template" widget.
 *
 * 19 instances across 15 documents — the most-used Pro widget after nav-menu.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as ElementorPlugin;

defined( 'ABSPATH' ) || exit;

final class Template extends AbstractWidget {

	public function get_name(): string {
		return 'template';
	}

	public function get_title(): string {
		return esc_html__( 'Template', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-document-file';
	}

	public function get_keywords(): array {
		return array( 'elementor', 'template', 'library', 'block', 'page' );
	}

	/**
	 * Pro registers its widgets under `pro-elements`. Saved documents do not
	 * store the category, but the editor panel does, so keeping it means the
	 * widget stays exactly where whoever built these pages expects to find it.
	 */
	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — Library/Template';
	}

	/**
	 * Changing the preview on every keystroke is pointless for this widget:
	 * the content comes from another document entirely.
	 */
	public function is_reload_preview_required(): bool {
		return false;
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_template',
			array(
				'label' => esc_html__( 'Template', 'piecyfer-core' ),
			)
		);

		/*
		 * Pro uses its own QUERY_CONTROL_ID here, an AJAX autocomplete that
		 * lives in ElementorPro\Modules\QueryControl. We cannot use it — and do
		 * not need to. What matters for the saved data is the control *id*
		 * (`template_id`) and the shape of its value (a post ID). A SELECT2
		 * over the same set of documents stores exactly the same thing, so all
		 * 19 existing instances keep rendering untouched.
		 *
		 * The trade-off is the editor UX on a site with hundreds of templates,
		 * where an autocomplete beats a long dropdown. This site has 28.
		 */
		$this->add_control(
			'template_id',
			array(
				'label'       => esc_html__( 'Choose Template', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->get_template_options(),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Every Elementor library document that is allowed to appear in the library,
	 * as id => "Title (type)".
	 *
	 * @return array<int,string>
	 */
	private function get_template_options(): array {
		$documents = get_posts(
			array(
				'post_type'        => 'elementor_library',
				'post_status'      => 'publish',
				'posts_per_page'   => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$options = array();
		foreach ( $documents as $doc ) {
			$type = get_post_meta( $doc->ID, '_elementor_template_type', true );
			$options[ $doc->ID ] = $type
				? sprintf( '%s (%s)', $doc->post_title, $type )
				: $doc->post_title;
		}

		return $options;
	}

	protected function render_widget(): void {
		$template_id = (int) $this->get_settings_for_display( 'template_id' );

		if ( ! $template_id ) {
			return;
		}

		// Pro checks published status before rendering, so an unpublished
		// template silently outputs nothing rather than leaking a draft.
		if ( 'publish' !== get_post_status( $template_id ) ) {
			return;
		}

		?>
		<div class="elementor-template">
			<?php
			// Elementor escapes its own builder output; escaping again would
			// destroy the markup. Same call Pro makes.
			echo ElementorPlugin::$instance->frontend->get_builder_content_for_display( $template_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<?php
	}

	/**
	 * Pro deliberately outputs nothing here — the plain-text (search/excerpt)
	 * representation of a nested template would duplicate the other document's
	 * content.
	 */
	public function render_plain_content(): void {}
}
