<?php 
$post_item_class = get_query_var('post_item_class', 'post-item');
?>
<div class="<?php echo esc_attr($post_item_class); ?>">
    <div class="img-holder">
        <?php the_post_thumbnail('blog-loop'); ?>
    </div>
    <div class="content-holder">
        <span class="post-title"><?php the_title(); ?></span>
        <?php the_excerpt(); ?>
        <a href="<?php the_permalink(); ?>" class="button button--primary button--sm"><?php esc_html_e( 'Pročitaj više', 'dreampoint-b2b' ); ?></a>
    </div>
    <a href="<?php the_permalink(); ?>" class="url-wrapper"><?php the_title(); ?></a>
</div>