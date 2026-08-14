<?php
/**
 * `tel` — 17 fields on this site, the most-used validated type.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Fields;

use PieCyfer\Core\Forms\AjaxHandler;
use PieCyfer\Core\Forms\FormRecord;

defined( 'ABSPATH' ) || exit;

final class TelField extends FieldBase {

	public function get_type(): string {
		return 'tel';
	}

	/**
	 * Verbatim from Pro (fields/tel.php:30-37), including the message.
	 *
	 * The pattern is also what the widget prints as the input's `pattern`
	 * attribute, so client and server agree. Note the character class is Pro's
	 * exactly: inside it, `*-=` is a range (`*`, `+`, `,`, `-`, `.`, `/`, digits
	 * up to `=`), which is why `,` and `/` are accepted by a "numbers only"
	 * field. Changing it would reject phone numbers that are accepted today.
	 *
	 * @param array<string,mixed> $field
	 */
	public function validation( $field, FormRecord $record, AjaxHandler $ajax_handler ): void {
		$value = (string) ( $field['value'] ?? '' );

		if ( '' === $value ) {
			return;
		}

		if ( 1 !== preg_match( '/^[0-9()#&+*-=.]+$/', $value ) ) {
			$ajax_handler->add_error(
				(string) $field['id'],
				esc_html__( 'The field accepts only numbers and phone characters (#, -, *, etc).', 'piecyfer-core' )
			);
		}
	}
}
