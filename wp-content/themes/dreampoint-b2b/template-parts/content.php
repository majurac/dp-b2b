<?php
/**
 * Template part for displaying posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Dreampoint_B2B
 */


?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="blog-single">
		<div class="blog-single-holder block">
			<div class="container">
			     <div class="featured-image">
					<?php if (has_post_thumbnail()) { the_post_thumbnail('blog-large'); } ?>
				</div>
				<!-- /.featured-image -->
				
	            <div class="blog-body"><?php the_content(); ?></div>
	            <!-- /.blog-body -->
		       
		        <div class="blog-metas">
					<div class="share">
						<ul class="icons">
							<li class="icons__item">
								<a class="icon" href="#" id="copyLink"> <i class="icon-copy"></i><?php esc_html_e( 'Kopiraj Link', 'dreampoint-b2b' ); ?></a>
							</li>
							<li class="icons__item">
								<a class="icon" href="https://www.facebook.com/sharer/sharer.php?u=<?php the_permalink(); ?>" target="_blank">
									<i class="icon-facebook"></i>
								</a>
							</li>
							<li class="icons__item">
							    <a class="icon" href="https://wa.me/?text=<?php echo urlencode(get_the_title() . ' ' . get_permalink()); ?>" target="_blank">
							        <i class="icon-whatsapp"></i>
							    </a>
							</li>
							<li class="icons__item">
							    <a class="icon" href="viber://forward?text=<?php echo urlencode(get_the_title() . ' ' . get_permalink()); ?>" target="_blank">
							        <i class="icon-viber"></i>
							    </a>
							</li>
							<li class="icons__item">
								<a class="icon" href="https://twitter.com/intent/tweet?text=<?php the_title(); ?> <?php the_permalink(); ?>" target="_blank"> <i class="icon-twitter"></i> </a>
							</li>
							<li class="icons__item">
								<a class="icon" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php the_permalink(); ?>" target="_blank">
									<i class="icon-linkedin"></i>
								</a>
							</li>
						</ul>
					</div>
					<!-- /.share -->
				</div>
				<!-- /.blog-metas -->
			</div>
			<!-- /.container -->
		</div>
		<!-- /.blog-single-holder -->
		<?php
			$current_post_id = get_the_ID();
			$categories = wp_get_post_categories($current_post_id);
		
			if ($categories) {
			    $query = new WP_Query([
			        'posts_per_page' => 6, // Display 6 related posts
			        'post_type' => 'post',
			        'post_status' => 'publish',
			        'category__in' => $categories, // Only from the same categories
			        'post__not_in' => [$current_post_id] // Exclude the current post
			    ]);
		
			    if ($query->have_posts()) : ?>
			        <div class="related-posts block">
			            <div class="container">
			            	<div class="section-heading">
			            		<h3><?php esc_html_e( 'Moglo bi vas zanimati', 'dreampoint-b2b' ); ?></h3>
			            		<div class="nav-btns">
					                <a href="#" class="slick-prev btn-prev" aria-label="<?php esc_attr_e('prethodni', 'dreampoint-b2b'); ?>"></a>
					                <a href="#" class="slick-next btn-next" aria-label="<?php esc_attr_e('sljedeći', 'dreampoint-b2b'); ?>"></a>
					            </div>
			            	</div>
			            	<!-- /.section-heading -->
			            	<div class="related-posts-slider">
			            		<?php while ($query->have_posts()) : $query->the_post(); ?>
		            			  	<?php
	                                    set_query_var('post_item_class', 'post-item');
		                            ?>
			            		    <?php get_template_part('template-parts/loop', get_post_type()); ?>
                            	<?php endwhile; ?>
                            	<?php wp_reset_postdata(); ?>
			            	</div>
			            	<!-- /.related-posts-slider -->
			            </div>
			            <!-- /.container -->
			        </div>
			        <?php
			        wp_reset_postdata();
			    endif;
			}
		?>
	</div>
	<!-- /.blog-single -->

</article><!-- #post-<?php the_ID(); ?> -->

<script>
    document.getElementById('copyLink').addEventListener('click', function(event) {
        event.preventDefault();
        var tempInput = document.createElement('input');
        tempInput.value = "<?php the_permalink(); ?>";
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        alert('<?php echo esc_js( __( 'Link je kopiran', 'dreampoint-b2b' ) ); ?>');
    });
</script>

