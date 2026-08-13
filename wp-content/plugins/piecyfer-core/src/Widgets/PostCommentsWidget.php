<?php
/**
 * Replacement for Elementor Pro's `post-comments` widget.
 *
 * 2 instances: the Blog Post Template and the "Blog Post – Comments" section.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as ElementorPlugin;

defined( 'ABSPATH' ) || exit;

final class PostCommentsWidget extends AbstractWidget {

	private const SOURCE_CURRENT = 'current';
	private const SOURCE_CUSTOM  = 'custom';

	public function get_name(): string {
		return 'post-comments';
	}

	public function get_title(): string {
		return esc_html__( 'Post Comments', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-comments';
	}

	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	public function get_keywords(): array {
		return array( 'comments', 'post', 'response', 'form' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — ThemeElements/Post_Comments';
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => esc_html__( 'Comments', 'piecyfer-core' ) )
		);

		/*
		 * Both saved instances carry `_skin: "theme_comments"`, left over from an
		 * older Pro release that implemented this as a skin. Pro keeps a HIDDEN
		 * control of that id so the stored value survives a save; without it,
		 * Elementor would drop the key. It has no effect on rendering — the
		 * "Theme Comments" skin simply means "hand off to the theme", which is
		 * the only behaviour there is.
		 */
		$this->add_control(
			'_skin',
			array( 'type' => Controls_Manager::HIDDEN )
		);

		$this->add_control(
			'skin_temp',
			array(
				'label'       => esc_html__( 'Skin', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					'' => esc_html__( 'Theme Comments', 'piecyfer-core' ),
				),
				'description' => esc_html__( 'Uses the active theme\'s comment form and list.', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'source_type',
			array(
				'label'     => esc_html__( 'Source', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					self::SOURCE_CURRENT => esc_html__( 'Current Post', 'piecyfer-core' ),
					self::SOURCE_CUSTOM  => esc_html__( 'Custom', 'piecyfer-core' ),
				),
				'default'   => self::SOURCE_CURRENT,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'source_custom',
			array(
				'label'       => esc_html__( 'Search & Select', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->get_post_options(),
				'condition'   => array( 'source_type' => self::SOURCE_CUSTOM ),
			)
		);

		$this->end_controls_section();
	}

	/** @return array<int,string> */
	private function get_post_options(): array {
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 200 ) ) as $p ) {
			$out[ $p->ID ] = $p->post_title;
		}
		return $out;
	}

	protected function render_widget(): void {
		$settings   = $this->get_settings_for_display();
		$is_custom  = self::SOURCE_CUSTOM === ( $settings['source_type'] ?? self::SOURCE_CURRENT );
		$switched   = false;

		if ( $is_custom && ! empty( $settings['source_custom'] ) ) {
			ElementorPlugin::$instance->db->switch_to_post( (int) $settings['source_custom'] );
			$switched = true;
		}

		try {
			if ( ! comments_open() && $this->is_editing() ) {
				// Only editors see this notice. A visitor on a post with
				// comments closed should simply see nothing, which is what Pro
				// does too.
				?>
				<div class="elementor-alert elementor-alert-danger" role="alert">
					<span class="elementor-alert-title"><?php esc_html_e( 'Comments are closed.', 'piecyfer-core' ); ?></span>
					<span class="elementor-alert-description"><?php esc_html_e( 'Enable comments from the post editor or the WordPress discussion settings.', 'piecyfer-core' ); ?></span>
				</div>
				<?php
			} else {
				comments_template();
			}
		} finally {
			if ( $switched ) {
				ElementorPlugin::$instance->db->restore_current_post();
			}
		}
	}

	private function is_editing(): bool {
		return ElementorPlugin::$instance->preview->is_preview_mode()
			|| ElementorPlugin::$instance->editor->is_edit_mode();
	}

	public function render_plain_content(): void {}
}
