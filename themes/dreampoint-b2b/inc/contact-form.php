<?php
/**
 * Contact Form 7 auto-prefill for logged-in B2B users.
 *
 * @package Dreampoint_B2B
 */

defined('ABSPATH') || exit;

add_action('template_redirect', 'dreampoint_b2b_register_contact_prefill');
function dreampoint_b2b_register_contact_prefill(): void {
    if (!is_page_template('contact.php')) {
        return;
    }

    add_action('wp_footer', 'dreampoint_b2b_prefill_cf7_via_js');
}

function dreampoint_b2b_prefill_cf7_via_js(): void {
    if (!is_user_logged_in()) {
        return;
    }

    $user_id  = get_current_user_id();
    $user     = get_userdata($user_id);
    $customer = new WC_Customer($user_id);

    $data = [
        'your-name'       => $user->first_name,
        'last-name'       => $user->last_name,
        'your-email'      => $user->user_email,
        'your-tel'        => get_user_meta($user_id, 'billing_phone', true),
        'company-name'    => get_user_meta($user_id, 'billing_company', true),
        'company-oib'     => get_user_meta($user_id, 'billing_oib', true),
        'company-address' => $customer->get_billing_address(),
        'company-zip'     => $customer->get_billing_postcode(),
        'company-city'    => $customer->get_billing_city(),
        'company-country' => WC()->countries->countries[$customer->get_billing_country()] ?? '',
    ];

    $data = array_filter($data);

    if (empty($data)) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var fields = <?php echo wp_json_encode($data); ?>;

        Object.keys(fields).forEach(function(name) {
            var input = document.querySelector(
                'input[name="' + name + '"], textarea[name="' + name + '"]'
            );

            if (input && !input.value) {
                input.value = fields[name];
            }
        });
    });
    </script>
    <?php
}
