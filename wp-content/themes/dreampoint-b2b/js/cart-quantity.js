/**
 * Cart Quantity Controls
 * Handles plus/minus buttons for cart item quantities
 * 
 * @package Dreampoint_B2B
 * @version 1.0.1
 */

jQuery(function($) {
    'use strict';
    
    /**
     * Handle minus button click
     * Decreases quantity by step value, respecting minimum
     */
    $('body').on('click', '.minus', function() {
        const $input = $(this).closest('.number').find('input.qty');
        const min = parseFloat($input.attr('min')) || 0;
        const step = parseFloat($input.attr('step')) || 1;
        let val = parseFloat($input.val()) || 0;
        
        if (val > min) {
            $input.val(val - step).trigger('change');
        }
    });
    
    /**
     * Handle plus button click
     * Increases quantity by step value, respecting maximum
     */
    $('body').on('click', '.plus', function() {
        const $input = $(this).closest('.number').find('input.qty');
        const max = parseFloat($input.attr('max')) || Infinity;
        const step = parseFloat($input.attr('step')) || 1;
        let val = parseFloat($input.val()) || 0;
        let newVal = val + step;
        
        if (newVal > max) {
            alert( ( typeof dpCartQty !== 'undefined' ? dpCartQty.maxQty : 'Maksimalna količina:' ) + ' ' + max );
            newVal = max;
        }
        
        $input.val(newVal).trigger('change');
    });
    
    /**
     * Auto-update cart when quantity changes
     * Enables and triggers the update cart button
     */
    $('body').on('change', 'input.qty', function() {
        $('[name="update_cart"]').prop('disabled', false).trigger('click');
    });
});
