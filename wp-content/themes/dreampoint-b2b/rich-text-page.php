<?php
    /**
     * Template name: Rich Text
     *
     * This is the template that displays Rich Text Page.
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
    <div id="rich-text-page">
        <div class="container">
            <div class="inner-body block">
                <?php the_content(); ?>
            </div>
            <!-- /.inner-body -->
        </div>
        <!-- /.container -->
    </div>
    <!-- /#rich-text-page -->

    <?php
get_footer();