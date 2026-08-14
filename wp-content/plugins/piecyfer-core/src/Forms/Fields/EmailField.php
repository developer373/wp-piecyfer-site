<?php
/**
 * `email` — 9 fields on this site, and until now completely unvalidated.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Fields;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;

defined( 'ABSPATH' ) || exit;

final class EmailField extends FieldBase {

	public function get_type(): string {
		return 'email';
	}

	/**
	 * DIVERGENCE FROM PRO: Pro has **no email field class at all**. The only
	 * server-side handling an `email` value gets is `sanitize_email()` in
	 * Form_Record (form-record.php:277-279), and `sanitize_email()` does not
	 * reject — it strips. `no-at-sign` sanitises to `no-at-sign` and is mailed
	 * on. The browser's `type="email"` is the entire gate, and it is not present
	 * for anything posting straight to admin-ajax.
	 *
	 * Two consequences of adding the check, both intended:
	 *
	 *  * A submission whose email is unusable now fails with an inline error
	 *    instead of arriving as an enquiry nobody can answer.
	 *  * `email_reply_to` (Email action) already required `is_email()` before it
	 *    would use a value, so the address in the body and the address in the
	 *    header can no longer disagree.
	 *
	 * The raw value decides whether the field was filled in; the sanitised value
	 * decides whether what was filled in is usable. That distinction matters:
	 * `sanitize_email( 'not-an-email' )` returns `''`, so checking only the
	 * sanitised value would see an empty field, skip the check, and the required
	 * check would see a non-empty raw value and skip too — the submission would
	 * go through with a blank email address. That is exactly what happens today.
	 *
	 * @param array<string,mixed> $field
	 */
	public function validation( $field, FormRecord $record, AjaxHandler $ajax_handler ): void {
		$raw   = $field['raw_value'] ?? '';
		$raw   = is_array( $raw ) ? implode( ', ', $raw ) : (string) $raw;
		$value = (string) ( $field['value'] ?? '' );

		if ( '' === trim( $raw ) ) {
			// Empty is the required check's business, not ours.
			return;
		}

		if ( '' === $value || ! is_email( $value ) ) {
			$ajax_handler->add_error(
				(string) $field['id'],
				esc_html__( 'Please enter a valid email address.', 'piecyfer-core' )
			);
		}
	}

	/**
	 * Never reached — Form_Record sanitises `email` in its own switch, before the
	 * filter this would hang off. Declared so the behaviour is explicit rather
	 * than inherited from FieldBase's sanitize_text_field().
	 *
	 * @param mixed               $value
	 * @param array<string,mixed> $field
	 */
	public function sanitize_field( $value, $field ) {
		return sanitize_email( (string) $value );
	}
}
