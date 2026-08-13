// Contains logic related to elementor.
( function( $, undefined ) {
	"use strict";

	window.VAMTAM_FRONT.elementor = window.VAMTAM_FRONT.elementor || {};

	var VAMTAM_ELEMENTOR = {
		domLoaded: function () {
			this.VamtamMainNavHandler.init();
			this.VamtamActionLinksHandler.init();
			this.VamtamPopupsHandler.disablePopupAutoFocus();

			if ( elementorFrontend.config.experimentalFeatures.e_optimized_css_loading ) {
				this.VamtamImprovedCSSLoadingHandler.init();
			}
		},
		pageLoaded: function () {
			this.VamtamPopupsHandler.init();
		},
		// Handles Popup-related stuff.
		VamtamPopupsHandler: {
			init: function () {
				this.searchFormPopupsGetFocus();
				this.absolutelyPositionedPopups();
				this.alignedWithSelectorPopups();
				this.enablePageTransitionsForPopupLinks();
				this.lateInit();
			},
			lateInit: function () {
				setTimeout( () => {
					this.hoverActivatedPopups();
				}, 50 );
			},
			searchFormPopupsGetFocus: function () {
				// Based on theme setting, when a popup containing a search form is opened, the search field is focused.
				if ( ! window.elementorFrontendConfig.kit.vamtam_theme_search_form_popups_get_focus) {
					return
				}

				elementorFrontend.elements.$window.on( 'elementor/popup/show', ( event ) => {
					if ( ! event.originalEvent ) {
						return;
					}

					const id = event.originalEvent.detail.id,
						instance = event.originalEvent.detail.instance;

						if ( ! id || ! instance ) {
							return; // Invalid.
						}

					const popupHasSearchForm = instance.$element.find( 'form[role="search"]' ).length;
					if ( popupHasSearchForm ) {
						const $input = instance.$element.find( 'input[type="search"]' ).first();
						$input.focus();
					}
				} );
			},
			absolutelyPositionedPopups: function () {
				const cache = [];
				// Popups that need to retain their initial position regardless of page scroll.
				elementorFrontend.elements.$window.on( 'elementor/popup/show', ( event ) => {
					if ( ! event.originalEvent ) {
						return;
					}

					const id = event.originalEvent.detail.id,
						instance = event.originalEvent.detail.instance;

					if ( ! id || ! instance || cache.includes( id ) ) {
						return; // Handled or invalid.
					}

					const isAbsPositioned = instance.$element.filter( '[data-elementor-settings*="vamtam_abs_pos"]' ).length;
					if ( isAbsPositioned ) {
						instance.$element.closest(`#elementor-popup-modal-${id}`).addClass( 'vamtam-abs-pos' );
						cache.push( id );
					}
				} );
			},
			alignedWithSelectorPopups: function () {
				const cache = [];

				const resetCache = () => {
					cache.length = 0;
				};

				// Popups that are aligned with their selector (trigger) element.
				elementorFrontend.elements.$window.on( 'elementor/popup/show', ( event ) => {
					if ( ! event.originalEvent ) {
						return;
					}

					const id = event.originalEvent.detail.id,
						instance = event.originalEvent.detail.instance;

					if ( ! id || ! instance || cache.includes( id ) ) {
						return; // Handled or invalid..
					}

					const $alignedEl = instance.$element.filter( '[data-elementor-settings*="vamtam_align_with_selector"]' );

					if ( $alignedEl.length ) {
						const dialog = instance.$element.closest(`.dialog-widget-content`),
							selector = $alignedEl.data('elementorSettings')['open_selector'],
							selectorEl = selector && $( `${selector}:visible` );

						if ( ! selectorEl.length ) {
							return;
						}

						const selectorOffset = selectorEl[0].getBoundingClientRect();

						window.requestAnimationFrame( () => {
							dialog.css( {
								top: selectorOffset[ 'bottom' ] + 'px',
								left: selectorOffset[ 'left' ] + 'px',
							} );
						} );

						// Add to cache.
						cache.push( id );
					}
				} );

				// Resize event
				window.removeEventListener( 'resize', resetCache );
				window.addEventListener( 'resize', resetCache, true );
			},
			disablePopupAutoFocus: function () {
				// Update to elementor pro 3.11.2 results in accidentally focused links within popups.
				elementorFrontend.elements.$window.on( 'elementor/popup/show elementor/popup/hide', ( event ) => {
					setTimeout( () => { // For popups with entry animations.
						if ( document.activeElement.nodeName !== 'input' ) {
							document.activeElement.blur();
						}
					}, 100 );
				} );
			},
			enablePageTransitionsForPopupLinks() {
				if ( ! elementorFrontend.config.experimentalFeatures['page-transitions'] ) {
					return;
				}

				let $pageTransitionEl = jQuery('e-page-transition');

				if ( $pageTransitionEl.length ) {
					$pageTransitionEl = $pageTransitionEl[0];
				} else {
					return;
				}

				const triggers = $pageTransitionEl.getAttribute('triggers');

				if ( triggers ) {
					return; // User has selected custom triggers for the page transition.
				}

     			const selector = '.elementor-popup-modal a:not( [data-elementor-open-lightbox="yes"] )',
					onLinkClickHandler = $pageTransitionEl.onLinkClick;

				if ( ! onLinkClickHandler ) {
					return;
				}

				// Re-route click event to PageTransitions onClick handler.
				jQuery( document ).on( 'click', selector, onLinkClickHandler.bind( $pageTransitionEl ) );
			},
			hoverActivatedPopups: function () {
				// Frontend Only.
				if ( window.elementorFrontend.isEditMode() ) {
					return;
				}

				// Get popups.
				const popups = this.utils.getPopups();

				if ( ! popups.length ) {
					return;
				}

				// Get hover activated popups.
				const hoverActivatedPopups = [];
				popups.forEach( popup  => {
					const isHoverActivated = popup.instance.getDocumentSettings()[ 'vamtam_open_on_selector_hover' ];

					if ( isHoverActivated ) {
						const openSelector = popup.instance.getDocumentSettings()[ 'open_selector' ],
							closeOnHoverLost = popup.instance.getDocumentSettings()[  'vamtam_close_on_hover_lost' ];

						hoverActivatedPopups.push( {
							id: parseInt( popup.id ),
							selector: openSelector,
							instance: popup.instance,
							isHovering: false,
							selectorIsHovering: false,
							closeOnHoverLost,
							modal: popup.instance.getModal(),
							showPopup: function() {
								this.instance.showModal();

								// update to elementor pro 3.11.2 results in accidentally focused links within popups
								if ( document.activeElement.nodeName !== 'input' ) {
									document.activeElement.blur();
								}
							},
							hidePopup: function() {
								this.modal.hide();
							},
							isVisible: function() {
								return this.modal.isVisible();
							}
						} );
					}
				} );

				if ( ! hoverActivatedPopups.length ) {
					return;
				}

				function getHoverActivatedPopupBySelector( selector ) {
					const $selector = jQuery( selector );

					if ( ! $selector.length ) {
						return;
					}

					let popupToReturn;

					hoverActivatedPopups.forEach( popup => {
						if ( ! popupToReturn ) {
							if (  $selector.filter( popup.selector ).length ) {
								popupToReturn = popup;
							}
						}
					} );

					return popupToReturn;
				}

				function getHoverActivatedPopupById( id ) {
					let popupToReturn;

					hoverActivatedPopups.forEach( popup => {
						if ( ! popupToReturn ) {
							if (  popup.id === id ) {
								popupToReturn = popup;
							}
						}
					} );

					return popupToReturn;
				}

				function isValidMouseleave( el, context ) {
					// Invalid mouseleaves that we dont want to react to:

					if ( context === 'open_selector' ) {
						// 1 - Hover moved from selector to some child of the selector.
						if ( jQuery( el ).find( '*:hover' ).length ) {
							return false;
						}
					}

					return true
				}

				// Attach event listeners
				hoverActivatedPopups.forEach( popup => {

					// Open selector - mouseenter
					jQuery( popup.selector ).on( 'mouseenter', function() {
						const popup = getHoverActivatedPopupBySelector( this );

						if ( popup ) {
							popup.selectorIsHovering = true;

							if ( ! popup.isVisible() ) {
								popup.showPopup();
							}
						}
					} );

					// Popup - mouseenter
					jQuery( document.body ).on( 'mouseenter', `#elementor-popup-modal-${popup.id} [data-elementor-id="${popup.id}"]`, function() {
						const pId = parseInt( jQuery( this ).data( 'elementorId' ) );

						if ( Number.isInteger( pId ) ) {
							const popup = getHoverActivatedPopupById( pId );

							if ( popup ) {
								popup.isHovering = true;
							}

						}
					} );

					if ( popup.closeOnHoverLost ) {
						// Open selector - mouseleave
						jQuery( popup.selector ).on( 'mouseleave', function() {
							setTimeout( () => {
								if ( ! isValidMouseleave( this, 'open_selector' ) ) {
									return;
								}

								const popup = getHoverActivatedPopupBySelector( this );

								if ( popup ) {
									popup.selectorIsHovering = false;

									if ( popup.isVisible() && ! popup.isHovering ) {
										// Selector is not hovering. If popup is also not hovering then hide the popup.
										popup.hidePopup();
									}
								}
							}, 200 );
						} );

						// Popup - mouseleave
						jQuery( document.body ).on( 'mouseleave', `#elementor-popup-modal-${popup.id} [data-elementor-id="${popup.id}"]`, function() {
							setTimeout( () => {
								// if ( ! isValidMouseleave( this, 'popup' ) ) {
								// 	return;
								// }

								const pId = parseInt( jQuery( this ).data( 'elementorId' ) );

								if ( Number.isInteger( pId ) ) {
									const popup = getHoverActivatedPopupById( pId );

									if ( popup ) {
										popup.isHovering = false;

										if ( ! popup.selectorIsHovering ) {
											// Popup is not hovering. If selector is also not hovering then hide the popup.
											popup.hidePopup();
										}
									}

								}
							}, 200 );
						} );
					}
				} );
			},
			utils: {
				getPopups: function() {
					const docs = elementorFrontend.documentsManager.documents,
						popups = [];

					Object.entries( docs ).forEach( ([ docId, doc ]) => {
						if ( doc.initModal ) {

							if ( ! doc.getModal ) {
								// Not inited yet. This happens (weirdly) only when browsing on incognito. We need getModal(), so we init it here.
								doc.initModal();
							}

							popups.push( { id: docId, instance: doc } );
						}
					} );

					return popups;
				},
			}
		},
		// Hanldes issues related to the main na menu.
		VamtamMainNavHandler: {
			init: function() {
				this.fixMenuDrodownScrolling();
			},
			fixMenuDrodownScrolling: function () {
				var $mainMenuDropdown = $( '.elementor-location-header .elementor-nav-menu--dropdown-tablet .elementor-nav-menu--dropdown.elementor-nav-menu__container' ).first();
				var menuToggle        = $mainMenuDropdown.siblings( '.elementor-menu-toggle' )[ 0 ];

				if ( ! $mainMenuDropdown.length || ! menuToggle.length ) {
					return;
				}

				var onMenuToggleClick = function () {
					if ( $( menuToggle ).hasClass( 'elementor-active' ) ) {
						// For safari we substract an additional 100px to account for the bottom action-bar (different size per iOS version). Uggh..
						var height = $( 'html' ).hasClass( 'ios-safari' ) ? $mainMenuDropdown[ 0 ].getBoundingClientRect().top + 100 : $mainMenuDropdown[ 0 ].getBoundingClientRect().top;
						$mainMenuDropdown.css( 'max-height', 'calc(100vh - ' + height + 'px)' );
						menuToggle.removeEventListener( 'click', onMenuToggleClick );
					}
				}
				menuToggle.addEventListener( 'click', onMenuToggleClick );
			},
		},
		// Handles funcionality regarding action-linked popups.
		VamtamActionLinksHandler: {
			init: function() {
				this.popupActionLinks();
			},
			popupActionLinks: function() {
				var _self               = this,
					prevIsBelowMax      = window.VAMTAM.isBelowMaxDeviceWidth(),
					alignedPopups       = [];

				var handleAlignWithParent = function( popupId, popupParent, clearPrevPos ) {
					var popupWrapper   = $( '#elementor-popup-modal-' + popupId ),
						popup          = $( popupWrapper ).find( '.dialog-widget-content' ),
						adminBarHeight = window.VAMTAM.adminBarHeight;

					if ( ! popup.length || ! popupParent ) {
						return;
					}

					var parentPos = popupParent.getBoundingClientRect();

					if ( clearPrevPos ) {
						$( popup ).css( {
							top: '',
							left: '',
						} );
					} else {
						$( popup ).css( {
							top: parentPos.bottom - adminBarHeight,
							left: parentPos.left,
						} );
						// After the popup is hidden we unset top/left.
						( function () { // IIFE for closure so popup, popupWrapper are available.
							// Visibity check.
							var visibilityCheck = setInterval( function() {
								if ( $( popupWrapper ).css( 'display' ) === 'none' ) {
									$( popup ).css( {
										top: '',
										left: '',
									} );
									clearInterval( visibilityCheck );
								}
							}, 500 );
						})();
					}
				};

				var repositionAlignedPopups = function ( clear ) {
					alignedPopups.forEach( function( popup ) {
						if ( clear ) {
							handleAlignWithParent( popup.id, popup.parent, true );
						} else {
							handleAlignWithParent( popup.id, popup.parent );
						}
					} );
				};

				var popupResizeHandler = function () {
					var isBelowMax = window.VAMTAM.isBelowMaxDeviceWidth();
					if ( prevIsBelowMax !== isBelowMax) {
						// We changed breakpoint (max/below-max).
						if ( isBelowMax ) {
							// Clear popup vals set from desktop.
							repositionAlignedPopups( true );
						} else {
							repositionAlignedPopups();
						}
						prevIsBelowMax = isBelowMax;
					} else if ( ! isBelowMax ) {
						repositionAlignedPopups();
					}
				};

				var popupScrollHandler = function () {
					requestAnimationFrame( function() {
						repositionAlignedPopups();
					} );
				};

				var storePopup = function ( popupId, popupParent ) {
					// If exists, update parent, otherwise store.
					// A popup can have multiple parents. We only care for the last parent that triggers it each time.
					var done;

					alignedPopups.forEach( function( popup ) {
						if ( popup.id === popupId ) {
							popup.parent = popupParent;
							done = true;
						}
					} );

					if ( ! done ) {
						alignedPopups.push( {
							id: popupId,
							parent: popupParent,
						} );
					}
				}

				function checkForStickyParent( popupParent ) {
					const parentEl = $( popupParent ).parents('.elementor-element.elementor-widget')[0];
					if ( ! parentEl ) {
						return;
					}

					let parentElSettings = $( parentEl ).attr( 'data-settings' );
					if ( ! parentElSettings ) {
						return;
					}

					try {
						parentElSettings = JSON.parse( parentElSettings );
					} catch (error) {
						return;
					}

					const hasStickyParent = parentElSettings.sticky;
					if ( hasStickyParent ) {
						window.removeEventListener( 'scroll', popupScrollHandler );
						window.addEventListener( 'scroll', popupScrollHandler, { passive: true } );
					}
				}

				var checkAlignWithParent = function( e ) {
					var actionLink = $( e.currentTarget ).attr( 'href' );
					if ( ! actionLink ) {
						return;
					}

					var settings = _self.utils.getSettingsFromActionLink( actionLink );
					if ( settings && settings.align_with_parent ) {

						storePopup( settings.id, e.currentTarget );

						if ( window.VAMTAM.isMaxDeviceWidth() ) {
							// Desktop
							handleAlignWithParent( settings.id, e.currentTarget );
						}

						window.removeEventListener( 'resize', popupResizeHandler );
						window.addEventListener( 'resize', popupResizeHandler, false );

						// This is for following the parent's scroll (for sticky parents).
						checkForStickyParent( e.currentTarget );
					}
				};

				elementorFrontend.elements.$document.on( 'click', 'a[href^="#elementor-action"]', checkAlignWithParent );
			},
			utils: {
				getSettingsFromActionLink: function( url ) {
					url = decodeURIComponent( url );

					if ( ! url ) {
						return;
					}

					var settingsMatch = url.match( /settings=(.+)/ );

					var settings = {};

					if ( settingsMatch ) {
						settings = JSON.parse( atob( settingsMatch[ 1 ] ) );
					}

					return settings;
				},
				getActionFromActionLink: function( url ) {
					url = decodeURIComponent( url );

					if ( ! url ) {
						return;
					}

					var actionMatch = url.match( /action=(.+?)&/ );

					if ( ! actionMatch ) {
						return;
					}

					var action = actionMatch[ 1 ];

					return action;
				}
			}
		},
		VamtamWidgetsHandler: {
			// Checks if there is an active mod (master toggle) for a widget.
			isWidgetModActive: ( widgetName ) => {
				if ( ! widgetName ) {
					return false;
				}

				const siteSettings = elementorFrontend.getKitSettings(),
					themePrefix    = 'vamtam_theme_',
					widgetModsList = window.VAMTAM_FRONT.widget_mods_list;

				// Do we have a master toggle for this widget?
				if ( ! widgetModsList[ widgetName ] ) {
					return false;
				}

				if ( siteSettings[ `${themePrefix}enable_all_widget_mods` ] === 'yes' ) {
					// All theme widget mods enabled by user pref.
					return true;
				} else if ( siteSettings[ `${themePrefix}disable_all_widget_mods` ] === 'yes' ) {
						// All theme widget mods disabled by user pref.
						return false;
				} else {
					// User pref for current widget.
					return siteSettings[ `${themePrefix}${widgetName}` ] === 'yes';
				}
			},
		},
		VamtamImprovedCSSLoadingHandler: {
			init: () => {
				const widgetData = { ...VAMTAM_FRONT.widgets_assets_data }; // Added from server.


				if ( ! widgetData ) {
					return; // Nothing to do.
				} else {
					// So the paths are not displayed.
					$( '#vamtam-all-js-after' ).remove(); // Could be problematic if we need to add more inline stuff (wp_add_inline_script) to "vamtam-all" script.
					delete( VAMTAM_FRONT.widgets_assets_data );
				}

				let out = '';
				Object.keys(widgetData).forEach( widget => {
					const content = widgetData[ widget ][ 'content' ],
						widgetSelector = `.elementor-widget-${widget} > .elementor-widget-container`;

					if ( content ) {
						if ( $( `${widgetSelector} > style, ${widgetSelector} > link`).length ) {
							return; // Inline style has been added for widget.
						}

						// Add to fallback.
						out = out + '\r\n' + content;
					}
				});

				// Add fallback inline CSS to DOM.
				if ( out ) {
					$( 'body' ).append( `<div id="vamtam-css-opt-fallback">${out}</div>` );
				}
			}
		},
	}

	window.VAMTAM_FRONT.elementor.urlActions = VAMTAM_ELEMENTOR.VamtamActionLinksHandler.utils;
	window.VAMTAM_FRONT.elementor.popups = VAMTAM_ELEMENTOR.VamtamPopupsHandler.utils;
	window.VAMTAM_FRONT.elementor.widgets = {
		isWidgetModActive: VAMTAM_ELEMENTOR.VamtamWidgetsHandler.isWidgetModActive
	}

	$( document ).ready( function() {
		VAMTAM_ELEMENTOR.domLoaded();
	} );

	$( window ).on( 'load', function () {
		VAMTAM_ELEMENTOR.pageLoaded();
	} );

	// JS Handler applied to all elements.
	class VamtamElementBase extends elementorModules.frontend.handlers.Base {

		onInit(...args) {
			super.onInit( ...args );
			this.checkAddBaseThemeStylesClass();
		}

		checkAddBaseThemeStylesClass() {
			const isEditor = $( 'body' ).hasClass( 'elementor-editor-active' );
			if ( ! isEditor ) {
				return;
			} else if ( this.isWidgetModActive() ) {
				this.$element.addClass('vamtam-has-theme-widget-styles');
			}
		}

		// Checks if there is an active mod (master toggle) for a widget.
		isWidgetModActive() {
			let widgetName = this.getElementType();
			if ( widgetName === 'widget' ) {
				widgetName = this.getWidgetType();
			}
			return VAMTAM_ELEMENTOR.VamtamWidgetsHandler.isWidgetModActive( widgetName );
		}

	}

	jQuery( window ).on( 'elementor/frontend/init', () => {
		const addHandler = ( $element ) => {
			elementorFrontend.elementsHandler.addHandler( VamtamElementBase, {
				$element,
			} );
		};

		elementorFrontend.hooks.addAction( 'frontend/element_ready/global', addHandler );
	});
})( jQuery );
