<?php
/**
 * Port of `ElementorPro\Modules\ThemeBuilder\Skins\Posts_Archive_Skin_Full_Content`
 * (elementor-pro/modules/theme-builder/skins/posts-archive-skin-full-content.php).
 *
 * Pro re-applies `Skin_Content_Base` on top of `Skin_Full_Content`, which
 * already uses it. That is not redundant: re-using the trait re-binds
 * `_register_controls_actions()` at this level, and the trait's body reads
 * `$this->parent->get_name()` — so the same code hooks `archive-posts` here and
 * `posts` in the parent class. Reproduced exactly.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

class ArchiveFullContentSkin extends FullContentSkin {

	use ContentBaseTrait;
	use ArchiveSkinRenderTrait;

	public function get_id() {
		return 'archive_full_content';
	}

	/* Remove `posts_per_page` control */
	protected function register_post_count_control() {}
}
