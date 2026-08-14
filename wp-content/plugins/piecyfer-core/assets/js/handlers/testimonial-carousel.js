/**
 * Frontend handler for the `testimonial-carousel` widget.
 *
 * Reproduces elementor-pro/assets/js/carousel.298f1fc9c115422aad0e.bundle.js —
 * both classes it contains:
 *
 *   ../modules/carousel/assets/js/frontend/handlers/base.js
 *   ../modules/carousel/assets/js/frontend/handlers/testimonial-carousel.js
 *
 * The two are merged into one class here because testimonial-carousel is the
 * only carousel widget this plugin owns; when media-carousel or reviews are
 * ported, split the base back out rather than copying it.
 *
 * WHICH SWIPER, AND WHOSE
 * -----------------------
 * Swiper is Elementor FREE's, not Pro's. Free ships two copies —
 * assets/lib/swiper/ (5.3.6) and assets/lib/swiper/v8/ (8.4.5) — and picks
 * between them with the `e_swiper_latest` experiment, which also decides
 * whether the container class is `swiper` (v8) or `swiper-container` (v5).
 *
 * The captured baseline HTML settles it for this site:
 *
 *     <div class="elementor-main-swiper swiper swiper-initialized
 *                 swiper-horizontal swiper-pointer-events swiper-backface-hidden">
 *
 * `swiper` with no `-container`, plus `swiper-pointer-events` and
 * `swiper-backface-hidden`, are v8 markers. So: Swiper 8.4.5, loaded on demand
 * by free's own `elementorFrontend.utils.swiper` wrapper (which calls
 * `assetsLoader.load('script','swiper')`). Nothing here touches a Pro asset,
 * and we construct through that wrapper rather than `window.Swiper` directly so
 * free keeps owning the version choice, the lazy load and the
 * `handleElementorBreakpoints` config rewrite.
 *
 * WHAT BREAKS WITHOUT THIS
 * ------------------------
 * The captured markup is post-JS, so the snapshot shows an initialised swiper.
 * Server-side the widget emits a plain `.swiper-wrapper` full of
 * `.swiper-slide` divs with no transform, no width and no `swiper-initialized`.
 * Un-initialised, Swiper's CSS stacks every slide at full width: the carousel
 * becomes a tall static column. Autoplay, drag, arrows and pagination are all
 * gone.
 *
 * @package PieCyfer\Core
 */

( function ( window, $ ) {
	'use strict';

	if ( ! window.piecyferFrontend ) {
		return;
	}

	window.piecyferFrontend.register( 'testimonial-carousel', function () {
		/**
		 * `SwiperBase` (elementorModules.frontend.handlers.SwiperBase) is an ES6
		 * class, so it must be extended with `class ... extends` — the
		 * `Module.extend()` helper used by nav-menu and search-form calls the
		 * parent without `new` and would throw on a class constructor.
		 */
		return class PieCyferTestimonialCarousel extends elementorModules.frontend.handlers.SwiperBase {
			getDefaultSettings() {
				const settings = {
					selectors: {
						swiperContainer: '.elementor-main-swiper',
						swiperSlide: '.swiper-slide'
					},
					// Pro's CarouselBase defaults are 3/3/3/3/2/2/1; the
					// testimonial subclass overrides every breakpoint to 1, so
					// an unset `slides_per_view_<device>` falls back to one slide.
					slidesPerView: {
						desktop: 1
					}
				};

				Object.keys( elementorFrontend.config.responsive.activeBreakpoints ).forEach( ( breakpointName ) => {
					settings.slidesPerView[ breakpointName ] = 1;
				} );

				return settings;
			}

			getDefaultElements() {
				const selectors = this.getSettings( 'selectors' );
				const elements  = {
					$swiperContainer: this.$element.find( selectors.swiperContainer )
				};

				elements.$slides = elements.$swiperContainer.find( selectors.swiperSlide );

				return elements;
			}

			/**
			 * Hard-coded in Pro's testimonial subclass: this widget has no
			 * `effect` control, and `slide` is what makes getSlidesPerView and
			 * getSlidesToScroll read the real settings instead of returning 1.
			 */
			getEffect() {
				return 'slide';
			}

			getDeviceSlidesPerView( device ) {
				const slidesPerViewKey = 'slides_per_view' + ( 'desktop' === device ? '' : '_' + device );

				return Math.min(
					this.getSlidesCount(),
					+this.getElementSettings( slidesPerViewKey ) || this.getSettings( 'slidesPerView' )[ device ]
				);
			}

			getSlidesPerView( device ) {
				if ( 'slide' === this.getEffect() ) {
					return this.getDeviceSlidesPerView( device );
				}

				return 1;
			}

			getDeviceSlidesToScroll( device ) {
				const slidesToScrollKey = 'slides_to_scroll' + ( 'desktop' === device ? '' : '_' + device );

				return Math.min( this.getSlidesCount(), +this.getElementSettings( slidesToScrollKey ) || 1 );
			}

			getSlidesToScroll( device ) {
				if ( 'slide' === this.getEffect() ) {
					return this.getDeviceSlidesToScroll( device );
				}

				return 1;
			}

			/**
			 * Guard added: Pro does `this.getElementSettings(name).size`, which
			 * throws if the key is absent from data-settings. Frontend
			 * getElementSettings() returns the raw data-settings object with no
			 * defaults merged in, so an unset responsive slider — e.g. a
			 * breakpoint activated after the page was last saved — is a live
			 * TypeError. `|| {}` makes it fall through to 0, which is the value
			 * Pro would have used anyway.
			 */
			getSpaceBetween( device ) {
				let propertyName = 'space_between';

				if ( device && 'desktop' !== device ) {
					propertyName += '_' + device;
				}

				return ( this.getElementSettings( propertyName ) || {} ).size || 0;
			}

			getSwiperOptions() {
				const elementSettings = this.getElementSettings();

				const swiperOptions = {
					grabCursor: true,
					initialSlide: this.getInitialSlide(),
					slidesPerView: this.getSlidesPerView( 'desktop' ),
					slidesPerGroup: this.getSlidesToScroll( 'desktop' ),
					spaceBetween: this.getSpaceBetween(),
					loop: 'yes' === elementSettings.loop,
					speed: elementSettings.speed,
					effect: this.getEffect(),
					preventClicksPropagation: false,
					slideToClickedSlide: true,
					// Tells free's wrapper to rewrite the breakpoint map from
					// Elementor's max-width semantics to Swiper's min-width ones.
					handleElementorBreakpoints: true
				};

				if ( 'yes' === elementSettings.lazyload ) {
					swiperOptions.lazy = {
						loadPrevNext: true,
						loadPrevNextAmount: 1
					};
				}

				if ( elementSettings.show_arrows ) {
					swiperOptions.navigation = {
						prevEl: '.elementor-swiper-button-prev',
						nextEl: '.elementor-swiper-button-next'
					};
				}

				if ( elementSettings.pagination ) {
					swiperOptions.pagination = {
						el: '.swiper-pagination',
						type: elementSettings.pagination,
						clickable: true
					};
				}

				if ( 'cube' !== this.getEffect() ) {
					const breakpointsSettings = {};
					const breakpoints = elementorFrontend.config.responsive.activeBreakpoints;

					Object.keys( breakpoints ).forEach( ( breakpointName ) => {
						breakpointsSettings[ breakpoints[ breakpointName ].value ] = {
							slidesPerView: this.getSlidesPerView( breakpointName ),
							slidesPerGroup: this.getSlidesToScroll( breakpointName ),
							spaceBetween: this.getSpaceBetween( breakpointName )
						};
					} );

					swiperOptions.breakpoints = breakpointsSettings;
				}

				if ( ! this.isEdit && elementSettings.autoplay ) {
					swiperOptions.autoplay = {
						delay: elementSettings.autoplay_speed,
						disableOnInteraction: !! elementSettings.pause_on_interaction
					};
				}

				return swiperOptions;
			}

			getDeviceBreakpointValue( device ) {
				if ( ! this.breakpointsDictionary ) {
					const breakpoints = elementorFrontend.config.responsive.activeBreakpoints;

					this.breakpointsDictionary = {};

					Object.keys( breakpoints ).forEach( ( breakpointName ) => {
						this.breakpointsDictionary[ breakpointName ] = breakpoints[ breakpointName ].value;
					} );
				}

				return this.breakpointsDictionary[ device ];
			}

			updateSpaceBetween( propertyName ) {
				const deviceMatch = propertyName.match( 'space_between_(.*)' );
				const device = deviceMatch ? deviceMatch[ 1 ] : 'desktop';
				const newSpaceBetween = this.getSpaceBetween( device );

				if ( 'desktop' !== device ) {
					this.swiper.params.breakpoints[ this.getDeviceBreakpointValue( device ) ].spaceBetween = newSpaceBetween;
				} else {
					this.swiper.params.spaceBetween = newSpaceBetween;
				}

				this.swiper.params.spaceBetween = newSpaceBetween;
				this.swiper.update();
			}

			/**
			 * Pro's onInit is `async` and awaits the wrapper. `.then()` is the
			 * same thing without making a lifecycle method Elementor calls
			 * synchronously return a promise; the `catch` is ours, because an
			 * unhandled rejection here is a carousel that fails silently.
			 */
			onInit( ...args ) {
				elementorModules.frontend.handlers.Base.prototype.onInit.apply( this, args );

				if ( 1 >= this.getSlidesCount() ) {
					return;
				}

				if ( ! elementorFrontend.utils || ! elementorFrontend.utils.swiper ) {
					window.piecyferFrontend.warn(
						'elementorFrontend.utils.swiper is missing — the testimonial carousel will not initialise'
					);
					return;
				}

				const Swiper = elementorFrontend.utils.swiper;

				new Swiper( this.elements.$swiperContainer, this.getSwiperOptions() ).then( ( swiper ) => {
					this.swiper = swiper;

					if ( 'yes' === this.getElementSettings( 'pause_on_hover' ) ) {
						this.togglePauseOnHover( true );
					}

					// Pro exposes the instance here; the behaviour harness reads
					// it back off the container to prove the carousel is live.
					this.elements.$swiperContainer.data( 'swiper', swiper );
				} ).catch( ( e ) => {
					window.piecyferFrontend.warn( 'Swiper failed to construct for testimonial-carousel', e );
				} );
			}

			getChangeableProperties() {
				return {
					autoplay: 'autoplay',
					pause_on_hover: 'pauseOnHover',
					pause_on_interaction: 'disableOnInteraction',
					autoplay_speed: 'delay',
					speed: 'speed',
					width: 'width'
				};
			}

			updateSwiperOption( propertyName ) {
				if ( 0 === propertyName.indexOf( 'width' ) ) {
					this.swiper.update();
					return;
				}

				const elementSettings = this.getElementSettings();
				const newSettingValue = elementSettings[ propertyName ];
				const changeableProperties = this.getChangeableProperties();

				let propertyToUpdate = changeableProperties[ propertyName ];
				let valueToUpdate = newSettingValue;

				switch ( propertyName ) {
					case 'autoplay':
						if ( newSettingValue ) {
							valueToUpdate = {
								delay: elementSettings.autoplay_speed,
								disableOnInteraction: 'yes' === elementSettings.pause_on_interaction
							};
						} else {
							valueToUpdate = false;
						}
						break;

					case 'autoplay_speed':
						propertyToUpdate = 'autoplay';
						valueToUpdate = {
							delay: newSettingValue,
							disableOnInteraction: 'yes' === elementSettings.pause_on_interaction
						};
						break;

					case 'pause_on_hover':
						this.togglePauseOnHover( 'yes' === newSettingValue );
						break;

					case 'pause_on_interaction':
						valueToUpdate = 'yes' === newSettingValue;
						break;
				}

				// pause_on_hover is implemented with event listeners, not by Swiper.
				if ( 'pause_on_hover' !== propertyName ) {
					this.swiper.params[ propertyToUpdate ] = valueToUpdate;
				}

				this.swiper.update();
			}

			onElementChange( propertyName ) {
				// Editor-only path; `this.swiper` may still be pending on a very
				// fast first edit, hence the extra guard Pro does not need
				// because its onInit awaited the instance.
				if ( 1 >= this.getSlidesCount() || ! this.swiper ) {
					return;
				}

				if ( 0 === propertyName.indexOf( 'width' ) ) {
					this.swiper.update();

					if ( this.thumbsSwiper ) {
						this.thumbsSwiper.update();
					}

					return;
				}

				/*
				 * Responsive controls need their own path — a Swiper 5.3.6 bug
				 * Pro worked around and never revisited. Kept as-is.
				 */
				if ( 0 === propertyName.indexOf( 'space_between' ) ) {
					this.updateSpaceBetween( propertyName );
					return;
				}

				const changeableProperties = this.getChangeableProperties();

				if ( Object.prototype.hasOwnProperty.call( changeableProperties, propertyName ) ) {
					this.updateSwiperOption( propertyName );
				}
			}

			onEditSettingsChange( propertyName ) {
				if ( 1 >= this.getSlidesCount() || ! this.swiper ) {
					return;
				}

				if ( 'activeItemIndex' === propertyName ) {
					this.swiper.slideToLoop( this.getEditSettings( 'activeItemIndex' ) - 1 );
				}
			}
		};
	} );
} )( window, jQuery );
