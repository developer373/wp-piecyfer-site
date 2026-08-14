<?php
/**
 * The `popup` dynamic tag — the link that opens a popup.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Popup;

use Elementor\Controls_Manager;
use Elementor\Core\Base\Document as DocumentBase;
use Elementor\Core\DynamicTags\Tag as BaseTag;
use Elementor\Modules\DynamicTags\Module as DynamicTagsModule;

defined( 'ABSPATH' ) || exit;

/**
 * Replacement for the `popup` dynamic tag.
 *
 * ============================================================================
 *  THIS IS A PORT OF VAMTAM'S TAG, NOT OF PRO'S — and that is deliberate
 * ============================================================================
 *
 * Two plugins register a tag called `popup` on this site:
 *
 *   - Elementor Pro, at `modules/popup/tag.php`, priority 10;
 *   - VamTam's companion plugin, at
 *     `vamtam-elementor-integration-tecnologia/includes/dynamic-tags/vamtam-popup.php`,
 *     priority **100**.
 *
 * The tags manager is a plain array keyed on the tag name, so **VamTam's wins**
 * and Pro's never runs. Porting Pro's would have produced markup that differs
 * from the baseline on every one of the six links, and the difference would have
 * been inside a base64 blob where it is easy to miss.
 *
 * The one difference is VamTam's extra `align_with_parent` switcher, which is
 * emitted into the action-hash payload whether or not it is set. Live output,
 * decoded from the baseline:
 *
 *     {"id":"7718","toggle":false,"align_with_parent":""}
 *
 * Key order is part of that contract: the payload is base64-encoded, so
 * reordering the array changes every href on the site.
 *
 * The behaviour behind the flag lives in the theme's own JavaScript
 * (`vamtam-elementor-frontend.js`, `VamtamActionLinksHandler`), which reads the
 * href, decodes it and repositions the dialog under the clicked element. That
 * code is not Pro's and survives the cutover, so it must keep receiving the key.
 *
 * VamTam's tag disappears on its own the day `elementor-pro/` is deleted — the
 * file `return`s early on `class_exists( 'ElementorPro\Plugin' )` and its class
 * extends a Pro base class. So the transition is: VamTam's tag serves today, ours
 * serves afterwards, and the output is identical across the change.
 *
 * ============================================================================
 *
 * All six uses on this site are `action = open` against popup 7718, on a
 * `button.link` control:
 *
 *   - 171  header, elements eefe266 and eee060b  ("Request a Meeting")
 *   - 1273 footer, elements 1087c6b0 and b153be4 ("Book Your Slot")
 *   - 146  Home page, element 0ebddb8            ("Request A Consultation")
 *   - 995521 "HomeBannerOriginal-Old", draft, element c4e91af
 *
 * The header is on every page, which is the whole reason popup 7718 appears
 * everywhere.
 */
final class Tag extends BaseTag {

	public function get_name() {
		return 'popup';
	}

	public function get_title() {
		return esc_html__( 'Popup', 'piecyfer-core' );
	}

	/**
	 * The `action` group, registered by `PieCyfer\Core\DynamicTags\Manager`.
	 *
	 * Literal rather than a constant because Pro's `DynamicTagsModule::ACTION_GROUP`
	 * is the only place that constant exists, and depending on it would put a Pro
	 * class reference on the hot path of every button link.
	 */
	public function get_group() {
		return 'action';
	}

	/**
	 * @return string[]
	 */
	public function get_categories() {
		return array( DynamicTagsModule::URL_CATEGORY );
	}

	protected function register_controls() {
		$this->add_control(
			'action',
			array(
				'label'   => esc_html__( 'Action', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'open',
				'options' => array(
					'open'   => esc_html__( 'Open Popup', 'piecyfer-core' ),
					'close'  => esc_html__( 'Close Popup', 'piecyfer-core' ),
					'toggle' => esc_html__( 'Toggle Popup', 'piecyfer-core' ),
				),
			)
		);

		/*
		 * Pro and VamTam both use Pro's QUERY_CONTROL_ID autocomplete here. The
		 * stored value is a bare post id either way, so a SELECT2 over the same
		 * objects round-trips saved data identically — the same substitution
		 * `DynamicTags\Tags\InternalUrl` already makes.
		 */
		$this->add_control(
			'popup',
			array(
				'label'       => esc_html__( 'Popup', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => $this->get_popup_options(),
				'label_block' => true,
				'condition'   => array(
					'action' => array( 'open', 'toggle' ),
				),
			)
		);

		$this->add_control(
			'do_not_show_again',
			array(
				'label'     => esc_html__( 'Don\'t Show Again', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array(
					'action' => 'close',
				),
			)
		);

		$this->add_control(
			'align_with_parent',
			array(
				'label' => esc_html__( 'Align With Parent', 'piecyfer-core' ),
				'type'  => Controls_Manager::SWITCHER,
			)
		);

		$this->add_control(
			'align_with_parent_notice',
			array(
				'type'      => Controls_Manager::RAW_HTML,
				'raw'       => esc_html__( 'On desktop, the popup will be positioned relative to its parent.', 'piecyfer-core' ),
				'condition' => array(
					'align_with_parent!' => '',
				),
			)
		);
	}

	/**
	 * Keep empty so the default Before/After advanced section is not added.
	 *
	 * Not cosmetic: this tag's value is a URL fragment. A `before` or `after`
	 * string would be concatenated straight into the href and break the action
	 * hash. Pro and VamTam both suppress it for the same reason.
	 */
	protected function register_advanced_section() {}

	public function render() {
		$settings = $this->get_active_settings();

		if ( 'close' === ( $settings['action'] ?? '' ) ) {
			$this->print_close_popup_link( $settings );

			return;
		}

		$this->print_open_popup_link( $settings );
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function print_open_popup_link( array $settings ): void {
		if ( empty( $settings['popup'] ) ) {
			return;
		}

		/*
		 * `create_action_hash()` is Elementor **free**
		 * (`includes/frontend.php:1338`) — it is a rawurlencode of
		 * "elementor-action:action=…&settings=<base64 json>". Nothing about the
		 * link format depends on Pro.
		 *
		 * The array order below is the byte-for-byte contract with the baseline.
		 * `popup` is left as the stored string ("7718", not 7718) because
		 * json_encode would otherwise emit a number and change the hash.
		 */
		$link_action_url = \Elementor\Plugin::$instance->frontend->create_action_hash(
			'popup:open',
			array(
				'id'                => $settings['popup'],
				'toggle'            => 'toggle' === ( $settings['action'] ?? '' ),
				'align_with_parent' => $settings['align_with_parent'] ?? '',
			)
		);

		/*
		 * Rendering the link is also what puts the popup on the page. There is no
		 * display condition involved: this call is the entire routing decision.
		 */
		Module::add_popup_to_location( $settings['popup'] );

		echo $link_action_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- create_action_hash() rawurlencodes its own output.
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function print_close_popup_link( array $settings ): void {
		$link_action_url = \Elementor\Plugin::$instance->frontend->create_action_hash(
			'popup:close',
			array(
				'do_not_show_again' => $settings['do_not_show_again'] ?? '',
			)
		);

		echo $link_action_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- create_action_hash() rawurlencodes its own output.
	}

	/**
	 * Every popup document, for the picker.
	 *
	 * @return array<int,string>
	 */
	private function get_popup_options(): array {
		$options = array();

		$popups = get_posts(
			array(
				'post_type'              => \Elementor\TemplateLibrary\Source_Local::CPT,
				'post_status'            => array( 'publish', 'private' ),
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'               => DocumentBase::TYPE_META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'             => Module::DOCUMENT_TYPE,
			)
		);

		foreach ( $popups as $popup ) {
			$options[ $popup->ID ] = $popup->post_title;
		}

		return $options;
	}
}
