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
        'dashboard'       => __('Moj profil', 'dreampoint-b2b'),
        'orders'          => __('Moje narudžbe', 'dreampoint-b2b'),
        'edit-address'    => __('Adrese', 'dreampoint-b2b'),
        'komercijalist'   => __( 'Prodajni predstavnik', 'dreampoint-b2b' ),
        'customer-logout' => __('Odjava', 'dreampoint-b2b'),
    );
    
    return $new_items;
}


/**
 * Orders table column order and labels (Figma MPR-05): number, status, date, total, actions.
 * Presentation only: the orders query and the action buttons are untouched, and any column added
 * by another plugin is kept after the known ones.
 *
 * @param array $columns Column id => label
 * @return array
 */
add_filter( 'woocommerce_account_orders_columns', 'dreampoint_b2b_account_orders_columns' );
function dreampoint_b2b_account_orders_columns( $columns ) {
    $labels = array(
        'order-number'  => __( 'Broj narudžbe', 'dreampoint-b2b' ),
        'order-status'  => __( 'Status', 'dreampoint-b2b' ),
        'order-date'    => __( 'Datum', 'dreampoint-b2b' ),
        'order-total'   => __( 'Ukupno', 'dreampoint-b2b' ),
        'order-actions' => __( 'Akcija', 'dreampoint-b2b' ),
    );

    $ordered = array();
    foreach ( $labels as $id => $label ) {
        if ( isset( $columns[ $id ] ) ) {
            $ordered[ $id ] = $label;
            unset( $columns[ $id ] );
        }
    }

    return $ordered + $columns;
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
// REGISTRATION FORM - Privacy Policy Text
// ============================================================================

/**
 * Remove default WooCommerce privacy policy text from the registration form.
 * Closed B2B system: legal pages are not available to guests, so the registration form shows
 * no privacy/terms text or acceptance checkbox.
 */
remove_action('woocommerce_register_form', 'wc_registration_privacy_policy_text', 20);

// ============================================================================
// ACCOUNT FORM - Phone Field
// ============================================================================

/**
 * The single phone field of the My Account edit form (name/id account_phone, storage billing_phone).
 * Called directly from woocommerce/myaccount/user-form.php ("Podaci o računu" group); it is no longer
 * hooked to woocommerce_edit_account_form, which used to output a second, conflicting copy.
 */
function dreampoint_b2b_add_phone_field_to_account() {
    $user_id = get_current_user_id();
    $phone = get_user_meta($user_id, 'billing_phone', true);
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_phone"><?php esc_html_e('Telefon', 'dreampoint-b2b'); ?>&nbsp;<span class="required">*</span></label>
        <input
            type="tel"
            class="woocommerce-Input woocommerce-Input--text input-text"
            name="account_phone"
            id="account_phone"
            value="<?php echo esc_attr($phone); ?>"
            placeholder="<?php esc_attr_e('Broj telefona', 'dreampoint-b2b'); ?>"
            pattern="[0-9+\s\-\(\)]+"
            title="<?php esc_attr_e('Unesite važeći broj telefona', 'dreampoint-b2b'); ?>"
            required
        />
    </p>
    <?php
}

/**
 * Validate the phone BEFORE anything is saved.
 * WC_Form_Handler::save_account_details() runs woocommerce_save_account_details_errors before
 * wp_update_user() and before woocommerce_save_account_details; any error aborts the whole save.
 * Writes nothing. The phone is required server-side too (missing key, empty, whitespace-only and
 * non-string/array values are rejected); the accepted format is unchanged.
 *
 * @param WP_Error $errors Account-save errors collected by WooCommerce
 */
add_action('woocommerce_save_account_details_errors', 'dreampoint_b2b_validate_account_phone', 10, 1);
function dreampoint_b2b_validate_account_phone($errors) {
    $raw = isset($_POST['account_phone']) ? wp_unslash($_POST['account_phone']) : null;

    // Missing key or an unexpected type (e.g. account_phone[]=x): reject without touching the value.
    if (!is_string($raw)) {
        $errors->add('account_phone_required', __('Unesite broj telefona.', 'dreampoint-b2b'));
        return;
    }

    // sanitize_text_field() trims, so whitespace-only becomes ''.
    $phone = sanitize_text_field($raw);

    if ('' === $phone) {
        $errors->add('account_phone_required', __('Unesite broj telefona.', 'dreampoint-b2b'));
        return;
    }

    if (!preg_match('/^[0-9+\s\-\(\)]+$/', $phone)) {
        $errors->add('account_phone_invalid', __('Neispravan format telefona.', 'dreampoint-b2b'));
    }
}

/**
 * The only writer of billing_phone from the My Account form. Runs only after the validation above
 * passed (WooCommerce skips this hook when any error exists).
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
        update_user_meta($user_id, 'billing_phone', sanitize_text_field(wp_unslash($_POST['account_phone'])));
    }
}

// ============================================================================
// BILLING MASTER DATA - read-only for the customer (client rule)
// ============================================================================
// Customers cannot change company name, OIB or the billing address; they can change shipping data and
// the contact phone. Company/OIB are not in the profile form any more (see above); this block protects
// the billing master fields on the customer paths that can still reach them. Authorised writers
// (registration, wp-admin, importer, APROS) are separate code paths and are not touched: nothing here
// filters update_user_meta or WC_Customer::save() globally.
//
// Hook points (WooCommerce 11.2.1, verified in source):
// - My Account billing endpoint: WC_Form_Handler::save_address() (template_redirect) passes every submitted
//   value through woocommerce_process_myaccount_field_{key} before set_*() and save() -> return the stored value.
// - Classic checkout: woocommerce_checkout_update_customer (inc/checkout-logic.php), fired on the unsaved
//   WC_Customer right before $customer->save().
// - Store API cart/update-customer and Blocks checkout: implemented in inc/checkout-logic.php (not here); the
//   existing-order route and customer-save protections are documented in docs/active/figma-alignment.md
//   sections 53.7-53.8.

/**
 * The protected billing master fields. Not protected on purpose: billing_phone, billing_email, shipping_*.
 * billing_oib is not an address field and has no customer write path any more.
 *
 * @return string[]
 */
function dreampoint_b2b_billing_master_fields(): array {
    return array(
        'billing_company',
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_postcode',
        'billing_state',
        'billing_country',
    );
}

/**
 * Whether the protection applies to this user in the current request: the logged-in customer editing
 * their own data, on an account that existed before this request (an account created during the same
 * request, e.g. at checkout, is still being initialised).
 */
function dreampoint_b2b_billing_master_applies( int $user_id ): bool {
    if ( $user_id <= 0 || get_current_user_id() !== $user_id ) {
        return false;
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return false;
    }

    $registered = strtotime( $user->user_registered . ' UTC' );
    $request    = (int) ( $_SERVER['REQUEST_TIME'] ?? time() );

    return false !== $registered && $registered < $request;
}

/**
 * Persisted master value (user meta). Read before the current request saves anything, so it cannot
 * already contain the submitted value. A mutated WC_Customer getter must not be used for this.
 */
function dreampoint_b2b_stored_billing_master_value( int $user_id, string $key ): string {
    return (string) get_user_meta( $user_id, $key, true );
}

/**
 * Reset the protected billing props of an UNSAVED WC_Customer to the persisted master values (empty
 * stored values stay empty). Must be called before $customer->save(); restoring after the save would
 * only re-read what the same request has just written.
 *
 * @param mixed $customer Expected WC_Customer.
 */
function dreampoint_b2b_restore_billing_master( $customer ): void {
    if ( ! $customer instanceof WC_Customer ) {
        return;
    }

    $user_id = (int) $customer->get_id();
    if ( ! dreampoint_b2b_billing_master_applies( $user_id ) ) {
        return;
    }

    foreach ( dreampoint_b2b_billing_master_fields() as $key ) {
        $setter = 'set_' . $key;
        if ( is_callable( array( $customer, $setter ) ) ) {
            $customer->$setter( dreampoint_b2b_stored_billing_master_value( $user_id, $key ) );
        }
    }
}

// My Account billing endpoint: a submitted value never replaces the master value, and an omitted
// field cannot clear it (the stored value is returned in both cases).
array_map(
    static function ( string $key ): void {
        add_filter(
            'woocommerce_process_myaccount_field_' . $key,
            static function ( $value ) use ( $key ) {
                $user_id = get_current_user_id();
                return dreampoint_b2b_billing_master_applies( $user_id )
                    ? dreampoint_b2b_stored_billing_master_value( $user_id, $key )
                    : $value;
            }
        );
    },
    dreampoint_b2b_billing_master_fields()
);

// UI only (the filters above are the enforcement). Selects cannot be `readonly` and must not be
// `disabled`: save_address() returns without saving when billing_country is not posted, which would also
// drop phone / e-mail changes. They are made inert with a class instead (myaccount.scss).
add_filter( 'woocommerce_address_to_edit', 'dreampoint_b2b_lock_billing_master_fields_ui', 10, 2 );
function dreampoint_b2b_lock_billing_master_fields_ui( $address, $load_address ) {
    if ( 'billing' !== $load_address || ! is_array( $address ) ) {
        return $address;
    }

    foreach ( dreampoint_b2b_billing_master_fields() as $key ) {
        if ( ! isset( $address[ $key ] ) || ! is_array( $address[ $key ] ) ) {
            continue;
        }

        $type = $address[ $key ]['type'] ?? 'text';

        $address[ $key ]['class']   = array_merge( (array) ( $address[ $key ]['class'] ?? array() ), array( 'dp-locked-field' ) );
        $address[ $key ]['custom_attributes']['aria-readonly'] = 'true';

        if ( ! in_array( $type, array( 'country', 'state', 'select' ), true ) ) {
            $address[ $key ]['custom_attributes']['readonly'] = 'readonly';
        }
    }

    return $address;
}

// ============================================================================
// ADMIN USER PROFILE - Legacy importer fields (oib, task_id)
// ============================================================================
// billing_phone u adminu isporučuje samo WooCommerce (Customer billing address).
// b2b-partner-importer (zaštićen) renderira i sprema legacy "oib" i "task_id" uz
// polje Zaduženi komercijalist; ta dva retka se samo skrivaju, meta i importer
// ostaju netaknuti. Aktivni OIB je billing_oib (WooCommerce polje).

/**
 * Sakrij retke legacy polja oib i task_id na ekranu uređivanja korisnika.
 */
function dreampoint_b2b_hide_legacy_importer_profile_fields(): void {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, [ 'user-edit', 'profile' ], true ) ) {
        return;
    }
    echo '<style>#your-profile tr:has(input#oib),#your-profile tr:has(input#task_id){display:none}</style>';
}
add_action( 'admin_head', 'dreampoint_b2b_hide_legacy_importer_profile_fields' );

/**
 * Skriveni inputi se ne smiju spremiti: importerov save poziva update_user_meta
 * kad je ključ u $_POST, što bi kreiralo prazne oib/task_id redove. Prioritet 1
 * se izvršava prije importerovog callbacka (10); komercijalist ostaje nedirnut.
 */
function dreampoint_b2b_drop_legacy_importer_post_keys(): void {
    unset( $_POST['oib'], $_POST['task_id'] );
}
add_action( 'personal_options_update', 'dreampoint_b2b_drop_legacy_importer_post_keys', 1 );
add_action( 'edit_user_profile_update', 'dreampoint_b2b_drop_legacy_importer_post_keys', 1 );

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