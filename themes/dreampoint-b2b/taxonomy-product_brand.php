<?php
/**
 * The template for displaying a single product_brand taxonomy term page.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Dreampoint_B2B
 */

get_header();

// Get the current brand term object.
$current_brand = get_queried_object();

if ( $current_brand && is_a( $current_brand, 'WP_Term' ) ) :

    $brand_id = $current_brand->term_id;
    $taxonomy_slug = $current_brand->taxonomy; // e.g., 'product_brand'

    // Get brand logo directly from WooCommerce Brands thumbnail option
    $brand_logo_id = get_term_meta( $brand_id, 'thumbnail_id', true );
    $brand_logo_url = $brand_logo_id ? wp_get_attachment_url( $brand_logo_id ) : '';

    // Get brand description
    $brand_description = term_description( $brand_id, $taxonomy_slug );

    // Get the main brand_image ACF field
    $main_brand_image_acf = get_field( 'brand_image', $taxonomy_slug . '_' . $brand_id );
    $main_brand_image_id = is_array($main_brand_image_acf) && isset($main_brand_image_acf['ID']) ? $main_brand_image_acf['ID'] : 0;

    // Get the ACF gallery field
    $gallery_images = get_field( 'brand_gallery', $taxonomy_slug . '_' . $brand_id );

    $brand_color = get_field( 'brand_color', $taxonomy_slug . '_' . $brand_id );
    $slide_content_style = $brand_color ? ' style="background-color: ' . esc_attr( $brand_color ) . ';"' : '';
    ?>

    <div id="brand-single">
        <div class="brand-hero">
            <div class="container">
                <div class="brand-hero-slider">
                    <?php
                    // Display the main brand_image as the first slide
                    if ( $main_brand_image_id ) :
                        $main_image_url = wp_get_attachment_image_url( $main_brand_image_id, 'brand-featured-photo' );
                        if ( $main_image_url ) :
                            ?>
                            <div class="slide">
                                <img src="<?php echo esc_url( $main_image_url ); ?>" alt="<?php echo esc_attr( $current_brand->name ); ?>">
                            </div>
                            <?php
                        endif;
                    endif;

                    // Display images from the ACF gallery
                    if ( $gallery_images ) :
                        foreach ( (array) $gallery_images as $gallery_image ) :
                            $gallery_image_url = wp_get_attachment_image_url( $gallery_image['ID'], 'brand-featured-photo' );
                            if ( $gallery_image_url ) :
                                ?>
                                <div class="slide">
                                    <img src="<?php echo esc_url( $gallery_image_url ); ?>" alt="<?php echo esc_attr( $gallery_image['alt'] ); ?>">
                                </div>
                                <?php
                            endif;
                        endforeach;
                    endif;
                    ?>
                </div>
                <div class="slide-content"<?php echo $slide_content_style; ?>>
                    <div class="slide-content-in">
                        <h1><?php echo esc_html( $current_brand->name ); ?></h1>
                        <?php if ( $brand_logo_url ) : ?>
                            <div class="brand-logo-wrapper"><img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $current_brand->name ); ?>" class="brand-logo"></div>
                            <?php endif; ?>

                        <?php if ( $brand_description ) : ?>
                            <div class="brand-description">
                                <?php echo wp_kses_post( $brand_description ); ?>
                            </div>
                        <?php endif; ?>

                        <a href="#brand-products" class="button button-xl"><?php esc_html_e( 'Shop Brand', 'dreampoint-b2b' ); ?></a>
                    </div>
                    </div>

            <div class="nav-btns">
                <a href="#" class="slick-prev btn-prev" aria-label="<?php esc_attr_e( 'prethodni', 'dreampoint-b2b' ); ?>"></a>
                <a href="#" class="slick-next btn-next" aria-label="<?php esc_attr_e( 'sljedeći', 'dreampoint-b2b' ); ?>"></a>
            </div>
        </div>

        </div>
        <div class="inner-content">
            
            <div class="container">
                <div id="brand-products" class="brand-products">
                    <div class="inner-page">
                        <div class="inner-heading" style="padding-top: 0; padding-bottom: 0;">
                            <div class="container">
                                <?php echo do_shortcode('[wpf-filters id=4]'); ?>
                            </div>
                            <!-- /.container -->
                        </div>
                        <!-- /.inner-heading -->
                     </div>
                     <!-- /.inner-page -->
                    <?php if ( have_posts() ) : ?>

                        <div class="row">
                            <?php while ( have_posts() ) : the_post(); ?>
                                <?php wc_get_template_part( 'content', 'product' ); ?>
                            <?php endwhile; ?>
                        </div>

                        <?php woocommerce_pagination(); ?>

                    <?php else : ?>
                        <p><?php esc_html_e( 'Ovaj brend nema proizvode.', 'dreampoint-b2b' ); ?></p>
                    <?php endif; ?>

                </div>


            </div>
        </div>
    </div>
<?php
else :
    // Handle the case where no brand term is found (e.g., 404)
    echo '<div class="container"><p>' . esc_html__( 'Nema brendova', 'dreampoint-b2b' ) . '</p></div>';
endif;

get_footer();