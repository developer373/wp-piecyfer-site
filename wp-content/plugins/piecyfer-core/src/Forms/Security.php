<?php
/**
 * Request-level helpers: client IP, referrer, nonce, header hygiene.
 *
 * Everything in here exists because Pro's submit endpoint trusts the client for
 * things it should not (06-FORM-SPEC.md §1.3, §3.4, §7).
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class Security {

	/**
	 * The hidden input the widget is expected to print, and the nonce action.
	 */
	public const NONCE_FIELD  = '_pcf_nonce';
	public const NONCE_ACTION = 'piecyfer_form';

	public const NONCE_OFF      = 'off';
	public const NONCE_OPTIONAL = 'optional';
	public const NONCE_STRICT   = 'strict';

	/**
	 * The visitor's IP address.
	 *
	 * DIVERGENCE FROM PRO. `ElementorPro\Core\Utils::get_client_ip()`
	 * (core/utils.php:53-73) walks HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR and four
	 * more request headers *before* REMOTE_ADDR, and returns the first that parses
	 * as an IP. Every one of those headers is attacker-controlled, so on a site
	 * that is not behind a proxy it means the "Remote IP" line in every
	 * notification email, the reCAPTCHA `remoteip` parameter and any rate limit
	 * keyed on IP can be set to anything the sender likes.
	 *
	 * We trust REMOTE_ADDR only. If this site ever moves behind Cloudflare or a
	 * load balancer, add the proxy's address to `piecyfer/forms/trusted_proxies`
	 * and the right-most X-Forwarded-For hop is used instead — which is the only
	 * hop the trusted proxy itself wrote.
	 */
	public static function client_ip(): string {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( ! $remote_addr || ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			return '127.0.0.1';
		}

		/**
		 * Proxy addresses whose X-Forwarded-For header may be believed.
		 *
		 * @param string[] $proxies IP addresses.
		 */
		$trusted = (array) apply_filters( 'piecyfer/forms/trusted_proxies', array() );

		if ( ! in_array( $remote_addr, $trusted, true ) ) {
			return $remote_addr;
		}

		$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
			: '';

		if ( ! $forwarded ) {
			return $remote_addr;
		}

		$hops = array_map( 'trim', explode( ',', $forwarded ) );
		$hop  = (string) end( $hops );

		return filter_var( $hop, FILTER_VALIDATE_IP ) ? $hop : $remote_addr;
	}

	/**
	 * The URL the form was submitted from, or '' when it cannot be trusted.
	 *
	 * `referrer` is appended by the frontend JS from `location.toString()`
	 * (Pro form bundle:220) and lands in the `page_url` meta line unvalidated
	 * (form-record.php:180). We accept it only when it is an absolute URL on this
	 * site's own host, and fall back to the real `Referer` header.
	 */
	public static function referrer_url(): string {
		$candidates = array();

		if ( isset( $_POST[ 'referrer' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- validated below, and the endpoint's nonce policy is Security::nonce_mode().
			$candidates[] = esc_url_raw( wp_unslash( $_POST['referrer'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$candidates[] = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( $candidates as $candidate ) {
			if ( ! $candidate ) {
				continue;
			}

			$host = wp_parse_url( $candidate, PHP_URL_HOST );

			if ( $host && strtolower( (string) $host ) === strtolower( (string) $home_host ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Which post the form was rendered on, derived server side.
	 *
	 * Pro takes `queried_id` straight from the POST body and hands it to
	 * `Plugin::elementor()->db->switch_to_post()` (ajax-handler.php:67-74), which
	 * is what resolves dynamic tags inside the form's own settings — including
	 * `email_to`. No form on this site uses a dynamic tag, so this is a latent
	 * hole rather than an open one, but it costs nothing to close.
	 *
	 * Resolution order:
	 *   1. the post the (same-origin) referrer URL resolves to,
	 *   2. the posted `queried_id`, if it is a real, readable post,
	 *   3. `post_id`.
	 *
	 * @param mixed $posted_queried_id Raw POST value.
	 * @param int   $post_id           The document holding the form.
	 */
	public static function resolve_queried_id( $posted_queried_id, int $post_id ): int {
		$referrer = self::referrer_url();

		if ( $referrer ) {
			$from_url = url_to_postid( $referrer );

			if ( $from_url > 0 ) {
				return $from_url;
			}
		}

		$candidate = is_scalar( $posted_queried_id ) ? absint( $posted_queried_id ) : 0;

		if ( $candidate > 0 && get_post( $candidate ) instanceof \WP_Post ) {
			return $candidate;
		}

		return $post_id;
	}

	/**
	 * Nonce policy.
	 *
	 * `off`      — never checked (Pro's behaviour).
	 * `optional` — checked when the field is present, ignored when it is not.
	 *              This is the default: the widget does not print the field yet
	 *              (that one line belongs to whoever owns src/Widgets), and a
	 *              full-page cache can serve a stale nonce for hours. Rate
	 *              limiting is what carries the load until then.
	 * `strict`   — a missing or stale nonce fails the submission. Only turn this
	 *              on once the field is printed AND the form page is excluded
	 *              from page caching, or you will silently lose enquiries.
	 */
	public static function nonce_mode(): string {
		$mode = (string) apply_filters( 'piecyfer/forms/nonce_mode', self::NONCE_OPTIONAL );

		return in_array( $mode, array( self::NONCE_OFF, self::NONCE_OPTIONAL, self::NONCE_STRICT ), true )
			? $mode
			: self::NONCE_OPTIONAL;
	}

	/**
	 * Whether the request satisfies the current nonce policy.
	 */
	public static function nonce_ok(): bool {
		$mode = self::nonce_mode();

		if ( self::NONCE_OFF === $mode ) {
			return true;
		}

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this IS the nonce check.
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';

		if ( '' === $nonce ) {
			return self::NONCE_OPTIONAL === $mode;
		}

		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * The hidden input the widget should print inside `<form class="elementor-form">`.
	 *
	 * Kept here rather than in FormWidget because the widget is presentation-only
	 * and is owned by another branch. Two ways to get the field onto the page:
	 *
	 *   a. add `<?php echo \PieCyfer\Core\Forms\Security::nonce_field(); ?>` next
	 *      to the other hidden inputs in FormWidget::render_widget() — preferred,
	 *      one line; or
	 *   b. `add_filter( 'piecyfer/forms/inject_nonce', '__return_true' )`, which
	 *      splices it into the rendered widget HTML from
	 *      {@see Module::inject_nonce_field()}.
	 *
	 * Both change the rendered markup, which is why (b) is off by default: the
	 * widget's byte-identical parity check would otherwise start failing.
	 */
	public static function nonce_field(): string {
		return sprintf(
			'<input type="hidden" name="%s" value="%s"/>',
			esc_attr( self::NONCE_FIELD ),
			esc_attr( wp_create_nonce( self::NONCE_ACTION ) )
		);
	}

	/**
	 * Make a value safe to interpolate into a mail header.
	 *
	 * Pro interpolates `email_from`, `email_from_name`, `email_subject`, `Cc` and
	 * `Bcc` into header strings with no filtering (email.php:320-330). All five
	 * accept `[field id="…"]` substitutions and dynamic tags, and on this site
	 * `email_subject` really does embed a client-supplied hidden field
	 * (`[field id="appPageName"]` on the five job forms). `sanitize_text_field`
	 * already strips newlines from that particular path, but relying on a
	 * sanitiser two layers away for header-injection safety is not a control.
	 */
	public static function header_safe( string $value ): string {
		return trim( str_replace( array( "\r", "\n", "\0" ), '', $value ) );
	}
}
