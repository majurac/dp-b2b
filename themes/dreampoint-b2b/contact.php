<?php
    /**
     * Template name: Contact
     *
     * This is the template that displays Contact Us page.
     * Please note that this is the WordPress construct of pages
     * and that other 'pages' on your WordPress site may use a
     * different template.
     *
     * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
     *
     * @package Dreampoint_B2B
     */
    
    get_header();;;
    $form_title = get_field('form_title');
    $form_text = get_field('form_text');
    $contact_form_id = get_field('contact_form_id', 'option');
    $company_logo = get_field('logo', 'option');
    $company_info = get_field('company_info', 'option');
    ?>

<div id="contact-page">
    <div class="contact-form block">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-intro">
                        <?php if ($form_title) : ?>
                            <h2><?php echo esc_html($form_title); ?></h2>
                        <?php endif; ?>
                        <?php if ($form_text) : ?>
                            <p><?php echo esc_html($form_text); ?></p>
                        <?php endif; ?>
                    </div>
                    <!-- /.form-intro -->
                    <div class="contact-form-in custom-form">
                        <?php echo do_shortcode('[contact-form-7 id="' . esc_attr($contact_form_id) . '"]'); ?>
                    </div>
                    <!-- /.contact-form -->
                </div>
                <!-- /.col-md-6 -->
                <div class="col-md-6">
                    <div class="company-info">
                        <?php if (!empty($company_logo) && !empty($company_logo['url'])) : ?>
                        <div class="company-info-logo">
                            <img 
                                src="<?php echo esc_url($company_logo['url']); ?>" 
                                alt="<?php echo esc_attr($company_logo['alt'] ?: get_bloginfo('name') . ' Logo'); ?>"
                                width="<?php echo isset($company_logo['width']) ? absint($company_logo['width']) : ''; ?>"
                                height="<?php echo isset($company_logo['height']) ? absint($company_logo['height']) : ''; ?>"
                            >
                        </div>
                        <!-- /.company-info-logo -->
                        <?php endif; ?>
                        <?php if (!empty($company_info)) : ?>
                        <div class="company-info-items">
                            <?php foreach ($company_info as $info) : ?>
                                <p><strong><?php echo esc_html($info['label']); ?></strong> <?php echo wp_kses_post($info['value']); ?></p>
                            <?php endforeach; ?>
                        </div>
                        <!-- /.company-info-items -->
                        <?php endif; ?>
                    </div>
                    <!-- /.company-info -->
                </div>
                <!-- /.col-md-6 -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.container -->
    </div>
    <!-- /.contact-form block -->
</div>
<!-- /#contact-page -->

<?php get_footer(); ?>