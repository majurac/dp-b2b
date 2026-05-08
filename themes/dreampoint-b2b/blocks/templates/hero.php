<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = get_field('items');

if (empty($items)) {
    return;
}
?>
<div class="homehero block">

    <div class="hero-slider">
        <?php foreach ($items as $index => $item) :
            $bg_image = $item['image'] ?? null;
            $button   = $item['button'] ?? null;
            $title    = $item['title'] ?? null;
            $text     = $item['text'] ?? null;
            
            // Prvi slide je LCP - prioritizuj njegovo učitavanje
            $is_first = ($index === 0);
        ?>
            <div>
                <section>
                   <?php if (!empty($bg_image)) : 
                        $image_id = $bg_image['ID'] ?? null;
                    ?>
                        <div class="hero-bg"> 
                            <?php if ($image_id) : ?>
                                <?php echo wp_get_attachment_image(
                                    $image_id,
                                    'full',
                                    false,
                                    [
                                        'class'         => 'bg_image',
                                        'alt'           => esc_attr($bg_image['alt'] ?? ''),
                                        'width'         => esc_attr($bg_image['width'] ?? 1920),
                                        'height'        => esc_attr($bg_image['height'] ?? 1080),
                                        'fetchpriority' => $is_first ? 'high' : 'low',
                                        'loading'       => $is_first ? 'eager' : 'lazy',
                                        'decoding'      => 'async',
                                    ]
                                ); ?>
                            <?php else : ?>
                                <img 
                                    src="<?php echo esc_url($bg_image['url']); ?>" 
                                    alt="<?php echo esc_attr($bg_image['alt'] ?? ''); ?>"
                                    class="bg_image"
                                    width="<?php echo esc_attr($bg_image['width'] ?? 1920); ?>"
                                    height="<?php echo esc_attr($bg_image['height'] ?? 1080); ?>"
                                    <?php echo $is_first ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"'; ?>
                                    decoding="async"
                                >
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="container">
                        <div class="hero-content">
                            <?php if ($title) : ?>
                                <h1><?php echo esc_html($title); ?></h1>
                            <?php endif; ?>
                    
                            <?php if ($text) : ?>
                                <p><?php echo esc_html($text); ?></p>
                            <?php endif; ?>
                    
                            <?php if (!empty($button)) : 
                                $button_text = $button['title'] ?? '';
                                $button_url = $button['url'] ?? '';
                                $aria_label = $button_text;
                                ?>
                                <a href="<?php echo esc_url($button_url); ?>" 
                                   class="button button--outline" 
                                   aria-label="<?php echo esc_attr($aria_label); ?>">
                                    <?php echo esc_html($button_text); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <!-- /.hero-content -->
                    </div>
                    <!-- /.container -->
                </section>
            </div>
            <!-- /.hero-slider-item -->
        <?php endforeach; ?>
    </div>
    <!-- /.hero-slider -->
</div>
<!-- /.homehero.block -->
