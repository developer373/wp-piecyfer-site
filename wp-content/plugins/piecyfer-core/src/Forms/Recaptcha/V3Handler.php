<?php
/**
 * reCAPTCHA v3 — the version 7 of the 9 forms on this site use.
 *
 * ---------------------------------------------------------------------------
 * THE KEY PROBLEM, STATED UP FRONT
 * ---------------------------------------------------------------------------
 * On this install `elementor_pro_recaptcha_v3_site_key`,
 * `elementor_pro_recaptcha_v3_secret_key`, `elementor_pro_recaptcha_site_key`
 * and `elementor_pro_recaptcha_secret_key` are all 40 characters and all begin
 * `6LcmQX`. Four keys that share a prefix are, in practice, one key pasted into
 * four boxes. A v2 secret cannot verify a v3 token: Google answers
 * `{"success":true, ...}` with **no `score`**, or `{"success":false,
 * "error-codes":["invalid-input-response"]}`.
 *
 * Pro's `validate_result()` (recaptcha-v3-handler.php:121-125) reads
 * `$result['score']` unguarded. On PHP 8 a missing score is a warning and
 * `null > 0.5` is false, so **every submission on all seven protected forms
 * fails** with "Invalid form, reCAPTCHA validation failed." and nobody can tell
 * a misconfiguration from a bot.
 *
 * So this class distinguishes the three cases:
 *
 *   * verified, score above threshold  → pass;
 *   * verified, score below threshold  → fail, as Pro does (spam);
 *   * **no score at all**              → a configuration fault, not a visitor
 *                                        fault. Logged, `piecyfer/forms/recaptcha_unverified`
 *                                        fires, and the submission is accepted
 *                                        (subject to the same fail-open switch as
 *                                        a transport failure). Set
 *                                        `piecyfer/forms/recaptcha/fail_open` to
 *                                        false to refuse instead.
 *
 * Verify the pair in the Google console before the cut-over. Until then the
 * failure mode is "spam may get through and it is in the log", not "the site
 * stops taking enquiries".
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Recaptcha;

defined( 'ABSPATH' ) || exit;

final class V3Handler extends Handler {

	public const OPTION_SITE_KEY   = 'elementor_pro_recaptcha_v3_site_key';
	public const OPTION_SECRET_KEY = 'elementor_pro_recaptcha_v3_secret_key';
	public const OPTION_THRESHOLD  = 'elementor_pro_recaptcha_v3_threshold';

	public const DEFAULT_THRESHOLD = 0.5;

	/**
	 * Hard-coded in Pro (recaptcha-v3-handler.php:21) and rendered as
	 * `data-action` by FormWidget::render_recaptcha_field(). The two must agree
	 * or every verification fails.
	 */
	public const DEFAULT_ACTION = 'Form';

	public static function get_recaptcha_name(): string {
		return 'recaptcha_v3';
	}

	/**
	 * Threshold from the global option, clamped, 0.5 on anything odd
	 * (recaptcha-v3-handler.php:39-45). It is not a widget control.
	 */
	public static function get_threshold(): float {
		$threshold = (float) get_option( self::OPTION_THRESHOLD, self::DEFAULT_THRESHOLD );

		if ( 0 > $threshold || 1 < $threshold ) {
			return self::DEFAULT_THRESHOLD;
		}

		return $threshold;
	}

	/**
	 * @param array<string,mixed> $result
	 * @param array<string,mixed> $field
	 *
	 * @return bool|string
	 */
	protected function validate_result( array $result, array $field ) {
		if ( empty( $result['success'] ) ) {
			return false;
		}

		if ( ! isset( $result['score'] ) ) {
			$this->log( 'verification succeeded but returned no score — the configured v3 secret is almost certainly a v2 key' );

			/** This action is documented in PieCyfer\Core\Forms\Recaptcha\Handler */
			do_action( 'piecyfer/forms/recaptcha_unverified', 'no-score' );

			return $this->fail_open() ? true : esc_html__( 'reCAPTCHA is not configured correctly. Please contact us directly.', 'piecyfer-core' );
		}

		// Pro's action check: absent means "do not check" (recaptcha-v3-handler.php:123).
		$action_ok = ! isset( $result['action'] ) || self::DEFAULT_ACTION === $result['action'];

		// Strict `>`: a score exactly equal to the threshold fails, as in Pro.
		return $action_ok && ( (float) $result['score'] > self::get_threshold() );
	}

	/**
	 * V3 adds a form-level message as well as the field-keyed one
	 * (recaptcha-v3-handler.php:116-119).
	 *
	 * The field-keyed error never renders: the captcha field has no visible
	 * input, so the JS finds no `#form-field-<id>` node to attach it to. The
	 * form-level message is the only thing the visitor sees.
	 *
	 * @param array<string,mixed> $field
	 */
	protected function add_error( \PieCyfer\Core\Forms\AjaxHandler $ajax_handler, array $field, string $message ): void {
		parent::add_error( $ajax_handler, $field, $message );

		$ajax_handler->add_error_message( esc_html__( 'reCAPTCHA V3 validation failed, suspected as abusive usage', 'piecyfer-core' ) );
	}
}
