<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = get_field('title');
$text = get_field('text');
$image = get_field('image');
$button = get_field('button');


?>
<div class="featured-brand block">
    <?php if (is_array($image) && isset($image['url'])) : ?>
        <div class="bg-image">
            <?php echo wp_get_attachment_image($image['ID'], 'full', false, [
                'class'    => 'featured-brand-photo',
                'alt'      => esc_attr(!empty($image['alt']) ? $image['alt'] : ($title ?? '')),
                'width'    => esc_attr($image['width'] ?? 1920),
                'height'   => esc_attr($image['height'] ?? 1080),
                'decoding' => 'async',
            ]); ?>
        </div>
    <?php endif; ?>
    <div class="block-content">
        <div class="container">
            <?php if ($title) : ?>
            <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p><?php echo wp_kses_post($text); ?></p>
            <?php endif; ?>
            <?php if ($button) : ?>
                <?php the_acf_link($button, 'button'); ?>
            <?php endif; ?>
        </div>
        <!-- /.container -->
    </div>
</div>