<?php
/**
 * Replacement for Elementor Pro's `theme-post-content` widget.
 *
 * 1 instance, in the Blog Post Template — but that template renders every
 * blog post, so this is on 15 URLs.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as ElementorPlugin;

defined( 'ABSPATH' ) || exit;

final class PostContentWidget extends AbstractWidget {

	public function get_name(): string {
		return 'theme-post-content';
	}

	public function get_title(): string {
		return esc_html__( 'Post Content', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-post-content';
	}

	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	public function get_keywords(): array {
		return array( 'content', 'post' );
	}

	/**
	 * Pro hides this from the panel by default — it only makes sense inside a
	 * single-post template, and dropping it on an ordinary page recurses.
	 */
	public function show_in_panel(): bool {
		return false;
	}

	protected function replaces(): string {
		return 'Elementor Pro — ThemeBuilder/Post_Content';
	}

	/**
	 * Three controls, transcribed with their exact ids and `selectors`.
	 *
	 * The selectors are the part that matters: Elementor compiles them into the
	 * per-page CSS file, so a selector that differs by one character produces
	 * a different stylesheet even when the markup is identical.
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Style', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'    => array(
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'   => array(
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Justified', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}}' => 'text-align: {{VALUE}};',
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
					'{{WRAPPER}}' => 'color: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'   => 'typography',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render_widget(): void {
		// Pro passes with_css = false here: the page's CSS is already printed,
		// and printing it again from inside the content overrides it.
		$this->render_post_content();
	}

	/**
	 * Render the current post's content.
	 *
	 * Faithful to Pro's `Skin_Content_Base::render_post_content()` minus its
	 * theme-builder preview substitution, which only applies inside Pro's
	 * editor. The parts that matter on the front end are all here:
	 *
	 *  - the password form short-circuit
	 *  - the recursion guard (a post whose content contains this widget would
	 *    otherwise render itself forever)
	 *  - Elementor's builder content when the post was built with Elementor,
	 *    falling back to the classic `the_content` path when it was not
	 *  - the content-filter juggling, without which Elementor's own
	 *    `the_content` filter re-enters and recurses
	 */
	private function render_post_content(): void {
		static $rendered = array();

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		if ( post_password_required( $post->ID ) ) {
			echo get_the_password_form( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		if ( isset( $rendered[ $post->ID ] ) ) {
			return;
		}
		$rendered[ $post->ID ] = true;

		$editor       = ElementorPlugin::$instance->editor;
		$was_edit     = $editor->is_edit_mode();
		$frontend     = ElementorPlugin::$instance->frontend;

		// Render settings belong to the outer document, not to the embedded
		// content; Pro suppresses edit mode for the duration for that reason.
		$editor->set_edit_mode( false );

		$content = $frontend->get_builder_content( $post->ID, false );
		$frontend->remove_content_filter();

		if ( '' === trim( (string) $content ) ) {
			// Not an Elementor-built post: fall back to the classic loop output.
			setup_postdata( $post );

			/** This filter is documented in wp-includes/post-template.php */
			echo apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			wp_link_pages(
				array(
					'before'      => '<div class="page-links elementor-page-links"><span class="page-links-title elementor-page-links-title">' . esc_html__( 'Pages:', 'piecyfer-core' ) . '</span>',
					'after'       => '</div>',
					'link_before' => '<span>',
					'link_after'  => '</span>',
					'pagelink'    => '<span class="screen-reader-text">' . esc_html__( 'Page', 'piecyfer-core' ) . ' </span>%',
					'separator'   => '<span class="screen-reader-text">, </span>',
				)
			);

			$frontend->add_content_filter();
			$editor->set_edit_mode( $was_edit );
			return;
		}

		$frontend->remove_content_filters();
		$content = apply_filters( 'the_content', $content );
		$frontend->restore_content_filters();

		$editor->set_edit_mode( $was_edit );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Pro outputs nothing here: the plain-text representation of a post's own
	 * content inside that post would duplicate it.
	 */
	public function render_plain_content(): void {}
}
