<?php 

// Display commercialist contact info (uses assigned_komercijalist meta)
function display_commercialist_contact_info($user_id) {
    // Get the selected komercijalist ID from the user's meta
    $selected_komercijalist_id = get_user_meta($user_id, 'assigned_komercijalist', true);

    if (!$selected_komercijalist_id) {
        return; // No komercijalist selected, exit the function
    }

    // Get the commercialist's name and ACF fields
    $commercialist_name  = get_the_title($selected_komercijalist_id);
    $commercialist_phone = get_field('phone_commercialist', $selected_komercijalist_id);
    $commercialist_email = get_field('email_commercialist', $selected_komercijalist_id);

    // Ensure both fields are available before displaying
    if ($commercialist_phone || $commercialist_email) {

        echo '<div class="commercialist-heading">';
        echo '<div class="commercialist-name">' . esc_html($commercialist_name) . '</div>';
        echo '<p>' . esc_html__('Prodajni predstavnik', 'dreampoint-b2b') . '</p>';
        echo '</div>';

        if ($commercialist_phone) {
            echo '<p><a href="tel:' . esc_attr($commercialist_phone) . '">' . esc_html($commercialist_phone) . '</a></p>';
        }
        if ($commercialist_email) {
            echo '<p><a href="mailto:' . esc_attr($commercialist_email) . '">' . esc_html($commercialist_email) . '</a></p>';
        }

    }
}


// Menu tab label/položaj se definiše u inc/my-account.php (dreampoint_b2b_modify_account_menu) —
// jedinstveni izvor istine za redoslijed/nazive My Account menija.

// Register endpoint for Komercijalist page
function add_komercijalist_endpoint() {
    add_rewrite_endpoint('komercijalist', EP_ROOT | EP_PAGES);
}
add_action('init', 'add_komercijalist_endpoint');

// Add content to the Komercijalist endpoint
function komercijalist_endpoint_content() {
    $current_user_id = get_current_user_id();
    $selected_komercijalist_id = get_user_meta($current_user_id, 'assigned_komercijalist', true);

    if ($selected_komercijalist_id) {
        // Get Komercijalist post and fields
        $komercijalist = get_post($selected_komercijalist_id);
        $phone_commercialist = get_field('phone_commercialist', $selected_komercijalist_id);
        $mobile_commercialist = get_field('mobile_commercialist', $selected_komercijalist_id);
        $email_commercialist = get_field('email_commercialist', $selected_komercijalist_id);

        // Display the commercialist info inside the new HTML structure
        echo '<div class="commercialists-wrapper">';
        echo '    <div class="commercialist-header">';
        echo '        <h3><img src="' . get_template_directory_uri() . '/img/ico/UserGear.svg" alt=""> ' . esc_html__('Vaš komercijalist', 'dreampoint-b2b') . '</h3>';
        echo '    </div>';
        echo '    <div class="commercialist-info">';
        echo '        <p><strong>' . esc_html__('Ime i prezime', 'dreampoint-b2b') . '</strong>: ' . esc_html($komercijalist->post_title) . '</p>';
        echo '        <p><strong>' . esc_html__('Broj telefona', 'dreampoint-b2b') . '</strong>: ' . esc_html($phone_commercialist) . '</p>';
        echo '        <p><strong>' . esc_html__('Broj mobitela', 'dreampoint-b2b') . '</strong>: ' . esc_html($mobile_commercialist) . '</p>';
        echo '        <p><strong>' . esc_html__('Email', 'dreampoint-b2b') . '</strong>: <a href="mailto:' . esc_attr($email_commercialist) . '">' . esc_html($email_commercialist) . '</a></p>';
        echo '    </div>';
        echo '</div>';
    } else {
        // Fallback message if no Komercijalist is assigned
        echo '<p>' . esc_html__('No Komercijalist assigned.', 'dreampoint-b2b') . '</p>';
    }
}
add_action('woocommerce_account_komercijalist_endpoint', 'komercijalist_endpoint_content');

// Ensure endpoint is added to query vars
function add_komercijalist_query_vars($vars) {
    $vars[] = 'komercijalist';
    return $vars;
}
add_filter('query_vars', 'add_komercijalist_query_vars');

// Flush rewrite rules on theme activation to register new endpoint
function flush_rewrite_rules_on_activation() {
    add_komercijalist_endpoint();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'flush_rewrite_rules_on_activation');

?>