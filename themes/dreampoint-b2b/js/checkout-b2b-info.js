( function () {
    'use strict';

    const data = window.dpCheckoutB2B;
    if ( ! data ) return;

    const SEL = {
        checkout:   '.wc-block-checkout',
        main:       '.wc-block-checkout__main',
        sidebar:    '.wc-block-checkout__sidebar',
        actions:    '.wc-block-checkout__actions',
        actionsRow: '.wc-block-checkout__actions_row',
        native:     '.wc-block-components-checkout-place-order-button',
        items:      '.wp-block-woocommerce-checkout-order-summary-cart-items-block',
        lowerSummary: '.checkout-order-summary-block-fill-wrapper',
    };

    // Stanje harmonike proizvoda preživljava Blocks re-render (DOM se može ponovo napraviti, varijabla ostaje).
    let summaryExpanded = false;
    // Native dugme koje trenutno promatramo (disabled / loading sinkronizacija).
    let boundNative = null;
    let nativeObserver = null;

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Red "Labela: vrijednost". Vrijednosti dolaze iz PHP-a već escapirane (esc_html),
     * prazne vrijednosti se ne prikazuju (npr. kontakt osoba / e-mail bez podataka).
     */
    function buildRow( label, value ) {
        if ( ! value ) return '';
        return `<p><span>${label}:</span> <strong>${value}</strong></p>`;
    }

    function joinNonEmpty( parts, separator ) {
        return parts.filter( Boolean ).join( separator );
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

    /**
     * Desktop raspored: sidebar je desno od glavne kolone. Ispod toga (mobitel / uski tablet) Blocks slaže
     * sažetak iznad / ispod forme i ima vlastiti sklopivi sažetak.
     */
    function isSideBySide() {
        const main = document.querySelector( SEL.main );
        const sidebar = document.querySelector( SEL.sidebar );
        if ( ! main || ! sidebar ) return false;
        const m = main.getBoundingClientRect();
        const s = sidebar.getBoundingClientRect();
        return s.width > 0 && s.left >= m.right - 1;
    }

    // =========================================================================
    // PRIKAZ READONLY PODATAKA TVRTKE (Figma CH-01: kartica 11148:37357)
    // =========================================================================
    // PDV broj se namjerno ne prikazuje (potvrđena greška u Figmi, odluka vlasnika 2026-11-25).
    // Adresa naplate je red u istoj kartici (Figma "Adresa (sjedište)"), ne zasebna kartica.

    function buildBillingInfoMarkup() {
        const fullAddress = joinNonEmpty(
            [
                data.billing_address,
                joinNonEmpty( [ data.postcode, data.city ], ' ' ),
                data.country,
            ],
            ', '
        );

        const note = data.note_erp
            ? `<div class="ma-erp-notice">
                    <img src="${data.lock_icon}" width="24" height="24" alt="">
                    <p>${data.note_erp}</p>
                </div>`
            : '';

        return `
            <div class="dp-b2b-billing-info">
                <div class="ma-holder">
                    <div class="ma-header">
                        <div class="wod-title-holder">
                            <h2>${data.label_company_section}</h2>
                        </div>
                    </div>
                    <div class="ma-columns">
                        <div class="ma-info">
                            ${buildRow( data.label_company_name, data.billing_company )}
                            ${buildRow( data.label_oib, data.company_oib )}
                            ${buildRow( data.label_contact_person, data.contact_person )}
                        </div>
                        <div class="ma-info">
                            ${buildRow( data.label_phone, data.phone )}
                            ${buildRow( data.label_email, data.email )}
                            ${buildRow( data.label_address, fullAddress )}
                        </div>
                    </div>
                    ${note}
                </div>
            </div>
        `;
    }

    // Idempotentno: Blocks može ponovo renderirati contact-fields, pa se markup vraća samo ako ga nema.
    // Položaj (kao na Cotra B2B: kartica tvrtke pa checkbox): odmah ispred bloka s checkboxom
    // "Koristite adresu naplate za dostavu" (#custom-checkbox-use-billing), tj. kartica tvrtke → checkbox →
    // dodatna adresa za dostavu. Blokovi koje je napravio React se ne premještaju, premješta se samo naša kartica.
    function insertBillingInfo( contactFields ) {
        if ( ! contactFields ) return;

        const content = contactFields.querySelector( ':scope > .wc-block-components-checkout-step__content' ) || contactFields;
        const anchor = content.querySelector( ':scope > .wp-block-silkypress-input-field' );
        let card = contactFields.querySelector( '.dp-b2b-billing-info' );

        if ( ! card ) {
            const tpl = document.createElement( 'template' );
            tpl.innerHTML = buildBillingInfoMarkup().trim();
            card = tpl.content.firstElementChild;
        }

        if ( anchor ) {
            if ( card.parentElement !== content || card.nextElementSibling !== anchor ) content.insertBefore( card, anchor );
        } else if ( card.parentElement !== content ) {
            content.appendChild( card );
        }
    }

    function ensureBillingInfo() {
        insertBillingInfo( document.querySelector( '.wc-block-checkout__contact-fields' ) );
    }

    // =========================================================================
    // HARMONIKA: POPIS PROIZVODA U SAŽETKU (Figma CH-01 11148:37418 "PREGLED PROIZVODA")
    // =========================================================================
    // Nativni popis ostaje netaknut (nema dupliciranja podataka): dodaje se samo gumb-toggle kao prvo dijete
    // wrappera, a sakrivanje radi CSS preko data-dp-collapsed. Jedan sažetak na svakoj širini (Blocks prelazi na
    // složeni raspored na <= 991px):
    //  - desktop (sidebar desno): sažetak u sidebaru;
    //  - tablet / mobitel: sažetak koji Blocks renderira u bloku akcija (ispod polja i uvjeta, ispred gumba), a
    //    nativni sklopivi sažetak iznad forme sakriva CSS tek kad ovaj ima harmoniku (data-dp-summary="lower").

    const CHEVRON = '<svg class="dp-summary-toggle__icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M13.28 5.97 8.93 10.32a.92.92 0 0 1-1.3 0L3.28 5.97" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    function ensureSummaryAccordion() {
        const sideBySide = isSideBySide();
        const checkout = document.querySelector( SEL.checkout );
        let activeToggle = false;

        document.querySelectorAll( SEL.items ).forEach( ( wrapper ) => {
            let toggle = wrapper.querySelector( ':scope > .dp-summary-toggle' );
            const inSidebar = !! wrapper.closest( SEL.sidebar );
            const inLower = !! wrapper.closest( SEL.lowerSummary );

            // Harmonika samo na wrapperu koji je u trenutnom rasporedu vidljiv (sidebar na desktopu, donji sažetak
            // inače). Blocks drži oba u DOM-u, pa se neaktivna kopija uvijek očisti (bez dupliranih kontrola / ID-eva).
            if ( sideBySide ? ! inSidebar : ! inLower ) {
                if ( toggle ) toggle.remove();
                wrapper.removeAttribute( 'data-dp-collapsed' );
                wrapper.querySelectorAll( ':scope > #dp-summary-items' ).forEach( ( el ) => el.removeAttribute( 'id' ) );
                return;
            }

            const content = wrapper.querySelector( ':scope > :not(.dp-summary-toggle)' );
            if ( content && ! content.id ) content.id = 'dp-summary-items';
            activeToggle = true;

            if ( ! toggle ) {
                toggle = document.createElement( 'button' );
                toggle.type = 'button';
                toggle.className = 'dp-summary-toggle';
                toggle.innerHTML = `<span>${data.label_summary_items}</span>${CHEVRON}`;
                wrapper.prepend( toggle );
            }
            if ( content ) toggle.setAttribute( 'aria-controls', content.id );

            toggle.setAttribute( 'aria-expanded', summaryExpanded ? 'true' : 'false' );
            wrapper.setAttribute( 'data-dp-collapsed', summaryExpanded ? 'false' : 'true' );
        } );

        // Nativni sklopivi sažetak (iznad forme) sakriva se samo CSS-om i samo dok donji sažetak s harmonikom stvarno
        // postoji; bez JS-a / bez harmonike oba nativna sažetka ostaju upotrebljiva (fallback).
        if ( checkout ) {
            if ( ! sideBySide && activeToggle ) checkout.setAttribute( 'data-dp-summary', 'lower' );
            else checkout.removeAttribute( 'data-dp-summary' );
        }
    }

    // =========================================================================
    // GUMB "KRENI NA NARUDŽBU" (Figma CH-01 11148:37439) — proxy na NATIVNI gumb
    // =========================================================================
    // Ne radi vlastiti Store API zahtjev: klik se prosljeđuje nativnom place-order gumbu pa validacija,
    // uvjeti, dostava i plaćanje rade točno kao i prije. Nativni redak je samo vizualno sakriven kad
    // custom gumb postoji (CSS po atributu data-dp-submit); ako JS ne uspije, nativni gumb ostaje vidljiv.

    const ARROW = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M12.025 4.942 17.083 10l-5.058 5.058M2.917 10h14.025" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    function isNativeBusy( native ) {
        return native.disabled
            || native.getAttribute( 'aria-disabled' ) === 'true'
            || native.classList.contains( 'wc-block-components-button--loading' )
            || !! native.querySelector( '.wc-block-components-spinner' );
    }

    function syncProceedState() {
        const custom = document.getElementById( 'dp-proceed-button' );
        if ( ! custom || ! boundNative ) return;
        const busy = isNativeBusy( boundNative );
        custom.disabled = boundNative.disabled;
        custom.setAttribute( 'aria-busy', busy && ! boundNative.disabled ? 'true' : 'false' );
        custom.classList.toggle( 'is-loading', busy && ! boundNative.disabled );
    }

    function observeNative( native ) {
        if ( boundNative === native ) return;
        if ( nativeObserver ) nativeObserver.disconnect();
        boundNative = native;
        if ( ! native ) return;
        nativeObserver = new MutationObserver( syncProceedState );
        nativeObserver.observe( native, { attributes: true, attributeFilter: [ 'disabled', 'class', 'aria-disabled' ], childList: true, subtree: true } );
    }

    function ensureProceedButton() {
        const checkout = document.querySelector( SEL.checkout );
        const native = document.querySelector( SEL.native );
        if ( ! checkout ) return;

        if ( ! native ) {
            // Bez nativnog gumba nema se što proslijediti: ukloni custom i ostavi nativni fallback.
            document.getElementById( 'dp-proceed-wrap' )?.remove();
            checkout.removeAttribute( 'data-dp-submit' );
            observeNative( null );
            return;
        }

        const sideBySide = isSideBySide();
        const sidebar = document.querySelector( SEL.sidebar );
        const actions = document.querySelector( SEL.actions );
        const row = document.querySelector( SEL.actionsRow );

        let wrap = document.getElementById( 'dp-proceed-wrap' );
        if ( ! wrap ) {
            wrap = document.createElement( 'div' );
            wrap.id = 'dp-proceed-wrap';
            wrap.className = 'dp-proceed';
            wrap.innerHTML = `<button type="button" id="dp-proceed-button" class="button button--xl dp-proceed-button"><span>${data.label_proceed}</span>${ARROW}</button>`;
        }

        // Desktop: unutar kartice sažetka (kraj sidebara). Mobitel: točno ispred nativnog retka s gumbom.
        if ( sideBySide && sidebar ) {
            if ( wrap.parentElement !== sidebar || wrap !== sidebar.lastElementChild ) sidebar.appendChild( wrap );
        } else if ( actions && row ) {
            if ( wrap.parentElement !== actions || wrap.nextElementSibling !== row ) actions.insertBefore( wrap, row );
        } else {
            // Nema gdje smjestiti custom gumb: nativni ostaje vidljiv.
            wrap.remove();
            checkout.removeAttribute( 'data-dp-submit' );
            observeNative( null );
            return;
        }

        checkout.setAttribute( 'data-dp-submit', 'custom' );
        observeNative( native );
        syncProceedState();
    }

    // Jedan delegirani listener (postavlja se jednom): preživljava re-rendere, bez dupliranja.
    function bindDelegatedClicks( checkout ) {
        if ( checkout.dataset.dpBound === '1' ) return;
        checkout.dataset.dpBound = '1';

        checkout.addEventListener( 'click', function ( e ) {
            const toggle = e.target.closest( '.dp-summary-toggle' );
            if ( toggle ) {
                e.preventDefault();
                summaryExpanded = ! summaryExpanded;
                ensureSummaryAccordion();
                return;
            }

            const proceed = e.target.closest( '#dp-proceed-button' );
            if ( proceed ) {
                e.preventDefault();
                const native = document.querySelector( SEL.native );
                // Zaštita od dvostrukog slanja: ništa dok je nativni gumb onemogućen ili u obradi.
                if ( ! native || proceed.disabled || isNativeBusy( native ) ) return;
                native.click();
            }
        } );
    }

    // =========================================================================
    // DOSTAVA NA ADRESU NAPLATE (A9 / Silkypress #custom-checkbox-use-billing)
    // =========================================================================
    // Označen checkbox = dostava na zaključanu adresu naplate: dodatna forma adrese za dostavu je skrivena, pa polja
    // za dostavu moraju STVARNO sadržavati upotrebljivu adresu. Početno stanje checkboxa odlučuje PHP
    // (inc/checkout-logic.php): označen za (A) praznu adresu dostave i (B) adresu dostave jednaku adresi naplate;
    // neoznačen za (C) drugu adresu, (D) djelomično popunjenu i (E) neupotrebljivu adresu naplate.
    // Sinkronizacija shipping := billing ide samo kroz nativni Blocks store (wc/store/cart, setShippingAddress), i to:
    //  - jednom pri učitavanju, u scenariju A, tek kad su podaci kupca učitani;
    //  - kad kupac sam označi checkbox (isTrusted).
    // Nikad pri re-renderu, nikad kad postoji druga adresa i nikad nakon što je kupac dirao checkbox.

    // Prazne vrijednosti za provjere "upotrebljiva adresa" (isto što i PHP dreampoint_b2b_customer_address_usable()).
    function countryLocale( country ) {
        const data = window.wcSettings && window.wcSettings.countryData;
        return ( data && data[ country ] && data[ country ].locale ) || {};
    }

    function addressUsable( a ) {
        if ( ! a || ! a.country || ! a.address_1 || ! a.city || ! a.first_name || ! a.last_name ) return false;
        const loc = countryLocale( a.country );
        const postcodeRequired = loc.postcode ? loc.postcode.required !== false : true;
        const stateRequired = loc.state ? !! loc.state.required : false;
        if ( postcodeRequired && ! a.postcode ) return false;
        if ( stateRequired && ! a.state ) return false;
        return true;
    }

    // "Stvarno prazna" adresa dostave: ulica, druga linija, grad i poštanski broj nisu popunjeni. Država (WooCommerce
    // default) i županija (Blocks je unaprijed odabere) NISU dio unesene adrese; isto pravilo kao u PHP-u.
    function addressEmpty( a ) {
        return ! ( a && ( a.address_1 || a.address_2 || a.city || a.postcode ) );
    }

    function cartStore() {
        const wpData = window.wp && window.wp.data;
        if ( ! wpData ) return null;
        const select = wpData.select( 'wc/store/cart' );
        const actions = wpData.dispatch( 'wc/store/cart' );
        if ( ! select || ! actions || typeof select.getCustomerData !== 'function' || typeof actions.setShippingAddress !== 'function' ) return null;
        return { select, actions };
    }

    function customerAddresses() {
        const store = cartStore();
        if ( ! store ) return null;
        const data = store.select.getCustomerData() || {};
        return { billing: data.billingAddress || {}, shipping: data.shippingAddress || {} };
    }

    function syncShippingToBilling() {
        const store = cartStore();
        if ( ! store ) return false;
        const b = ( store.select.getCustomerData() || {} ).billingAddress || {};
        // Neupotrebljiva adresa naplate se nikad ne kopira (ne prepisuje postojeću adresu dostave).
        if ( ! addressUsable( b ) ) return false;
        store.actions.setShippingAddress( {
            first_name: b.first_name || '',
            last_name: b.last_name || '',
            company: b.company || '',
            address_1: b.address_1 || '',
            address_2: b.address_2 || '',
            city: b.city || '',
            state: b.state || '',
            postcode: b.postcode || '',
            country: b.country || '',
            phone: b.phone || '',
        } );
        return true;
    }

    // Jednokratna početna sinkronizacija (scenarij A: prazna adresa dostave + upotrebljiva adresa naplate).
    // Stanje checkboxa (React state Silkypress pluginom) se primijeni tek NAKON prvog rendera, a promjena svojstva
    // `checked` ne proizvodi DOM mutaciju, pa se uvjeti provjeravaju kratkim ograničenim intervalom (najviše 15 s, mjereno vremenom, ne brojem provjera)
    // umjesto na MutationObserveru. Sinkronizacija se izvede najviše jednom (`done`), nikad nakon kupčeve interakcije
    // (`checkboxTouched`) i nikad ako adresa dostave nije stvarno prazna ili adresa naplate nije upotrebljiva.
    const initialSync = { done: false, startedAt: 0, checkboxTouched: false, timer: 0 };
    const INITIAL_SYNC_MAX_MS = 15000;

    function finishInitialSync() {
        initialSync.done = true;
        if ( initialSync.timer ) {
            window.clearInterval( initialSync.timer );
            initialSync.timer = 0;
        }
    }

    function maybeInitialShippingSync() {
        if ( initialSync.done ) return;
        if ( initialSync.checkboxTouched || ( initialSync.startedAt && Date.now() - initialSync.startedAt > INITIAL_SYNC_MAX_MS ) ) {
            finishInitialSync();
            return;
        }
        const checkbox = document.getElementById( 'custom-checkbox-use-billing' );
        const addr = customerAddresses();
        // Podaci kupca ili checkbox još nisu učitani: pokušaj ponovno pri sljedećoj provjeri.
        if ( ! checkbox || ! addr || ! addr.billing.address_1 ) return;
        // Nema što sinkronizirati: postoji (djelomična ili jednaka) adresa dostave ili naplata nije upotrebljiva.
        if ( ! addressEmpty( addr.shipping ) || ! addressUsable( addr.billing ) ) {
            finishInitialSync();
            return;
        }
        // Scenarij A: čekamo da React primijeni zadano "označeno" stanje, pa tek tada kopiramo adresu naplate.
        if ( ! checkbox.checked ) return;
        finishInitialSync();
        syncShippingToBilling();
    }

    function startInitialSyncWatch() {
        if ( initialSync.timer || initialSync.done ) return;
        initialSync.startedAt = Date.now();
        initialSync.timer = window.setInterval( function () {
            maybeInitialShippingSync();
            handleShippingVisibility();
        }, 250 );
    }

    // Kad adresa naplate nije upotrebljiva za dostavu (scenarij E), checkbox se ne nudi (nema kontradiktornog stanja).
    function ensureCheckboxAvailability() {
        const checkbox = document.getElementById( 'custom-checkbox-use-billing' );
        const addr = customerAddresses();
        if ( ! checkbox || ! addr || ! addr.billing.address_1 ) return;
        const usable = addressUsable( addr.billing );
        if ( checkbox.disabled === usable ) checkbox.disabled = ! usable;
    }

    // =========================================================================
    // VIDLJIVOST SHIPPING POLJA (dostava na drugu adresu)
    // =========================================================================

    function handleShippingVisibility() {
        const checkbox        = document.getElementById( 'custom-checkbox-use-billing' );
        const shippingWrapper = document.getElementById( 'shipping-fields' );
        if ( checkbox && shippingWrapper ) {
            // Forma se skriva samo ako je checkbox označen I polja za dostavu sadrže upotrebljivu adresu. Ako to ne
            // možemo provjeriti ili adresa nije upotrebljiva, obavezna polja ostaju vidljiva (nema lažno valjanog stanja).
            const addr = customerAddresses();
            shippingWrapper.style.display = checkbox.checked && addr && addressUsable( addr.shipping ) ? 'none' : 'block';
        }
    }

    // Sve je idempotentno, pa jedan grupirani prolaz nakon svake Blocks promjene DOM-a nema petlje.
    // setTimeout (ne rAF): rAF se ne izvršava u pozadinskom tabu pa bi se re-render popravio tek po povratku.
    let syncQueued = false;
    function syncCheckoutEnhancements() {
        syncQueued = false;
        ensureBillingInfo();
        ensureSummaryAccordion();
        ensureProceedButton();
        ensureCheckboxAvailability();
        handleShippingVisibility();
    }

    function queueSync() {
        if ( syncQueued ) return;
        syncQueued = true;
        window.setTimeout( syncCheckoutEnhancements, 30 );
    }

    function observeCheckoutChanges( checkoutContainer ) {
        // Blocks renderira korake postupno: #shipping-method može postojati tek nakon .wc-block-checkout, pa se na
        // njega ne čeka — promatra se cijeli kontejner (subtree već obuhvaća sve korake).
        const observer = new MutationObserver( queueSync );
        observer.observe( checkoutContainer, { childList: true, subtree: true } );

        bindDelegatedClicks( checkoutContainer );
        // Blocks mijenja raspored (sidebar desno / složen) tek nakon vlastitog resize obrađivanja: provjera kasni
        // 250 ms da isSideBySide() čita novi raspored, a ne stari.
        window.addEventListener( 'resize', () => window.setTimeout( queueSync, 250 ) );

        syncCheckoutEnhancements();
        startInitialSyncWatch();

        checkoutContainer.addEventListener( 'change', function ( e ) {
            if ( e.target.id === 'custom-checkbox-use-billing' ) {
                if ( e.isTrusted ) initialSync.checkboxTouched = true;
                if ( e.target.checked && e.isTrusted ) syncShippingToBilling();
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
