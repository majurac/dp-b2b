<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = get_field('title');

$items = get_field('items');

if (empty($items)) {
    return;
}
?>
<div class="testimonials block">
    <div class="container">
        <?php if ($title) : ?>
            <h2><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <div class="testimonials-slider">
            <?php foreach ($items as $item) : ?>
                <div>
                    <div class="testimonial-content">
                        <?php if ($testimonial = $item['testimonial'] ?? null) : ?>
                            <blockquote><?php echo esc_html($testimonial); ?></blockquote>
                        <?php endif; ?>
                        <?php if ($author = $item['author'] ?? null) : ?>
                            <div class="author">
                                <?php echo esc_html($author); ?>
                            </div>
                            <!-- /.author -->
                        <?php endif; ?>
                    </div>
                    <!-- /.testimonial-content -->
                </div>
            <?php endforeach; ?>     
        </div>
        <!-- /.testimonials-slider -->
     </div>
     <!-- /.container -->
</div>
<!-- /.testimonials .block -->
