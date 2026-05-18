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
                    let total = parseInt(response.data.total);
                    if (total === 0) {
                        $('#wasgo-content-bulk-notice').html('<span style="color:orange;">No product to processing remaining.</span>');
                        $('#wasgo-content-btn-start').removeAttr('disabled');
                        $('#wasgo-content-btn-restart').removeAttr('disabled');
                        $('#wasgo-content-btn-stop').attr('disabled', 'disabled');
                        return;
                    }
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
                            if (total > 0) {
                                $('#wasgo-content-progress-bar-fill').css('width', '100%');
                                $('#wasgo-content-progress-text').text(total + ' / ' + total + ' (100%)');
                                $('#wasgo-content-bulk-notice').html('<span style="color:green;">Bulk content generation complete!</span>');
                            } else {
                                $('#wasgo-content-bulk-notice').html('<span style="color:orange;">No product to processing remaining.</span>');
                            }
                            $('#wasgo-content-status-text').text('Finished!');
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
        }, 2000);
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
        let itemType = $btn.data('item-type') || 'product';
        
        let rowId = '#review-row-' + pid + '-' + type;
        if (itemType === 'category') {
            rowId += '-category';
        }
        let $row = $(rowId);

        $btn.attr('disabled', 'disabled').text('...');

        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_content_review_action',
                nonce: wasgo_ajax.nonce,
                review_action: action,
                pid: pid,
                type: type,
                item_type: itemType
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

    // -------------------------------------------------------------
    // Live Prompt Preview Engine
    // -------------------------------------------------------------
    function updatePromptPreview() {
        let $input = $('#wasgo-prompt-input');
        if (!$input.length) return;

        let $preview = $('#wasgo-prompt-live-preview');
        let userPrompt = $input.val();
        let type = $input.data('type');
        let sample = wasgo_ajax.sample;

        let finalPrompt = '';
        let isCat = (type === 'cat_title' || type === 'cat_desc');
        
        // Visual Image Context
        let imageHtml = '';
        if (isCat) {
            if (sample.image) {
                imageHtml = '<div class="wasgo-terminal-image"><img src="' + sample.image + '" /><span>📎 Category AI Vision Context Attached</span></div>';
            } else {
                imageHtml = '<div class="wasgo-terminal-image warning"><span class="dashicons dashicons-warning"></span><span>⚠️ No Category Image Found - Vision Analysis will be skipped</span></div>';
            }
        } else {
            if (sample.image) {
                imageHtml = '<div class="wasgo-terminal-image"><img src="' + sample.image + '" /><span>📎 AI Vision Context Attached</span></div>';
            } else if (sample.req_img) {
                imageHtml = '<div class="wasgo-terminal-image warning"><span class="dashicons dashicons-warning"></span><span>⚠️ No Image Found - Vision Analysis will be skipped</span></div>';
            }
        }

        // 1. Vision Status Logic
        let visionStatus = '';
        if (isCat) {
            visionStatus = sample.image ? 'ENABLED (Category Image)' : 'Enabled (No Category Image found)';
        } else if (sample.req_img) {
            if (sample.image) {
                visionStatus = 'ENABLED';
            } else {
                visionStatus = 'Enabled (No Featured Image found for this product)';
            }
        } else {
            visionStatus = 'DISABLED (Image Required setting is OFF)';
        }

        finalPrompt += '### SYSTEM CONTEXT:\n';
        finalPrompt += 'Vision Analysis: ' + visionStatus + '\n';
        finalPrompt += 'Target Language: ' + sample.lang + '\n\n';

        if (isCat) {
            if (sample.is_empty) {
                finalPrompt += '### CATEGORY IDENTITY:\n';
                finalPrompt += 'No WooCommerce product categories found in this store.\n\n';
            } else {
                let catName = sample.title || '[SAMPLE CATEGORY]';
                finalPrompt += '### CATEGORY IDENTITY:\n';
                finalPrompt += 'Name: ' + catName + '\n';
                finalPrompt += '\n';
            }
        } else {
            finalPrompt += '### PRODUCT IDENTITY:\n';
            finalPrompt += 'Name: ' + sample.title + '\n';
            if (sample.cats) {
                finalPrompt += 'Categories: ' + sample.cats + '\n';
            }
            if (sample.attrs) {
                finalPrompt += 'Attributes:\n' + sample.attrs + '\n';
            }
            finalPrompt += '\n';
        }

        // Add selected specs simulation
        let selectedSpecs = [];
        $('.wasgo-spec-checkbox:checked').each(function() {
            selectedSpecs.push($(this).val().replace(/_/g, ' '));
        });

        if (selectedSpecs.length > 0) {
            if (isCat) {
                finalPrompt += '### USEFUL INFORMATION:\n';
                selectedSpecs.forEach(function(spec) {
                    let specKey = spec.toLowerCase().replace(/ /g, '_');
                    let label = spec;
                    let specVal = '[SAMPLE VALUE]';

                    if (specKey === 'category_description') {
                        label = 'Category Description';
                        specVal = sample.desc || '[CATEGORY ORGANIC DESCRIPTION]';
                    } else if (specKey === 'parent_category') {
                        label = 'Parent Category';
                        specVal = sample.parent || 'None';
                    } else if (specKey === 'product_count') {
                        label = 'Total Product Count';
                        specVal = sample.count !== undefined ? sample.count : '0';
                    } else if (specKey === 'latest_products') {
                        label = 'Include Latest 3 Products';
                        specVal = sample.sample_products || '[SAMPLE PRODUCT 1, SAMPLE PRODUCT 2, SAMPLE PRODUCT 3]';
                    }
                    finalPrompt += label + ': ' + specVal + '\n';
                });
            } else {
                finalPrompt += '### USEFUL SPECS:\n';
                selectedSpecs.forEach(function(spec) {
                    finalPrompt += spec + ': [SAMPLE VALUE]\n';
                });
            }
            finalPrompt += '\n';
        }

        let typeLabels = {
            'short': 'SHORT DESCRIPTION',
            'long': 'LONG DESCRIPTION',
            'title': 'META TITLE',
            'desc': 'META DESCRIPTION',
            'cat_title': 'PRODUCT CATEGORY META TITLE',
            'cat_desc': 'PRODUCT CATEGORY META DESCRIPTION'
        };

        finalPrompt += '### INSTRUCTIONS FOR ' + (typeLabels[type] || type.toUpperCase()) + ':\n';
        finalPrompt += userPrompt;

        // Escape HTML for safety in preview
        let escapedPrompt = $('<div>').text(finalPrompt).html();
        
        // Highlight system headers for better readability
        escapedPrompt = escapedPrompt.replace(/(### [A-Z ]+:)/g, '<span class="terminal-header">$1</span>');
        escapedPrompt = escapedPrompt.replace(/(Name:|Categories:|Attributes:|Target Language:|Vision Analysis:|Organic Description:|Parent Category:|Total Product Count:|Include Latest 3 Products:|Category Description:)/g, '<span class="terminal-key">$1</span>');

        $preview.html(imageHtml + '<pre>' + escapedPrompt + '</pre>');
    }

    $(document).on('input', '#wasgo-prompt-input', updatePromptPreview);
    $(document).on('change', '.wasgo-spec-checkbox', updatePromptPreview);

    // -------------------------------------------------------------
    // Live Search for Preview
    // -------------------------------------------------------------
    let searchTimer;
    $(document).on('input', '#wasgo-preview-search', function() {
        clearTimeout(searchTimer);
        let $input = $(this);
        let term = $input.val();
        let $results = $('#wasgo-preview-search-results');
        let type = $('#wasgo-prompt-input').data('type');
        let searchType = (type === 'cat_title' || type === 'cat_desc') ? 'category' : 'product';

        if (term.length < 3) {
            $results.hide();
            return;
        }

        searchTimer = setTimeout(function() {
            $.ajax({
                url: wasgo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wasgo_content_search_products',
                    nonce: wasgo_ajax.nonce,
                    term: term,
                    search_type: searchType
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '';
                        response.data.forEach(function(item) {
                            html += '<div class="wasgo-search-item" data-id="' + item.id + '">' + item.title + ' (#' + item.id + ')</div>';
                        });
                        $results.html(html).show();
                    } else {
                        $results.html('<div style="padding:10px; color:#94a3b8;">No results.</div>').show();
                    }
                }
            });
        }, 300);
    });

    $(document).on('click', '.wasgo-search-item', function() {
        let pid = $(this).data('id');
        let $results = $('#wasgo-preview-search-results');
        let $input = $('#wasgo-preview-search');
        let type = $('#wasgo-prompt-input').data('type');
        let searchType = (type === 'cat_title' || type === 'cat_desc') ? 'category' : 'product';

        $results.hide();
        $input.val($(this).text()).attr('disabled', 'disabled');

        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_content_get_preview_data',
                nonce: wasgo_ajax.nonce,
                pid: pid,
                search_type: searchType
            },
            success: function(response) {
                if (response.success) {
                    wasgo_ajax.sample = response.data;
                    updatePromptPreview();
                }
                $input.removeAttr('disabled');
            }
        });
    });

    // Close dropdown on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.wasgo-preview-search-container').length) {
            $('#wasgo-preview-search-results').hide();
        }
    });

    // -------------------------------------------------------------
    // Success Logs Content Toggle
    // -------------------------------------------------------------
    $(document).on('click', '.wasgo-toggle-log-content', function(e) {
        e.preventDefault();
        let targetId = $(this).data('target');
        let $target = $('#' + targetId);
        let $icon = $(this).find('.dashicons');

        if ($target.is(':visible')) {
            $target.hide();
            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-media-document');
            $(this).contents().last()[0].textContent = ' Preview Content';
        } else {
            $target.show();
            $icon.removeClass('dashicons-media-document').addClass('dashicons-arrow-up-alt2');
            $(this).contents().last()[0].textContent = ' Hide Content';
        }
    });

    // -------------------------------------------------------------
    // Content Review Studio (Edit/Regenerate)
    // -------------------------------------------------------------
    
    // Toggle Edit Mode
    $(document).on('click', '.wasgo-review-edit-btn', function() {
        let $row = $(this).closest('tr');
        $row.find('.wasgo-review-static-content').hide();
        $row.find('.wasgo-review-edit-content').show().focus();
        $(this).hide();
        $row.find('.wasgo-review-save-btn').show();
    });

    // Save Manual Edit
    $(document).on('click', '.wasgo-review-save-btn', function() {
        let $btn = $(this);
        let pid = $btn.data('pid');
        let type = $btn.data('type');
        let itemType = $btn.data('item-type') || 'product';
        let $row = $btn.closest('tr');
        let content = $row.find('.wasgo-review-edit-content').val();

        $btn.attr('disabled', 'disabled').addClass('updating');

        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_content_save_review_edit',
                nonce: wasgo_ajax.nonce,
                pid: pid,
                type: type,
                content: content,
                item_type: itemType
            },
            success: function(response) {
                if (response.success) {
                    $row.find('.wasgo-review-static-content').html(content.replace(/\n/g, '<br>')).show();
                    $row.find('.wasgo-review-edit-content').hide();
                    $btn.hide().removeAttr('disabled').removeClass('updating');
                    $row.find('.wasgo-review-edit-btn').show();
                } else {
                    alert('Error saving edit: ' + response.data);
                    $btn.removeAttr('disabled').removeClass('updating');
                }
            }
        });
    });

    // Regenerate Content
    $(document).on('click', '.wasgo-review-regenerate-btn', function() {
        let $btn = $(this);
        let pid = $btn.data('pid');
        let type = $btn.data('type');
        let itemType = $btn.data('item-type') || 'product';
        let $row = $btn.closest('tr');

        if (!confirm('Are you sure you want to regenerate this content? The current version will be replaced.')) return;

        $btn.attr('disabled', 'disabled').addClass('updating').find('.dashicons').addClass('spin');

        $.ajax({
            url: wasgo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wasgo_content_regenerate_review',
                nonce: wasgo_ajax.nonce,
                pid: pid,
                type: type,
                item_type: itemType
            },
            success: function(response) {
                if (response.success) {
                    // Update content
                    $row.find('.wasgo-review-static-content').html(response.data.content.replace(/\n/g, '<br>'));
                    $row.find('.wasgo-review-edit-content').val(response.data.content);
                    
                    // Update score
                    let $badge = $row.find('.wasgo-review-score-badge');
                    $badge.text(response.data.score_pct + '%').css({
                        'background': response.data.score_color + '10',
                        'color': response.data.score_color,
                        'border-color': response.data.score_color + '30'
                    });

                    // Update issues
                    let $issuesList = $row.find('.wasgo-review-issues-container ul');
                    $issuesList.empty();
                    if (response.data.issues.length > 0) {
                        response.data.issues.forEach(function(issue) {
                            $issuesList.append('<li style="margin-bottom: 6px; display: flex; gap: 6px; align-items: flex-start;"><span class="dashicons dashicons-warning" style="font-size: 14px; width:14px; height:14px; margin-top: 2px;"></span><span>' + issue + '</span></li>');
                        });
                    } else {
                        $issuesList.append('<li style="display: flex; gap: 6px; align-items: flex-start;"><span class="dashicons dashicons-warning" style="font-size: 14px; width:14px; height:14px; margin-top: 2px;"></span><span>Low confidence score</span></li>');
                    }

                    $btn.removeAttr('disabled').removeClass('updating').find('.dashicons').removeClass('spin');
                } else {
                    alert('Regeneration failed: ' + response.data);
                    $btn.removeAttr('disabled').removeClass('updating').find('.dashicons').removeClass('spin');
                }
            }
        });
    });

    // Initial run
    updatePromptPreview();

});
