<?php
/**
 * Thrown by AjaxHandler::send() to unwind the pipeline.
 *
 * Pro's `Ajax_Handler::send()` calls `wp_send_json_*`, which calls `wp_die()`,
 * which is why `->add_error_message( … )->send()` genuinely stops the request
 * mid-method. Reproducing that control flow with an exception instead of a
 * `die()` keeps the same shape while making the whole pipeline callable from a
 * test harness that must not exit.
 *
 * It is an \Exception rather than an \Error so that a third-party action calling
 * `$ajax_handler->send()` behaves sanely — the dispatch loop re-throws this type
 * instead of swallowing it as an action failure.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class HaltSubmission extends \Exception {
}
