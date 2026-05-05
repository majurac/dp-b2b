<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = get_field('title');
$image = get_field('image');
$video = get_field('video'); 

if (empty($video)) {
    return;
}
?>
<div class="video block">
    <?php if (is_array($image) && isset($image['url'])) : ?>
        <div class="bg-image">
            <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr(!empty($image['alt']) ? $image['alt'] : ($title ?? '')); ?>">
        </div>
    <?php endif; ?>
    <div class="container">
        <?php if ($title) : ?>
            <h3><?php echo esc_html($title); ?></h3>
        <?php endif; ?>
        <div class="play-ico">
            <div class="spinner-item"></div>
            <div class="spinner-item spinner-item--2"></div>
            <img src="<?php bloginfo('template_directory'); ?>/img/ico/play-ico.svg" alt="Play Video">
        </div>
    </div>
    <!-- /.container -->
    <?php if ($video) : ?>
        <a data-fancybox="video" href="<?php echo esc_url($video); ?>" class="video-play">
            <?php echo $title ?? ''; ?>
        </a>
    <?php endif; ?>
</div>
<!-- /.video .block -->
