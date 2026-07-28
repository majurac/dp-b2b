<?php
/**
 * WooCommerce My Account Customizations
 * Handles account fields, validation, and menu modifications
 * 
 * @package Dreampoint_B2B
 * @version 1.1.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// ============================================================================
// REGISTRATION FORM - First Name & Last Name Validation
// ============================================================================

/**
 * Validate First Name and Last Name fields on registration
 * 
 * @param WP_Error $errors Validation errors object
 * @param string $username Username being registered
 * @param string $email Email being registered
 * @return WP_Error Modified errors object
 */
add_filter('woocommerce_registration_errors', 'dreampoint_b2b_validate_registration_names', 10, 3);
function dreampoint_b2b_validate_registration_names($errors, $username, $email) {
    // Validate first name
    if (isset($_POST['billing_first_name']) && empty($_POST['billing_first_name'])) {
        $errors->add(
            'billing_first_name_error', 
            __('Ime je obavezno!', 'dreampoint-b2b')
        );
    }
    
    // Validate last name
    if (isset($_POST['billing_last_name']) && empty($_POST['billing_last_name'])) {
        $errors->add(
            'billing_last_name_error', 
            __('Prezime je obavezno!', 'dreampoint-b2b')
        );
    }
    
    return $errors;
}

/**
 * Save First Name and Last Name on user registration
 * Stores both as user meta and native WordPress user fields
 * 
 * @param int $customer_id Newly created customer ID
 */
add_action('woocommerce_created_customer', 'dreampoint_b2b_save_registration_names');
function dreampoint_b2b_save_registration_names($customer_id) {
    // Verify nonce for security (WooCommerce handles this, but double-check)
    if (!isset($_POST['woocommerce-register-nonce'])) {
        return;
    }
    
    // Save first name
    if (isset($_POST['billing_first_name'])) {
        $first_name = sanitize_text_field($_POST['billing_first_name']);
        update_user_meta($customer_id, 'billing_first_name', $first_name);
        update_user_meta($customer_id, 'first_name', $first_name); // Also save to native field
    }
    
    // Save last name
    if (isset($_POST['billing_last_name'])) {
        $last_name = sanitize_text_field($_POST['billing_last_name']);
        update_user_meta($customer_id, 'billing_last_name', $last_name);
        update_user_meta($customer_id, 'last_name', $last_name); // Also save to native field
    }
}

// ============================================================================
// MY ACCOUNT MENU - Customize Navigation Items
// ============================================================================

/**
 * Modify My Account menu items
 * Translates and reorders menu items
 * 
 * @param array $items Original menu items
 * @return array Modified menu items
 */
add_filter('woocommerce_account_menu_items', 'dreampoint_b2b_modify_account_menu', 999);
function dreampoint_b2b_modify_account_menu($items) {
    $new_items = array(
        'dashboard'       => __('Lični podaci', 'dreampoint-b2b'),
        'orders'          => __('Moje narudžbe', 'dreampoint-b2b'),
        'edit-address'    => __('Adrese', 'dreampoint-b2b'),
        'komercijalist'   => __( 'Prodajni predstavnik', 'dreampoint-b2b' ),
        'customer-logout' => __('Odjava', 'dreampoint-b2b'),
    );
    
    return $new_items;
}


/**
 * Add 'is-active' class to Dashboard when on Edit Account page
 * Fixes active state highlighting
 * 
 * @param array $classes Menu item classes
 * @param string $endpoint Current endpoint
 * @return array Modified classes
 */
add_filter('woocommerce_account_menu_item_classes', 'dreampoint_b2b_dashboard_active_class', 10, 2);
function dreampoint_b2b_dashboard_active_class($classes, $endpoint) {
    // Get current WooCommerce endpoint
    $current_endpoint = WC()->query->get_current_endpoint();
    
    // Add 'is-active' to Dashboard if on edit-account page
    if ($endpoint === 'dashboard' && $current_endpoint === 'edit-account') {
        $classes[] = 'is-active';
    }
    
    return $classes;
}

// ============================================================================
// REGISTRATION FORM - Privacy Policy Checkbox
// ============================================================================

/**
 * Remove default WooCommerce privacy policy text
 */
remove_action('woocommerce_register_form', 'wc_registration_privacy_policy_text', 20);

/**
 * Add custom privacy policy checkbox
 * Croatian translation with proper formatting
 */
add_action('woocommerce_register_form', 'dreampoint_b2b_custom_privacy_checkbox', 20);
function dreampoint_b2b_custom_privacy_checkbox() {
    $privacy_url = get_privacy_policy_url();
    
    if (!$privacy_url) {
        return; // Exit if no privacy policy page set
    }
    
    ?>
    <div class="woocommerce-privacy-policy-text custom-checkbox">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__accept_toc accept-terms">
            <input 
                class="woocommerce-form__input woocommerce-form__input-checkbox" 
                name="accept_toc" 
                type="checkbox" 
                id="accept_toc" 
                value="1" 
                required 
                aria-required="true"
            >
            <span>
                <?php 
                printf(
                    __('Pročitao/la sam i razumijem Dream Point d.o.o. <a href="%s" target="_blank" rel="noopener">Politiku privatnosti</a> koja objašnjava kako se moji podaci koriste i kako ih mogu povući ili izmijeniti.', 'dreampoint-b2b'),
                    esc_url($privacy_url)
                );
                ?>
            </span>
        </label>
    </div>
    <?php
}

// ============================================================================
// ACCOUNT FORM - Phone Field
// ============================================================================

/**
 * Add phone field to My Account edit form
 */
add_action('woocommerce_edit_account_form', 'dreampoint_b2b_add_phone_field_to_account');
function dreampoint_b2b_add_phone_field_to_account() {
    $user_id = get_current_user_id();
    $phone = get_user_meta($user_id, 'billing_phone', true);
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_phone">
            <?php esc_html_e('Telefon', 'dreampoint-b2b'); ?>
            <span class="required">*</span>
        </label>
        <input 
            type="tel" 
            class="woocommerce-Input woocommerce-Input--phone input-text" 
            name="account_phone" 
            id="account_phone" 
            value="<?php echo esc_attr($phone); ?>" 
            placeholder="<?php esc_attr_e('Broj telefona', 'dreampoint-b2b'); ?>"
            pattern="[0-9+\s\-\(\)]+"
            title="<?php esc_attr_e('Unesite važeći broj telefona', 'dreampoint-b2b'); ?>"
        />
    </p>
    <?php
}

/**
 * Save phone field from My Account form
 * 
 * @param int $user_id User ID being updated
 */
add_action('woocommerce_save_account_details', 'dreampoint_b2b_save_phone_field', 10, 1);
function dreampoint_b2b_save_phone_field($user_id) {
    // Verify nonce (WooCommerce handles this)
    if (!isset($_POST['save-account-details-nonce'])) {
        return;
    }
    
    if (isset($_POST['account_phone'])) {
        $phone = sanitize_text_field($_POST['account_phone']);
        
        // Basic phone validation
        if (!empty($phone) && !preg_match('/^[0-9+\s\-\(\)]+$/', $phone)) {
            wc_add_notice(__('Neispravan format telefona.', 'dreampoint-b2b'), 'error');
            return;
        }
        
        update_user_meta($user_id, 'billing_phone', $phone);
    }
}

// ============================================================================
// ADMIN USER PROFILE - Phone Field
// ============================================================================

/**
 * Add phone field to admin user profile
 * 
 * @param WP_User $user User object being edited
 */
add_action('show_user_profile', 'dreampoint_b2b_admin_phone_field');
add_action('edit_user_profile', 'dreampoint_b2b_admin_phone_field');
function dreampoint_b2b_admin_phone_field($user) {
    if (!current_user_can('edit_user', $user->ID)) {
        return;
    }
    
    $phone = get_user_meta($user->ID, 'billing_phone', true);
    ?>
    <h3><?php esc_html_e('Dodatne Informacije', 'dreampoint-b2b'); ?></h3>
    <table class="form-table">
        <tr>
            <th>
                <label for="billing_phone">
                    <?php esc_html_e('Telefon', 'dreampoint-b2b'); ?>
                </label>
            </th>
            <td>
                <input 
                    type="tel" 
                    name="billing_phone" 
                    id="billing_phone" 
                    value="<?php echo esc_attr($phone); ?>" 
                    class="regular-text" 
                    placeholder="<?php esc_attr_e( 'Broj telefona', 'dreampoint-b2b' ); ?>"
                />
                <p class="description">
                    <?php esc_html_e('Broj telefona korisnika za WooCommerce narudžbe.', 'dreampoint-b2b'); ?>
                </p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Save phone field from admin user profile
 * 
 * @param int $user_id User ID being updated
 */
add_action('personal_options_update', 'dreampoint_b2b_save_admin_phone_field');
add_action('edit_user_profile_update', 'dreampoint_b2b_save_admin_phone_field');
function dreampoint_b2b_save_admin_phone_field($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }
    
    if (isset($_POST['billing_phone'])) {
        $phone = sanitize_text_field($_POST['billing_phone']);
        update_user_meta($user_id, 'billing_phone', $phone);
    }
}

// ============================================================================
// THANK YOU PAGE - Hide Customer Details
// ============================================================================

/**
 * Hide customer details section on Thank You page
 * Cleaner order confirmation display
 * 
 * @param string $located Template location
 * @param string $template_name Template name
 * @param array $args Template arguments
 * @return string Modified template location
 */
add_filter('wc_get_template', 'dreampoint_b2b_hide_customer_details_thankyou', 10, 3);
function dreampoint_b2b_hide_customer_details_thankyou( $located, $template_name, $args ) {
    if ( ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'view-order' ) ) && $template_name === 'order/order-details-customer.php' ) {
        return '';
    }
    return $located;
}

// ============================================================================
// ACCOUNT FORM - Sync Names Between Fields
// ============================================================================

/**
 * Save billing names from account form and sync with WordPress user fields
 * Ensures consistency across billing, user meta, and WordPress core fields
 * 
 * @param int $user_id User ID being updated
 */
add_action('woocommerce_save_account_details', 'dreampoint_b2b_sync_account_names', 20, 1);
function dreampoint_b2b_sync_account_names($user_id) {
    // Verify nonce (WooCommerce handles this)
    if (!isset($_POST['save-account-details-nonce'])) {
        return;
    }
    
    $user_data = array('ID' => $user_id);
    
    // First name - sync across all fields
    if (isset($_POST['account_first_name'])) {
        $first_name = sanitize_text_field($_POST['account_first_name']);
        
        // Update billing meta
        update_user_meta($user_id, 'billing_first_name', $first_name);
        
        // Update WordPress native field
        $user_data['first_name'] = $first_name;
    }
    
    // Last name - sync across all fields
    if (isset($_POST['account_last_name'])) {
        $last_name = sanitize_text_field($_POST['account_last_name']);
        
        // Update billing meta
        update_user_meta($user_id, 'billing_last_name', $last_name);
        
        // Update WordPress native field
        $user_data['last_name'] = $last_name;
    }
    
    // Display name
    if (isset($_POST['account_display_name'])) {
        $display_name = sanitize_text_field($_POST['account_display_name']);
        $user_data['display_name'] = $display_name;
    }
    
    // Update WordPress user
    if (count($user_data) > 1) { // Only if we have data to update
        wp_update_user($user_data);
    }
}

// ============================================================================
// VALIDATION - Phone Field Requirement (Optional)
// ============================================================================

/**
 * Make phone field required on account edit
 * Uncomment to enable
 */
/*
add_action('woocommerce_save_account_details_errors', 'dreampoint_b2b_validate_phone_required', 10, 1);
function dreampoint_b2b_validate_phone_required($args) {
    if (empty($_POST['account_phone'])) {
        $args->add('error', __('Telefon je obavezan!', 'dreampoint-b2b'));
    }
}
*/

// ============================================================================
// CLEANUP - Remove Unused Account Fields (Optional)
// ============================================================================

/**
 * Remove unwanted default WooCommerce account fields
 * Uncomment fields you want to remove
 */
/*
add_filter('woocommerce_save_account_details_required_fields', 'dreampoint_b2b_remove_account_fields');
function dreampoint_b2b_remove_account_fields($required_fields) {
    // unset($required_fields['account_display_name']); // Remove display name requirement
    // unset($required_fields['account_email']); // Remove email requirement (NOT RECOMMENDED)
    return $required_fields;
}
*/

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get formatted phone number
 * Formats phone for display
 * 
 * @param int $user_id User ID
 * @return string Formatted phone number
 */
function dreampoint_b2b_get_formatted_phone($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $phone = get_user_meta($user_id, 'billing_phone', true);
    
    if (empty($phone)) {
        return '';
    }
    
    // Basic formatting for display (adjust to your needs)
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    return $phone;
}

/**
 * Check if user has complete billing information
 * Useful for conditional displays
 * 
 * @param int $user_id User ID
 * @return bool True if complete
 */
function dreampoint_b2b_has_complete_billing($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $required_fields = array(
        'billing_first_name',
        'billing_last_name',
        'billing_phone',
        'billing_address_1',
        'billing_city',
        'billing_postcode',
    );
    
    foreach ($required_fields as $field) {
        $value = get_user_meta($user_id, $field, true);
        if (empty($value)) {
            return false;
        }
    }
    
    return true;
}


// ============================================================================
// REGISTRATION - Copy Billing to Shipping on Account Creation
// ============================================================================

/**
 * Copy billing fields to shipping fields when a new customer registers
 * This ensures the "Use same address for billing" checkbox is checked by default
 * on the block checkout page
 *
 * @param int $customer_id Newly created customer ID
 */
add_action('woocommerce_created_customer', 'dreampoint_b2b_copy_billing_to_shipping');
function dreampoint_b2b_copy_billing_to_shipping($customer_id) {
    $billing_fields = [
        'first_name', 
        'last_name', 
        'company', 
        'address_1', 
        'address_2', 
        'city', 
        'postcode', 
        'country', 
        'state', 
        'phone'
    ];
    
    foreach ($billing_fields as $field) {
        $billing_value = get_user_meta($customer_id, 'billing_' . $field, true);
        if (!empty($billing_value)) {
            update_user_meta($customer_id, 'shipping_' . $field, $billing_value);
        }
    }
}

add_filter('woocommerce_default_address_fields', function($fields) {
    $fields['state']['required'] = false;
    return $fields;
});

add_filter( 'woocommerce_my_account_my_orders_actions', function( $actions ) {
	if ( isset( $actions['refund'] ) ) {
		$actions['refund']['name'] = __( 'Povrat', 'dreampoint-b2b' );
	}
	return $actions;
}, 101 );