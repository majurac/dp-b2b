<?php
/**
 * Template name: FAQ
 * 
 * Displays FAQ posts with accordion functionality and pagination
 * 
 * @package Dreampoint_B2B
 * @version 1.1.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

get_header();

// ============================================================================
// QUERY SETUP
// ============================================================================

$post_type = 'faq';

// Get current page number (sanitized)
// Check URL parameter first, then paged query var
$current_page = 1;
if (isset($_GET['faq_page'])) {
    $current_page = absint($_GET['faq_page']);
} elseif (get_query_var('paged')) {
    $current_page = absint(get_query_var('paged'));
}

// Ensure minimum page is 1
$current_page = max(1, $current_page);

// WP_Query arguments
$query_args = [
    'post_type'      => $post_type,
    'posts_per_page' => 10,
    'paged'          => $current_page,
    'post_status'    => 'publish',
    'orderby'        => 'menu_order date',
    'order'          => 'ASC',
    'no_found_rows'  => false, // Need this for pagination
];

$faq_query = new WP_Query($query_args);
?>

<div id="faq" class="block">
    <div class="container">
        <div id="faq-body">
            <div class="accordion">
                <?php if ($faq_query->have_posts()) : ?>
                    
                    <?php while ($faq_query->have_posts()) : $faq_query->the_post(); ?>
                        <div class="faq-wrap" id="faq-<?php echo absint(get_the_ID()); ?>">
                            <h3>
                                <?php the_title(); ?>
                            </h3>
                            <div class="content">
                                <?php
                                $answer = get_field('faq_answer');
                                if ($answer) {
                                    echo wp_kses_post($answer);
                                } else {
                                    // Fallback to post content if ACF field empty
                                    the_content();
                                }
                                ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    
                    <!-- Pagination -->
                    <?php if ($faq_query->max_num_pages > 1) : ?>
                        <div class="faq-pagination">
                            <?php
                            $pagination_args = [
                                'total'      => $faq_query->max_num_pages,
                                'current'    => $current_page,
                                'format'     => '?faq_page=%#%',
                                'prev_text'  => '<i class="icon-chevron-left" aria-hidden="true"></i> <span>' . esc_html__('Nazad', 'dreampoint-b2b') . '</span>',
                                'next_text'  => '<span>' . esc_html__('Naprijed', 'dreampoint-b2b') . '</span> <i class="icon-chevron-right" aria-hidden="true"></i>',
                                'type'       => 'list',
                                'mid_size'   => 2,
                                'end_size'   => 1,
                                'add_args'   => false,
                                'aria_label' => esc_attr__('FAQ paginacija', 'dreampoint-b2b'),
                            ];
                            
                            echo paginate_links($pagination_args);
                            ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else : ?>
                    
                    <div class="no-results">
                        <p><?php esc_html_e('Nema dostupnih pitanja.', 'dreampoint-b2b'); ?></p>
                    </div>
                    
                <?php endif; ?>
                
                <?php wp_reset_postdata(); ?>
            </div>
            <!-- /.accordion -->
            
        </div>
        <!-- /#faq-body -->
    </div>
    <!-- /.container -->
</div>
<!-- /#faq -->

<?php
/**
 * Optional: Add Schema.org FAQ markup for SEO
 */
if ($faq_query->have_posts()) :
    $schema_items = [];
    
    while ($faq_query->have_posts()) : $faq_query->the_post();
        $answer = get_field('faq_answer');
        if (!$answer) {
            $answer = get_the_content();
        }
        
        $schema_items[] = [
            '@type'          => 'Question',
            'name'           => get_the_title(),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => wp_strip_all_tags($answer),
            ],
        ];
    endwhile;
    
    wp_reset_postdata();
    
    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $schema_items,
    ];
    ?>
    <script type="application/ld+json">
        <?php echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    </script>
<?php endif; ?>

<?php get_footer(); ?>
