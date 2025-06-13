<?php
/**
 * Plugin Name: BKM AKSİYON TAKİP
 * Plugin URI: https://github.com/anadolubirlik/BKMAksiyonTakip_Claude4
 * Description: WordPress eklentisi ile aksiyon ve görev takip sistemi
 * Version: 1.0.4
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
define('BKM_AKSIYON_TAKIP_VERSION', '1.0.4');
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
        
        // Note AJAX handlers
        add_action('wp_ajax_bkm_add_note', array($this, 'ajax_add_note'));
        add_action('wp_ajax_nopriv_bkm_add_note', array($this, 'ajax_add_note'));
        add_action('wp_ajax_bkm_reply_note', array($this, 'ajax_reply_note'));
        add_action('wp_ajax_nopriv_bkm_reply_note', array($this, 'ajax_reply_note'));
        add_action('wp_ajax_bkm_get_notes', array($this, 'ajax_get_notes'));
        add_action('wp_ajax_nopriv_bkm_get_notes', array($this, 'ajax_get_notes'));
        add_action('wp_ajax_bkm_check_tables', array($this, 'ajax_check_tables'));
        add_action('wp_ajax_nopriv_bkm_check_tables', array($this, 'ajax_check_tables'));
        
        // Action AJAX handlers
        add_action('wp_ajax_bkm_add_action', array($this, 'ajax_add_action'));
        add_action('wp_ajax_nopriv_bkm_add_action', array($this, 'ajax_add_action'));
        
        // Task AJAX handlers
        add_action('wp_ajax_bkm_add_task', array($this, 'ajax_add_task'));
        add_action('wp_ajax_nopriv_bkm_add_task', array($this, 'ajax_add_task'));
        add_action('wp_ajax_bkm_get_task_notes', array($this, 'ajax_get_task_notes'));
        add_action('wp_ajax_nopriv_bkm_get_task_notes', array($this, 'ajax_get_task_notes'));
        
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
        // Check and create missing database tables
        $this->check_and_create_tables();
        
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
    
    // Task Notes table
    $notes_table = $wpdb->prefix . 'bkm_task_notes';
    $notes_sql = "CREATE TABLE $notes_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        task_id mediumint(9) NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        content text NOT NULL,
        parent_note_id mediumint(9) NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY task_id (task_id),
        KEY parent_note_id (parent_note_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($actions_sql);
    dbDelta($categories_sql);
    dbDelta($performance_sql);
    dbDelta($tasks_sql);
    dbDelta($notes_sql);
    
    // Update database version
    update_option('bkm_aksiyon_takip_db_version', BKM_AKSIYON_TAKIP_VERSION);
}

    /**
     * Check and create missing tables
     */
    private function check_and_create_tables() {
        global $wpdb;
        
        $required_tables = array(
            'bkm_actions',
            'bkm_categories', 
            'bkm_performance',
            'bkm_tasks',
            'bkm_task_notes'
        );
        
        $missing_tables = array();
        
        foreach ($required_tables as $table_name) {
            $full_table_name = $wpdb->prefix . $table_name;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            
            if (!$exists) {
                $missing_tables[] = $table_name;
                error_log("BKM Aksiyon Takip: Tablo eksik - $full_table_name");
            } else {
                error_log("BKM Aksiyon Takip: Tablo mevcut - $full_table_name");
            }
        }
        
        // If any table is missing, create all tables
        if (!empty($missing_tables)) {
            error_log('BKM Aksiyon Takip: Eksik tablolar tespit edildi: ' . implode(', ', $missing_tables));
            error_log('BKM Aksiyon Takip: Tüm tablolar yeniden oluşturuluyor...');
            $this->create_database_tables();
            
            // Verify tables were created
            foreach ($missing_tables as $table_name) {
                $full_table_name = $wpdb->prefix . $table_name;
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
                if ($exists) {
                    error_log("BKM Aksiyon Takip: Tablo başarıyla oluşturuldu - $full_table_name");
                } else {
                    error_log("BKM Aksiyon Takip: Tablo oluşturulamadı - $full_table_name");
                }
            }
        } else {
            error_log('BKM Aksiyon Takip: Tüm tablolar mevcut.');
        }
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
            
        case 'note_added':
            $subject = sprintf('[%s] Göreve Yeni Not Eklendi', $site_name);
            $message = sprintf(
                "Merhaba,\n\nBir göreve yeni bir not eklendi:\n\nGörev ID: %d\nAksiyon ID: %d\nNot: %s\nEkleyen: %s\n\nDetayları görmek için sisteme giriş yapın.\n\nSaygılar,\n%s",
                $data['task_id'],
                $data['action_id'],
                $data['content'],
                $data['sorumlu'],
                $site_name
            );
            break;
            
        case 'note_replied':
            $subject = sprintf('[%s] Görev Notuna Cevap Verildi', $site_name);
            $message = sprintf(
                "Merhaba,\n\nBir görev notuna cevap verildi:\n\nGörev ID: %d\nAksiyon ID: %d\nCevap: %s\nCevaplayan: %s\n\nDetayları görmek için sisteme giriş yapın.\n\nSaygılar,\n%s",
                $data['task_id'],
                $data['action_id'],
                $data['content'],
                $data['sorumlu'],
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
    
    /**
     * AJAX handler for adding notes
     */
    public function ajax_add_note() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik doğrulaması başarısız.'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmalısınız.'));
            return;
        }
        
        global $wpdb;
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        
        $task_id = intval($_POST['task_id']);
        $content = sanitize_textarea_field($_POST['content']);
        
        // Validate input
        if (empty($content)) {
            wp_send_json_error(array('message' => 'Not içeriği boş olamaz.'));
            return;
        }
        
        // Check tables exist
        $notes_table = $wpdb->prefix . 'bkm_task_notes';
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$notes_table'");
        if (!$table_exists) {
            error_log("BKM Aksiyon Takip: Notes table missing - $notes_table");
            wp_send_json_error(array('message' => 'Veritabanı tablosu bulunamadı. Plugin yöneticisine başvurun.'));
            return;
        }
        
        // Get task and check permissions
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE id = %d",
            $task_id
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Görev bulunamadı.'));
            return;
        }
        
        $is_admin = current_user_can('manage_options');
        if ($task->sorumlu_id != $current_user_id && !$is_admin) {
            wp_send_json_error(array('message' => 'Bu göreve not ekleme yetkiniz yok.'));
            return;
        }
        
        // Insert note
        $result = $wpdb->insert(
            $notes_table,
            array(
                'task_id' => $task_id,
                'user_id' => $current_user_id,
                'content' => $content,
                'parent_note_id' => null
            ),
            array('%d', '%d', '%s', '%d')
        );
        
        if ($result === false) {
            error_log("BKM Aksiyon Takip: Note insert failed. Error: " . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Not eklenirken veritabanı hatası oluştu: ' . $wpdb->last_error));
            return;
        }
        
        // Get the new note with user info
        $note_id = $wpdb->insert_id;
        $new_note = $wpdb->get_row($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name 
             FROM $notes_table n 
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID 
             WHERE n.id = %d",
            $note_id
        ));
            
            // Send email notification
            $notification_data = array(
                'content' => $content,
                'action_id' => $task->action_id,
                'task_id' => $task_id,
                'sorumlu' => $current_user->display_name,
                'sorumlu_emails' => array(get_user_by('ID', $task->sorumlu_id)->user_email)
            );
            
            $this->send_email_notification('note_added', $notification_data);
            
            // Return HTML for the new note
            $note_html = '<div class="bkm-note-item" data-level="0" data-note-id="' . $new_note->id . '">';
            $note_html .= '<div class="bkm-note-content">';
            $note_html .= '<p><strong>' . esc_html($new_note->user_name) . ':</strong> ' . esc_html($new_note->content) . '</p>';
            $note_html .= '<div class="bkm-note-meta">' . date('d.m.Y H:i', strtotime($new_note->created_at)) . '</div>';
            // All logged-in users can reply to notes
            $note_html .= '<button class="bkm-btn bkm-btn-small" onclick="toggleReplyForm(' . $task_id . ', ' . $new_note->id . ')">Notu Cevapla</button>';
            $note_html .= '<div id="reply-form-' . $task_id . '-' . $new_note->id . '" class="bkm-note-form" style="display: none;">';
            $note_html .= '<form class="bkm-reply-form" data-task-id="' . $task_id . '" data-parent-id="' . $new_note->id . '">';
            $note_html .= '<textarea name="note_content" rows="3" placeholder="Cevabınızı buraya yazın..." required></textarea>';
            $note_html .= '<div class="bkm-form-actions">';
            $note_html .= '<button type="submit" class="bkm-btn bkm-btn-primary bkm-btn-small">Cevap Gönder</button>';
            $note_html .= '<button type="button" class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleReplyForm(' . $task_id . ', ' . $new_note->id . ')">İptal</button>';
            $note_html .= '</div>';
            $note_html .= '</form>';
            $note_html .= '</div>';
            $note_html .= '</div>';
            $note_html .= '</div>';
            
            wp_send_json_success(array(
                'message' => 'Not başarıyla eklendi.',
                'note_html' => $note_html,
                'note_id' => $note_id
            ));
    }
    
    /**
     * AJAX handler for replying to notes
     */
    public function ajax_reply_note() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik doğrulaması başarısız.'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmalısınız.'));
            return;
        }
        
        global $wpdb;
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        
        $task_id = intval($_POST['task_id']);
        $parent_note_id = intval($_POST['parent_note_id']);
        $content = sanitize_textarea_field($_POST['content']);
        
        // Validate input
        if (empty($content)) {
            wp_send_json_error(array('message' => 'Cevap içeriği boş olamaz.'));
            return;
        }
        
        // Check if user has permission to reply (all logged-in users can reply to notes)
        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => 'Cevap yazma yetkiniz yok.'));
            return;
        }
        
        // Check tables exist
        $notes_table = $wpdb->prefix . 'bkm_task_notes';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$notes_table'");
        if (!$table_exists) {
            error_log("BKM Aksiyon Takip: Notes table missing in reply - $notes_table");
            wp_send_json_error(array('message' => 'Veritabanı tablosu bulunamadı. Plugin yöneticisine başvurun.'));
            return;
        }
        
        // Insert reply note
        $result = $wpdb->insert(
            $notes_table,
            array(
                'task_id' => $task_id,
                'user_id' => $current_user_id,
                'content' => $content,
                'parent_note_id' => $parent_note_id
            ),
            array('%d', '%d', '%s', '%d')
        );
        
        if ($result === false) {
            error_log("BKM Aksiyon Takip: Reply note insert failed. Error: " . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Cevap eklenirken veritabanı hatası oluştu: ' . $wpdb->last_error));
            return;
        }
        
        if ($result !== false) {
            // Get the new note with user info
            $note_id = $wpdb->insert_id;
            $new_note = $wpdb->get_row($wpdb->prepare(
                "SELECT n.*, u.display_name as user_name 
                 FROM $notes_table n 
                 LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID 
                 WHERE n.id = %d",
                $note_id
            ));
            
            // Get parent note to determine level
            $parent_note = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $notes_table WHERE id = %d",
                $parent_note_id
            ));
            
            $level = 1; // Default for first level reply
            if ($parent_note && $parent_note->parent_note_id) {
                $level = 2; // Second level reply or deeper
            }
            
            // Send email notification
            $tasks_table = $wpdb->prefix . 'bkm_tasks';
            $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tasks_table WHERE id = %d", $task_id));
            
            $notification_data = array(
                'content' => $content,
                'action_id' => $task->action_id,
                'task_id' => $task_id,
                'sorumlu' => $current_user->display_name,
                'sorumlu_emails' => array(get_user_by('ID', $task->sorumlu_id)->user_email)
            );
            
            $this->send_email_notification('note_replied', $notification_data);
            
            // Return HTML for the new reply note
            $note_html = '<div class="bkm-note-item bkm-note-reply" data-level="' . $level . '" data-note-id="' . $new_note->id . '">';
            $note_html .= '<div class="bkm-note-content">';
            $note_html .= '<p><strong>' . esc_html($new_note->user_name) . ':</strong> ' . esc_html($new_note->content) . '</p>';
            $note_html .= '<div class="bkm-note-meta">' . date('d.m.Y H:i', strtotime($new_note->created_at)) . '</div>';
            $note_html .= '</div>';
            $note_html .= '</div>';
            
            wp_send_json_success(array(
                'message' => 'Cevap başarıyla eklendi.',
                'note_html' => $note_html,
                'note_id' => $note_id,
                'parent_id' => $parent_note_id
            ));
    }
    }
    
    /**
     * AJAX handler for getting notes
     */
    public function ajax_get_notes() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik doğrulaması başarısız.'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmalısınız.'));
            return;
        }
        
        global $wpdb;
        $task_id = intval($_POST['task_id']);
        
        // Check tables exist
        $notes_table = $wpdb->prefix . 'bkm_task_notes';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$notes_table'");
        if (!$table_exists) {
            error_log("BKM Aksiyon Takip: Notes table missing in get_notes - $notes_table");
            wp_send_json_error(array('message' => 'Veritabanı tablosu bulunamadı. Plugin yöneticisine başvurun.'));
            return;
        }
        
        // Get all notes for this task
        $notes_table = $wpdb->prefix . 'bkm_task_notes';
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name 
             FROM $notes_table n 
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID 
             WHERE n.task_id = %d 
             ORDER BY n.created_at ASC",
            $task_id
        ));
        
        if (empty($notes)) {
            wp_send_json_success(array(
                'notes_html' => '<p>Bu görev için henüz not bulunmamaktadır.</p>',
                'notes_count' => 0
            ));
            return;
        }
        
        // Generate notes HTML
        ob_start();
        $is_admin = current_user_can('manage_options');
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tasks_table WHERE id = %d", $task_id));
        
        // Include the display_notes function logic here
        $this->render_notes_html($notes, null, 0, $is_admin, $task);
        $notes_html = ob_get_clean();
        
        wp_send_json_success(array(
            'notes_html' => $notes_html,
            'notes_count' => count($notes)
        ));
    }
    
    /**
     * Helper function to render notes HTML
     */
    private function render_notes_html($notes, $parent_id = null, $level = 0, $is_admin = false, $task = null) {
        foreach ($notes as $note) {
            if ($note->parent_note_id == $parent_id) {
                $reply_class = ($note->parent_note_id ? ' bkm-note-reply' : '');
                
                echo '<div class="bkm-note-item' . $reply_class . '" data-level="' . $level . '" data-note-id="' . $note->id . '">';
                echo '<div class="bkm-note-content">';
                echo '<p><strong>' . esc_html($note->user_name) . ':</strong> ' . esc_html($note->content) . '</p>';
                echo '<div class="bkm-note-meta">' . date('d.m.Y H:i', strtotime($note->created_at)) . '</div>';
                
                // All logged-in users can reply to notes
                if ($task) {
                    echo '<button class="bkm-btn bkm-btn-small" onclick="toggleReplyForm(' . esc_js($task->id) . ', ' . esc_js($note->id) . ')">Notu Cevapla</button>';
                    echo '<div id="reply-form-' . esc_attr($task->id) . '-' . esc_attr($note->id) . '" class="bkm-note-form" style="display: none;">';
                    echo '<form class="bkm-reply-form" data-task-id="' . esc_attr($task->id) . '" data-parent-id="' . esc_attr($note->id) . '">';
                    echo '<textarea name="note_content" rows="3" placeholder="Cevabınızı buraya yazın..." required></textarea>';
                    echo '<div class="bkm-form-actions">';
                    echo '<button type="submit" class="bkm-btn bkm-btn-primary bkm-btn-small">Cevap Gönder</button>';
                    echo '<button type="button" class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleReplyForm(' . esc_js($task->id) . ', ' . esc_js($note->id) . ')">İptal</button>';
                    echo '</div>';
                    echo '</form>';
                    echo '</div>';
                }
                
                echo '</div>';
                echo '</div>';
                
                // Recursively display child notes
                $this->render_notes_html($notes, $note->id, $level + 1, $is_admin, $task);
            }
        }
    }
    
    /**
     * AJAX handler for checking tables
     */
    public function ajax_check_tables() {
        global $wpdb;
        
        $required_tables = array(
            'bkm_actions',
            'bkm_categories', 
            'bkm_performance',
            'bkm_tasks',
            'bkm_task_notes'
        );
        
        $table_status = array();
        $missing_count = 0;
        
        foreach ($required_tables as $table_name) {
            $full_table_name = $wpdb->prefix . $table_name;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
                $table_status[$table_name] = array(
                    'exists' => true,
                    'count' => intval($count),
                    'full_name' => $full_table_name
                );
            } else {
                $table_status[$table_name] = array(
                    'exists' => false,
                    'count' => 0,
                    'full_name' => $full_table_name
                );
                $missing_count++;
            }
        }
        
        wp_send_json_success(array(
            'message' => $missing_count > 0 ? 
                "$missing_count tablo eksik!" : 
                "Tüm tablolar mevcut",
            'tables' => $table_status,
            'missing_count' => $missing_count,
            'database_prefix' => $wpdb->prefix
        ));
    }
    
    /**
     * AJAX handler for adding actions
     */
    public function ajax_add_action() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik doğrulaması başarısız.'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmalısınız.'));
            return;
        }
        
        // Check if user has permission to add actions (only admins)
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Aksiyon ekleme yetkiniz yok.'));
            return;
        }
        
        global $wpdb;
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        
        // Get and validate input
        $kategori_id = intval($_POST['kategori_id']);
        $performans_id = intval($_POST['performans_id']);
        $sorumlu_ids = isset($_POST['sorumlu_ids']) ? array_map('intval', $_POST['sorumlu_ids']) : array();
        $tespit_konusu = sanitize_textarea_field($_POST['tespit_konusu']);
        $aciklama = sanitize_textarea_field($_POST['aciklama']);
        $hedef_tarih = sanitize_text_field($_POST['hedef_tarih']);
        $onem_derecesi = intval($_POST['onem_derecesi']);
        
        // Validate required fields
        if (empty($kategori_id) || empty($performans_id) || empty($sorumlu_ids) || 
            empty($tespit_konusu) || empty($aciklama) || empty($hedef_tarih) || empty($onem_derecesi)) {
            wp_send_json_error(array('message' => 'Lütfen tüm zorunlu alanları doldurun.'));
            return;
        }
        
        // Validate date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hedef_tarih)) {
            wp_send_json_error(array('message' => 'Geçersiz tarih formatı.'));
            return;
        }
        
        // Check tables exist
        $actions_table = $wpdb->prefix . 'bkm_actions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$actions_table'");
        if (!$table_exists) {
            wp_send_json_error(array('message' => 'Veritabanı tabloları eksik.'));
            return;
        }
        
        // Convert sorumlu_ids array to comma-separated string
        $sorumlu_ids_string = implode(',', $sorumlu_ids);
        
        // Auto-generate acilma_tarihi and hafta
        $acilma_tarihi = current_time('Y-m-d');
        $hafta = date('W', current_time('timestamp'));
        
        // Insert action
        $result = $wpdb->insert(
            $actions_table,
            array(
                'tanımlayan_id' => $current_user_id,
                'kategori_id' => $kategori_id,
                'sorumlu_ids' => $sorumlu_ids_string,
                'tespit_konusu' => $tespit_konusu,
                'aciklama' => $aciklama,
                'hedef_tarih' => $hedef_tarih,
                'performans_id' => $performans_id,
                'onem_derecesi' => $onem_derecesi,
                'acilma_tarihi' => $acilma_tarihi,
                'hafta' => $hafta,
                'ilerleme_durumu' => 0
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d')
        );
        
        if ($result !== false) {
            $action_id = $wpdb->insert_id;
            
            // Get action details for email notification
            $category = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}bkm_categories WHERE id = %d", 
                $kategori_id
            ));
            
            // Send email notification
            $notification_data = array(
                'id' => $action_id,
                'tanımlayan' => $current_user->display_name,
                'kategori' => $category,
                'aciklama' => $aciklama,
                'hedef_tarih' => $hedef_tarih,
                'sorumlu_emails' => array()
            );
            
            // Get responsible users' emails
            foreach ($sorumlu_ids as $user_id) {
                $user = get_user_by('ID', $user_id);
                if ($user) {
                    $notification_data['sorumlu_emails'][] = $user->user_email;
                }
            }
            
            $this->send_email_notification('action_created', $notification_data);
            
            // Generate HTML for the new action row
            $action_html = $this->generate_action_row_html($action_id);
            
            wp_send_json_success(array(
                'message' => 'Aksiyon başarıyla eklendi.',
                'action_id' => $action_id,
                'action_html' => $action_html,
                'action_details' => array(
                    'aciklama' => $aciklama,
                    'tespit_konusu' => $tespit_konusu,
                    'hedef_tarih' => $hedef_tarih
                )
            ));
        } else {
            wp_send_json_error(array('message' => 'Aksiyon eklenirken bir hata oluştu.'));
        }
    }
    
    /**
     * Generate HTML for new action row
     */
    private function generate_action_row_html($action_id) {
        global $wpdb;
        
        // Get the full action data
        $actions_table = $wpdb->prefix . 'bkm_actions';
        $categories_table = $wpdb->prefix . 'bkm_categories';
        $performance_table = $wpdb->prefix . 'bkm_performance';
        
        $action = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, 
                    c.name as kategori_name,
                    p.name as performans_name,
                    a.tanımlayan_id as tanimlayan_id
             FROM $actions_table a
             LEFT JOIN $categories_table c ON a.kategori_id = c.id
             LEFT JOIN $performance_table p ON a.performans_id = p.id
             WHERE a.id = %d",
            $action_id
        ));
        
        if (!$action) {
            return '';
        }
        
        // Get tanımlayan user data
        $tanımlayan_user = get_user_by('ID', $action->tanımlayan_id);
        if (!$tanımlayan_user) {
            $tanımlayan_user = (object)array('display_name' => 'Bilinmeyen');
        }
        
        // Get tasks count for this action
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        $tasks_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tasks_table WHERE action_id = %d",
            $action_id
        ));
        
        // Priority labels
        $priority_labels = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek');
        
        ob_start();
        ?>
        <tr class="new-action">
            <td><?php echo $action->id; ?></td>
            <td><?php echo esc_html($tanımlayan_user->display_name); ?></td>
            <td><?php echo esc_html($action->kategori_name); ?></td>
            <td class="bkm-action-desc">
                <?php echo esc_html(substr($action->aciklama, 0, 100)) . '...'; ?>
            </td>
            <td>
                <span class="bkm-priority priority-<?php echo $action->onem_derecesi; ?>">
                    <?php echo $priority_labels[$action->onem_derecesi]; ?>
                </span>
            </td>
            <td>
                <div class="bkm-progress">
                    <div class="bkm-progress-bar" style="width: <?php echo $action->ilerleme_durumu; ?>%"></div>
                    <span class="bkm-progress-text"><?php echo $action->ilerleme_durumu; ?>%</span>
                </div>
            </td>
            <td>
                <?php if ($action->kapanma_tarihi): ?>
                    <span class="bkm-status status-closed">Kapalı</span>
                <?php else: ?>
                    <span class="bkm-status status-open">Açık</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="bkm-action-buttons-cell">
                    <?php if (current_user_can('manage_options')): ?>
                        <button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="toggleActionDetails(<?php echo $action->id; ?>)">
                            📋 Detaylar
                        </button>
                    <?php endif; ?>
                    <button class="bkm-btn bkm-btn-small" onclick="toggleTasks(<?php echo $action->id; ?>)">
                        📝 Görevler (<?php echo $tasks_count; ?>)
                    </button>
                </div>
            </td>
        </tr>
        
        <!-- Action Details Row -->
        <?php if (current_user_can('manage_options')): ?>
        <tr id="details-<?php echo $action->id; ?>" class="bkm-action-details-row" style="display: none;">
            <td colspan="8">
                <div class="bkm-action-details-container">
                    <h4>📋 Aksiyon Detayları</h4>
                    
                    <div class="bkm-details-grid">
                        <div class="bkm-detail-section">
                            <h5>📊 Genel Bilgiler</h5>
                            <div class="bkm-detail-item">
                                <strong>Aksiyon ID:</strong> 
                                <span>#<?php echo $action->id; ?></span>
                            </div>
                            <div class="bkm-detail-item">
                                <strong>Tanımlayan:</strong> 
                                <span><?php echo esc_html($tanımlayan_user->display_name); ?></span>
                            </div>
                            <div class="bkm-detail-item">
                                <strong>Kategori:</strong> 
                                <span class="bkm-badge bkm-badge-category"><?php echo esc_html($action->kategori_name); ?></span>
                            </div>
                            <div class="bkm-detail-item">
                                <strong>Performans:</strong> 
                                <span class="bkm-badge bkm-badge-performance"><?php echo esc_html($action->performans_name); ?></span>
                            </div>
                            <div class="bkm-detail-item">
                                <strong>Önem Derecesi:</strong> 
                                <span class="bkm-priority priority-<?php echo $action->onem_derecesi; ?>">
                                    <?php echo $priority_labels[$action->onem_derecesi]; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="bkm-detail-section">
                            <h5>📅 Tarih Bilgileri</h5>
                            <div class="bkm-detail-item">
                                <strong>Hedef Tarih:</strong> 
                                <span class="bkm-date"><?php echo date('d.m.Y', strtotime($action->hedef_tarih)); ?></span>
                            </div>
                            <div class="bkm-detail-item">
                                <strong>Oluşturulma:</strong> 
                                <span class="bkm-date"><?php echo date('d.m.Y H:i', strtotime($action->acilma_tarihi)); ?></span>
                            </div>
                            <?php if ($action->kapanma_tarihi): ?>
                            <div class="bkm-detail-item">
                                <strong>Kapanma Tarihi:</strong> 
                                <span class="bkm-date"><?php echo date('d.m.Y H:i', strtotime($action->kapanma_tarihi)); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="bkm-detail-item">
                                <strong>İlerleme Durumu:</strong> 
                                <div class="bkm-progress">
                                    <div class="bkm-progress-bar" style="width: <?php echo $action->ilerleme_durumu; ?>%"></div>
                                    <span class="bkm-progress-text"><?php echo $action->ilerleme_durumu; ?>%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bkm-detail-section">
                            <h5>👥 Sorumlu Kişiler</h5>
                            <div class="bkm-detail-item">
                                <?php 
                                $sorumlu_ids = explode(',', $action->sorumlu_ids);
                                $sorumlu_names = array();
                                foreach ($sorumlu_ids as $sorumlu_id) {
                                    $user = get_user_by('ID', trim($sorumlu_id));
                                    if ($user) {
                                        $sorumlu_names[] = $user->display_name;
                                    }
                                }
                                ?>
                                <div class="bkm-responsible-users">
                                    <?php foreach ($sorumlu_names as $name): ?>
                                        <span class="bkm-badge bkm-badge-user"><?php echo esc_html($name); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bkm-detail-section bkm-detail-full">
                        <h5>🔍 Tespit Konusu</h5>
                        <div class="bkm-detail-content">
                            <?php echo nl2br(esc_html($action->tespit_konusu)); ?>
                        </div>
                    </div>
                    
                    <div class="bkm-detail-section bkm-detail-full">
                        <h5>📝 Açıklama</h5>
                        <div class="bkm-detail-content">
                            <?php echo nl2br(esc_html($action->aciklama)); ?>
                        </div>
                    </div>
                    
                    <div class="bkm-details-actions">
                        <button class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleActionDetails(<?php echo $action->id; ?>)">
                            ❌ Detayları Kapat
                        </button>
                    </div>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        
        <!-- Tasks Row (initially hidden) -->
        <tr id="tasks-<?php echo $action->id; ?>" class="bkm-tasks-row" style="display: none;">
            <td colspan="8">
                <div class="bkm-tasks-container">
                    <h4>Görevler</h4>
                    <p>Bu aksiyon için henüz görev bulunmamaktadır.</p>
                </div>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for adding tasks
     */
    public function ajax_add_task() {
        error_log('BKM: ajax_add_task çağrıldı');
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            error_log('BKM: Nonce doğrulaması başarısız');
            wp_send_json_error(array('message' => 'Güvenlik doğrulaması başarısız.'));
            return;
        }
        
        if (!is_user_logged_in()) {
            error_log('BKM: Kullanıcı giriş yapmamış');
            wp_send_json_error(array('message' => 'Giriş yapmalısınız.'));
            return;
        }
        
        // Check if user has permission to add tasks (edit_posts capability)
        if (!current_user_can('edit_posts')) {
            error_log('BKM: Kullanıcının görev ekleme yetkisi yok');
            wp_send_json_error(array('message' => 'Görev ekleme yetkiniz yok.'));
            return;
        }
        
        global $wpdb;
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        
        // Log all POST data
        error_log('BKM: POST verileri: ' . print_r($_POST, true));
        
        // Get and validate input
        $action_id = intval($_POST['action_id']);
        $content = sanitize_textarea_field($_POST['task_content']);
        $baslangic_tarihi = sanitize_text_field($_POST['baslangic_tarihi']);
        $sorumlu_id = intval($_POST['sorumlu_id']);
        $hedef_bitis_tarihi = sanitize_text_field($_POST['hedef_bitis_tarihi']);
        $ilerleme_durumu = intval($_POST['ilerleme_durumu']);
        
        error_log("BKM: Değerler - Action ID: $action_id, Content: $content, Sorumlu: $sorumlu_id");
        
        // Validate required fields
        if (empty($content) || $action_id <= 0 || $sorumlu_id <= 0 || 
            empty($baslangic_tarihi) || empty($hedef_bitis_tarihi)) {
            error_log('BKM: Zorunlu alanlar eksik');
            wp_send_json_error(array('message' => 'Lütfen tüm zorunlu alanları doldurun.'));
            return;
        }
        
        // Validate date format
        if (!$this->validate_date($baslangic_tarihi) || !$this->validate_date($hedef_bitis_tarihi)) {
            error_log('BKM: Geçersiz tarih formatı');
            wp_send_json_error(array('message' => 'Geçersiz tarih formatı.'));
            return;
        }
        
        // Check if action exists
        $actions_table = $wpdb->prefix . 'bkm_actions';
        $action_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $actions_table WHERE id = %d",
            $action_id
        ));
        
        if (!$action_exists) {
            error_log('BKM: Aksiyon bulunamadı');
            wp_send_json_error(array('message' => 'Seçilen aksiyon bulunamadı.'));
            return;
        }
        
        // Check if user exists
        $sorumlu_user = get_user_by('ID', $sorumlu_id);
        if (!$sorumlu_user) {
            error_log('BKM: Sorumlu kullanıcı bulunamadı');
            wp_send_json_error(array('message' => 'Seçilen sorumlu kullanıcı bulunamadı.'));
            return;
        }
        
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        
        // Insert task
        $result = $wpdb->insert(
            $tasks_table,
            array(
                'action_id' => $action_id,
                'content' => $content,
                'baslangic_tarihi' => $baslangic_tarihi,
                'sorumlu_id' => $sorumlu_id,
                'hedef_bitis_tarihi' => $hedef_bitis_tarihi,
                'ilerleme_durumu' => $ilerleme_durumu,
                'tamamlandi' => 0
            ),
            array('%d', '%s', '%s', '%d', '%s', '%d', '%d')
        );
        
        if ($result !== false) {
            $task_id = $wpdb->insert_id;
            error_log("BKM: Görev başarıyla eklendi, ID: $task_id");
            
            // Send email notification
            $notification_data = array(
                'content' => $content,
                'action_id' => $action_id,
                'task_id' => $task_id,
                'sorumlu' => $sorumlu_user->display_name,
                'sorumlu_emails' => array($sorumlu_user->user_email),
                'hedef_bitis_tarihi' => $hedef_bitis_tarihi
            );
            
            $this->send_email_notification('task_created', $notification_data);
            
            // Generate HTML for the new task row
            $task_html = $this->generate_task_row_html($task_id, $action_id);
            
            wp_send_json_success(array(
                'message' => 'Görev başarıyla eklendi.',
                'task_id' => $task_id,
                'action_id' => $action_id,
                'task_html' => $task_html
            ));
        } else {
            error_log('BKM: Görev ekleme hatası: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Görev eklenirken bir hata oluştu: ' . $wpdb->last_error));
        }
    }
    
    /**
     * Generate HTML for new task row
     */
    private function generate_task_row_html($task_id, $action_id) {
        global $wpdb;
        
        // Get the full task data
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, u.display_name as sorumlu_name
             FROM $tasks_table t
             LEFT JOIN {$wpdb->users} u ON t.sorumlu_id = u.ID
             WHERE t.id = %d",
            $task_id
        ));
        
        if (!$task) {
            return '';
        }
        
        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        
        ob_start();
        ?>
        <div class="bkm-task-item new-task-item">
            <div class="bkm-task-content">
                <p><strong><?php echo esc_html($task->content); ?></strong></p>
                <div class="bkm-task-meta">
                    <span>👤 Sorumlu: <?php echo esc_html($task->sorumlu_name); ?></span>
                    <span>📅 Başlangıç: <?php echo esc_html(date('d.m.Y', strtotime($task->baslangic_tarihi))); ?></span>
                    <span>🎯 Hedef: <?php echo esc_html(date('d.m.Y', strtotime($task->hedef_bitis_tarihi))); ?></span>
                    <span>📊 İlerleme: <?php echo esc_html($task->ilerleme_durumu); ?>%</span>
                </div>
                <div class="bkm-progress bkm-task-progress">
                    <div class="bkm-progress-bar" style="width: <?php echo $task->ilerleme_durumu; ?>%"></div>
                    <span class="bkm-progress-text"><?php echo $task->ilerleme_durumu; ?>%</span>
                </div>
            </div>
            
            <div class="bkm-task-actions">
                <?php if ($task->sorumlu_id == $current_user->ID && !$task->tamamlandi): ?>
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('bkm_frontend_action', 'bkm_frontend_nonce'); ?>
                        <input type="hidden" name="task_action" value="complete_task" />
                        <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                        <button type="submit" class="bkm-btn bkm-btn-success bkm-btn-small"
                                onclick="return confirm('Bu görevi tamamladınız mı?')">
                            ✅ Tamamla
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($task->sorumlu_id == $current_user->ID || $is_admin): ?>
                    <button class="bkm-btn bkm-btn-primary bkm-btn-small" onclick="toggleNoteForm(<?php echo $task->id; ?>)">
                        📝 Not Ekle
                    </button>
                <?php endif; ?>
                
                <button class="bkm-btn bkm-btn-info bkm-btn-small" onclick="toggleNotes(<?php echo $task->id; ?>)">
                    💬 Notlar
                </button>
            </div>
            
            <!-- Note Form (hidden by default) -->
            <?php if ($task->sorumlu_id == $current_user->ID || $is_admin): ?>
                <div id="note-form-<?php echo $task->id; ?>" class="bkm-note-form bkm-task-note-form" style="display: none;">
                    <h5>✍️ Yeni Not Ekle</h5>
                    <form class="bkm-task-note-form-element">
                        <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                        <textarea name="note_content" rows="4" placeholder="Bu görev ile ilgili notunuzu buraya yazın... (markdown formatı desteklenir)" required></textarea>
                        <div class="bkm-form-actions">
                            <button type="submit" class="bkm-btn bkm-btn-primary">
                                📤 Not Ekle
                            </button>
                            <button type="button" class="bkm-btn bkm-btn-secondary" onclick="toggleNoteForm(<?php echo $task->id; ?>)">
                                ❌ İptal
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Notes Section (hidden by default) -->
            <div id="notes-<?php echo $task->id; ?>" class="bkm-notes-section bkm-task-notes-section" style="display: none;">
                <h5>💬 Görev Notları</h5>
                <div class="bkm-notes-content">
                    <p style="text-align: center; color: #9e9e9e; font-style: italic; margin: 20px 0; padding: 30px; border: 2px dashed #e0e0e0; border-radius: 12px;">
                        📝 Bu görev için henüz not bulunmamaktadır.
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Validate date format (YYYY-MM-DD)
     */
    private function validate_date($date_string) {
        $date = DateTime::createFromFormat('Y-m-d', $date_string);
        return $date && $date->format('Y-m-d') === $date_string;
    }
    
    /**
     * AJAX handler for getting task notes
     */
    public function ajax_get_task_notes() {
        error_log('BKM: ajax_get_task_notes çağrıldı');
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            error_log('BKM: Nonce doğrulaması başarısız');
            wp_send_json_error(array('message' => 'Güvenlik doğrulaması başarısız.'));
            return;
        }
        
        if (!is_user_logged_in()) {
            error_log('BKM: Kullanıcı giriş yapmamış');
            wp_send_json_error(array('message' => 'Giriş yapmalısınız.'));
            return;
        }
        
        global $wpdb;
        $task_id = intval($_POST['task_id']);
        
        if ($task_id <= 0) {
            error_log('BKM: Geçersiz task ID');
            wp_send_json_error(array('message' => 'Geçersiz görev ID.'));
            return;
        }
        
        // Check if task exists
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        $task_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tasks_table WHERE id = %d",
            $task_id
        ));
        
        if (!$task_exists) {
            error_log('BKM: Görev bulunamadı');
            wp_send_json_error(array('message' => 'Görev bulunamadı.'));
            return;
        }
        
        // Get task notes
        $notes_table = $wpdb->prefix . 'bkm_task_notes';
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as author_name, 
             DATE_FORMAT(n.created_at, '%%d.%%m.%%Y %%H:%%i') as created_at
             FROM $notes_table n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE n.task_id = %d 
             ORDER BY n.created_at DESC",
            $task_id
        ));
        
        error_log("BKM: Task $task_id için " . count($notes) . " not bulundu");
        
        wp_send_json_success(array(
            'notes' => $notes,
            'count' => count($notes)
        ));
    }

    // ===== MEVCUT KODLAR =====
}

// Initialize plugin
BKM_Aksiyon_Takip::get_instance();