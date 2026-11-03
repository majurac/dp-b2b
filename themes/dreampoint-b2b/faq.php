<?php
/**
 * Template name: FAQ
 *
 * FAQ posts filtered by `faq-category` (tabs rendered in header.php via
 * dreampoint_b2b_faq_render_tabs()), accessible accordion and pagination.
 * The query, page count and pagination all use the same category-filtered result set.
 *
 * @package Dreampoint_B2B
 * @version 2.0.0
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

// Selected category (first one by default); no categories -> all questions
$faq_terms   = dreampoint_b2b_faq_get_terms();
$active_term = dreampoint_b2b_faq_get_active_term($faq_terms);

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

if ($active_term) {
    $query_args['tax_query'] = [
        [
            'taxonomy' => 'faq-category',
            'field'    => 'term_id',
            'terms'    => [$active_term->term_id],
        ],
    ];
}

$faq_query = new WP_Query($query_args);

// Page number beyond the last page of this category -> show the last page.
// (WP_Query reports max_num_pages = 0 for an empty out-of-range page, so probe page 1 first.)
if ($current_page > 1 && !$faq_query->have_posts()) {
    $query_args['paged'] = 1;
    $faq_probe           = new WP_Query($query_args);
    $current_page        = max(1, (int) $faq_probe->max_num_pages);

    if (1 === $current_page) {
        $faq_query = $faq_probe;
    } else {
        $query_args['paged'] = $current_page;
        $faq_query           = new WP_Query($query_args);
    }
}

$schema_items = [];
$item_index   = 0;
?>

<div id="faq" class="block">
    <div class="container">
        <div id="faq-body">
            <div class="accordion">
                <?php if ($faq_query->have_posts()) : ?>

                    <?php while ($faq_query->have_posts()) : $faq_query->the_post(); ?>
                        <?php
                        $faq_id   = absint(get_the_ID());
                        $is_open  = (0 === $item_index); // first visible question is expanded
                        $item_index++;

                        $answer = get_field('faq_answer');
                        if (!$answer) {
                            // Fallback to post content if ACF field empty
                            $answer = apply_filters('the_content', get_the_content());
                        }

                        $schema_items[] = [
                            '@type'          => 'Question',
                            'name'           => get_the_title(),
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text'  => wp_strip_all_tags($answer),
                            ],
                        ];
                        ?>
                        <div class="faq-wrap<?php echo $is_open ? ' active' : ''; ?>" id="faq-<?php echo $faq_id; ?>">
                            <h2 class="faq-question">
                                <button type="button" class="faq-toggle" id="faq-q-<?php echo $faq_id; ?>" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>" aria-controls="faq-a-<?php echo $faq_id; ?>">
                                    <span class="faq-toggle__text"><?php the_title(); ?></span>
                                    <span class="faq-toggle__icon" aria-hidden="true"></span>
                                </button>
                            </h2>
                            <div class="content" id="faq-a-<?php echo $faq_id; ?>"<?php echo $is_open ? '' : ' hidden'; ?>>
                                <?php echo wp_kses_post($answer); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>

                    <!-- Pagination -->
                    <?php if ($faq_query->max_num_pages > 1) : ?>
                        <div class="faq-pagination">
                            <?php
                            // Keep the selected category in every page link
                            $pagination_base = $active_term
                                ? dreampoint_b2b_faq_term_url($active_term, $faq_terms)
                                : get_permalink();

                            $pagination_args = [
                                'base'       => add_query_arg('faq_page', '%#%', $pagination_base),
                                'format'     => '',
                                'total'      => $faq_query->max_num_pages,
                                'current'    => $current_page,
                                'prev_text'  => '<span class="faq-pagination__icon faq-pagination__icon--prev" aria-hidden="true"></span><span>' . esc_html__('Nazad', 'dreampoint-b2b') . '</span>',
                                'next_text'  => '<span>' . esc_html__('Naprijed', 'dreampoint-b2b') . '</span><span class="faq-pagination__icon faq-pagination__icon--next" aria-hidden="true"></span>',
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
 * Schema.org FAQPage markup for the questions shown on this page
 */
if ($schema_items) :
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
