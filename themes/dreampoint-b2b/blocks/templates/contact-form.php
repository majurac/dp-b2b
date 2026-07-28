<?php
if (!defined('ABSPATH')) {
    exit;
}


$form_id = get_field('contact_form_id', 'option');
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
