<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
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
<main<?php echo dreampoint_b2b_header_renders_main_target() ? '' : ' id="primary-content" tabindex="-1"'; ?> class="site-main">
    <?php the_content(); ?>
</main>
<?php
get_footer();