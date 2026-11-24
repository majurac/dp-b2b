<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = get_field('title');
$text = get_field('text');
$image = get_field('image');
$button = get_field('button');
$badge = get_field('badge');

// E13: on a Segment Landing page the first Featured Section is the Segment Single hero
// (Figma HOME-01 pojedinacni segment 11148:31626): contained card, optional badge and the
// page's single H1. Every other instance (homepage / Quick Order banner) keeps the panel below.
if (isset($block) && is_array($block) && dreampoint_b2b_is_segment_hero_block($block, $post_id ?? null)) :
    $hero_heading = $title ?: get_the_title($post_id ?? null); // one meaningful H1 even if the title field is empty
?>
<div class="featured-section featured-section--hero block">
    <div class="container">
        <div class="segment-hero">
            <div class="segment-hero__content">
                <?php if ($badge) : ?>
                    <span class="segment-hero__badge"><?php echo esc_html($badge); ?></span>
                <?php endif; ?>
                <h1><?php echo esc_html($hero_heading); ?></h1>
                <?php if ($text) : ?>
                    <p><?php echo wp_kses_post($text); ?></p>
                <?php endif; ?>
                <?php if ($button) : ?>
                    <?php the_acf_link($button, 'button button--xl'); ?>
                <?php endif; ?>
            </div>
            <?php if (is_array($image) && isset($image['url'])) : ?>
                <div class="segment-hero__media">
                    <?php echo wp_get_attachment_image($image['ID'], 'full', false, [
                        'class'         => 'featured-section-photo',
                        'alt'           => esc_attr(!empty($image['alt']) ? $image['alt'] : $hero_heading),
                        'width'         => esc_attr($image['width'] ?? 1920),
                        'height'        => esc_attr($image['height'] ?? 1080),
                        'loading'       => 'eager', // hero image is the LCP element
                        'fetchpriority' => 'high',
                        'decoding'      => 'async',
                    ]); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
    return;
endif;
?>
<div class="featured-section block">
    <?php if (is_array($image) && isset($image['url'])) : ?>
        <div class="bg-image">
            <?php echo wp_get_attachment_image($image['ID'], 'full', false, [
                'class'    => 'featured-section-photo',
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
                <?php the_acf_link($button, 'button button--xl'); ?>
            <?php endif; ?>
        </div>
        <!-- /.container -->
    </div>
</div>