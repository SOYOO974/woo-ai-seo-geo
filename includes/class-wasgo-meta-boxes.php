<?php
/**
 * Post Meta Boxes Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Meta_Boxes {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_product_ai_controls' ] );
        add_action( 'save_post', [ $this, 'save_ai_controls' ] );
    }

    public function add_product_ai_controls() {
        add_meta_box(
            'wasgo_ai_image_controls',
            'AI Image Controls',
            [ $this, 'render_ai_controls_meta_box' ],
            'product',
            'side',
            'high'
        );
    }

    public function render_ai_controls_meta_box( $post ) {
        $is_disabled = get_post_meta( $post->ID, '_wasgo_disable_ai_gen', true );
        $is_ai_generated = get_post_meta( $post->ID, 'prevent_ebp_image_sync', true );
        
        $backup_id = get_post_meta( $post->ID, 'original_wasgo_image_id', true );
        $legacy_backup_url = get_post_meta( $post->ID, 'original_ebp_image_url', true );
        $original_url = '';

        if ( $backup_id ) {
            $original_url = wp_get_attachment_url( $backup_id );
        } elseif ( $legacy_backup_url ) {
            $original_url = $legacy_backup_url;
        }

        wp_nonce_field( 'wasgo_ai_controls_save', 'wasgo_ai_controls_nonce' );
        ?>
        <div style="margin-bottom: 15px;">
            <label>
                <input type="checkbox" name="_wasgo_disable_ai_gen" value="1" <?php checked( $is_disabled, '1' ); ?>>
                <strong>Disable AI Image Generation</strong>
            </label>
            <p class="description">If checked, auto-generation will skip this product.</p>
        </div>

        <div style="border-top: 1px solid #eee; padding-top: 10px; margin-bottom: 10px;">
            <strong>Status: </strong>
            <?php if ( $is_ai_generated ): ?>
                <span style="color: green; font-weight: bold;">✨ AI-Enhanced</span>
            <?php else: ?>
                <span style="color: #666;">Original Image</span>
            <?php endif; ?>
        </div>

        <div style="margin-top: 10px;">
            <button type="button" id="wasgo-force-regen-btn" class="button button-large" style="width:100%;">Regenerate Image</button>
            <span id="wasgo-regen-spinner" class="spinner" style="float:none;"></span>
        </div>
        
        <?php if($original_url): ?>
            <div style="margin-top: 15px; padding: 10px; background: #f6f7f7; border: 1px solid #c3c4c7;">
                <p style="font-size:11px; margin-top:0; margin-bottom: 5px;"><strong>Backup Source:</strong></p>
                <a href="<?php echo esc_url( $original_url ); ?>" target="_blank" style="font-size:10px; color:#2271b1; word-break:break-all;"><?php echo esc_url( $original_url ); ?></a>
                <div style="margin-top: 8px;">
                    <button type="button" id="wasgo-delete-backup-btn" class="button button-small" style="color: #d63638; border-color: #d63638;">Delete Backup</button>
                    <span id="wasgo-del-bkp-spinner" class="spinner" style="float:none;"></span>
                </div>
            </div>
        <?php endif; ?>

        <script>
        jQuery(document).ready(function($) {
            $('#wasgo-force-regen-btn').on('click', function(e) {
                e.preventDefault();
                if(!confirm('Are you sure? This will replace the current image with a new AI version.')) return;
                var $btn = $(this);
                var $spinner = $('#wasgo-regen-spinner');
                $btn.attr('disabled', 'disabled');
                $spinner.addClass('is-active');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wasgo_force_regen',
                        post_id: <?php echo $post->ID; ?>,
                        nonce: '<?php echo wp_create_nonce("wasgo_ajax_nonce"); ?>'
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        $btn.removeAttr('disabled');
                        if(response.success) {
                            alert('Success! Image regenerated. Page will reload.');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        $spinner.removeClass('is-active');
                        $btn.removeAttr('disabled');
                        alert('Server error occurred.');
                    }
                });
            });

            $('#wasgo-delete-backup-btn').on('click', function(e) {
                e.preventDefault();
                if(!confirm('Delete the original backup image permanently? This cannot be undone.')) return;
                var $btn = $(this);
                var $spinner = $('#wasgo-del-bkp-spinner');
                $btn.attr('disabled', 'disabled');
                $spinner.addClass('is-active');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wasgo_delete_single_backup',
                        post_id: <?php echo $post->ID; ?>,
                        nonce: '<?php echo wp_create_nonce("wasgo_ajax_nonce"); ?>'
                    },
                    success: function(response) {
                        if(response.success) {
                            alert('Backup deleted!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                            $spinner.removeClass('is-active');
                            $btn.removeAttr('disabled');
                        }
                    },
                    error: function() {
                        alert('Server error occurred.');
                        $spinner.removeClass('is-active');
                        $btn.removeAttr('disabled');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function save_ai_controls( $post_id ) {
        if ( ! isset( $_POST['wasgo_ai_controls_nonce'] ) || ! wp_verify_nonce( $_POST['wasgo_ai_controls_nonce'], 'wasgo_ai_controls_save' ) ) {
            return;
        }

        if ( isset( $_POST['_wasgo_disable_ai_gen'] ) ) {
            update_post_meta( $post_id, '_wasgo_disable_ai_gen', '1' );
        } else {
            delete_post_meta( $post_id, '_wasgo_disable_ai_gen' );
        }
    }
}
