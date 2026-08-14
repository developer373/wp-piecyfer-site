/*!
 * piecyfer-theme - site behaviour.
 *
 * PROVENANCE
 * ----------
 * This file replaces `wp_options.vamtam_additional_js`, a 6,164-byte option in
 * three slots (`head` 806 B, `body` 4,885 B, `footer` 473 B) that the companion
 * plugin `vamtam-elementor-integration-tecnologia` echoed **raw and unescaped**
 * into <script> tags (vamtam-elementor-integration.php:222-244). Deleting that
 * plugin deletes the code with it, so it is lifted here, into version control,
 * before the plugin goes.
 *
 * The original was captured verbatim before this rewrite; it is recoverable
 * from the database option and from `vamtam_additional_js_backup_<ts>`.
 *
 * WHAT WAS DROPPED, AND WHY
 * -------------------------
 * Two blocks of the `body` slot are dead against the current markup and are NOT
 * carried. `elementor_experiment-e_optimized_markup` is active, which removes
 * `<div class="elementor-button-wrapper">` from the button widget's output. The
 * rendered markup on all 39 captured pages is:
 *
 *     <div class="... post-btn ... elementor-widget-button">
 *       <div class="elementor-widget-container">
 *         <a class="elementor-button elementor-button-minimal">Request a Meeting</a>
 *
 * i.e. ONE intermediate div, and no `.elementor-button-wrapper`.
 *
 *   1. `.post-btn > div > div.elementor-button-wrapper > a`
 *      click/touchend colour cycle (#242627 -> #3469B3 after 1.5 s),
 *      mouseenter -> addClass('post-btn-hover'), touchstart -> removeClass.
 *      Matches nothing. Correct selector today: `.post-btn > div > a`.
 *
 *   2. `.bookSlot > div > div > a`
 *      click colour cycle (#4EAEB7 -> #ffffff after 1.5 s) plus
 *      addClass('bookSlot-hover'). One div too deep; matches nothing.
 *      Correct selector today: `.bookSlot > div > a`.
 *
 * Both are deliberately left out rather than silently fixed: enabling them
 * would change hover and click behaviour on every button of those two classes
 * at the same moment we are trying to prove the theme swap changed nothing.
 * The Additional CSS still carries the matching `.post-btn-hover:hover` and
 * `.bookSlot-hover:hover` rules and `.post-btn > div > div > a:hover`, which is
 * dead for the same reason. Re-enabling all of it with the corrected selectors
 * is a separate, separately-verified change.
 *
 *   3. `#ekit_modal-popup-0f8c7e2 > div > form > input` -> style.padding.
 *      Dead for a different reason: the `body` slot was printed by the plugin
 *      at `wp_body_open`, and this block ran at parse time - before the
 *      ElementsKit search popup exists further down the document. Confirmed
 *      against the captures: that input carries `style=""`, not the padding.
 *      Every other block in the slot is inside a ready/onload/timeout handler,
 *      which is why they work and this one never did. Not carried; wrapping it
 *      in a ready handler would newly apply 30 px of left padding to the mobile
 *      search field, a visible change.
 *
 * Everything else below is live and was verified present in
 * `_project/snapshots/ref-a/html/` before being carried:
 *   .select-caret-down-wrapper  39/39      #ht-ctc-chat > div > div > div  39/39
 *   input[type=search]          39/39      li.menu-heading...-993772..6    39/39
 *   .elementor-widget-button    39/39      #pie-consult-form               39/39
 *                                          input#author                    15/39
 *
 * Two of those were checked by their *effect* rather than their presence,
 * which is stronger: the captured menu headings carry
 * `style="font-weight: 700; pointer-events: none;"` and the captured search
 * inputs carry `minlength="1" maxlength="60"`, so both handlers demonstrably
 * ran before the snapshot was taken.
 *
 * The original slots ran inline in <head>, immediately after <body>, and in
 * <footer>. They are one deferred file now; every block was already gated on
 * DOMContentLoaded, jQuery(document).ready or window.onload, so the observable
 * order is unchanged. Registration order below preserves head -> body -> footer.
 */
( function () {
	'use strict';

	/* =====================================================================
	 * head slot - replace the select caret SVG.
	 *
	 * The original read `document.querySelector(...)` at the top of <head>,
	 * where <body> does not exist yet, so it threw on every page load and the
	 * throw aborted the rest of that inline block. Fixed during phase 3 to run
	 * on DOM ready and to apply to every match rather than the first; that
	 * fixed version is what is carried here.
	 * ===================================================================== */
	var CARET_SVG =
		'\n    <svg fill="#000000" width="10px" height="10px" viewBox="-6.5 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg">\n' +
		'        <path d="M18.813 11.406l-7.906 9.906c-0.75 0.906-1.906 0.906-2.625 0l-7.906-9.906c-0.75-0.938-0.375-1.656 0.781-1.656h16.875c1.188 0 1.531 0.719 0.781 1.656z"></path>\n' +
		'    </svg>\n';

	function applyCaret() {
		document.querySelectorAll( '.select-caret-down-wrapper' ).forEach( function ( wrapper ) {
			wrapper.innerHTML = CARET_SVG;
		} );
	}

	/* =====================================================================
	 * body slot - search field limits and empty-submit guard.
	 * ===================================================================== */
	function applySearchLimits() {
		jQuery( 'input[type="search"]' ).attr( 'minlength', 1 ).attr( 'maxlength', 60 );

		jQuery( 'form:has(input[type="search"])' ).on( 'submit', function ( e ) {
			if ( jQuery( e.target ).find( 'input[type="search"]' ).val() === '' ) {
				e.preventDefault();
			}
		} );
	}

	/* =====================================================================
	 * body slot - five menu headings are labels, not links.
	 *
	 * The 2000 ms delay is carried over unchanged. It exists because the menu
	 * is rendered by an ElementsKit widget that populates late; shortening it
	 * would make the font-weight change land at a different moment, which is a
	 * visible difference, so it stays until it can be tested on its own.
	 * ===================================================================== */
	var MENU_HEADING_IDS = [ 993772, 993773, 993774, 993775, 993776 ];

	function disableMenuHeadings() {
		var selector = MENU_HEADING_IDS.map( function ( id ) {
			return 'li.menu-heading.menu-item.menu-item-type-custom.menu-item-object-custom.menu-item-' + id + ' > a';
		} ).join( ', ' );

		document.querySelectorAll( selector ).forEach( function ( item ) {
			item.style.fontWeight = '700';
			item.style.pointerEvents = 'none';
		} );
	}

	/* =====================================================================
	 * body slot - client-side form validation.
	 *
	 * Attribute-based only: `pattern`, `title` and `maxlength` on named
	 * Elementor form fields, plus a checkValidity() guard on the consultation
	 * form and on the comment form's submit button. Server-side validation is
	 * unaffected and unchanged; none of this is a security control.
	 * ===================================================================== */
	var TEXT_ONLY_FIELDS = [
		'input[name="form_fields[first_name_consultation_form]"]',
		'input[name="form_fields[last_name_consultation_form]"]',
		'input[name="form_fields[company_organization_consultation_form]"]',
		'input[name="form_fields[contact_us_full_name]"]',
		'input[name="form_fields[application_name]"]',
		'input#author'
	].join( ', ' );

	function applyFormValidation() {
		var $ = jQuery;

		$( TEXT_ONLY_FIELDS ).attr( {
			pattern: '[A-Za-z\\s]+',
			title: 'Please enter only text.',
			maxlength: '30'
		} );

		$( 'input[type="tel"]' ).attr( {
			pattern: '[+0-9]+',
			title: 'Please enter only numbers.',
			maxlength: '20'
		} );

		$( 'input[name="form_fields[application_current_salary]"]' ).attr( {
			pattern: '[0-9]+',
			title: 'Please enter only numbers.',
			maxlength: '7'
		} );

		$( 'input[name="form_fields[application_experience]"]' ).attr( {
			pattern: '[0-9]+',
			title: 'Please enter only numbers.',
			maxlength: '2'
		} );

		$( '#pie-consult-form' ).on( 'submit', function ( e ) {
			if ( ! this.checkValidity() ) {
				e.preventDefault();
				window.alert( 'Please correct the form inputs as per the specified patterns.' );
			}
		} );
	}

	/* =====================================================================
	 * body slot - comment author name validation.
	 *
	 * The original repeated the `input#author` attributes here with a slightly
	 * different `title` ("Please Enter Only Text." vs "Please enter only
	 * text."). The second block ran last, so its casing is what the browser
	 * shows today; that is preserved. Only the submit guard is new work.
	 * ===================================================================== */
	function applyCommentValidation() {
		var $ = jQuery;

		$( 'input#author' ).attr( {
			pattern: '[A-Za-z\\s]+',
			title: 'Please Enter Only Text.',
			maxlength: '30'
		} );

		$( 'input#submit' ).on( 'click', function ( e ) {
			var form = $( this ).closest( 'form' );

			if ( form.length && ! form[ 0 ].checkValidity() ) {
				e.preventDefault();
				window.alert( 'Please correct the form inputs as per the specified patterns.' );
			}
		} );
	}

	/* =====================================================================
	 * body slot - Click to Chat (WhatsApp) button sizing.
	 *
	 * Ran on window.onload in the original, which also meant it clobbered any
	 * other window.onload assignment. Bound as a listener here instead; the
	 * timing is the same, the collision is not.
	 * ===================================================================== */
	function restyleWhatsAppButton() {
		document.querySelectorAll( '#ht-ctc-chat > div > div > div' ).forEach( function ( chatElement ) {
			chatElement.style.padding = '14px';
			chatElement.style.boxShadow = 'none';

			var svg = chatElement.querySelector( 'svg' );

			if ( svg ) {
				svg.style.width = '32px';
				svg.style.height = '32px';
			}
		} );
	}

	/* =====================================================================
	 * footer slot - flatten the Elementor button markup.
	 *
	 * THIS ONE IS VISIBLE ON EVERY PAGE. It is the reason every captured page
	 * shows `<a class="elementor-button elementor-button-minimal">Text</a>`
	 * with no inner spans, and the reason `elementor-button-minimal` exists
	 * nowhere in any file under wp-content.
	 *
	 * `themes/tecnologia/functions.php:85-88` registered a PHP filter on
	 * `elementor/widget/button/template_content` that looks like it does this
	 * job. That hook does not exist in Elementor or Elementor Pro and never
	 * fires; the filter is a no-op and is deliberately not ported. The work is
	 * done here, client-side, and always has been.
	 * ===================================================================== */
	function flattenButtons() {
		jQuery( '.elementor-widget-button' ).each( function () {
			var $button = jQuery( this ).find( '.elementor-button' );

			$button
				.removeClass( 'elementor-button-link elementor-size-sm' )
				.addClass( 'elementor-button-minimal' );

			$button.html( $button.find( '.elementor-button-text' ).text() );
		} );
	}

	/* ===================================================================== */

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', applyCaret );
	} else {
		applyCaret();
	}

	jQuery( function () {
		applySearchLimits();
		applyFormValidation();
		applyCommentValidation();
		flattenButtons();
	} );

	window.setTimeout( disableMenuHeadings, 2000 );

	if ( document.readyState === 'complete' ) {
		restyleWhatsAppButton();
	} else {
		window.addEventListener( 'load', restyleWhatsAppButton );
	}
} )();
