/**
 * WASGO Admin JavaScript
 */

jQuery(document).ready(function($) {

    // -------------------------------------------------------------
    // Bulk Image Generation Logic
    // -------------------------------------------------------------
    let genProgressTimer = null;

    function triggerGeneration( resume ) {
        let forceAll = $('input[name="wasgo_force_all"]:checked').val();
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

    // -------------------------------------------------------------
    // Bulk Gallery Enhancement Logic
    // -------------------------------------------------------------
    let galProgressTimer = null;

    function triggerGallery( resume ) {
        let force = $('input[name="wasgo_gallery_force"]:checked').val();
        let resumeFlag = resume ? '1' : '0';

        $('#wasgo-gal-btn-start').attr('disabled', 'disabled');
        $('#wasgo-gal-btn-restart').attr('disabled', 'disabled');
        $('#wasgo-gal-btn-stop').removeAttr('disabled');
        
        $('#wasgo-gal-bulk-notice').html('Calculating total galleries...');
        
        if (!resume) {
            $('#wasgo-gal-progress-bar-fill').css('width', '0%');
            $('#wasgo-gal-progress-text').text('0 / 0');
        }

        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_start_bulk_gallery',
                nonce: wasgo_ajax.nonce,
                force: force,
                resume: resumeFlag
            },
            success: function(response) {
                if(response.success) {
                    $('#wasgo-gal-bulk-notice').html('<span style="color:green;">' + response.data.message + '</span>');
                    $('#wasgo-gal-progress-container').slideDown();
                    startGalPolling();
                } else {
                    $('#wasgo-gal-bulk-notice').html('<span style="color:red;">' + response.data.message + '</span>');
                    $('#wasgo-gal-btn-start').removeAttr('disabled');
                    $('#wasgo-gal-btn-restart').removeAttr('disabled');
                    $('#wasgo-gal-btn-stop').attr('disabled', 'disabled');
                    $('#wasgo-gal-progress-container').slideUp();
                }
            },
            error: function() {
                $('#wasgo-gal-bulk-notice').html('<span style="color:red;">Server Error.</span>');
                $('#wasgo-gal-btn-start').removeAttr('disabled');
                $('#wasgo-gal-btn-restart').removeAttr('disabled');
                $('#wasgo-gal-btn-stop').attr('disabled', 'disabled');
                $('#wasgo-gal-progress-container').slideUp();
            }
        });
    }

    $('#wasgo-gal-btn-start').on('click', function(e) {
        e.preventDefault();
        triggerGallery(true); // resume
    });

    $('#wasgo-gal-btn-restart').on('click', function(e) {
        e.preventDefault();
        if(!confirm('Are you sure you want to RESTART gallery enhancement from scratch?')) return;
        triggerGallery(false); // restart
    });

    $('#wasgo-gal-btn-stop').on('click', function(e) {
        e.preventDefault();
        $(this).attr('disabled', 'disabled');
        
        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_stop_bulk_gallery',
                nonce: wasgo_ajax.nonce
            },
            success: function(response) {
                $('#wasgo-gal-bulk-notice').html('<span style="color:orange;">Processing paused.</span>');
                $('#wasgo-gal-status-text').text('Paused');
                $('#wasgo-gal-btn-start').removeAttr('disabled');
                $('#wasgo-gal-btn-restart').removeAttr('disabled');
                if(galProgressTimer) clearInterval(galProgressTimer);
            }
        });
    });

    function startGalPolling() {
        if(galProgressTimer) clearInterval(galProgressTimer);
        
        galProgressTimer = setInterval(function() {
            $.ajax({
                url: wasgo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wasgo_get_gallery_progress',
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
                            $('#wasgo-gal-progress-bar-fill').css('width', percentage + '%');
                            $('#wasgo-gal-progress-text').text(processed + ' / ' + total + ' (' + percentage + '%)');
                        }

                        if(status === 'finished') {
                            $('#wasgo-gal-status-text').text('Finished!');
                            $('#wasgo-gal-bulk-notice').html('<span style="color:green;">Bulk gallery enhancement complete!</span>');
                            $('#wasgo-gal-btn-stop').attr('disabled', 'disabled');
                            $('#wasgo-gal-btn-start').removeAttr('disabled');
                            $('#wasgo-gal-btn-restart').removeAttr('disabled');
                            clearInterval(galProgressTimer);
                        } else if(status === 'stopped') {
                            $('#wasgo-gal-status-text').text('Paused');
                            $('#wasgo-gal-btn-stop').attr('disabled', 'disabled');
                            $('#wasgo-gal-btn-start').removeAttr('disabled');
                            $('#wasgo-gal-btn-restart').removeAttr('disabled');
                            clearInterval(galProgressTimer);
                        } else {
                            $('#wasgo-gal-status-text').text('Processing...');
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

    $.post(wasgo_ajax.ajax_url, { action: 'wasgo_get_gallery_progress', nonce: wasgo_ajax.nonce }, function(response) {
        if(response.success && response.data.status === 'running') {
            $('#wasgo-gal-progress-container').slideDown();
            $('#wasgo-gal-btn-start').attr('disabled', 'disabled');
            $('#wasgo-gal-btn-restart').attr('disabled', 'disabled');
            $('#wasgo-gal-btn-stop').removeAttr('disabled');
            startGalPolling();
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
    // -------------------------------------------------------------
    // Bulk Content Generation Logic
    // -------------------------------------------------------------
    let contentProgressTimer = null;

    function triggerContentGeneration( resume ) {
        let types = [];
        $('input[name="wasgo_content_bulk_types[]"]:checked').each(function() {
            types.push($(this).val());
        });
        let mode = $('input[name="wasgo_content_bulk_mode"]:checked').val();
        let resumeFlag = resume ? '1' : '0';

        if (types.length === 0) {
            alert('Please select at least one content type to generate.');
            return;
        }

        $('#wasgo-content-btn-start').attr('disabled', 'disabled');
        $('#wasgo-content-btn-restart').attr('disabled', 'disabled');
        $('#wasgo-content-btn-stop').removeAttr('disabled');
        
        $('#wasgo-content-bulk-notice').html('Calculating total products...');
        
        if (!resume) {
            $('#wasgo-content-progress-bar-fill').css('width', '0%');
            $('#wasgo-content-progress-text').text('0 / 0');
        }

        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_content_start',
                nonce: wasgo_ajax.nonce,
                types: types,
                mode: mode,
                resume: resumeFlag
            },
            success: function(response) {
                if(response.success) {
                    $('#wasgo-content-bulk-notice').html('<span style="color:green;">Generation started!</span>');
                    $('#wasgo-content-progress-container').slideDown();
                    startContentPolling();
                } else {
                    $('#wasgo-content-bulk-notice').html('<span style="color:red;">' + response.data.message + '</span>');
                    $('#wasgo-content-btn-start').removeAttr('disabled');
                    $('#wasgo-content-btn-restart').removeAttr('disabled');
                    $('#wasgo-content-btn-stop').attr('disabled', 'disabled');
                }
            }
        });
    }

    $('#wasgo-content-btn-start').on('click', function(e) {
        e.preventDefault();
        triggerContentGeneration(true);
    });

    $('#wasgo-content-btn-restart').on('click', function(e) {
        e.preventDefault();
        if(!confirm('Are you sure you want to RESTART content generation?')) return;
        triggerContentGeneration(false);
    });

    $('#wasgo-content-btn-stop').on('click', function(e) {
        e.preventDefault();
        $(this).attr('disabled', 'disabled');
        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_content_stop',
                nonce: wasgo_ajax.nonce
            },
            success: function(response) {
                $('#wasgo-content-bulk-notice').html('<span style="color:orange;">Processing paused.</span>');
                $('#wasgo-content-status-text').text('Paused');
                $('#wasgo-content-btn-start').removeAttr('disabled');
                $('#wasgo-content-btn-restart').removeAttr('disabled');
                if(contentProgressTimer) clearInterval(contentProgressTimer);
            }
        });
    });

    function startContentPolling() {
        if(contentProgressTimer) clearInterval(contentProgressTimer);
        contentProgressTimer = setInterval(function() {
            $.ajax({
                url: wasgo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wasgo_content_progress',
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
                            $('#wasgo-content-progress-bar-fill').css('width', percentage + '%');
                            $('#wasgo-content-progress-text').text(processed + ' / ' + total + ' (' + percentage + '%)');
                        }

                        if(status === 'finished') {
                            $('#wasgo-content-status-text').text('Finished!');
                            $('#wasgo-content-bulk-notice').html('<span style="color:green;">Bulk content generation complete!</span>');
                            $('#wasgo-content-btn-stop').attr('disabled', 'disabled');
                            $('#wasgo-content-btn-start').removeAttr('disabled');
                            $('#wasgo-content-btn-restart').removeAttr('disabled');
                            clearInterval(contentProgressTimer);
                        } else if(status === 'stopped') {
                            $('#wasgo-content-status-text').text('Paused');
                            $('#wasgo-content-btn-stop').attr('disabled', 'disabled');
                            $('#wasgo-content-btn-start').removeAttr('disabled');
                            $('#wasgo-content-btn-restart').removeAttr('disabled');
                            clearInterval(contentProgressTimer);
                        } else {
                            $('#wasgo-content-status-text').text('Processing...');
                        }
                    }
                }
            });
        }, 4000);
    }

    // Auto-resume check for content
    $.post(wasgo_ajax.ajax_url, { action: 'wasgo_content_progress', nonce: wasgo_ajax.nonce }, function(response) {
        if(response.success && response.data.status === 'running') {
            $('#wasgo-content-progress-container').slideDown();
            $('#wasgo-content-btn-start').attr('disabled', 'disabled');
            $('#wasgo-content-btn-restart').attr('disabled', 'disabled');
            $('#wasgo-content-btn-stop').removeAttr('disabled');
            startContentPolling();
        }
    });

    // -------------------------------------------------------------
    // Review Queue Actions
    // -------------------------------------------------------------
    $(document).on('click', '.wasgo-review-action', function(e) {
        e.preventDefault();
        let $btn = $(this);
        let action = $btn.data('action');
        let pid = $btn.data('pid');
        let type = $btn.data('type');
        let $row = $('#review-row-' + pid + '-' + type);

        $btn.attr('disabled', 'disabled').text('...');

        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_content_review_action',
                nonce: wasgo_ajax.nonce,
                review_action: action,
                pid: pid,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        if ($('#wasgo-content-review-body tr').length === 0) {
                            location.reload(); 
                        }
                    });
                } else {
                    alert('Error: ' + response.data);
                    $btn.removeAttr('disabled').text(action === 'approve' ? 'Approve' : 'Discard');
                }
            }
        });
    });

});
