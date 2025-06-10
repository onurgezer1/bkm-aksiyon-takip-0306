<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if user is logged in
if (!is_user_logged_in()) {
    include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'frontend/login.php';
    return;
}

// Handle logout
if (isset($_GET['bkm_logout'])) {
    wp_logout();
    global $wp; // Global $wp nesnesini tanımla
    wp_safe_redirect(home_url(add_query_arg(array(), $wp->request)));
    exit;
}

// User is logged in, show dashboard
global $wpdb;
$current_user = wp_get_current_user();

// Check if user has permission to view
if (!current_user_can('read')) {
    echo '<div class="bkm-error">Bu sayfaya erişim yetkiniz bulunmamaktadır.</div>';
    return;
}

// Get data
$actions_table = $wpdb->prefix . 'bkm_actions';
$tasks_table = $wpdb->prefix . 'bkm_tasks';
$categories_table = $wpdb->prefix . 'bkm_categories';
$performance_table = $wpdb->prefix . 'bkm_performance';

// Handle task actions
if (isset($_POST['task_action']) && wp_verify_nonce($_POST['bkm_frontend_nonce'], 'bkm_frontend_action')) {
    if ($_POST['task_action'] === 'complete_task') {
        $task_id = intval($_POST['task_id']);
        
        // Check if user owns this task
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE id = %d AND sorumlu_id = %d",
            $task_id, $current_user->ID
        ));
        
        if ($task) {
            $wpdb->update(
                $tasks_table,
                array(
                    'tamamlandi' => 1,
                    'ilerleme_durumu' => 100,
                    'gercek_bitis_tarihi' => current_time('mysql')
                ),
                array('id' => $task_id),
                array('%d', '%d', '%s'),
                array('%d')
            );
            
            // Send email notification
            $plugin = BKM_Aksiyon_Takip::get_instance();
            $notification_data = array(
                'content' => $task->content,
                'sorumlu' => $current_user->display_name,
                'tamamlanma_tarihi' => current_time('mysql')
            );
            
            $plugin->send_email_notification('task_completed', $notification_data);
            
            // Redirect to prevent form resubmission
            global $wp;
            wp_safe_redirect(home_url(add_query_arg(array('success' => 'task_completed'), $wp->request)));
            exit;
        }
    }
}

// Handle add task (for admin/editor)
if (isset($_POST['add_task']) && wp_verify_nonce($_POST['bkm_frontend_nonce'], 'bkm_frontend_action') && current_user_can('edit_posts')) {
    $action_id = intval($_POST['action_id']);
    $content = sanitize_textarea_field($_POST['task_content']);
    $baslangic_tarihi = sanitize_text_field($_POST['baslangic_tarihi']);
    $sorumlu_id = intval($_POST['sorumlu_id']);
    $hedef_bitis_tarihi = sanitize_text_field($_POST['hedef_bitis_tarihi']);
    $ilerleme_durumu = intval($_POST['ilerleme_durumu']);
    
    if (!empty($content) && $action_id > 0 && $sorumlu_id > 0) {
        $result = $wpdb->insert(
            $tasks_table,
            array(
                'action_id' => $action_id,
                'content' => $content,
                'baslangic_tarihi' => $baslangic_tarihi,
                'sorumlu_id' => $sorumlu_id,
                'hedef_bitis_tarihi' => $hedef_bitis_tarihi,
                'ilerleme_durumu' => $ilerleme_durumu
            ),
            array('%d', '%s', '%s', '%d', '%s', '%d')
        );
        
        if ($result !== false) {
            // Send email notification
            $plugin = BKM_Aksiyon_Takip::get_instance();
            $sorumlu_user = get_user_by('ID', $sorumlu_id);
            
            $notification_data = array(
                'action_id' => $action_id,
                'content' => $content,
                'baslangic_tarihi' => $baslangic_tarihi,
                'hedef_bitis_tarihi' => $hedef_bitis_tarihi,
                'sorumlu_emails' => $sorumlu_user ? array($sorumlu_user->user_email) : array()
            );
            
            $plugin->send_email_notification('task_created', $notification_data);
            
            // Redirect to prevent form resubmission
            global $wp;
            wp_safe_redirect(home_url(add_query_arg(array('success' => 'task_added'), $wp->request)));
            exit;
        } else {
            echo '<div class="bkm-error">Görev eklenirken bir hata oluştu.</div>';
        }
    } else {
        echo '<div class="bkm-error">Lütfen tüm zorunlu alanları doldurun.</div>';
    }
}

// Display success messages
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'task_completed') {
        echo '<div class="bkm-success">Görev başarıyla tamamlandı!</div>';
    } elseif ($_GET['success'] === 'task_added') {
        echo '<div class="bkm-success">Görev başarıyla eklendi!</div>';
    }
}

// Get actions with related data
$actions = $wpdb->get_results(
    "SELECT a.*, 
            u.display_name as tanımlayan_name,
            c.name as kategori_name,
            p.name as performans_name
     FROM $actions_table a
     LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID
     LEFT JOIN $categories_table c ON a.kategori_id = c.id
     LEFT JOIN $performance_table p ON a.performans_id = p.id
     ORDER BY a.created_at DESC"
);

// Get users for task assignment
$users = get_users(array('role__in' => array('administrator', 'editor', 'author', 'contributor')));
?>

<div class="bkm-frontend-container">
    <div class="bkm-dashboard">
        <!-- Header -->
        <div class="bkm-dashboard-header">
            <h1>Aksiyon Takip Sistemi</h1>
            <div class="bkm-user-info">
                Hoş geldiniz, <strong><?php echo esc_html($current_user->display_name); ?></strong>
                <a href="?bkm_logout=1" class="bkm-logout">Çıkış</a>
            </div>
        </div>
        
        <!-- Actions Table -->
        <div class="bkm-actions-section">
            <div class="bkm-section-header">
                <h2>Aksiyonlar</h2>
                <?php if (current_user_can('edit_posts')): ?>
                    <button class="bkm-btn bkm-btn-primary" onclick="toggleTaskForm()">
                        Görev Ekle
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Add Task Form (hidden by default) -->
            <?php if (current_user_can('edit_posts')): ?>
                <div id="bkm-task-form" class="bkm-task-form" style="display: none;">
                    <h3>Yeni Görev Ekle</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('bkm_frontend_action', 'bkm_frontend_nonce'); ?>
                        <input type="hidden" name="add_task" value="1" />
                        
                        <div class="bkm-form-grid">
                            <div class="bkm-field">
                                <label for="action_id">Aksiyon:</label>
                                <select name="action_id" id="action_id" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo $action->id; ?>">
                                            #<?php echo $action->id; ?> - <?php echo esc_html(substr($action->aciklama, 0, 50)) . '...'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="task_content">Görev İçeriği:</label>
                                <textarea name="task_content" id="task_content" rows="3" required></textarea>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="baslangic_tarihi">Başlangıç Tarihi:</label>
                                <input type="date" name="baslangic_tarihi" id="baslangic_tarihi" required />
                            </div>
                            
                            <div class="bkm-field">
                                <label for="sorumlu_id">Sorumlu:</label>
                                <select name="sorumlu_id" id="sorumlu_id" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="hedef_bitis_tarihi">Hedef Bitiş Tarihi:</label>
                                <input type="date" name="hedef_bitis_tarihi" id="hedef_bitis_tarihi" required />
                            </div>
                            
                            <div class="bkm-field">
                                <label for="ilerleme_durumu">İlerleme (%):</label>
                                <input type="number" name="ilerleme_durumu" id="ilerleme_durumu" min="0" max="100" value="0" />
                            </div>
                        </div>
                        
                        <div class="bkm-form-actions">
                            <button type="submit" class="bkm-btn bkm-btn-primary">Görev Ekle</button>
                            <button type="button" class="bkm-btn bkm-btn-secondary" onclick="toggleTaskForm()">İptal</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Actions Table -->
            <div class="bkm-actions-table">
                <table class="bkm-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanımlayan</th>
                            <th>Kategori</th>
                            <th>Açıklama</th>
                            <th>Önem</th>
                            <th>İlerleme</th>
                            <th>Durum</th>
                            <th>Görevler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($actions): ?>
                            <?php foreach ($actions as $action): ?>
                                <?php
                                // Get tasks for this action
                                $action_tasks = $wpdb->get_results($wpdb->prepare(
                                    "SELECT t.*, u.display_name as sorumlu_name 
                                     FROM $tasks_table t 
                                     LEFT JOIN {$wpdb->users} u ON t.sorumlu_id = u.ID 
                                     WHERE t.action_id = %d 
                                     ORDER BY t.created_at DESC",
                                    $action->id
                                ));
                                ?>
                                <tr>
                                    <td><?php echo $action->id; ?></td>
                                    <td><?php echo esc_html($action->tanımlayan_name); ?></td>
                                    <td><?php echo esc_html($action->kategori_name); ?></td>
                                    <td class="bkm-action-desc">
                                        <?php echo esc_html(substr($action->aciklama, 0, 100)) . '...'; ?>
                                    </td>
                                    <td>
                                        <span class="bkm-priority priority-<?php echo $action->onem_derecesi; ?>">
                                            <?php 
                                            $priority_labels = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek');
                                            echo $priority_labels[$action->onem_derecesi];
                                            ?>
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
                                        <button class="bkm-btn bkm-btn-small" onclick="toggleTasks(<?php echo $action->id; ?>)">
                                            Görevleri Göster (<?php echo count($action_tasks); ?>)
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Tasks Row -->
                                <tr id="tasks-<?php echo $action->id; ?>" class="bkm-tasks-row" style="display: none;">
                                    <td colspan="8">
                                        <div class="bkm-tasks-container">
                                            <h4>Görevler</h4>
                                            <?php if ($action_tasks): ?>
                                                <div class="bkm-tasks-list">
                                                    <?php foreach ($action_tasks as $task): ?>
                                                        <div class="bkm-task-item <?php echo $task->tamamlandi ? 'completed' : ''; ?>">
                                                            <div class="bkm-task-content">
                                                                <p><strong><?php echo esc_html($task->content); ?></strong></p>
                                                                <div class="bkm-task-meta">
                                                                    <span>Sorumlu: <?php echo esc_html($task->sorumlu_name); ?></span>
                                                                    <span>Başlangıç: <?php echo date('d.m.Y', strtotime($task->baslangic_tarihi)); ?></span>
                                                                    <span>Hedef: <?php echo date('d.m.Y', strtotime($task->hedef_bitis_tarihi)); ?></span>
                                                                    <?php if ($task->gercek_bitis_tarihi): ?>
                                                                        <span>Bitiş: <?php echo date('d.m.Y H:i', strtotime($task->gercek_bitis_tarihi)); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="bkm-task-progress">
                                                                    <div class="bkm-progress">
                                                                        <div class="bkm-progress-bar" style="width: <?php echo $task->ilerleme_durumu; ?>%"></div>
                                                                        <span class="bkm-progress-text"><?php echo $task->ilerleme_durumu; ?>%</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if ($task->sorumlu_id == $current_user->ID && !$task->tamamlandi): ?>
                                                                <div class="bkm-task-actions">
                                                                    <form method="post" style="display: inline;">
                                                                        <?php wp_nonce_field('bkm_frontend_action', 'bkm_frontend_nonce'); ?>
                                                                        <input type="hidden" name="task_action" value="complete_task" />
                                                                        <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                                                                        <button type="submit" class="bkm-btn bkm-btn-success bkm-btn-small"
                                                                                onclick="return confirm('Bu görevi tamamladınız mı?')">
                                                                            Tamamla
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p>Bu aksiyon için henüz görev bulunmamaktadır.</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">Henüz aksiyon bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTaskForm() {
    var form = document.getElementById('bkm-task-form');
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

function toggleTasks(actionId) {
    var tasksRow = document.getElementById('tasks-' + actionId);
    if (tasksRow.style.display === 'none' || tasksRow.style.display === '') {
        tasksRow.style.display = 'table-row';
    } else {
        tasksRow.style.display = 'none';
    }
}
</script>