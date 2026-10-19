/**
 * Slick Slider — inicijalizacija i konfiguracija
 *
 * Stranice: homepage, blog (single i archive), product single
 * Zavisnosti: jquery, slick.min.js
 *
 * @package Dreampoint_B2B
 */

jQuery( function ( $ ) {
    'use strict';

    // =========================================================================
    // HERO SLIDER — prioritet za LCP
    // =========================================================================

    /**
     * Hero slider se inicijalizuje odmah — bez odlaganja.
     * Prva slika je eager-loaded u PHP-u, nema lazyLoad opcije.
     *
     * ISPRAVKA: init event se vezuje PRE .slick() poziva jer se okida
     * tokom inicijalizacije. Logika prvog slajda je postavljena direktno
     * nakon init-a umesto unutar init handlera.
     */
    const $heroSlider = $( '.hero-slider' );

    if ( $heroSlider.length ) {

        // Vežemo init event PRE inicijalizacije
        $heroSlider.on( 'init', function ( event, slick ) {
            $( slick.$slides[ 0 ] ).attr( 'aria-hidden', 'false' )
                .find( 'a, button' ).attr( 'tabindex', '0' );

            slick.$slides.not( slick.$slides[ 0 ] )
                .find( 'a, button' ).attr( 'tabindex', '-1' );
        } );

        $heroSlider.slick( {
            arrows:          true,
            dots:            false,
            infinite:        true,
            speed:           500,
            fade:            true,
            cssEase:         'ease-in-out',
            autoplay:        false,
            autoplaySpeed:   5000,
            pauseOnHover:    true,
            pauseOnFocus:    true,
            waitForAnimate:  false,
            useCSS:          true,
            useTransform:    true,
            accessibility:   true,
            focusOnSelect:   false,
            swipe:           true,
            touchMove:       true,
            draggable:       true,
        } );

        $heroSlider.on( 'afterChange', function ( event, slick, currentSlide ) {
            $( slick.$slides[ currentSlide ] )
                .attr( 'aria-hidden', 'false' )
                .find( 'a, button' ).attr( 'tabindex', '0' );

            slick.$slides.not( slick.$slides[ currentSlide ] )
                .find( 'a, button' ).attr( 'tabindex', '-1' );
        } );
    }

    // =========================================================================
    // OSNOVNA KONFIGURACIJA — deljene opcije za product slajdere
    // =========================================================================

    const slickDefaults = {
        dots:             false,
        arrows:           true,
        infinite:         false,
        speed:            300,
        slidesToShow:     4,
        slidesToScroll:   1,
        lazyLoad:         'ondemand',
        responsive: [
            {
                breakpoint: 991,
                settings: {
                    slidesToShow:   3,
                    slidesToScroll: 1,
                    dots:           true,
                    arrows:         false,
                },
            },
            {
                breakpoint: 767,
                settings: {
                    slidesToShow:   1,
                    slidesToScroll: 1,
                    dots:           true,
                    arrows:         false,
                },
            },
        ],
    };

    // =========================================================================
    // POMOĆNE FUNKCIJE
    // =========================================================================

    /**
     * Inicijalizuje slajder samo ako postoji u DOM-u i nije već inicijalizovan.
     *
     * @param {jQuery} $element  - jQuery objekat slajdera
     * @param {object} config    - Slick konfiguracija
     */
    function initSlider( $element, config ) {
        if ( $element.length && ! $element.hasClass( 'slick-initialized' ) ) {
            $element.slick( config );
        }
    }

    // Zajednička navigacija product/content slajdera: .nav-btns u .section-heading
    // (markup: template-parts/slider-nav.php). Slick-ove vlastite strelice su isključene.
    const sliderNavScope = '.slider-grid, .cross-sells, .related-posts';

    const productSliderOptions = {
        dots:           false,
        arrows:         false,
        infinite:       false,
        speed:          300,
        slidesToShow:   4,
        slidesToScroll: 1,
        lazyLoad:       'ondemand',
        responsive: [
            { breakpoint: 991, settings: { slidesToShow: 3, slidesToScroll: 1 } },
            { breakpoint: 767, settings: { slidesToShow: 1, slidesToScroll: 1 } },
        ],
    };

    /**
     * Veže prev/next dugmad iz .section-heading na jedan slajder (po instanci bloka).
     * Navigacija se krije kad nema više slajdova nego što je trenutno vidljivo
     * (čita aktivni slidesToShow, pa prati responsive breakpointe), a krajnja
     * dugmad dobijaju .slick-disabled + aria-disabled.
     *
     * @param {jQuery} $slider - inicijalizovani Slick slajder
     * @param {jQuery} $nav    - .nav-btns element
     */
    function bindSliderNav( $slider, $nav ) {
        const $prev = $nav.find( '.btn-prev' );
        const $next = $nav.find( '.btn-next' );

        function setState( $btn, disabled ) {
            $btn.toggleClass( 'slick-disabled', disabled ).attr( 'aria-disabled', disabled ? 'true' : 'false' );
        }

        function sync() {
            const slick = $slider.slick( 'getSlick' );
            const show  = slick.options.slidesToShow;

            $nav.css( 'display', slick.slideCount > show ? '' : 'none' );
            setState( $prev, slick.currentSlide <= 0 );
            setState( $next, slick.currentSlide >= slick.slideCount - show );
        }

        $prev.on( 'click', function ( e ) {
            e.preventDefault();
            if ( ! $prev.hasClass( 'slick-disabled' ) ) $slider.slick( 'slickPrev' );
        } );

        $next.on( 'click', function ( e ) {
            e.preventDefault();
            if ( ! $next.hasClass( 'slick-disabled' ) ) $slider.slick( 'slickNext' );
        } );

        // 'breakpoint' se okida nakon što Slick ažurira opcije (slidesToShow je već nov).
        $slider.on( 'reInit afterChange breakpoint', sync );
        sync();
    }

    /**
     * Inicijalizuje slajder sa navigacijom iz svog .section-heading (po instanci,
     * jer više blokova istog tipa može biti na istoj stranici).
     *
     * @param {jQuery} $slider  - slajder(i)
     * @param {object} options  - Slick konfiguracija
     */
    function initNavSlider( $slider, options ) {
        $slider.each( function () {
            const $one = $( this );
            if ( $one.hasClass( 'slick-initialized' ) ) return;

            const $nav = $one.closest( sliderNavScope ).find( '.section-heading .nav-btns' ).first();

            $one.slick( options );
            if ( $nav.length ) bindSliderNav( $one, $nav );
        } );
    }

    // =========================================================================
    // INICIJALIZACIJA NON-CRITICAL SLAJDERA — odloženo 100ms
    // Sprečava blokiranje hero LCP-a
    // =========================================================================

    setTimeout( function () {

        // --- Product slajderi: navigacija u .section-heading (template-parts/slider-nav.php) ---
        [
            '.latest-products-slider',
            '.bestseller-slider',
            '.recommended-slider',
            '.featured-products-slider',
            '.cross-sells-slider',
        ].forEach( function ( selector ) {
            initNavSlider( $( selector ), productSliderOptions );
        } );

        // --- Gallery slider: bez naslova, zadržava Slick-ove strelice ---
        initSlider( $( '.gallery-slider' ), slickDefaults );

        // --- Segments Slider (acf/segments-section) ---
        // Tile je pravi link, pa mu ostaje fokus; uklanjamo samo dodatni tabindex sa slajd wrappera
        // (Slick ADA) da ne bude dvostrukog tab zaustavljanja. Slick ga postavlja u initADA() NAKON
        // 'init' eventa, zato se uklanja nakon initSlider() i odloženo na reInit/afterChange/breakpoint.
        const $segments = $( '.segments-slider' );
        if ( $segments.length ) {
            const stripSlideTabindex = function () {
                $segments.find( '.slick-slide' ).removeAttr( 'tabindex' );
            };

            $segments.on( 'reInit afterChange breakpoint', function () {
                setTimeout( stripSlideTabindex, 0 );
            } );

            initSlider( $segments, {
                dots:           false,
                arrows:         true,
                infinite:       false,
                speed:          300,
                slidesToShow:   3,
                slidesToScroll: 1,
                responsive: [
                    {
                        breakpoint: 991,
                        settings: { slidesToShow: 2, slidesToScroll: 1, dots: true, arrows: false },
                    },
                    {
                        breakpoint: 767,
                        settings: { slidesToShow: 1, slidesToScroll: 1, dots: true, arrows: false },
                    },
                ],
            } );

            stripSlideTabindex();
        }

        // --- Related Posts Slider (blog) — ista navigacija u .section-heading ---
        initNavSlider( $( '.related-posts-slider' ), {
            dots:           false,
            arrows:         false,
            infinite:       false,
            speed:          300,
            slidesToShow:   3,
            slidesToScroll: 1,
            lazyLoad:       'ondemand',
            responsive: [
                {
                    breakpoint: 991,
                    settings: { slidesToShow: 2, slidesToScroll: 1, dots: true },
                },
                {
                    breakpoint: 767,
                    settings: { slidesToShow: 1, slidesToScroll: 1, dots: true },
                },
            ],
        } );

        // --- Brands Slider — beskonačna animacija ---
        initSlider( $( '.brands-slider' ), {
            infinite:       true,
            autoplay:       true,
            autoplaySpeed:  0,
            speed:          3000,
            variableWidth:  true,
            arrows:         false,
            dots:           false,
            cssEase:        'linear',
            slidesToShow:   1,
            slidesToScroll: 1,
            pauseOnHover:   false,
            pauseOnFocus:   false,
            swipe:          false,
            touchMove:      false,
        } );

        // --- Testimonials Slider ---
        initSlider( $( '.testimonials-slider' ), {
            arrows:          true,
            dots:            false,
            infinite:        true,
            speed:           500,
            fade:            true,
            adaptiveHeight:  true,
            cssEase:         'linear',
            autoplay:        false,
            pauseOnHover:    true,
            responsive: [
                {
                    breakpoint: 991,
                    settings: { arrows: false, dots: true },
                },
            ],
        } );

        // --- Notification Slider ---
        initSlider( $( '.notification-slider' ), {
            arrows:         true,
            dots:           false,
            infinite:       true,
            speed:          500,
            fade:           true,
            adaptiveHeight: true,
            cssEase:        'linear',
            pauseOnHover:   true,
        } );

        // --- Accessibility: role i aria-label na svim inicijalizovanim slajderima ---
        // primenjuje se unutar setTimeout-a, nakon što su svi slajderi
        // inicijalizovani, umesto van njega gde slajderi još ne postoje u DOM-u
        $( '.slick-slider' )
            .attr( 'role', 'region' )
            .attr( 'aria-label', typeof dpSlick !== 'undefined' ? dpSlick.sliderLabel : 'Slider' );

    }, 100 );

    // =========================================================================
    // PRODUCTS TABS SLIDER — uslovni (samo ispod 1200px)
    // =========================================================================

    /**
     * Ispod 1200px: Slick slider
     * Iznad 1200px: CSS Grid (Slick se uništava ako je aktivan)
     */
    function initProductsTabsSlider() {
        const $tabsGrid = $( '.products-tabs-block .products-grid' );

        if ( ! $tabsGrid.length ) return;

        if ( $( window ).width() < 1200 ) {
            if ( ! $tabsGrid.hasClass( 'slick-initialized' ) ) {
                $tabsGrid.slick( {
                    dots:           true,
                    arrows:         true,
                    infinite:       false,
                    speed:          300,
                    slidesToShow:   3,
                    slidesToScroll: 1,
                    lazyLoad:       'ondemand',
                    responsive: [
                        {
                            breakpoint: 991,
                            settings: { slidesToShow: 2, slidesToScroll: 1, dots: true, arrows: false },
                        },
                        {
                            breakpoint: 767,
                            settings: { slidesToShow: 1, slidesToScroll: 1, dots: true, arrows: false },
                        },
                    ],
                } );
            }
        } else {
            if ( $tabsGrid.hasClass( 'slick-initialized' ) ) {
                $tabsGrid.slick( 'unslick' );
            }
        }
    }

    setTimeout( initProductsTabsSlider, 150 );

    // Debounced resize handler
    let productsTabsResizeTimer;
    $( window ).on( 'resize', function () {
        clearTimeout( productsTabsResizeTimer );
        productsTabsResizeTimer = setTimeout( initProductsTabsSlider, 250 );
    } );

    // Ponovna inicijalizacija nakon AJAX promene taba
    $( document ).on( 'productsTabsLoaded', function () {
        setTimeout( initProductsTabsSlider, 100 );
    } );

    // =========================================================================
    // PRODUCT SINGLE GALERIJA — sa podrškom za varijacije
    // =========================================================================

    const $sliderFor = $( '#product-single-main .slider-for' );
    const $sliderNav = $( '#product-single-main .slider-nav' );

    if ( $sliderFor.length && $sliderNav.length ) {

        $sliderFor.slick( {
            infinite:       true,
            slidesToShow:   1,
            slidesToScroll: 1,
            dots:           false,
            arrows:         true,
            focusOnSelect:  true,
            fade:           true,
            cssEase:        'ease-in-out',
            lazyLoad:       'ondemand',
            asNavFor:       '#product-single-main .slider-nav',
            responsive: [
                {
                    breakpoint: 767,
                    settings: { slidesToShow: 1, slidesToScroll: 1 },
                },
            ],
        } );

        $sliderNav.slick( {
            infinite:       true,
            slidesToShow:   4,
            slidesToScroll: 1,
            asNavFor:       '#product-single-main .slider-for',
            dots:           false,
            arrows:         false,
            focusOnSelect:  true,
            centerMode:     false,
            responsive: [
                {
                    breakpoint: 1199,
                    settings: { slidesToShow: 3, arrows: false },
                },
                {
                    breakpoint: 767,
                    settings: { slidesToShow: 4, arrows: false },
                },
            ],
        } );

        // --- Prebacivanje slika varijacija ---

        /**
         * Izvlači ime fajla iz URL-a, bez dimenzija (npr. -800x600).
         *
         * @param  {string} url
         * @return {string}
         */
        function getFilename( url ) {
            if ( ! url ) return '';
            const filename = url.split( '/' ).pop().split( '?' )[ 0 ];
            return filename.replace( /-\d+x\d+/, '' );
        }

        /**
         * Pronalazi indeks slajda koji odgovara izabranoj varijaciji.
         * Pokušava redom: attachment ID, pun URL, parcijalni URL, ime fajla.
         *
         * @param  {object} variation - WooCommerce variation objekat
         * @return {number} Indeks slajda ili -1 ako nije pronađen
         */
        function findVariationSlideIndex( variation ) {
            if ( ! variation || ! variation.image ) return -1;

            const varImgUrl = variation.image.src || variation.image.full_src || variation.image.url || '';
            const varImgId  = variation.image_id || variation.image.id || null;
            let targetIndex = -1;

            $sliderFor.find( '.slick-slide:not(.slick-cloned) img' ).each( function ( index ) {
                const $img         = $( this );
                const imgSrc       = $img.data( 'full-src' ) || $img.attr( 'src' ) || '';
                const attachmentId = $img.data( 'attachment-id' ) || $img.data( 'image-id' ) || null;

                // 1. Poklapanje po attachment ID-u (najpouzdanije)
                if ( attachmentId && varImgId && String( attachmentId ) === String( varImgId ) ) {
                    targetIndex = index;
                    return false;
                }

                // 2. Poklapanje po punom URL-u
                if ( imgSrc && varImgUrl && imgSrc === varImgUrl ) {
                    targetIndex = index;
                    return false;
                }

                // 3. Parcijalno poklapanje URL-a
                if ( imgSrc && varImgUrl && (
                    imgSrc.indexOf( varImgUrl ) !== -1 ||
                    varImgUrl.indexOf( imgSrc ) !== -1
                ) ) {
                    targetIndex = index;
                    return false;
                }

                // 4. Poklapanje po imenu fajla (rezervni slučaj)
                if ( getFilename( imgSrc ) && getFilename( imgSrc ) === getFilename( varImgUrl ) ) {
                    targetIndex = index;
                    return false;
                }
            } );

            return targetIndex;
        }

        $( document ).on( 'found_variation', 'form.variations_form', function ( event, variation ) {
            try {
                const slideIndex = findVariationSlideIndex( variation );

                if ( slideIndex !== -1 ) {
                    $sliderFor.slick( 'slickGoTo', slideIndex, false );
                    $sliderNav.find( '.slick-slide' ).removeClass( 'slick-current' );
                    $sliderNav.find( '.slick-slide[data-slick-index="' + slideIndex + '"]' )
                                .addClass( 'slick-current' );
                }
            } catch ( error ) {
                if ( window.location.search.indexOf( 'debug=1' ) !== -1 ) {
                    console.error( 'Greška pri prebacivanju slike varijacije:', error );
                }
            }
        } );

        $( document ).on( 'reset_data', 'form.variations_form', function () {
            $sliderFor.slick( 'slickGoTo', 0, false );
        } );
    }

} );
