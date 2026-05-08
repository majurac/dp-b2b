<?php
if (!defined('ABSPATH')) {
    exit;
}

// Transient cache — eliminiše N+1 DB upite (get_terms + get_term_meta po brandu).
// Briše se automatski kada se promijeni product_brand taksonomija (vidi blocks/brands.php).
$cache_key   = 'dp_brands_data';
$brands_data = get_transient($cache_key);

if (false === $brands_data) {
    $brands = get_terms([
        'taxonomy'   => 'product_brand',
        'hide_empty' => false,
    ]);

    $brands_data = [];

    if (!empty($brands) && !is_wp_error($brands)) {
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
    }

    set_transient($cache_key, $brands_data, 6 * HOUR_IN_SECONDS);
}

if (empty($brands_data)) { ?>
    <p><?php esc_html_e('Nema brendova.', 'dreampoint-b2b'); ?></p>
<?php
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
