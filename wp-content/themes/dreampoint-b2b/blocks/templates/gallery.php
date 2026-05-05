<?php
if (!defined('ABSPATH')) {
    exit;
}
$images   = get_field('gallery');
$is_gray  = get_field('is_gray');

if (empty($images)) {
    return;
}

$gray_class = $is_gray ? ' is-gray' : '';
?>
<div class="gallery-slider-block slider-grid block<?php echo esc_attr($gray_class); ?>">
    <div class="container">
        <div class="gallery-slider">
            <?php foreach ($images as $image) :
                // ACF gallery vraća sizes niz sa URL-om i dimenzijama za svaku registrovanu veličinu.
                // gallery_thumb (400×300) je naša registrovana veličina — koristimo je kao prikaz u slideru.
                $thumb   = $image['sizes']['gallery_thumb'] ?? $image['sizes']['medium'] ?? $image['url'];
                $thumb_w = $image['sizes']['gallery_thumb-width']  ?? $image['sizes']['medium-width']  ?? ($image['width']  ?? 0);
                $thumb_h = $image['sizes']['gallery_thumb-height'] ?? $image['sizes']['medium-height'] ?? ($image['height'] ?? 0);
                $full    = $image['url'];
                $alt     = $image['alt'] ?: 'Slika galerije';
            ?>
                <div class="gallery-item">
                    <a href="<?php echo esc_url($full); ?>"
                       data-fancybox="gallery">
                        <img
                            src="<?php echo esc_url($thumb); ?>"
                            alt="<?php echo esc_attr($alt); ?>"
                            <?php if ($thumb_w && $thumb_h) : ?>
                                width="<?php echo absint($thumb_w); ?>"
                                height="<?php echo absint($thumb_h); ?>"
                            <?php endif; ?>
                            loading="lazy"
                            decoding="async"
                        >
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- /.gallery-slider -->
    </div>
    <!-- /.container -->
</div>
<!-- /.gallery-slider-block -->
