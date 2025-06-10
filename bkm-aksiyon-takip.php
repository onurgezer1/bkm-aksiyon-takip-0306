<?php
/**
 * Plugin Name: BKM AKSİYON TAKİP
 * Plugin URI: https://github.com/anadolubirlik/BKMAksiyonTakip_Claude4
 * Description: WordPress eklentisi ile aksiyon ve görev takip sistemi
 * Version: 1.0.2
 * Author: Anadolu Birlik
 * Text Domain: bkm-aksiyon-takip
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BKM_AKSIYON_TAKIP_VERSION', '1.0.2');
define('BKM_AKSIYON_TAKIP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BKM_AKSIYON_TAKIP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BKM_AKSIYON_TAKIP_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class BKM_Aksiyon_Takip {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        
        // Add shortcode
        add_shortcode('aksiyon_takipx', array($this, 'shortcode_handler'));
        
        // Add AJAX handlers
        add_action('wp_ajax_bkm_refresh_stats', array($this, 'ajax_refresh_stats'));
        add_action('wp_ajax_bkm_delete_item', array($this, 'ajax_delete_item'));
        add_action('wp_ajax_bkm_update_task_progress', array($this, 'ajax_update_task_progress'));
        
        // Custom login handling
        add_action('wp_login_failed', array($this, 'handle_login_failed'), 10, 2);
        add_filter('authenticate', array($this, 'custom_authenticate'), 30, 3);
    }
    
    /**
     * Get current page URL
     */
    private function get_current_page_url() {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request));
    }
    
    /**
     * Handle login failures
     */
    public function handle_login_failed($username, $error) {
        // Redirect back to login page with error message
        $redirect_url = $this->get_current_page_url();
        $redirect_url = add_query_arg('login_error', urlencode($error->get_error_message()), $redirect_url);
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    /**
     * Custom authentication
     */
    public function custom_authenticate($user, $username, $password) {
        if (is_wp_error($user)) {
            return $user;
        }
        
        if (!empty($username) && !empty($password)) {
            $user = wp_authenticate_username_password(null, $username, $password);
            if (is_wp_error($user)) {
                return $user;
            }
            
            // Set auth cookie
            wp_set_auth_cookie($user->ID, isset($_POST['rememberme']));
            
            // Redirect to the current page after successful login
            $redirect_url = $this->get_current_page_url();
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        return $user;
    }
    
    /**
     * Plugin initialization
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('bkm-aksiyon-takip', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Handle custom login form submission
        if (isset($_POST['bkm_login_submit']) && isset($_POST['bkm_nonce']) && wp_verify_nonce($_POST['bkm_nonce'], 'bkm_login_nonce')) {
            $username = sanitize_text_field($_POST['log']);
            $password = $_POST['pwd'];
            $remember = isset($_POST['rememberme']) ? true : false;
            
            $user = wp_signon(array(
                'user_login' => $username,
                'user_password' => $password,
                'remember' => $remember
            ), is_ssl());
            
            if (is_wp_error($user)) {
                // Store error message in transient to display on redirect
                set_transient('bkm_login_error', $user->get_error_message(), 30);
                wp_safe_redirect($this->get_current_page_url());
                exit;
            } else {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, $remember);
                wp_safe_redirect($this->get_current_page_url());
                exit;
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check requirements
        $this->check_requirements();
        
        $this->create_database_tables();
        
        // Create default categories
        //$this->create_default_data();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('bkm_aksiyon_takip_activated', true);
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        global $wp_version;
        
        if (version_compare($wp_version, '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Bu eklenti WordPress 5.0 veya üzeri sürüm gerektirir.');
        }
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Bu eklenti PHP 7.4 veya üzeri sürüm gerektirir.');
        }
        
        if (!function_exists('wp_mail')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Bu eklenti wp_mail fonksiyonuna ihtiyaç duyar.');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Actions table
        $actions_table = $wpdb->prefix . 'bkm_actions';
        $actions_sql = "CREATE TABLE $actions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tanımlayan_id bigint(20) UNSIGNED NOT NULL,
            onem_derecesi tinyint(1) NOT NULL DEFAULT 1,
            acilma_tarihi date NOT NULL,
            hafta int(11) NOT NULL,
            kategori_id mediumint(9) NOT NULL,
            sorumlu_ids text NOT NULL,
            tespit_konusu text NOT NULL,
            aciklama text NOT NULL,
            hedef_tarih date NOT NULL,
            kapanma_tarihi date NULL,
            performans_id mediumint(9) NOT NULL,
            ilerleme_durumu int(3) NOT NULL DEFAULT 0,
            notlar text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Categories table
        $categories_table = $wpdb->prefix . 'bkm_categories';
        $categories_sql = "CREATE TABLE $categories_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Performance table
        $performance_table = $wpdb->prefix . 'bkm_performance';
        $performance_sql = "CREATE TABLE $performance_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Tasks table
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        $tasks_sql = "CREATE TABLE $tasks_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            action_id mediumint(9) NOT NULL,
            content text NOT NULL,
            baslangic_tarihi date NOT NULL,
            sorumlu_id bigint(20) UNSIGNED NOT NULL,
            hedef_bitis_tarihi date NOT NULL,
            ilerleme_durumu int(3) NOT NULL DEFAULT 0,
            gercek_bitis_tarihi datetime NULL,
            tamamlandi tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action_id (action_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($actions_sql);
        dbDelta($categories_sql);
        dbDelta($performance_sql);
        dbDelta($tasks_sql);
        
        // Update database version
        update_option('bkm_aksiyon_takip_db_version', BKM_AKSIYON_TAKIP_VERSION);
    }
    
    /**
     * Create default data
     */
    private function create_default_data() {
        global $wpdb;
        
        // Default categories
        $categories = array(
            'Kalite',
            'Üretim',
            'Satış',
            'İnsan Kaynakları',
            'Bilgi İşlem'
        );
        
        $categories_table = $wpdb->prefix . 'bkm_categories';
        foreach ($categories as $category) {
            $wpdb->insert($categories_table, array('name' => $category));
        }
        
        // Default performance data
        $performances = array(
            'Düşük',
            'Orta',
            'Yüksek',
            'Kritik'
        );
        
        $performance_table = $wpdb->prefix . 'bkm_performance';
        foreach ($performances as $performance) {
            $wpdb->insert($performance_table, array('name' => $performance));
        }
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        // Main menu
        add_menu_page(
            'BKM Aksiyon Takip',
            'Aksiyon Takip',
            'edit_posts',
            'bkm-aksiyon-takip',
            array($this, 'admin_page_main'),
            'dashicons-clipboard',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Aksiyon Ekle',
            'Aksiyon Ekle',
            'edit_posts',
            'bkm-aksiyon-ekle',
            array($this, 'admin_page_add_action')
        );
        
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Kategoriler',
            'Kategoriler',
            'edit_posts',
            'bkm-kategoriler',
            array($this, 'admin_page_categories')
        );
        
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Performanslar',
            'Performanslar',
            'edit_posts',
            'bkm-performanslar',
            array($this, 'admin_page_performance')
        );
        
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Raporlar',
            'Raporlar',
            'edit_posts',
            'bkm-raporlar',
            array($this, 'admin_page_reports')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook_suffix) {
        if (strpos($hook_suffix, 'bkm-') !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
            
            wp_enqueue_script(
                'bkm-admin-js',
                BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'jquery-ui-datepicker'),
                BKM_AKSIYON_TAKIP_VERSION,
                true
            );
            
            wp_enqueue_style(
                'bkm-admin-css',
                BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                BKM_AKSIYON_TAKIP_VERSION
            );
            
            wp_localize_script('bkm-admin-js', 'bkmAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bkm_ajax_nonce')
            ));
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_enqueue_scripts() {
        wp_enqueue_script('jquery');
        
        wp_enqueue_script(
            'bkm-frontend-js',
            BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            BKM_AKSIYON_TAKIP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'bkm-frontend-css',
            BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            BKM_AKSIYON_TAKIP_VERSION
        );
        
        wp_localize_script('bkm-frontend-js', 'bkmFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bkm_frontend_nonce')
        ));
    }
    
    /**
     * Main admin page
     */
    public function admin_page_main() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/main.php';
    }
    
    /**
     * Add action admin page
     */
    public function admin_page_add_action() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/add-action.php';
    }
    
    /**
     * Categories admin page
     */
    public function admin_page_categories() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/categories.php';
    }
    
    /**
     * Performance admin page
     */
    public function admin_page_performance() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/performance.php';
    }
    
    /**
     * Reports admin page
     */
    public function admin_page_reports() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/reports.php';
    }
    
    /**
     * Handle shortcode
     */
    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(), $atts, 'aksiyon_takipx');
        
        ob_start();
        if (!is_user_logged_in()) {
            include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'frontend/login.php';
        } else {
            include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'frontend/dashboard.php';
        }
        return ob_get_clean();
    }
    
    /**
     * Send email notification
     */
    public function send_email_notification($type, $data) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        switch ($type) {
            case 'action_created':
                $subject = sprintf('[%s] Yeni Aksiyon Oluşturuldu', $site_name);
                $message = sprintf(
                    "Merhaba,\n\nYeni bir aksiyon oluşturuldu:\n\nAksiyon ID: %d\nTanımlayan: %s\nKategori: %s\nAçıklama: %s\n\nDetayları görmek için admin paneline giriş yapın.\n\nSaygılar,\n%s",
                    $data['id'],
                    $data['tanımlayan'],
                    $data['kategori'],
                    $data['aciklama'],
                    $site_name
                );
                break;
                
            case 'task_created':
                $subject = sprintf('[%s] Yeni Görev Atandı', $site_name);
                $message = sprintf(
                    "Merhaba,\n\nSize yeni bir görev atandı:\n\nGörev: %s\nAksiyon ID: %d\nBaşlangıç Tarihi: %s\nHedef Tarih: %s\n\nDetayları görmek için sisteme giriş yapın.\n\nSaygılar,\n%s",
                    $data['content'],
                    $data['action_id'],
                    $data['baslangic_tarihi'],
                    $data['hedef_bitis_tarihi'],
                    $site_name
                );
                break;
                
            case 'task_completed':
                $subject = sprintf('[%s] Görev Tamamlandı', $site_name);
                $message = sprintf(
                    "Merhaba,\n\nBir görev tamamlandı:\n\nGörev: %s\nTamamlayan: %s\nTamamlanma Tarihi: %s\n\nDetayları görmek için admin paneline giriş yapın.\n\nSaygılar,\n%s",
                    $data['content'],
                    $data['sorumlu'],
                    $data['tamamlanma_tarihi'],
                    $site_name
                );
                break;
        }
        
        // Send to admin
        wp_mail($admin_email, $subject, $message);
        
        // Send to responsible users if specified
        if (isset($data['sorumlu_emails']) && is_array($data['sorumlu_emails'])) {
            foreach ($data['sorumlu_emails'] as $email) {
                wp_mail($email, $subject, $message);
            }
        }
    }
    
    /**
     * AJAX: Refresh dashboard stats
     */
    public function ajax_refresh_stats() {
        check_ajax_referer('bkm_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Unauthorized', 'bkm-aksiyon-takip'));
        }
        
        global $wpdb;
        
        $actions_table = $wpdb->prefix . 'bkm_actions';
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        
        $total_actions = $wpdb->get_var("SELECT COUNT(*) FROM $actions_table");
        $open_actions = $wpdb->get_var("SELECT COUNT(*) FROM $actions_table WHERE kapanma_tarihi IS NULL");
        $closed_actions = $wpdb->get_var("SELECT COUNT(*) FROM $actions_table WHERE kapanma_tarihi IS NOT NULL");
        $total_tasks = $wpdb->get_var("SELECT COUNT(*) FROM $tasks_table");
        $completed_tasks = $wpdb->get_var("SELECT COUNT(*) FROM $tasks_table WHERE tamamlandi = 1");
        
        wp_send_json_success(array(
            'stats' => array($total_actions, $open_actions, $closed_actions, $total_tasks, $completed_tasks)
        ));
    }
    
    /**
     * AJAX: Delete item
     */
    public function ajax_delete_item() {
        check_ajax_referer('bkm_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Unauthorized', 'bkm-aksiyon-takip'));
        }
        
        $type = sanitize_text_field($_POST['type']);
        $id = intval($_POST['id']);
        
        global $wpdb;
        
        if ($type === 'category') {
            $table = $wpdb->prefix . 'bkm_categories';
            $usage_table = $wpdb->prefix . 'bkm_actions';
            $usage_column = 'kategori_id';
        } elseif ($type === 'performance') {
            $table = $wpdb->prefix . 'bkm_performance';
            $usage_table = $wpdb->prefix . 'bkm_actions';
            $usage_column = 'performans_id';
        } else {
            wp_send_json_error(array('message' => 'Geçersiz tip.'));
        }
        
        // Check usage
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $usage_table WHERE $usage_column = %d",
            $id
        ));
        
        if ($usage_count > 0) {
            wp_send_json_error(array('message' => 'Bu öğe kullanımda olduğu için silinemez.'));
        }
        
        $result = $wpdb->delete($table, array('id' => $id), array('%d'));
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Öğe başarıyla silindi.'));
        } else {
            wp_send_json_error(array('message' => 'Silme işlemi başarısız.'));
        }
    }
    
    /**
     * AJAX: Update task progress
     */
    public function ajax_update_task_progress() {
        check_ajax_referer('bkm_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('Unauthorized', 'bkm-aksiyon-takip'));
        }
        
        $task_id = intval($_POST['task_id']);
        $progress = intval($_POST['progress']);
        
        global $wpdb;
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        
        // Check if user owns this task
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE id = %d AND sorumlu_id = %d",
            $task_id, get_current_user_id()
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Bu görevi güncelleme yetkiniz yok.'));
        }
        
        $result = $wpdb->update(
            $tasks_table,
            array('ilerleme_durumu' => $progress),
            array('id' => $task_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'İlerleme güncellendi.'));
        } else {
            wp_send_json_error(array('message' => 'Güncelleme başarısız.'));
        }
    }
}

// Initialize plugin
BKM_Aksiyon_Takip::get_instance();