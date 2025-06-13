/**
 * BKM Aksiyon Takip - Frontend JavaScript
 */

jQuery(document).ready(function($) {
    // Debug information
    console.log('🔧 BKM Frontend JS yüklendi');
    console.log('📊 jQuery versiyonu:', $.fn.jquery);
    console.log('🌍 bkmFrontend objesi:', typeof bkmFrontend !== 'undefined' ? bkmFrontend : 'UNDEFINED');
    
    // Test AJAX connectivity on page load
    if (typeof bkmFrontend !== 'undefined') {
        console.log('✅ WordPress AJAX sistemi aktif');
        console.log('🔗 AJAX URL:', bkmFrontend.ajax_url);
        console.log('🔐 Nonce token mevcut:', bkmFrontend.nonce ? 'YES' : 'NO');
    } else {
        console.error('❌ KRITIK HATA: bkmFrontend objesi yüklenemedi!');
        console.error('💡 ÇÖZÜM: WordPress admin paneline giriş yapın veya sayfayı yenileyin');
    }
    
    // Check if task form exists
    if ($('#bkm-task-form-element').length > 0) {
        console.log('✅ Görev ekleme formu bulundu');
    } else {
        console.log('⚠️ Görev ekleme formu bulunamadı - sadece yetkili kullanıcılar görebilir');
    }
    
    // ===== AJAX NOT İŞLEVLERİ =====
    
    // Ana not ekleme formu AJAX (görev notları dahil)
    $(document).on('submit', '.bkm-note-form form:not(.bkm-reply-form), .bkm-task-note-form-element', function(e) {
        e.preventDefault();
        console.log('🔧 Not ekleme formu submit edildi');
        
        var form = $(this);
        var taskId = form.find('input[name="task_id"]').val();
        var content = form.find('textarea[name="note_content"]').val().trim();
        
        console.log('📝 Task ID:', taskId, 'Content:', content);
        
        if (!content) {
            showNotification('Not içeriği boş olamaz.', 'error');
            return;
        }
        
        // Check if bkmFrontend is available
        if (typeof bkmFrontend === 'undefined') {
            console.error('❌ bkmFrontend objesi tanımlanmamış!');
            showNotification('WordPress AJAX sistemi yüklenemedi.', 'error');
            return;
        }
        
        // Disable form during submission
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Gönderiliyor...');
        
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'bkm_add_note',
                task_id: taskId,
                content: content,
                nonce: bkmFrontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Clear form
                    form[0].reset();
                    
                    // Hide note form
                    toggleNoteForm(taskId);
                    
                    // Reload notes to show the new note with proper hierarchy
                    loadTaskNotes(taskId, function() {
                        // Ensure notes section is visible
                        var notesSection = $('#notes-' + taskId);
                        if (notesSection.is(':hidden')) {
                            notesSection.slideDown(300);
                        }
                        
                        // Highlight the new note (last main note)
                        var newNote = notesSection.find('.bkm-main-note').last();
                        if (newNote.length > 0) {
                            newNote.addClass('new-note-highlight');
                            
                            // Smooth scroll to the new note
                            setTimeout(function() {
                                $('html, body').animate({
                                    scrollTop: newNote.offset().top - 100
                                }, 500);
                            }, 300);
                            
                            // Remove highlight after animation
                            setTimeout(function() {
                                newNote.removeClass('new-note-highlight');
                            }, 3000);
                        }
                    });
                    
                    // Update notes button count or create the button
                    var notesButton = $('button[onclick="toggleNotes(' + taskId + ')"]');
                    if (notesButton.length > 0) {
                        var currentCount = parseInt(notesButton.text().match(/\d+/)[0] || 0);
                        var newCount = currentCount + 1;
                        notesButton.text('💬 Notları Göster (' + newCount + ')');
                    } else {
                        // Add notes button if it doesn't exist
                        var taskActions = form.closest('.bkm-task-item').find('.bkm-task-actions');
                        if (taskActions.length === 0) {
                            // If no task actions div, look for it in the task container
                            taskActions = form.closest('.bkm-task-item').find('.bkm-task-actions');
                        }
                        if (taskActions.length === 0) {
                            // Create task actions div if it doesn't exist
                            var taskItem = form.closest('.bkm-task-item');
                            taskActions = $('<div class="bkm-task-actions"></div>');
                            taskItem.append(taskActions);
                        }
                        taskActions.append('<button class="bkm-btn bkm-btn-small" onclick="toggleNotes(' + taskId + ')">💬 Notları Göster (1)</button>');
                    }
                    
                    // Hide note form
                    toggleNoteForm(taskId);
                    
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Bir hata oluştu: ' + error, 'error');
            },
            complete: function() {
                // Re-enable form
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Not Ekle');
            }
        });
    });
    
    // Cevap formu AJAX
    $(document).on('submit', '.bkm-reply-form', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var taskId = form.data('task-id');
        var parentId = form.data('parent-id');
        var content = form.find('textarea[name="note_content"]').val().trim();
        
        if (!content) {
            showNotification('Cevap içeriği boş olamaz.', 'error');
            return;
        }
        
        // Disable form during submission
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Gönderiliyor...');
        
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'bkm_reply_note',
                task_id: taskId,
                parent_note_id: parentId,
                content: content,
                nonce: bkmFrontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Clear form and hide it
                    form[0].reset();
                    toggleReplyForm(taskId, parentId);
                    
                    // Reload notes to show the new reply with proper hierarchy
                    loadTaskNotes(taskId, function() {
                        // Ensure notes section is visible
                        var notesSection = $('#notes-' + taskId);
                        if (notesSection.is(':hidden')) {
                            notesSection.slideDown(300);
                        }
                        
                        // Find and highlight the new reply
                        var parentMainNote = notesSection.find('.bkm-main-note[data-note-id="' + parentId + '"]');
                        if (parentMainNote.length > 0) {
                            // Find the last reply to this parent
                            var newReply = parentMainNote.nextAll('.bkm-reply-note[data-parent-id="' + parentId + '"]').last();
                            if (newReply.length > 0) {
                                newReply.addClass('new-note-highlight');
                                
                                // Smooth scroll to the new reply
                                setTimeout(function() {
                                    $('html, body').animate({
                                        scrollTop: newReply.offset().top - 100
                                    }, 500);
                                }, 300);
                                
                                // Remove highlight after animation
                                setTimeout(function() {
                                    newReply.removeClass('new-note-highlight');
                                }, 3000);
                            }
                        }
                        
                        // Update notes count
                        var notesButton = $('button[onclick="toggleNotes(' + taskId + ')"]');
                        if (notesButton.length > 0) {
                            var currentCount = parseInt(notesButton.text().match(/\d+/)[0] || 0);
                            var newCount = currentCount + 1;
                            notesButton.text('💬 Notları Göster (' + newCount + ')');
                        }
                    });
                    
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Bir hata oluştu: ' + error, 'error');
            },
            complete: function() {
                // Re-enable form
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Cevap Gönder');
            }
        });
    });
    
    // ===== AKSIYON EKLEME İŞLEVLERİ =====
    
    // Aksiyon ekleme formu AJAX
    $(document).on('submit', '#bkm-action-form-element', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var formData = form.serialize();
        
        // Validate required fields
        var isValid = true;
        form.find('[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            showNotification('Lütfen tüm zorunlu alanları doldurun.', 'error');
            return;
        }
        
        // Disable form during submission
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Ekleniyor...');
        
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            data: formData + '&action=bkm_add_action&nonce=' + bkmFrontend.nonce,
            success: function(response) {
                if (response.success) {
                    // Clear form with custom function
                    clearActionForm();
                    
                    // Hide form
                    toggleActionForm();
                    
                    // Show success message
                    showNotification(response.data.message, 'success');
                    
                    // Add new action to table without page reload
                    if (response.data.action_html) {
                        addNewActionToTable(response.data.action_html);
                    }
                    
                    // Update task form action dropdown
                    if (response.data.action_id && response.data.action_details) {
                        updateTaskFormActionDropdown(response.data.action_id, response.data.action_details);
                    }
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Bir hata oluştu: ' + error, 'error');
            },
            complete: function() {
                // Re-enable form
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Aksiyon Ekle');
            }
        });
    });
    
    // ===== GÖREV EKLEME AJAX SİSTEMİ =====
    
    // Görev ekleme formu AJAX
    $(document).on('submit', '#bkm-task-form-element', function(e) {
        e.preventDefault();
        
        console.log('🚀 Görev ekleme formu submit edildi');
        
        // Check if bkmFrontend is defined
        if (typeof bkmFrontend === 'undefined') {
            console.error('❌ bkmFrontend objesi tanımlanmamış!');
            alert('HATA: WordPress AJAX sistemi yüklenmemiş. Sayfayı yenileyin ve WordPress\'e giriş yapmayı deneyin.');
            return;
        }
        
        var form = $(this);
        var formData = form.serialize();
        
        console.log('📝 Form verileri:', formData);
        console.log('🔗 AJAX URL:', bkmFrontend.ajax_url);
        console.log('🔐 Nonce:', bkmFrontend.nonce);
        
        // Validate required fields
        var isValid = true;
        form.find('[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('error');
                isValid = false;
                console.log('❌ Eksik alan:', $(this).attr('name'));
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            console.log('❌ Form validasyonu başarısız');
            showNotification('Lütfen tüm zorunlu alanları doldurun.', 'error');
            return;
        }
        
        console.log('✅ Form validasyonu başarılı, AJAX isteği gönderiliyor...');
        
        // Disable form during submission
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Ekleniyor...');
        
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            data: formData + '&action=bkm_add_task&nonce=' + bkmFrontend.nonce,
            timeout: 30000, // 30 second timeout
            success: function(response) {
                console.log('📨 AJAX yanıtı:', response);
                
                if (response.success) {
                    console.log('✅ Görev başarıyla eklendi');
                    
                    // Clear form
                    form[0].reset();
                    
                    // Hide form
                    toggleTaskForm();
                    
                    // Show success message
                    showNotification(response.data.message, 'success');
                    
                    // Add new task to the corresponding action's task list
                    if (response.data.task_html && response.data.action_id) {
                        console.log('📋 Görev HTML\'i tabloya ekleniyor...');
                        addNewTaskToAction(response.data.action_id, response.data.task_html);
                    }
                } else {
                    console.log('❌ Görev ekleme başarısız:', response.data.message);
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('💥 AJAX hatası:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    readyState: xhr.readyState
                });
                
                var errorMessage = 'Bir hata oluştu: ' + error;
                if (xhr.status === 0) {
                    errorMessage = 'Bağlantı hatası: Sunucuya ulaşılamıyor.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Yetki hatası: Bu işlemi yapmaya yetkiniz yok.';
                } else if (xhr.status === 404) {
                    errorMessage = 'AJAX endpoint bulunamadı.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Sunucu hatası: PHP hatası oluştu.';
                }
                
                showNotification(errorMessage, 'error');
            },
            complete: function() {
                console.log('🏁 AJAX isteği tamamlandı');
                // Re-enable form
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Görev Ekle');
            }
        });
    });
    
    /**
     * Add new task to action's task list
     */
    function addNewTaskToAction(actionId, taskHtml) {
        var tasksRow = $('#tasks-' + actionId);
        
        if (tasksRow.length === 0) {
            // If tasks row doesn't exist, create it (shouldn't happen normally)
            return;
        }
        
        var tasksContainer = tasksRow.find('.bkm-tasks-container');
        var tasksList = tasksContainer.find('.bkm-tasks-list');
        
        // If no tasks list exists, create it and remove "no tasks" message
        if (tasksList.length === 0) {
            tasksContainer.find('p:contains("henüz görev bulunmamaktadır")').remove();
            tasksList = $('<div class="bkm-tasks-list"></div>');
            tasksContainer.append(tasksList);
        }
        
        // Add new task with enhanced animation
        var newTaskElement = $(taskHtml);
        newTaskElement.hide();
        tasksList.append(newTaskElement);
        
        // Show with slide down animation
        newTaskElement.slideDown(400, function() {
            // Add highlighting animation
            newTaskElement.addClass('new-task-highlight');
            
            // Remove highlight after animation completes
            setTimeout(function() {
                newTaskElement.removeClass('new-task-highlight');
            }, 3000);
            
            // Scroll to the new task with smooth animation
            $('html, body').animate({
                scrollTop: newTaskElement.offset().top - 100
            }, 600, 'swing');
        });
        
        // Update task count in button
        var tasksButton = $('button[onclick="toggleTasks(' + actionId + ')"]');
        if (tasksButton.length > 0) {
            var currentText = tasksButton.text();
            var match = currentText.match(/\((\d+)\)/);
            if (match) {
                var currentCount = parseInt(match[1]);
                var newCount = currentCount + 1;
                var newText = currentText.replace(/\(\d+\)/, '(' + newCount + ')');
                tasksButton.text(newText);
            }
        }
        
        // If tasks row is not visible, show it
        if (tasksRow.is(':hidden')) {
            tasksRow.slideDown(300);
        }
    }
    
    // ===== MEVCUT KODLAR =====
    
    // Görev ekleme formu validasyonu (ESKİ - ARTIK KULLANILMIYOR)
    // $('#bkm-task-form form').on('submit', function(e) { ... });

    // Login form validasyonu
    $('.bkm-login-form').on('submit', function(e) {
        var username = $('#log').val();
        var password = $('#pwd').val();
        
        if (!username || !password) {
            e.preventDefault();
            alert('Lütfen kullanıcı adı ve şifre girin.');
            return false;
        }
    });
   
    // Initialize date inputs
    $('input[type="date"]').each(function() {
        if (!$(this).val()) {
            $(this).val(new Date().toISOString().slice(0, 10));
        }
    });
    
    // Form validation (AJAX note formları hariç - bunlar kendi validasyonlarını yapar)
    $('form:not(.bkm-note-form form):not(.bkm-reply-form)').on('submit', function(e) {
        var form = $(this);
        var isValid = true;
        
        // Clear previous error styles
        form.find('.error').removeClass('error');
        
        // Validate required fields
        form.find('input[required], select[required], textarea[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('error');
                isValid = false;
            }
        });
        
        // Validate date fields
        form.find('input[type="date"]').each(function() {
            var dateValue = $(this).val();
            if (dateValue && !isValidDate(dateValue)) {
                $(this).addClass('error');
                isValid = false;
            }
        });
        
        // Validate progress percentage
        var progressInput = form.find('input[name="ilerleme_durumu"]');
        if (progressInput.length > 0) {
            var progress = parseInt(progressInput.val());
            if (isNaN(progress) || progress < 0 || progress > 100) {
                progressInput.addClass('error');
                isValid = false;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            showNotification('Lütfen tüm gerekli alanları doğru şekilde doldurun.', 'error');
            
            // Scroll to first error
            var firstError = form.find('.error').first();
            if (firstError.length > 0) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
                firstError.focus();
            }
            
            return false;
        }
    });
    
    // Progress bar real-time update
    $('input[name="ilerleme_durumu"]').on('input', function() {
        var value = $(this).val();
        var progressBar = $(this).closest('.bkm-field').find('.bkm-progress-bar');
        if (progressBar.length > 0) {
            progressBar.css('width', value + '%');
        }
    });
    
    // Auto-hide notifications
    $('.bkm-success, .bkm-error').each(function() {
        var notification = $(this);
        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    });
    
    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $($(this).attr('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });
    
    // Task completion confirmation
    $('.bkm-btn-success[onclick*="confirm"]').on('click', function(e) {
        e.preventDefault();
        
        var form = $(this).closest('form');
        var taskContent = $(this).closest('.bkm-task-item').find('.bkm-task-content p strong').text();
        
        if (confirm('Bu görevi tamamladınız mı?\n\n"' + taskContent + '"')) {
            form.submit();
        }
    });
    
    // Table sorting
    $('.bkm-table th[data-sort]').on('click', function() {
        var table = $(this).closest('table');
        var column = $(this).data('sort');
        var order = $(this).hasClass('asc') ? 'desc' : 'asc';
        
        // Remove existing sort classes
        table.find('th').removeClass('asc desc');
        $(this).addClass(order);
        
        sortTable(table, column, order);
    });
    
    // Search functionality
    $('#bkm-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.bkm-table tbody tr').each(function() {
            var row = $(this);
            var text = row.text().toLowerCase();
            
            if (text.indexOf(searchTerm) > -1) {
                row.show();
            } else {
                row.hide();
            }
        });
    });
    
    // Filter functionality
    $('.bkm-filter select').on('change', function() {
        var filterType = $(this).data('filter');
        var filterValue = $(this).val();
        
        $('.bkm-table tbody tr').each(function() {
            var row = $(this);
            var cellValue = row.find('[data-filter="' + filterType + '"]').text().trim();
            
            if (!filterValue || cellValue === filterValue) {
                row.show();
            } else {
                row.hide();
            }
        });
    });
    
    // Real-time character counter for textareas
    $('textarea[maxlength]').each(function() {
        var textarea = $(this);
        var maxLength = textarea.attr('maxlength');
        var counter = $('<div class="char-counter">' + textarea.val().length + '/' + maxLength + '</div>');
        
        textarea.after(counter);
        
        textarea.on('input', function() {
            var currentLength = $(this).val().length;
            counter.text(currentLength + '/' + maxLength);
            
            if (currentLength > maxLength * 0.9) {
                counter.addClass('warning');
            } else {
                counter.removeClass('warning');
            }
        });
    });
    
    // Mobile menu toggle
    $('.bkm-mobile-menu-toggle').on('click', function() {
        $('.bkm-mobile-menu').slideToggle();
    });
    
    // Responsive table handling
    function makeTablesResponsive() {
        $('.bkm-table').each(function() {
            var table = $(this);
            if (!table.parent().hasClass('table-responsive')) {
                table.wrap('<div class="table-responsive"></div>');
            }
        });
    }
    
    makeTablesResponsive();
    
    // Helper functions
    function isValidDate(dateString) {
        var regEx = /^\d{4}-\d{2}-\d{2}$/;
        if (!dateString.match(regEx)) return false;
        var d = new Date(dateString);
        var dNum = d.getTime();
        if (!dNum && dNum !== 0) return false;
        return d.toISOString().slice(0, 10) === dateString;
    }
    
    // showNotification fonksiyonu global scope'a taşındı
    
    function sortTable(table, column, order) {
        var tbody = table.find('tbody');
        var rows = tbody.find('tr').toArray();
        
        rows.sort(function(a, b) {
            var aValue = $(a).find('[data-sort="' + column + '"]').text().trim();
            var bValue = $(b).find('[data-sort="' + column + '"]').text().trim();
            
            // Try to parse as numbers
            var aNum = parseFloat(aValue);
            var bNum = parseFloat(bValue);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return order === 'asc' ? aNum - bNum : bNum - aNum;
            }
            
            // Parse as dates
            var aDate = new Date(aValue);
            var bDate = new Date(bValue);
            
            if (!isNaN(aDate) && !isNaN(bDate)) {
                return order === 'asc' ? aDate - bDate : bDate - aDate;
            }
            
            // String comparison
            if (order === 'asc') {
                return aValue.localeCompare(bValue);
            } else {
                return bValue.localeCompare(aValue);
            }
        });
        
        tbody.empty().append(rows);
    }
    
    // Helper function to add new action to table
    function addNewActionToTable(actionHtml) {
        var tableBody = $('.bkm-table tbody');
        var newRow;
        
        // Check if "no actions" message exists
        var noActionsRow = tableBody.find('td:contains("Henüz aksiyon bulunmamaktadır")').closest('tr');
        
        if (noActionsRow.length > 0) {
            // Replace "no actions" message with new action
            noActionsRow.replaceWith(actionHtml);
            newRow = tableBody.find('tr').first();
        } else {
            // Prepend new action to the top of the table
            tableBody.prepend(actionHtml);
            newRow = tableBody.find('tr').first();
        }
        
        // Add highlight animation to the new row
        newRow.addClass('new-action-row');
        
        // Improved scroll to new action
        setTimeout(function() {
            if (newRow.length && newRow.is(':visible')) {
                // Get the table element for reference
                var table = $('.bkm-table');
                var tableOffset = table.offset();
                
                if (tableOffset) {
                    // Calculate the position of the new row within the table
                    var rowOffset = newRow.offset();
                    var targetPosition = rowOffset.top - 120; // 120px from top for better visibility
                    
                    // Ensure we don't scroll above the table
                    var minPosition = tableOffset.top - 50;
                    targetPosition = Math.max(minPosition, targetPosition);
                    
                    // Use a different scroll method for better reliability
                    $('html, body').stop().animate({
                        scrollTop: targetPosition
                    }, {
                        duration: 1200,
                        easing: 'swing',
                        complete: function() {
                            // Flash effect after scroll completes
                            newRow.fadeOut(150).fadeIn(150).fadeOut(150).fadeIn(150);
                        }
                    });
                } else {
                    // Fallback: scroll to top of page
                    $('html, body').animate({ scrollTop: 0 }, 800);
                }
            }
        }, 400); // Increased delay for DOM to fully update
        
        // Remove highlight after animation
        setTimeout(function() {
            newRow.removeClass('new-action-row');
        }, 5000);
    }
    
    // Helper function to update task form action dropdown
    function updateTaskFormActionDropdown(actionId, actionDetails) {
        var actionSelect = $('#action_id');
        
        if (actionSelect.length === 0) {
            console.log('⚠️ updateTaskFormActionDropdown: Aksiyon dropdown bulunamadı');
            return;
        }
        
        // Create new option element
        var optionText = '#' + actionId + ' - ' + actionDetails.aciklama.substring(0, 50) + '...';
        var newOption = $('<option></option>')
            .attr('value', actionId)
            .text(optionText);
        
        // Check if option already exists
        if (actionSelect.find('option[value="' + actionId + '"]').length === 0) {
            // Add new option after the first "Seçiniz..." option
            actionSelect.find('option:first').after(newOption);
            
            // Highlight the new option temporarily
            newOption.addClass('new-option');
            setTimeout(function() {
                newOption.removeClass('new-option');
            }, 3000);
            
            console.log('✅ Yeni aksiyon görev dropdown\'ına eklendi:', optionText);
            showNotification('Yeni aksiyon görev formunda da görüntülendi!', 'success');
        }
    }

    // AJAX functionality
    if (typeof bkmFrontend !== 'undefined') {
        
        // Auto-save form data to localStorage (aksiyon formu hariç)
        $('form input, form select, form textarea').not('#bkm-action-form-element input, #bkm-action-form-element select, #bkm-action-form-element textarea').on('change input', function() {
            var form = $(this).closest('form');
            var formId = form.attr('id') || 'bkm-form';
            
            // Skip action form auto-save to prevent conflicts
            if (formId === 'bkm-action-form-element') {
                return;
            }
            
            var formData = form.serialize();
            localStorage.setItem('bkm_form_data_' + formId, formData);
        });
        
        // Restore form data from localStorage (aksiyon formu hariç)
        $('form').not('#bkm-action-form-element').each(function() {
            var form = $(this);
            var formId = form.attr('id') || 'bkm-form';
            var savedData = localStorage.getItem('bkm_form_data_' + formId);
            
            if (savedData) {
                var params = new URLSearchParams(savedData);
                params.forEach(function(value, key) {
                    var field = form.find('[name="' + key + '"]');
                    if (field.length > 0) {
                        if (field.is('select')) {
                            field.val(value);
                        } else if (field.is('input[type="checkbox"]') || field.is('input[type="radio"]')) {
                            if (field.val() === value) {
                                field.prop('checked', true);
                            }
                        } else {
                            field.val(value);
                        }
                    }
                });
            }
        });
        
        // Clear saved form data on successful submission (aksiyon formu hariç - manuel yönetim)
        $('form').not('#bkm-action-form-element').on('submit', function() {
            var formId = $(this).attr('id') || 'bkm-form';
            localStorage.removeItem('bkm_form_data_' + formId);
        });
    }
    
    // Accessibility improvements
    $('input, select, textarea').on('focus', function() {
        $(this).closest('.bkm-field').addClass('focused');
    }).on('blur', function() {
        $(this).closest('.bkm-field').removeClass('focused');
    });
    
    // Keyboard navigation
    $(document).on('keydown', function(e) {
        // ESC to close modals/forms
        if (e.key === 'Escape') {
            $('.bkm-task-form:visible').hide();
            $('.bkm-tasks-row:visible').hide();
        }
        
        // Enter to submit forms (if not in textarea)
        if (e.key === 'Enter' && !$(e.target).is('textarea')) {
            var form = $(e.target).closest('form');
            if (form.length > 0) {
                e.preventDefault();
                form.submit();
            }
        }
    });
    
    // Performance optimization: Lazy load images
    $('img[data-src]').each(function() {
        var img = $(this);
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var lazyImg = $(entry.target);
                    lazyImg.attr('src', lazyImg.data('src'));
                    lazyImg.removeAttr('data-src');
                    observer.unobserve(entry.target);
                }
            });
        });
        
        observer.observe(this);
    });
    
    // Aksiyon formu sorumlu kişiler multi-select fix
    $(document).on('change', '#action_sorumlu_ids', function(e) {
        console.log('🔧 Sorumlu kişiler seçimi değişti:', $(this).val());
        // Prevent auto-clear by stopping any conflicting events
        e.stopPropagation();
        
        // Store the selection to prevent loss
        var selectedValues = $(this).val() || [];
        $(this).data('selected-values', selectedValues);
        
        // Update visual feedback
        $(this).attr('title', selectedValues.length + ' kişi seçildi');
    });
    
    // Prevent multi-select from losing selection on blur
    $(document).on('blur', '#action_sorumlu_ids', function(e) {
        var storedValues = $(this).data('selected-values');
        if (storedValues && storedValues.length > 0) {
            // Restore selection if it was cleared
            setTimeout(() => {
                if (!$(this).val() || $(this).val().length === 0) {
                    $(this).val(storedValues);
                    console.log('🔄 Sorumlu kişiler seçimi geri yüklendi:', storedValues);
                }
            }, 100);
        }
    });
});

// Global functions
function toggleTaskForm() {
    var form = jQuery('#bkm-task-form');
    var isVisible = form.is(':visible');
    
    if (isVisible) {
        // Form kapanıyorsa sadece kapat (görev formu otomatik temizleme zaten yapılıyor)
        form.slideUp();
    } else {
        // Form açılıyorsa diğer formu kapat
        jQuery('#bkm-action-form').slideUp();
        form.slideDown();
    }
}

function toggleActionForm() {
    var form = jQuery('#bkm-action-form');
    var isVisible = form.is(':visible');
    
    if (isVisible) {
        // Form kapanıyorsa temizle
        form.slideUp();
        clearActionForm();
    } else {
        // Form açılıyorsa diğer formu kapat
        jQuery('#bkm-task-form').slideUp();
        form.slideDown();
    }
}

function clearActionForm() {
    var form = jQuery('#bkm-action-form-element');
    
    if (form.length === 0) {
        console.log('⚠️ clearActionForm: Form bulunamadı');
        return;
    }
    
    // Reset form completely but preserve the structure
    form[0].reset();
    
    // Remove any error classes
    form.find('.error').removeClass('error');
    
    // Clear multi-select specifically (but don't override user selections)
    // Only clear when form is actually being reset after submission
    var multiSelect = form.find('#action_sorumlu_ids');
    if (multiSelect.length > 0) {
        multiSelect.val([]).trigger('change');
    }
    
    // Set default date to tomorrow
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    form.find('#action_hedef_tarih').val(tomorrow.toISOString().slice(0, 10));
    
    // Reset all field borders to normal
    form.find('input, select, textarea').css('border-color', '');
    
    // Clear saved form data to prevent conflicts
    var formId = form.attr('id') || 'bkm-action-form-element';
    localStorage.removeItem('bkm_form_data_' + formId);
    
    console.log('🧹 Aksiyon formu temizlendi (global function)');
}

function toggleTasks(actionId) {
    jQuery('#tasks-' + actionId).slideToggle();
}

function toggleActionDetails(actionId) {
    var detailsRow = jQuery('#details-' + actionId);
    var isVisible = detailsRow.is(':visible');
    
    if (isVisible) {
        // Detaylar açıksa kapat
        detailsRow.slideUp();
    } else {
        // Detaylar kapalıysa aç ve diğer detayları kapat
        jQuery('.bkm-action-details-row:visible').slideUp();
        detailsRow.slideDown();
        
        // Smooth scroll to details
        setTimeout(function() {
            jQuery('html, body').animate({
                scrollTop: detailsRow.offset().top - 100
            }, 500);
        }, 300);
    }
}

function bkmPrintTable() {
    var printContents = jQuery('.bkm-table').clone();
    var originalContents = document.body.innerHTML;
    
    document.body.innerHTML = '<table class="bkm-table">' + printContents.html() + '</table>';
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}

/**
 * Show notification message to user
 */
window.showNotification = function(message, type) {
    // Modern AJAX notification system
    var notificationClass = type === 'error' ? 'error' : 'success';
    var notification = jQuery('<div class="bkm-ajax-notification ' + notificationClass + '">' + 
                        '<span>' + message + '</span>' +
                        '<button class="close-btn" onclick="jQuery(this).parent().removeClass(\'show\')">&times;</button>' +
                        '</div>');
    
    // Remove existing notifications
    jQuery('.bkm-ajax-notification').remove();
    
    // Add to body
    jQuery('body').append(notification);
    
    // Show with animation
    setTimeout(function() {
        notification.addClass('show');
    }, 100);
    
    // Auto hide after 5 seconds
    setTimeout(function() {
        notification.removeClass('show');
        setTimeout(function() {
            notification.remove();
        }, 300);
    }, 5000);
}

// ===== YENİ GÖREV NOTLARI FONKSİYONLARI =====

/**
 * Toggle note form visibility
 */
window.toggleNoteForm = function(taskId) {
    console.log('🔧 toggleNoteForm çağrıldı, taskId:', taskId);
    var noteForm = jQuery('#note-form-' + taskId);
    console.log('📝 Note form bulundu:', noteForm.length);
    
    if (noteForm.length > 0) {
        if (noteForm.is(':visible')) {
            noteForm.slideUp(300);
        } else {
            // Close other note forms first
            jQuery('.bkm-note-form:visible').slideUp(300);
            noteForm.slideDown(300, function() {
                noteForm.find('textarea').focus();
            });
        }
    } else {
        console.error('❌ Not formu bulunamadı, ID:', '#note-form-' + taskId);
    }
};
    
/**
 * Toggle notes section visibility
 */
window.toggleNotes = function(taskId) {
    console.log('🔧 toggleNotes çağrıldı, taskId:', taskId);
    var notesSection = jQuery('#notes-' + taskId);
    console.log('💬 Notes section bulundu:', notesSection.length);
    
    if (notesSection.length > 0) {
        if (notesSection.is(':visible')) {
            notesSection.slideUp(300);
        } else {
            // Load notes first, then show
            loadTaskNotes(taskId, function() {
                notesSection.slideDown(300);
            });
        }
    } else {
        console.error('❌ Notlar bölümü bulunamadı, ID:', '#notes-' + taskId);
    }
};
    
/**
 * Load task notes via AJAX
 */
window.loadTaskNotes = function(taskId, callback) {
    console.log('🔄 Loading notes for task:', taskId);
    
    // Check if bkmFrontend is available
    if (typeof bkmFrontend === 'undefined') {
        console.error('❌ bkmFrontend objesi tanımlanmamış!');
        showNotification('WordPress AJAX sistemi yüklenemedi.', 'error');
        return;
    }
    
    jQuery.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        data: {
            action: 'bkm_get_task_notes',
            task_id: taskId,
            nonce: bkmFrontend.nonce
        },
        success: function(response) {
            console.log('📨 Task notes response:', response);
            
            if (response.success) {
                var notesContainer = jQuery('#notes-' + taskId + ' .bkm-notes-content');
                if (notesContainer.length === 0) {
                    notesContainer = jQuery('#notes-' + taskId);
                }
                
                if (response.data.notes && response.data.notes.length > 0) {
                    var notesHtml = '<div class="bkm-notes-content">';
                    
                    // Separate main notes and replies
                    var mainNotes = [];
                    var replies = {};
                    
                    response.data.notes.forEach(function(note) {
                        if (!note.parent_note_id) {
                            mainNotes.push(note);
                        } else {
                            if (!replies[note.parent_note_id]) {
                                replies[note.parent_note_id] = [];
                            }
                            replies[note.parent_note_id].push(note);
                        }
                    });
                    
                    // Build hierarchical HTML
                    mainNotes.forEach(function(note) {
                        // Main note
                        notesHtml += '<div class="bkm-note-item bkm-main-note" data-note-id="' + note.id + '">';
                        notesHtml += '<div class="bkm-note-indicator"></div>';
                        notesHtml += '<div class="bkm-note-content-wrapper">';
                        notesHtml += '<div class="bkm-note-meta">';
                        notesHtml += '<span class="bkm-note-author">👤 ' + note.author_name + '</span>';
                        notesHtml += '<span class="bkm-note-date">📅 ' + note.created_at + '</span>';
                        notesHtml += '</div>';
                        notesHtml += '<div class="bkm-note-content">' + note.content + '</div>';
                        notesHtml += '<div class="bkm-note-actions">';
                        notesHtml += '<button class="bkm-btn bkm-btn-small bkm-btn-secondary" onclick="toggleReplyForm(' + taskId + ', ' + note.id + ')">💬 Notu Cevapla</button>';
                        notesHtml += '</div>';
                        notesHtml += '<div id="reply-form-' + taskId + '-' + note.id + '" class="bkm-note-form" style="display: none;">';
                        notesHtml += '<form class="bkm-reply-form" data-task-id="' + taskId + '" data-parent-id="' + note.id + '">';
                        notesHtml += '<textarea name="note_content" rows="3" placeholder="Cevabınızı buraya yazın..." required></textarea>';
                        notesHtml += '<div class="bkm-form-actions">';
                        notesHtml += '<button type="submit" class="bkm-btn bkm-btn-primary bkm-btn-small">Cevap Gönder</button>';
                        notesHtml += '<button type="button" class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleReplyForm(' + taskId + ', ' + note.id + ')">İptal</button>';
                        notesHtml += '</div>';
                        notesHtml += '</form>';
                        notesHtml += '</div>';
                        notesHtml += '</div>';
                        notesHtml += '</div>';
                        
                        // Replies to this note
                        if (replies[note.id]) {
                            replies[note.id].forEach(function(reply, index) {
                                notesHtml += '<div class="bkm-note-item bkm-reply-note" data-note-id="' + reply.id + '" data-parent-id="' + note.id + '">';
                                notesHtml += '<div class="bkm-reply-connector"></div>';
                                notesHtml += '<div class="bkm-reply-arrow">↳</div>';
                                notesHtml += '<div class="bkm-note-content-wrapper">';
                                notesHtml += '<div class="bkm-note-meta">';
                                notesHtml += '<span class="bkm-note-author">👤 ' + reply.author_name + '</span>';
                                notesHtml += '<span class="bkm-note-date">📅 ' + reply.created_at + '</span>';
                                notesHtml += '<span class="bkm-reply-badge">Cevap</span>';
                                notesHtml += '</div>';
                                notesHtml += '<div class="bkm-note-content">' + reply.content + '</div>';
                                notesHtml += '</div>';
                                notesHtml += '</div>';
                            });
                        }
                    });
                    
                    notesHtml += '</div>';
                    notesContainer.html(notesHtml);
                } else {
                    notesContainer.html('<div class="bkm-notes-content"><p style="text-align: center; color: #9e9e9e; font-style: italic; margin: 20px 0; padding: 30px; border: 2px dashed #e0e0e0; border-radius: 12px;">📝 Bu görev için henüz not bulunmamaktadır.</p></div>');
                }
                
                if (callback) callback();
            } else {
                console.error('❌ Failed to load task notes:', response.data.message);
                showNotification('Notlar yüklenirken hata oluştu: ' + response.data.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('💥 AJAX error loading task notes:', error);
            showNotification('Notlar yüklenirken bağlantı hatası oluştu.', 'error');
            if (callback) callback();
        }
    });
}

/**
 * Toggle reply form visibility for a specific note
 */
window.toggleReplyForm = function(taskId, noteId) {
    console.log('🔧 toggleReplyForm çağrıldı, taskId:', taskId, 'noteId:', noteId);
    var replyForm = jQuery('#reply-form-' + taskId + '-' + noteId);
    console.log('💬 Reply form bulundu:', replyForm.length);
    
    if (replyForm.length > 0) {
        if (replyForm.is(':visible')) {
            replyForm.slideUp(300);
        } else {
            // Close other reply forms first
            jQuery('.bkm-note-form:visible').slideUp(300);
            replyForm.slideDown(300, function() {
                replyForm.find('textarea').focus();
            });
        }
    } else {
        console.error('❌ Cevap formu bulunamadı, ID:', '#reply-form-' + taskId + '-' + noteId);
    }
};

// Service Worker devre dışı - sw.js dosyası mevcut değil
// if ('serviceWorker' in navigator) {
//     navigator.serviceWorker.register('/sw.js').then(function(registration) {
//         console.log('ServiceWorker registration successful');
//     }).catch(function(err) {
//         console.log('ServiceWorker registration failed');
//     });
// }