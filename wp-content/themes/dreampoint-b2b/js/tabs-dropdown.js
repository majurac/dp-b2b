/**
 * Product Tabs to Dropdown
 * Converts product tabs to dropdown select on mobile devices
 * 
 * @package Dreampoint_B2B
 * @version 1.0.1
 */

jQuery(function($) {
    'use strict';
    
    const $wrapper = $('.woocommerce-tabs.wc-tabs-wrapper');
    
    if (!$wrapper.length) {
        return;
    }
    
    const $tabs = $wrapper.find('ul.tabs.wc-tabs li');
    const $panels = $wrapper.find('.woocommerce-Tabs-panel');
    let $select;
    
    /**
     * Initialize tabs dropdown for mobile view
     * Creates select dropdown from existing tabs
     */
    function initDropdown() {
        // Cleanup existing dropdown
        if ($select) {
            if ($select.data('select2')) {
                $select.select2('destroy');
            }
            $select.remove();
            $select = null;
        }
        
        // Mobile view (< 767px)
        if ($(window).width() < 767 && $tabs.length) {
            // Create select element
            $select = $('<select class="wc-tabs-dropdown"/>');
            
            // Build options from tabs
            $tabs.each(function() {
                const $tab = $(this);
                const id = $tab.find('a').attr('href');
                const text = $tab.text().trim();
                const active = $tab.hasClass('active');
                
                $select.append($('<option/>', {
                    value: id,
                    text: text,
                    selected: active
                }));
            });
            
            // Add dropdown to wrapper and hide original tabs
            $wrapper.prepend($select);
            $wrapper.find('ul.tabs.wc-tabs').hide();
            
            // Initialize select2 if available
            if ($.fn.select2) {
                $select.select2({
                    minimumResultsForSearch: Infinity,
                    width: '100%'
                });
            }
            
            // Handle dropdown change event
            $select.on('change', function() {
                const target = $(this).val();
                
                // Hide all panels and tabs
                $panels.hide().removeClass('active');
                $tabs.removeClass('active');
                
                // Show selected panel and activate corresponding tab
                $(target).show().addClass('active');
                $tabs.find('a[href="' + target + '"]').parent().addClass('active');
            });
            
            // Show only active panel
            $panels.hide().filter('.active').show();
        } 
        // Desktop view
        else {
            $wrapper.find('ul.tabs.wc-tabs').show();
            $panels.hide().filter('.active').show();
        }
    }
    
    // Initialize on page load
    initDropdown();
    
    // Reinitialize on window resize with debouncing
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(initDropdown, 250);
    });
});
