<?php
/**
 * Brand Hero — presentation for the current product_brand term.
 *
 * Term description is intentionally NOT rendered here — it is already
 * output generically by category_description() in header-shop-archive.php.
 * Rendering it here too would duplicate it.
 *
 * Note: get_template_part()'s $args parameter is not extracted into the
 * included file's scope by WordPress core (load_template() only extracts
 * $wp_query->query_vars) — so the term is re-fetched here via
 * get_queried_object() rather than received as a variable. The caller
 * (inc/brand-hero.php) has already validated it is_tax('product_brand')
 * and that the queried object is a valid WP_Term before loading this part.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

$current_brand = get_queried_object();
$brand_id       = $current_brand->term_id;
$taxonomy_slug  = $current_brand->taxonomy;

// Get brand logo directly from WooCommerce Brands thumbnail option
$brand_logo_id  = get_term_meta( $brand_id, 'thumbnail_id', true );
$brand_logo_url = $brand_logo_id ? wp_get_attachment_url( $brand_logo_id ) : '';

// Get brand description
$brand_description = term_description( $brand_id, $taxonomy_slug );

// Get the main brand_image ACF field
$main_brand_image_acf = get_field( 'brand_image', $taxonomy_slug . '_' . $brand_id );
$main_brand_image_id   = is_array( $main_brand_image_acf ) && isset( $main_brand_image_acf['ID'] ) ? $main_brand_image_acf['ID'] : 0;
?>

<div id="brand-single">
    <div class="brand-hero">
        <div class="container">
            <div class="brand-hero-slider">
                <?php
                // Display the main brand_image
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
                ?>
            </div>
            <div class="slide-content">
                <div class="slide-content-in">
                    <h1><?php echo esc_html( $current_brand->name ); ?></h1>
                    <?php if ( $brand_logo_url ) : ?>
                        <div class="brand-logo-wrapper"><img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $current_brand->name ); ?>" class="brand-logo"></div>
                        <?php endif; ?>
                         <?php if ( $brand_description ) : ?>
                            <div class="brand-description">
                                <?php echo wp_kses_post( $brand_description ); // Use wp_kses_post for safe HTML output ?>
                            </div>
                        <?php endif; ?>
                    <a href="#products-page" class="button button-xl"><?php esc_html_e( 'Shop Brand', 'dreampoint-b2b' ); ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
