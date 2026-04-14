/**
 * WASGO Admin JavaScript
 */

jQuery(document).ready(function($) {

    // -------------------------------------------------------------
    // Bulk Image Generation Logic
    // -------------------------------------------------------------
    let genProgressTimer = null;

    function triggerGeneration( resume ) {
        let forceAll = $('#wasgo_force_all').is(':checked') ? '1' : '0';
        let resumeFlag = resume ? '1' : '0';

        $('#wasgo-btn-start').attr('disabled', 'disabled');
        $('#wasgo-btn-restart').attr('disabled', 'disabled');
        $('#wasgo-btn-stop').removeAttr('disabled');
        
        $('#wasgo-bulk-notice').html('Calculating total products...');
        
        if (!resume) {
            $('#wasgo-progress-bar-fill').css('width', '0%');
            $('#wasgo-progress-text').text('0 / 0');
        }

        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_start_bulk',
                nonce: wasgo_ajax.nonce,
                force_all: forceAll,
                resume: resumeFlag
            },
            success: function(response) {
                if(response.success) {
                    $('#wasgo-bulk-notice').html('<span style="color:green;">' + response.data.message + '</span>');
                    $('#wasgo-progress-container').slideDown();
                    startGenPolling();
                } else {
                    $('#wasgo-bulk-notice').html('<span style="color:red;">' + response.data.message + '</span>');
                    $('#wasgo-btn-start').removeAttr('disabled');
                    $('#wasgo-btn-restart').removeAttr('disabled');
                    $('#wasgo-btn-stop').attr('disabled', 'disabled');
                    $('#wasgo-progress-container').slideUp();
                }
            },
            error: function() {
                $('#wasgo-bulk-notice').html('<span style="color:red;">Server Error.</span>');
                $('#wasgo-btn-start').removeAttr('disabled');
                $('#wasgo-btn-restart').removeAttr('disabled');
                $('#wasgo-btn-stop').attr('attr', 'disabled');
                $('#wasgo-progress-container').slideUp();
            }
        });
    }

    $('#wasgo-btn-start').on('click', function(e) {
        e.preventDefault();
        triggerGeneration(true); // resume
    });

    $('#wasgo-btn-restart').on('click', function(e) {
        e.preventDefault();
        if(!confirm('Are you sure you want to RESTART bulk generation from scratch?')) return;
        triggerGeneration(false); // don't resume, restart
    });

    $('#wasgo-btn-stop').on('click', function(e) {
        e.preventDefault();
        $(this).attr('disabled', 'disabled');
        
        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_stop_bulk',
                nonce: wasgo_ajax.nonce
            },
            success: function(response) {
                $('#wasgo-bulk-notice').html('<span style="color:orange;">Processing paused.</span>');
                $('#wasgo-status-text').text('Paused');
                $('#wasgo-btn-start').removeAttr('disabled');
                $('#wasgo-btn-restart').removeAttr('disabled');
                if(genProgressTimer) clearInterval(genProgressTimer);
            }
        });
    });

    function startGenPolling() {
        if(genProgressTimer) clearInterval(genProgressTimer);
        
        genProgressTimer = setInterval(function() {
            $.ajax({
                url: wasgo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wasgo_get_progress',
                    nonce: wasgo_ajax.nonce
                },
                success: function(response) {
                    if(response.success) {
                        let data = response.data;
                        let processed = parseInt(data.processed);
                        let total = parseInt(data.total);
                        let status = data.status;

                        if(total > 0) {
                            let percentage = Math.round((processed / total) * 100);
                            $('#wasgo-progress-bar-fill').css('width', percentage + '%');
                            $('#wasgo-progress-text').text(processed + ' / ' + total + ' (' + percentage + '%)');
                        }

                        if(status === 'finished') {
                            $('#wasgo-status-text').text('Finished!');
                            $('#wasgo-bulk-notice').html('<span style="color:green;">Bulk generation complete!</span>');
                            $('#wasgo-btn-stop').attr('disabled', 'disabled');
                            $('#wasgo-btn-start').removeAttr('disabled');
                            $('#wasgo-btn-restart').removeAttr('disabled');
                            clearInterval(genProgressTimer);
                        } else if(status === 'stopped') {
                            $('#wasgo-status-text').text('Paused');
                            $('#wasgo-btn-stop').attr('disabled', 'disabled');
                            $('#wasgo-btn-start').removeAttr('disabled');
                            $('#wasgo-btn-restart').removeAttr('disabled');
                            clearInterval(genProgressTimer);
                        } else {
                            $('#wasgo-status-text').text('Processing...');
                        }
                    }
                }
            });
        }, 3000);
    }


    // -------------------------------------------------------------
    // Bulk Backup Deletion Logic
    // -------------------------------------------------------------
    let delProgressTimer = null;

    function triggerDeletion( resume ) {
        let resumeFlag = resume ? '1' : '0';

        $('#wasgo-del-btn-start').attr('disabled', 'disabled');
        $('#wasgo-del-btn-restart').attr('disabled', 'disabled');
        $('#wasgo-del-btn-stop').removeAttr('disabled');
        
        $('#wasgo-del-bulk-notice').html('Calculating total backups...');
        
        if (!resume) {
            $('#wasgo-del-progress-bar-fill').css('width', '0%');
            $('#wasgo-del-progress-text').text('0 / 0');
        }

        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_start_bulk_delete',
                nonce: wasgo_ajax.nonce,
                resume: resumeFlag
            },
            success: function(response) {
                if(response.success) {
                    $('#wasgo-del-bulk-notice').html('<span style="color:green;">' + response.data.message + '</span>');
                    $('#wasgo-del-progress-container').slideDown();
                    startDelPolling();
                } else {
                    $('#wasgo-del-bulk-notice').html('<span style="color:red;">' + response.data.message + '</span>');
                    $('#wasgo-del-btn-start').removeAttr('disabled');
                    $('#wasgo-del-btn-restart').removeAttr('disabled');
                    $('#wasgo-del-btn-stop').attr('disabled', 'disabled');
                    $('#wasgo-del-progress-container').slideUp();
                }
            },
            error: function() {
                $('#wasgo-del-bulk-notice').html('<span style="color:red;">Server Error.</span>');
                $('#wasgo-del-btn-start').removeAttr('disabled');
                $('#wasgo-del-btn-restart').removeAttr('disabled');
                $('#wasgo-del-btn-stop').attr('disabled', 'disabled');
                $('#wasgo-del-progress-container').slideUp();
            }
        });
    }

    $('#wasgo-del-btn-start').on('click', function(e) {
        e.preventDefault();
        triggerDeletion(true); // resume
    });

    $('#wasgo-del-btn-restart').on('click', function(e) {
        e.preventDefault();
        if(!confirm('Are you sure you want to RESTART backup deletion from scratch?')) return;
        triggerDeletion(false); // restart
    });

    $('#wasgo-del-btn-stop').on('click', function(e) {
        e.preventDefault();
        $(this).attr('disabled', 'disabled');
        
        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_stop_bulk_delete',
                nonce: wasgo_ajax.nonce
            },
            success: function(response) {
                $('#wasgo-del-bulk-notice').html('<span style="color:orange;">Deletion paused.</span>');
                $('#wasgo-del-status-text').text('Paused');
                $('#wasgo-del-btn-start').removeAttr('disabled');
                $('#wasgo-del-btn-restart').removeAttr('disabled');
                if(delProgressTimer) clearInterval(delProgressTimer);
            }
        });
    });

    function startDelPolling() {
        if(delProgressTimer) clearInterval(delProgressTimer);
        
        delProgressTimer = setInterval(function() {
            $.ajax({
                url: wasgo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wasgo_get_delete_progress',
                    nonce: wasgo_ajax.nonce
                },
                success: function(response) {
                    if(response.success) {
                        let data = response.data;
                        let processed = parseInt(data.processed);
                        let total = parseInt(data.total);
                        let status = data.status;

                        if(total > 0) {
                            let percentage = Math.round((processed / total) * 100);
                            $('#wasgo-del-progress-bar-fill').css('width', percentage + '%');
                            $('#wasgo-del-progress-text').text(processed + ' / ' + total + ' (' + percentage + '%)');
                        }

                        if(status === 'finished') {
                            $('#wasgo-del-status-text').text('Finished!');
                            $('#wasgo-del-bulk-notice').html('<span style="color:green;">Backup deletion complete!</span>');
                            $('#wasgo-del-btn-stop').attr('disabled', 'disabled');
                            $('#wasgo-del-btn-start').removeAttr('disabled');
                            $('#wasgo-del-btn-restart').removeAttr('disabled');
                            clearInterval(delProgressTimer);
                        } else if(status === 'stopped') {
                            $('#wasgo-del-status-text').text('Paused');
                            $('#wasgo-del-btn-stop').attr('disabled', 'disabled');
                            $('#wasgo-del-btn-start').removeAttr('disabled');
                            $('#wasgo-del-btn-restart').removeAttr('disabled');
                            clearInterval(delProgressTimer);
                        } else {
                            $('#wasgo-del-status-text').text('Processing...');
                        }
                    }
                }
            });
        }, 3000);
    }
    
    // Auto-resume polling if currently running
    $.post(wasgo_ajax.ajax_url, { action: 'wasgo_get_progress', nonce: wasgo_ajax.nonce }, function(response) {
        if(response.success && response.data.status === 'running') {
            $('#wasgo-progress-container').slideDown();
            $('#wasgo-btn-start').attr('disabled', 'disabled');
            $('#wasgo-btn-restart').attr('disabled', 'disabled');
            $('#wasgo-btn-stop').removeAttr('disabled');
            startGenPolling();
        }
    });
    
    $.post(wasgo_ajax.ajax_url, { action: 'wasgo_get_delete_progress', nonce: wasgo_ajax.nonce }, function(response) {
        if(response.success && response.data.status === 'running') {
            $('#wasgo-del-progress-container').slideDown();
            $('#wasgo-del-btn-start').attr('disabled', 'disabled');
            $('#wasgo-del-btn-restart').attr('disabled', 'disabled');
            $('#wasgo-del-btn-stop').removeAttr('disabled');
            startDelPolling();
        }
    });

    // -------------------------------------------------------------
    // Settings UI Toggles
    // -------------------------------------------------------------
    $('#wasgo_auto_compress').on('change', function() {
        if ($(this).is(':checked')) {
            $('.wasgo-compress-dependency').show();
        } else {
            $('.wasgo-compress-dependency').hide();
        }
    });

});
