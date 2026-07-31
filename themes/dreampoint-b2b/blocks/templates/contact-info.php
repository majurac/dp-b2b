<?php
if (!defined('ABSPATH')) {
    exit;
}


$company_phone = get_field('company_phone', 'option');
$company_email = get_field('company_email', 'option');
$company_timings = get_field('company_timings', 'option');
$company_address = get_field('company_address', 'option');
$title = get_field('title');
$text = get_field('text');
$button = get_field('button');
?>
<div class="contact-info block">
    <div class="container">
        <div class="contact-content">
            <div class="commercialist">
                <?php if ($title) : ?>
                    <h2><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <p><?php echo wp_kses_post($text); ?></p>
                <?php endif; ?>
                <?php if ($button) : ?>
                    <?php the_acf_link($button, 'button'); ?>
                <?php endif; ?>
                <?php 
                    $current_user_id = get_current_user_id(); // Get the logged-in user ID

                    // Check if the commercialist contact info was displayed
                    ob_start();
                    display_commercialist_contact_info($current_user_id);
                    $commercialist_output = ob_get_clean();

                    if (trim($commercialist_output)) {
                        // If there is output for the commercialist, display it
                        echo $commercialist_output;
                    } else {
                        // Fallback to company contact details if no commercialist is set
                        ?>
                        <p><?php esc_html_e('Partner nema dodeljenog komercijalistu', 'dreampoint-b2b'); ?></p>
                        <?php
                    }
                    ?>
                    
            </div>
            <!-- /.commercialist -->
             <div class="contact-details">
                <div class="item">
                    <p><strong><?php esc_html_e( 'Radno vrijeme', 'dreampoint-b2b' ); ?></strong></p>
                    <p><?php echo wp_kses_post( $company_timings ); ?></p>
                </div>
                <div class="item">
                    <p><strong><?php esc_html_e( 'Podrška kupcima', 'dreampoint-b2b' ); ?></strong></p>
                    <p><a href="tel:<?php echo esc_attr( $company_phone ); ?>"><?php echo esc_html( $company_phone ); ?></a></p>
                    <p><a href="mailto:<?php echo esc_attr($company_email); ?>"><?php echo esc_html($company_email); ?></a></p>
                </div>
                
             </div>
             <!-- /.contact-details -->
        </div>
        <!-- /.contact-content -->
    </div>
</div>
