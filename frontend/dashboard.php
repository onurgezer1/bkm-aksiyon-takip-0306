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
$notes_table = $wpdb->prefix . 'bkm_task_notes';
$categories_table = $wpdb->prefix . 'bkm_categories';
$performance_table = $wpdb->prefix . 'bkm_performance';

// Determine SQL query based on user role
$user_roles = $current_user->roles;
$is_admin = in_array('administrator', $user_roles);
$current_user_id = $current_user->ID;

error_log("Is admin (global): " . ($is_admin ? 'true' : 'false') . ", User roles: " . implode(', ', $user_roles) . ", User ID: " . $current_user_id);

if ($is_admin) {
    // Admins see all actions
    $actions_query = "SELECT a.*, 
                            u.display_name as tanımlayan_name,
                            c.name as kategori_name,
                            p.name as performans_name
                     FROM $actions_table a
                     LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID
                     LEFT JOIN $categories_table c ON a.kategori_id = c.id
                     LEFT JOIN $performance_table p ON a.performans_id = p.id
                     ORDER BY a.created_at DESC";
} else {
    // Non-admins see only their assigned actions
    $actions_query = $wpdb->prepare(
        "SELECT a.*, 
                u.display_name as tanımlayan_name,
                c.name as kategori_name,
                p.name as performans_name
         FROM $actions_table a
         LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID
         LEFT JOIN $categories_table c ON a.kategori_id = c.id
         LEFT JOIN $performance_table p ON a.performans_id = p.id
         WHERE a.sorumlu_ids LIKE %s
         ORDER BY a.created_at DESC",
        '%' . $wpdb->esc_like($current_user_id) . '%'
    );
}

$actions = $wpdb->get_results($actions_query);

// Define display_notes function once at the top
function display_notes($notes, $parent_id = null, $level = 0, $is_admin_param = false, $task = null) {
    global $wpdb, $current_user_id;
    error_log("Displaying notes, is_admin_param: " . ($is_admin_param ? 'true' : 'false') . ", parent_id: " . $parent_id . ", note count: " . count($notes) . ", task_id: " . ($task ? $task->id : 'null'));
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
            display_notes($notes, $note->id, $level + 1, $is_admin_param, $task); // Pass task recursively
        }
    }
}

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

// Handle add task - DISABLED: Now using AJAX
/*
// OLD POST-based task adding - replaced with AJAX
if (isset($_POST['add_task']) && wp_verify_nonce($_POST['bkm_frontend_nonce'], 'bkm_frontend_action') && current_user_can('edit_posts')) {
    // ... old code moved to ajax_add_task() in bkm-aksiyon-takip.php
}
*/

// Handle add note - DISABLED: Now using AJAX
/*
if (isset($_POST['note_action']) && wp_verify_nonce($_POST['bkm_frontend_nonce'], 'bkm_frontend_action')) {
    if ($_POST['note_action'] === 'add_note' || $_POST['note_action'] === 'reply_note') {
        $task_id = intval($_POST['task_id']);
        $content = sanitize_textarea_field($_POST['note_content']);
        $parent_note_id = isset($_POST['parent_note_id']) ? intval($_POST['parent_note_id']) : null;
        
        error_log("Note action: " . $_POST['note_action'] . ", task_id: $task_id, parent_note_id: $parent_note_id, content: $content");

        // Check if user is authorized to add note
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE id = %d",
            $task_id
        ));
        
        if ($task && (($task->sorumlu_id == $current_user_id && $_POST['note_action'] === 'add_note') || $is_admin)) {
            if (!empty($content)) {
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
                
                if ($result !== false) {
                    // Send email notification
                    $plugin = BKM_Aksiyon_Takip::get_instance();
                    $notification_data = array(
                        'content' => $content,
                        'action_id' => $task->action_id,
                        'task_id' => $task_id,
                        'sorumlu' => $current_user->display_name,
                        'sorumlu_emails' => array(get_user_by('ID', $task->sorumlu_id)->user_email)
                    );
                    
                    $plugin->send_email_notification($_POST['note_action'] === 'add_note' ? 'note_added' : 'note_replied', $notification_data);
                    
                    // Redirect to prevent form resubmission
                    global $wp;
                    wp_safe_redirect(home_url(add_query_arg(array('success' => 'note_added'), $wp->request)));
                    exit;
                } else {
                    echo '<div class="bkm-error">Not eklenirken bir hata oluştu.</div>';
                }
            } else {
                echo '<div class="bkm-error">Not içeriği boş olamaz.</div>';
            }
        } else {
            echo '<div class="bkm-error">Bu göreve not ekleme veya cevap yazma yetkiniz yok.</div>';
        }
    }
*/

// Display success messages
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'task_completed') {
        echo '<div class="bkm-success">Görev başarıyla tamamlandı!</div>';
    } elseif ($_GET['success'] === 'task_added') {
        echo '<div class="bkm-success">Görev başarıyla eklendi!</div>';
    } elseif ($_GET['success'] === 'note_added') {
        echo '<div class="bkm-success">Not başarıyla eklendi!</div>';
    }
}

// Get users for task assignment
$users = get_users(array('role__in' => array('administrator', 'editor', 'author', 'contributor')));

// Get categories and performance data for action form
$categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name ASC");
$performances = $wpdb->get_results("SELECT * FROM $performance_table ORDER BY name ASC");
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
                <div class="bkm-action-buttons">
                    <?php if (current_user_can('manage_options')): ?>
                        <button class="bkm-btn bkm-btn-success" onclick="toggleActionForm()">
                            ➕ Yeni Aksiyon
                        </button>
                    <?php endif; ?>
                    <?php if (current_user_can('edit_posts')): ?>
                        <button class="bkm-btn bkm-btn-primary" onclick="toggleTaskForm()">
                            📋 Görev Ekle
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Add Action Form (hidden by default) -->
            <?php if (current_user_can('manage_options')): ?>
                <div id="bkm-action-form" class="bkm-task-form" style="display: none;">
                    <h3>Yeni Aksiyon Ekle</h3>
                    
                    <form id="bkm-action-form-element">
                        <!-- İlk satır: Kategori -->
                        <div class="bkm-form-row">
                            <div class="bkm-field">
                                <label for="action_kategori_id">Kategori <span class="required">*</span>:</label>
                                <select name="kategori_id" id="action_kategori_id" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category->id; ?>"><?php echo esc_html($category->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- İkinci satır: Performans, Önem Derecesi, Hedef Tarih -->
                        <div class="bkm-form-grid-3">
                            <div class="bkm-field">
                                <label for="action_performans_id">Performans <span class="required">*</span>:</label>
                                <select name="performans_id" id="action_performans_id" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($performances as $performance): ?>
                                        <option value="<?php echo $performance->id; ?>"><?php echo esc_html($performance->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="action_onem_derecesi">Önem Derecesi <span class="required">*</span>:</label>
                                <select name="onem_derecesi" id="action_onem_derecesi" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="1">Düşük</option>
                                    <option value="2">Orta</option>
                                    <option value="3">Yüksek</option>
                                </select>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="action_hedef_tarih">Hedef Tarih <span class="required">*</span>:</label>
                                <input type="date" name="hedef_tarih" id="action_hedef_tarih" required />
                            </div>
                        </div>
                        
                        <!-- Üçüncü satır: Sorumlu Kişiler ve Tespit Konusu -->
                        <div class="bkm-form-grid-2">
                            <div class="bkm-field">
                                <label for="action_sorumlu_ids">Sorumlu Kişiler <span class="required">*</span>:</label>
                                <select name="sorumlu_ids[]" id="action_sorumlu_ids" multiple required size="5">
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Ctrl tuşu ile birden fazla seçim yapabilirsiniz</small>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="action_tespit_konusu">Tespit Konusu <span class="required">*</span>:</label>
                                <textarea name="tespit_konusu" id="action_tespit_konusu" rows="5" required placeholder="Tespit edilen konuyu kısaca açıklayın..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Dördüncü satır: Açıklama (tam genişlik) -->
                        <div class="bkm-form-row">
                            <div class="bkm-field">
                                <label for="action_aciklama">Açıklama <span class="required">*</span>:</label>
                                <textarea name="aciklama" id="action_aciklama" rows="4" required placeholder="Aksiyonun detaylı açıklamasını yazın..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Form Actions (sağ alt) -->
                        <div class="bkm-form-actions">
                            <button type="submit" class="bkm-btn bkm-btn-success">
                                ✅ Aksiyon Ekle
                            </button>
                            <button type="button" class="bkm-btn bkm-btn-secondary" onclick="toggleActionForm()">
                                ❌ İptal
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Add Task Form (hidden by default) -->
            <?php if (current_user_can('edit_posts')): ?>
                <div id="bkm-task-form" class="bkm-task-form" style="display: none;">
                    <h3>Yeni Görev Ekle</h3>
                    <form id="bkm-task-form-element">
                        <div class="bkm-form-grid">
                            <div class="bkm-field">
                                <label for="action_id">Aksiyon <span class="required">*</span>:</label>
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
                                <label for="task_content">Görev İçeriği <span class="required">*</span>:</label>
                                <textarea name="task_content" id="task_content" rows="3" required></textarea>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="baslangic_tarihi">Başlangıç Tarihi <span class="required">*</span>:</label>
                                <input type="date" name="baslangic_tarihi" id="baslangic_tarihi" required />
                            </div>
                            
                            <div class="bkm-field">
                                <label for="sorumlu_id">Sorumlu <span class="required">*</span>:</label>
                                <select name="sorumlu_id" id="sorumlu_id" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="hedef_bitis_tarihi">Hedef Bitiş Tarihi <span class="required">*</span>:</label>
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
                                        <div class="bkm-action-buttons-cell">
                                            <?php if (current_user_can('manage_options')): ?>
                                                <button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="toggleActionDetails(<?php echo $action->id; ?>)">
                                                    📋 Detaylar
                                                </button>
                                            <?php endif; ?>
                                            <button class="bkm-btn bkm-btn-small" onclick="toggleTasks(<?php echo $action->id; ?>)">
                                                📝 Görevler (<?php echo count($action_tasks); ?>)
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
                                                        <span><?php echo esc_html($action->tanımlayan_name); ?></span>
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
                                                            <?php 
                                                            $priority_labels = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek');
                                                            echo $priority_labels[$action->onem_derecesi];
                                                            ?>
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
                                                        <span class="bkm-date"><?php echo date('d.m.Y H:i', strtotime($action->created_at)); ?></span>
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
                                
                                <!-- Tasks Row -->
                                <tr id="tasks-<?php echo $action->id; ?>" class="bkm-tasks-row" style="display: none;">
                                    <td colspan="8">
                                        <div class="bkm-tasks-container">
                                            <h4>Görevler</h4>
                                            <?php if ($action_tasks): ?>
                                                <div class="bkm-tasks-list">
                                                    <?php foreach ($action_tasks as $task): ?>
                                                        <?php
                                                        // Get notes for this task
                                                        $task_notes = $wpdb->get_results($wpdb->prepare(
                                                            "SELECT n.*, u.display_name as user_name 
                                                             FROM $notes_table n 
                                                             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID 
                                                             WHERE n.task_id = %d 
                                                             ORDER BY n.created_at ASC",
                                                            $task->id
                                                        ));
                                                        $has_notes = !empty($task_notes);
                                                        ?>
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
                                                            
                                                            <div class="bkm-task-actions">
                                                                <?php if ($task->sorumlu_id == $current_user->ID && !$task->tamamlandi): ?>
                                                                    <form method="post" style="display: inline;">
                                                                        <?php wp_nonce_field('bkm_frontend_action', 'bkm_frontend_nonce'); ?>
                                                                        <input type="hidden" name="task_action" value="complete_task" />
                                                                        <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                                                                        <button type="submit" class="bkm-btn bkm-btn-success bkm-btn-small"
                                                                                onclick="return confirm('Bu görevi tamamladınız mı?')">
                                                                            Tamamla
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($task->sorumlu_id == $current_user->ID || $is_admin): ?>
                                                                    <button class="bkm-btn bkm-btn-small" onclick="toggleNoteForm(<?php echo $task->id; ?>)">
                                                                        Not Ekle
                                                                    </button>
                                                                    <?php if ($has_notes): ?>
                                                                        <button class="bkm-btn bkm-btn-small" onclick="toggleNotes(<?php echo $task->id; ?>)">
                                                                            Notları Göster (<?php echo count($task_notes); ?>)
                                                                        </button>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Note Form (hidden by default) -->
                                                        <?php if ($task->sorumlu_id == $current_user->ID || $is_admin): ?>
                                                            <div id="note-form-<?php echo $task->id; ?>" class="bkm-note-form" style="display: none;">
                                                                <form>
                                                                    <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                                                                    <textarea name="note_content" rows="3" placeholder="Notunuzu buraya yazın..." required></textarea>
                                                                    <div class="bkm-form-actions">
                                                                        <button type="submit" class="bkm-btn bkm-btn-primary bkm-btn-small">
                                                                            Not Ekle
                                                                        </button>
                                                                        <button type="button" class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleNoteForm(<?php echo $task->id; ?>)">
                                                                            İptal
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                            
                                                            <!-- Notes Section (hidden by default) -->
                                                            <div id="notes-<?php echo $task->id; ?>" class="bkm-notes-section" style="display: none;">
                                                                <?php if ($task_notes): ?>
                                                                    <?php display_notes($task_notes, null, 0, $is_admin, $task); ?>
                                                                <?php else: ?>
                                                                    <p>Bu görev için henüz not bulunmamaktadır.</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
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
function toggleTasks(actionId) {
    var tasksRow = document.getElementById('tasks-' + actionId);
    if (tasksRow.style.display === 'none' || tasksRow.style.display === '') {
        tasksRow.style.display = 'table-row';
    } else {
        tasksRow.style.display = 'none';
    }
}

// Görev notları fonksiyonları frontend.js'te tanımlandı - çakışmayı önlemek için buradakiler kaldırıldı

function toggleReplyForm(taskId, noteId) {
    var replyForm = document.getElementById('reply-form-' + taskId + '-' + noteId);
    if (replyForm.style.display === 'none' || replyForm.style.display === '') {
        replyForm.style.display = 'block';
    } else {
        replyForm.style.display = 'none';
    }
}
</script>