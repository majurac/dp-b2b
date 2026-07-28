<?php
/**
 * Plugin Name: B2B Partner Importer
 * Plugin URI: https://uncledev.info
 * Description: Import B2B partnera iz Excel/CSV datoteke s automatskim matchanjem komercijalista
 * Version: 1.0.0
 * Author: UncleDev
 * Author URI: https://uncledev.info
 * License: GPL v2 or later
 * Text Domain: b2b-partner-importer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>B2B Partner Importer</strong> zahtijeva aktiviran WooCommerce plugin.</p></div>';
    });
    return;
}

class B2B_Partner_Importer {
    
    private $log = [];
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_b2b_process_import', [$this, 'ajax_process_import']);
        
        // Add custom user fields display
        add_action('show_user_profile', [$this, 'show_extra_profile_fields']);
        add_action('edit_user_profile', [$this, 'show_extra_profile_fields']);
        add_action('personal_options_update', [$this, 'save_extra_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_extra_profile_fields']);
        
        // Add custom columns to users list
        add_filter('manage_users_columns', [$this, 'add_user_columns']);
        add_filter('manage_users_custom_column', [$this, 'show_user_column_content'], 10, 3);
        
        // Company search, sort and filter enhancements
        add_filter('manage_users_sortable_columns', [$this, 'make_company_column_sortable']);
        add_action('pre_user_query', [$this, 'handle_company_query']);
    }
    
    public function add_admin_menu() {
        add_management_page(
            'Import B2B Partnera',
            'Import B2B Partnera',
            'manage_options',
            'b2b-partner-importer',
            [$this, 'render_admin_page']
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'tools_page_b2b-partner-importer') {
            return;
        }
        
        wp_enqueue_style('b2b-importer-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '1.0.0');
        wp_enqueue_script('b2b-importer-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery'], '1.0.0', true);
        
        wp_localize_script('b2b-importer-admin', 'b2bImporter', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('b2b_import_nonce')
        ]);
    }
    
    public function render_admin_page() {
        // Check if PhpSpreadsheet is installed
        $vendor_exists = file_exists(plugin_dir_path(__FILE__) . 'includes/vendor/autoload.php');
        
        ?>
        <div class="wrap b2b-importer-wrap">
            <h1>🏢 Import B2B Partnera</h1>
            
            <?php if (!$vendor_exists): ?>
            <div class="notice notice-warning">
                <p><strong>⚠️ Excel support nije dostupan</strong></p>
                <p>PhpSpreadsheet biblioteka nije instalirana. Za Excel support (.xlsx, .xls), pokrenite:</p>
                <pre style="background: #f0f0f1; padding: 10px; border-radius: 4px;"><code>cd <?php echo plugin_dir_path(__FILE__); ?>
composer install --no-dev</code></pre>
                <p><strong>Alternativa:</strong> Koristite <strong>CSV format</strong> koji radi bez dodatnih biblioteka! ✅</p>
            </div>
            <?php endif; ?>
            
            <div class="b2b-importer-card">
                <h2>📋 Upute za pripremu datoteke</h2>
                <p>Excel ili CSV datoteka mora sadržavati sljedeće kolone (točnim redoslijedom):</p>
                <ol>
                    <li><strong>OIB</strong> - OIB tvrtke</li>
                    <li><strong>Šifra partnera</strong> - Task ID</li>
                    <li><strong>Naziv tvrtke</strong> - Puni naziv tvrtke</li>
                    <li><strong>Korisničko ime</strong> - Username (ako prazno, koristi se dio emaila prije @)</li>
                    <li><strong>Ime</strong> - Ime osobe</li>
                    <li><strong>Prezime</strong> - Prezime osobe</li>
                    <li><strong>Email</strong> - Email adresa</li>
                    <li><strong>Mobitel</strong> - Broj mobitela</li>
                    <li><strong>Ulica</strong> - Naziv ulice</li>
                    <li><strong>Kućni broj</strong> - Kućni broj</li>
                    <li><strong>Mjesto</strong> - Grad/Mjesto</li>
                    <li><strong>Poštanski broj</strong> - Poštanski broj</li>
                    <li><strong>Email komercijalista</strong> - Email zaduženog komercijalista</li>
                </ol>
                
                <p class="description">
                    <strong>Napomena:</strong> Prvi red mora biti zaglavlje s nazivima kolona. 
                    Plugin automatski prepoznaje Excel (.xlsx) i CSV datoteke.
                </p>
                
                <a href="<?php echo plugin_dir_url(__FILE__) . 'template/b2b-import-template.csv'; ?>" class="button button-secondary" download>
                    📥 Preuzmi CSV Template
                </a>
                
                <?php if ($vendor_exists): ?>
                <a href="<?php echo plugin_dir_url(__FILE__) . 'template/b2b-import-template.xlsx'; ?>" class="button button-secondary" download style="margin-left: 10px;">
                    📥 Preuzmi Excel Template
                </a>
                <?php endif; ?>
            </div>
            
            <div class="b2b-importer-card">
                <h2>📤 Upload datoteke</h2>
                
                <form id="b2b-import-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('b2b_import_action', 'b2b_import_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="import_file">Datoteka za import</label>
                            </th>
                            <td>
                                <input type="file" name="import_file" id="import_file" accept="<?php echo $vendor_exists ? '.xlsx,.xls,.csv' : '.csv'; ?>" required>
                                <p class="description">
                                    Podržani formati: 
                                    <?php if ($vendor_exists): ?>
                                        .xlsx, .xls, .csv
                                    <?php else: ?>
                                        <strong>.csv</strong> (samo CSV dok ne instalirate PhpSpreadsheet)
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Opcije</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="send_password_reset" id="send_password_reset" value="1" checked>
                                    Pošalji email za postavljanje lozinke svim novim korisnicima
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="dry_run" id="dry_run" value="1">
                                    Test mod (ne sprema podatke, samo prikazuje što bi se desilo)
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large" id="start-import">
                            🚀 Započni Import
                        </button>
                    </p>
                </form>
            </div>
            
            <div id="import-progress" class="b2b-importer-card" style="display:none;">
                <h2>⏳ Import u tijeku...</h2>
                <div class="progress-bar-container">
                    <div class="progress-bar" id="progress-bar"></div>
                </div>
                <p id="progress-text">Priprema...</p>
            </div>
            
            <div id="import-results" class="b2b-importer-card" style="display:none;">
                <h2>✅ Rezultati importa</h2>
                <div id="results-content"></div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_process_import() {
        check_ajax_referer('b2b_import_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nemate dozvolu za ovu akciju.']);
        }
        
        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(['message' => 'Datoteka nije uploadana.']);
        }
        
        $file = $_FILES['import_file'];
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
        $send_password_reset = isset($_POST['send_password_reset']) && $_POST['send_password_reset'] === '1';
        
        $data = $this->parse_file($file);
        
        if (is_wp_error($data)) {
            wp_send_json_error(['message' => $data->get_error_message()]);
        }
        
        $results = $this->process_import($data, $dry_run, $send_password_reset);
        
        wp_send_json_success([
            'results' => $results,
            'log' => $this->log
        ]);
    }
    
    private function parse_file($file) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                // Check if PhpSpreadsheet is available
                $vendor_autoload = plugin_dir_path(__FILE__) . 'includes/vendor/autoload.php';
                if (!file_exists($vendor_autoload)) {
                    return new WP_Error(
                        'missing_dependencies',
                        'PhpSpreadsheet nije instaliran. Za Excel support, morate pokrenuti: <code>composer install --no-dev</code> u plugin folderu.<br><br>Alternativno, koristite CSV format koji ne zahtijeva dodatne biblioteke.'
                    );
                }
                require_once $vendor_autoload;
                return $this->parse_excel($file['tmp_name']);
            } elseif ($extension === 'csv') {
                return $this->parse_csv($file['tmp_name']);
            } else {
                return new WP_Error('invalid_file', 'Nepodržan format datoteke. Koristite .xlsx, .xls ili .csv format.');
            }
        } catch (Exception $e) {
            return new WP_Error('parse_error', 'Greška pri čitanju datoteke: ' . $e->getMessage());
        }
    }
    
    private function parse_excel($file_path) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // Remove header row
        array_shift($rows);
        
        $data = [];
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            $data[] = [
                'oib' => $this->clean_value($row[0]),
                'task_id' => $this->clean_value($row[1]),
                'company_name' => $this->clean_value($row[2]),
                'username' => $this->clean_value($row[3]),
                'first_name' => $this->clean_value($row[4]),
                'last_name' => $this->clean_value($row[5]),
                'email' => $this->clean_value($row[6]),
                'phone' => $this->clean_value($row[7]),
                'address_1' => $this->clean_value($row[8]),
                'address_2' => $this->clean_value($row[9]),
                'city' => $this->clean_value($row[10]),
                'postcode' => $this->clean_value($row[11]),
                'komercijalist_email' => $this->clean_value($row[12]),
            ];
        }
        
        return $data;
    }
    
    private function parse_csv($file_path) {
        $data = [];
        
        // Read file content
        $content = file_get_contents($file_path);
        
        // Remove UTF-8 BOM if present
        $content = str_replace("\xEF\xBB\xBF", '', $content);
        
        // Detect encoding and convert to UTF-8 if needed
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        // Create temporary file with cleaned content
        $temp_file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($temp_file, $content);
        
        if (($handle = fopen($temp_file, 'r')) !== false) {
            // Skip header row
            fgetcsv($handle, 0, ',');
            
            $row_num = 1;
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $row_num++;
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                // Ensure we have exactly 13 columns
                if (count($row) < 13) {
                    // Pad with empty strings
                    $row = array_pad($row, 13, '');
                }
                
                $data[] = [
                    'oib' => $this->clean_value($row[0]),
                    'task_id' => $this->clean_value($row[1]),
                    'company_name' => $this->clean_value($row[2]),
                    'username' => $this->clean_value($row[3]),
                    'first_name' => $this->clean_value($row[4]),
                    'last_name' => $this->clean_value($row[5]),
                    'email' => $this->clean_value($row[6]),
                    'phone' => $this->clean_value($row[7]),
                    'address_1' => $this->clean_value($row[8]),
                    'address_2' => $this->clean_value($row[9]),
                    'city' => $this->clean_value($row[10]),
                    'postcode' => $this->clean_value($row[11]),
                    'komercijalist_email' => $this->clean_value($row[12]),
                ];
            }
            
            fclose($handle);
        }
        
        // Clean up temp file
        unlink($temp_file);
        
        if (empty($data)) {
            return new WP_Error('empty_file', 'Datoteka ne sadrži podatke ili je u krivom formatu. Provjerite da prvi red ima zaglavlja, a drugi red podatke.');
        }
        
        return $data;
    }
    
    private function clean_value($value) {
        return trim($value);
    }
    
    private function process_import($data, $dry_run = false, $send_password_reset = true) {
        $results = [
            'total' => count($data),
            'success' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        
        foreach ($data as $index => $row) {
            $row_number = $index + 2; // +2 because of header and 0-index
            
            // Validate required fields
            if (empty($row['email']) || !is_email($row['email'])) {
                $this->add_log('error', "Red {$row_number}: Nevažeća email adresa.");
                $results['errors']++;
                continue;
            }
            
            // Check if user already exists
            $existing_user = get_user_by('email', $row['email']);
            if ($existing_user) {
                $this->add_log('warning', "Red {$row_number}: Korisnik s emailom {$row['email']} već postoji. Preskočeno.");
                $results['skipped']++;
                continue;
            }
            
            // Generate username if empty
            $username = !empty($row['username']) ? $row['username'] : $this->generate_username_from_email($row['email']);
            
            // Check if username exists
            if (username_exists($username)) {
                $username = $this->make_unique_username($username);
                $this->add_log('info', "Red {$row_number}: Username već postoji, generiran novi: {$username}");
            }
            
            if ($dry_run) {
                $this->add_log('success', "Red {$row_number}: [TEST] Korisnik {$username} ({$row['email']}) bi bio kreiran.");
                $results['success']++;
                continue;
            }
            
            // Create user
            $user_id = $this->create_b2b_user($row, $username);
            
            if (is_wp_error($user_id)) {
                $this->add_log('error', "Red {$row_number}: Greška pri kreiranju korisnika: " . $user_id->get_error_message());
                $results['errors']++;
                continue;
            }
            
            // Match and assign komercijalist
            $this->assign_komercijalist($user_id, $row['komercijalist_email'], $row_number);
            
            // Send password reset email
            if ($send_password_reset) {
                $this->send_password_reset_email($user_id);
            }
            
            $this->add_log('success', "Red {$row_number}: ✅ Korisnik {$username} ({$row['email']}) uspješno kreiran.");
            $results['success']++;
        }
        
        return $results;
    }
    
    private function generate_username_from_email($email) {
        $parts = explode('@', $email);
        return sanitize_user($parts[0], true);
    }
    
    private function make_unique_username($username) {
        $base_username = $username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    private function create_b2b_user($data, $username) {
        $user_data = [
            'user_login' => $username,
            'user_email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => 'customer',
            'user_pass' => wp_generate_password(20),
        ];
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Add custom meta fields
        update_user_meta($user_id, 'oib', $data['oib']);
        update_user_meta($user_id, 'task_id', $data['task_id']);
        
        // WooCommerce billing fields
        update_user_meta($user_id, 'billing_first_name', $data['first_name']);
        update_user_meta($user_id, 'billing_last_name', $data['last_name']);
        update_user_meta($user_id, 'billing_company', $data['company_name']);
        update_user_meta($user_id, 'billing_email', $data['email']);
        update_user_meta($user_id, 'billing_phone', $data['phone']);
        update_user_meta($user_id, 'billing_address_1', $data['address_1']);
        update_user_meta($user_id, 'billing_address_2', $data['address_2']);
        update_user_meta($user_id, 'billing_city', $data['city']);
        update_user_meta($user_id, 'billing_postcode', $data['postcode']);
        update_user_meta($user_id, 'billing_country', 'HR'); // Croatia default
        
        // WooCommerce shipping fields (same as billing)
        update_user_meta($user_id, 'shipping_first_name', $data['first_name']);
        update_user_meta($user_id, 'shipping_last_name', $data['last_name']);
        update_user_meta($user_id, 'shipping_company', $data['company_name']);
        update_user_meta($user_id, 'shipping_address_1', $data['address_1']);
        update_user_meta($user_id, 'shipping_address_2', $data['address_2']);
        update_user_meta($user_id, 'shipping_city', $data['city']);
        update_user_meta($user_id, 'shipping_postcode', $data['postcode']);
        update_user_meta($user_id, 'shipping_country', 'HR');
        
        return $user_id;
    }
    
    private function assign_komercijalist($user_id, $komercijalist_email, $row_number) {
        if (empty($komercijalist_email)) {
            $this->add_log('warning', "Red {$row_number}: Email komercijalista nije naveden.");
            return;
        }
        
        // Query komercijalist CPT by ACF email field
        $args = [
            'post_type' => 'komercijalist',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'email_commercialist',
                    'value' => $komercijalist_email,
                    'compare' => '='
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $komercijalist_id = $query->posts[0]->ID;
            update_user_meta($user_id, 'assigned_komercijalist', $komercijalist_id);
            $this->add_log('info', "Red {$row_number}: Komercijalist '{$query->posts[0]->post_title}' uspješno dodijeljen.");
        } else {
            $this->add_log('warning', "Red {$row_number}: Komercijalist s emailom '{$komercijalist_email}' nije pronađen u bazi.");
        }
        
        wp_reset_postdata();
    }
    
    private function send_password_reset_email($user_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return;
        }
        
        $key = get_password_reset_key($user);
        
        if (is_wp_error($key)) {
            return;
        }
        
        $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');
        
        $message = sprintf(
            "Poštovani,\n\nKreiran je novi korisnički račun za Dreampoint B2B webshop:\n\nKorisničko ime: %s\nEmail: %s\n\nMolimo vas da kliknete na link ispod kako biste postavili svoju lozinku:\n%s\n\nLink je važeći 24 sata.\n\nS poštovanjem,\nDreampoint B2B webshop",
            $user->user_login,
            $user->user_email,
            $reset_url
        );
        
        wp_mail(
            $user->user_email,
            'Dobrodošli u Dreampoint B2B webshop - Postavite lozinku',
            $message
        );
    }
    
    private function add_log($type, $message) {
        $this->log[] = [
            'type' => $type,
            'message' => $message,
            'time' => current_time('mysql')
        ];
    }
    
    /**
     * Display extra profile fields
     */
    public function show_extra_profile_fields($user) {
        $oib = get_user_meta($user->ID, 'oib', true);
        $task_id = get_user_meta($user->ID, 'task_id', true);
        $komercijalist_id = get_user_meta($user->ID, 'assigned_komercijalist', true);
        
        $komercijalist_name = '';
        if ($komercijalist_id) {
            $komercijalist_post = get_post($komercijalist_id);
            if ($komercijalist_post) {
                $komercijalist_name = $komercijalist_post->post_title;
                $komercijalist_email = get_field('email_commercialist', $komercijalist_id);
            }
        }
        ?>
        <h2>🏢 B2B Partner Informacije</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="oib">OIB tvrtke</label></th>
                <td>
                    <input type="text" name="oib" id="oib" value="<?php echo esc_attr($oib); ?>" class="regular-text" />
                    <p class="description">OIB tvrtke (11 znamenki)</p>
                </td>
            </tr>
            <tr>
                <th><label for="task_id">Šifra partnera (Task ID)</label></th>
                <td>
                    <input type="text" name="task_id" id="task_id" value="<?php echo esc_attr($task_id); ?>" class="regular-text" />
                    <p class="description">ID iz Task ERP sustava</p>
                </td>
            </tr>
            <tr>
                <th><label for="assigned_komercijalist">Zaduženi komercijalist</label></th>
                <td>
                    <?php
                    // Get all komercijalisti
                    $komercijalisti = get_posts([
                        'post_type' => 'komercijalist',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ]);
                    ?>
                    <select name="assigned_komercijalist" id="assigned_komercijalist" class="regular-text">
                        <option value="">-- Odaberi komercijalista --</option>
                        <?php foreach ($komercijalisti as $kom): ?>
                            <option value="<?php echo $kom->ID; ?>" <?php selected($komercijalist_id, $kom->ID); ?>>
                                <?php 
                                echo esc_html($kom->post_title);
                                $email = get_field('email_commercialist', $kom->ID);
                                if ($email) {
                                    echo ' (' . esc_html($email) . ')';
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($komercijalist_id && $komercijalist_name): ?>
                        <p class="description">
                            Trenutno dodijeljen: <strong><?php echo esc_html($komercijalist_name); ?></strong>
                            <?php if (!empty($komercijalist_email)): ?>
                                (<?php echo esc_html($komercijalist_email); ?>)
                            <?php endif; ?>
                            <br>
                            <a href="<?php echo get_edit_post_link($komercijalist_id); ?>" target="_blank">Uredi komercijalista →</a>
                        </p>
                    <?php else: ?>
                        <p class="description">Odaberi zaduženog komercijalista za ovog B2B partnera</p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save extra profile fields
     */
    public function save_extra_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['oib'])) {
            update_user_meta($user_id, 'oib', sanitize_text_field($_POST['oib']));
        }
        
        if (isset($_POST['task_id'])) {
            update_user_meta($user_id, 'task_id', sanitize_text_field($_POST['task_id']));
        }
        
        if (isset($_POST['assigned_komercijalist'])) {
            $komercijalist_id = intval($_POST['assigned_komercijalist']);
            if ($komercijalist_id > 0) {
                update_user_meta($user_id, 'assigned_komercijalist', $komercijalist_id);
            } else {
                delete_user_meta($user_id, 'assigned_komercijalist');
            }
        }
    }
    
    /**
     * Add custom columns to users list
     */
    public function add_user_columns($columns) {
        $columns['b2b_company'] = 'Tvrtka';
        $columns['b2b_komercijalist'] = 'Komercijalist';
        $columns['b2b_task_id'] = 'Task ID';
        return $columns;
    }
    
    /**
     * Show custom column content
     */
    public function show_user_column_content($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'b2b_company':
                $company = get_user_meta($user_id, 'billing_company', true);
                if (!$company) {
                    return '—';
                }
                $filter_url = add_query_arg(
                    'billing_company_filter',
                    rawurlencode($company),
                    admin_url('users.php')
                );
                return '<a href="' . esc_url($filter_url) . '">' . esc_html($company) . '</a>';
                
            case 'b2b_komercijalist':
                $komercijalist_id = get_user_meta($user_id, 'assigned_komercijalist', true);
                if ($komercijalist_id) {
                    $komercijalist = get_post($komercijalist_id);
                    if ($komercijalist) {
                        return '<a href="' . get_edit_post_link($komercijalist_id) . '" target="_blank">' . 
                               esc_html($komercijalist->post_title) . '</a>';
                    }
                }
                return '—';
                
            case 'b2b_task_id':
                $task_id = get_user_meta($user_id, 'task_id', true);
                return $task_id ? '<code>' . esc_html($task_id) . '</code>' : '—';
        }
        
        return $value;
    }

    /**
     * Make the Tvrtka column sortable.
     */
    public function make_company_column_sortable($columns) {
        $columns['b2b_company'] = 'b2b_company';
        return $columns;
    }

    /**
     * Handle pre_user_query: company name sort, exact filter, and search extension.
     *
     * Sorting:  orderby=b2b_company  -> LEFT JOIN on billing_company meta
     * Filter:   ?billing_company_filter=X -> INNER JOIN + exact WHERE match
     * Search:   ?s=X -> inject OR subquery into WP search AND block
     */
    public function handle_company_query(WP_User_Query $q) {
        global $wpdb;

        if (!is_admin()) {
            return;
        }

        // Sort by company name
        if ($q->get('orderby') === 'b2b_company') {
            $order = strtoupper($q->get('order')) === 'ASC' ? 'ASC' : 'DESC';
            $q->query_from .= " LEFT JOIN {$wpdb->usermeta} AS um_b2b_co"
                . " ON ({$wpdb->users}.ID = um_b2b_co.user_id AND um_b2b_co.meta_key = 'billing_company')";
            $q->query_orderby = "ORDER BY um_b2b_co.meta_value {$order}";
        }

        // Filter by exact company name (set via column link click)
        $company_filter = isset($_GET['billing_company_filter'])
            ? sanitize_text_field(wp_unslash($_GET['billing_company_filter']))
            : '';

        if ($company_filter !== '') {
            $q->query_from .= " INNER JOIN {$wpdb->usermeta} AS um_co_filter"
                . " ON ({$wpdb->users}.ID = um_co_filter.user_id AND um_co_filter.meta_key = 'billing_company')";
            $q->query_where .= $wpdb->prepare(
                ' AND um_co_filter.meta_value = %s',
                $company_filter
            );
            return;
        }

        // Extend admin search to also match billing_company
        $search = $q->get('search');
        if (empty($search)) {
            return;
        }

        $term = trim($search, '*');
        if ($term === '') {
            return;
        }

        $like = '%' . $wpdb->esc_like($term) . '%';

        // Get user IDs that match billing_company — direct query, no WP format dependency.
        $company_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billing_company' AND meta_value LIKE %s",
            $like
        ));

        if (empty($company_ids)) {
            return;
        }

        $ids_sql = implode(',', array_map('intval', $company_ids));

        // WP 7.x search AND block ends with a single closing ).
        // Inject OR before it regardless of the internal LIKE value format.
        $last_paren = strrpos($q->query_where, ')');
        if ($last_paren !== false) {
            $q->query_where = substr_replace(
                $q->query_where,
                " OR {$wpdb->users}.ID IN ({$ids_sql}))",
                $last_paren,
                1
            );
        }
    }
}

// Initialize plugin
new B2B_Partner_Importer();
