/**
 * Frontend handler for the `form` widget.
 *
 * Replaces Elementor Pro's forms frontend bundle (form.bundle.js).
 * Handles client-side submission, validation, reCAPTCHA v3 token generation,
 * AJAX dispatch to admin-ajax.php, and message/redirect handling.
 *
 * @package PieCyfer\Core
 */

( function ( window, $ ) {
	'use strict';

	if ( ! window.piecyferFrontend ) {
		return;
	}

	window.piecyferFrontend.register( 'form', function () {
		return elementorModules.frontend.handlers.Base.extend( {
			getDefaultSettings: function () {
				return {
					selectors: {
						form: '.elementor-form',
						submitButton: '.elementor-size-md, button[type="submit"], input[type="submit"]',
						fields: '.elementor-field-textual, .elementor-field-group input, .elementor-field-group textarea, .elementor-field-group select',
						messagesContainer: '.elementor-message',
						recaptchaV3: '.elementor-g-recaptcha[data-type="v3"]'
					},
					classes: {
						loading: 'elementor-loading',
						message: 'elementor-message',
						messageSuccess: 'elementor-message-success',
						messageDanger: 'elementor-message-danger',
						fieldError: 'elementor-error',
						helpInline: 'elementor-help-inline'
					}
				};
			},

			getDefaultElements: function () {
				var selectors = this.getSettings( 'selectors' );
				var elements  = {
					$form: this.$element.find( selectors.form )
				};

				elements.$submitButton = elements.$form.find( selectors.submitButton );
				elements.$fields       = elements.$form.find( selectors.fields );

				return elements;
			},

			bindEvents: function () {
				var self = this;

				if ( ! self.elements.$form.length ) {
					return;
				}

				self.elements.$form.on( 'submit', function ( event ) {
					event.preventDefault();
					self.handleSubmit();
				} );
			},

			handleSubmit: function () {
				var self        = this;
				var $form       = self.elements.$form;
				var settings    = self.getSettings();
				var $recaptcha  = $form.find( settings.selectors.recaptchaV3 );
				var siteKey     = $recaptcha.data( 'sitekey' );
				var action      = $recaptcha.data( 'action' ) || 'Form';

				self.clearMessages();

				if ( $recaptcha.length && siteKey && window.grecaptcha && window.grecaptcha.execute ) {
					window.grecaptcha.ready( function () {
						window.grecaptcha.execute( siteKey, { action: action } )
							.then( function ( token ) {
								self.sendAjax( token );
							} )
							['catch']( function ( err ) {
								if ( window.console && window.console.warn ) {
									window.console.warn( '[piecyfer-form] reCAPTCHA execution error:', err );
								}
								// Fallback: send without token; server fail-open policy handles it safely.
								self.sendAjax( '' );
							} );
					} );
				} else {
					self.sendAjax( '' );
				}
			},

			sendAjax: function ( recaptchaToken ) {
				var self      = this;
				var $form     = self.elements.$form;
				var $button   = self.elements.$submitButton;
				var classes   = self.getSettings( 'classes' );
				var formData  = new FormData( $form[ 0 ] );

				formData.append( 'action', 'elementor_pro_forms_send_form' );
				formData.append( 'referrer', window.location.toString() );

				if ( recaptchaToken ) {
					formData.set( 'g-recaptcha-response', recaptchaToken );
				}

				var ajaxUrl = ( window.ElementorProFrontendConfig && window.ElementorProFrontendConfig.ajaxurl )
					|| ( window.piecyferFrontendConfig && window.piecyferFrontendConfig.ajaxurl )
					|| '/wp-admin/admin-ajax.php';

				$button.addClass( classes.loading );
				$form.find( 'input, textarea, select, button' ).prop( 'disabled', true );

				$.ajax( {
					url: ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: formData,
					processData: false,
					contentType: false,
					success: function ( response ) {
						$button.removeClass( classes.loading );
						$form.find( 'input, textarea, select, button' ).prop( 'disabled', false );

						if ( response && response.success ) {
							self.onSuccess( response );
						} else {
							self.onError( response );
						}
					},
					error: function ( jqXHR, textStatus, errorThrown ) {
						$button.removeClass( classes.loading );
						$form.find( 'input, textarea, select, button' ).prop( 'disabled', false );

						self.showError( 'An error occurred while submitting the form. Please try again later.' );
					}
				} );
			},

			onSuccess: function ( response ) {
				var self     = this;
				var $form    = self.elements.$form;
				var classes  = self.getSettings( 'classes' );
				var message  = ( response.data && response.data.message ) ? response.data.message : 'Your submission was successful.';

				self.clearMessages();
				$form.trigger( 'reset' );

				var $msg = $( '<div>' )
					.addClass( classes.message + ' ' + classes.messageSuccess )
					.html( message );

				$form.append( $msg );
				$form.trigger( 'submit_success', response );

				if ( response.data && response.data.data && response.data.data.redirect_url ) {
					window.location.href = response.data.data.redirect_url;
				}
			},

			onError: function ( response ) {
				var self    = this;
				var $form   = self.elements.$form;
				var classes = self.getSettings( 'classes' );
				var message = ( response.data && response.data.message ) ? response.data.message : 'There was an error with your submission.';

				self.clearMessages();

				if ( response.data && response.data.errors ) {
					$.each( response.data.errors, function ( fieldId, errorText ) {
						var $field = $form.find( '[name="form_fields[' + fieldId + ']"], #' + fieldId );
						$field.addClass( classes.fieldError );

						var $help = $( '<span class="' + classes.helpInline + ' ' + classes.fieldError + '">' )
							.text( errorText );
						$field.closest( '.elementor-field-group' ).append( $help );
					} );
				}

				var $msg = $( '<div>' )
					.addClass( classes.message + ' ' + classes.messageDanger )
					.html( message );

				$form.append( $msg );
				$form.trigger( 'submit_error', response );
			},

			showError: function ( text ) {
				var self    = this;
				var $form   = self.elements.$form;
				var classes = self.getSettings( 'classes' );

				self.clearMessages();

				var $msg = $( '<div>' )
					.addClass( classes.message + ' ' + classes.messageDanger )
					.text( text );

				$form.append( $msg );
			},

			clearMessages: function () {
				var self     = this;
				var $form    = self.elements.$form;
				var settings = self.getSettings();

				$form.find( settings.selectors.messagesContainer ).remove();
				$form.find( '.' + settings.classes.fieldError ).removeClass( settings.classes.fieldError );
				$form.find( '.' + settings.classes.helpInline ).remove();
			}
		} );
	} );
}( window, jQuery ) );
