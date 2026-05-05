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
            'Content Generation',
            'Content Generation',
            'manage_options',
            'wasgo-content-generation',
            [ $this, 'render_content_generation_page' ]
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
            
            $sample_product_id = 0;
            $sample_data = [
                'title' => 'Sample Product Name',
                'cats'  => 'Electronics > Laptops',
                'attrs' => "Color: Silver\nRAM: 16GB",
                'lang'  => WASGO_Settings::get_content_language(),
                'image' => '',
                'req_img' => WASGO_Settings::is_image_required()
            ];

            $latest = get_posts(['post_type' => 'product', 'posts_per_page' => 1, 'post_status' => 'publish', 'fields' => 'ids']);
            if ( ! empty( $latest ) ) {
                $pid = $latest[0];
                $sample_data['title'] = get_the_title( $pid );
                $sample_data['cats']  = WASGO_Content_Utility::get_product_categories_string( $pid );
                $sample_data['attrs'] = WASGO_Content_Utility::get_product_attributes_string( $pid );
                $img_id = get_post_thumbnail_id( $pid );
                if ( $img_id ) {
                    $sample_data['image'] = wp_get_attachment_thumb_url( $img_id );
                }
            }

            wp_localize_script( 'wasgo-admin-js', 'wasgo_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wasgo_ajax_nonce' ),
                'sample'   => $sample_data
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
                <div class="wasgo-home-card">
                    <h2>Content Generation</h2>
                    <p>Generate SEO-optimized product descriptions and metadata using GPT-4o and Claude 3.5 Sonnet.</p>
                    <a href="<?php echo admin_url( 'admin.php?page=wasgo-content-generation' ); ?>" class="button button-primary">Manage Content</a>
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
                                <p class="description">Enter your Gemini 3.1 Flash API Key for image processing.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="wasgo_openai_api_key">OpenAI API Key (GPT-4o)</label></th>
                            <td>
                                <input type="password" name="wasgo_openai_api_key" id="wasgo_openai_api_key" value="<?php echo esc_attr( WASGO_Settings::get_openai_api_key() ); ?>">
                                <p class="description">Enter your OpenAI API Key for content generation.</p>
                            </td>
                        </tr>
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
                <tr>
                    <th><label for="wasgo_enhance_gallery">Enhance Product Gallery</label></th>
                    <td>
                        <input type="checkbox" name="wasgo_enhance_gallery" id="wasgo_enhance_gallery" value="1" <?php checked( 1, get_option( 'wasgo_enhance_gallery', 0 ) ); ?> />
                        <span class="description">If enabled, every image in the product gallery will be automatically enhanced as separate background tasks.</span>
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
                <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 15px;">
                    <p style="margin: 0 0 10px 0; font-weight: 600; color: #1e293b;">Processing Mode:</p>
                    <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                        <input type="radio" name="wasgo_force_all" value="0" checked> 
                        <strong style="color: #4f46e5;">Smart Process</strong> 
                        <span style="color: #64748b;">(Skip products already enhanced by AI)</span>
                    </label>
                    <label style="display: block; cursor: pointer;">
                        <input type="radio" name="wasgo_force_all" value="1"> 
                        <strong style="color: #ef4444;">Full Regeneration</strong> 
                        <span style="color: #64748b;">(Reprocess everything, even if done previously)</span>
                    </label>
                </div>

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
                <h2>Bulk Gallery Enhancement</h2>
                <p>Enhance the galleries of all products in the background. Does not affect featured images.</p>
                <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 15px;">
                    <p style="margin: 0 0 10px 0; font-weight: 600; color: #1e293b;">Processing Mode:</p>
                    <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                        <input type="radio" name="wasgo_gallery_force" value="0" checked> 
                        <strong style="color: #4f46e5;">Smart Process</strong> 
                        <span style="color: #64748b;">(Skip galleries already enhanced by AI)</span>
                    </label>
                    <label style="display: block; cursor: pointer;">
                        <input type="radio" name="wasgo_gallery_force" value="1"> 
                        <strong style="color: #ef4444;">Full Regeneration</strong> 
                        <span style="color: #64748b;">(Reprocess everything, even if done previously)</span>
                    </label>
                </div>

                <div class="wasgo-buttons" style="margin-top: 25px;">
                    <button type="button" id="wasgo-gal-btn-start" class="button button-primary button-large">Start / Resume Gallery Enhancement</button>
                    <button type="button" id="wasgo-gal-btn-stop" class="button button-secondary button-large" disabled>Pause</button>
                    <button type="button" id="wasgo-gal-btn-restart" class="button button-secondary button-large button-danger">Restart Gallery (From Scratch)</button>
                </div>

                <div id="wasgo-gal-progress-container" style="margin-top: 30px; display: none;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <strong id="wasgo-gal-status-text" style="color: #4f46e5;">Calculating...</strong>
                        <strong id="wasgo-gal-progress-text" style="color: #475569;">0 / 0</strong>
                    </div>
                    <div class="wasgo-progress-bar-bg">
                        <div id="wasgo-gal-progress-bar-fill" style="width: 0%; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                    </div>
                </div>
                <div id="wasgo-gal-bulk-notice" style="margin-top:20px;"></div>
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
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_wasgo_log_type',
                    'value'   => 'image',
                    'compare' => '='
                ],
                [
                    'key'     => '_wasgo_log_type',
                    'compare' => 'NOT EXISTS'
                ]
            ]
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

    public function render_content_generation_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'prompt';
        ?>
        <div class="wrap wasgo-premium-wrap">
            <div class="wasgo-header">
                <h1>Content Generation</h1>
                <p class="wasgo-header-subtitle">Intelligent AI copywriting with multi-model validation</p>
            </div>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=wasgo-content-generation&tab=prompt" class="nav-tab <?php echo $active_tab == 'prompt' ? 'nav-tab-active' : ''; ?>">AI Prompt</a>
                <a href="?page=wasgo-content-generation&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Local Settings</a>
                <a href="?page=wasgo-content-generation&tab=bulk" class="nav-tab <?php echo $active_tab == 'bulk' ? 'nav-tab-active' : ''; ?>">Bulk Actions</a>
                <a href="?page=wasgo-content-generation&tab=review" class="nav-tab <?php echo $active_tab == 'review' ? 'nav-tab-active' : ''; ?>">Review Required</a>
                <a href="?page=wasgo-content-generation&tab=success_logs" class="nav-tab <?php echo $active_tab == 'success_logs' ? 'nav-tab-active' : ''; ?>">Success Logs</a>
                <a href="?page=wasgo-content-generation&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">Failure Logs</a>
            </h2>

            <div class="wasgo-tab-content">
                <?php
                if ( $active_tab == 'prompt' ) {
                    $this->render_content_tab_prompt();
                } elseif ( $active_tab == 'settings' ) {
                    $this->render_content_tab_settings();
                } elseif ( $active_tab == 'bulk' ) {
                    $this->render_content_tab_bulk();
                } elseif ( $active_tab == 'review' ) {
                    $this->render_content_tab_review();
                } elseif ( $active_tab == 'success_logs' ) {
                    $this->render_tab_success_logs();
                } elseif ( $active_tab == 'logs' ) {
                    $this->render_content_tab_logs();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_content_tab_prompt() {
        $sub_tab = isset( $_GET['sub_tab'] ) ? sanitize_text_field( $_GET['sub_tab'] ) : 'short';
        $specs = WASGO_Content_Utility::get_available_specs();
        
        $tabs = [
            'short' => 'Short Description',
            'long'  => 'Long Description',
            'title' => 'Meta Title',
            'desc'  => 'Meta Description'
        ];
        ?>
        <div class="wasgo-sub-tab-wrapper">
            <?php foreach ( $tabs as $key => $label ) : ?>
                <a href="?page=wasgo-content-generation&tab=prompt&sub_tab=<?php echo $key; ?>" 
                   class="wasgo-sub-tab <?php echo $sub_tab == $key ? 'wasgo-sub-tab-active' : ''; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php
        $settings_map = [
            'short' => [ 'group' => 'wasgo_content_short_group', 'prompt' => 'wasgo_content_short_prompt', 'specs' => 'wasgo_content_short_specs' ],
            'long'  => [ 'group' => 'wasgo_content_long_group', 'prompt' => 'wasgo_content_long_prompt', 'specs' => 'wasgo_content_long_specs' ],
            'title' => [ 'group' => 'wasgo_content_title_group', 'prompt' => 'wasgo_content_title_prompt', 'specs' => 'wasgo_content_title_specs' ],
            'desc'  => [ 'group' => 'wasgo_content_desc_group', 'prompt' => 'wasgo_content_desc_prompt', 'specs' => 'wasgo_content_desc_specs' ],
        ];

        $current = $settings_map[$sub_tab];
        $saved_specs = get_option( $current['specs'], [] );
        if ( ! is_array( $saved_specs ) ) $saved_specs = [];
        ?>

        <form method="post" action="options.php">
            <?php
            settings_fields( $current['group'] );
            do_settings_sections( $current['group'] );
            ?>
            <div class="wasgo-prompt-split-container">
                <!-- Editor Side -->
                <div class="wasgo-prompt-editor-side">
                    <div class="wasgo-admin-card" style="padding: 30px;">
                        <h3 style="margin-top: 0; margin-bottom: 20px; color: #1e293b;">
                            <?php echo $tabs[$sub_tab]; ?> AI Prompt
                        </h3>
                        
                        <p class="description" style="margin-bottom:15px;">
                            Define the custom instructions for this content type.
                        </p>

                        <textarea id="wasgo-prompt-input" name="<?php echo $current['prompt']; ?>" rows="10" 
                                  placeholder="e.g. Write a catchy and professional <?php echo strtolower($tabs[$sub_tab]); ?>..."
                                  data-type="<?php echo $sub_tab; ?>"><?php echo esc_textarea( get_option( $current['prompt'] ) ); ?></textarea>

                        <div class="wasgo-specs-title">
                            <span class="dashicons dashicons-list-view"></span>
                            Useful Specs to Include
                        </div>
                        <p class="description" style="margin-bottom:15px;">
                            Select which product details should be dynamically attached.
                        </p>

                        <div class="wasgo-specs-container">
                            <?php foreach ( $specs as $spec ) : ?>
                                <label class="wasgo-spec-item">
                                    <input type="checkbox" name="<?php echo $current['specs']; ?>[]" 
                                           class="wasgo-spec-checkbox"
                                           value="<?php echo esc_attr( $spec ); ?>" 
                                           <?php checked( in_array( $spec, $saved_specs ) ); ?>>
                                    <?php echo esc_html( str_replace( '_', ' ', $spec ) ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 30px;">
                            <?php submit_button( 'Save ' . $tabs[$sub_tab] . ' Settings' ); ?>
                        </div>
                    </div>
                </div>

                <!-- Preview Side (Sticky) -->
                <div class="wasgo-prompt-preview-side">
                    <div class="wasgo-preview-card">
                        <div class="wasgo-preview-header">
                            <span class="dashicons dashicons-visibility"></span>
                            Live AI Perspective
                        </div>
                        <div class="wasgo-preview-terminal" id="wasgo-prompt-live-preview">
                            <!-- JS will populate this -->
                            <div class="wasgo-terminal-loading">Waiting for input...</div>
                        </div>
                        <div class="wasgo-preview-footer">
                            This is exactly what is sent to <strong>GPT-4o</strong> for a sample product.
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php
    }

    private function render_content_tab_settings() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'wasgo_content_settings_group' );
            do_settings_sections( 'wasgo_content_settings_group' );
            ?>
            <div class="wasgo-admin-card">
                <h3 style="margin-top: 0; margin-bottom: 20px;">Generation Behavior</h3>
                <table class="form-table">
                    <tr>
                        <th><label>Disable Generation For:</label></th>
                        <td>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="wasgo_content_disable_short" value="1" <?php checked( 1, get_option( 'wasgo_content_disable_short', 0 ) ); ?> />
                                Short Description
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="wasgo_content_disable_long" value="1" <?php checked( 1, get_option( 'wasgo_content_disable_long', 0 ) ); ?> />
                                Long Description
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="wasgo_content_disable_title" value="1" <?php checked( 1, get_option( 'wasgo_content_disable_title', 0 ) ); ?> />
                                Meta Title
                            </label>
                            <label style="display: block;">
                                <input type="checkbox" name="wasgo_content_disable_desc" value="1" <?php checked( 1, get_option( 'wasgo_content_disable_desc', 0 ) ); ?> />
                                Meta Description
                            </label>
                            <p class="description">Disabled fields will not be generated during single product edits or auto-processing. <strong>Note:</strong> Bulk Actions will ignore these settings.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wasgo_content_image_required">Image Requirement</label></th>
                        <td>
                            <input type="checkbox" name="wasgo_content_image_required" id="wasgo_content_image_required" value="1" <?php checked( 1, get_option( 'wasgo_content_image_required', 0 ) ); ?> />
                            <span class="description">Only generate content for products that have a featured image.</span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wasgo_content_out_of_stock">Exclude Out Of Stock</label></th>
                        <td>
                            <input type="checkbox" name="wasgo_content_out_of_stock" id="wasgo_content_out_of_stock" value="1" <?php checked( 1, get_option( 'wasgo_content_out_of_stock', 0 ) ); ?> />
                            <span class="description">Skip products that are out of stock.</span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wasgo_content_auto_process">Auto-Process New Products</label></th>
                        <td>
                            <input type="checkbox" name="wasgo_content_auto_process" id="wasgo_content_auto_process" value="1" <?php checked( 1, get_option( 'wasgo_content_auto_process', 0 ) ); ?> />
                            <span class="description">Automatically generate content for new products upon creation/publish.</span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wasgo_content_language">Target Language</label></th>
                        <td>
                            <select name="wasgo_content_language" id="wasgo_content_language">
                                <?php 
                                $langs = [
                                    'English', 'French', 'Arabic', 'Spanish', 'German', 'Italian', 'Portuguese', 'Dutch', 'Russian', 'Chinese (Simplified)',
                                    'Japanese', 'Korean', 'Turkish', 'Polish', 'Swedish', 'Norwegian', 'Danish', 'Finnish', 'Greek', 'Czech',
                                    'Hungarian', 'Romanian', 'Bulgarian', 'Hindi', 'Bengali', 'Thai', 'Vietnamese', 'Indonesian', 'Malay', 'Ukrainian'
                                ];
                                $current_lang = WASGO_Settings::get_content_language();
                                foreach ( $langs as $lang ) : ?>
                                    <option value="<?php echo esc_attr( $lang ); ?>" <?php selected( $lang, $current_lang ); ?>><?php echo esc_html( $lang ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select the language for all AI-generated content.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wasgo_content_include_categories">Context Enrichment</label></th>
                        <td>
                            <input type="checkbox" name="wasgo_content_include_categories" id="wasgo_content_include_categories" value="1" <?php checked( 1, get_option( 'wasgo_content_include_categories', 0 ) ); ?> />
                            <span class="description">Include product categories in the AI context for better accuracy.</span>
                        </td>
                    </tr>
                </table>
                <div style="margin-top: 20px;">
                    <?php submit_button( 'Save Content Settings' ); ?>
                </div>
            </div>
        </form>
        <?php
    }

    private function render_content_tab_bulk() {
        ?>
        <div class="wasgo-bulk-wrapper">
            <div class="wasgo-admin-card">
                <h2>Bulk Content Generation</h2>
                <p>Generate SEO-optimized descriptions and metadata for your product catalog in the background.</p>
                
                <div style="background: #f8fafc; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; margin-top: 20px;">
                    <p style="margin: 0 0 15px 0; font-weight: 700; color: #1e293b;">Select Content Types to Generate:</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="wasgo_content_bulk_types[]" value="short" checked> Short Description
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="wasgo_content_bulk_types[]" value="long" checked> Long Description
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="wasgo_content_bulk_types[]" value="title" checked> Meta Title
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="wasgo_content_bulk_types[]" value="desc" checked> Meta Description
                        </label>
                    </div>

                    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">

                    <p style="margin: 0 0 10px 0; font-weight: 700; color: #1e293b;">Processing Mode:</p>
                    <label style="display: block; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="wasgo_content_bulk_mode" value="smart" checked> 
                        <strong style="color: #4f46e5;">Smart Process</strong> 
                        <span style="color: #64748b;">(Only generate missing fields)</span>
                    </label>
                    <label style="display: block; cursor: pointer;">
                        <input type="radio" name="wasgo_content_bulk_mode" value="full"> 
                        <strong style="color: #ef4444;">Full Generation</strong> 
                        <span style="color: #64748b;">(Overwrite all selected fields)</span>
                    </label>
                </div>

                <div class="wasgo-buttons" style="margin-top: 30px;">
                    <button type="button" id="wasgo-content-btn-start" class="button button-primary button-large">Start / Resume Generation</button>
                    <button type="button" id="wasgo-content-btn-stop" class="button button-secondary button-large" disabled>Pause</button>
                    <button type="button" id="wasgo-content-btn-restart" class="button button-secondary button-large button-danger">Restart From Scratch</button>
                </div>

                <div id="wasgo-content-progress-container" style="margin-top: 35px; display: none;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <strong id="wasgo-content-status-text" style="color: #4f46e5;">Calculating...</strong>
                        <strong id="wasgo-content-progress-text" style="color: #475569;">0 / 0</strong>
                    </div>
                    <div class="wasgo-progress-bar-bg">
                        <div id="wasgo-content-progress-bar-fill" style="width: 0%; height: 100%; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                    </div>
                </div>
                <div id="wasgo-content-bulk-notice" style="margin-top:20px;"></div>
            </div>
        </div>
        <?php
    }

    private function render_content_tab_review() {
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 20,
            'meta_query'     => [
                [
                    'key'   => '_wasgo_needs_review',
                    'value' => '1'
                ]
            ]
        ];
        $query = new WP_Query( $args );
        ?>
        <div class="wasgo-admin-card" style="padding:0; overflow:hidden;">
            <table class="wp-list-table widefat fixed striped">
                        <thead>
                    <tr>
                        <th style="width: 20%; padding-left: 20px;">Product Info</th>
                        <th style="width: 35%;">Generated Content Preview</th>
                        <th style="width: 15%;">Risk / Reason</th>
                        <th style="width: 15%; text-align: center;">Validation Score</th>
                        <th style="width: 15%; text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="wasgo-content-review-body">
                    <?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); 
                        $pid = get_the_ID();
                        $review_data = get_post_meta( $pid, '_wasgo_content_review', true );
                        if ( ! is_array( $review_data ) ) continue;

                        foreach ( $review_data as $type => $data ) :
                            $type_label = str_replace( ['short', 'long', 'title', 'desc'], ['Short Desc', 'Long Desc', 'Meta Title', 'Meta Desc'], $type );
                            $score = isset( $data['score'] ) ? floatval( $data['score'] ) : 0;
                            $score_pct = round( $score * 100 );
                            
                            // Determine score color
                            $score_color = '#ef4444'; // Red
                            if ( $score >= 0.8 ) $score_color = '#22c55e'; // Green
                            elseif ( $score >= 0.5 ) $score_color = '#f59e0b'; // Amber
                        ?>
                        <tr id="review-row-<?php echo $pid; ?>-<?php echo $type; ?>">
                            <td style="padding-left: 20px;">
                                <strong><a href="<?php echo get_edit_post_link( $pid ); ?>" target="_blank"><?php the_title(); ?></a></strong>
                                <div style="font-size: 11px; color: #64748b; margin-top: 5px;">
                                    Type: <span style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;"><?php echo $type_label; ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="max-height: 100px; overflow-y: auto; font-size: 13px; line-height: 1.4; color: #475569; border: 1px solid #f1f5f9; padding: 10px; border-radius: 6px; background: #fff;">
                                    <?php echo nl2br( esc_html( $data['content'] ) ); ?>
                                </div>
                            </td>
                            <td>
                                <ul style="margin:0; padding:0; list-style:none; font-size: 12px; color: #ef4444;">
                                    <?php if ( ! empty( $data['issues'] ) ) : foreach ( $data['issues'] as $issue ) : ?>
                                        <li style="margin-bottom: 4px;">• <?php echo esc_html( $issue ); ?></li>
                                    <?php endforeach; else: ?>
                                        <li>• Low confidence score</li>
                                    <?php endif; ?>
                                </ul>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <div style="display:inline-block; padding: 6px 12px; border-radius: 20px; background: <?php echo $score_color; ?>10; color: <?php echo $score_color; ?>; font-weight: bold; border: 1px solid <?php echo $score_color; ?>30;">
                                    <?php echo $score_pct; ?>%
                                </div>
                            </td>
                            <td style="text-align: right; padding-right: 20px; vertical-align: middle;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <button type="button" class="wasgo-review-action button button-primary button-small" 
                                            data-action="approve" data-pid="<?php echo $pid; ?>" data-type="<?php echo $type; ?>">Approve</button>
                                    <button type="button" class="wasgo-review-action button button-secondary button-small" 
                                            data-action="discard" data-pid="<?php echo $pid; ?>" data-type="<?php echo $type; ?>" style="color: #ef4444; border-color: #ef4444;">Discard</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endwhile; wp_reset_postdata(); else : ?>
                        <tr>
                            <td colspan="4" style="padding: 40px; text-align: center; color: #64748b;">
                                <span class="dashicons dashicons-shield-check" style="font-size: 40px; width: 40px; height: 40px; display: block; margin: 0 auto 15px;"></span>
                                No products currently require manual review.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    private function render_tab_success_logs() {
        $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $args = [
            'post_type'      => 'wasgo_log',
            'posts_per_page' => 20,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_wasgo_log_type',
                    'value' => 'content'
                ],
                [
                    'key'   => '_wasgo_log_nature',
                    'value' => 'success'
                ]
            ]
        ];
        $query = new WP_Query( $args );
        ?>
        <div class="wasgo-admin-card" style="padding:0; overflow:hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fff;">
                <h3 style="margin:0; font-size: 16px; color: #1e293b;">Successfully Generated Products</h3>
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field('wasgo_clear_logs', 'wasgo_logs_nonce'); ?>
                    <button type="submit" name="wasgo_clear_success_logs" class="button button-secondary" style="color: #64748b; border-color: #e2e8f0;">Clear Success History</button>
                </form>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 18%; padding-left: 20px;">Date / Time</th>
                        <th style="width: 35%;">Product</th>
                        <th style="width: 27%;">Update Detail</th>
                        <th style="width: 20%; text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); 
                        $pid = get_post_meta( get_the_ID(), 'success_product_id', true );
                        $msg = get_post_meta( get_the_ID(), 'success_message', true );
                        $product_url = get_permalink( $pid );
                        $edit_url = get_edit_post_link( $pid );
                    ?>
                    <tr>
                        <td style="padding-left: 20px; color: #64748b; font-size: 13px;"><?php echo get_the_date( 'M j, H:i' ); ?></td>
                        <td>
                            <strong style="color: #1e293b;">#<?php echo esc_html( $pid ); ?> - <?php the_title(); ?></strong>
                        </td>
                        <td>
                            <span style="display:inline-block; padding: 2px 8px; border-radius: 4px; background: #dcfce7; color: #166534; font-size: 11px; font-weight: 600;">
                                <span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px; line-height: 1.4;"></span>
                                <?php echo esc_html( $msg ); ?>
                            </span>
                        </td>
                        <td style="text-align: right; padding-right: 20px;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <a href="<?php echo esc_url( $product_url ); ?>" target="_blank" class="button button-small" title="View on Site">
                                    <span class="dashicons dashicons-visibility" style="margin-top: 4px;"></span> View
                                </a>
                                <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" class="button button-small" title="Edit in Admin">
                                    <span class="dashicons dashicons-edit" style="margin-top: 4px;"></span> Edit
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; wp_reset_postdata(); else : ?>
                    <tr>
                        <td colspan="4" style="padding: 60px; text-align: center; color: #64748b;">
                            <span class="dashicons dashicons-clock" style="font-size: 40px; width: 40px; height: 40px; display: block; margin: 0 auto 15px; color: #cbd5e1;"></span>
                            No successful updates recorded yet. Start a generation to see results here.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ( $query->max_num_pages > 1 ) : ?>
                <div class="tablenav bottom" style="padding: 15px;">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links( [
                            'base'      => add_query_arg( 'paged', '%#%' ),
                            'format'    => '',
                            'prev_text' => __( '&laquo;' ),
                            'next_text' => __( '&raquo;' ),
                            'total'     => $query->max_num_pages,
                            'current'   => $paged,
                        ] );
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            // Handle clearing success logs
            if ( isset($_POST['wasgo_clear_success_logs']) && check_admin_referer('wasgo_clear_logs', 'wasgo_logs_nonce') ) {
                $logs_to_clear = get_posts([
                    'post_type'      => 'wasgo_log',
                    'numberposts'    => -1,
                    'post_status'    => 'any',
                    'meta_query'     => [
                        'relation' => 'AND',
                        ['key' => '_wasgo_log_type', 'value' => 'content'],
                        ['key' => '_wasgo_log_nature', 'value' => 'success']
                    ]
                ]);
                foreach ( $logs_to_clear as $log ) {
                    wp_delete_post( $log->ID, true );
                }
                echo "<script>location.href='admin.php?page=wasgo-content-generation&tab=success_logs';</script>";
            }
            ?>
        </div>
        <?php
    }


    private function render_content_tab_logs() {
        $args = [
            'post_type'      => 'wasgo_log',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_wasgo_log_type',
                    'value' => 'content'
                ],
                [
                    'key'   => '_wasgo_log_nature',
                    'value' => 'error'
                ]
            ]
        ];
        $query = new WP_Query( $args );
        ?>
        <div class="wasgo-admin-card" style="padding:0; overflow:hidden;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%; padding-left: 20px;">Date / Time</th>
                        <th style="width: 15%;">Product ID</th>
                        <th style="width: 45%;">Error Reason</th>
                        <th style="width: 20%; text-align: right; padding-right: 20px;">Product Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); 
                        $pid = get_post_meta( get_the_ID(), 'failed_product_id', true );
                        $msg = get_post_meta( get_the_ID(), 'error_message', true );
                    ?>
                    <tr>
                        <td style="padding-left: 20px;"><?php echo get_the_date( 'Y-m-d H:i' ); ?></td>
                        <td>#<?php echo esc_html( $pid ); ?></td>
                        <td style="color: #ef4444;"><?php echo esc_html( $msg ); ?></td>
                        <td style="text-align: right; padding-right: 20px;"><?php the_title(); ?></td>
                    </tr>
                    <?php endwhile; wp_reset_postdata(); else : ?>
                    <tr>
                        <td colspan="4" style="padding: 40px; text-align: center; color: #64748b;">
                            No content generation failures logged.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
