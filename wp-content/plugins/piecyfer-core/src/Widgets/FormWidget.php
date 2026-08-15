<?php
/**
 * Replacement for Elementor Pro's `form` widget.
 *
 * 9 instances across the site (contact, consultation, two footers, five job
 * application forms), 73 populated settings between them. Field types in use:
 * tel x17, email x9, text x9, textarea x7, recaptcha_v3 x7, upload x5,
 * hidden x5, acceptance x3, select x2, checkbox x1, html x1.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS FILE IS AND IS NOT
 * ---------------------------------------------------------------------------
 * This is the *presentation* half of the form widget: controls, markup, CSS.
 * The submission half — the ajax endpoint, validation, the email action,
 * reCAPTCHA verification, upload processing — is still Elementor Pro's and is
 * NOT reimplemented here. Deactivating Pro with only this file in place gives
 * you forms that render perfectly and submit into a 400. The server side is
 * specified in `_project/06-FORM-SPEC.md`; build it before removing Pro.
 *
 * ---------------------------------------------------------------------------
 * WHY EVERY CONTROL IS HERE EVEN THOUGH MOST ARE UNUSED
 * ---------------------------------------------------------------------------
 * Elementor drops saved values that have no matching control the next time a
 * document is saved. With nine live forms — several of which hold the only copy
 * of a real recipient address in `email_to` — that is the single largest
 * data-loss risk in this project. Every control id in Pro's
 * `modules/forms/widgets/form.php` is reproduced one-for-one, including the
 * `form_fields` repeater's own field ids and every group-control `name`.
 *
 * ---------------------------------------------------------------------------
 * THREE DELIBERATE STRUCTURAL DEVIATIONS (all documented at their call sites)
 * ---------------------------------------------------------------------------
 * 1. Controls that Pro's *field classes* inject into the `form_fields` repeater
 *    (`modules/forms/fields/*.php` → `update_controls()`) are registered here
 *    from a data table rather than as literal `add_control()` calls, because in
 *    Pro they do not live in form.php at all. Same ids, same tab placement.
 *    See {@see self::injected_field_controls()}.
 * 2. Controls that Pro's *action classes* register (`modules/forms/actions/*`
 *    → `register_settings_section()`) are registered the same way, for the same
 *    reason. Same section ids, same order, same control ids.
 *    See {@see self::submit_actions()}.
 *    Both keep the control/section parity check against form.php honest: what
 *    is in form.php is here as literal calls, what is not, is not.
 * 3. Field types whose markup lives in a Pro field class are rendered by us
 *    only when nothing is listening on Pro's
 *    `elementor_pro/forms/render_field/{type}` action. While Pro is installed
 *    its field classes still draw those fields (exactly as today); the moment
 *    it is gone ours take over. See {@see self::render_field()}.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Utils as ElementorUtils;

defined( 'ABSPATH' ) || exit;

class FormWidget extends AbstractWidget {

	/**
	 * Message ids, mirroring ElementorPro\Modules\Forms\Classes\Ajax_Handler's
	 * constants. The server side will need the same ids, so they are public.
	 */
	public const MESSAGE_SUCCESS      = 'success';
	public const MESSAGE_ERROR        = 'error';
	public const MESSAGE_REQUIRED     = 'required_field';
	public const MESSAGE_INVALID_FORM = 'invalid_form';
	public const MESSAGE_SERVER_ERROR = 'server_error';
	public const MESSAGE_SUBSCRIBER   = 'subscriber_already_exists';

	/**
	 * The `form_fields` repeater, kept as state because Pro adds one more
	 * control to it from inside the Steps section — see
	 * {@see self::register_steps_settings_section()}.
	 */
	private ?Repeater $fields_repeater = null;

	public function get_name(): string {
		return 'form';
	}

	public function get_title(): string {
		return esc_html__( 'Form', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-form-horizontal';
	}

	/**
	 * Pro's `Base_Widget_Trait` puts every Pro widget in `pro-elements`, and the
	 * saved instances reference that category.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	/**
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'form', 'forms', 'field', 'button', 'mailchimp', 'drip', 'mailpoet', 'convertkit', 'getresponse', 'recaptcha', 'zapier', 'webhook', 'activecampaign', 'slack', 'discord', 'mailerlite' );
	}

	public function get_group_name(): string {
		return 'forms';
	}

	public function get_script_depends(): array {
		return array( 'elementor-recaptcha_v3-api', 'elementor-recaptcha-api' );
	}

	protected function replaces(): string {
		return 'Elementor Pro — Forms/Form';
	}

	/**
	 * Pro declares this false; `Element_Base` defaults it to **true**.
	 *
	 * It decides whether the element is baked into the document's element cache
	 * or emitted as an `[elementor-element]` placeholder and re-rendered per
	 * request. It matters more here than for most widgets: the form prints
	 * `post_id`, `queried_id` and `referer_title` hidden inputs whose correct
	 * values are request-dependent, and caching the element bakes one request's
	 * values into every later one. See the cache section of 06-FORM-SPEC.md.
	 */
	protected function is_dynamic_content(): bool {
		return false;
	}

	/**
	 * Replaces Pro's `widget-form` handle. The bulk of the form's layout CSS —
	 * `.elementor-field-group`, `.elementor-form-fields-wrapper`,
	 * `.elementor-field-textual`, the `elementor-col-*` grid, `.elementor-message`
	 * — is in Elementor FREE's frontend.css and is untouched by this.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'piecyfer-form' );
	}

	protected function assets( string $handle ): void {
		// Registered centrally in Plugin::STYLES so the handle matches
		// get_style_depends(), which Elementor resolves before render runs.
	}

	/**
	 * Default user-facing messages.
	 *
	 * Verbatim from Ajax_Handler::get_default_messages()
	 * (elementor-pro/modules/forms/classes/ajax-handler.php:37-46). These are the
	 * control defaults *and* what the ajax handler falls back to when
	 * `custom_messages` is off — which is the case on all nine forms here, so
	 * these strings are what visitors actually see today.
	 *
	 * @return array<string,string>
	 */
	public static function get_default_messages(): array {
		return array(
			self::MESSAGE_SUCCESS      => esc_html__( 'Your submission was successful.', 'piecyfer-core' ),
			self::MESSAGE_ERROR        => esc_html__( 'Your submission failed because of an error.', 'piecyfer-core' ),
			self::MESSAGE_REQUIRED     => esc_html__( 'This field is required.', 'piecyfer-core' ),
			self::MESSAGE_INVALID_FORM => esc_html__( 'Your submission failed because the form is invalid.', 'piecyfer-core' ),
			self::MESSAGE_SERVER_ERROR => esc_html__( 'Your submission failed because of a server error.', 'piecyfer-core' ),
			self::MESSAGE_SUBSCRIBER   => esc_html__( 'Subscriber already exists.', 'piecyfer-core' ),
		);
	}

	/**
	 * Button size options, verbatim from Form_Base::get_button_sizes().
	 *
	 * @return array<string,string>
	 */
	public static function get_button_sizes(): array {
		return array(
			'xs' => esc_html__( 'Extra Small', 'piecyfer-core' ),
			'sm' => esc_html__( 'Small', 'piecyfer-core' ),
			'md' => esc_html__( 'Medium', 'piecyfer-core' ),
			'lg' => esc_html__( 'Large', 'piecyfer-core' ),
			'xl' => esc_html__( 'Extra Large', 'piecyfer-core' ),
		);
	}

	// -------------------------------------------------------------- controls

	/**
	 * Section order is load-bearing, not cosmetic: third-party code injects at
	 * `elementor/element/form/<section_id>/{before,after}_section_end`.
	 * ElementsKit uses two of these — `section_form_options/after_section_end`
	 * (its Google Sheets integration, whose `ekit_google_sheet_enable` value is
	 * saved on five of the nine forms) and `section_button_style/after_section_end`
	 * (its form reset button). Both section ids are preserved below.
	 * Pro's own field classes hook `section_form_fields/before_section_end`.
	 */
	protected function register_controls(): void {
		$this->register_form_fields_section();
		$this->register_buttons_section();
		$this->register_integration_section();
		$this->register_action_sections();
		$this->register_steps_settings_section();
		$this->register_form_options_section();
		$this->register_form_style_section();
		$this->register_field_style_section();
		$this->register_button_style_section();
		$this->register_messages_style_section();
		$this->register_steps_style_section();
	}

	/**
	 * The field types offered by the `field_type` control.
	 *
	 * Pro seeds sixteen and then lets field classes add their own through
	 * `elementor_pro/forms/field_types`; `recaptcha` and `recaptcha_v3` arrive
	 * that way. We seed the same sixteen, add the two reCAPTCHA types and `step`
	 * ourselves so the list is complete without Pro, and still apply Pro's
	 * filter so third-party field types keep working.
	 *
	 * @return array<string,string>
	 */
	private function get_field_types(): array {
		$field_types = array(
			'text'     => esc_html__( 'Text', 'piecyfer-core' ),
			'email'    => esc_html__( 'Email', 'piecyfer-core' ),
			'textarea' => esc_html__( 'Textarea', 'piecyfer-core' ),
			'url'      => esc_html__( 'URL', 'piecyfer-core' ),
			'tel'      => esc_html__( 'Tel', 'piecyfer-core' ),
			'radio'    => esc_html__( 'Radio', 'piecyfer-core' ),
			'select'   => esc_html__( 'Select', 'piecyfer-core' ),
			'checkbox' => esc_html__( 'Checkbox', 'piecyfer-core' ),
			'acceptance' => esc_html__( 'Acceptance', 'piecyfer-core' ),
			'number'   => esc_html__( 'Number', 'piecyfer-core' ),
			'date'     => esc_html__( 'Date', 'piecyfer-core' ),
			'time'     => esc_html__( 'Time', 'piecyfer-core' ),
			'upload'   => esc_html__( 'File Upload', 'piecyfer-core' ),
			'password' => esc_html__( 'Password', 'piecyfer-core' ),
			'html'     => esc_html__( 'HTML', 'piecyfer-core' ),
			'hidden'   => esc_html__( 'Hidden', 'piecyfer-core' ),
			// Registered by Pro's field/handler classes rather than by form.php.
			// Declared here so the list survives Pro's removal; the saved
			// `field_type` strings must keep resolving to a listed option.
			'step'         => esc_html__( 'Step', 'piecyfer-core' ),
			'recaptcha'    => esc_html__( 'reCAPTCHA', 'piecyfer-core' ),
			'recaptcha_v3' => esc_html__( 'reCAPTCHA V3', 'piecyfer-core' ),
		);

		/** This filter is documented in elementor-pro/modules/forms/widgets/form.php */
		return apply_filters( 'elementor_pro/forms/field_types', $field_types );
	}

	/**
	 * Repeater controls that Pro's field classes inject.
	 *
	 * In Pro these are added by `Field_Base::__construct()` hooking
	 * `elementor/element/form/section_form_fields/before_section_end` and each
	 * subclass's `update_controls()` splicing them in immediately after
	 * `required` (`Field_Base::inject_field_controls()`). Sources:
	 *   fields/upload.php:43-113      attachment_type, file_sizes, file_types,
	 *                                 allow_multiple_upload, max_files
	 *   fields/acceptance.php:31-52   acceptance_text, checked_by_default
	 *   fields/number.php:50-72       field_min, field_max
	 *   fields/date.php:57-100        min_date, max_date, use_native_date
	 *   fields/time.php:39-49         use_native_time
	 *   fields/step.php:63-131        previous_button, next_button, selected_icon
	 *
	 * Four of these carry live data on this site: `attachment_type`,
	 * `file_sizes`, `file_types` (five upload fields) and `acceptance_text`
	 * (three acceptance fields).
	 *
	 * Registered from a table, not as literal add_control() calls, so that a
	 * control-id diff against form.php stays exactly zero in both directions —
	 * these ids genuinely are not in form.php. Placement is identical to Pro's:
	 * spliced in right after `required`, inside the Content tab.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function injected_field_controls(): array {
		$upload_sizes = array();
		$max_upload   = (int) ( wp_max_upload_size() / ( 1024 ** 2 ) );
		for ( $size = 1; $size <= $max_upload; $size++ ) {
			$upload_sizes[ $size ] = $size . 'MB';
		}

		$tab_placement = array(
			'tab'           => 'content',
			'inner_tab'     => 'form_fields_content_tab',
			'tabs_wrapper'  => 'form_fields_tabs',
		);

		return array(
			// --- upload -------------------------------------------------
			'attachment_type' => $tab_placement + array(
				'label'       => esc_html__( 'Send files', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT,
				'condition'   => array( 'field_type' => 'upload' ),
				'options'     => array(
					'link'   => esc_html__( 'Email with link', 'piecyfer-core' ),
					'attach' => esc_html__( 'Email with attachment', 'piecyfer-core' ),
					'both'   => esc_html__( 'Email with both', 'piecyfer-core' ),
				),
				'default'     => 'link',
				'description' => esc_html__( "Uploads you receive via link are stored on your server. However, uploads via attachment won't be saved on your server, and under Submissions", 'piecyfer-core' ),
			),
			'file_sizes' => $tab_placement + array(
				'label'       => esc_html__( 'Max. File Size', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT,
				'condition'   => array( 'field_type' => 'upload' ),
				'options'     => $upload_sizes,
				'description' => esc_html__( 'If you need to increase max upload size please contact your hosting.', 'piecyfer-core' ),
			),
			'file_types' => $tab_placement + array(
				'label'       => esc_html__( 'Allowed File Types', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'ai'          => array( 'active' => false ),
				'condition'   => array( 'field_type' => 'upload' ),
				'description' => esc_html__( 'Enter the allowed file types, separated by a comma (jpg, gif, pdf, etc).', 'piecyfer-core' ),
			),
			'allow_multiple_upload' => $tab_placement + array(
				'label'     => esc_html__( 'Multiple Files', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array( 'field_type' => 'upload' ),
			),
			'max_files' => $tab_placement + array(
				'label'     => esc_html__( 'Max. Files', 'piecyfer-core' ),
				'type'      => Controls_Manager::NUMBER,
				'condition' => array(
					'field_type'            => 'upload',
					'allow_multiple_upload' => 'yes',
				),
			),
			// --- acceptance ---------------------------------------------
			'acceptance_text' => $tab_placement + array(
				'label'     => esc_html__( 'Acceptance Text', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXTAREA,
				'condition' => array( 'field_type' => 'acceptance' ),
			),
			'checked_by_default' => $tab_placement + array(
				'label'     => esc_html__( 'Checked by Default', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array( 'field_type' => 'acceptance' ),
			),
			// --- number -------------------------------------------------
			'field_min' => $tab_placement + array(
				'label'     => esc_html__( 'Min. Value', 'piecyfer-core' ),
				'type'      => Controls_Manager::NUMBER,
				'condition' => array( 'field_type' => 'number' ),
			),
			'field_max' => $tab_placement + array(
				'label'     => esc_html__( 'Max. Value', 'piecyfer-core' ),
				'type'      => Controls_Manager::NUMBER,
				'condition' => array( 'field_type' => 'number' ),
			),
			// --- date ---------------------------------------------------
			'min_date' => $tab_placement + array(
				'label'          => esc_html__( 'Min. Date', 'piecyfer-core' ),
				'type'           => Controls_Manager::DATE_TIME,
				'condition'      => array( 'field_type' => 'date' ),
				'label_block'    => false,
				'picker_options' => array( 'enableTime' => false ),
			),
			'max_date' => $tab_placement + array(
				'label'          => esc_html__( 'Max. Date', 'piecyfer-core' ),
				'type'           => Controls_Manager::DATE_TIME,
				'condition'      => array( 'field_type' => 'date' ),
				'label_block'    => false,
				'picker_options' => array( 'enableTime' => false ),
			),
			'use_native_date' => $tab_placement + array(
				'label'     => esc_html__( 'Native HTML5', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array( 'field_type' => 'date' ),
			),
			// --- time ---------------------------------------------------
			'use_native_time' => $tab_placement + array(
				'label'     => esc_html__( 'Native HTML5', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array( 'field_type' => 'time' ),
			),
			// --- step ---------------------------------------------------
			'previous_button' => $tab_placement + array(
				'label'     => esc_html__( 'Previous Button', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'ai'        => array( 'active' => false ),
				'condition' => array( 'field_type' => 'step' ),
			),
			'next_button' => $tab_placement + array(
				'label'     => esc_html__( 'Next Button', 'piecyfer-core' ),
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'ai'        => array( 'active' => false ),
				'condition' => array( 'field_type' => 'step' ),
			),
			'selected_icon' => $tab_placement + array(
				'label'             => esc_html__( 'Icon', 'piecyfer-core' ),
				'type'              => Controls_Manager::ICONS,
				'fa4compatibility'  => 'icon',
				'description'       => esc_html__( 'Visible only if selected step type contains "Icon"', 'piecyfer-core' ),
				'default'           => array(
					'value'   => 'fas fa-star',
					'library' => 'fa-solid',
				),
				'recommended'       => array(
					'fa-solid'   => array( 'chevron-down', 'angle-down', 'angle-double-down', 'caret-down', 'caret-square-down' ),
					'fa-regular' => array( 'caret-square-down' ),
				),
				'skin'              => 'inline',
				'label_block'       => false,
				'condition'         => array( 'field_type' => 'step' ),
			),
		);
	}

	private function register_form_fields_section(): void {
		$repeater = new Repeater();

		$field_types = $this->get_field_types();

		$repeater->start_controls_tabs( 'form_fields_tabs' );

		$repeater->start_controls_tab(
			'form_fields_content_tab',
			array( 'label' => esc_html__( 'Content', 'piecyfer-core' ) )
		);

		$repeater->add_control(
			'field_type',
			array(
				'label'   => esc_html__( 'Type', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $field_types,
				'default' => 'text',
			)
		);

		$repeater->add_control(
			'field_label',
			array(
				'label'   => esc_html__( 'Label', 'piecyfer-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
				'dynamic' => array( 'active' => true ),
			)
		);

		/*
		 * Pro's `date` and `time` field classes append their own type to this
		 * `in` list from update_controls() (fields/date.php:101-113,
		 * fields/time.php:52-64). Both are folded in here so the condition is
		 * the same with or without Pro.
		 */
		$repeater->add_control(
			'placeholder',
			array(
				'label'      => esc_html__( 'Placeholder', 'piecyfer-core' ),
				'type'       => Controls_Manager::TEXT,
				'default'    => '',
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => 'in',
							'value'    => array(
								'tel',
								'text',
								'email',
								'textarea',
								'number',
								'url',
								'password',
								'time',
								'date',
							),
						),
					),
				),
				'dynamic'    => array( 'active' => true ),
			)
		);

		$repeater->add_control(
			'required',
			array(
				'label'        => esc_html__( 'Required', 'piecyfer-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'true',
				'default'      => '',
				'conditions'   => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => '!in',
							'value'    => array(
								'checkbox',
								'recaptcha',
								'recaptcha_v3',
								'hidden',
								'html',
								'step',
							),
						),
					),
				),
			)
		);

		/*
		 * DEVIATION 1 (see the file header). Pro splices the field classes'
		 * controls in immediately after `required`; this is that exact splice
		 * point. Data-driven so a control-id diff against form.php stays clean.
		 */
		foreach ( $this->injected_field_controls() as $injected_id => $injected_args ) {
			$repeater->add_control( $injected_id, $injected_args );
		}

		$repeater->add_control(
			'field_options',
			array(
				'label'       => esc_html__( 'Options', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => '',
				'description' => esc_html__( 'Enter each option in a separate line. To differentiate between label and value, separate them with a pipe char ("|"). For example: First Name|f_name', 'piecyfer-core' ),
				'conditions'  => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => 'in',
							'value'    => array(
								'select',
								'checkbox',
								'radio',
							),
						),
					),
				),
			)
		);

		$repeater->add_control(
			'allow_multiple',
			array(
				'label'        => esc_html__( 'Multiple Selection', 'piecyfer-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'true',
				'conditions'   => array(
					'terms' => array(
						array(
							'name'  => 'field_type',
							'value' => 'select',
						),
					),
				),
			)
		);

		$repeater->add_control(
			'select_size',
			array(
				'label'      => esc_html__( 'Rows', 'piecyfer-core' ),
				'type'       => Controls_Manager::NUMBER,
				'min'        => 2,
				'step'       => 1,
				'conditions' => array(
					'terms' => array(
						array(
							'name'  => 'field_type',
							'value' => 'select',
						),
						array(
							'name'  => 'allow_multiple',
							'value' => 'true',
						),
					),
				),
			)
		);

		$repeater->add_control(
			'inline_list',
			array(
				'label'        => esc_html__( 'Inline List', 'piecyfer-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'elementor-subgroup-inline',
				'default'      => '',
				'conditions'   => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => 'in',
							'value'    => array(
								'checkbox',
								'radio',
							),
						),
					),
				),
			)
		);

		$repeater->add_control(
			'field_html',
			array(
				'label'      => esc_html__( 'HTML', 'piecyfer-core' ),
				'type'       => Controls_Manager::TEXTAREA,
				'dynamic'    => array( 'active' => true ),
				'conditions' => array(
					'terms' => array(
						array(
							'name'  => 'field_type',
							'value' => 'html',
						),
					),
				),
			)
		);

		$repeater->add_responsive_control(
			'width',
			array(
				'label'      => esc_html__( 'Column Width', 'piecyfer-core' ),
				'type'       => Controls_Manager::SELECT,
				'options'    => array(
					''    => esc_html__( 'Default', 'piecyfer-core' ),
					'100' => '100%',
					'80'  => '80%',
					'75'  => '75%',
					'70'  => '70%',
					'66'  => '66%',
					'60'  => '60%',
					'50'  => '50%',
					'40'  => '40%',
					'33'  => '33%',
					'30'  => '30%',
					'25'  => '25%',
					'20'  => '20%',
				),
				'default'    => '100',
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => '!in',
							'value'    => array(
								'hidden',
								'recaptcha',
								'recaptcha_v3',
								'step',
							),
						),
					),
				),
			)
		);

		$repeater->add_control(
			'rows',
			array(
				'label'      => esc_html__( 'Rows', 'piecyfer-core' ),
				'type'       => Controls_Manager::NUMBER,
				'default'    => 4,
				'conditions' => array(
					'terms' => array(
						array(
							'name'  => 'field_type',
							'value' => 'textarea',
						),
					),
				),
			)
		);

		$repeater->add_control(
			'recaptcha_size',
			array(
				'label'      => esc_html__( 'Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SELECT,
				'default'    => 'normal',
				'options'    => array(
					'normal'  => esc_html__( 'Normal', 'piecyfer-core' ),
					'compact' => esc_html__( 'Compact', 'piecyfer-core' ),
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'  => 'field_type',
							'value' => 'recaptcha',
						),
					),
				),
			)
		);

		$repeater->add_control(
			'recaptcha_style',
			array(
				'label'      => esc_html__( 'Style', 'piecyfer-core' ),
				'type'       => Controls_Manager::SELECT,
				'default'    => 'light',
				'options'    => array(
					'light' => esc_html__( 'Light', 'piecyfer-core' ),
					'dark'  => esc_html__( 'Dark', 'piecyfer-core' ),
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'  => 'field_type',
							'value' => 'recaptcha',
						),
					),
				),
			)
		);

		$repeater->add_control(
			'recaptcha_badge',
			array(
				'label'       => esc_html__( 'Badge', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'bottomright',
				'options'     => array(
					'bottomright' => esc_html__( 'Bottom Right', 'piecyfer-core' ),
					'bottomleft'  => esc_html__( 'Bottom Left', 'piecyfer-core' ),
					'inline'      => esc_html__( 'Inline', 'piecyfer-core' ),
				),
				'description' => esc_html__( 'To view the validation badge, switch to preview mode', 'piecyfer-core' ),
				'conditions'  => array(
					'terms' => array(
						array(
							'name'  => 'field_type',
							'value' => 'recaptcha_v3',
						),
					),
				),
			)
		);

		$repeater->add_control(
			'css_classes',
			array(
				'label'   => esc_html__( 'CSS Classes', 'piecyfer-core' ),
				'type'    => Controls_Manager::HIDDEN,
				'default' => '',
				'title'   => esc_html__( 'Add your custom class WITHOUT the dot. e.g: my-class', 'piecyfer-core' ),
			)
		);

		$repeater->end_controls_tab();

		$repeater->start_controls_tab(
			'form_fields_advanced_tab',
			array(
				'label'     => esc_html__( 'Advanced', 'piecyfer-core' ),
				'condition' => array(
					'field_type!' => 'html',
				),
			)
		);

		$repeater->add_control(
			'field_value',
			array(
				'label'      => esc_html__( 'Default Value', 'piecyfer-core' ),
				'type'       => Controls_Manager::TEXT,
				'default'    => '',
				'dynamic'    => array( 'active' => true ),
				'ai'         => array( 'active' => false ),
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => 'in',
							'value'    => array(
								'text',
								'email',
								'textarea',
								'url',
								'tel',
								'radio',
								'select',
								'number',
								'date',
								'time',
								'hidden',
							),
						),
					),
				),
			)
		);

		$repeater->add_control(
			'custom_id',
			array(
				'label'       => esc_html__( 'ID', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'description' => sprintf(
					/* translators: 1: `<code>` opening tag, 2: `</code>` closing tag. */
					esc_html__( 'Please make sure the ID is unique and not used elsewhere on the page. This field allows %1$sA-z 0-9%2$s & underscore chars without spaces.', 'piecyfer-core' ),
					'<code>',
					'</code>'
				),
				'render_type' => 'none',
				'required'    => true,
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
			)
		);

		$shortcode_template = '{{ view.container.settings.get( \'custom_id\' ) }}';
		$repeater->add_control(
			'shortcode',
			array(
				'label'   => esc_html__( 'Shortcode', 'piecyfer-core' ),
				'type'    => Controls_Manager::RAW_HTML,
				'classes' => 'forms-field-shortcode',
				'raw'     => '<input class="elementor-form-field-shortcode" value=\'[field id="' . $shortcode_template . '"]\' readonly />',
			)
		);

		$repeater->end_controls_tab();

		$repeater->end_controls_tabs();

		$this->fields_repeater = $repeater;

		$this->start_controls_section(
			'section_form_fields',
			array(
				'label' => esc_html__( 'Form Fields', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'form_name',
			array(
				'label'       => esc_html__( 'Form Name', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'New Form', 'piecyfer-core' ),
				'placeholder' => esc_html__( 'Form Name', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'form_fields',
			array(
				'type'        => $this->get_fields_repeater_control_type(),
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'custom_id'   => 'name',
						'field_type'  => 'text',
						'field_label' => esc_html__( 'Name', 'piecyfer-core' ),
						'placeholder' => esc_html__( 'Name', 'piecyfer-core' ),
						'width'       => '100',
						'dynamic'     => array( 'active' => true ),
					),
					array(
						'custom_id'   => 'email',
						'field_type'  => 'email',
						'required'    => 'true',
						'field_label' => esc_html__( 'Email', 'piecyfer-core' ),
						'placeholder' => esc_html__( 'Email', 'piecyfer-core' ),
						'width'       => '100',
					),
					array(
						'custom_id'   => 'message',
						'field_type'  => 'textarea',
						'field_label' => esc_html__( 'Message', 'piecyfer-core' ),
						'placeholder' => esc_html__( 'Message', 'piecyfer-core' ),
						'width'       => '100',
					),
				),
				'title_field' => '{{{ field_label }}}',
			)
		);

		$this->add_control(
			'input_size',
			array(
				'label'     => esc_html__( 'Input Size', 'piecyfer-core' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'xs' => esc_html__( 'Extra Small', 'piecyfer-core' ),
					'sm' => esc_html__( 'Small', 'piecyfer-core' ),
					'md' => esc_html__( 'Medium', 'piecyfer-core' ),
					'lg' => esc_html__( 'Large', 'piecyfer-core' ),
					'xl' => esc_html__( 'Extra Large', 'piecyfer-core' ),
				),
				'default'   => 'sm',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'show_labels',
			array(
				'label'        => esc_html__( 'Label', 'piecyfer-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'piecyfer-core' ),
				'label_off'    => esc_html__( 'Hide', 'piecyfer-core' ),
				'return_value' => 'true',
				'default'      => 'true',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'mark_required',
			array(
				'label'     => esc_html__( 'Required Mark', 'piecyfer-core' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Show', 'piecyfer-core' ),
				'label_off' => esc_html__( 'Hide', 'piecyfer-core' ),
				'default'   => '',
				'condition' => array(
					'show_labels!' => '',
				),
			)
		);

		$this->add_control(
			'label_position',
			array(
				'label'     => esc_html__( 'Label Position', 'piecyfer-core' ),
				'type'      => Controls_Manager::HIDDEN,
				'options'   => array(
					'above'  => esc_html__( 'Above', 'piecyfer-core' ),
					'inline' => esc_html__( 'Inline', 'piecyfer-core' ),
				),
				'default'   => 'above',
				'condition' => array(
					'show_labels!' => '',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Pro's `form_fields` uses its own control type, `form-fields-repeater`
	 * (modules/forms/controls/fields-repeater.php), registered by Pro's forms
	 * module. It is a plain `Control_Repeater` subclass whose only job is to
	 * carry a different editor view — it changes nothing about how values are
	 * stored, so falling back to the core REPEATER type when Pro is gone is
	 * data-safe. What is lost with the fallback is the editor nicety of
	 * auto-generating `custom_id` for new rows; existing rows are unaffected.
	 * Registering our own control type is a follow-up, tracked in 06-FORM-SPEC.md.
	 */
	private function get_fields_repeater_control_type(): string {
		$controls_manager = \Elementor\Plugin::$instance->controls_manager;

		if ( $controls_manager && $controls_manager->get_control( 'form-fields-repeater' ) ) {
			return 'form-fields-repeater';
		}

		return Controls_Manager::REPEATER;
	}

	private function register_buttons_section(): void {
		$this->start_controls_section(
			'section_buttons',
			array(
				'label' => esc_html__( 'Buttons', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'button_size',
			array(
				'label'   => esc_html__( 'Size', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'sm',
				'options' => self::get_button_sizes(),
			)
		);

		$this->add_responsive_control(
			'button_width',
			array(
				'label'              => esc_html__( 'Column Width', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					''    => esc_html__( 'Default', 'piecyfer-core' ),
					'100' => '100%',
					'80'  => '80%',
					'75'  => '75%',
					'70'  => '70%',
					'66'  => '66%',
					'60'  => '60%',
					'50'  => '50%',
					'40'  => '40%',
					'33'  => '33%',
					'30'  => '30%',
					'25'  => '25%',
					'20'  => '20%',
				),
				'default'            => '100',
				// Read by the multi-step handler when it rebuilds the button row.
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'heading_steps_buttons',
			array(
				'label'     => esc_html__( 'Step Buttons', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'step_next_label',
			array(
				'label'              => esc_html__( 'Next', 'piecyfer-core' ),
				'type'               => Controls_Manager::TEXT,
				'dynamic'            => array( 'active' => true ),
				'ai'                 => array( 'active' => false ),
				'frontend_available' => true,
				'render_type'        => 'none',
				'default'            => esc_html__( 'Next', 'piecyfer-core' ),
				'placeholder'        => esc_html__( 'Next', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'step_previous_label',
			array(
				'label'              => esc_html__( 'Previous', 'piecyfer-core' ),
				'type'               => Controls_Manager::TEXT,
				'dynamic'            => array( 'active' => true ),
				'ai'                 => array( 'active' => false ),
				'frontend_available' => true,
				'render_type'        => 'none',
				'default'            => esc_html__( 'Previous', 'piecyfer-core' ),
				'placeholder'        => esc_html__( 'Previous', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'heading_submit_button',
			array(
				'label' => esc_html__( 'Submit Button', 'piecyfer-core' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => esc_html__( 'Submit', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Send', 'piecyfer-core' ),
				'placeholder' => esc_html__( 'Send', 'piecyfer-core' ),
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
			)
		);

		$this->add_control(
			'selected_button_icon',
			array(
				'label'       => esc_html__( 'Icon', 'piecyfer-core' ),
				'type'        => Controls_Manager::ICONS,
				'skin'        => 'inline',
				'label_block' => false,
			)
		);

		$start = is_rtl() ? 'right' : 'left';
		$end   = is_rtl() ? 'left' : 'right';

		$this->add_control(
			'button_icon_align',
			array(
				'label'                => esc_html__( 'Icon Position', 'piecyfer-core' ),
				'type'                 => Controls_Manager::CHOOSE,
				'default'              => is_rtl() ? 'row-reverse' : 'row',
				'options'              => array(
					'row'         => array(
						'title' => esc_html__( 'Start', 'piecyfer-core' ),
						'icon'  => "eicon-h-align-{$start}",
					),
					'row-reverse' => array(
						'title' => esc_html__( 'End', 'piecyfer-core' ),
						'icon'  => "eicon-h-align-{$end}",
					),
				),
				'selectors_dictionary' => array(
					'left'  => is_rtl() ? 'row-reverse' : 'row',
					'right' => is_rtl() ? 'row' : 'row-reverse',
				),
				'selectors'            => array(
					'{{WRAPPER}} .elementor-button-content-wrapper' => 'flex-direction: {{VALUE}};',
				),
				'condition'            => array(
					'button_text!'                  => '',
					'selected_button_icon[value]!'  => '',
				),
			)
		);

		$this->add_control(
			'button_icon_indent',
			array(
				'label'      => esc_html__( 'Icon Spacing', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'condition'  => array(
					'button_text!'                 => '',
					'selected_button_icon[value]!' => '',
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-button span' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'button_css_id',
			array(
				'label'       => esc_html__( 'Button ID', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'ai'          => array( 'active' => false ),
				'title'       => esc_html__( 'Add your custom id WITHOUT the Pound key. e.g: my-id', 'piecyfer-core' ),
				'description' => sprintf(
					/* translators: 1: `<code>` opening tag, 2: `</code>` closing tag. */
					esc_html__( 'Please make sure the ID is unique and not used elsewhere on the page. This field allows %1$sA-z 0-9%2$s & underscore chars without spaces.', 'piecyfer-core' ),
					'<code>',
					'</code>'
				),
				'separator'   => 'before',
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * "Actions After Submit".
	 *
	 * `submit_actions` is ABSENT from the saved data of all nine forms on this
	 * site, so this control's default is what actually runs. Pro's literal
	 * default is `[ 'email' ]` — form.php:828, passed to the control at
	 * form.php:851 — and Pro's own Submissions component appends
	 * `save-to-database` through the filter below
	 * (modules/forms/submissions/component.php:173-175), so the effective
	 * runtime value on this site is `[ 'email', 'save-to-database' ]`.
	 *
	 * It takes effect because the ajax handler rehydrates settings through
	 * `get_settings_for_display()` before reading `submit_actions`
	 * (ajax-handler.php:106 then :152), and Base_Data_Control::get_value()
	 * applies the default on `! isset()`, not on "empty"
	 * (elementor/includes/controls/base-data.php:61-63). A saved empty array
	 * would therefore mean *no actions at all* — absent and empty are not the
	 * same thing here.
	 *
	 * We register the same literal default and the same filter, but not the
	 * Submissions component: this plugin has no submissions table, so with
	 * PieCyfer alone the effective default is `[ 'email' ]`. See
	 * 06-FORM-SPEC.md before switching the server side over.
	 */
	private function register_integration_section(): void {
		$this->start_controls_section(
			'section_integration',
			array(
				'label' => esc_html__( 'Actions After Submit', 'piecyfer-core' ),
			)
		);

		$actions_options = array();

		foreach ( $this->submit_actions() as $name => $action ) {
			$actions_options[ $name ] = $action['label'];
		}

		$default_submit_actions = array( 'email' );

		/** This filter is documented in elementor-pro/modules/forms/widgets/form.php */
		$default_submit_actions = apply_filters( 'elementor_pro/forms/default_submit_actions', $default_submit_actions );

		$this->add_control(
			'submit_actions',
			array(
				'label'       => esc_html__( 'Add Action', 'piecyfer-core' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $actions_options,
				'render_type' => 'none',
				'label_block' => true,
				'default'     => $default_submit_actions,
				'description' => esc_html__( 'Add actions that will be performed after a visitor submits the form (e.g. send an email notification). Choosing an action will add its setting below.', 'piecyfer-core' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Sections registered by Pro's action classes.
	 *
	 * DEVIATION 2 (see the file header): registered from a table so a
	 * section-id and control-id diff against form.php stays clean — in Pro these
	 * come from `modules/forms/actions/*.php`, invoked by form.php:858-860,
	 * i.e. immediately after `section_integration` and before
	 * `section_steps_settings`. That position is reproduced exactly.
	 *
	 * Order matches Form_Actions_Registrar::FEATURE_NAME_CLASS_NAME_MAP.
	 *
	 * KNOWN GAP: the Slack and Discord sections are not reproduced. No instance
	 * on this site has ever saved a Slack or Discord setting, so there is
	 * nothing to lose today, but a future editor cannot configure them either.
	 *
	 * @return array<string,array{label:string,section:string,controls:array<string,array<string,mixed>>}>
	 */
	private function submit_actions(): array {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );
		$site_domain = $this->get_site_domain();

		/* translators: %s: Site title. */
		$default_subject = sprintf( esc_html__( 'New message from "%s"', 'piecyfer-core' ), get_option( 'blogname' ) );

		$email_controls = static function ( string $suffix ) use ( $admin_email, $site_name, $site_domain, $default_subject ): array {
			return array(
				'email_to' . $suffix       => array(
					'label'       => esc_html__( 'To', 'piecyfer-core' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => $admin_email,
					'ai'          => array( 'active' => false ),
					'placeholder' => $admin_email,
					'label_block' => true,
					'title'       => esc_html__( 'Separate emails with commas', 'piecyfer-core' ),
					'render_type' => 'none',
					'dynamic'     => array( 'active' => true ),
				),
				'email_subject' . $suffix  => array(
					'label'       => esc_html__( 'Subject', 'piecyfer-core' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => $default_subject,
					'ai'          => array( 'active' => false ),
					'placeholder' => $default_subject,
					'label_block' => true,
					'render_type' => 'none',
					'dynamic'     => array( 'active' => true ),
				),
				'email_content' . $suffix  => array(
					'label'       => esc_html__( 'Message', 'piecyfer-core' ),
					'type'        => Controls_Manager::TEXTAREA,
					'default'     => '[all-fields]',
					'ai'          => array( 'active' => false ),
					'placeholder' => '[all-fields]',
					'description' => sprintf(
						/* translators: %s: The [all-fields] shortcode. */
						esc_html__( 'By default, all form fields are sent via %s shortcode. To customize sent fields, copy the shortcode that appears inside each field and paste it above.', 'piecyfer-core' ),
						'<code>[all-fields]</code>'
					),
					'render_type' => 'none',
					'dynamic'     => array( 'active' => true ),
				),
				'email_from' . $suffix     => array(
					'label'       => esc_html__( 'From Email', 'piecyfer-core' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => 'email@' . $site_domain,
					'ai'          => array( 'active' => false ),
					'render_type' => 'none',
					'dynamic'     => array( 'active' => true ),
				),
				'email_from_name' . $suffix => array(
					'label'       => esc_html__( 'From Name', 'piecyfer-core' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => $site_name,
					'ai'          => array( 'active' => false ),
					'render_type' => 'none',
					'dynamic'     => array( 'active' => true ),
				),
				/*
				 * Email 1 picks the reply-to from a dropdown of the form's own
				 * fields (options are filled by Pro's editor JS); Email 2
				 * overrides it to a free-text field defaulting to the admin
				 * address (actions/email2.php:33-45). Both shapes preserved.
				 */
				'email_reply_to' . $suffix => '' === $suffix
					? array(
						'label'       => esc_html__( 'Reply-To', 'piecyfer-core' ),
						'type'        => Controls_Manager::SELECT,
						'options'     => array( '' => '' ),
						'render_type' => 'none',
					)
					: array(
						'label'       => esc_html__( 'Reply-To', 'piecyfer-core' ),
						'type'        => Controls_Manager::TEXT,
						'default'     => $admin_email,
						'placeholder' => $admin_email,
						'ai'          => array( 'active' => false ),
						'render_type' => 'none',
					),
				'email_to_cc' . $suffix    => array(
					'label'       => esc_html__( 'Cc', 'piecyfer-core' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => '',
					'ai'          => array( 'active' => false ),
					'title'       => esc_html__( 'Separate emails with commas', 'piecyfer-core' ),
					'render_type' => 'none',
					'dynamic'     => array( 'active' => true ),
				),
				'email_to_bcc' . $suffix   => array(
					'label'       => esc_html__( 'Bcc', 'piecyfer-core' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => '',
					'ai'          => array( 'active' => false ),
					'title'       => esc_html__( 'Separate emails with commas', 'piecyfer-core' ),
					'render_type' => 'none',
					'dynamic'     => array( 'active' => true ),
				),
				'form_metadata' . $suffix  => array(
					'label'       => esc_html__( 'Meta Data', 'piecyfer-core' ),
					'type'        => Controls_Manager::SELECT2,
					'multiple'    => true,
					'label_block' => true,
					'separator'   => 'before',
					// Email 2 overrides the default to empty (email2.php:47-52).
					'default'     => '' === $suffix
						? array( 'date', 'time', 'page_url', 'user_agent', 'remote_ip', 'credit' )
						: array(),
					'options'     => array(
						'date'       => esc_html__( 'Date', 'piecyfer-core' ),
						'time'       => esc_html__( 'Time', 'piecyfer-core' ),
						'page_url'   => esc_html__( 'Page URL', 'piecyfer-core' ),
						'user_agent' => esc_html__( 'User Agent', 'piecyfer-core' ),
						'remote_ip'  => esc_html__( 'Remote IP', 'piecyfer-core' ),
						'credit'     => esc_html__( 'Credit', 'piecyfer-core' ),
					),
					'render_type' => 'none',
				),
				'email_content_type' . $suffix => array(
					'label'       => esc_html__( 'Send As', 'piecyfer-core' ),
					'type'        => Controls_Manager::SELECT,
					'default'     => 'html',
					'render_type' => 'none',
					'options'     => array(
						'html'  => esc_html__( 'HTML', 'piecyfer-core' ),
						'plain' => esc_html__( 'Plain', 'piecyfer-core' ),
					),
				),
			);
		};

		/*
		 * The six `*_fields_map` keys below are saved (as empty arrays) on all
		 * nine forms because Pro's integration panels initialise them. They are
		 * declared as HIDDEN so the editor does not silently drop them on the
		 * next save. Pro's own control type (`forms-fields-map`) is not
		 * reproduced — none of these integrations is configured, and the value
		 * shape round-trips unchanged either way.
		 */
		$fields_map = static function ( string $name ): array {
			return array(
				$name . '_fields_map' => array(
					'label' => esc_html__( 'Field Mapping', 'piecyfer-core' ),
					'type'  => Controls_Manager::HIDDEN,
				),
			);
		};

		return array(
			'email'  => array(
				'label'    => esc_html__( 'Email', 'piecyfer-core' ),
				'section'  => 'section_email',
				'controls' => $email_controls( '' ),
			),
			'email2' => array(
				'label'    => esc_html__( 'Email 2', 'piecyfer-core' ),
				// Pro builds this id as get_control_id( 'section_email' ), and
				// Email2's get_control_id appends '_2' — so it is
				// `section_email_2`, not `section_email2`.
				'section'  => 'section_email_2',
				'controls' => $email_controls( '_2' ),
			),
			'redirect' => array(
				'label'    => esc_html__( 'Redirect', 'piecyfer-core' ),
				'section'  => 'section_redirect',
				'controls' => array(
					'redirect_to' => array(
						'label'       => esc_html__( 'Redirect To', 'piecyfer-core' ),
						'type'        => Controls_Manager::TEXT,
						'placeholder' => esc_html__( 'https://your-link.com', 'piecyfer-core' ),
						'ai'          => array( 'active' => false ),
						'dynamic'     => array( 'active' => true ),
						'label_block' => true,
						'render_type' => 'none',
						'classes'     => 'elementor-control-direction-ltr',
					),
				),
			),
			'webhook' => array(
				'label'    => esc_html__( 'Webhook', 'piecyfer-core' ),
				'section'  => 'section_webhook',
				'controls' => array(
					'webhooks' => array(
						'label'       => esc_html__( 'Webhook URL', 'piecyfer-core' ),
						'type'        => Controls_Manager::TEXT,
						'placeholder' => esc_html__( 'https://your-webhook-url.com', 'piecyfer-core' ),
						'ai'          => array( 'active' => false ),
						'label_block' => true,
						'separator'   => 'before',
						'description' => esc_html__( "Enter the integration URL (like Zapier) that will receive the form's submitted data.", 'piecyfer-core' ),
						'render_type' => 'none',
						'dynamic'     => array( 'active' => true ),
					),
					'webhooks_advanced_data' => array(
						'label'       => esc_html__( 'Advanced Data', 'piecyfer-core' ),
						'type'        => Controls_Manager::SWITCHER,
						'default'     => 'no',
						'render_type' => 'none',
					),
				),
			),
			'mailchimp' => array(
				'label'    => esc_html__( 'Mailchimp', 'piecyfer-core' ),
				'section'  => 'section_mailchimp',
				'controls' => $fields_map( 'mailchimp' ),
			),
			'drip' => array(
				'label'    => esc_html__( 'Drip', 'piecyfer-core' ),
				'section'  => 'section_drip',
				'controls' => $fields_map( 'drip' ),
			),
			'activecampaign' => array(
				'label'    => esc_html__( 'ActiveCampaign', 'piecyfer-core' ),
				'section'  => 'section_activecampaign',
				'controls' => $fields_map( 'activecampaign' ),
			),
			'getresponse' => array(
				'label'    => esc_html__( 'GetResponse', 'piecyfer-core' ),
				'section'  => 'section_getresponse',
				'controls' => $fields_map( 'getresponse' ),
			),
			'convertkit' => array(
				'label'    => esc_html__( 'ConvertKit', 'piecyfer-core' ),
				'section'  => 'section_convertkit',
				'controls' => $fields_map( 'convertkit' ),
			),
			'mailerlite' => array(
				'label'    => esc_html__( 'MailerLite', 'piecyfer-core' ),
				'section'  => 'section_mailerlite',
				'controls' => $fields_map( 'mailerlite' ),
			),
		);
	}

	private function register_action_sections(): void {
		foreach ( $this->submit_actions() as $name => $action ) {
			$this->start_controls_section(
				$action['section'],
				array(
					'label'     => $action['label'],
					'tab'       => Controls_Manager::TAB_CONTENT,
					// Pro puts this condition on every action section. It is
					// reproduced, not invented — see the header note about not
					// adding conditions Pro does not have.
					'condition' => array(
						'submit_actions' => $name,
					),
				)
			);

			foreach ( $action['controls'] as $control_id => $control_args ) {
				$this->add_control( $control_id, $control_args );
			}

			$this->end_controls_section();
		}
	}

	private function register_steps_settings_section(): void {
		$this->start_controls_section(
			'section_steps_settings',
			array(
				'label' => esc_html__( 'Steps Settings', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'step_type',
			array(
				'label'              => esc_html__( 'Type', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'frontend_available' => true,
				'render_type'        => 'none',
				'options'            => array(
					'none'         => 'None',
					'text'         => 'Text',
					'icon'         => 'Icon',
					'number'       => 'Number',
					'progress_bar' => 'Progress Bar',
					'number_text'  => 'Number & Text',
					'icon_text'    => 'Icon & Text',
				),
				'default'            => 'number_text',
			)
		);

		$this->add_control(
			'step_icon_shape',
			array(
				'label'              => esc_html__( 'Shape', 'piecyfer-core' ),
				'type'               => Controls_Manager::SELECT,
				'frontend_available' => true,
				'render_type'        => 'none',
				'options'            => array(
					'circle'  => 'Circle',
					'square'  => 'Square',
					'rounded' => 'Rounded',
					'none'    => 'None',
				),
				'default'            => 'circle',
				'conditions'         => array(
					'terms' => array(
						array(
							'name'     => 'step_type',
							'operator' => '!in',
							'value'    => array(
								'progress_bar',
								'text',
							),
						),
					),
				),
			)
		);

		/*
		 * Reproduced verbatim from form.php:920-933, bug and all: Pro adds this
		 * to the *repeater* here, long after `$repeater->get_controls()` was
		 * already handed to `form_fields` (form.php:514). The control therefore
		 * never reaches the form_fields stack in Pro either, and its `condition`
		 * references `step_type`, a widget-level control, not a repeater one.
		 * It is kept so the control-id set matches one-for-one; if it is ever
		 * fixed upstream, fix it here in the same commit.
		 */
		if ( $this->fields_repeater ) {
			$this->fields_repeater->add_control(
				'display_percentage',
				array(
					'label'              => esc_html__( 'Display Percentage', 'piecyfer-core' ),
					'type'               => Controls_Manager::SWITCHER,
					'frontend_available' => true,
					'render_type'        => 'none',
					'return_value'       => 'true',
					'default'            => '',
					'condition'          => array(
						'step_type' => 'progress_bar',
					),
				)
			);
		}

		$this->end_controls_section();
	}

	private function register_form_options_section(): void {
		$this->start_controls_section(
			'section_form_options',
			array(
				'label' => esc_html__( 'Additional Options', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'form_id',
			array(
				'label'       => esc_html__( 'Form ID', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'ai'          => array( 'active' => false ),
				'placeholder' => 'new_form_id',
				'description' => sprintf(
					/* translators: 1: `<code>` opening tag, 2: `</code>` closing tag. */
					esc_html__( 'Please make sure the ID is unique and not used elsewhere on the page. This field allows %1$sA-z 0-9%2$s & underscore chars without spaces.', 'piecyfer-core' ),
					'<code>',
					'</code>'
				),
				'separator'   => 'after',
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'form_validation',
			array(
				'label'   => esc_html__( 'Form Validation', 'piecyfer-core' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''       => esc_html__( 'Browser Default', 'piecyfer-core' ),
					'custom' => esc_html__( 'Custom', 'piecyfer-core' ),
				),
				'default' => '',
			)
		);

		$this->add_control(
			'custom_messages',
			array(
				'label'       => esc_html__( 'Custom Messages', 'piecyfer-core' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'separator'   => 'before',
				'render_type' => 'none',
			)
		);

		$default_messages = self::get_default_messages();

		$this->add_control(
			'success_message',
			array(
				'label'       => esc_html__( 'Success Message', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $default_messages[ self::MESSAGE_SUCCESS ],
				'placeholder' => $default_messages[ self::MESSAGE_SUCCESS ],
				'label_block' => true,
				'condition'   => array(
					'custom_messages!' => '',
				),
				'render_type' => 'none',
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'error_message',
			array(
				'label'       => esc_html__( 'Form Error', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $default_messages[ self::MESSAGE_ERROR ],
				'placeholder' => $default_messages[ self::MESSAGE_ERROR ],
				'label_block' => true,
				'condition'   => array(
					'custom_messages!' => '',
				),
				'render_type' => 'none',
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'server_message',
			array(
				'label'       => esc_html__( 'Server Error', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $default_messages[ self::MESSAGE_SERVER_ERROR ],
				'placeholder' => $default_messages[ self::MESSAGE_SERVER_ERROR ],
				'label_block' => true,
				'condition'   => array(
					'custom_messages!' => '',
				),
				'render_type' => 'none',
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'invalid_message',
			array(
				'label'       => esc_html__( 'Invalid Form', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $default_messages[ self::MESSAGE_INVALID_FORM ],
				'placeholder' => $default_messages[ self::MESSAGE_INVALID_FORM ],
				'label_block' => true,
				'condition'   => array(
					'custom_messages!' => '',
				),
				'render_type' => 'none',
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'required_field_message',
			array(
				'label'       => esc_html__( 'Required Field', 'piecyfer-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $default_messages[ self::MESSAGE_REQUIRED ],
				'placeholder' => $default_messages[ self::MESSAGE_REQUIRED ],
				'label_block' => true,
				'condition'   => array(
					'custom_messages!' => '',
					'form_validation'  => 'custom',
				),
				'render_type' => 'none',
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->end_controls_section();
	}

	private function register_form_style_section(): void {
		$this->start_controls_section(
			'section_form_style',
			array(
				'label' => esc_html__( 'Form', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'column_gap',
			array(
				'label'      => esc_html__( 'Columns Gap', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 10 ),
				'range'      => array(
					'px'  => array( 'max' => 60 ),
					'em'  => array( 'max' => 6 ),
					'rem' => array( 'max' => 6 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-field-group' => 'padding-right: calc( {{SIZE}}{{UNIT}}/2 ); padding-left: calc( {{SIZE}}{{UNIT}}/2 );',
					'{{WRAPPER}} .elementor-form-fields-wrapper' => 'margin-left: calc( -{{SIZE}}{{UNIT}}/2 ); margin-right: calc( -{{SIZE}}{{UNIT}}/2 );',
				),
			)
		);

		$this->add_control(
			'row_gap',
			array(
				'label'      => esc_html__( 'Rows Gap', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 10 ),
				'range'      => array(
					'px'  => array( 'max' => 60 ),
					'em'  => array( 'max' => 6 ),
					'rem' => array( 'max' => 6 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-field-group' => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-field-group.recaptcha_v3-bottomleft, {{WRAPPER}} .elementor-field-group.recaptcha_v3-bottomright' => 'margin-bottom: 0;',
					'{{WRAPPER}} .elementor-form-fields-wrapper' => 'margin-bottom: -{{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'heading_label',
			array(
				'label'     => esc_html__( 'Label', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'label_spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 0 ),
				'range'      => array(
					'px'  => array( 'max' => 60 ),
					'em'  => array( 'max' => 6 ),
					'rem' => array( 'max' => 6 ),
				),
				'selectors'  => array(
					// for the label position = inline option
					'body.rtl {{WRAPPER}} .elementor-labels-inline .elementor-field-group > label' => 'padding-left: {{SIZE}}{{UNIT}};',
					// for the label position = inline option
					'body:not(.rtl) {{WRAPPER}} .elementor-labels-inline .elementor-field-group > label' => 'padding-right: {{SIZE}}{{UNIT}};',
					// for the label position = above option
					'body {{WRAPPER}} .elementor-labels-above .elementor-field-group > label' => 'padding-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-field-group > label, {{WRAPPER}} .elementor-field-subgroup label' => 'color: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		/*
		 * Pro's condition is `mark_required => 'yes'` while the `mark_required`
		 * switcher has no `return_value`, so it stores 'yes' — the condition is
		 * correct. Copied unchanged.
		 */
		$this->add_control(
			'mark_required_color',
			array(
				'label'     => esc_html__( 'Mark Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-mark-required .elementor-field-label:after' => 'color: {{COLOR}};',
				),
				'condition' => array(
					'mark_required' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'selector' => '{{WRAPPER}} .elementor-field-group > label',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->add_control(
			'heading_html',
			array(
				'label'     => esc_html__( 'HTML Field', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'html_spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 0 ),
				'range'      => array(
					'px'  => array( 'max' => 60 ),
					'em'  => array( 'max' => 6 ),
					'rem' => array( 'max' => 6 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-field-type-html' => 'padding-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'html_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-field-type-html' => 'color: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'html_typography',
				'selector' => '{{WRAPPER}} .elementor-field-type-html',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_field_style_section(): void {
		$this->start_controls_section(
			'section_field_style',
			array(
				'label' => esc_html__( 'Field', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'field_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-field-group .elementor-field' => 'color: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'field_typography',
				'selector' => '{{WRAPPER}} .elementor-field-group .elementor-field, {{WRAPPER}} .elementor-field-subgroup label',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->add_control(
			'field_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper)' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'background-color: {{VALUE}};',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'field_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper)' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper::before' => 'color: {{VALUE}};',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'field_border_width',
			array(
				'label'       => esc_html__( 'Border Width', 'piecyfer-core' ),
				'type'        => Controls_Manager::DIMENSIONS,
				'placeholder' => '1',
				'size_units'  => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'   => array(
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper)' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'field_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper)' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_button_style_section(): void {
		$start = is_rtl() ? 'right' : 'left';
		$end   = is_rtl() ? 'left' : 'right';

		$this->start_controls_section(
			'section_button_style',
			array(
				'label' => esc_html__( 'Buttons', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'button_align',
			array(
				'label'        => esc_html__( 'Position', 'piecyfer-core' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'start'   => array(
						'title' => esc_html__( 'Left', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-center',
					),
					'end'     => array(
						'title' => esc_html__( 'Right', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-right',
					),
					'stretch' => array(
						'title' => esc_html__( 'Stretch', 'piecyfer-core' ),
						'icon'  => 'eicon-h-align-stretch',
					),
				),
				'default'      => 'stretch',
				// The `%s` is Elementor's responsive-suffix slot: it renders as
				// `elementor-button-align-*` on desktop and
				// `elementor-tablet-button-align-*` / `elementor-mobile-...` on
				// the smaller breakpoints. Dropping it silently breaks the
				// responsive variants only.
				'prefix_class' => 'elementor%s-button-align-',
			)
		);

		$this->add_responsive_control(
			'button_content_align',
			array(
				'label'     => esc_html__( 'Alignment', 'piecyfer-core' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'start'         => array(
						'title' => esc_html__( 'Start', 'piecyfer-core' ),
						'icon'  => "eicon-text-align-{$start}",
					),
					'center'        => array(
						'title' => esc_html__( 'Center', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'           => array(
						'title' => esc_html__( 'End', 'piecyfer-core' ),
						'icon'  => "eicon-text-align-{$end}",
					),
					'space-between' => array(
						'title' => esc_html__( 'Space between', 'piecyfer-core' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-button span' => 'justify-content: {{VALUE}};',
				),
				'condition' => array( 'button_align' => 'stretch' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
				'selector' => '{{WRAPPER}} .elementor-button',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .elementor-button',
				'exclude'  => array(
					'color',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		$this->start_controls_tab(
			'tab_button_normal',
			array(
				'label' => esc_html__( 'Normal', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'heading_next_submit_button',
			array(
				'label' => esc_html__( 'Next & Submit Button', 'piecyfer-core' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"] svg *' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]' => 'border-color: {{VALUE}};',
				),
				'condition' => array(
					'button_border_border!' => '',
				),
			)
		);

		$this->add_control(
			'heading_previous_button',
			array(
				'label' => esc_html__( 'Previous Button', 'piecyfer-core' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'previous_button_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'previous_button_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'previous_button_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous' => 'border-color: {{VALUE}};',
				),
				'condition' => array(
					'button_border_border!' => '',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			array(
				'label' => esc_html__( 'Hover', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'heading_next_submit_button_hover',
			array(
				'label' => esc_html__( 'Next & Submit Button', 'piecyfer-core' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'button_background_hover_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next:hover' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]:hover svg *' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next:hover' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]:hover' => 'border-color: {{VALUE}};',
				),
				'condition' => array(
					'button_border_border!' => '',
				),
			)
		);

		$this->add_control(
			'heading_previous_button_hover',
			array(
				'label' => esc_html__( 'Previous Button', 'piecyfer-core' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'previous_button_background_color_hover',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'previous_button_text_color_hover',
			array(
				'label'     => esc_html__( 'Text Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'previous_button_border_color_hover',
			array(
				'label'     => esc_html__( 'Border Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous:hover' => 'border-color: {{VALUE}};',
				),
				'condition' => array(
					'button_border_border!' => '',
				),
			)
		);

		$this->add_control(
			'hover_transition_duration',
			array(
				'label'      => esc_html__( 'Transition Duration', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array( 'unit' => 'ms' ),
				'selectors'  => array(
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous' => 'transition-duration: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next' => 'transition-duration: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-button[type="submit"] svg *' => 'transition-duration: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-button[type="submit"]' => 'transition-duration: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'button_hover_animation',
			array(
				'label' => esc_html__( 'Animation', 'piecyfer-core' ),
				'type'  => Controls_Manager::HOVER_ANIMATION,
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_control(
			'button_text_padding',
			array(
				'label'      => esc_html__( 'Text Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .elementor-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_messages_style_section(): void {
		$this->start_controls_section(
			'section_messages_style',
			array(
				'label' => esc_html__( 'Messages', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'message_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector' => '{{WRAPPER}} .elementor-message',
			)
		);

		$this->add_control(
			'success_message_color',
			array(
				'label'     => esc_html__( 'Success Message Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-message.elementor-message-success' => 'color: {{COLOR}};',
				),
			)
		);

		$this->add_control(
			'error_message_color',
			array(
				'label'     => esc_html__( 'Error Message Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-message.elementor-message-danger' => 'color: {{COLOR}};',
				),
			)
		);

		$this->add_control(
			'inline_message_color',
			array(
				'label'     => esc_html__( 'Inline Message Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-message.elementor-help-inline' => 'color: {{COLOR}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_steps_style_section(): void {
		$this->start_controls_section(
			'section_steps_style',
			array(
				'label' => esc_html__( 'Steps', 'piecyfer-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'       => 'steps_typography',
				'global'     => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
				'selector'   => '{{WRAPPER}} .e-form__indicators__indicator, {{WRAPPER}} .e-form__indicators__indicator__label',
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'step_type',
							'operator' => '!in',
							'value'    => array(
								'icon',
								'progress_bar',
							),
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'steps_gap',
			array(
				'label'      => esc_html__( 'Spacing', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 20 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-form-steps-indicators-spacing: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'steps_icon_size',
			array(
				'label'      => esc_html__( 'Icon Size', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 15 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'step_type',
							'operator' => 'in',
							'value'    => array(
								'icon',
								'icon_text',
							),
						),
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-icon-size: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'steps_padding',
			array(
				'label'      => esc_html__( 'Padding', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array( 'size' => 30 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-padding: {{SIZE}}{{UNIT}}',
				),
				'conditions' => array(
					'terms' => array(
						array(
							'name'     => 'step_type',
							'operator' => '!in',
							'value'    => array(
								'text',
								'progress_bar',
							),
						),
					),
				),
			)
		);

		$this->start_controls_tabs(
			'steps_state',
			array(
				'condition' => array(
					'step_type!' => 'progress_bar',
				),
			)
		);

		$this->start_controls_tab(
			'tab_steps_state_inactive',
			array(
				'label' => esc_html__( 'Inactive', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'step_inactive_primary_color',
			array(
				'label'     => esc_html__( 'Primary Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-inactive-primary-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'step_inactive_secondary_color',
			array(
				'label'     => esc_html__( 'Secondary Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-inactive-secondary-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_steps_state_active',
			array(
				'label' => esc_html__( 'Active', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'step_active_primary_color',
			array(
				'label'     => esc_html__( 'Primary Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-active-primary-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'step_active_secondary_color',
			array(
				'label'     => esc_html__( 'Secondary Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-active-secondary-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_steps_state_completed',
			array(
				'label' => esc_html__( 'Completed', 'piecyfer-core' ),
			)
		);

		$this->add_control(
			'step_completed_primary_color',
			array(
				'label'     => esc_html__( 'Primary Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-completed-primary-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'step_completed_secondary_color',
			array(
				'label'     => esc_html__( 'Secondary Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'condition' => array(
					'step_icon_shape!' => 'none',
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-completed-secondary-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'step_divider_width',
			array(
				'label'      => esc_html__( 'Divider Width', 'piecyfer-core' ),
				'separator'  => 'before',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array( 'size' => 1 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'condition'  => array(
					'step_type!' => 'progress_bar',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-form-steps-divider-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'step_divider_gap',
			array(
				'label'      => esc_html__( 'Divider Gap', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 10 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'condition'  => array(
					'step_type!' => 'progress_bar',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-form-steps-divider-gap: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'step_progress_bar_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'condition' => array(
					'step_type' => 'progress_bar',
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'step_progress_bar_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'condition' => array(
					'step_type' => 'progress_bar',
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'step_progress_bar_height',
			array(
				'label'      => esc_html__( 'Height', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'default'    => array( 'size' => 20 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'condition'  => array(
					'step_type' => 'progress_bar',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-height: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'step_progress_bar_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'piecyfer-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 0 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'condition'  => array(
					'step_type' => 'progress_bar',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'step_progress_bar_percentage_heading',
			array(
				'label'     => esc_html__( 'Percentage', 'piecyfer-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'step_type' => 'progress_bar',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'step_progress_bar_percentage__typography',
				'global'    => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
				'selector'  => '{{WRAPPER}} .e-form__indicators__indicator__progress__meter',
				'condition' => array(
					'step_type' => 'progress_bar',
				),
			)
		);

		$this->add_control(
			'step_progress_bar_percentage_color',
			array(
				'label'     => esc_html__( 'Color', 'piecyfer-core' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'condition' => array(
					'step_type' => 'progress_bar',
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-meter-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	// ---------------------------------------------------------------- render

	/**
	 * Reproduces ElementorPro\Modules\Forms\Widgets\Form::render()
	 * (form.php:2257-2488) including the literal whitespace between `?>` and
	 * `<?php` — that whitespace is output, and the HTML diff in the pixel tool
	 * compares it byte for byte.
	 */
	protected function render_widget(): void {
		$instance = $this->get_settings_for_display();

		if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			/** This action is documented in elementor-pro/modules/forms/widgets/form.php */
			do_action( 'elementor-pro/forms/pre_render', $instance, $this );
		}

		$this->add_render_attribute(
			array(
				'wrapper' => array(
					'class' => array(
						'elementor-form-fields-wrapper',
						'elementor-labels-' . $instance['label_position'],
					),
				),
				'submit-group' => array(
					'class' => array(
						'elementor-field-group',
						'elementor-column',
						'elementor-field-type-submit',
					),
				),
				'button' => array(
					'class' => 'elementor-button',
					'type' => 'submit',
				),
				'button-content-wrapper' => array(
					'class' => 'elementor-button-content-wrapper',
				),
				'button-icon' => array(
					'class' => 'elementor-button-icon',
				),
				'button-text' => array(
					'class' => 'elementor-button-text',
				),
			)
		);

		if ( empty( $instance['button_width'] ) ) {
			$instance['button_width'] = '100';
		}

		$this->add_render_attribute( 'submit-group', 'class', 'elementor-col-' . $instance['button_width'] . ' e-form__buttons' );

		if ( ! empty( $instance['button_width_tablet'] ) ) {
			$this->add_render_attribute( 'submit-group', 'class', 'elementor-md-' . $instance['button_width_tablet'] );
		}

		if ( ! empty( $instance['button_width_mobile'] ) ) {
			$this->add_render_attribute( 'submit-group', 'class', 'elementor-sm-' . $instance['button_width_mobile'] );
		}

		if ( ! empty( $instance['button_size'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-size-' . $instance['button_size'] );
		}

		// `button_type` has no control; it survives from a pre-3.x form widget
		// and is read defensively exactly as Pro reads it.
		if ( ! empty( $instance['button_type'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-button-' . $instance['button_type'] );
		}

		if ( $instance['button_hover_animation'] ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-animation-' . $instance['button_hover_animation'] );
		}

		if ( ! empty( $instance['form_id'] ) ) {
			$this->add_render_attribute( 'form', 'id', $instance['form_id'] );
		}

		if ( ! empty( $instance['form_name'] ) ) {
			$this->add_render_attribute( 'form', 'name', $instance['form_name'] );
		}

		if ( 'custom' === $instance['form_validation'] ) {
			$this->add_render_attribute( 'form', 'novalidate' );
		}

		if ( ! empty( $instance['button_css_id'] ) ) {
			$this->add_render_attribute( 'button', 'id', $instance['button_css_id'] );
		}

		/*
		 * `referer_title` is computed per request from wp_title(). It is one of
		 * the two request-dependent hidden inputs (the other is `queried_id`)
		 * that make this widget unsafe to serve from a page cache without care —
		 * see the caching section of 06-FORM-SPEC.md.
		 */
		$referer_title = trim( wp_title( '', false ) );

		if ( ! $referer_title && is_home() ) {
			$referer_title = get_option( 'blogname' );
		}

		?>
		<form class="elementor-form" method="post" <?php $this->print_render_attribute_string( 'form' ); ?>>
			<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $this->get_current_post_id() ); ?>"/>
			<input type="hidden" name="form_id" value="<?php echo esc_attr( $this->get_id() ); ?>"/>
			<input type="hidden" name="referer_title" value="<?php echo esc_attr( $referer_title ); ?>" />

			<?php if ( is_singular() ) {
				// `queried_id` may be different from `post_id` on Single theme builder templates.
				?>
				<input type="hidden" name="queried_id" value="<?php echo esc_attr( (string) get_the_ID() ); ?>"/>
			<?php } ?>

			<div <?php $this->print_render_attribute_string( 'wrapper' ); ?>>
				<?php
				foreach ( $instance['form_fields'] as $item_index => $item ) :
					$item['input_size'] = $instance['input_size'];
					$this->form_fields_render_attributes( $item_index, $instance, $item );

					$field_type = $item['field_type'];

					/** This filter is documented in elementor-pro/modules/forms/widgets/form.php */
					$item = apply_filters( 'elementor_pro/forms/render/item', $item, $item_index, $this );

					/** This filter is documented in elementor-pro/modules/forms/widgets/form.php */
					$item = apply_filters( "elementor_pro/forms/render/item/{$field_type}", $item, $item_index, $this );

					$print_label = ! in_array( $item['field_type'], array( 'hidden', 'html', 'step', 'recaptcha', 'recaptcha_v3' ), true );
					?>
				<div <?php $this->print_render_attribute_string( 'field-group' . $item_index ); ?>>
					<?php
					if ( $print_label && $item['field_label'] ) {
						?>
							<label <?php $this->print_render_attribute_string( 'label' . $item_index ); ?>>
								<?php
								echo $item['field_label']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</label>
						<?php
					}

					switch ( $item['field_type'] ) :
						case 'html':
							echo do_shortcode( $item['field_html'] );
							break;
						case 'textarea':
							echo $this->make_textarea_field( $item, $item_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							break;

						case 'select':
							echo $this->make_select_field( $item, $item_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							break;

						case 'radio':
						case 'checkbox':
							echo $this->make_radio_checkbox_field( $item, $item_index, $item['field_type'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							break;
						case 'text':
						case 'email':
						case 'url':
						case 'password':
						case 'hidden':
						case 'search':
							$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-field-textual' );
							?>
								<input size="1" <?php $this->print_render_attribute_string( 'input' . $item_index ); ?>>
							<?php
							break;
						default:
							$this->render_field( $item, $item_index );
					endswitch;
					?>
				</div>
				<?php endforeach; ?>
				<div <?php $this->print_render_attribute_string( 'submit-group' ); ?>>
					<button <?php $this->print_render_attribute_string( 'button' ); ?>>
						<span <?php $this->print_render_attribute_string( 'button-content-wrapper' ); ?>>
							<?php if ( ! empty( $instance['button_icon'] ) || ! empty( $instance['selected_button_icon']['value'] ) ) : ?>
								<span <?php $this->print_render_attribute_string( 'button-icon' ); ?>>
									<?php $this->render_icon_with_fallback( $instance ); ?>
									<?php if ( empty( $instance['button_text'] ) ) : ?>
										<span class="elementor-screen-only"><?php echo esc_html__( 'Submit', 'piecyfer-core' ); ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
							<?php if ( ! empty( $instance['button_text'] ) ) : ?>
								<span <?php $this->print_render_attribute_string( 'button-text' ); ?>><?php $this->print_unescaped_setting( 'button_text' ); ?></span>
							<?php endif; ?>
						</span>
					</button>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Mirrors ElementorPro\Core\Utils::get_current_post_id() (core/utils.php:79-85).
	 * On a theme-builder template this is the *template's* id, which is what the
	 * ajax handler needs to find the form's saved settings — not the queried
	 * post, which travels separately in `queried_id`.
	 */
	private function get_current_post_id(): int {
		$documents = \Elementor\Plugin::$instance->documents ?? null;

		if ( $documents ) {
			$current = $documents->get_current();
			if ( $current ) {
				return (int) $current->get_main_id();
			}
		}

		return (int) get_the_ID();
	}

	/**
	 * DEVIATION 3 (see the file header).
	 *
	 * Pro renders every field type that is not in form.php's own switch by
	 * firing `elementor_pro/forms/render_field/{type}`; its field classes and
	 * the two reCAPTCHA handlers listen there, and so do third-party field
	 * plugins. That dispatch is preserved verbatim, so nothing that works today
	 * changes. Only when *nothing* is listening — i.e. once Pro's forms module
	 * is gone — do our own renderers take over.
	 *
	 * Consequence worth stating plainly: while Pro is installed, the tel,
	 * upload, acceptance and recaptcha_v3 fields on this site are still drawn by
	 * Pro. The built-ins below are byte-compared against Pro's field classes but
	 * are not exercised by the pixel tool until Pro's forms module is disabled.
	 * Force them early with:
	 *   add_filter( 'piecyfer/forms/use_builtin_fields', '__return_true' );
	 */
	private function render_field( array $item, int $item_index ): void {
		$field_type = $item['field_type'];

		$use_builtin = ! has_action( "elementor_pro/forms/render_field/{$field_type}" );

		/**
		 * Force PieCyfer's own field rendering even while Pro is still hooked.
		 *
		 * @param bool   $use_builtin Whether to use our renderer.
		 * @param string $field_type  The field type being rendered.
		 */
		$use_builtin = (bool) apply_filters( 'piecyfer/forms/use_builtin_fields', $use_builtin, $field_type );

		if ( ! $use_builtin ) {
			/** This action is documented in elementor-pro/modules/forms/widgets/form.php */
			do_action( "elementor_pro/forms/render_field/{$field_type}", $item, $item_index, $this );

			return;
		}

		switch ( $field_type ) {
			case 'tel':
				$this->render_tel_field( $item, $item_index );
				break;
			case 'number':
				$this->render_number_field( $item, $item_index );
				break;
			case 'date':
				$this->render_date_field( $item, $item_index );
				break;
			case 'time':
				$this->render_time_field( $item, $item_index );
				break;
			case 'upload':
				$this->render_upload_field( $item, $item_index );
				break;
			case 'acceptance':
				$this->render_acceptance_field( $item, $item_index );
				break;
			case 'step':
				$this->render_step_field( $item, $item_index );
				break;
			case 'recaptcha':
			case 'recaptcha_v3':
				$this->render_recaptcha_field( $item, $item_index );
				break;
			default:
				// An unknown type with no handler: emit nothing rather than a
				// broken input, exactly as Pro's unhandled do_action would.
				break;
		}
	}

	/** Mirrors ElementorPro\Modules\Forms\Fields\Tel::render() (fields/tel.php:21-29). */
	private function render_tel_field( array $item, int $item_index ): void {
		$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-field-textual' );
		$this->add_render_attribute( 'input' . $item_index, 'pattern', '[0-9()#&+*-=.]+' );
		$this->add_render_attribute( 'input' . $item_index, 'title', esc_html__( 'Only numbers and phone characters (#, -, *, etc) are accepted.', 'piecyfer-core' ) );
		?>
		<input size="1" <?php $this->print_render_attribute_string( 'input' . $item_index ); ?>>

		<?php
	}

	/** Mirrors ElementorPro\Modules\Forms\Fields\Number::render() (fields/number.php:23-36). */
	private function render_number_field( array $item, int $item_index ): void {
		$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-field-textual' );

		if ( isset( $item['field_min'] ) ) {
			$this->add_render_attribute( 'input' . $item_index, 'min', esc_attr( $item['field_min'] ) );
		}
		if ( isset( $item['field_max'] ) ) {
			$this->add_render_attribute( 'input' . $item_index, 'max', esc_attr( $item['field_max'] ) );
		}

		?>
			<input <?php $this->print_render_attribute_string( 'input' . $item_index ); ?> >
		<?php
	}

	/**
	 * Mirrors ElementorPro\Modules\Forms\Fields\Date::render() (fields/date.php:28-46).
	 *
	 * Pro also enqueues the `flatpickr` script/style for this type. Flatpickr is
	 * registered by Elementor FREE, so the dependency survives Pro's removal —
	 * but nothing on this site uses a date field, so it is not enqueued here.
	 * Add `wp_enqueue_script( 'flatpickr' )` if a date field is ever introduced.
	 */
	private function render_date_field( array $item, int $item_index ): void {
		$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-field-textual elementor-date-field' );
		$this->add_render_attribute( 'input' . $item_index, 'pattern', '[0-9]{4}-[0-9]{2}-[0-9]{2}' );
		if ( isset( $item['use_native_date'] ) && 'yes' === $item['use_native_date'] ) {
			$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-use-native' );
		}

		if ( ! empty( $item['min_date'] ) ) {
			$this->add_render_attribute( 'input' . $item_index, 'min', esc_attr( $item['min_date'] ) );
		}

		if ( ! empty( $item['max_date'] ) ) {
			$this->add_render_attribute( 'input' . $item_index, 'max', esc_attr( $item['max_date'] ) );
		}
		?>

		<input <?php $this->print_render_attribute_string( 'input' . $item_index ); ?>>
		<?php
	}

	/** Mirrors ElementorPro\Modules\Forms\Fields\Time::render() (fields/time.php:71-79). */
	private function render_time_field( array $item, int $item_index ): void {
		$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-field-textual elementor-time-field' );
		if ( isset( $item['use_native_time'] ) && 'yes' === $item['use_native_time'] ) {
			$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-use-native' );
		}
		?>
		<input <?php $this->print_render_attribute_string( 'input' . $item_index ); ?>>
		<?php
	}

	/**
	 * Mirrors ElementorPro\Modules\Forms\Fields\Upload::render() (fields/upload.php:124-146).
	 *
	 * The `type` attribute is overwritten (fourth argument `true`) because
	 * form_fields_render_attributes() already set it to the field type,
	 * `upload`, which is not a valid input type.
	 */
	private function render_upload_field( array $item, int $item_index ): void {
		$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-upload-field' );
		$this->add_render_attribute( 'input' . $item_index, 'type', 'file', true );

		if ( ! empty( $item['allow_multiple_upload'] ) ) {
			$this->add_render_attribute( 'input' . $item_index, 'multiple', 'multiple' );
			$this->add_render_attribute( 'input' . $item_index, 'name', $this->get_attribute_name( $item ) . '[]', true );
		}

		if ( ! empty( $item['file_sizes'] ) ) {
			$this->add_render_attribute(
				'input' . $item_index,
				array(
					'data-maxsize' => $item['file_sizes'], // MB
					'data-maxsize-message' => esc_html__( 'This file exceeds the maximum allowed size.', 'piecyfer-core' ),
				)
			);
		}
		?>
		<input <?php $this->print_render_attribute_string( 'input' . $item_index ); ?>>

		<?php
	}

	/** Mirrors ElementorPro\Modules\Forms\Fields\Acceptance::render() (fields/acceptance.php:58-84). */
	private function render_acceptance_field( array $item, int $item_index ): void {
		$label = '';
		$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-acceptance-field' );
		$this->add_render_attribute( 'input' . $item_index, 'type', 'checkbox', true );

		if ( ! empty( $item['acceptance_text'] ) ) {
			$label = '<label for="' . $this->get_attribute_id( $item ) . '">' . $item['acceptance_text'] . '</label>';
		}

		if ( ! empty( $item['checked_by_default'] ) ) {
			$this->add_render_attribute( 'input' . $item_index, 'checked', 'checked' );
		}

		?>
		<div class="elementor-field-subgroup">
			<span class="elementor-field-option">
				<input <?php $this->print_render_attribute_string( 'input' . $item_index ); ?>>
				<?php
				echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
		</div>
		<?php
	}

	/** Mirrors ElementorPro\Modules\Forms\Fields\Step::render() (fields/step.php:19-45). */
	private function render_step_field( array $item, int $item_index ): void {
		$font_icon      = '';
		$selected_icon  = isset( $item['selected_icon'] ) && is_array( $item['selected_icon'] )
			? $item['selected_icon']
			: array(
				'value'   => '',
				'library' => '',
			);

		if ( \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' ) && $selected_icon['value'] ) {
			if ( 'svg' === $selected_icon['library'] ) {
				$font_icon = Icons_Manager::render_uploaded_svg_icon( $selected_icon['value'] );
			} else {
				$font_icon = Icons_Manager::render_font_icon( $selected_icon );
			}
		}

		$this->add_render_attribute(
			'step' . $item_index,
			array(
				'class' => 'e-field-step elementor-hidden',
				'data-label' => $item['field_label'],
				'data-previousButton' => $item['previous_button'] ?? '',
				'data-nextButton' => $item['next_button'] ?? '',
				'data-iconUrl' => 'svg' === $selected_icon['library'] && $selected_icon['value'] ? $selected_icon['value']['url'] : '',
				'data-iconLibrary' => 'svg' !== $selected_icon['library'] && $selected_icon['value'] ? $selected_icon['value'] : '',
				'data-icon' => $font_icon,
			)
		);

		?>
		<div <?php $this->print_render_attribute_string( 'step' . $item_index ); ?> ></div>

		<?php
	}

	/**
	 * Mirrors Recaptcha_Handler::render_field() (classes/recaptcha-handler.php:217-268)
	 * plus Recaptcha_V3_Handler's version-specific attributes
	 * (classes/recaptcha-v3-handler.php:102-109) and its field-group badge class
	 * (recaptcha-v3-handler.php:140-146), which Pro adds from a filter rather
	 * than from the renderer.
	 *
	 * Keys are read from Pro's own option names so nothing has to be migrated;
	 * see the reCAPTCHA section of 06-FORM-SPEC.md for why those option names
	 * are load-bearing.
	 *
	 * NOTE: this renders the widget container and enqueues Google's api.js. It
	 * does NOT verify the token — verification is server side and still Pro's.
	 */
	private function render_recaptcha_field( array $item, int $item_index ): void {
		$is_v3 = 'recaptcha_v3' === $item['field_type'];
		$name  = $is_v3 ? 'recaptcha_v3' : 'recaptcha';

		$site_key   = (string) get_option( $is_v3 ? 'elementor_pro_recaptcha_v3_site_key' : 'elementor_pro_recaptcha_site_key' );
		$secret_key = (string) get_option( $is_v3 ? 'elementor_pro_recaptcha_v3_secret_key' : 'elementor_pro_recaptcha_secret_key' );
		$is_enabled = '' !== $site_key && '' !== $secret_key;

		if ( $is_v3 ) {
			$this->add_render_attribute( 'field-group' . $item_index, 'class', $name . '-' . $item['recaptcha_badge'] );
		}

		$recaptcha_html = '<div class="elementor-field" id="form-field-' . $item['custom_id'] . '">';

		if ( $is_enabled ) {
			if ( ! \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
				wp_enqueue_script( 'elementor-' . $name . '-api', 'https://www.google.com/recaptcha/api.js?render=explicit', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			}

			$attributes = array(
				'class'        => 'elementor-g-recaptcha',
				'data-sitekey' => $site_key,
				'data-type'    => $is_v3 ? 'v3' : 'v2_checkbox',
			);

			if ( $is_v3 ) {
				$attributes['data-action'] = 'Form';
				$attributes['data-badge']  = $item['recaptcha_badge'];
				$attributes['data-size']   = 'invisible';
			} else {
				$attributes['data-theme'] = $item['recaptcha_style'];
				$attributes['data-size']  = $item['recaptcha_size'];
			}

			$this->add_render_attribute( $name . $item_index, $attributes );

			$recaptcha_html .= '<div ' . $this->get_render_attribute_string( $name . $item_index ) . '></div>';
		} elseif ( current_user_can( 'manage_options' ) ) {
			$recaptcha_html .= '<div class="elementor-alert elementor-alert-info">';
			$recaptcha_html .= $is_v3
				? esc_html__( 'To use reCAPTCHA V3, you need to add the API Key and complete the setup process in Dashboard > Elementor > Settings > Integrations > reCAPTCHA V3.', 'piecyfer-core' )
				: esc_html__( 'To use reCAPTCHA, you need to add the API Key and complete the setup process in Dashboard > Elementor > Settings > Integrations > reCAPTCHA.', 'piecyfer-core' );
			$recaptcha_html .= '</div>';
		}

		$recaptcha_html .= '</div>';

		echo $recaptcha_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Mirrors Form::render_icon_with_fallback() (form.php:2246-2255). */
	private function render_icon_with_fallback( array $settings ): void {
		$migrated = isset( $settings['__fa4_migrated']['selected_button_icon'] );
		$is_new   = empty( $settings['button_icon'] ) && Icons_Manager::is_migration_allowed();

		if ( $is_new || $migrated ) {
			Icons_Manager::render_icon( $settings['selected_button_icon'], array( 'aria-hidden' => 'true' ) );
		} else {
			?><i class="<?php echo esc_attr( $settings['button_icon'] ); ?>" aria-hidden="true"></i><?php
		}
	}

	// ------------------------------------------------- Form_Base equivalents

	/**
	 * Field name and id helpers, verbatim from Form_Base (form-base.php:261-272).
	 * These two strings are the contract between the markup and the submit
	 * handler: `form_fields[<custom_id>]` is what the ajax endpoint reads.
	 */
	public function get_attribute_name( array $item ): string {
		return "form_fields[{$item['custom_id']}]";
	}

	public function get_attribute_id( array $item ): string {
		return 'form-field-' . esc_attr( $item['custom_id'] );
	}

	private function add_required_attribute( string $element ): void {
		$this->add_render_attribute( $element, 'required', 'required' );
		$this->add_render_attribute( $element, 'aria-required', 'true' );
	}

	/** Mirrors Form_Base::make_textarea_field() (form-base.php:39-63). */
	private function make_textarea_field( array $item, int $item_index ): string {
		$this->add_render_attribute(
			'textarea' . $item_index,
			array(
				'class' => array(
					'elementor-field-textual',
					'elementor-field',
					esc_attr( $item['css_classes'] ),
					'elementor-size-' . $item['input_size'],
				),
				'name' => $this->get_attribute_name( $item ),
				'id' => $this->get_attribute_id( $item ),
				'rows' => $item['rows'],
			)
		);

		if ( $item['placeholder'] ) {
			$this->add_render_attribute( 'textarea' . $item_index, 'placeholder', $item['placeholder'] );
		}

		if ( $item['required'] ) {
			$this->add_required_attribute( 'textarea' . $item_index );
		}

		$value = empty( $item['field_value'] ) ? '' : $item['field_value'];

		return '<textarea ' . $this->get_render_attribute_string( 'textarea' . $item_index ) . '>' . $value . '</textarea>';
	}

	/**
	 * Mirrors Form_Base::make_select_field() (form-base.php:65-148), whitespace
	 * included — the buffered template's own indentation reaches the page.
	 */
	private function make_select_field( array $item, int $i ): string {
		$this->add_render_attribute(
			array(
				'select-wrapper' . $i => array(
					'class' => array(
						'elementor-field',
						'elementor-select-wrapper',
						'remove-before',
						esc_attr( $item['css_classes'] ),
					),
				),
				'select' . $i => array(
					'name' => $this->get_attribute_name( $item ) . ( ! empty( $item['allow_multiple'] ) ? '[]' : '' ),
					'id' => $this->get_attribute_id( $item ),
					'class' => array(
						'elementor-field-textual',
						'elementor-size-' . $item['input_size'],
					),
				),
			)
		);

		if ( $item['required'] ) {
			$this->add_required_attribute( 'select' . $i );
		}

		if ( $item['allow_multiple'] ) {
			$this->add_render_attribute( 'select' . $i, 'multiple' );
			if ( ! empty( $item['select_size'] ) ) {
				$this->add_render_attribute( 'select' . $i, 'size', $item['select_size'] );
			}
		}

		$options = preg_split( "/\\r\\n|\\r|\\n/", $item['field_options'] );

		if ( ! $options ) {
			return '';
		}

		ob_start();
		?>
		<div <?php $this->print_render_attribute_string( 'select-wrapper' . $i ); ?>>
			<div class="select-caret-down-wrapper">
				<?php
				if ( ! $item['allow_multiple'] ) {
					$icon = array(
						'library' => 'eicons',
						'value' => 'eicon-caret-down',
						'position' => 'right',
					);
					Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
				}
				?>
			</div>
			<select <?php $this->print_render_attribute_string( 'select' . $i ); ?>>
				<?php
				foreach ( $options as $key => $option ) {
					$option_id = esc_attr( $item['custom_id'] . $key );
					$option_value = esc_attr( $option );
					$option_label = esc_html( $option );

					if ( false !== strpos( $option, '|' ) ) {
						list( $label, $value ) = explode( '|', $option );
						$option_value = esc_attr( $value );
						$option_label = esc_html( $label );
					}

					$this->add_render_attribute( $option_id, 'value', $option_value );

					// Support multiple selected values
					if ( ! empty( $item['field_value'] ) && in_array( $option_value, explode( ',', $item['field_value'] ) ) ) {
						$this->add_render_attribute( $option_id, 'selected', 'selected' );
					} ?>
					<option <?php $this->print_render_attribute_string( $option_id ); ?>><?php
						echo $option_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></option>
				<?php } ?>
			</select>
		</div>
		<?php

		$select = ob_get_clean();
		return (string) $select;
	}

	/** Mirrors Form_Base::make_radio_checkbox_field() (form-base.php:150-188). */
	private function make_radio_checkbox_field( array $item, int $item_index, string $type ): string {
		$options = preg_split( "/\\r\\n|\\r|\\n/", $item['field_options'] );
		$html = '';
		if ( $options ) {
			$html .= '<div class="elementor-field-subgroup ' . esc_attr( $item['css_classes'] ) . ' ' . esc_attr( $item['inline_list'] ) . '">';
			foreach ( $options as $key => $option ) {
				$element_id = esc_attr( $item['custom_id'] ) . $key;
				$html_id = $this->get_attribute_id( $item ) . '-' . $key;
				$option_label = $option;
				$option_value = $option;
				if ( false !== strpos( $option, '|' ) ) {
					list( $option_label, $option_value ) = explode( '|', $option );
				}

				$this->add_render_attribute(
					$element_id,
					array(
						'type' => $type,
						'value' => $option_value,
						'id' => $html_id,
						'name' => $this->get_attribute_name( $item ) . ( ( 'checkbox' === $type && count( $options ) > 1 ) ? '[]' : '' ),
					)
				);

				if ( ! empty( $item['field_value'] ) && $option_value === $item['field_value'] ) {
					$this->add_render_attribute( $element_id, 'checked', 'checked' );
				}

				if ( $item['required'] && 'radio' === $type ) {
					$this->add_required_attribute( $element_id );
				}

				$html .= '<span class="elementor-field-option"><input ' . $this->get_render_attribute_string( $element_id ) . '> <label for="' . $html_id . '">' . $option_label . '</label></span>';
			}
			$html .= '</div>';
		}

		return $html;
	}

	/** Mirrors Form_Base::form_fields_render_attributes() (form-base.php:190-257). */
	private function form_fields_render_attributes( int $i, array $instance, array $item ): void {
		$this->add_render_attribute(
			array(
				'field-group' . $i => array(
					'class' => array(
						'elementor-field-type-' . $item['field_type'],
						'elementor-field-group',
						'elementor-column',
						'elementor-field-group-' . $item['custom_id'],
					),
				),
				'input' . $i => array(
					'type' => $item['field_type'],
					'name' => $this->get_attribute_name( $item ),
					'id' => $this->get_attribute_id( $item ),
					'class' => array(
						'elementor-field',
						'elementor-size-' . $item['input_size'],
						empty( $item['css_classes'] ) ? '' : esc_attr( $item['css_classes'] ),
					),
				),
				'label' . $i => array(
					'for' => $this->get_attribute_id( $item ),
					'class' => 'elementor-field-label',
				),
			)
		);

		if ( empty( $item['width'] ) ) {
			$item['width'] = '100';
		}

		$this->add_render_attribute( 'field-group' . $i, 'class', 'elementor-col-' . $item['width'] );

		if ( ! empty( $item['width_tablet'] ) ) {
			$this->add_render_attribute( 'field-group' . $i, 'class', 'elementor-md-' . $item['width_tablet'] );
		}

		if ( $item['allow_multiple'] ) {
			$this->add_render_attribute( 'field-group' . $i, 'class', 'elementor-field-type-' . $item['field_type'] . '-multiple' );
		}

		if ( ! empty( $item['width_mobile'] ) ) {
			$this->add_render_attribute( 'field-group' . $i, 'class', 'elementor-sm-' . $item['width_mobile'] );
		}

		if ( 'recaptcha_v3' === $item['field_type'] && ! empty( $item['recaptcha_badge'] ) ) {
			$this->add_render_attribute( 'field-group' . $i, 'class', 'recaptcha_v3-' . $item['recaptcha_badge'] );
		}

		// Allow zero as placeholder.
		if ( ! ElementorUtils::is_empty( $item['placeholder'] ) ) {
			$this->add_render_attribute( 'input' . $i, 'placeholder', $item['placeholder'] );
		}

		if ( ! empty( $item['field_value'] ) ) {
			$this->add_render_attribute( 'input' . $i, 'value', $item['field_value'] );
		}

		if ( ! $instance['show_labels'] ) {
			$this->add_render_attribute( 'label' . $i, 'class', 'elementor-screen-only' );
		}

		if ( ! empty( $item['required'] ) ) {
			$class = 'elementor-field-required';
			if ( ! empty( $instance['mark_required'] ) ) {
				$class .= ' elementor-mark-required';
			}
			$this->add_render_attribute( 'field-group' . $i, 'class', $class );
			$this->add_required_attribute( 'input' . $i );
		}
	}

	/** Mirrors Form_Base::render_plain_content() (form-base.php:259) — a no-op. */
	public function render_plain_content(): void {}

	/**
	 * Mirrors ElementorPro\Core\Utils::get_site_domain(), used for the
	 * `email@<domain>` From default.
	 */
	private function get_site_domain(): string {
		return str_ireplace( 'www.', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * The editor's live preview, reproduced from Form::content_template()
	 * (form.php:2498-2749).
	 *
	 * Kept because without it the editor falls back to a server render per
	 * keystroke, which is both slow and visibly different from the front end.
	 * Note the known asymmetries with the PHP render, all of them Pro's:
	 *   - the select markup here has no `.select-caret-down-wrapper`;
	 *   - `field_value.split(',').indexOf(...)` is truthy for -1, so the first
	 *     option always gets `selected`;
	 *   - the icon branch always prints the screen-reader "Submit" span.
	 * They are preserved so the editor looks exactly as it does today.
	 */
	protected function content_template(): void {
		?>
		<#
		view.addRenderAttribute( 'form', 'class', 'elementor-form' );

		if ( '' !== settings.form_id ) {
			view.addRenderAttribute( 'form', 'id', settings.form_id );
		}

		if ( '' !== settings.form_name ) {
			view.addRenderAttribute( 'form', 'name', settings.form_name );
		}

		if ( 'custom' === settings.form_validation ) {
			view.addRenderAttribute( 'form', 'novalidate' );
		}
		#>
		<form {{{ view.getRenderAttributeString( 'form' ) }}}>
			<div class="elementor-form-fields-wrapper elementor-labels-{{settings.label_position}}">
				<#
					for ( var i in settings.form_fields ) {
						var item = settings.form_fields[ i ];
						item = elementor.hooks.applyFilters( 'elementor_pro/forms/content_template/item', item, i, settings );

						item.field_type  = _.escape( item.field_type );
						item.field_value = _.escape( item.field_value );

						var options = item.field_options ? item.field_options.split( '\n' ) : [],
							itemClasses = _.escape( item.css_classes ),
							labelVisibility = '',
							placeholder = '',
							required = '',
							inputField = '',
							multiple = '',
							fieldGroupClasses = 'elementor-field-group elementor-column elementor-field-type-' + item.field_type,
							printLabel = settings.show_labels && ! [ 'hidden', 'html', 'step' ].includes( item.field_type );

						fieldGroupClasses += ' elementor-col-' + ( ( '' !== item.width ) ? item.width : '100' );

						if ( item.width_tablet ) {
							fieldGroupClasses += ' elementor-md-' + item.width_tablet;
						}

						if ( item.width_mobile ) {
							fieldGroupClasses += ' elementor-sm-' + item.width_mobile;
						}

						if ( item.required ) {
							required = 'required';
							fieldGroupClasses += ' elementor-field-required';

							if ( settings.mark_required ) {
								fieldGroupClasses += ' elementor-mark-required';
							}
						}

						if ( item.placeholder ) {
							placeholder = 'placeholder="' + _.escape( item.placeholder ) + '"';
						}

						if ( item.allow_multiple ) {
							multiple = ' multiple';
							fieldGroupClasses += ' elementor-field-type-' + item.field_type + '-multiple';
						}

						switch ( item.field_type ) {
							case 'step':
								inputField = `<div
									class="e-field-step elementor-hidden"
									data-label="${ item.field_label }"
									data-previousButton="${ item.previous_button || '' }"
									data-nextButton="${ item.next_button || '' }"
									data-iconUrl="${ 'svg' === item.selected_icon.library && item.selected_icon.value ? item.selected_icon.value.url : '' }"
									data-iconLibrary="${ 'svg' !== item.selected_icon.library && item.selected_icon.value ? item.selected_icon.value : '' }"></div>`;
								break;
							case 'html':
								inputField = item.field_html;
								break;

							case 'textarea':
								inputField = '<textarea class="elementor-field elementor-field-textual elementor-size-' + settings.input_size + ' ' + itemClasses + '" name="form_field_' + i + '" id="form_field_' + i + '" rows="' + item.rows + '" ' + required + ' ' + placeholder + '>' + item.field_value + '</textarea>';
								break;

							case 'select':
								if ( options ) {
									var size = '';
									if ( item.allow_multiple && item.select_size ) {
										size = ' size="' + item.select_size + '"';
									}
									inputField = '<div class="elementor-field elementor-select-wrapper ' + itemClasses + '">';
									inputField += '<select class="elementor-field-textual elementor-size-' + settings.input_size + '" name="form_field_' + i + '" id="form_field_' + i + '" ' + required + multiple + size + ' >';
									for ( var x in options ) {
										var option_value = options[ x ];
										var option_label = options[ x ];
										var option_id = 'form_field_option' + i + x;

										if ( options[ x ].indexOf( '|' ) > -1 ) {
											var label_value = options[ x ].split( '|' );
											option_label = label_value[0];
											option_value = label_value[1];
										}

										view.addRenderAttribute( option_id, 'value', option_value );
										if ( item.field_value.split( ',' ) .indexOf( option_value ) ) {
											view.addRenderAttribute( option_id, 'selected', 'selected' );
										}
										inputField += '<option ' + view.getRenderAttributeString( option_id ) + '>' + option_label + '</option>';
									}
									inputField += '</select></div>';
								}
								break;

							case 'radio':
							case 'checkbox':
								if ( options ) {
									var multiple = '';

									if ( 'checkbox' === item.field_type && options.length > 1 ) {
										multiple = '[]';
									}

									inputField = '<div class="elementor-field-subgroup ' + itemClasses + ' ' + _.escape( item.inline_list ) + '">';

									for ( var x in options ) {
										var option_value = options[ x ];
										var option_label = options[ x ];
										var option_id = 'form_field_' + item.field_type + i + x;
										if ( options[x].indexOf( '|' ) > -1 ) {
											var label_value = options[x].split( '|' );
											option_label = label_value[0];
											option_value = label_value[1];
										}

										view.addRenderAttribute( option_id, {
											value: option_value,
											type: item.field_type,
											id: 'form_field_' + i + '-' + x,
											name: 'form_field_' + i + multiple
										} );

										if ( option_value ===  item.field_value ) {
											view.addRenderAttribute( option_id, 'checked', 'checked' );
										}

										inputField += '<span class="elementor-field-option"><input ' + view.getRenderAttributeString( option_id ) + ' ' + required + '> ';
										inputField += '<label for="form_field_' + i + '-' + x + '">' + option_label + '</label></span>';

									}

									inputField += '</div>';
								}
								break;

							case 'text':
							case 'email':
							case 'url':
							case 'password':
							case 'number':
							case 'search':
								itemClasses = 'elementor-field-textual ' + itemClasses;
								inputField = '<input size="1" type="' + item.field_type + '" value="' + item.field_value + '" class="elementor-field elementor-size-' + settings.input_size + ' ' + itemClasses + '" name="form_field_' + i + '" id="form_field_' + i + '" ' + required + ' ' + placeholder + ' >';
								break;
							default:
								item.placeholder = _.escape( item.placeholder );
								inputField = elementor.hooks.applyFilters( 'elementor_pro/forms/content_template/field/' + item.field_type, '', item, i, settings );
						}

						if ( inputField ) {
							#>
							<div class="{{ fieldGroupClasses }}">

								<# if ( printLabel && item.field_label ) { #>
									<label class="elementor-field-label" for="form_field_{{ i }}" {{{ labelVisibility }}}>{{{ item.field_label }}}</label>
								<# } #>

								{{{ inputField }}}
							</div>
							<#
						}
					}

					view.addRenderAttribute(
						'submit-group',
						{
							'class': [
								'elementor-field-group',
								'elementor-column',
								'elementor-field-type-submit',
								'e-form__buttons',
								'elementor-col-' + ( ( '' !== settings.button_width ) ? settings.button_width : '100' )
							]
						}
					);

					if ( settings.button_width_tablet ) {
						view.addRenderAttribute( 'submit-group', 'class', 'elementor-md-' + settings.button_width_tablet );
					}

					if ( settings.button_width_mobile ) {
						view.addRenderAttribute( 'submit-group', 'class', 'elementor-sm-' + settings.button_width_mobile );
					}

					view.addRenderAttribute( 'button', 'type', 'submit' );
					view.addRenderAttribute( 'button', 'class', 'elementor-button' );

					if ( '' !== settings.button_css_id ) {
						view.addRenderAttribute( 'button', 'id', settings.button_css_id );
					}

					if ( '' !== settings.button_size ) {
						view.addRenderAttribute( 'button', 'class', 'elementor-size-' + settings.button_size );
					}

					if ( '' !== settings.button_type ) {
						view.addRenderAttribute( 'button', 'class', 'elementor-button-' + settings.button_type );
					}

					if ( '' !== settings.button_hover_animation ) {
						view.addRenderAttribute( 'button', 'class', 'elementor-animation-' + settings.button_hover_animation );
					}

					view.addRenderAttribute( 'button-content-wrapper', 'class', 'elementor-button-content-wrapper' );
					view.addRenderAttribute( 'button-icon', 'class', 'elementor-button-icon' );
					view.addRenderAttribute( 'button-text', 'class', 'elementor-button-text' );

					const iconHTML = elementor.helpers.renderIcon( view, settings.selected_button_icon, { 'aria-hidden': true }, 'i' , 'object' );
					const migrated = elementor.helpers.isIconMigrated( settings, 'selected_button_icon' );
					#>
					<div {{{ view.getRenderAttributeString( 'submit-group' ) }}}>
						<button {{{ view.getRenderAttributeString( 'button' ) }}}>
							<span {{{ view.getRenderAttributeString( 'button-content-wrapper' ) }}}>
								<# if ( settings.button_icon || settings.selected_button_icon ) { #>
									<span {{{ view.getRenderAttributeString( 'button-icon' ) }}}>
										<# if ( iconHTML && iconHTML.rendered && ( ! settings.button_icon || migrated ) ) { #>
											{{{ iconHTML.value }}}
										<# } else { #>
											<i class="{{ settings.button_icon }}" aria-hidden="true"></i>
										<# } #>
										<span class="elementor-screen-only"><?php echo esc_html__( 'Submit', 'piecyfer-core' ); ?></span>
									</span>
								<# } #>

								<# if ( settings.button_text ) { #>
									<span {{{ view.getRenderAttributeString( 'button-text' ) }}}>{{{ settings.button_text }}}</span>
								<# } #>
							</span>
						</button>
					</div>
			</div>
		</form>
		<?php
	}
}
