<?php
/**
 * Template name: Brands
 *
 * This is the template that displays Brands page.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Dreampoint_B2B
 */

get_header();
?>

<div id="brands-page" class="block">
    <div class="inner-content">
        <div class="container">
            <?php
            // Get all brands
            $all_brands = get_terms(array(
                'taxonomy'   => 'product_brand', // Replace 'product_brand' with your actual brand taxonomy slug if it's different
                'hide_empty' => false,           // Set to true if you only want to show brands with products
            ));

            if (!empty($all_brands) && !is_wp_error($all_brands)) :

                // Segment izbor se cita iz ACF Select polja (brand_segment) na product_brand taksonomiji —
                // izvor istine su konfigurisani choices, ne hardkodovana lista u PHP-u.
                $segment_choices = array();
                foreach ( acf_get_field_groups( array( 'taxonomy' => 'product_brand' ) ) as $segment_group ) {
                    foreach ( acf_get_fields( $segment_group ) as $segment_field ) {
                        if ( $segment_field['name'] === 'brand_segment' && ! empty( $segment_field['choices'] ) ) {
                            $segment_choices = $segment_field['choices'];
                            break 2;
                        }
                    }
                }

                // Dodijeljeni segment po brendu + set segmenata koji su stvarno u upotrebi (bez praznih tabova).
                $brand_segments = array();
                $used_segments  = array();

                foreach ( $all_brands as $brand ) {
                    $segment_value = (string) get_field( 'brand_segment', 'product_brand_' . $brand->term_id );
                    $brand_segments[ $brand->term_id ] = $segment_value;

                    if ( $segment_value !== '' && isset( $segment_choices[ $segment_value ] ) ) {
                        $used_segments[ $segment_value ] = $segment_choices[ $segment_value ];
                    }
                }

                if ( ! empty( $used_segments ) ) :
                    ?>
                    <div class="brand-segments">
                        <button type="button" class="brand-segments__tab is-active" data-segment="all" aria-pressed="true">
                            <?php esc_html_e( 'Svi brendovi', 'dreampoint-b2b' ); ?>
                        </button>
                        <?php foreach ( $used_segments as $segment_value => $segment_label ) : ?>
                            <button type="button" class="brand-segments__tab" data-segment="<?php echo esc_attr( $segment_value ); ?>" aria-pressed="false">
                                <?php echo esc_html( $segment_label ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <?php
                endif;

                echo '<div class="brands-list"><div class="row">'; // Keep your existing wrapper class

                foreach ($all_brands as $brand) :
                    $link = get_term_link($brand);

                    // Get logo from WooCommerce Brands plugin (if used)
                    $logo_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
                    $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';

                    // Get ACF image field (if used)
                    $acf_image = get_field('brand_image', 'product_brand_' . $brand->term_id); // 'product_brand_' should match your taxonomy slug
                    $acf_image_id = is_array($acf_image) && isset($acf_image['ID']) ? $acf_image['ID'] : 0;

                    // Use the defined custom image size name
                    $acf_image_url = $acf_image_id ? wp_get_attachment_image_url($acf_image_id, 'brand-all-page') : '';

                    $brand_segment_attr = $brand_segments[ $brand->term_id ];
                    ?>

                    <div class="col"<?php echo $brand_segment_attr !== '' ? ' data-segment="' . esc_attr( $brand_segment_attr ) . '"' : ''; ?>>
                        <div class="brand-item">
                            <a href="<?php echo esc_url($link); ?>">
                                <?php if ($logo_url) : ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($brand->name); ?>" class="brand-logo">
                                <?php endif; ?>
                                <?php if ($acf_image_url) : ?>
                                    <img src="<?php echo esc_url($acf_image_url); ?>" alt="<?php echo esc_attr($brand->name); ?>" class="brand-image">
                                    <?php elseif (!$logo_url) :
                                    // Neither logo nor brand_image exists — fall back to the WC placeholder.
                                    // Get default placeholder and replace 150x150 with 384x282
                                    $placeholder_url = str_replace( '150x150', '384x282', wc_placeholder_img_src() );
                                ?>
                                    <img src="<?php echo esc_url( $placeholder_url ); ?>"
                                         width="384" height="282"
                                         alt="<?php echo esc_attr( get_the_title() ); ?>"  class="brand-image" />
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                    <?php
                endforeach;
                echo '</div></div>';
            endif;
            ?>
        </div>
    </div>
</div>
<?php get_footer(); ?>