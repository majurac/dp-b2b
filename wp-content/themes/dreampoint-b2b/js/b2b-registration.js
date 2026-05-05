/**
 * B2B Registration — višekorački obrazac
 * Validacija i navigacija između koraka registracijske forme.
 *
 * @package Dreampoint_B2B
 * @version 1.1.0
 */

( function ( $ ) {
    'use strict';

    const i18n = typeof dpB2bRegister !== 'undefined' ? dpB2bRegister : {
        invalidEmail:    'Molimo unesite ispravnu e-mail adresu.',
        postcodeShort:   'Poštanski broj mora sadržati točno 5 cifara.',
        postcodePattern: 'Poštanski broj mora biti 5 cifara (samo brojevi).',
        requiredFields:  'Molimo vas da ispravno ispunite sva obavezna polja.',
    };

    let currentStep = 1;
    const totalSteps = 3;

    function updateStep() {
        $( '.registration-steps .step-item' ).removeClass( 'active' );
        $( 'section' ).hide();

        $( '.registration-steps .step-item' ).eq( currentStep - 1 ).addClass( 'active' );
        $( 'section' ).eq( currentStep - 1 ).show();

        $( '.nav-btns' ).removeClass( 'step1-is-active step2-is-active step3-is-active' );
        $( '.nav-btns' ).addClass( 'step' + currentStep + '-is-active' );

        $( '.lr-image img' ).removeClass( 'active-image' );
        $( '.lr-image img' ).eq( currentStep - 1 ).addClass( 'active-image' );

        handleSuccessfulValidation();
    }

    function validateStep() {
        let isValid = true;

        $( 'section' ).eq( currentStep - 1 ).find( '[required]' ).each( function () {
            $( this ).removeClass( 'woocommerce-invalid woocommerce-invalid-required-field' );

            if ( $( this ).is( ':checkbox' ) && ! $( this ).prop( 'checked' ) ) {
                isValid = false;
                $( this ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
            } else if ( $( this ).val().trim() === '' ) {
                isValid = false;
                $( this ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
            } else if ( $( this ).is( '[type="email"]' ) && ! isValidEmail( $( this ).val() ) ) {
                isValid = false;
                $( this ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                alert( i18n.invalidEmail );
            } else if ( $( this ).attr( 'name' ) === 'billing_postcode' ) {
                const field = $( this )[0];

                if ( ! field.validity.valid ) {
                    isValid = false;
                    $( this ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );

                    if ( field.validity.tooShort ) {
                        alert( i18n.postcodeShort );
                    } else if ( field.validity.patternMismatch ) {
                        alert( i18n.postcodePattern );
                    }
                }
            } else if ( $( this ).attr( 'id' ) === 'reg_password' ) {
                const strengthClass = $( '#password_strength' ).attr( 'class' ) || '';

                if ( ! strengthClass.includes( 'good' ) && ! strengthClass.includes( 'strong' ) ) {
                    isValid = false;
                    $( this ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                }
            }
        } );

        return isValid;
    }

    function isValidEmail( email ) {
        return /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/.test( email );
    }

    function handleSuccessfulValidation() {
        $( 'section' ).eq( currentStep - 1 ).find( '[required]' ).each( function () {
            $( this ).off( 'focusout' );

            $( this ).on( 'focusout', function () {
                if ( $( this ).is( ':checkbox' ) ) {
                    if ( $( this ).prop( 'checked' ) ) {
                        $( this ).addClass( 'woocommerce-validated' ).removeClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                    } else {
                        $( this ).removeClass( 'woocommerce-validated' ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                    }
                } else if ( $( this ).attr( 'name' ) === 'billing_postcode' ) {
                    const field = $( this )[0];

                    if ( field.validity.valid && $( this ).val().length === 5 ) {
                        $( this ).addClass( 'woocommerce-validated' ).removeClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                    } else {
                        $( this ).removeClass( 'woocommerce-validated' ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                    }
                } else if ( $( this ).val().trim() !== '' ) {
                    if ( $( this ).is( '[type="email"]' ) && ! isValidEmail( $( this ).val() ) ) {
                        $( this ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' ).removeClass( 'woocommerce-validated' );
                    } else if ( $( this ).attr( 'id' ) === 'reg_password' ) {
                        const strengthClass = $( '#password_strength' ).attr( 'class' ) || '';

                        if ( strengthClass.includes( 'good' ) || strengthClass.includes( 'strong' ) ) {
                            $( this ).addClass( 'woocommerce-validated' ).removeClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                        } else {
                            $( this ).removeClass( 'woocommerce-validated' ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                        }
                    } else {
                        $( this ).addClass( 'woocommerce-validated' ).removeClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                    }
                } else {
                    $( this ).removeClass( 'woocommerce-validated' ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
                }
            } );
        } );
    }

    // Poštanski broj — samo cifre, real-time
    $( 'input[name="billing_postcode"]' ).on( 'input', function () {
        let val = $( this ).val().replace( /\D/g, '' );
        $( this ).val( val );

        if ( val.length === 5 ) {
            $( this ).addClass( 'woocommerce-validated' ).removeClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
        }
    } );

    // Inicijalizacija
    updateStep();

    $( '.button-next' ).on( 'click', function ( e ) {
        e.preventDefault();

        if ( validateStep() ) {
            if ( currentStep < totalSteps ) {
                currentStep++;
                updateStep();
            }
        } else {
            alert( i18n.requiredFields );
        }
    } );

    $( '.button-previous' ).on( 'click', function ( e ) {
        e.preventDefault();
        if ( currentStep > 1 ) {
            currentStep--;
            updateStep();
        }
    } );

    // Provjera jakosti lozinke u realnom vremenu
    $( '#reg_password' ).on( 'input', function () {
        const strengthClass = $( '#password_strength' ).attr( 'class' ) || '';

        if ( strengthClass.includes( 'good' ) || strengthClass.includes( 'strong' ) ) {
            $( this ).addClass( 'woocommerce-validated' ).removeClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
        } else {
            $( this ).removeClass( 'woocommerce-validated' ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
        }
    } );

} )( jQuery );
