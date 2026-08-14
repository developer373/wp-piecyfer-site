<?php
/**
 * Pro-namespaced bridge class — a wart with an expiry date.
 *
 * DELETE THIS FILE the day `piecyfer-theme` replaces `tecnologia`, together with
 * the require in Module::load_public_api(). Add it to the Phase 5 checklist.
 *
 * Why it has to exist at all:
 *
 *   themes/tecnologia/vamtam/classes/elementor-bridge.php:734
 *     public static function is_location_template_exits( $location ) {
 *         if ( ! function_exists( 'elementor_theme_do_location' ) ) { return false; }
 *         $templates_asigned = Theme_Builder_Module::instance()
 *             ->get_conditions_manager()
 *             ->get_documents_for_location( $location );
 *
 * The guard is on our function; the next line is on Pro's class. Satisfy the
 * guard without providing the class and every page fatals — `page.php:13`,
 * `page.php:42`, `single.php:17` and `single.php:33` all call it, and
 * `is_title_present_in_doc_locations()` (line 851) calls the same pair from
 * `templates/header/sub-header.php:15` with no guard at all.
 *
 * This file is loaded only when Elementor Pro is genuinely gone: the class is
 * not already declared and `elementor-pro/elementor-pro.php` is not on disk.
 * While Pro is installed it must never load, or PHP fatals on the duplicate
 * class declaration.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace ElementorPro\Modules\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( __NAMESPACE__ . '\Module', false ) ) {

	/**
	 * Stand-in for `ElementorPro\Modules\ThemeBuilder\Module`.
	 *
	 * Only the four methods the theme actually calls are implemented, and the
	 * signatures are untyped to match Pro's. `get_documents_for_location()` must
	 * return real `\Elementor\Core\Base\Document` objects, because
	 * `is_title_present_in_doc_locations()` calls `get_elements_data()` on each
	 * one.
	 */
	class Module {

		/**
		 * @return self
		 */
		public static function instance() {
			static $instance = null;

			return $instance ??= new self();
		}

		/**
		 * @return \PieCyfer\Core\ThemeBuilder\ConditionsManager
		 */
		public function get_conditions_manager() {
			return \PieCyfer\Core\ThemeBuilder\Module::instance()->get_conditions_manager();
		}

		/**
		 * @return \PieCyfer\Core\ThemeBuilder\LocationsManager
		 */
		public function get_locations_manager() {
			return \PieCyfer\Core\ThemeBuilder\Module::instance()->get_locations_manager();
		}

		/**
		 * @param int $post_id
		 */
		public function get_document( $post_id ) {
			return \PieCyfer\Core\ThemeBuilder\Module::instance()->get_document( (int) $post_id );
		}
	}
}
