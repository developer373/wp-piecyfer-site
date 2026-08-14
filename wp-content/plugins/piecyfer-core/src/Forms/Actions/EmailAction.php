<?php
/**
 * The Email action — the one action every form on this site actually runs.
 *
 * `submit_actions` is absent from all nine saved forms, so it resolves to the
 * control default, and with no submissions component registered that default is
 * exactly `['email']` (06-FORM-SPEC.md §3.2). If this class does not work, the
 * site silently loses every enquiry.
 *
 * Behaviour is Pro's (elementor-pro/modules/forms/actions/email.php:277-404)
 * including the parts that look like mistakes — the runtime fallbacks that
 * differ from the control defaults, the `---` separator block, the `<br />` used
 * for newlines inside a textarea while everything else joins with `<br>`. Each
 * deliberate difference is marked DIVERGENCE.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Actions;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;
use PieCyfer\Core\Forms\Hooks;
use PieCyfer\Core\Forms\Messages;
use PieCyfer\Core\Forms\Security;
use PieCyfer\Core\Forms\Fields\UploadField;
use PieCyfer\Core\Forms\Storage\UploadStore;

defined( 'ABSPATH' ) || exit;

class EmailAction extends ActionBase {

	public function get_name(): string {
		return 'email';
	}

	public function get_label(): string {
		return esc_html__( 'Email', 'piecyfer-core' );
	}

	/**
	 * Prefix for Email 2's control ids. See {@see Email2Action}.
	 */
	protected function get_control_id( string $control_id ): string {
		return $control_id;
	}

	/**
	 * @throws \Exception When wp_mail() reports failure.
	 */
	public function run( FormRecord $record, AjaxHandler $ajax_handler ): void {
		$settings = (array) $record->get( 'form_settings' );

		$send_html  = 'plain' !== ( $settings[ $this->get_control_id( 'email_content_type' ) ] ?? 'html' );
		$line_break = $send_html ? '<br>' : "\n";

		/*
		 * Runtime fallbacks. Note these are NOT the same as the control
		 * defaults: `email_from` falls back to the admin address rather than
		 * `email@<domain>`, and the subject uses bloginfo('name') where the
		 * control used option('blogname'). Reproduced rather than tidied — all
		 * nine forms set both explicitly, so the difference is invisible here,
		 * and "tidying" it is exactly the kind of change that silently redirects
		 * mail on the tenth form somebody adds.
		 */
		$fields = array(
			'email_to'        => get_option( 'admin_email' ),
			/* translators: %s: Site title. */
			'email_subject'   => sprintf( esc_html__( 'New message from "%s"', 'piecyfer-core' ), get_bloginfo( 'name' ) ),
			'email_content'   => '[all-fields]',
			'email_from_name' => get_bloginfo( 'name' ),
			'email_from'      => get_bloginfo( 'admin_email' ),
			'email_reply_to'  => 'noreply@' . $this->site_domain(),
			'email_to_cc'     => '',
			'email_to_bcc'    => '',
		);

		foreach ( $fields as $key => $default ) {
			unset( $default );

			$setting = $settings[ $this->get_control_id( $key ) ] ?? '';
			$setting = is_scalar( $setting ) ? trim( (string) $setting ) : '';

			// This is what makes `Full Name: [field id="contact_us_full_name"]`
			// and the `[field id="appPageName"]` inside the job-form subjects
			// work.
			$setting = $record->replace_setting_shortcodes( $setting );

			if ( '' !== $setting ) {
				$fields[ $key ] = $setting;
			}
		}

		$email_reply_to = $this->get_reply_to( $record, $fields );

		$fields['email_content'] = $this->replace_content_shortcodes( (string) $fields['email_content'], $record, $line_break );

		$email_meta              = $this->meta_block( $record, $settings, $line_break );
		$fields['email_content'] = '' === $email_meta
			? $fields['email_content']
			: $fields['email_content'] . $line_break . '---' . $line_break . $line_break . $email_meta;

		/*
		 * DIVERGENCE FROM PRO: every value interpolated into a header is
		 * stripped of CR/LF first. Pro interpolates them raw (email.php:320-330),
		 * and `email_subject` on the five job-application forms embeds a
		 * client-supplied hidden field. sanitize_text_field() already removes
		 * newlines on that particular path, but header-injection safety should
		 * not depend on a sanitiser two layers away.
		 */
		$from_name = Security::header_safe( (string) $fields['email_from_name'] );
		$from      = Security::header_safe( (string) $fields['email_from'] );
		$subject   = Security::header_safe( (string) $fields['email_subject'] );

		$headers = sprintf( 'From: %s <%s>' . "\r\n", $from_name, $from );

		/*
		 * DIVERGENCE FROM PRO: Pro always writes the Reply-To header, so when no
		 * reply-to field is configured — which is every form on this site — it
		 * emits a literal `Reply-To: \r\n`. PHPMailer discards it, so the mail
		 * that arrives is identical; a malformed header that happens to be
		 * ignored is not worth reproducing.
		 */
		if ( '' !== $email_reply_to ) {
			$headers .= sprintf( 'Reply-To: %s' . "\r\n", Security::header_safe( $email_reply_to ) );
		}

		if ( $send_html ) {
			$headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
		}

		$cc = $this->address_list( (string) $fields['email_to_cc'] );

		$cc_header = array() === $cc ? '' : 'Cc: ' . implode( ',', $cc ) . "\r\n";

		/** This filter mirrors elementor_pro/forms/wp_mail_headers — see Hooks. */
		$headers = Hooks::apply_filters( 'wp_mail_headers', $headers );

		/** This filter mirrors elementor_pro/forms/wp_mail_message — see Hooks. */
		$fields['email_content'] = Hooks::apply_filters( 'wp_mail_message', $fields['email_content'] );

		$attachments_mode_attach = $this->files_by_attachment_type( $settings, $record, UploadField::MODE_ATTACH );
		$attachments_mode_both   = $this->files_by_attachment_type( $settings, $record, UploadField::MODE_BOTH );
		$attachments             = array_merge( $attachments_mode_attach, $attachments_mode_both );

		$email_sent = wp_mail(
			Security::header_safe( (string) $fields['email_to'] ),
			$subject,
			$fields['email_content'],
			$headers . $cc_header,
			$attachments
		);

		// Bcc is sent as separate messages, not as a header (email.php:367-378),
		// and deliberately without the Cc header.
		foreach ( $this->address_list( (string) $fields['email_to_bcc'] ) as $bcc_email ) {
			wp_mail( $bcc_email, $subject, $fields['email_content'], $headers, $attachments );
		}

		// Attach-mode files exist only to be posted; they are removed once sent.
		foreach ( $attachments_mode_attach as $file ) {
			$this->forget( (string) $file );
		}

		if ( ! $email_sent ) {
			$message = Messages::get( Messages::SERVER_ERROR, $settings );

			$ajax_handler->add_error_message( $message );

			throw new \Exception( esc_html( $message ) );
		}

		/*
		 * DIVERGENCE FROM PRO: Pro fires this before checking `$email_sent`
		 * (email.php:395), so `mail_sent` fires on failed sends too. Anything
		 * listening in order to log a successful notification was being lied to.
		 */
		/** This action mirrors elementor_pro/forms/mail_sent — see Hooks. */
		Hooks::do_action( 'mail_sent', $settings, $record );
	}

	/**
	 * The metadata block appended after the `---` separator.
	 *
	 * @param array<string,mixed> $settings
	 */
	private function meta_block( FormRecord $record, array $settings, string $line_break ): string {
		$wanted = (array) ( $settings[ $this->get_control_id( 'form_metadata' ) ] ?? array() );
		$block  = '';

		foreach ( (array) $record->get( 'meta' ) as $id => $field ) {
			if ( in_array( $id, $wanted, true ) ) {
				$block .= $this->field_formatted( $field ) . $line_break;
			}
		}

		return $block;
	}

	/**
	 * `Title: value`, or the bare value when the field has no label, or ''.
	 *
	 * Verbatim from email.php:406-415. It is why the two footer forms — whose
	 * fields have no labels at all — mail a body of bare values.
	 *
	 * @param array<string,mixed> $field
	 */
	private function field_formatted( array $field ): string {
		if ( ! empty( $field['title'] ) ) {
			return sprintf( '%s: %s', $field['title'], $field['value'] );
		}

		if ( ! empty( $field['value'] ) ) {
			return sprintf( '%s', $field['value'] );
		}

		return '';
	}

	/**
	 * Reply-To is an *index into the form's fields*, not an address.
	 *
	 * Pro takes the value from `sent_data` — the raw POST — rather than from the
	 * sanitised field, and only uses it if `is_email()` passes (email.php:422-436).
	 * Kept, including the raw-value detail: `is_email()` is the gate either way.
	 *
	 * @param array<string,string> $fields
	 */
	protected function get_reply_to( FormRecord $record, array $fields ): string {
		if ( empty( $fields['email_reply_to'] ) ) {
			return '';
		}

		$sent_data = (array) $record->get( 'sent_data' );

		foreach ( (array) $record->get( 'fields' ) as $field_index => $field ) {
			unset( $field );

			if ( (string) $field_index !== (string) $fields['email_reply_to'] ) {
				continue;
			}

			$candidate = $sent_data[ $field_index ] ?? '';

			if ( is_string( $candidate ) && '' !== $candidate && is_email( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Expand `[all-fields]` and any WordPress shortcode in the body.
	 *
	 * Verbatim from email.php:444-469, including the ordering (WP shortcodes
	 * first) and the textarea newline handling.
	 */
	private function replace_content_shortcodes( string $email_content, FormRecord $record, string $line_break ): string {
		$email_content        = do_shortcode( $email_content );
		$all_fields_shortcode = '[all-fields]';

		if ( false === strpos( $email_content, $all_fields_shortcode ) ) {
			return $email_content;
		}

		$text = '';

		foreach ( (array) $record->get( 'fields' ) as $field ) {
			// Attach-only uploads are excluded from the body: the file is on the
			// message, so a line saying "attached" adds nothing.
			if ( UploadField::MODE_ATTACH === ( $field['attachment_type'] ?? null ) ) {
				continue;
			}

			$formatted = $this->field_formatted( $field );

			if ( 'textarea' === ( $field['type'] ?? '' ) && '<br>' === $line_break ) {
				$formatted = str_replace( array( "\r\n", "\n", "\r" ), '<br />', $formatted );
			}

			$text .= $formatted . $line_break;
		}

		return str_replace( $all_fields_shortcode, $text, $email_content );
	}

	/**
	 * Absolute paths of the files to attach for one attachment mode.
	 *
	 * @param array<string,mixed> $settings
	 *
	 * @return string[]
	 */
	private function files_by_attachment_type( array $settings, FormRecord $record, string $type ): array {
		$paths = array();
		$files = (array) $record->get( 'files' );

		foreach ( (array) ( $settings['form_fields'] ?? array() ) as $form_field ) {
			// Pro reads $field['attachment_type'] on every field, upload or not
			// (email.php:481), which is an undefined-key warning per field on
			// PHP 8.
			if ( $type !== ( $form_field['attachment_type'] ?? null ) ) {
				continue;
			}

			$id = (string) ( $form_field['custom_id'] ?? '' );

			foreach ( (array) ( $files[ $id ]['path'] ?? array() ) as $path ) {
				if ( is_string( $path ) && is_readable( $path ) ) {
					$paths[] = $path;
				}
			}
		}

		return $paths;
	}

	/**
	 * Split a comma-separated address setting, dropping anything that is not an
	 * address.
	 *
	 * DIVERGENCE FROM PRO: Pro passes `email_to_cc` into a header and each
	 * `email_to_bcc` entry into `wp_mail()` unchecked. Both settings accept
	 * `[field id="…"]` substitution, i.e. visitor input, so an unvalidated value
	 * there is a header-injection and open-relay primitive. Both are empty on all
	 * nine forms, so nothing observable changes.
	 *
	 * @return string[]
	 */
	private function address_list( string $raw ): array {
		$addresses = array();

		foreach ( explode( ',', $raw ) as $candidate ) {
			$candidate = trim( Security::header_safe( $candidate ) );

			if ( '' !== $candidate && is_email( $candidate ) ) {
				$addresses[] = $candidate;
			}
		}

		return $addresses;
	}

	/**
	 * Delete an attach-mode file and its sidecar once it has been posted.
	 */
	private function forget( string $path ): void {
		$id = pathinfo( $path, PATHINFO_FILENAME );

		if ( is_string( $id ) && 1 === preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			UploadStore::delete( $id );

			return;
		}

		wp_delete_file( $path );
	}

	/**
	 * Mirrors ElementorPro\Core\Utils::get_site_domain() (core/utils.php:75-77).
	 */
	private function site_domain(): string {
		return str_ireplace( 'www.', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}
}
