<?php
/**
 * Template Name: Segment Landing
 *
 * Reusable template for the Lifestyle / Toys / Outdoor Segment Landing pages
 * (docs/active/homepage-segment-landing-architecture.md). Structurally
 * identical to page.php — the segment behavior comes entirely from the
 * `dp_page_segment_context` ACF field (Page Attributes sidebar) read by the
 * blocks placed in post_content via inc/homepage-segments.php helpers
 * (ADR-009 Phase B).
 *
 * @package Dreampoint_B2B
 */

get_header();
?>
<main id="primary-content" class="site-main segment-landing">
    <?php the_content(); ?>
</main>
<?php
get_footer();
