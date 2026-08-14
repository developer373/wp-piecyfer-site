<?php
/**
 * The second Email action.
 *
 * Pro's whole mechanism is one method — every control id gets a `_2` suffix
 * (elementor-pro/modules/forms/actions/email2.php:47-49) — plus two control
 * overrides that live in FormWidget, not here.
 *
 * **It does not run on this site.** All ten `*_2` settings are saved on all nine
 * forms (leftover VamTam demo values pointing at `office@vamtam.com`), but
 * `email2` is not in `submit_actions`, so the dispatcher skips it. It is
 * implemented anyway because the settings round-trip through the widget and an
 * editor can tick the box at any time — at which point silently doing nothing
 * would be the worst outcome.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms\Actions;

use PieCyfer\Core\Forms\FormRecord;

defined( 'ABSPATH' ) || exit;

final class Email2Action extends EmailAction {

	public function get_name(): string {
		return 'email2';
	}

	public function get_label(): string {
		return esc_html__( 'Email 2', 'piecyfer-core' );
	}

	protected function get_control_id( string $control_id ): string {
		return $control_id . '_2';
	}

	/**
	 * Email 2's Reply-To control is a free-text field, not a field picker
	 * (email2.php:33-45 retypes it), so the value is the address itself.
	 *
	 * DIVERGENCE FROM PRO: Pro returns that value unchecked (email2.php:24-26).
	 * The control is dynamic-tag capable, and its output goes straight into a
	 * mail header, so it gets an `is_email()` gate here. The saved value on all
	 * nine forms is a plain address, so nothing changes.
	 *
	 * @param array<string,string> $fields
	 */
	protected function get_reply_to( FormRecord $record, array $fields ): string {
		$candidate = trim( (string) ( $fields['email_reply_to'] ?? '' ) );

		return ( '' !== $candidate && is_email( $candidate ) ) ? $candidate : '';
	}
}
