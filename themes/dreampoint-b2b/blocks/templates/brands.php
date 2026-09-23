<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = get_field('title');

// ADR-009 Phase B: on a Segment Landing page, the carousel is driven
// dynamically by brand_segment (product → product_brand → brand_segment),
// same semantics as the product sections — not by editorial curation.
// On Homepage/other pages it stays editor-curated via selected_brands.
$segment = dreampoint_b2b_get_page_segment( $post_id ?? null );

if ( '' !== $segment ) {
    $selected_ids = dreampoint_b2b_get_brand_ids_for_segment( $segment );
} else {
    $selected_ids = get_field('selected_brands');
}

if (empty($selected_ids)) {
    return;
}

// Jedan get_terms poziv za sve odabrane brendove — 'orderby' => 'include' čuva
// redoslijed odabira urednika. term meta (thumbnail_id) se cache-uje nativno
// od strane get_terms (update_term_meta_cache), pa nema N+1 upita.
// ADR-009 Phase B: on Homepage/Segment Landing, bypass customer-bucket
// visibility for this carousel too ($post_id from ACF block render).
$term_args = dreampoint_b2b_shared_surface_query_args( [
    'taxonomy'   => 'product_brand',
    'include'    => $selected_ids,
    'orderby'    => 'include',
    'hide_empty' => false,
], $post_id ?? null );

$brands = get_terms( $term_args );

if (empty($brands) || is_wp_error($brands)) {
    return;
}

$brands_data = [];
foreach ($brands as $brand) {
    $logo_id  = (int) get_term_meta($brand->term_id, 'thumbnail_id', true);
    $logo_src = $logo_id ? wp_get_attachment_image_src($logo_id, 'thumbnail') : null;

    $brands_data[] = [
        'name'     => $brand->name,
        'logo_url' => $logo_src[0] ?? '',
        'logo_w'   => $logo_src[1] ?? 0,
        'logo_h'   => $logo_src[2] ?? 0,
        'link'     => is_wp_error(get_term_link($brand)) ? '' : get_term_link($brand),
    ];
}

if (empty($brands_data)) {
    return;
}

// Slider treba minimalno 8 stavki za kontinuirani scroll efekt.
// Slick-ovo kloniranje nije dovoljno uz variableWidth + autoplaySpeed:0.
$brands_to_display = $brands_data;
$initial_count     = count($brands_to_display);

if ($initial_count > 0 && $initial_count < 8) {
    $original = $brands_to_display;
    while (count($brands_to_display) < 8) {
        $brands_to_display = array_merge($brands_to_display, $original);
    }
    $brands_to_display = array_slice($brands_to_display, 0, 8);
}
?>
<div class="brands block">
    <div class="container">
        <?php if ($title) : ?>
            <h2><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <div class="brands-slider">
            <?php foreach ($brands_to_display as $brand) : ?>

                <div class="brand-item">
                    <a href="<?php echo esc_url($brand['link']); ?>">
                        <?php if ($brand['logo_url']) : ?>
                            <img
                                src="<?php echo esc_url($brand['logo_url']); ?>"
                                alt="<?php echo esc_attr($brand['name']); ?>"
                                <?php if ($brand['logo_w'] && $brand['logo_h']) : ?>
                                    width="<?php echo absint($brand['logo_w']); ?>"
                                    height="<?php echo absint($brand['logo_h']); ?>"
                                <?php endif; ?>
                                loading="lazy"
                                decoding="async"
                            >
                        <?php else : ?>
                            <span><?php echo esc_html($brand['name']); ?></span>
                        <?php endif; ?>
                    </a>
                </div>

            <?php endforeach; ?>
        </div>
        <!-- /.brands-slider -->
    </div>
    <!-- /.container -->
</div>
