<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = get_field('title');
$text = get_field('text');
$image = get_field('image');
$button = get_field('button');
$orientation = get_field('orientation'); // acf true/false

$row_classes = ['row'];
if ($orientation) {
    $row_classes[] = 'reverse';
}

?>
<div class="about block">
    <div class="container">
        <div class="about-content">
            <div class="<?php echo esc_attr(implode(' ', $row_classes)); ?>">
                <div class="col-lg-6">
                    <?php if (is_array($image) && isset($image['url'])) : ?>
                        <div class="block-image img-holder">
                            <?php echo wp_get_attachment_image($image['ID'], 'about-photo', false, [
                                'alt' => !empty($image['alt']) ? $image['alt'] : ($title ?? ''),
                                'class' => 'about-photo',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-6">
                    <div class="block-content">
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
                </div>
            </div>
        </div>
        <!-- /.about-content -->
    </div>
</div>