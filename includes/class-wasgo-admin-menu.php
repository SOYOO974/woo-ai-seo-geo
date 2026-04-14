<?php
/**
 * Admin Menu Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Admin_Menu {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function register_menus() {
        // Main page
        add_menu_page(
            'WooCommerce AI SEO & GEO',
            'WooCommerce AI SEO',
            'manage_options',
            'wasgo-main',
            [ $this, 'render_main_page' ],
            'dashicons-superhero',
            6
        );

        // Subpages
        add_submenu_page(
            'wasgo-main',
            'Enhance Product Images',
            'Enhance Images',
            'manage_options',
            'wasgo-enhance-images',
            [ $this, 'render_enhance_images_page' ]
        );

        add_submenu_page(
            'wasgo-main',
            'Settings',
            'Settings',
            'manage_options',
            'wasgo-settings',
            [ $this, 'render_settings_page' ]
        );
        
        // Remove duplicate submenu that WP creates from the main menu slug
        remove_submenu_page( 'wasgo-main', 'wasgo-main' );
    }

    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'wasgo-' ) !== false ) {
            wp_enqueue_style( 'wasgo-admin-css', WASGO_PLUGIN_URL . 'assets/css/admin.css', [], WASGO_VERSION );
            wp_enqueue_script( 'wasgo-admin-js', WASGO_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], WASGO_VERSION, true );
            
            wp_localize_script( 'wasgo-admin-js', 'wasgo_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wasgo_ajax_nonce' )
            ] );
        }
    }

    public function render_main_page() {
        ?>
        <div class="wrap wasgo-premium-wrap">
            <div class="wasgo-header">
                <h1>WooCommerce AI SEO & GEO</h1>
                <p class="wasgo-header-subtitle">Intelligent asset management powered by Gemini AI Vision</p>
            </div>
            <div class="wasgo-cards">
                <div class="wasgo-home-card">
                    <h2>Enhance Product Images</h2>
                    <p>Use Gemini AI to regenerate and enhance your WooCommerce product images instantly.</p>
                    <a href="<?php echo admin_url( 'admin.php?page=wasgo-enhance-images' ); ?>" class="button button-primary">Manage Images</a>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap wasgo-premium-wrap">
            <div class="wasgo-header">
                <h1>Settings</h1>
                <p class="wasgo-header-subtitle">Configure your global AI integration parameters</p>
            </div>
            <div class="wasgo-admin-card">
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'wasgo_api_group' );
                    do_settings_sections( 'wasgo_api_group' );
                    ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="wasgo_gemini_api_key">Gemini API Key</label></th>
                            <td>
                                <input type="password" name="wasgo_gemini_api_key" id="wasgo_gemini_api_key" value="<?php echo esc_attr( WASGO_Settings::get_api_key() ); ?>">
                                <p class="description">Enter your Gemini 3.1 Flash API Key here.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Save API Settings' ); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_enhance_images_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'prompt';
        ?>
        <div class="wrap wasgo-premium-wrap">
            <div class="wasgo-header">
                <h1>Enhance Product Images</h1>
                <p class="wasgo-header-subtitle">Bulk generation, recovery, and AI prompt configuration</p>
            </div>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=wasgo-enhance-images&tab=prompt" class="nav-tab <?php echo $active_tab == 'prompt' ? 'nav-tab-active' : ''; ?>">AI Prompt</a>
                <a href="?page=wasgo-enhance-images&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Local Settings</a>
                <a href="?page=wasgo-enhance-images&tab=bulk" class="nav-tab <?php echo $active_tab == 'bulk' ? 'nav-tab-active' : ''; ?>">Bulk Actions</a>
                <a href="?page=wasgo-enhance-images&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">Failure Logs</a>
            </h2>

            <div class="wasgo-tab-content">
                <?php
                if ( $active_tab == 'prompt' ) {
                    $this->render_tab_prompt();
                } elseif ( $active_tab == 'settings' ) {
                    $this->render_tab_settings();
                } elseif ( $active_tab == 'bulk' ) {
                    $this->render_tab_bulk();
                } elseif ( $active_tab == 'logs' ) {
                    $this->render_tab_logs();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_tab_prompt() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'wasgo_prompt_group' );
            do_settings_sections( 'wasgo_prompt_group' );
            ?>
            <table class="form-table">
                <tr>
                    <td>
                        <p class="description" style="margin-bottom:10px;">During the product image generation, it will automatically bind the details of product e.g title and image to it. Use <code>[PRODUCT_TITLE]</code> to insert the name.</p>
                        <textarea name="wasgo_ai_prompt" rows="10" placeholder="Make this product look cinematic... [PRODUCT_TITLE]"><?php echo esc_textarea( WASGO_Settings::get_prompt() ); ?></textarea>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save Prompt' ); ?>
        </form>
        <?php
    }

    private function render_tab_settings() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'wasgo_settings_group' );
            do_settings_sections( 'wasgo_settings_group' );
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="wasgo_delete_original">Delete Original Image</label></th>
                    <td>
                        <input type="checkbox" name="wasgo_delete_original" id="wasgo_delete_original" value="1" <?php checked( 1, get_option( 'wasgo_delete_original', 0 ) ); ?> />
                        <span class="description">Delete the original image from media library after AI image generation succeeds. (Irreversible!)</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="wasgo_auto_compress">Auto Compress</label></th>
                    <td>
                        <input type="checkbox" name="wasgo_auto_compress" id="wasgo_auto_compress" value="1" <?php checked( 1, get_option( 'wasgo_auto_compress', 0 ) ); ?> />
                        <span class="description">Auto compress images to WebP format.</span>
                    </td>
                </tr>
                <tr class="wasgo-compress-dependency" style="<?php echo WASGO_Settings::should_auto_compress() ? '' : 'display:none;'; ?>">
                    <th><label for="wasgo_image_quality">Image Quality</label></th>
                    <td>
                        <input type="number" name="wasgo_image_quality" id="wasgo_image_quality" min="1" max="100" value="<?php echo esc_attr( WASGO_Settings::get_image_quality() ); ?>" class="small-text" />
                        <span class="description">Set compression quality (1-100). Default is 85.</span>
                    </td>
                </tr>
                <tr class="wasgo-compress-dependency" style="<?php echo WASGO_Settings::should_auto_compress() ? '' : 'display:none;'; ?>">
                    <th><label for="wasgo_max_height">Maximum Height (px)</label></th>
                    <td>
                        <input type="number" name="wasgo_max_height" id="wasgo_max_height" min="100" step="50" value="<?php echo esc_attr( WASGO_Settings::get_max_height() ); ?>" class="small-text" />
                        <span class="description">Set the maximum height (in pixels) for generated images. Default is 1000px. Images larger than this will be resized while maintaining aspect ratio.</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="wasgo_auto_clear_logs">Auto Clear Old Logs</label></th>
                    <td>
                        <input type="checkbox" name="wasgo_auto_clear_logs" id="wasgo_auto_clear_logs" value="1" <?php checked( 1, get_option( 'wasgo_auto_clear_logs', 0 ) ); ?> />
                        <span class="description">Automatically delete failure logs that are older than 30 days.</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="wasgo_exclude_outofstock">Exclude Out Of Stock Products</label></th>
                    <td>
                        <input type="checkbox" name="wasgo_exclude_outofstock" id="wasgo_exclude_outofstock" value="1" <?php checked( 1, get_option( 'wasgo_exclude_outofstock', 0 ) ); ?> />
                        <span class="description">If checked, products that are strictly "Out of stock" will be completely skipped during bulk generation.</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="wasgo_auto_process_new">Auto-Process New Products</label></th>
                    <td>
                        <input type="checkbox" name="wasgo_auto_process_new" id="wasgo_auto_process_new" value="1" <?php checked( 1, get_option( 'wasgo_auto_process_new', 0 ) ); ?> />
                        <span class="description">Automatically enqueue new products to Action Scheduler for AI enhancement upon creation/publish.</span>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save Settings' ); ?>
        </form>
        <?php
    }

    private function render_tab_bulk() {
        ?>
        <div class="wasgo-bulk-wrapper">
            <div class="wasgo-admin-card">
                <h2>Bulk Image Generation</h2>
                <p>Generate AI images for all products rapidly in the background.</p>
                <p style="margin-top: 15px;">
                    <label style="font-weight: 500;">
                        <input type="checkbox" id="wasgo_force_all" value="1">
                        Force generate for all <span style="font-weight: normal; color: #64748b;">(Regenerate even if an AI image already exists)</span>
                    </label>
                </p>

                <div class="wasgo-buttons" style="margin-top: 25px;">
                    <button type="button" id="wasgo-btn-start" class="button button-primary button-large">Start / Resume</button>
                    <button type="button" id="wasgo-btn-stop" class="button button-secondary button-large" disabled>Pause</button>
                    <button type="button" id="wasgo-btn-restart" class="button button-secondary button-large button-danger">Restart (From Scratch)</button>
                </div>

                <div id="wasgo-progress-container" style="margin-top: 30px; display: none;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <strong id="wasgo-status-text" style="color: #4f46e5;">Calculating...</strong>
                        <strong id="wasgo-progress-text" style="color: #475569;">0 / 0</strong>
                    </div>
                    <div class="wasgo-progress-bar-bg">
                        <div id="wasgo-progress-bar-fill" style="width: 0%; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                    </div>
                </div>
                <div id="wasgo-bulk-notice" style="margin-top:20px;"></div>
            </div>

            <div class="wasgo-admin-card">
                <h2>Bulk Backup Deletion</h2>
                <p>Permanently delete all physical backup images attached to products. This frees up server space but prevents image restoration forever.</p>
                
                <div class="wasgo-buttons" style="margin-top: 25px;">
                    <button type="button" id="wasgo-del-btn-start" class="button button-primary button-large">Start / Resume Deletion</button>
                    <button type="button" id="wasgo-del-btn-stop" class="button button-secondary button-large" disabled>Pause Deletion</button>
                    <button type="button" id="wasgo-del-btn-restart" class="button button-secondary button-large button-danger">Restart Deletion</button>
                </div>

                <div id="wasgo-del-progress-container" style="margin-top: 30px; display: none;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <strong id="wasgo-del-status-text" style="color: #ef4444;">Calculating...</strong>
                        <strong id="wasgo-del-progress-text" style="color: #475569;">0 / 0</strong>
                    </div>
                    <div class="wasgo-progress-bar-bg">
                        <div id="wasgo-del-progress-bar-fill" style="width: 0%; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                    </div>
                </div>
                <div id="wasgo-del-bulk-notice" style="margin-top:20px;"></div>
            </div>
        </div>
        <?php
    }

    private function render_tab_logs() {
        $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $posts_per_page = 20;

        $query = new WP_Query([
            'post_type'      => 'wasgo_log',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC'
        ]);
        ?>
        <p class="description">Displays logs exclusively for products that failed image generation during bulk processing.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;">Date / Time</th>
                    <th style="width: 15%;">Product ID</th>
                    <th style="width: 30%;">Product Title</th>
                    <th style="width: 35%;">Error Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); 
                    $product_id = get_post_meta( get_the_ID(), 'failed_product_id', true );
                    $error_msg  = get_post_meta( get_the_ID(), 'error_message', true );
                ?>
                <tr>
                    <td><strong><?php echo get_the_date('Y-m-d') . ' <br>' . get_the_time('H:i:s'); ?></strong></td>
                    <td><a href="<?php echo esc_url( admin_url('post.php?post=' . $product_id . '&action=edit') ); ?>" target="_blank">#<?php echo esc_html( $product_id ); ?></a></td>
                    <td><strong><?php echo esc_html( get_the_title() ); ?></strong></td>
                    <td style="color: #d63638;"><?php echo esc_html( $error_msg ); ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="4">No errors exist. Excellent!</td>
                </tr>
                <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => __( '&laquo; Previous' ),
                    'next_text' => __( 'Next &raquo;' ),
                    'total'     => $query->max_num_pages,
                    'current'   => $paged
                ]);
                ?>
            </div>
            
            <form method="post" action="" style="float: left;">
                <?php wp_nonce_field( 'wasgo_clear_logs', 'wasgo_logs_nonce' ); ?>
                <input type="submit" name="wasgo_clear_all_logs" class="button" onclick="return confirm('Are you sure you want to clear all error logs?');" value="Clear All Logs">
            </form>
            <?php
            if ( isset($_POST['wasgo_clear_all_logs']) && check_admin_referer('wasgo_clear_logs', 'wasgo_logs_nonce') ) {
                $logs = get_posts([ 'post_type' => 'wasgo_log', 'numberposts' => -1, 'post_status' => 'any' ]);
                foreach ( $logs as $log ) {
                    wp_delete_post( $log->ID, true );
                }
                echo "<script>location.href='admin.php?page=wasgo-enhance-images&tab=logs';</script>";
            }
            ?>
        </div>
        <?php
    }
}
