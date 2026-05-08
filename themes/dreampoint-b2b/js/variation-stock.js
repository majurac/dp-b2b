/**
 * Variation Stock Status Display
 * Shows stock availability when product variation is selected
 * 
 * @package Dreampoint_B2B
 * @version 1.0.1
 */

jQuery(function($) {
    'use strict';
    
    // Get translated strings from localized data
    const inStockText = stockText.inStock || 'Proizvod na stanju';
    const outStockText = stockText.outStock || 'Proizvod nije na stanju';
    
    const $form = $('.variations_form');
    
    if (!$form.length) {
        return;
    }
    
    /**
     * Handle variation found event
     * Display appropriate stock status message
     */
    $form.on('found_variation', function(event, variation) {
        // Remove any existing stock status messages
        $('.stock-status').remove();
        
        // Determine stock status class and text
        const status = variation.is_in_stock ? 'in-stock' : 'out-of-stock';
        const text = variation.is_in_stock ? inStockText : outStockText;
        
        // Prepend stock status message to wrapper
        $('.stock-status-wrapper').prepend(
            `<p class="stock-status ${status}">${text}</p>`
        );
    });
    
    /**
     * Handle variation reset event
     * Remove stock status message when variation is cleared
     */
    $form.on('reset_image', function() {
        $('.stock-status').remove();
    });
});
