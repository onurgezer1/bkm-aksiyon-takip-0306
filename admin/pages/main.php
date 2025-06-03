<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('edit_posts')) {
    wp_die(__('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'bkm-aksiyon-takip'));
}

global $wpdb;

// Get statistics
$actions_table = $wpdb->prefix . 'bkm_actions';
$tasks_table = $wpdb->prefix . 'bkm_tasks';

$total_actions = $wpdb->get_var("SELECT COUNT(*) FROM $actions_table");
$open_actions = $wpdb->get_var("SELECT COUNT(*) FROM $actions_table WHERE kapanma_tarihi IS NULL");
$closed_actions = $wpdb->get_var("SELECT COUNT(*) FROM $actions_table WHERE kapanma_tarihi IS NOT NULL");
$total_tasks = $wpdb->get_var("SELECT COUNT(*) FROM $tasks_table");
$completed_tasks = $wpdb->get_var("SELECT COUNT(*) FROM $tasks_table WHERE tamamlandi = 1");

// Get recent actions
$recent_actions = $wpdb->get_results(
    "SELECT a.*, u.display_name as tanımlayan_name, c.name as kategori_name 
     FROM $actions_table a 
     LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID 
     LEFT JOIN {$wpdb->prefix}bkm_categories c ON a.kategori_id = c.id 
     ORDER BY a.created_at DESC 
     LIMIT 10"
);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="bkm-dashboard">
        <div class="bkm-stats-grid">
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $total_actions; ?></div>
                <div class="bkm-stat-label">Toplam Aksiyon</div>
            </div>
            
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $open_actions; ?></div>
                <div class="bkm-stat-label">Açık Aksiyon</div>
            </div>
            
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $closed_actions; ?></div>
                <div class="bkm-stat-label">Kapalı Aksiyon</div>
            </div>
            
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $total_tasks; ?></div>
                <div class="bkm-stat-label">Toplam Görev</div>
            </div>
            
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $completed_tasks; ?></div>
                <div class="bkm-stat-label">Tamamlanan Görev</div>
            </div>
        </div>
        
        <div class="bkm-recent-actions">
            <h2>Son Aksiyonlar</h2>
            <div class="bkm-actions-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanımlayan</th>
                            <th>Kategori</th>
                            <th>Önem</th>
                            <th>Açılma Tarihi</th>
                            <th>İlerleme</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_actions): ?>
                            <?php foreach ($recent_actions as $action): ?>
                                <tr>
                                    <td><?php echo $action->id; ?></td>
                                    <td><?php echo esc_html($action->tanımlayan_name); ?></td>
                                    <td><?php echo esc_html($action->kategori_name); ?></td>
                                    <td>
                                        <span class="bkm-priority priority-<?php echo $action->onem_derecesi; ?>">
                                            <?php 
                                            $priority_labels = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek');
                                            echo $priority_labels[$action->onem_derecesi];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($action->acilma_tarihi)); ?></td>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">Henüz aksiyon bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="bkm-quick-actions">
            <h2>Hızlı İşlemler</h2>
            <div class="bkm-quick-actions-grid">
                <a href="<?php echo admin_url('admin.php?page=bkm-aksiyon-ekle'); ?>" class="bkm-quick-action">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Yeni Aksiyon Ekle
                </a>
                <a href="<?php echo admin_url('admin.php?page=bkm-kategoriler'); ?>" class="bkm-quick-action">
                    <span class="dashicons dashicons-category"></span>
                    Kategorileri Yönet
                </a>
                <a href="<?php echo admin_url('admin.php?page=bkm-performanslar'); ?>" class="bkm-quick-action">
                    <span class="dashicons dashicons-chart-bar"></span>
                    Performansları Yönet
                </a>
                <a href="<?php echo admin_url('admin.php?page=bkm-raporlar'); ?>" class="bkm-quick-action">
                    <span class="dashicons dashicons-analytics"></span>
                    Raporları Görüntüle
                </a>
            </div>
        </div>
    </div>
</div>