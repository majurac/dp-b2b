<?php
if (!defined('ABSPATH')) {
    exit;
}
$text = get_field('wysiwyg_content');
if (empty($text)) {
    return;
}

$is_gray = get_field('is_gray');
$gray_class = $is_gray ? ' is-gray' : '';
?>
<div class="wysiwyg-block block<?php echo esc_attr($gray_class); ?>">
    <div class="container">
        <div class="wysiwyg-content">
            <?php echo wp_kses_post($text); ?>
        </div>
        <!-- /.wysiwyg-content -->
    </div>
    <!-- /.container -->
</div>
<!-- /.wysiwyg-block -->