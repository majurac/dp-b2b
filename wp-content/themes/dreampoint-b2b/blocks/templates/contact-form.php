<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get the selected form type from the block
$form_type = get_field('form_type');

// Default to contact if not set
if (empty($form_type)) {
    $form_type = 'contact';
}

// Map form types to their corresponding option fields
$form_id_mapping = array(
    'contact'         => 'contact_form_id',
    'subscribe'       => 'subscribe_form_id',
    'product_inquiry' => 'product_inquiry_form_id',
    'business_gift'   => 'gift_form_id',
    'gift_package'    => 'gift_package_id'
);

// Get the form ID based on selected type
$form_id_field = isset($form_id_mapping[$form_type]) ? $form_id_mapping[$form_type] : 'contact_form_id';
$form_id = get_field($form_id_field, 'option');

// Exit if no form ID is set
if (empty($form_id)) {
    return;
}

// Get block content
$title = get_field('title');
$text  = get_field('text');
?>
<div class="contact block">
    <div class="container">
        <div class="contact-content">
            <div class="contact-intro">
                <?php if ($title) : ?>
                    <h2><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
                
                <?php if ($text) : ?>
                    <p><?php echo wp_kses_post($text); ?></p>
                <?php endif; ?>
            </div>
            <!-- /.contact-intro -->
            <div class="custom-form">
                <?php echo do_shortcode('[contact-form-7 id="' . esc_attr($form_id) . '"]'); ?>
            </div>
            <!-- /.custom-form -->
        </div>
        <!-- /.contact-content -->
    </div>
</div>
