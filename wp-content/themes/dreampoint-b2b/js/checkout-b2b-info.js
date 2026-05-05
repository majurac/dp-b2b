( function () {
    'use strict';

    const data = window.dpCheckoutB2B;
    if ( ! data ) return;

    // =========================================================================
    // HELPERS
    // =========================================================================

    function buildRow( label, value ) {
        return `<p><strong>${label}:</strong> ${value}</p>`;
    }

    /**
     * Čeka da se element pojavi u DOM-u — block checkout renderuje async
     * pa setTimeout nije pouzdan. Poziva callback čim selektor postane dostupan.
     */
    function waitForElement( selector, callback ) {
        const existing = document.querySelector( selector );
        if ( existing ) {
            callback( existing );
            return;
        }
        const observer = new MutationObserver( () => {
            const el = document.querySelector( selector );
            if ( el ) {
                observer.disconnect();
                callback( el );
            }
        } );
        observer.observe( document.body, { childList: true, subtree: true } );
    }

    // =========================================================================
    // PRIKAZ READONLY PODATAKA TVRTKE
    // =========================================================================

    function insertBillingInfo( contactFields ) {
        if ( contactFields.querySelector( '.dp-b2b-billing-info' ) ) return;

        const markup = `
            <div class="dp-b2b-billing-info">
                <div class="ma-holder">
                    <div class="ma-header">
                        <div class="wod-title-holder">
                            <h2>${data.label_company_section}</h2>
                        </div>
                    </div>
                    <div class="ma-info">
                        ${buildRow( data.label_company_name, data.billing_company )}
                        ${buildRow( data.label_oib, data.company_oib )}
                        ${buildRow( data.label_phone, data.phone )}
                    </div>
                </div>
                <div class="ma-holder">
                    <div class="ma-header">
                        <div class="wod-title-holder">
                            <h2>${data.label_address_section}</h2>
                        </div>
                    </div>
                    <div class="ma-info">
                        ${buildRow( data.label_address, data.billing_address )}
                        ${buildRow( data.label_postcode, data.postcode )}
                        ${buildRow( data.label_city, data.city )}
                        ${buildRow( data.label_country, data.country )}
                    </div>
                </div>
            </div>
        `;

        contactFields.insertAdjacentHTML( 'beforeend', markup );
    }

    // =========================================================================
    // VIDLJIVOST SHIPPING POLJA (dostava na drugu adresu)
    // =========================================================================

    function handleShippingVisibility() {
        const checkbox        = document.getElementById( 'custom-checkbox-use-billing' );
        const shippingWrapper = document.getElementById( 'shipping-fields' );
        if ( checkbox && shippingWrapper ) {
            shippingWrapper.style.display = checkbox.checked ? 'none' : 'block';
        }
    }

    function observeCheckoutChanges( checkoutContainer ) {
        const shippingMethodContainer = document.getElementById( 'shipping-method' );
        if ( ! shippingMethodContainer ) return;

        const observer = new MutationObserver( handleShippingVisibility );
        observer.observe( checkoutContainer, { childList: true, subtree: true } );
        observer.observe( shippingMethodContainer, { childList: true, subtree: true } );

        handleShippingVisibility();

        checkoutContainer.addEventListener( 'change', function ( e ) {
            if ( e.target.id === 'custom-checkbox-use-billing' ) {
                handleShippingVisibility();
            }
        } );
    }

    // =========================================================================
    // INIT
    // =========================================================================

    waitForElement( '.wc-block-checkout__contact-fields', insertBillingInfo );
    waitForElement( '.wc-block-checkout', observeCheckoutChanges );

} )();
