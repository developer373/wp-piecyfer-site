<?php
/**
 * Shared reCAPTCHA verification (v2 semantics; v3 extends it).
 *
 * Mirrors ElementorPro\Modules\Forms\Classes\Recaptcha_Handler::validation()
 * (recaptcha-handler.php:119-193). The option names are Pro's, unchanged, so the
 * keys already in `wp_options` keep working and nothing has to be migrated.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Recaptcha;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;
use PieCyfer\Core\Forms\Security;

defined( 'ABSPATH' ) || exit;

class Handler {

	public const OPTION_SITE_KEY   = 'elementor_pro_recaptcha_site_key';
	public const OPTION_SECRET_KEY = 'elementor_pro_recaptcha_secret_key';

	public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	public static function get_recaptcha_name(): string {
		return 'recaptcha';
	}

	public static function get_site_key(): string {
		return (string) get_option( static::OPTION_SITE_KEY, '' );
	}

	public static function get_secret_key(): string {
		return (string) get_option( static::OPTION_SECRET_KEY, '' );
	}

	/**
	 * Pro's test: both keys present (recaptcha-v3-handler.php:47-49).
	 *
	 * "Present" is not "valid" — see {@see V3Handler} for what happens when the
	 * secret turns out to belong to a different reCAPTCHA version.
	 */
	public static function is_enabled(): bool {
		return '' !== static::get_site_key() && '' !== static::get_secret_key();
	}

	/**
	 * Hook the validator, but only when there are keys to validate with.
	 *
	 * Same condition Pro uses (recaptcha-handler.php:292-295). With no keys
	 * configured, a form carrying a captcha field is simply not protected — the
	 * field renders an admin-only notice and submissions go through. That is
	 * Pro's behaviour and it is the right one: the alternative is that a
	 * misplaced option row silently blocks every enquiry on seven of nine forms.
	 */
	public function register(): void {
		if ( ! static::is_enabled() ) {
			return;
		}

		add_action( 'piecyfer/forms/validation', array( $this, 'validation' ), 10, 2 );
	}

	/**
	 * Verify the token that came with this submission.
	 */
	public function validation( FormRecord $record, AjaxHandler $ajax_handler ): void {
		$fields = $record->get_field( array( 'type' => static::get_recaptcha_name() ) );

		if ( empty( $fields ) ) {
			// No captcha field on this form: nothing to check. Two of the nine
			// forms are in this state, and they are wide open — which is what
			// the rate limiter and the (optional) nonce are for.
			return;
		}

		$field = current( $fields );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the token itself is the proof of origin here.
		$token = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';

		if ( '' === $token ) {
			$ajax_handler->add_error(
				(string) $field['id'],
				esc_html__( 'The Captcha field cannot be blank. Please enter a value.', 'piecyfer-core' )
			);

			$record->remove_field( (string) $field['id'] );

			return;
		}

		$result = $this->verify( $token );

		if ( null === $result ) {
			/*
			 * DIVERGENCE FROM PRO — transport failure is fail-OPEN by default.
			 *
			 * Pro adds "Can not connect to the reCAPTCHA server (%d)" and stops
			 * (recaptcha-handler.php:161-166). With a 5-second timeout, no retry
			 * and no alerting, a Google outage or a firewall change blocks every
			 * submission on seven of the nine forms and nobody finds out until
			 * someone phones to ask why the form is broken.
			 *
			 * An attacker cannot cause our outbound request to fail, so the
			 * exposure is "spam gets through during an outage" against
			 * "enquiries are silently lost during an outage". Set
			 * `piecyfer/forms/recaptcha/fail_open` to false for Pro's behaviour.
			 */
			$this->log( 'verification transport failure; fail-open = ' . ( $this->fail_open() ? 'yes' : 'no' ) );

			if ( ! $this->fail_open() ) {
				$ajax_handler->add_error(
					(string) $field['id'],
					esc_html__( 'Can not connect to the reCAPTCHA server.', 'piecyfer-core' )
				);
			} else {
				/**
				 * A submission was accepted without a verified captcha.
				 *
				 * Hook this to alert someone: if it fires repeatedly, either
				 * Google is unreachable from this server or the keys are wrong,
				 * and every protected form is currently unprotected.
				 *
				 * @param string $reason
				 */
				do_action( 'piecyfer/forms/recaptcha_unverified', 'transport' );
			}

			$record->remove_field( (string) $field['id'] );

			return;
		}

		$verdict = $this->validate_result( $result, $field );

		if ( true !== $verdict ) {
			$this->add_error( $ajax_handler, $field, is_string( $verdict ) ? $verdict : $this->error_message( $result ) );
		}

		// Always removed, pass or fail, so the token never reaches an email or a
		// stored record (recaptcha-handler.php:191).
		$record->remove_field( (string) $field['id'] );
	}

	/**
	 * POST the token to Google. Null means "could not ask", not "failed".
	 *
	 * One retry, because a single dropped connection should not cost an enquiry.
	 *
	 * @return array<string,mixed>|null
	 */
	protected function verify( string $token ): ?array {
		$request = array(
			'timeout' => (int) apply_filters( 'piecyfer/forms/recaptcha/timeout', 8 ),
			'body'    => array(
				'secret'   => static::get_secret_key(),
				'response' => $token,
				'remoteip' => Security::client_ip(),
			),
		);

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$response = wp_remote_post( self::VERIFY_URL, $request );

			if ( is_wp_error( $response ) ) {
				$this->log( 'wp_remote_post error: ' . $response->get_error_message() );

				continue;
			}

			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				$this->log( 'unexpected response code ' . wp_remote_retrieve_response_code( $response ) );

				continue;
			}

			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

			if ( is_array( $decoded ) ) {
				return $decoded;
			}

			$this->log( 'response body was not JSON' );
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $result
	 * @param array<string,mixed> $field
	 *
	 * @return bool|string True, false, or a message explaining the failure.
	 */
	protected function validate_result( array $result, array $field ) {
		return ! empty( $result['success'] );
	}

	/**
	 * Map Google's error codes onto Pro's messages (recaptcha-handler.php:139-144).
	 *
	 * @param array<string,mixed> $result
	 */
	protected function error_message( array $result ): string {
		$known = array(
			'missing-input-secret'   => esc_html__( 'The secret parameter is missing.', 'piecyfer-core' ),
			'invalid-input-secret'   => esc_html__( 'The secret parameter is invalid or malformed.', 'piecyfer-core' ),
			'missing-input-response' => esc_html__( 'The response parameter is missing.', 'piecyfer-core' ),
			'invalid-input-response' => esc_html__( 'The response parameter is invalid or malformed.', 'piecyfer-core' ),
		);

		foreach ( (array) ( $result['error-codes'] ?? array() ) as $code ) {
			if ( isset( $known[ $code ] ) ) {
				return $known[ $code ];
			}
		}

		return esc_html__( 'Invalid form, reCAPTCHA validation failed.', 'piecyfer-core' );
	}

	/**
	 * @param array<string,mixed> $field
	 */
	protected function add_error( AjaxHandler $ajax_handler, array $field, string $message ): void {
		$ajax_handler->add_error( (string) $field['id'], $message );
	}

	protected function fail_open(): bool {
		/**
		 * Accept submissions when reCAPTCHA cannot be reached at all.
		 *
		 * @param bool $fail_open
		 */
		return (bool) apply_filters( 'piecyfer/forms/recaptcha/fail_open', true );
	}

	protected function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[piecyfer-forms/recaptcha] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
