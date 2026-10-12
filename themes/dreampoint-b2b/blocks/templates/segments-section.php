<?php
if (!defined('ABSPATH')) {
    exit;
}

$segment_conflicts = [];
$segment_pages     = dreampoint_b2b_get_segment_pages($segment_conflicts);

// Duplicate segment pages are never resolved arbitrarily — tell editors, keep visitors clean.
if (!empty($segment_conflicts) && current_user_can('edit_pages')) {
    foreach ($segment_conflicts as $conflict_segment => $conflict_ids) {
        echo '<!-- dp segments-section: duplicate pages for segment ' . esc_html($conflict_segment)
            . ' (IDs ' . esc_html(implode(', ', array_map('absint', $conflict_ids))) . ') - segment skipped -->';
    }
}

if (empty($segment_pages)) {
    return;
}
?>
<div class="segments-section block">
    <div class="container">
        <div class="segments-slider">
            <?php foreach ($segment_pages as $segment_key => $segment_page) :
                $thumbnail_id = get_post_thumbnail_id($segment_page);
                // Static theme asset per canonical segment key (keys come from the helper's fixed list).
                $logo_file    = 'img/segments/' . $segment_key . '.png';
                $has_logo     = file_exists(get_template_directory() . '/' . $logo_file);
                ?>
                <div class="segment-box">
                    <a href="<?php echo esc_url(get_permalink($segment_page)); ?>" class="segment-tile<?php echo $thumbnail_id ? '' : ' segment-tile--no-image'; ?>">
                        <?php
                        if ($thumbnail_id) {
                            // Decorative: the link name comes from the visible segment title below.
                            echo wp_get_attachment_image(
                                $thumbnail_id,
                                'large',
                                false,
                                [
                                    'class' => 'segment-tile__image',
                                    'alt'   => '',
                                    'sizes' => '(max-width: 767px) 100vw, (max-width: 991px) 50vw, 384px',
                                ]
                            );
                        }
                        ?>
                        <span class="segment-tile__label">
                            <?php if ($has_logo) : ?>
                                <img src="<?php echo esc_url(get_template_directory_uri() . '/' . $logo_file); ?>" alt="" width="64" height="64" class="segment-tile__logo">
                            <?php endif; ?>
                            <span class="segment-tile__name"><?php echo esc_html(get_the_title($segment_page)); ?></span>
                        </span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- /.container -->
</div>
<!-- /.segments-section block -->
