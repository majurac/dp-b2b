<?php
if (!defined('ABSPATH')) {
    exit;
}


$form_id = get_field('contact_form_id', 'option');
// Figma CON-01 heading; the contact.php template has no block data, so fall back to the design's text.
$title = get_field('title') ?: __('Pošaljite poruku', 'dreampoint-b2b');
$text  = get_field('text');
?>
<div class="contact block" id="contact-form">
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
