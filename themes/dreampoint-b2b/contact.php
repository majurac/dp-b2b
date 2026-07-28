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
    
    get_header();
    ?>

<div id="contact-page" class="inner-page">
    <div class="inner-content">
        <?php get_template_part('blocks/templates/contact-info'); ?>
        <?php get_template_part('blocks/templates/contact-form'); ?>
    </div>
    <!-- /.container -->
</div>
<!-- /#contact-page -->

<?php get_footer(); ?>