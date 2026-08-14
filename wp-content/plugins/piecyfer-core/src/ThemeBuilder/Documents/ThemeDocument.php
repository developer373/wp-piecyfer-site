<?php
/**
 * Base document type for every Theme Builder template.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\ThemeBuilder\Documents;

use Elementor\Controls_Manager;
use Elementor\Modules\Library\Documents\Library_Document;
use Elementor\Utils;
use PieCyfer\Core\ThemeBuilder\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Port of `elementor-pro/modules/theme-builder/documents/theme-document.php`.
 *
 * Registering these document types is the single most important part of the
 * Theme Builder replacement, and the reason is not obvious: when a
 * `_elementor_template_type` is not registered, Elementor does not error — it
 * quietly falls back to the `post` document class
 * (`elementor/core/documents-manager.php:305`). The fallback's
 * get_css_wrapper_selector() is `body.elementor-page-{id}`, so the header's
 * generated CSS would be written scoped to `body.elementor-page-171` instead of
 * `.elementor-171`, and the header would lose every style rule.
 *
 * Nothing about that failure is visible until the CSS cache is flushed and the
 * files are regenerated — possibly weeks later. After the cutover, force
 * `Plugin::$instance->files_manager->clear_cache()` and re-run the pixel harness
 * so the comparison is made against regenerated CSS, not against files Pro left
 * behind.
 *
 * Only four things in Pro's document classes affect front-end output: the
 * wrapper element, the wrapper attributes, the CSS wrapper selector and the
 * body class. Everything else is editor UI. This port keeps the four, plus
 * enough control registrations that saving a template in the editor does not
 * silently drop settings that already exist in the database.
 */
abstract class ThemeDocument extends Library_Document {

	public const LOCATION_META_KEY = '_elementor_location';

	public static function get_properties() {
		$properties = parent::get_properties();

		// Puts these templates under the "Theme" tab group in
		// Templates -> Saved Templates. Core reads it; it is not Pro-only.
		$properties['admin_tab_group']    = Module::ADMIN_LIBRARY_TAB_GROUP;
		$properties['support_kit']        = true;
		$properties['support_conditions'] = true;

		// The Theme Builder app is Pro React we cannot reproduce (spec §5.7).
		// Declared false rather than omitted so anything reading the property
		// gets a definite answer instead of null.
		$properties['support_site_editor'] = false;

		return $properties;
	}

	public function get_name() {
		return static::get_type();
	}

	/**
	 * Where this document may be printed.
	 *
	 * The static property comes first and the `_elementor_location` postmeta is
	 * the fallback — that fallback is the entire mechanism by which a `section`
	 * document can be placed into a location.
	 */
	public function get_location() {
		$value = static::get_property( 'location' );

		if ( ! $value ) {
			$value = $this->get_main_meta( self::LOCATION_META_KEY );
		}

		return $value;
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	public function before_get_content() {
		/*
		 * Pro switches the global query here via its Preview_Manager. That
		 * manager returns immediately unless the *queried* post is itself a
		 * theme document, which never happens on a front-end request, so on the
		 * front end this is a no-op and is left as one. Single_Base overrides it
		 * for the `loop_start` the_post() call, which is not a no-op.
		 */
	}

	public function after_get_content() {
	}

	public function get_content( $with_css = false ) {
		$this->before_get_content();

		$content = parent::get_content( $with_css );

		$this->after_get_content();

		return $content;
	}

	public function print_content() {
		$plugin = \Elementor\Plugin::$instance;

		if ( $plugin->preview->is_preview_mode( $this->get_main_id() ) ) {
			echo $plugin->preview->builder_wrapper( '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core method, already safe.
		} else {
			echo $this->get_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered Elementor content.
		}
	}

	/**
	 * Append `elementor-location-{location}` to the wrapper class.
	 *
	 * It is the *location*, not the document type. That is why template 8716
	 * (`error-404`) renders with `elementor-location-single` and 8711
	 * (`search-results`) with `elementor-location-archive`.
	 */
	public function get_container_attributes() {
		$attributes = parent::get_container_attributes();

		$location = Module::instance()->get_locations_manager()->get_current_location();

		if ( $location ) {
			$attributes['class'] .= ' elementor-location-' . $location;
		}

		return $attributes;
	}

	/**
	 * Wrapper element, honouring the document's HTML-tag setting.
	 *
	 * All ten theme documents on this site use the default `div`, verified from
	 * the live HTML — but the control exists in their saved settings, so the
	 * lookup has to be here or the setting would be dropped on the next save.
	 *
	 * @param array<int,mixed>|null $elements_data
	 */
	public function print_elements_with_wrapper( $elements_data = null ) {
		$has_wrapper_tags = $this->get_wrapper_tags();
		$settings         = $this->get_settings_for_display();
		$wrapper_tag      = 'div';

		if ( $has_wrapper_tags && ! empty( $settings['content_wrapper_html_tag'] ) ) {
			$wrapper_tag = Utils::validate_html_tag( $settings['content_wrapper_html_tag'] );
		}

		if ( ! $elements_data ) {
			$elements_data = $this->get_elements_data();
		}

		?>
		<<?php Utils::print_validated_html_tag( $wrapper_tag ); ?> <?php Utils::print_html_attributes( $this->get_container_attributes() ); ?>>
			<?php $this->print_elements( $elements_data ); ?>
		</<?php Utils::print_validated_html_tag( $wrapper_tag ); ?>>
		<?php
	}

	/**
	 * @return string[] `div` is prepended by the control, not listed here.
	 */
	public function get_wrapper_tags() {
		return array(
			'main',
			'article',
			'header',
			'footer',
			'section',
			'aside',
			'nav',
		);
	}

	/* ---------------------------------------------------------------------
	 * Controls
	 * ------------------------------------------------------------------ */

	protected function register_controls() {
		parent::register_controls();

		$this->register_preview_controls();
		$this->inject_html_tag_control();
	}

	/**
	 * Keep the preview settings *values* without the preview settings *feature*.
	 *
	 * "Preview Dynamic Content as" is Pro editor behaviour we are not porting
	 * (spec §3.7, §5.7). But Elementor drops any saved setting that has no
	 * matching control the next time the document is saved — so simply omitting
	 * these would mean that opening a theme template in the editor and pressing
	 * Update silently deletes `preview_type` / `preview_id` from the database.
	 * Registering them as hidden controls preserves the data at the cost of
	 * nothing.
	 */
	private function register_preview_controls(): void {
		$this->start_controls_section(
			'preview_settings',
			array(
				'label' => esc_html__( 'Preview Settings', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_SETTINGS,
			)
		);

		foreach ( array( 'preview_type', 'preview_id', 'preview_search_term' ) as $control_id ) {
			$this->add_control(
				$control_id,
				array(
					'type'   => Controls_Manager::HIDDEN,
					'export' => false,
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * The wrapper-tag control, injected next to post status exactly where Pro
	 * puts it so the saved control id and its position both match.
	 */
	private function inject_html_tag_control(): void {
		$wrapper_tags = $this->get_wrapper_tags();

		if ( ! $wrapper_tags ) {
			return;
		}

		array_unshift( $wrapper_tags, 'div' );

		$this->start_injection(
			array(
				'of'       => 'post_status',
				'fallback' => array(
					'of' => 'post_title',
				),
			)
		);

		$this->add_control(
			'content_wrapper_html_tag',
			array(
				'label'   => esc_html__( 'HTML Tag', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'div',
				'options' => array_combine( $wrapper_tags, $wrapper_tags ),
			)
		);

		$this->end_injection();
	}
}
