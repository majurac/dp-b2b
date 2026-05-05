/**
 * Product Single Page Scripts
 * Handles quantity controls and product-specific functionality
 * Loaded only on single product pages for better performance
 * 
 * @package Dreampoint_B2B
 * @version 1.1.0
 */

jQuery(function ($) {
    'use strict';
    
    // ========================================================================
    // QUANTITY CONTROLS (Plus/Minus Buttons)
    // ========================================================================
    
    /**
     * Handle minus button click
     * Decreases quantity by step value, respecting minimum
     */
    $(document).on('click', '#product-single-main .product-main-info .addcart-wrapper .minus', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $input = $button.closest('.quantity, .number').find('input.qty');
        const min = parseFloat($input.attr('min')) || 1;
        const step = parseFloat($input.attr('step')) || 1;
        const currentValue = parseFloat($input.val()) || min;
        let newValue = currentValue - step;
        
        // Don't go below minimum
        if (newValue < min) {
            newValue = min;
            
            // Visual feedback when minimum reached
            $button.addClass('disabled');
            setTimeout(() => $button.removeClass('disabled'), 200);
        }
        
        $input.val(newValue).trigger('change');
        
        // Update button states
        updateQuantityButtons($input, newValue);
        
        return false;
    });
    
    /**
     * Handle plus button click
     * Increases quantity by step value, respecting maximum
     */
    $(document).on('click', '#product-single-main .product-main-info .addcart-wrapper .plus', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $input = $button.closest('.quantity, .number').find('input.qty');
        const max = parseFloat($input.attr('max')) || Infinity;
        const step = parseFloat($input.attr('step')) || 1;
        const currentValue = parseFloat($input.val()) || 0;
        let newValue = currentValue + step;
        
        // Check if exceeds maximum
        if (newValue > max) {
            newValue = max;
            
            // Show notification if max reached
            if (max !== Infinity) {
                showMaxQuantityNotification(max);
                
                // Visual feedback
                $button.addClass('disabled');
                setTimeout(() => $button.removeClass('disabled'), 200);
            }
        }
        
        $input.val(newValue).trigger('change');
        
        // Update button states
        updateQuantityButtons($input, newValue);
        
        return false;
    });
    
    /**
     * Handle manual input changes
     * Validates and constrains quantity value
     */
    $(document).on('change blur', '#product-single-main input.qty', function() {
        const $input = $(this);
        const min = parseFloat($input.attr('min')) || 1;
        const max = parseFloat($input.attr('max')) || Infinity;
        let value = parseFloat($input.val()) || min;
        
        // Constrain to min/max
        if (value < min) {
            value = min;
            $input.val(value);
        }
        
        if (value > max && max !== Infinity) {
            value = max;
            $input.val(value);
            showMaxQuantityNotification(max);
        }
        
        // Update button states
        updateQuantityButtons($input, value);
    });
    
    /**
     * Update plus/minus button disabled states
     * 
     * @param {jQuery} $input - Quantity input element
     * @param {number} value - Current quantity value
     */
    function updateQuantityButtons($input, value) {
        const min = parseFloat($input.attr('min')) || 1;
        const max = parseFloat($input.attr('max')) || Infinity;
        const $container = $input.closest('.quantity, .number');
        const $minusBtn = $container.find('.minus');
        const $plusBtn = $container.find('.plus');
        
        // Update minus button
        if (value <= min) {
            $minusBtn.addClass('disabled').attr('disabled', true);
        } else {
            $minusBtn.removeClass('disabled').removeAttr('disabled');
        }
        
        // Update plus button
        if (max !== Infinity && value >= max) {
            $plusBtn.addClass('disabled').attr('disabled', true);
        } else {
            $plusBtn.removeClass('disabled').removeAttr('disabled');
        }
    }
    
    /**
     * Show maximum quantity notification
     * Uses Toastify if available, falls back to alert
     * 
     * @param {number} max - Maximum allowed quantity
     */
    function showMaxQuantityNotification(max) {
        if (typeof Toastify !== 'undefined') {
            Toastify({
                text: ( typeof dpProductSingle !== 'undefined' ? dpProductSingle.maxQty : 'Maksimalna količina:' ) + ' ' + max,
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f0ad4e",
                stopOnFocus: true,
                close: true
            }).showToast();
        } else {
            // Fallback to alert (only if Toastify not loaded)
            alert( ( typeof dpProductSingle !== 'undefined' ? dpProductSingle.maxQty : 'Maksimalna količina:' ) + ' ' + max );
        }
    }
    
    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    
    /**
     * Initialize quantity controls on page load
     * Sets correct button states based on initial values
     */
    function initQuantityControls() {
        $('#product-single-main input.qty').each(function() {
            const $input = $(this);
            const value = parseFloat($input.val()) || 1;
            updateQuantityButtons($input, value);
        });
    }
    
    // Initialize on DOM ready
    initQuantityControls();
    
    // Store original price HTML for reset functionality
    if ($('.product-summary .price-container').length) {
        window.originalPriceHtml = $('.product-summary .price-container').html();
    }
    
    // Reinitialize after variation change (WooCommerce)
    $(document).on('found_variation', 'form.variations_form', function(event, variation) {
        // Reinitialize quantity controls
        setTimeout(initQuantityControls, 100);
        
        // Update price display when variation is selected
        if (variation.price_html) {
            $('.product-summary .price-container').html(variation.price_html);
        }
        
        // Note: Image switching is handled by sliders.js (lines 478-494)
    });
    
    // Reinitialize after cart update
    $(document).on('updated_cart_totals', function() {
        initQuantityControls();
    });
    
    /**
     * Handle variation reset
     * Reset price to original when variation is cleared
     */
    $(document).on('reset_data', 'form.variations_form', function() {
        // Store original price HTML on first load
        if (!window.originalPriceHtml) {
            window.originalPriceHtml = $('.product-summary .price-container').html();
        }
        
        // Restore original price
        if (window.originalPriceHtml) {
            $('.product-summary .price-container').html(window.originalPriceHtml);
        }
    });
    
    // ========================================================================
    // ADDITIONAL PRODUCT PAGE FUNCTIONALITY (Optional - Add as needed)
    // ========================================================================
    
    /**
     * Smooth scroll to reviews when clicking rating
     * Improves UX by taking user directly to review section
     */
    $(document).on('click', '.woocommerce-product-rating', function(e) {
        const $reviews = $('#reviews');
        
        if ($reviews.length) {
            e.preventDefault();
            
            $('html, body').animate({
                scrollTop: $reviews.offset().top - 100
            }, 500);
        }
    });
    
    /**
     * Add to cart button loading state
     * Visual feedback during AJAX add to cart
     */
    $(document).on('click', '.single_add_to_cart_button', function() {
        const $button = $(this);
        
        // Add loading class
        $button.addClass('loading');
        
        // Remove loading class after AJAX complete
        $(document).one('added_to_cart', function() {
            $button.removeClass('loading');
        });
    });
    
    /**
     * Keyboard accessibility for quantity inputs
     * Arrow up/down keys to increase/decrease quantity
     */
    $(document).on('keydown', '#product-single-main input.qty', function(e) {
        const $input = $(this);
        const step = parseFloat($input.attr('step')) || 1;
        
        // Arrow Up
        if (e.which === 38) {
            e.preventDefault();
            $input.closest('.quantity, .number').find('.plus').trigger('click');
        }
        
        // Arrow Down
        if (e.which === 40) {
            e.preventDefault();
            $input.closest('.quantity, .number').find('.minus').trigger('click');
        }
    });
    
    // ========================================================================
    // DEBUG MODE (Development Only)
    // ========================================================================
    
    /**
     * Console log for debugging
     * Add ?debug=1 to URL to enable
     */
    if (window.console && window.location.search.indexOf('debug=1') !== -1) {
        console.log('Product Single JS loaded successfully');
        console.log('Quantity controls initialized');
        console.log('Toastify available:', typeof Toastify !== 'undefined');
    }
});
