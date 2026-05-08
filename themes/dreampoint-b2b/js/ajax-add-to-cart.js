/**
 * WooCommerce AJAX Add to Cart & Mini Cart
 * Handles cart sidebar toggle, AJAX add to cart, and notifications
 * 
 * @package Dreampoint_B2B
 * @version 1.2.1 - FIXED: Overlay cleanup after remove from cart
 */

jQuery(function ($) {
    'use strict';
    
    //console.log('AJAX Add to Cart JS loaded v1.2.0');
    
    // ========================================================================
    // UTILITY FUNCTIONS
    // ========================================================================
    
    /**
     * Toggle body overlay and scroll lock
     */
    function toggleOverlay(state) {
        $('.menu-overlay').toggleClass('active', state);
        $('html').toggleClass('fixed', state);
        $('body').toggleClass('fixed', state);
    }
    
    /**
     * Check if cart is currently open
     */
    function isCartOpen() {
        return $('.widget_shopping_cart_content').hasClass('active');
    }
    
    /**
     * Check if tabs are currently open
     */
    function areTabsOpen() {
        return $('.product-tabs-content').hasClass('active');
    }
    
    /**
     * Open cart
     */
    function openCart() {
        //console.log('Opening cart');
        const $cartContent = $('.widget_shopping_cart_content, .my-custom-mini-cart-container');
        $cartContent.addClass('active');
        toggleOverlay(true);
        
        // Focus management
        setTimeout(() => {
            const $firstFocusable = $cartContent.find('a, button').first();
            if ($firstFocusable.length) {
                $firstFocusable.focus();
            }
        }, 100);
    }
    
    /**
     * Close cart
     */
    function closeCart() {
        //console.log('Closing cart');
        const $cartContent = $('.widget_shopping_cart_content, .my-custom-mini-cart-container');
        const $cartBtn = $('.cart-contents, .cart-btn-wrapper a');
        
        $cartContent.removeClass('active');
        toggleOverlay(false);
        
        // Return focus
        if ($cartBtn.length) {
            $cartBtn.first().focus();
        }
    }
    
    // ========================================================================
    // MINI CART TOGGLE
    // ========================================================================
    
    /**
     * Initialize cart toggle functionality
     */
    function initCartToggle() {
        // IMPORTANT: Use more general selector that works in all cases
        const $cartBtn = $('.cart-contents, .cart-btn-wrapper a');
        const $cartContent = $('.widget_shopping_cart_content, .my-custom-mini-cart-container');
        const $cartClose = $('.mini-cart-close');
        
       /* console.log('Init cart toggle:', {
            cartBtn: $cartBtn.length,
            cartContent: $cartContent.length,
            cartClose: $cartClose.length
        });*/
        
        // Exit if elements don't exist
        if (!$cartBtn.length || !$cartContent.length) {
            //console.warn('Cart elements not found!');
            return;
        }
        
        // CRITICAL: Remove ALL existing handlers first to prevent conflicts
        $cartBtn.off('click.cartToggle click');
        
        // Open/close cart on button click
        $cartBtn.on('click.cartToggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            //console.log('Cart button clicked, current state:', isCartOpen());
            
            if (isCartOpen()) {
                closeCart();
            } else {
                openCart();
            }
        });
        
        // Close button
        $cartClose.off('click.cartClose click').on('click.cartClose', function(e) {
            e.preventDefault();
            closeCart();
        });
        
        // Click outside
        $(document).off('click.cartOutside').on('click.cartOutside', function(e) {
            if (!isCartOpen() || areTabsOpen()) {
                return;
            }
            
            const isOutsideCart = !$cartContent.is(e.target) && 
                                 $cartContent.has(e.target).length === 0;
            const isOutsideButton = !$cartBtn.is(e.target) && 
                                   $cartBtn.has(e.target).length === 0;
            
            if (isOutsideCart && isOutsideButton) {
                closeCart();
            }
        });
        
        // ESC key
        $(document).off('keyup.cartEsc').on('keyup.cartEsc', function(e) {
            if (e.key === 'Escape' && isCartOpen()) {
                closeCart();
            }
        });
    }
    
    // ========================================================================
    // WOOCOMMERCE FRAGMENTS
    // ========================================================================
    
    // Flag koji prati da je korisnik uklonio proizvod
    let cartItemRemoved = false;

    $(document.body).on('wc_fragments_loaded wc_fragments_refreshed', function() {
        //console.log('WC Fragments updated');
        const $cartContent = $('.widget_shopping_cart_content, .my-custom-mini-cart-container');
        
        // Re-initialize
        initCartToggle();
        
        // Handle keep-open flag
        if ($cartContent.hasClass('keep-open')) {
            //console.log('Cart flagged to stay open');
            $cartContent.addClass('active').removeClass('keep-open');
            toggleOverlay(true);
        }
        else if ($cartContent.hasClass('active')) {
            setTimeout(() => {
                $cartContent.removeClass('active');
                toggleOverlay(false);
            }, 50);
        }
        else {
            // Novi fragment nema 'active' klasu — osiguraj čisto stanje overlaya.
            // Ovo pokriva slučaj uklanjanja proizvoda iz mini carta: removed_from_cart
            // ukloni active sa starog DOM elementa, ali novi fragment koji WC injektuje
            // nema tu klasu, pa html/body/overlay mogu ostati sa starim klasama.
            toggleOverlay(false);
            cartItemRemoved = false;
        }
    });
    
    $(document.body).on('removed_from_cart', function() {
        //console.log('Item removed from cart');
        cartItemRemoved = true;
        closeCart();
    });
    
    // ========================================================================
    // TOAST NOTIFICATIONS
    // ========================================================================
    
    function showNotification(message, type = 'success') {
        //console.log('Show notification:', message, type);
        
        // Check if Toastify exists
        if (typeof Toastify === 'undefined') {
            //console.warn('Toastify not loaded, using alert');
            alert(message);
            return;
        }
        
        const typeClass = type === 'error' || type === 'danger' 
            ? 'is-danger' 
            : type === 'info' 
                ? 'is-info' 
                : 'is-success';
        
        try {
            Toastify({
                text: message,
                duration: 3000,
                gravity: 'bottom',
                position: 'center',
                stopOnFocus: true,
                className: typeClass,
                close: true,
                style: {
                    background: type === 'error' ? '#f44336' : 
                              type === 'info' ? '#2196F3' : 
                              '#4CAF50'
                }
            }).showToast();
        } catch (error) {
            //console.error('Toastify error:', error);
            alert(message);
        }
    }
    
    // ========================================================================
    // WOOCOMMERCE NATIVE ADD TO CART EVENT
    // ========================================================================
    
    /**
     * Listen to WooCommerce's native added_to_cart event
     * This fires AFTER WooCommerce successfully adds item to cart
     * This is THE RIGHT WAY to handle add to cart in WooCommerce!
     */
    $(document.body).on('added_to_cart', function(e, fragments, cart_hash, $button) {
        //console.log('✅ WooCommerce added_to_cart event fired!');
        //console.log('Fragments:', fragments);
        //console.log('Button:', $button);
        
        // Show success notification
        const message = ( typeof dpAddToCart !== 'undefined' && dpAddToCart.added_to_cart )
            ? dpAddToCart.added_to_cart
            : 'Proizvod dodat u košaricu!';
        
        showNotification(message, 'success');
        
        // Auto-open cart after successful add
        setTimeout(() => {
            openCart();
        }, 100);
    });
    
    // ========================================================================
    // CART QUANTITY UPDATES
    // ========================================================================
    
    $(document).on('change', '.widget_shopping_cart .quantity input', function() {
        const $input = $(this);
        const $form = $input.closest('form');
        
        $('[name="update_cart"]').prop('disabled', false);
        
        clearTimeout(window.cartUpdateTimeout);
        window.cartUpdateTimeout = setTimeout(() => {
            $form.find('[name="update_cart"]').trigger('click');
        }, 500);
    });
    
    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    
    //console.log('Initializing cart toggle...');
    initCartToggle();
    
    // Re-init on AJAX complete
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url && settings.url.indexOf('wc-ajax') !== -1) {
            //console.log('Re-initializing after WC AJAX');
            initCartToggle();
        }
    });
    
    // ========================================================================
    // DEBUG
    // ========================================================================
    
    //console.log('Toastify available:', typeof Toastify !== 'undefined');
    //console.log('WooCommerce AJAX available:', typeof wc_add_to_cart_params !== 'undefined');
    
    if (window.location.search.indexOf('debug=1') !== -1) {
        $(document.body).on('added_to_cart removed_from_cart', function(e) {
            console.log('Cart event:', e.type);
        });
    }
});
