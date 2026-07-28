jQuery(document).ready(function($) {
    
    $('#b2b-import-form').on('submit', function(e) {
        e.preventDefault();
        
        const fileInput = $('#import_file')[0];
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Molimo odaberite datoteku.');
            return;
        }
        
        // Validate file extension
        const fileName = file.name.toLowerCase();
        const validExtensions = ['xlsx', 'xls', 'csv'];
        const fileExtension = fileName.split('.').pop();
        
        if (!validExtensions.includes(fileExtension)) {
            alert('Nepodržan format datoteke. Molimo koristite Excel (.xlsx, .xls) ili CSV (.csv) format.');
            return;
        }
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'b2b_process_import');
        formData.append('nonce', b2bImporter.nonce);
        formData.append('import_file', file);
        formData.append('dry_run', $('#dry_run').is(':checked') ? '1' : '0');
        formData.append('send_password_reset', $('#send_password_reset').is(':checked') ? '1' : '0');
        
        // Update UI
        const $submitBtn = $('#start-import');
        $submitBtn.addClass('loading').prop('disabled', true).text('⏳ Import u tijeku...');
        
        $('#import-progress').show();
        $('#import-results').hide();
        $('#progress-bar').css('width', '0%').text('');
        $('#progress-text').text('Učitavanje datoteke...').addClass('loading-text');
        
        // Start import
        $.ajax({
            url: b2bImporter.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 50; // 50% for upload
                        updateProgress(percentComplete, 'Uploadanje datoteke...');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    updateProgress(100, 'Import završen!');
                    displayResults(response.data);
                } else {
                    updateProgress(0, 'Greška!');
                    
                    // Display error message with HTML support
                    let errorMsg = response.data.message || 'Nepoznata greška';
                    let errorHtml = `
                        <div style="background: #fde7e9; border-left: 4px solid #d63638; padding: 15px; margin: 20px 0; border-radius: 4px;">
                            <h3 style="margin-top: 0; color: #d63638;">❌ Greška pri importu</h3>
                            <div style="color: #50171a;">${errorMsg}</div>
                        </div>
                    `;
                    
                    $('#results-content').html(errorHtml);
                    $('#import-results').show();
                    
                    // Scroll to error
                    $('html, body').animate({
                        scrollTop: $('#import-results').offset().top - 100
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                updateProgress(0, 'Greška!');
                
                let errorMsg = 'Došlo je do greške pri importu.';
                
                // Try to get error from response
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    // Try to extract error from HTML response
                    let parser = new DOMParser();
                    let doc = parser.parseFromString(xhr.responseText, 'text/html');
                    let errorText = doc.querySelector('body')?.textContent || xhr.statusText;
                    errorMsg = `${errorMsg}<br><br><strong>Tehnički detalji:</strong><br>${errorText.substring(0, 500)}`;
                } else {
                    errorMsg = `${errorMsg}<br><strong>Status:</strong> ${xhr.status}<br><strong>Error:</strong> ${error}`;
                }
                
                let errorHtml = `
                    <div style="background: #fde7e9; border-left: 4px solid #d63638; padding: 15px; margin: 20px 0; border-radius: 4px;">
                        <h3 style="margin-top: 0; color: #d63638;">❌ Greška pri importu</h3>
                        <div style="color: #50171a;">${errorMsg}</div>
                        <br>
                        <p><strong>Savjeti:</strong></p>
                        <ul>
                            <li>Provjerite da je CSV datoteka pravilno formatirana</li>
                            <li>Provjerite WordPress error log za više detalja</li>
                            <li>Ako koristite Excel format, pokrenite: <code>composer install --no-dev</code></li>
                            <li>Kontaktirajte support: team@uncledev.info</li>
                        </ul>
                    </div>
                `;
                
                $('#results-content').html(errorHtml);
                $('#import-results').show();
                
                // Scroll to error
                $('html, body').animate({
                    scrollTop: $('#import-results').offset().top - 100
                }, 500);
            },
            complete: function() {
                $submitBtn.removeClass('loading').prop('disabled', false).text('🚀 Započni Import');
                $('#progress-text').removeClass('loading-text');
            }
        });
    });
    
    function updateProgress(percent, text) {
        $('#progress-bar').css('width', percent + '%').text(Math.round(percent) + '%');
        $('#progress-text').text(text);
    }
    
    function displayResults(data) {
        const results = data.results;
        const log = data.log;
        
        let html = '<div class="results-summary">';
        
        html += `
            <div class="result-stat success">
                <div class="number">${results.success}</div>
                <div class="label">Uspješno</div>
            </div>
        `;
        
        if (results.skipped > 0) {
            html += `
                <div class="result-stat skipped">
                    <div class="number">${results.skipped}</div>
                    <div class="label">Preskočeno</div>
                </div>
            `;
        }
        
        if (results.errors > 0) {
            html += `
                <div class="result-stat error">
                    <div class="number">${results.errors}</div>
                    <div class="label">Greške</div>
                </div>
            `;
        }
        
        html += `
            <div class="result-stat">
                <div class="number">${results.total}</div>
                <div class="label">Ukupno</div>
            </div>
        `;
        
        html += '</div>';
        
        // Add log
        if (log && log.length > 0) {
            html += '<h3>📋 Detaljan log:</h3>';
            html += '<div class="import-log">';
            
            log.forEach(function(entry) {
                const icon = getLogIcon(entry.type);
                html += `
                    <div class="log-entry ${entry.type}">
                        <span class="log-entry-time">[${formatTime(entry.time)}]</span>
                        ${icon} ${escapeHtml(entry.message)}
                    </div>
                `;
            });
            
            html += '</div>';
        }
        
        $('#results-content').html(html);
        $('#import-results').show();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#import-results').offset().top - 100
        }, 500);
    }
    
    function getLogIcon(type) {
        const icons = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        return icons[type] || '•';
    }
    
    function formatTime(timeString) {
        const date = new Date(timeString);
        return date.toLocaleTimeString('hr-HR');
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // File input styling
    $('#import_file').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        if (fileName) {
            $(this).next('.description').text('Odabrana datoteka: ' + fileName);
        }
    });
    
});
