<?php
/**
 * The value object every form action receives.
 *
 * Mirrors ElementorPro\Modules\Forms\Classes\Form_Record
 * (elementor-pro/modules/forms/classes/form-record.php) close to 1:1. The public
 * method names are a contract, not an implementation detail: ElementsKit's
 * Google Sheets integration calls `$record->get_formatted_data()` from
 * `elementor_pro/forms/new_record` (elementskit/modules/google-sheet-elementor-pro-form/init.php:102-117),
 * and any other listener may call anything on this list.
 *
 * Deliberate differences from Pro are marked DIVERGENCE and explained where they
 * occur.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms;

use PieCyfer\Core\Forms\Fields\UploadField;

defined( 'ABSPATH' ) || exit;

class FormRecord {

	/** @var array<string,mixed> */
	protected $sent_data;

	/** @var array<string,array<string,mixed>> */
	protected $fields = array();

	/** @var string */
	protected $form_type;

	/** @var array<string,mixed> */
	protected $form_settings;

	/** @var array<string,array{url:array<int,string>,path:array<int,string>}> */
	protected $files = array();

	/** @var array<string,array{title:string,value:string}> */
	protected $meta = array();

	/**
	 * Server-derived request context: `queried_id`, `referrer`, `post_id`.
	 *
	 * Pro reads these straight out of `$_POST` at meta-building time. Keeping
	 * them on the record instead means the handler can validate them once, in one
	 * place — see Security::resolve_queried_id().
	 *
	 * @var array<string,mixed>
	 */
	protected $context = array();

	/**
	 * @param array<string,mixed> $sent_data The `form_fields` POST array.
	 * @param array<string,mixed> $form      The located element, with resolved settings.
	 * @param array<string,mixed> $context   Validated request context.
	 */
	public function __construct( $sent_data, array $form, array $context = array() ) {
		$this->form_type     = (string) ( $form['widgetType'] ?? 'form' );
		$this->form_settings = (array) ( $form['settings'] ?? array() );
		$this->sent_data     = stripslashes_deep( is_array( $sent_data ) ? $sent_data : array() );
		$this->context       = $context;

		$this->set_fields();
		$this->set_meta();
	}

	/**
	 * Field title => value, the shape third-party listeners consume.
	 *
	 * @param bool $with_meta Append the metadata rows.
	 *
	 * @return array<string,mixed>
	 */
	public function get_formatted_data( $with_meta = false ): array {
		$formatted = array();
		$no_label  = esc_html__( 'No Label', 'piecyfer-core' );
		$fields    = $this->fields;

		if ( $with_meta ) {
			$fields = array_merge( $fields, $this->meta );
		}

		foreach ( $fields as $key => $field ) {
			if ( empty( $field['title'] ) ) {
				$formatted[ $no_label . ' ' . $key ] = $field['value'];
			} else {
				$formatted[ $field['title'] ] = $field['value'];
			}
		}

		return $formatted;
	}

	/**
	 * Required checks, per-type validators, then the form-level validators.
	 *
	 * @param AjaxHandler $ajax_handler The handler collecting the errors.
	 */
	public function validate( $ajax_handler ): bool {
		foreach ( $this->fields as $id => $field ) {
			$field_type = $field['type'];

			if ( ! empty( $field['required'] ) && 'upload' !== $field_type && self::is_empty_value( $field ) ) {
				$ajax_handler->add_error( (string) $id, Messages::get( Messages::FIELD_REQUIRED, $this->form_settings ) );
			}

			/** This action mirrors elementor_pro/forms/validation/{$field_type} — see Hooks. */
			Hooks::do_action( "validation/{$field_type}", $field, $this, $ajax_handler );
		}

		/** This action mirrors elementor_pro/forms/validation — see Hooks. */
		Hooks::do_action( 'validation', $this, $ajax_handler );

		return empty( $ajax_handler->errors );
	}

	/**
	 * Is this field empty for the purposes of the required check?
	 *
	 * DIVERGENCE FROM PRO — this is bug 4 of the brief, and it is a live hole.
	 *
	 * Pro tests `'' === $field['value']` (form-record.php:47) against the
	 * *sanitised* value. `Number::sanitize_field()` returns `intval( $value )`
	 * (fields/number.php:92-94), so an empty required number field sanitises to
	 * the integer 0, `'' === 0` is false, and the field passes. Any sanitiser
	 * that returns a non-string has the same hole, including third-party field
	 * types registered through `elementor_pro/forms/sanitize/{type}`.
	 *
	 * We test the raw submitted value instead, before any sanitiser has had a
	 * chance to change its type. `'0'` is still a legitimate answer and still
	 * passes; a missing key, an empty string, whitespace, or an array of nothing
	 * but empty strings does not.
	 *
	 * @param array<string,mixed> $field
	 */
	private static function is_empty_value( array $field ): bool {
		$raw = $field['raw_value'] ?? '';

		if ( is_array( $raw ) ) {
			foreach ( $raw as $item ) {
				if ( '' !== trim( (string) $item ) ) {
					return false;
				}
			}

			return true;
		}

		return '' === trim( (string) $raw );
	}

	/**
	 * @param AjaxHandler $ajax_handler
	 */
	public function process_fields( $ajax_handler ): void {
		foreach ( $this->fields as $id => $field ) {
			$field_type = $field['type'];

			/** This action mirrors elementor_pro/forms/process/{$field_type} — see Hooks. */
			Hooks::do_action( "process/{$field_type}", $field, $this, $ajax_handler );
		}

		/** This action mirrors elementor_pro/forms/process — see Hooks. */
		Hooks::do_action( 'process', $this, $ajax_handler );
	}

	/**
	 * @param string $property
	 *
	 * @return mixed
	 */
	public function get( $property ) {
		if ( isset( $this->{$property} ) ) {
			return $this->{$property};
		}

		return null;
	}

	/**
	 * @param string $property
	 * @param mixed  $value
	 */
	public function set( $property, $value ): void {
		if ( ! property_exists( $this, (string) $property ) ) {
			return;
		}

		$this->{$property} = $value;
	}

	/**
	 * @param string $setting
	 *
	 * @return mixed
	 */
	public function get_form_settings( $setting ) {
		return $this->form_settings[ $setting ] ?? null;
	}

	/**
	 * @param array<string,mixed> $args
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_field( $args ): array {
		return wp_list_filter( $this->fields, $args );
	}

	/**
	 * @param string $id
	 */
	public function remove_field( $id ): void {
		unset( $this->fields[ $id ] );
	}

	/**
	 * @param string $field_id
	 * @param string $property
	 * @param mixed  $value
	 */
	public function update_field( $field_id, $property, $value ): void {
		if ( ! isset( $this->fields[ $field_id ] ) || ! isset( $this->fields[ $field_id ][ $property ] ) ) {
			return;
		}

		$this->fields[ $field_id ][ $property ] = $value;
	}

	/**
	 * Record a stored upload against its field.
	 *
	 * `url` becomes the literal string `attached` in MODE_ATTACH, exactly as Pro
	 * does (form-record.php:325), because in that mode there is no link to give.
	 *
	 * @param string                       $id       Field custom_id.
	 * @param int                          $index    Position within a multi-file field.
	 * @param array{path:string,url:string} $filename Stored file.
	 */
	public function add_file( $id, $index, $filename ): void {
		if ( ! isset( $this->files[ $id ] ) || ! is_array( $this->files[ $id ] ) ) {
			$this->files[ $id ] = array(
				'url'  => array(),
				'path' => array(),
			);
		}

		// Pro reads $this->fields[ $id ]['attachment_type'] unguarded; a field
		// saved before the control existed makes that a fatal in PHP 8.
		$attachment_type = $this->fields[ $id ]['attachment_type'] ?? UploadField::MODE_LINK;

		$this->files[ $id ]['url'][ $index ]  = UploadField::MODE_ATTACH === $attachment_type ? 'attached' : $filename['url'];
		$this->files[ $id ]['path'][ $index ] = $filename['path'];
	}

	/**
	 * DIVERGENCE FROM PRO: Pro's version (form-record.php:329-337) compares
	 * `$field['field_type']`, a key `set_fields()` never writes — the key is
	 * `type` — so it always returns false. Nothing in Pro depends on the broken
	 * answer; a third party might depend on the working one.
	 *
	 * @param string $type
	 */
	public function has_field_type( $type ): bool {
		foreach ( $this->fields as $field ) {
			if ( $type === ( $field['type'] ?? null ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Substitute `[field id="…"]` in a setting with that field's value.
	 *
	 * This is what makes the hand-built `email_content` on every form here work,
	 * and also the `[field id="appPageName"]` inside `email_subject` on the five
	 * job-application forms. Regex is Pro's (form-record.php:302).
	 *
	 * @param string $setting
	 * @param bool   $urlencode
	 */
	public function replace_setting_shortcodes( $setting, $urlencode = false ): string {
		return (string) preg_replace_callback(
			'/(\[field[^]]*id="(\w+)"[^]]*\])/',
			function ( array $matches ) use ( $urlencode ): string {
				$value = '';

				if ( isset( $this->fields[ $matches[2] ] ) ) {
					$value = (string) $this->fields[ $matches[2] ]['value'];
				}

				return $urlencode ? rawurlencode( $value ) : $value;
			},
			(string) $setting
		);
	}

	/**
	 * The metadata rows the Email action can append.
	 *
	 * @param string[] $meta_keys
	 *
	 * @return array<string,array{title:string,value:string}>
	 */
	public function get_form_meta( $meta_keys = array() ): array {
		$result = array();

		foreach ( (array) $meta_keys as $metadata_type ) {
			switch ( $metadata_type ) {
				case 'date':
					$result['date'] = array(
						'title' => esc_html__( 'Date', 'piecyfer-core' ),
						'value' => date_i18n( (string) get_option( 'date_format' ) ),
					);
					break;

				case 'time':
					$result['time'] = array(
						'title' => esc_html__( 'Time', 'piecyfer-core' ),
						'value' => date_i18n( (string) get_option( 'time_format' ) ),
					);
					break;

				case 'page_url':
					$result['page_url'] = array(
						'title' => esc_html__( 'Page URL', 'piecyfer-core' ),
						'value' => (string) ( $this->context['referrer'] ?? '' ),
					);
					break;

				case 'page_title':
					$result['page_title'] = array(
						'title' => esc_html__( 'Page Title', 'piecyfer-core' ),
						'value' => $this->page_title(),
					);
					break;

				case 'user_agent':
					$result['user_agent'] = array(
						'title' => esc_html__( 'User Agent', 'piecyfer-core' ),
						'value' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					);
					break;

				case 'remote_ip':
					$result['remote_ip'] = array(
						'title' => esc_html__( 'Remote IP', 'piecyfer-core' ),
						'value' => Security::client_ip(),
					);
					break;

				case 'credit':
					/*
					 * Pro hard-codes 'Elementor' (form-record.php:207). Kept
					 * verbatim so today's emails do not change; `credit` is in
					 * the control default, so the five job forms and both footers
					 * really do carry this line right now. Filterable because the
					 * day we stop shipping Elementor Pro it stops being true.
					 */
					$result['credit'] = array(
						'title' => esc_html__( 'Powered by', 'piecyfer-core' ),
						'value' => (string) apply_filters( 'piecyfer/forms/credit', 'Elementor' ),
					);
					break;
			}
		}

		return $result;
	}

	/**
	 * The page title for the `page_title` meta row.
	 *
	 * DIVERGENCE FROM PRO, and the fix for the wrong-title-in-every-email symptom
	 * described in 06-FORM-SPEC.md §7. Pro reads the `referer_title` hidden input
	 * (form-record.php:187), which is baked into the HTML by `wp_title()` at
	 * *render* time — so a form that lives in a cached footer template reports
	 * whichever page happened to prime the cache.
	 *
	 * We derive it from the post the submission actually came from, and fall back
	 * to the posted value only when the URL resolves to nothing (a 404, an
	 * archive, the front page).
	 */
	private function page_title(): string {
		$queried_id = (int) ( $this->context['queried_id'] ?? 0 );

		if ( $queried_id > 0 ) {
			$title = get_the_title( $queried_id );

			if ( '' !== $title ) {
				return wp_strip_all_tags( $title );
			}
		}

		if ( isset( $_POST['referer_title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce policy is Security::nonce_mode(); value is sanitised here.
			return sanitize_text_field( wp_unslash( $_POST['referer_title'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		return '';
	}

	private function set_meta(): void {
		$form_metadata = $this->form_settings['form_metadata'] ?? array();

		if ( empty( $form_metadata ) ) {
			return;
		}

		$this->meta = $this->get_form_meta( (array) $form_metadata );
	}

	/**
	 * Build one record entry per repeater row. Mirrors form-record.php:226-257.
	 */
	private function set_fields(): void {
		foreach ( (array) ( $this->form_settings['form_fields'] ?? array() ) as $form_field ) {
			$custom_id = (string) ( $form_field['custom_id'] ?? '' );

			if ( '' === $custom_id ) {
				continue;
			}

			$field = array(
				'id'        => $custom_id,
				'type'      => (string) ( $form_field['field_type'] ?? 'text' ),
				'title'     => $form_field['field_label'] ?? '',
				'value'     => '',
				'raw_value' => '',
				'required'  => ! empty( $form_field['required'] ),
			);

			// Pro copies these four onto upload rows only (form-record.php:237-242);
			// the validators read them straight off the field array.
			if ( 'upload' === $field['type'] ) {
				$field['file_sizes']      = $form_field['file_sizes'] ?? '';
				$field['file_types']      = $form_field['file_types'] ?? '';
				$field['max_files']       = $form_field['max_files'] ?? '';
				$field['attachment_type'] = $form_field['attachment_type'] ?? UploadField::MODE_LINK;
			}

			// Number's bounds live on the repeater row, and Pro's Number::validation
			// reads them off the *field* array — which only works because Pro's
			// form-record copies nothing and the bounds are therefore always
			// missing, i.e. Pro's min/max validation never fires. Copying them
			// makes the control do what the editor thinks it does.
			if ( 'number' === $field['type'] ) {
				$field['field_min'] = $form_field['field_min'] ?? '';
				$field['field_max'] = $form_field['field_max'] ?? '';
			}

			if ( 'date' === $field['type'] ) {
				$field['min_date'] = $form_field['min_date'] ?? '';
				$field['max_date'] = $form_field['max_date'] ?? '';
			}

			if ( isset( $this->sent_data[ $custom_id ] ) ) {
				$field['raw_value'] = $this->sent_data[ $custom_id ];

				$value = $field['raw_value'];

				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}

				$field['value'] = $this->sanitize_field( $field, (string) $value );
			}

			$this->fields[ $custom_id ] = $field;
		}
	}

	/**
	 * Per-type sanitisation. Table is Pro's (form-record.php:259-298).
	 *
	 * @param array<string,mixed> $field
	 * @param string              $value
	 *
	 * @return mixed
	 */
	private function sanitize_field( array $field, string $value ) {
		switch ( $field['type'] ) {
			case 'text':
			case 'password':
			case 'hidden':
			case 'search':
			case 'checkbox':
			case 'radio':
			case 'select':
				return sanitize_text_field( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'email':
				return sanitize_email( $value );

			default:
				/** This filter mirrors elementor_pro/forms/sanitize/{$type} — see Hooks. */
				return Hooks::apply_filters( "sanitize/{$field['type']}", $value, $field );
		}
	}
}
