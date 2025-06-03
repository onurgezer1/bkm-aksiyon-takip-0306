/**
 * BKM Aksiyon Takip - Frontend JavaScript
 */

jQuery(document).ready(function($) {
    
    // Initialize date inputs
    $('input[type="date"]').each(function() {
        if (!$(this).val()) {
            $(this).val(new Date().toISOString().slice(0, 10));
        }
    });
    
    // Form validation
    $('form').on('submit', function(e) {
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
    
    function showNotification(message, type) {
        var notificationClass = type === 'error' ? 'bkm-error' : 'bkm-success';
        var notification = $('<div class="' + notificationClass + '">' + message + '</div>');
        
        $('.bkm-dashboard-header').after(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
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
    
    // AJAX functionality
    if (typeof bkmFrontend !== 'undefined') {
        
        // Auto-save form data to localStorage
        $('form input, form select, form textarea').on('change input', function() {
            var form = $(this).closest('form');
            var formId = form.attr('id') || 'bkm-form';
            var formData = form.serialize();
            
            localStorage.setItem('bkm_form_data_' + formId, formData);
        });
        
        // Restore form data from localStorage
        $('form').each(function() {
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
        
        // Clear saved form data on successful submission
        $('form').on('submit', function() {
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
});

// Global functions
function toggleTaskForm() {
    jQuery('#bkm-task-form').slideToggle();
}

function toggleTasks(actionId) {
    jQuery('#tasks-' + actionId).slideToggle();
}

function bkmPrintTable() {
    var printContents = jQuery('.bkm-table').clone();
    var originalContents = document.body.innerHTML;
    
    document.body.innerHTML = '<table class="bkm-table">' + printContents.html() + '</table>';
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}

// Service Worker for offline functionality (if available)
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').then(function(registration) {
        console.log('ServiceWorker registration successful');
    }).catch(function(err) {
        console.log('ServiceWorker registration failed');
    });
}