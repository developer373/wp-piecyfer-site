/**
 * Frontend handler for the `gallery` widget.
 *
 * Reproduces elementor-pro/assets/js/gallery.b7d55bc976e04f751975.bundle.js
 * (../modules/gallery/assets/js/frontend/handler.js).
 *
 * WHAT PRO ADDS ON TOP OF e-gallery
 * ---------------------------------
 * `EGallery` is Elementor FREE's layout library — assets/lib/e-gallery/js/,
 * registered by free as the `elementor-gallery` script handle in
 * includes/frontend.php. Our GalleryWidget::get_script_depends() already asks
 * for that handle, so the library itself survives Pro's removal untouched.
 *
 * What does NOT survive is the code that drives it. Pro's handler is the only
 * thing that:
 *
 *   1. CONSTRUCTS the gallery. `new EGallery( ... )` never happens otherwise.
 *      This is the big one and it is not subtle: the widget renders a bare
 *      `.elementor-gallery__container` of absolutely-positioned items with no
 *      computed top/left/width, so the whole gallery collapses into a heap. Of
 *      everything in this JS layer, this is the ONE failure the pixel harness
 *      would actually catch.
 *   2. Translates Elementor's responsive controls into EGallery's breakpoint
 *      map — columns, gap, ideal row height per device, RTL, lazyload.
 *   3. Adds the hover-animation classes at runtime:
 *      `elementor-animated-content` on each item plus
 *      `elementor-animated-item--<name>` on the image, the overlay and the
 *      overlay content. These are NOT in the server-rendered markup. This site
 *      sets `content_hover_animation: "fade-in"`, so without the handler the
 *      overlay caption simply never animates in — invisible at rest, which is
 *      precisely why a screenshot cannot catch it.
 *   4. Runs the filter bar: clicking a `.elementor-gallery-title` sets
 *      EGallery's `tags` setting and re-groups the lightbox slideshow.
 *
 * On this site the widget is configured `gallery_layout: "grid"` with a single
 * gallery (`columns: 5`, `aspect_ratio: "16:9"`), so the filter bar has no
 * titles to click and branch 4 is dormant — but branches 1-3 are all live.
 *
 * @package PieCyfer\Core
 */

( function ( window, $ ) {
	'use strict';

	if ( ! window.piecyferFrontend ) {
		return;
	}

	window.piecyferFrontend.register( 'gallery', function () {
		return class PieCyferGallery extends elementorModules.frontend.handlers.Base {
			getDefaultSettings() {
				return {
					selectors: {
						container: '.elementor-gallery__container',
						galleryTitles: '.elementor-gallery-title',
						galleryImages: '.e-gallery-image',
						galleryItemOverlay: '.elementor-gallery-item__overlay',
						galleryItemContent: '.elementor-gallery-item__content'
					},
					classes: {
						activeTitle: 'elementor-item-active'
					}
				};
			}

			getDefaultElements() {
				const { selectors } = this.getSettings();

				const elements = {
					$container: this.$element.find( selectors.container ),
					$titles: this.$element.find( selectors.galleryTitles )
				};

				elements.$items                = elements.$container.children();
				elements.$images               = elements.$items.children( selectors.galleryImages );
				elements.$itemsOverlay         = elements.$items.children( selectors.galleryItemOverlay );
				elements.$itemsContent         = elements.$items.children( selectors.galleryItemContent );
				elements.$itemsContentElements = elements.$itemsContent.children();

				return elements;
			}

			getGallerySettings() {
				const settings = this.getElementSettings();
				const activeBreakpoints = elementorFrontend.config.responsive.activeBreakpoints;
				const activeBreakpointsKeys = Object.keys( activeBreakpoints );
				const breakPointSettings = {};
				const desktopIdealRowHeight = elementorFrontend.getDeviceSetting( 'desktop', settings, 'ideal_row_height' );

				activeBreakpointsKeys.forEach( ( breakpoint ) => {
					// The Gallery widget does not support widescreen.
					if ( 'widescreen' === breakpoint ) {
						return;
					}

					const idealRowHeight = elementorFrontend.getDeviceSetting( breakpoint, settings, 'ideal_row_height' );
					// `|| {}` guards the same class of missing-responsive-control
					// TypeError as the carousel handler. Pro dereferences .size
					// directly.
					const gap = elementorFrontend.getDeviceSetting( breakpoint, settings, 'gap' ) || {};

					breakPointSettings[ activeBreakpoints[ breakpoint ].value ] = {
						horizontalGap: gap.size,
						verticalGap: gap.size,
						columns: elementorFrontend.getDeviceSetting( breakpoint, settings, 'columns' ),
						idealRowHeight: idealRowHeight ? idealRowHeight.size : undefined
					};
				} );

				const desktopGap = elementorFrontend.getDeviceSetting( 'desktop', settings, 'gap' ) || {};

				return {
					type: settings.gallery_layout,
					idealRowHeight: desktopIdealRowHeight ? desktopIdealRowHeight.size : undefined,
					container: this.elements.$container,
					columns: settings.columns,
					aspectRatio: settings.aspect_ratio,
					lastRow: 'normal',
					horizontalGap: desktopGap.size,
					verticalGap: desktopGap.size,
					animationDuration: settings.content_animation_duration,
					breakpoints: breakPointSettings,
					rtl: elementorFrontend.config.is_rtl,
					lazyLoad: 'yes' === settings.lazyload
				};
			}

			initGallery() {
				this.gallery = new window.EGallery( this.getGallerySettings() );
				this.toggleAllAnimationsClasses();
			}

			removeAnimationClasses( $element ) {
				$element.removeClass( ( index, className ) => ( className.match( /elementor-animated-item-\S+/g ) || [] ).join( ' ' ) );
			}

			toggleOverlayHoverAnimation() {
				this.removeAnimationClasses( this.elements.$itemsOverlay );

				const hoverAnimation = this.getElementSettings( 'background_overlay_hover_animation' );

				if ( hoverAnimation ) {
					this.elements.$itemsOverlay.addClass( 'elementor-animated-item--' + hoverAnimation );
				}
			}

			toggleOverlayContentAnimation() {
				this.removeAnimationClasses( this.elements.$itemsContentElements );

				const contentHoverAnimation = this.getElementSettings( 'content_hover_animation' );

				if ( contentHoverAnimation ) {
					this.elements.$itemsContentElements.addClass( 'elementor-animated-item--' + contentHoverAnimation );
				}
			}

			toggleOverlayContentSequencedAnimation() {
				this.elements.$itemsContent.toggleClass(
					'elementor-gallery--sequenced-animation',
					'yes' === this.getElementSettings( 'content_sequenced_animation' )
				);
			}

			toggleImageHoverAnimation() {
				const imageHoverAnimation = this.getElementSettings( 'image_hover_animation' );

				this.removeAnimationClasses( this.elements.$images );

				if ( imageHoverAnimation ) {
					this.elements.$images.addClass( 'elementor-animated-item--' + imageHoverAnimation );
				}
			}

			toggleAllAnimationsClasses() {
				const elementSettings = this.getElementSettings();
				const animation = elementSettings.background_overlay_hover_animation ||
					elementSettings.content_hover_animation ||
					elementSettings.image_hover_animation;

				this.elements.$items.toggleClass( 'elementor-animated-content', !! animation );

				this.toggleImageHoverAnimation();
				this.toggleOverlayHoverAnimation();
				this.toggleOverlayContentAnimation();
				this.toggleOverlayContentSequencedAnimation();
			}

			toggleAnimationClasses( settingKey ) {
				if ( 'content_sequenced_animation' === settingKey ) {
					this.toggleOverlayContentSequencedAnimation();
				}

				if ( 'background_overlay_hover_animation' === settingKey ) {
					this.toggleOverlayHoverAnimation();
				}

				if ( 'content_hover_animation' === settingKey ) {
					this.toggleOverlayContentAnimation();
				}

				if ( 'image_hover_animation' === settingKey ) {
					this.toggleImageHoverAnimation();
				}
			}

			setGalleryTags( id ) {
				this.gallery.setSettings( 'tags', 'all' === id ? [] : [ '' + id ] );
			}

			bindEvents() {
				this.elements.$titles
					.on( 'click', this.galleriesNavigationListener.bind( this ) )
					.on( 'keyup', ( event ) => {
						const ENTER_KEY = 13,
							SPACE_KEY = 32;

						if ( ENTER_KEY === event.keyCode || SPACE_KEY === event.keyCode ) {
							event.currentTarget.click();
						}
					} );

				elementorFrontend.elements.$window.on( 'elementor/nested-tabs/activate', this.initGallery.bind( this ) );
			}

			galleriesNavigationListener( event ) {
				const classes = this.getSettings( 'classes' );
				const clickedElement = $( event.target );

				// Only one filter title may be active at a time.
				this.elements.$titles.removeClass( classes.activeTitle );
				clickedElement.addClass( classes.activeTitle );

				this.setGalleryTags( clickedElement.data( 'gallery-index' ) );

				const updateLightboxGroup = () => this.setLightboxGalleryIndex( clickedElement.data( 'gallery-index' ) );

				// Pro waits a flat second for EGallery to finish filtering before
				// re-grouping the lightbox. Kept verbatim — shortening it is a
				// race, not an optimisation.
				setTimeout( updateLightboxGroup, 1000 );
			}

			setLightboxGalleryIndex( index = 'all' ) {
				if ( 'all' === index ) {
					return this.elements.$items.attr( 'data-elementor-lightbox-slideshow', 'all_' + this.getID() );
				}

				this.elements.$items
					.not( '.e-gallery-item--hidden' )
					.attr( 'data-elementor-lightbox-slideshow', index + '_' + this.getID() );
			}

			onInit( ...args ) {
				super.onInit( ...args );

				if ( elementorFrontend.isEditMode() && 1 <= this.$element.find( '.elementor-widget-empty-icon' ).length ) {
					this.$element.addClass( 'elementor-widget-empty' );
				}

				if ( ! this.elements.$container.length ) {
					return;
				}

				/*
				 * EGallery is free's, loaded via the `elementor-gallery` handle
				 * that our widget's get_script_depends() names. If it is not on
				 * the page, constructing would throw — so we decline loudly
				 * instead, and the markup stays exactly as the server rendered it.
				 */
				if ( 'undefined' === typeof window.EGallery ) {
					window.piecyferFrontend.warn(
						'the "elementor-gallery" script (EGallery) is not on the page — gallery layout will not be built'
					);
					return;
				}

				this.initGallery();

				// Selects the first filter tab. No-op when there is no filter bar.
				this.elements.$titles.first().trigger( 'click' );
			}

			getSettingsDictionary() {
				if ( this.settingsDictionary ) {
					return this.settingsDictionary;
				}

				const activeBreakpoints = elementorFrontend.config.responsive.activeBreakpoints;
				const activeBreakpointsKeys = Object.keys( activeBreakpoints );

				const settingsDictionary = {
					columns: [ 'columns' ],
					gap: [ 'horizontalGap', 'verticalGap' ],
					ideal_row_height: [ 'idealRowHeight' ]
				};

				activeBreakpointsKeys.forEach( ( breakpoint ) => {
					if ( 'widescreen' === breakpoint ) {
						return;
					}

					const value = activeBreakpoints[ breakpoint ].value;

					settingsDictionary[ 'columns_' + breakpoint ] = [ 'breakpoints.' + value + '.columns' ];
					settingsDictionary[ 'gap_' + breakpoint ] = [
						'breakpoints.' + value + '.horizontalGap',
						'breakpoints.' + value + '.verticalGap'
					];
					settingsDictionary[ 'ideal_row_height_' + breakpoint ] = [ 'breakpoints.' + value + '.idealRowHeight' ];
				} );

				settingsDictionary.aspect_ratio = [ 'aspectRatio' ];

				this.settingsDictionary = settingsDictionary;

				return this.settingsDictionary;
			}

			onElementChange( settingKey ) {
				if ( ! this.gallery ) {
					return;
				}

				if ( -1 !== [
					'background_overlay_hover_animation',
					'content_hover_animation',
					'image_hover_animation',
					'content_sequenced_animation'
				].indexOf( settingKey ) ) {
					this.toggleAnimationClasses( settingKey );
					return;
				}

				const settingsDictionary = this.getSettingsDictionary();
				const settingsToUpdate = settingsDictionary[ settingKey ];

				if ( settingsToUpdate ) {
					const gallerySettings = this.getGallerySettings();

					settingsToUpdate.forEach( ( settingToUpdate ) => {
						this.gallery.setSettings( settingToUpdate, this.getItems( gallerySettings, settingToUpdate ) );
					} );
				}
			}

			onDestroy() {
				super.onDestroy();

				if ( this.gallery ) {
					this.gallery.destroy();
				}
			}
		};
	} );
} )( window, jQuery );
