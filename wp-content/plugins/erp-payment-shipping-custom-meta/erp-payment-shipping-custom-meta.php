<?php
/**
 * Plugin Name: WooCommerce ERP ID Mapping (Robust)
 * Description: Adds ERP ID field to all WooCommerce shipping methods and payment gateways (classic + block) with reliable saving and pre-fill.
 * Author: Marko Vasic
 * Version: 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add ERP ID to shipping methods
 */
add_filter( 'woocommerce_shipping_instance_form_fields_flat_rate', 'wc_erp_id_add_shipping_field' );
add_filter( 'woocommerce_shipping_instance_form_fields_free_shipping', 'wc_erp_id_add_shipping_field' );
add_filter( 'woocommerce_shipping_instance_form_fields_local_pickup', 'wc_erp_id_add_shipping_field' );

function wc_erp_id_add_shipping_field( $settings ) {
    $settings['erp_id'] = array(
        'title'       => __( 'ERP ID', 'woocommerce' ),
        'type'        => 'text',
        'description' => __( 'Enter ERP ID for this shipping method (used for ERP mapping).', 'woocommerce' ),
        'default'     => '',
        'desc_tip'    => true,
    );
    return $settings;
}

/**
 * Add ERP ID to classic gateways (display only, pre-filled from central storage)
 */
add_action( 'woocommerce_payment_gateways_init', function() {
    $gateways = WC()->payment_gateways()->payment_gateways();
    if ( ! $gateways ) return;

    $central_data = get_option( 'wc_erp_id_all', array() );
    $gateway_values = isset($central_data['gateways']) ? $central_data['gateways'] : array();

    foreach ( $gateways as $gateway ) {
        if ( isset( $gateway->form_fields ) && is_array( $gateway->form_fields ) ) {
            $default_value = isset( $gateway_values[ $gateway->id ] ) ? $gateway_values[ $gateway->id ] : '';
            $gateway->form_fields['erp_id'] = array(
                'title'       => __( 'ERP ID', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'ERP ID (read-only, saved via central ERP ID page).', 'woocommerce' ),
                'default'     => $default_value,
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'readonly' => 'readonly'
                )
            );
        }
    }
});

/**
 * Central admin page for ERP IDs
 */
add_action( 'admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        __( 'ERP ID Mapping', 'woocommerce' ),
        __( 'ERP ID Mapping', 'woocommerce' ),
        'manage_options',
        'wc-erp-id-mapping',
        'wc_erp_id_admin_page'
    );
});

/**
 * Robust save handling using admin_init
 */
add_action( 'admin_init', function() {
    if ( isset($_POST['wc_erp_id_nonce']) && wp_verify_nonce($_POST['wc_erp_id_nonce'], 'save_erp_ids') ) {
        $saved = get_option('wc_erp_id_all', array());

        $posted = isset($_POST['wc_erp_id']) ? $_POST['wc_erp_id'] : array();
        $posted_shipping = isset($posted['shipping']) && is_array($posted['shipping']) ? array_map('sanitize_text_field', $posted['shipping']) : array();
        $posted_gateways = isset($posted['gateways']) && is_array($posted['gateways']) ? array_map('sanitize_text_field', $posted['gateways']) : array();

        $saved['shipping'] = $posted_shipping;
        $saved['gateways'] = $posted_gateways;

        update_option('wc_erp_id_all', $saved);

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>ERP IDs saved!</p></div>';
        });
    }
});

/**
 * Render admin page
 */
function wc_erp_id_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $data = get_option('wc_erp_id_all', array());
    $data['shipping'] = isset($data['shipping']) ? $data['shipping'] : array();
    $data['gateways'] = isset($data['gateways']) ? $data['gateways'] : array();

    // Shipping methods
    $shipping_methods = WC()->shipping()->get_shipping_methods();

    echo '<div class="wrap"><h1>ERP ID Mapping</h1><form method="post">';
    wp_nonce_field( 'save_erp_ids', 'wc_erp_id_nonce' );

    echo '<h2>Shipping Methods</h2><table class="form-table"><tbody>';
    foreach ( $shipping_methods as $method_id => $method ) {
        $key = $method->id;
        $value = isset($data['shipping'][$key]) ? esc_attr($data['shipping'][$key]) : '';
        echo '<tr><th>' . esc_html($method->get_method_title()) . '</th><td><input type="text" name="wc_erp_id[shipping][' . esc_attr($key) . ']" value="' . $value . '" /></td></tr>';
    }
    echo '</tbody></table>';

    // Payment gateways
    $gateways = WC()->payment_gateways()->payment_gateways();
    echo '<h2>Payment Gateways</h2><table class="form-table"><tbody>';
    foreach ( $gateways as $gateway_id => $gateway ) {
        $key = $gateway->id;
    
        // Use title if available, fallback to humanized ID
        if ( method_exists($gateway, 'get_title') && $gateway->get_title() ) {
            $title = $gateway->get_title();
        } elseif ( !empty($gateway->title) ) {
            $title = $gateway->title;
        } else {
            // Fallback: make ID readable (e.g., 'bacs' → 'BACS')
            $title = strtoupper(str_replace('_', ' ', $key));
        }
    
        $value = isset($data['gateways'][$key]) ? esc_attr($data['gateways'][$key]) : '';
        echo '<tr><th>' . esc_html($title) . '</th><td><input type="text" name="wc_erp_id[gateways][' . esc_attr($key) . ']" value="' . $value . '" /></td></tr>';
    }
    echo '</tbody></table>';


    echo '<p class="submit"><input type="submit" class="button-primary" value="Save ERP IDs"></p></form></div>';
}

/**
 * Helper functions
 */

// Get ERP ID for shipping
function wc_erp_id_get_shipping( $method_id, $instance_id ) {
    $shipping_methods = WC()->shipping()->get_shipping_methods();
    if ( isset($shipping_methods[$method_id]) ) {
        $method = $shipping_methods[$method_id];
        if ( method_exists($method,'get_option') ) {
            $value = $method->get_option('erp_id', $instance_id);
            if ($value) return $value;
        }
    }
    $data = get_option('wc_erp_id_all', array());
    return isset($data['shipping'][$method_id]) ? $data['shipping'][$method_id] : '';
}

// Get ERP ID for payment
function wc_erp_id_get_payment( $gateway_id ) {
    $gateways = WC()->payment_gateways()->payment_gateways();
    if ( isset($gateways[$gateway_id]) && method_exists($gateways[$gateway_id],'get_option') ) {
        $value = $gateways[$gateway_id]->get_option('erp_id');
        if ($value) return $value;
    }
    $data = get_option('wc_erp_id_all', array());
    return isset($data['gateways'][$gateway_id]) ? $data['gateways'][$gateway_id] : '';
}
