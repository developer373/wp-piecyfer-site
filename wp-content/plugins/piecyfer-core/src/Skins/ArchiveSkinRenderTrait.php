<?php
/**
 * Port of `ElementorPro\Modules\ThemeBuilder\Skins\Posts_Archive_Skin_Base`
 * (elementor-pro/modules/theme-builder/skins/posts-archive-skin-base.php).
 *
 * The only thing the three archive skins add over their `posts` counterparts:
 * an empty archive renders the container plus a "nothing found" message instead
 * of nothing at all.
 *
 * Pro overrides `render()`; we override `render_skin()`, because `render()` is
 * final in SkinBase and carries the fail-safe guard. Same call graph otherwise.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

trait ArchiveSkinRenderTrait {

	protected function render_skin() {
		$this->parent->query_posts();

		$wp_query = $this->parent->get_query();

		if ( ! $wp_query->found_posts ) {
			$this->render_loop_header();

			$should_escape = true;

			/**
			 * Should escape 'nothing found' message.
			 *
			 * By default the HTML tags a user puts in the message are stripped.
			 * Returning false here allows them through.
			 *
			 * @param bool $should_escape Whether to escape 'nothing found' message.
			 */
			$should_escape = apply_filters( 'elementor_pro/theme_builder/archive/escape_nothing_found_message', $should_escape );

			$message = $this->parent->get_settings_for_display( 'nothing_found_message' );
			if ( $should_escape ) {
				$message = esc_html( $message );
			}

			?>
				<div class="elementor-posts-nothing-found">
					<?php
						// PHPCS - escaped before if should escape
						echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			<?php

			$this->render_loop_footer();

			return;
		}

		parent::render_skin();
	}
}
