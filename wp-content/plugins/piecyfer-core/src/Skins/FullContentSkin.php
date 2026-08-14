<?php
/**
 * Port of `ElementorPro\Modules\Posts\Skins\Skin_Full_Content`
 * (elementor-pro/modules/posts/skins/skin-full-content.php).
 *
 * Extends the classic skin and swaps in the content-base trait, exactly as Pro
 * does — which is why its Box section id is `full_content_section_design_box`
 * even though the classic skin's `after_section_end` hook is never added for it
 * (`ContentBaseTrait::_register_controls_actions()` replaces the parent's).
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

class FullContentSkin extends ClassicSkin {

	use ContentBaseTrait;

	public function get_id() {
		return 'full_content';
	}
}
