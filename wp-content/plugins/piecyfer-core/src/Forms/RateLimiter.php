<?php
/**
 * A small IP + form rate limit for the public submit endpoint.
 *
 * Pro has none (06-FORM-SPEC.md §1.3): `wp_ajax_nopriv_*`, no nonce, no captcha
 * on two of the nine forms, and an upload path that writes to disk. That is a
 * free anonymous file host and a mail-relay amplifier.
 *
 * DIVERGENCE FROM PRO, and the only one that can refuse a submission a human
 * actually made. The limits below are deliberately loose — a visitor who fills a
 * form in, gets a validation error, fixes it and resubmits four times still gets
 * through. Anything caught by this is bulk.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class RateLimiter {

	private const PREFIX = 'pcf_frl_';

	/**
	 * Attempts allowed per IP, per form, per window.
	 */
	private const FORM_LIMIT  = 10;
	private const FORM_WINDOW = 600; // 10 minutes.

	/**
	 * Attempts allowed per IP across every form, per window.
	 */
	private const IP_LIMIT  = 30;
	private const IP_WINDOW = 3600; // 1 hour.

	/**
	 * Whether the limiter runs at all.
	 *
	 * Off in tests, and off for signed-in users who can edit content — an editor
	 * hammering a form to test it is not the threat model.
	 */
	public static function is_enabled(): bool {
		if ( current_user_can( 'edit_posts' ) ) {
			return false;
		}

		return (bool) apply_filters( 'piecyfer/forms/rate_limit/enabled', true );
	}

	/**
	 * Record this attempt and report whether it is over the limit.
	 *
	 * Counts *attempts*, not successes: a script that fails validation 500 times
	 * is exactly what we want to stop.
	 *
	 * Storage is transients. With no persistent object cache on this install that
	 * means two autoloaded-off option rows per active IP, cleaned up by WordPress
	 * when they expire. If a bot pool is large enough for that to become the
	 * problem, move the counter to a dedicated table — but that is a scale we are
	 * nowhere near.
	 *
	 * @param string $form_id The Elementor element id of the form.
	 */
	public static function too_many( string $form_id ): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$ip = Security::client_ip();

		/**
		 * Per-IP, per-form limit and window.
		 *
		 * @param array{limit:int,window:int} $config
		 */
		$form_config = (array) apply_filters(
			'piecyfer/forms/rate_limit/form',
			array(
				'limit'  => self::FORM_LIMIT,
				'window' => self::FORM_WINDOW,
			),
			$form_id,
			$ip
		);

		/**
		 * Per-IP limit and window across all forms.
		 *
		 * @param array{limit:int,window:int} $config
		 */
		$ip_config = (array) apply_filters(
			'piecyfer/forms/rate_limit/ip',
			array(
				'limit'  => self::IP_LIMIT,
				'window' => self::IP_WINDOW,
			),
			$ip
		);

		$over_form = self::bump( 'f_' . md5( $ip . '|' . $form_id ), (int) $form_config['window'] ) > (int) $form_config['limit'];
		$over_ip   = self::bump( 'i_' . md5( $ip ), (int) $ip_config['window'] ) > (int) $ip_config['limit'];

		return $over_form || $over_ip;
	}

	/**
	 * Increment a counter and return its new value.
	 *
	 * Not atomic. Two simultaneous requests can both read 5 and both write 6, so
	 * the effective limit is fuzzy under concurrency. That is acceptable for a
	 * throttle whose job is to turn thousands of requests into tens; it is not
	 * acceptable for anything that must count exactly, so do not reuse this for
	 * one.
	 */
	private static function bump( string $key, int $window ): int {
		$transient = self::PREFIX . $key;
		$count     = (int) get_transient( $transient );
		++$count;

		set_transient( $transient, $count, $window );

		return $count;
	}

	/**
	 * The message a throttled visitor sees.
	 *
	 * Deliberately vague and deliberately not a field error: it must not tell a
	 * bot which limit it hit, and it must not look like a validation failure.
	 */
	public static function message(): string {
		return esc_html__( 'Too many submissions from this address. Please wait a few minutes and try again.', 'piecyfer-core' );
	}
}
