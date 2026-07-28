<?php
/**
 * Plugin Name: B2B Komercijalist Notifications
 * Plugin URI: https://uncledev.info
 * Description: Šalje kopiju email notifikacija o narudžbama dodijeljenom komercijalisti. Addon za B2B Partner Importer plugin.
 * Version: 1.0.2
 * Author: UncleDev
 * Author URI: https://uncledev.info
 * License: GPL v2 or later
 * Text Domain: b2b-komercijalist-notifications
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>B2B Komercijalist Notifications</strong> zahtijeva aktiviran WooCommerce plugin.</p></div>';
    });
    return;
}

class B2B_Komercijalist_Notifications {
    
    private $option_name = 'b2b_komercijalist_notifications_enabled';
    
    public function __construct() {
        // Check if main B2B Partner Importer plugin exists
        add_action('admin_init', [$this, 'check_dependencies']);
        
        // Add settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Hook into WooCommerce email headers
        add_filter('woocommerce_email_headers', [$this, 'add_komercijalist_to_cc'], 10, 3);
    }
    
    /**
     * Check if main plugin is active
     */
    public function check_dependencies() {
        // Check if B2B Partner Importer functions exist
        if (!function_exists('get_user_meta')) {
            return; // WordPress not fully loaded yet
        }
        
        // We just need user meta to exist - the main plugin creates the meta fields
        // No strict dependency check needed
    }
    
    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'tools.php',
            'B2B Email Notifikacije',
            'B2B Email Notifikacije',
            'manage_options',
            'b2b-komercijalist-notifications',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('b2b_komercijalist_notifications', $this->option_name);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $enabled = get_option($this->option_name, '1');
        ?>
        <div class="wrap">
            <h1>📧 B2B Komercijalist Email Notifikacije</h1>
            
            <div class="card" style="max-width: 800px;">
                <h2>Postavke</h2>
                <p>Automatski šalje kopiju email notifikacija o novim narudžbama dodijeljenom komercijalisti.</p>
                
                <form method="post" action="options.php">
                    <?php settings_fields('b2b_komercijalist_notifications'); ?>
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Email notifikacije</th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->option_name; ?>" value="1" <?php checked($enabled, '1'); ?>>
                                        <strong>Omogući slanje emailova komercijalisti</strong>
                                    </label>
                                    <p class="description">
                                        Kada je uključeno, komercijalist će primiti kopiju email notifikacije (u CC) za sve nove narudžbe svojih B2B partnera.
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Spremi postavke'); ?>
                </form>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>ℹ️ Kako radi?</h2>
                <ol>
                    <li>B2B korisnik napravi narudžbu</li>
                    <li>WooCommerce šalje "Nova narudžba" email administratoru</li>
                    <li>Plugin automatski dodaje dodijeljenog komercijalista u <strong>CC</strong></li>
                    <li>Komercijalist prima identičnu kopiju emaila</li>
                </ol>
                
                <h3>Što treba za rad?</h3>
                <ul>
                    <li>✅ WooCommerce mora biti aktivan</li>
                    <li>✅ Korisnik mora imati dodijeljenog komercijalista (putem B2B Partner Importer plugina)</li>
                    <li>✅ Komercijalist mora imati email adresu u ACF polju <code>email_commercialist</code></li>
                </ul>
                
                <h3>Troubleshooting</h3>
                <p><strong>Komercijalist ne prima emailove?</strong></p>
                <ul>
                    <li>Provjeri da korisnik ima dodijeljenog komercijalista (Users → Edit User → B2B Partner Informacije)</li>
                    <li>Provjeri da komercijalist ima email u ACF polju</li>
                    <li>Provjeri Order Notes u narudžbi - tamo piše da li je email poslan</li>
                    <li>Provjeri spam folder</li>
                </ul>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                <h2>🔍 Status provjera</h2>
                <?php $this->display_status_check(); ?>
            </div>
        </div>
        
        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 20px;
                margin: 20px 0;
            }
            .card h2 {
                margin-top: 0;
            }
            .status-check {
                margin: 15px 0;
                padding: 10px;
                background: #fff;
                border-left: 4px solid #00a32a;
                border-radius: 2px;
            }
            .status-check.warning {
                border-left-color: #dba617;
                background: #fcf3e1;
            }
            .status-check.error {
                border-left-color: #d63638;
                background: #fde7e9;
            }
        </style>
        <?php
    }
    
    /**
     * Display status check
     */
    private function display_status_check() {
        // Check WooCommerce
        $wc_active = class_exists('WooCommerce');
        echo '<div class="status-check ' . ($wc_active ? '' : 'error') . '">';
        echo $wc_active ? '✅' : '❌';
        echo ' <strong>WooCommerce:</strong> ' . ($wc_active ? 'Aktivan' : 'Nije aktivan');
        echo '</div>';
        
        // Check if there are any komercijalisti
        $komercijalisti = get_posts([
            'post_type' => 'komercijalist',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);
        
        echo '<div class="status-check ' . (!empty($komercijalisti) ? '' : 'warning') . '">';
        echo !empty($komercijalisti) ? '✅' : '⚠️';
        echo ' <strong>CPT "Komercijalist":</strong> ';
        
        if (!empty($komercijalisti)) {
            $total = wp_count_posts('komercijalist');
            echo 'Pronađeno ' . ($total->publish ?? 0) . ' komercijalista';
        } else {
            echo 'Nisu pronađeni komercijalisti u bazi';
        }
        echo '</div>';
        
        // Check if there are users with assigned komercijalisti
        global $wpdb;
        $users_with_komercijalist = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'assigned_komercijalist' 
             AND meta_value != '' 
             AND meta_value != '0'"
        );
        
        echo '<div class="status-check ' . ($users_with_komercijalist > 0 ? '' : 'warning') . '">';
        echo $users_with_komercijalist > 0 ? '✅' : '⚠️';
        echo ' <strong>B2B korisnici:</strong> ';
        echo $users_with_komercijalist > 0 
            ? $users_with_komercijalist . ' korisnika ima dodijeljenog komercijalista' 
            : 'Nema korisnika s dodijeljenim komercijalstom';
        echo '</div>';
        
        // Check plugin setting
        $enabled = get_option($this->option_name, '1');
        echo '<div class="status-check ' . ($enabled ? '' : 'warning') . '">';
        echo $enabled ? '✅' : '⚠️';
        echo ' <strong>Email notifikacije:</strong> ' . ($enabled ? 'Uključene' : 'Isključene');
        echo '</div>';
    }
    
    /**
     * Add komercijalist to CC in email headers
     */
    public function add_komercijalist_to_cc($headers, $email_id, $order) {
        // Check if notifications are enabled
        if (get_option($this->option_name, '1') !== '1') {
            return $headers;
        }
        
        // Only for new order emails
        if ($email_id !== 'new_order') {
            return $headers;
        }
        
        // Make sure we have an order object
        if (!$order || !is_a($order, 'WC_Order')) {
            return $headers;
        }
        
        // Get customer user ID
        $user_id = $order->get_customer_id();
        
        if (!$user_id) {
            return $headers;
        }
        
        // Get assigned komercijalist
        $komercijalist_id = get_user_meta($user_id, 'assigned_komercijalist', true);
        
        if (!$komercijalist_id) {
            return $headers;
        }
        
        // Get komercijalist email from ACF field
        $komercijalist_email = get_field('email_commercialist', $komercijalist_id);
        
        if (!$komercijalist_email || !is_email($komercijalist_email)) {
            // Try to get email from post meta (fallback)
            $komercijalist_email = get_post_meta($komercijalist_id, 'email_commercialist', true);
        }
        
        if ($komercijalist_email && is_email($komercijalist_email)) {
            // Get komercijalist name for logging
            $komercijalist_name = get_the_title($komercijalist_id);
            
            // Add CC header (email only - avoids encoding issues with non-ASCII characters)
            if (is_array($headers)) {
                $headers[] = 'Cc: ' . $komercijalist_email;
            } else {
                $headers .= 'Cc: ' . $komercijalist_email . "\r\n";
            }
            
            // Log to order notes
            $order->add_order_note(
                sprintf(
                    'Email notifikacija poslana komercijalisti: %s (%s)',
                    $komercijalist_name,
                    $komercijalist_email
                ),
                false,
                false
            );
        }
        
        return $headers;
    }
}

// Initialize plugin
new B2B_Komercijalist_Notifications();
