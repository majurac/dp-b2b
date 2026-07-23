/**
 * AJAX Search with Debouncing and iOS Support
 * Optimized version with loading states and error handling
 * 
 * @package Dreampoint_B2B
 * @version 1.1.0
 */

jQuery(document).ready(function ($) {
    'use strict';
    
    // ========================================================================
    // UTILITY FUNCTIONS
    // ========================================================================
    
    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    }
    
    function debounce(func, wait) {
        let timeout;
        return function () {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }
    
    function toggleOverlay(state) {
        $('.menu-overlay').toggleClass('active', state);
        $('html').toggleClass('fixed', state);
        $('body').toggleClass('fixed', state);
    }
    
    function showLoading() {
        const loadingHTML = '<div class="ajax-search-loading"><span class="spinner"></span> ' + ( typeof dpAjax !== 'undefined' ? dpAjax.loading : 'Pretraga...' ) + '</div>';
        $('#ajax-search-result').html(loadingHTML);
    }
    
    function showError(message) {
        const errorHTML = `<div class="ajax-search-error"><p>${message}</p></div>`;
        $('#ajax-search-result').html(errorHTML);
    }
    
    // ========================================================================
    // CACHE & STATE MANAGEMENT
    // ========================================================================
    
    let currentRequest = null;
    const searchCache = {};
    
    // ========================================================================
    // MAIN SEARCH HANDLER
    // ========================================================================
    
    function handleSearchInput() {
        const searchTerm = $(this).val().trim();
        
        if (searchTerm === '') {
            $('#ajax-search-result').empty();
            return;
        }
        
        if (searchTerm.length < 2) {
            $('#ajax-search-result').html(
                '<div class="ajax-search-hint"><p>' + ( typeof dpAjax !== 'undefined' ? dpAjax.minChars : 'Unesite najmanje 2 znaka...' ) + '</p></div>'
            );
            return;
        }
        
        if (searchCache[searchTerm]) {
            $('#ajax-search-result').html(searchCache[searchTerm]);
            return;
        }
        
        if (currentRequest && currentRequest.readyState !== 4) {
            currentRequest.abort();
        }
        
        showLoading();
        
        currentRequest = $.ajax({
            url: dpAjax.url,
            type: 'GET',
            data: {
                action: 'search_products',
                searchTerm: searchTerm,
                nonce: dpAjax.nonce
            },
            timeout: 10000,
            success: function (response) {
                if (response && response.trim() !== '') {
                    searchCache[searchTerm] = response;
                    $('#ajax-search-result').html(response);
                } else {
                    showError( typeof dpAjax !== 'undefined' ? dpAjax.noResults : 'Nema rezultata pretrage.' );
                }
            },
            error: function (xhr, status, error) {
                if (status === 'abort') return;
                if (status === 'timeout') {
                    showError( typeof dpAjax !== 'undefined' ? dpAjax.timeout : 'Pretraga traje predugo. Pokušajte ponovo.' );
                } else {
                    showError( typeof dpAjax !== 'undefined' ? dpAjax.error : 'Greška pri pretrazi. Pokušajte ponovo.' );
                }
                if (window.console && window.console.error) {
                    console.error('AJAX Search Error:', status, error);
                }
            },
            complete: function () {
                currentRequest = null;
            }
        });
    }
    
    // ========================================================================
    // SEARCH POPUP MANAGEMENT
    // ========================================================================
    
    const $searchBtn = $('.search-area .toggle-search');
    const $searchPopup = $('.search-popup');
    const $searchClose = $('.search-popup .search-close');
    const $searchInput = $('#s');
    const $searchModalContent = $('.search-popup .modal-content');
    
    /**
     * Close search popup and reset state
     *
     * ORDER MATTERS:
     * Focus must be moved OUT of the modal BEFORE aria-hidden="true" is set.
     * If any element inside the modal has focus when aria-hidden is applied,
     * the browser throws: "Blocked aria-hidden on a focused element".
     */
    function closeSearchPopup() {
        // 1. Return focus to trigger button BEFORE touching aria-hidden
        $searchBtn.trigger('focus');
        // 2. Now safe to hide from assistive technology
        $searchPopup.attr('aria-hidden', 'true');
        // 3. Hide visually
        $searchPopup.removeClass('active');
        // 4. Remove input from tab order while modal is closed
        $searchInput.attr('tabindex', '-1');
        // 5. Reset content and overlay
        $('#ajax-search-result').empty();
        $searchInput.val('');
        toggleOverlay(false);
    }
    
    /**
     * Open search popup with proper focus
     *
     * ORDER MATTERS:
     * aria-hidden must be removed BEFORE focus is set on the input,
     * otherwise the browser blocks the focus attempt.
     */
    function openSearchPopup() {
        // 1. Remove aria-hidden FIRST — browser now allows focus inside modal
        $searchPopup.attr('aria-hidden', 'false');
        // 2. Restore input to tab order
        $searchInput.attr('tabindex', '0');
        // 3. Show modal
        $searchPopup.addClass('active');
        toggleOverlay(true);
        // 4. Focus input — short delay ensures CSS transition has started
        setTimeout(function () {
            $searchInput.trigger('focus');
        }, 50);
    }
    
    // ========================================================================
    // EVENT HANDLERS
    // ========================================================================
    
    $searchInput.on('input', debounce(handleSearchInput, 500));
    
    $searchBtn.on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openSearchPopup();
    });
    
    $searchClose.on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        closeSearchPopup();
    });
    
    $searchPopup.on('click', function (e) {
        if (e.target === this) {
            closeSearchPopup();
        }
    });
    
    $searchModalContent.on('click', function(e) {
        e.stopPropagation();
    });
    
    $(document).on('keyup', function(e) {
        if (e.key === 'Escape' && $searchPopup.hasClass('active')) {
            closeSearchPopup();
        }
    });
    
    if (isIOS()) {
        $('#ajax-search-result').on('click', '.sajx-nofund-prod, .ajax-search-hint', function () {
            $(this).remove();
        });
    }
    
    // ========================================================================
    // PERFORMANCE OPTIMIZATION
    // ========================================================================
    
    setInterval(function() {
        Object.keys(searchCache).forEach(key => delete searchCache[key]);
    }, 5 * 60 * 1000);
    
    $searchInput.on('focus', function() {
        // Analytics / prefetch hook
    });
});
