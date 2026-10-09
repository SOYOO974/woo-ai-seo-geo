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
            
            $sub_tab = isset( $_GET['sub_tab'] ) ? sanitize_text_field( $_GET['sub_tab'] ) : '';
            if ( in_array( $sub_tab, [ 'cat_title', 'cat_desc' ] ) ) {
                $sample_data = [
                    'title'           => 'Sample Category Name',
                    'desc'            => 'Sample Organic Category Description',
                    'parent'          => 'Parent Category Name',
                    'count'           => 12,
                    'image'           => '',
                    'sample_products' => 'Sample Product 1, Sample Product 2, Sample Product 3',
                    'lang'            => WASGO_Settings::get_content_language(),
                    'req_img'         => false
                ];

                $terms = get_terms( [
                    'taxonomy'   => 'product_cat',
                    'number'     => 1,
                    'orderby'    => 'count',
                    'order'      => 'DESC',
                    'hide_empty' => false
                ] );

                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    $term = $terms[0];
                    $sample_data['title'] = $term->name;
                    $sample_data['desc']  = $term->description ?: 'None';
                    
                    $parent_name = 'None';
                    if ( $term->parent ) {
                        $parent_term = get_term( $term->parent, 'product_cat' );
                        if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                            $parent_name = $parent_term->name;
                        }
                    }
                    $sample_data['parent'] = $parent_name;
                    $sample_data['count']  = $term->count;

                    $img_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
                    if ( $img_id ) {
                        $sample_data['image'] = wp_get_attachment_thumb_url( $img_id );
                    }

                    $products = get_posts( [
                        'post_type'      => 'product',
                        'posts_per_page' => 3,
                        'post_status'    => 'publish',
                        'tax_query'      => [
                            [
                                'taxonomy' => 'product_cat',
                                'field'    => 'term_id',
                                'terms'    => $term->term_id
                            ]
                        ]
                    ] );

                    $product_titles = [];
                    foreach ( $products as $p ) {
                        $product_titles[] = $p->post_title;
                    }
                    $sample_data['sample_products'] = ! empty( $product_titles ) ? implode( ', ', $product_titles ) : 'No products inside this category.';
                } else {
                    $sample_data['is_empty'] = true;
                }
            } else {
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
        $active_provider  = WASGO_Settings::get_image_provider();
        $fallback_enabled = WASGO_Settings::should_fallback_to_gemini();
        ?>
        <div class="wrap wasgo-premium-wrap">
            <div class="wasgo-header">
                <h1>Settings & AI Providers</h1>
                <p class="wasgo-header-subtitle">Configure AI Vision engines (Gemini, Magnific, Higgsfield) and SEO copywriting credentials</p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'wasgo_api_group' );
                do_settings_sections( 'wasgo_api_group' );
                ?>

                <!-- CARD 1: Image Generation AI Providers -->
                <div class="wasgo-admin-card">
                    <h2 style="margin-top:0; font-size:18px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
                        <span class="dashicons dashicons-format-image" style="vertical-align:middle; margin-right:6px; color:#4f46e5;"></span>
                        Image Generation AI Engine
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="wasgo_image_provider">Active Image Provider</label></th>
                            <td>
                                <select name="wasgo_image_provider" id="wasgo_image_provider" style="min-width:320px;">
                                    <option value="gemini" <?php selected( $active_provider, 'gemini' ); ?>>Google Gemini Vision (Direct preview)</option>
                                    <option value="magnific" <?php selected( $active_provider, 'magnific' ); ?>>Magnific AI (Nano Banana Pro / imagen-nano-banana-2)</option>
                                    <option value="higgsfield" <?php selected( $active_provider, 'higgsfield' ); ?>>Higgsfield AI (Studio packshot)</option>
                                </select>
                                <p class="description">Select the primary generative AI engine used for product packshots and gallery optimization.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="wasgo_image_fallback_gemini">Automatic Gemini Fallback</label></th>
                            <td>
                                <input type="hidden" name="wasgo_image_fallback_gemini" value="0" />
                                <label>
                                    <input type="checkbox" name="wasgo_image_fallback_gemini" id="wasgo_image_fallback_gemini" value="1" <?php checked( $fallback_enabled, true ); ?> />
                                    Enable automatic fallback to Google Gemini Vision if primary provider (Magnific/Higgsfield) encounters an error, timeout, or quota exhaustion.
                                </label>
                            </td>
                        </tr>
                    </table>

                    <!-- Provider Specific Credentials Container -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-top:15px;">
                        <!-- Gemini Section -->
                        <div class="wasgo-provider-section" id="provider-sec-gemini" style="margin-bottom:20px;">
                            <h3 style="margin-top:0; font-size:15px; color:#1e293b;">
                                <span class="dashicons dashicons-google" style="vertical-align:middle; color:#ea4335;"></span> Google Gemini Vision Settings
                            </h3>
                            <table class="form-table" style="margin-top:0;">
                                <tr>
                                    <th scope="row" style="width:220px;"><label for="wasgo_gemini_api_key">Gemini API Key</label></th>
                                    <td>
                                        <input type="password" name="wasgo_gemini_api_key" id="wasgo_gemini_api_key" class="regular-text" value="<?php echo esc_attr( WASGO_Settings::get_api_key() ); ?>">
                                        <p class="description">Required for Gemini direct generation or as the automatic fallback engine.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wasgo_gemini_model">Gemini Model</label></th>
                                    <td>
                                        <input type="text" name="wasgo_gemini_model" id="wasgo_gemini_model" class="regular-text" value="<?php echo esc_attr( WASGO_Settings::get_gemini_model() ); ?>" placeholder="gemini-3.1-flash-image-preview">
                                        <p class="description">Default: <code>gemini-3.1-flash-image-preview</code></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Magnific Section -->
                        <div class="wasgo-provider-section" id="provider-sec-magnific" style="margin-bottom:20px; border-top:1px solid #e2e8f0; padding-top:15px;">
                            <h3 style="margin-top:0; font-size:15px; color:#1e293b;">
                                <span class="dashicons dashicons-admin-customizer" style="vertical-align:middle; color:#4f46e5;"></span> Magnific AI (Nano Banana Pro)
                            </h3>
                            <table class="form-table" style="margin-top:0;">
                                <tr>
                                    <th scope="row" style="width:220px;"><label for="wasgo_magnific_api_key">Magnific API Key</label></th>
                                    <td>
                                        <input type="password" name="wasgo_magnific_api_key" id="wasgo_magnific_api_key" class="regular-text" value="<?php echo esc_attr( WASGO_Settings::get_magnific_api_key() ); ?>">
                                        <p class="description">Your Magnific MCP / API bearer token.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wasgo_magnific_model">Magnific Model</label></th>
                                    <td>
                                        <input type="text" name="wasgo_magnific_model" id="wasgo_magnific_model" class="regular-text" value="<?php echo esc_attr( WASGO_Settings::get_magnific_model() ); ?>" placeholder="imagen-nano-banana-2">
                                        <p class="description">Default: <code>imagen-nano-banana-2</code> (Studio Packshot & Harmonizer mode).</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Higgsfield Section -->
                        <div class="wasgo-provider-section" id="provider-sec-higgsfield" style="border-top:1px solid #e2e8f0; padding-top:15px;">
                            <h3 style="margin-top:0; font-size:15px; color:#1e293b;">
                                <span class="dashicons dashicons-art" style="vertical-align:middle; color:#06b6d4;"></span> Higgsfield AI Settings
                            </h3>
                            <table class="form-table" style="margin-top:0;">
                                <tr>
                                    <th scope="row" style="width:220px;"><label for="wasgo_higgsfield_api_key">Higgsfield API Key</label></th>
                                    <td>
                                        <input type="password" name="wasgo_higgsfield_api_key" id="wasgo_higgsfield_api_key" class="regular-text" value="<?php echo esc_attr( WASGO_Settings::get_higgsfield_api_key() ); ?>">
                                        <p class="description">API credentials in <code>KEY_ID:KEY_SECRET</code> or Bearer format.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wasgo_higgsfield_model">Higgsfield Model</label></th>
                                    <td>
                                        <input type="text" name="wasgo_higgsfield_model" id="wasgo_higgsfield_model" class="regular-text" value="<?php echo esc_attr( WASGO_Settings::get_higgsfield_model() ); ?>" placeholder="higgsfield-ai/soul">
                                        <p class="description">Default: <code>higgsfield-ai/soul</code></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: Content Generation Engine -->
                <div class="wasgo-admin-card">
                    <h2 style="margin-top:0; font-size:18px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
                        <span class="dashicons dashicons-editor-paragraph" style="vertical-align:middle; margin-right:6px; color:#9333ea;"></span>
                        Content & SEO Copywriting AI Engine
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width:220px;"><label for="wasgo_openai_api_key">OpenAI API Key (GPT-4o)</label></th>
                            <td>
                                <input type="password" name="wasgo_openai_api_key" id="wasgo_openai_api_key" class="regular-text" value="<?php echo esc_attr( WASGO_Settings::get_openai_api_key() ); ?>">
                                <p class="description">Enter your OpenAI API Key for bulk product titles, descriptions, and category metadata generation.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button( 'Save All Settings' ); ?>
            </form>
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
        if ( isset( $_POST['wasgo_clear_all_logs'] ) && check_admin_referer( 'wasgo_clear_logs', 'wasgo_logs_nonce' ) ) {
            WASGO_Logs::clear_logs( 'image' );
            echo "<script>location.href='admin.php?page=wasgo-enhance-images&tab=logs';</script>";
            return;
        }

        if ( isset( $_POST['wasgo_purge_legacy_db'] ) && check_admin_referer( 'wasgo_clear_logs', 'wasgo_logs_nonce' ) ) {
            WASGO_Logs::purge_legacy_posts( 5000 );
            echo "<script>location.href='admin.php?page=wasgo-enhance-images&tab=logs';</script>";
            return;
        }

        $logs = WASGO_Logs::get_logs( 'image', 'error', 50 );
        $legacy_count = WASGO_Logs::get_legacy_posts_count();
        $wc_logs_url = WASGO_Logs::get_wc_logs_url( 'image' );
        ?>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <p style="margin: 0; font-size: 13px; color: #475569;">
                    <strong style="color: #0f172a;">⚡ Moteur de logs optimisé (WC_Logger) :</strong>
                    Les événements sont écrits dans des fichiers tournants dans <code>wp-content/uploads/wc-logs/</code> sans aucun impact sur la table <code>wp_posts</code>.
                </p>
            </div>
            <div>
                <a href="<?php echo esc_url( $wc_logs_url ); ?>" target="_blank" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span class="dashicons dashicons-external" style="margin-top: 3px;"></span>
                    Journaux WooCommerce
                </a>
            </div>
        </div>

        <?php if ( $legacy_count > 0 ) : ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div style="color: #991b1b; font-size: 13px;">
                    <strong>Nettoyage base de données :</strong> Il reste <strong><?php echo esc_html( $legacy_count ); ?></strong> anciens logs dans la table <code>wp_posts</code> (ancien système).
                </div>
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field( 'wasgo_clear_logs', 'wasgo_logs_nonce' ); ?>
                    <button type="submit" name="wasgo_purge_legacy_db" class="button" style="color: #b91c1c; border-color: #fca5a5;" onclick="return confirm('Purger définitivement les anciens logs de wp_posts ?');">
                        Purger la table wp_posts
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;">Date / Heure</th>
                    <th style="width: 15%;">ID Produit</th>
                    <th style="width: 30%;">Titre du Produit</th>
                    <th style="width: 35%;">Détail de l'Erreur</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $logs ) ) : foreach ( $logs as $entry ) : 
                    $pid = $entry['item_id'];
                    $edit_url = $pid ? admin_url( 'post.php?post=' . $pid . '&action=edit' ) : '#';
                ?>
                <tr>
                    <td><strong><?php echo esc_html( $entry['date'] ); ?></strong></td>
                    <td>
                        <?php if ( $pid ) : ?>
                            <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank">#<?php echo esc_html( $pid ); ?></a>
                        <?php else : ?>
                            <span style="color: #94a3b8;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo esc_html( $entry['title'] ); ?></strong></td>
                    <td style="color: #d63638;"><?php echo esc_html( $entry['message'] ); ?></td>
                </tr>
                <?php endforeach; else : ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 30px; color: #64748b;">
                        Aucune erreur d'optimisation d'image enregistrée. Tout fonctionne parfaitement !
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom" style="margin-top: 15px;">
            <form method="post" action="" style="float: left;">
                <?php wp_nonce_field( 'wasgo_clear_logs', 'wasgo_logs_nonce' ); ?>
                <input type="submit" name="wasgo_clear_all_logs" class="button" onclick="return confirm('Vider les fichiers de logs d\'images ?');" value="Vider les logs d'images">
            </form>
            <div style="float: right; color: #64748b; font-size: 12px; line-height: 28px;">
                Affichage des 50 erreurs les plus récentes (fichiers tournants WC_Logger)
            </div>
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
        
        $tabs = [
            'short'     => 'Short Description',
            'long'      => 'Long Description',
            'title'     => 'Meta Title',
            'desc'      => 'Meta Description',
            'cat_title' => 'Meta Title (Category)',
            'cat_desc'  => 'Meta Description (Category)'
        ];

        if ( in_array( $sub_tab, [ 'cat_title', 'cat_desc' ] ) ) {
            $specs = [ 'category_description', 'parent_category', 'product_count', 'latest_products' ];
        } else {
            $specs = WASGO_Content_Utility::get_available_specs();
        }
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
            'short'     => [ 'group' => 'wasgo_content_short_group', 'prompt' => 'wasgo_content_short_prompt', 'specs' => 'wasgo_content_short_specs' ],
            'long'      => [ 'group' => 'wasgo_content_long_group', 'prompt' => 'wasgo_content_long_prompt', 'specs' => 'wasgo_content_long_specs' ],
            'title'     => [ 'group' => 'wasgo_content_title_group', 'prompt' => 'wasgo_content_title_prompt', 'specs' => 'wasgo_content_title_specs' ],
            'desc'      => [ 'group' => 'wasgo_content_desc_group', 'prompt' => 'wasgo_content_desc_prompt', 'specs' => 'wasgo_content_desc_specs' ],
            'cat_title' => [ 'group' => 'wasgo_content_cat_title_group', 'prompt' => 'wasgo_content_cat_title_prompt', 'specs' => 'wasgo_content_cat_title_specs' ],
            'cat_desc'  => [ 'group' => 'wasgo_content_cat_desc_group', 'prompt' => 'wasgo_content_cat_desc_prompt', 'specs' => 'wasgo_content_cat_desc_specs' ],
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

                        <?php 
                        $prompt_val = get_option( $current['prompt'] );
                        if ( empty( $prompt_val ) ) {
                            if ( $sub_tab === 'cat_title' ) {
                                $prompt_val = "Write a high-converting, professional, and SEO-optimized meta title for this product category.\nThe meta title should be compelling, incorporate the category name naturally, and stay within 50-60 characters for maximum search engine click-through rates.";
                            } elseif ( $sub_tab === 'cat_desc' ) {
                                $prompt_val = "Write an engaging, SEO-optimized meta description for this product category.\nIt should entice searchers to click, describe what products they will find in this category, and contain a clear call to action while strictly staying within 150-160 characters.";
                            }
                        }
                        ?>
                        <textarea id="wasgo-prompt-input" name="<?php echo $current['prompt']; ?>" rows="10" 
                                  placeholder="e.g. Write a catchy and professional <?php echo strtolower($tabs[$sub_tab]); ?>..."
                                  data-type="<?php echo $sub_tab; ?>"><?php echo esc_textarea( $prompt_val ); ?></textarea>

                        <div class="wasgo-specs-title">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php echo in_array( $sub_tab, [ 'cat_title', 'cat_desc' ] ) ? 'Useful Information to Include' : 'Useful Specs to Include'; ?>
                        </div>
                        <p class="description" style="margin-bottom:15px;">
                            <?php echo in_array( $sub_tab, [ 'cat_title', 'cat_desc' ] ) ? 'Select which category details should be dynamically attached.' : 'Select which product details should be dynamically attached.'; ?>
                        </p>

                        <div class="wasgo-specs-container">
                            <?php foreach ( $specs as $spec ) : ?>
                                <label class="wasgo-spec-item">
                                    <input type="checkbox" name="<?php echo $current['specs']; ?>[]" 
                                           class="wasgo-spec-checkbox"
                                           value="<?php echo esc_attr( $spec ); ?>" 
                                           <?php checked( in_array( $spec, $saved_specs ) ); ?>>
                                    <?php 
                                    if ( in_array( $sub_tab, [ 'cat_title', 'cat_desc' ] ) ) {
                                        $label_map = [
                                            'category_description' => 'Category Description',
                                            'parent_category'      => 'Parent Category',
                                            'product_count'        => 'Total Product Count',
                                            'latest_products'      => 'Include Latest 3 Products'
                                        ];
                                        echo esc_html( isset( $label_map[$spec] ) ? $label_map[$spec] : $spec );
                                    } else {
                                        echo esc_html( str_replace( '_', ' ', $spec ) ); 
                                    }
                                    ?>
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
                            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                                <span class="dashicons dashicons-visibility"></span>
                                Live AI Perspective
                            </div>
                            <div class="wasgo-preview-search-container">
                                <span class="dashicons dashicons-search"></span>
                                <input type="text" id="wasgo-preview-search" placeholder="<?php echo in_array( $sub_tab, [ 'cat_title', 'cat_desc' ] ) ? 'Search category to test...' : 'Search product to test...'; ?>">
                                <div id="wasgo-preview-search-results" class="wasgo-search-dropdown" style="display:none;"></div>
                            </div>
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
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="wasgo_content_disable_desc" value="1" <?php checked( 1, get_option( 'wasgo_content_disable_desc', 0 ) ); ?> />
                                Meta Description
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="wasgo_content_disable_cat_title" value="1" <?php checked( 1, get_option( 'wasgo_content_disable_cat_title', 0 ) ); ?> />
                                Meta Title (Category)
                            </label>
                            <label style="display: block;">
                                <input type="checkbox" name="wasgo_content_disable_cat_desc" value="1" <?php checked( 1, get_option( 'wasgo_content_disable_cat_desc', 0 ) ); ?> />
                                Meta Description (Category)
                            </label>
                            <p class="description">Disabled fields will not be generated during single item edits or auto-processing. <strong>Note:</strong> Bulk Actions will ignore these settings.</p>
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
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="wasgo_content_bulk_types[]" value="cat_title"> Category Meta Title
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="wasgo_content_bulk_types[]" value="cat_desc"> Category Meta Description
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

        $category_args = [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'   => '_wasgo_needs_review',
                    'value' => '1'
                ]
            ]
        ];
        $review_categories = get_terms( $category_args );
        ?>
        <div class="wasgo-admin-card" style="padding:0; overflow:hidden;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 15%; padding-left: 20px;">Item Info</th>
                        <th style="width: 30%;">Generated Content Preview</th>
                        <th style="width: 15%;">Risk / Reason</th>
                        <th style="width: 10%; text-align: center;">Score</th>
                        <th style="width: 30%; text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="wasgo-content-review-body">
                    <?php 
                    $has_items = false;

                    // Products Loop
                    if ( $query->have_posts() ) : 
                        $has_items = true;
                        while ( $query->have_posts() ) : $query->the_post(); 
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
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <?php if ( has_post_thumbnail( $pid ) ) : ?>
                                            <div style="flex-shrink: 0; width: 50px; height: 50px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0;">
                                                <?php echo get_the_post_thumbnail( $pid, [50, 50], ['style' => 'width:100%; height:auto; display:block;'] ); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><a href="<?php echo get_edit_post_link( $pid ); ?>" target="_blank" style="text-decoration: none; color: #1e293b;"><?php the_title(); ?></a></strong>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 5px;">
                                                Type: <span style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-weight: 600; color: #475569;"><?php echo $type_label; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wasgo-review-content-container">
                                        <div class="wasgo-review-static-content" style="max-height: 120px; overflow-y: auto; font-size: 13px; line-height: 1.5; color: #334155; border: 1px solid #f1f5f9; padding: 12px; border-radius: 8px; background: #fff; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                                            <?php echo nl2br( esc_html( $data['content'] ) ); ?>
                                        </div>
                                        <textarea class="wasgo-review-edit-content" style="display:none; width: 100%; height: 120px; font-size: 13px; line-height: 1.5; padding: 10px; border-radius: 8px; border: 1px solid #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.1);"><?php echo esc_textarea( $data['content'] ); ?></textarea>
                                    </div>
                                </td>
                                <td>
                                    <div class="wasgo-review-issues-container">
                                        <ul style="margin:0; padding:0; list-style:none; font-size: 12px; color: #ef4444;">
                                            <?php if ( ! empty( $data['issues'] ) ) : foreach ( $data['issues'] as $issue ) : ?>
                                                <li style="margin-bottom: 6px; display: flex; gap: 6px; align-items: flex-start;">
                                                    <span class="dashicons dashicons-warning" style="font-size: 14px; width:14px; height:14px; margin-top: 2px;"></span>
                                                    <span><?php echo esc_html( $issue ); ?></span>
                                                </li>
                                            <?php endforeach; else: ?>
                                                <li style="display: flex; gap: 6px; align-items: flex-start;">
                                                    <span class="dashicons dashicons-warning" style="font-size: 14px; width:14px; height:14px; margin-top: 2px;"></span>
                                                    <span>Low confidence score</span>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <div class="wasgo-review-score-badge" style="display:inline-block; padding: 6px 12px; border-radius: 20px; background: <?php echo $score_color; ?>10; color: <?php echo $score_color; ?>; font-weight: bold; border: 1px solid <?php echo $score_color; ?>30; font-size: 12px;">
                                        <?php echo $score_pct; ?>%
                                    </div>
                                </td>
                                <td style="text-align: right; padding-right: 20px; padding-left: 20px; vertical-align: middle;">
                                    <div style="display: flex; flex-direction: column; gap: 8px; align-items: flex-end;">
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="wasgo-studio-btn btn-edit wasgo-review-edit-btn" 
                                                    data-pid="<?php echo $pid; ?>" data-type="<?php echo $type; ?>" data-item-type="product" title="Edit Content">
                                                <span class="dashicons dashicons-edit"></span> Edit
                                            </button>
                                            <button type="button" class="wasgo-studio-btn btn-save wasgo-review-save-btn" 
                                                    data-pid="<?php echo $pid; ?>" data-type="<?php echo $type; ?>" data-item-type="product" style="display:none;">
                                                <span class="dashicons dashicons-saved"></span> Save
                                            </button>
                                            <button type="button" class="wasgo-studio-btn btn-regenerate wasgo-review-regenerate-btn" 
                                                    data-pid="<?php echo $pid; ?>" data-type="<?php echo $type; ?>" data-item-type="product">
                                                <span class="dashicons dashicons-update"></span> Regenerate
                                            </button>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="wasgo-studio-btn btn-approve wasgo-review-action" 
                                                    data-action="approve" data-pid="<?php echo $pid; ?>" data-type="<?php echo $type; ?>" data-item-type="product">Approve</button>
                                            <button type="button" class="wasgo-studio-btn btn-discard wasgo-review-action" 
                                                    data-action="discard" data-pid="<?php echo $pid; ?>" data-type="<?php echo $type; ?>" data-item-type="product">Discard</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endwhile; wp_reset_postdata(); ?>
                    <?php endif; ?>

                    <?php 
                    // Categories Loop
                    if ( ! empty( $review_categories ) && ! is_wp_error( $review_categories ) ) : 
                        $has_items = true;
                        foreach ( $review_categories as $cid ) :
                            $review_data = get_term_meta( $cid, '_wasgo_content_review', true );
                            if ( ! is_array( $review_data ) ) continue;

                            $term = get_term( $cid, 'product_cat' );
                            if ( ! $term || is_wp_error( $term ) ) continue;

                            foreach ( $review_data as $type => $data ) :
                                $type_label = str_replace( ['cat_title', 'cat_desc'], ['Cat Title', 'Cat Desc'], $type );
                                $score = isset( $data['score'] ) ? floatval( $data['score'] ) : 0;
                                $score_pct = round( $score * 100 );
                                
                                // Determine score color
                                $score_color = '#ef4444'; // Red
                                if ( $score >= 0.8 ) $score_color = '#22c55e'; // Green
                                elseif ( $score >= 0.5 ) $score_color = '#f59e0b'; // Amber

                                // Category Image
                                $thumbnail_id = get_term_meta( $cid, 'thumbnail_id', true );
                                $image_html = '';
                                if ( $thumbnail_id ) {
                                    $image_html = wp_get_attachment_image( $thumbnail_id, [50, 50], false, ['style' => 'width:100%; height:auto; display:block;'] );
                                }
                            ?>
                            <tr id="review-row-<?php echo $cid; ?>-<?php echo $type; ?>-category">
                                <td style="padding-left: 20px;">
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <?php if ( $image_html ) : ?>
                                            <div style="flex-shrink: 0; width: 50px; height: 50px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0;">
                                                <?php echo $image_html; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><a href="<?php echo get_edit_term_link( $cid, 'product_cat' ); ?>" target="_blank" style="text-decoration: none; color: #1e293b;"><?php echo esc_html( $term->name ); ?></a></strong>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 5px;">
                                                Type: <span style="background: #e0f2fe; padding: 2px 6px; border-radius: 4px; font-weight: 600; color: #0369a1;"><?php echo $type_label; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wasgo-review-content-container">
                                        <div class="wasgo-review-static-content" style="max-height: 120px; overflow-y: auto; font-size: 13px; line-height: 1.5; color: #334155; border: 1px solid #f1f5f9; padding: 12px; border-radius: 8px; background: #fff; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                                            <?php echo nl2br( esc_html( $data['content'] ) ); ?>
                                        </div>
                                        <textarea class="wasgo-review-edit-content" style="display:none; width: 100%; height: 120px; font-size: 13px; line-height: 1.5; padding: 10px; border-radius: 8px; border: 1px solid #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.1);"><?php echo esc_textarea( $data['content'] ); ?></textarea>
                                    </div>
                                </td>
                                <td>
                                    <div class="wasgo-review-issues-container">
                                        <ul style="margin:0; padding:0; list-style:none; font-size: 12px; color: #ef4444;">
                                            <?php if ( ! empty( $data['issues'] ) ) : foreach ( $data['issues'] as $issue ) : ?>
                                                <li style="margin-bottom: 6px; display: flex; gap: 6px; align-items: flex-start;">
                                                    <span class="dashicons dashicons-warning" style="font-size: 14px; width:14px; height:14px; margin-top: 2px;"></span>
                                                    <span><?php echo esc_html( $issue ); ?></span>
                                                </li>
                                            <?php endforeach; else: ?>
                                                <li style="display: flex; gap: 6px; align-items: flex-start;">
                                                    <span class="dashicons dashicons-warning" style="font-size: 14px; width:14px; height:14px; margin-top: 2px;"></span>
                                                    <span>Low confidence score</span>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <div class="wasgo-review-score-badge" style="display:inline-block; padding: 6px 12px; border-radius: 20px; background: <?php echo $score_color; ?>10; color: <?php echo $score_color; ?>; font-weight: bold; border: 1px solid <?php echo $score_color; ?>30; font-size: 12px;">
                                        <?php echo $score_pct; ?>%
                                    </div>
                                </td>
                                <td style="text-align: right; padding-right: 20px; padding-left: 20px; vertical-align: middle;">
                                    <div style="display: flex; flex-direction: column; gap: 8px; align-items: flex-end;">
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="wasgo-studio-btn btn-edit wasgo-review-edit-btn" 
                                                    data-pid="<?php echo $cid; ?>" data-type="<?php echo $type; ?>" data-item-type="category" title="Edit Content">
                                                <span class="dashicons dashicons-edit"></span> Edit
                                            </button>
                                            <button type="button" class="wasgo-studio-btn btn-save wasgo-review-save-btn" 
                                                    data-pid="<?php echo $cid; ?>" data-type="<?php echo $type; ?>" data-item-type="category" style="display:none;">
                                                <span class="dashicons dashicons-saved"></span> Save
                                            </button>
                                            <button type="button" class="wasgo-studio-btn btn-regenerate wasgo-review-regenerate-btn" 
                                                    data-pid="<?php echo $cid; ?>" data-type="<?php echo $type; ?>" data-item-type="category">
                                                <span class="dashicons dashicons-update"></span> Regenerate
                                            </button>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="wasgo-studio-btn btn-approve wasgo-review-action" 
                                                    data-action="approve" data-pid="<?php echo $cid; ?>" data-type="<?php echo $type; ?>" data-item-type="category">Approve</button>
                                            <button type="button" class="wasgo-studio-btn btn-discard wasgo-review-action" 
                                                    data-action="discard" data-pid="<?php echo $cid; ?>" data-type="<?php echo $type; ?>" data-item-type="category">Discard</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ( ! $has_items ) : ?>
                        <tr>
                            <td colspan="5" style="padding: 40px; text-align: center; color: #64748b;">
                                <span class="dashicons dashicons-shield-check" style="font-size: 40px; width: 40px; height: 40px; display: block; margin: 0 auto 15px;"></span>
                                No items currently require manual review.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    private function render_tab_success_logs() {
        if ( isset( $_POST['wasgo_clear_success_logs'] ) && check_admin_referer( 'wasgo_clear_logs', 'wasgo_logs_nonce' ) ) {
            WASGO_Logs::clear_logs( 'content' );
            echo "<script>location.href='admin.php?page=wasgo-content-generation&tab=success_logs';</script>";
            return;
        }

        if ( isset( $_POST['wasgo_purge_legacy_db'] ) && check_admin_referer( 'wasgo_clear_logs', 'wasgo_logs_nonce' ) ) {
            WASGO_Logs::purge_legacy_posts( 5000 );
            echo "<script>location.href='admin.php?page=wasgo-content-generation&tab=success_logs';</script>";
            return;
        }

        $logs = WASGO_Logs::get_logs( 'content', 'success', 50 );
        $legacy_count = WASGO_Logs::get_legacy_posts_count();
        $wc_logs_url = WASGO_Logs::get_wc_logs_url( 'content' );
        ?>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <p style="margin: 0; font-size: 13px; color: #475569;">
                    <strong style="color: #0f172a;">⚡ Moteur de logs optimisé (WC_Logger) :</strong>
                    L'historique des générations réussies est consigné dans les fichiers <code>wc-logs/wasgo-content-*.log</code>.
                </p>
            </div>
            <div>
                <a href="<?php echo esc_url( $wc_logs_url ); ?>" target="_blank" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span class="dashicons dashicons-external" style="margin-top: 3px;"></span>
                    Journaux WooCommerce
                </a>
            </div>
        </div>

        <?php if ( $legacy_count > 0 ) : ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div style="color: #991b1b; font-size: 13px;">
                    <strong>Nettoyage base de données :</strong> Il reste <strong><?php echo esc_html( $legacy_count ); ?></strong> anciens logs dans la table <code>wp_posts</code>.
                </div>
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field( 'wasgo_clear_logs', 'wasgo_logs_nonce' ); ?>
                    <button type="submit" name="wasgo_purge_legacy_db" class="button" style="color: #b91c1c; border-color: #fca5a5;" onclick="return confirm('Purger définitivement les anciens logs de wp_posts ?');">
                        Purger la table wp_posts
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="wasgo-admin-card" style="padding:0; overflow:hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fff;">
                <h3 style="margin:0; font-size: 16px; color: #1e293b;">Éléments Générés avec Succès</h3>
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field( 'wasgo_clear_logs', 'wasgo_logs_nonce' ); ?>
                    <button type="submit" name="wasgo_clear_success_logs" class="button button-secondary" style="color: #64748b; border-color: #e2e8f0;" onclick="return confirm('Vider l\'historique des succès ?');">
                        Vider l'historique des succès
                    </button>
                </form>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 18%; padding-left: 20px;">Date / Heure</th>
                        <th style="width: 35%;">Élément</th>
                        <th style="width: 27%;">Détail Mise à Jour</th>
                        <th style="width: 20%; text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $logs ) ) : foreach ( $logs as $idx => $entry ) : 
                        $pid = $entry['item_id'];
                        $msg = $entry['message'];
                        $log_data = $entry['data'];
                        $target_id = 'log-content-entry-' . $idx;

                        $is_term = strpos( $entry['title'], 'Category:' ) === 0;
                        if ( $is_term ) {
                            $product_url = get_term_link( $pid, 'product_cat' );
                            $edit_url = admin_url( 'term.php?taxonomy=product_cat&tag_ID=' . $pid . '&post_type=product' );
                        } else {
                            $product_url = $pid ? get_permalink( $pid ) : '#';
                            $edit_url = $pid ? get_edit_post_link( $pid ) : '#';
                        }
                    ?>
                    <tr>
                        <td style="padding-left: 20px; color: #64748b; font-size: 13px;"><?php echo esc_html( $entry['date'] ); ?></td>
                        <td>
                            <strong style="color: #1e293b;">#<?php echo esc_html( $pid ); ?> - <?php echo esc_html( $entry['title'] ); ?></strong>
                        </td>
                        <td>
                            <span style="display:inline-block; padding: 2px 8px; border-radius: 4px; background: #dcfce7; color: #166534; font-size: 11px; font-weight: 600; width: fit-content;">
                                <span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px; line-height: 1.4;"></span>
                                <?php echo esc_html( $msg ); ?>
                            </span>
                        </td>
                        <td style="text-align: right; padding-right: 20px;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <?php if ( ! empty( $log_data ) ) : ?>
                                    <button type="button" class="wasgo-toggle-log-content button button-small" 
                                            data-target="<?php echo esc_attr( $target_id ); ?>" 
                                            style="background: #6366f1; border-color: #4f46e5; color: #fff;">
                                        <span class="dashicons dashicons-media-document" style="margin-top: 4px;"></span> Preview Content
                                    </button>
                                <?php endif; ?>
                                <?php if ( $pid && ! is_wp_error( $product_url ) && $product_url !== '#' ) : ?>
                                    <a href="<?php echo esc_url( $product_url ); ?>" target="_blank" class="button button-small" title="Voir sur le site">
                                        <span class="dashicons dashicons-visibility" style="margin-top: 4px;"></span> Voir
                                    </a>
                                <?php endif; ?>
                                <?php if ( $pid && $edit_url !== '#' ) : ?>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" class="button button-small" title="Modifier dans l'administration">
                                        <span class="dashicons dashicons-edit" style="margin-top: 4px;"></span> Modifier
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php if ( ! empty( $log_data ) ) : ?>
                    <tr id="<?php echo esc_attr( $target_id ); ?>" style="display:none; background: #f8fafc; border-left: 4px solid #6366f1;">
                        <td colspan="4" style="padding: 20px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 15px;">
                                <?php foreach ( $log_data as $label => $content ) : ?>
                                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                        <div style="font-size: 10px; font-weight: 800; color: #64748b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                                            <span class="dashicons dashicons-editor-alignleft" style="font-size: 14px; width:14px; height:14px;"></span>
                                            <?php echo esc_html( $label ); ?>
                                        </div>
                                        <div style="font-size: 13px; color: #334155; line-height: 1.6; max-height: 200px; overflow-y: auto; padding-right: 5px;" class="wasgo-custom-scrollbar">
                                            <?php echo nl2br( esc_html( is_array( $content ) ? wp_json_encode( $content ) : $content ) ); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; else : ?>
                    <tr>
                        <td colspan="4" style="padding: 60px; text-align: center; color: #64748b;">
                            <span class="dashicons dashicons-clock" style="font-size: 40px; width: 40px; height: 40px; display: block; margin: 0 auto 15px; color: #cbd5e1;"></span>
                            Aucun succès consigné pour le moment.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div style="padding: 15px 20px; background: #fff; border-top: 1px solid #f1f5f9; text-align: right; color: #64748b; font-size: 12px;">
                Affichage des 50 succès les plus récents (WC_Logger)
            </div>
        </div>
        <?php
    }

    private function render_content_tab_logs() {
        if ( isset( $_POST['wasgo_clear_content_error_logs'] ) && check_admin_referer( 'wasgo_clear_logs', 'wasgo_logs_nonce' ) ) {
            WASGO_Logs::clear_logs( 'content' );
            echo "<script>location.href='admin.php?page=wasgo-content-generation&tab=logs';</script>";
            return;
        }

        $logs = WASGO_Logs::get_logs( 'content', 'error', 50 );
        $wc_logs_url = WASGO_Logs::get_wc_logs_url( 'content' );
        ?>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <p style="margin: 0; font-size: 13px; color: #475569;">
                    <strong style="color: #0f172a;">⚡ Moteur de logs optimisé (WC_Logger) :</strong>
                    Les erreurs de génération de contenu sont écrites dans <code>wc-logs/wasgo-content-*.log</code>.
                </p>
            </div>
            <div>
                <a href="<?php echo esc_url( $wc_logs_url ); ?>" target="_blank" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span class="dashicons dashicons-external" style="margin-top: 3px;"></span>
                    Journaux WooCommerce
                </a>
            </div>
        </div>

        <div class="wasgo-admin-card" style="padding:0; overflow:hidden;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%; padding-left: 20px;">Date / Heure</th>
                        <th style="width: 15%;">ID Élément</th>
                        <th style="width: 45%;">Raison de l'Erreur</th>
                        <th style="width: 20%; text-align: right; padding-right: 20px;">Titre Élément</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $logs ) ) : foreach ( $logs as $entry ) : 
                        $pid = $entry['item_id'];
                    ?>
                    <tr>
                        <td style="padding-left: 20px;"><?php echo esc_html( $entry['date'] ); ?></td>
                        <td>#<?php echo esc_html( $pid ); ?></td>
                        <td style="color: #ef4444;"><?php echo esc_html( $entry['message'] ); ?></td>
                        <td style="text-align: right; padding-right: 20px;"><?php echo esc_html( $entry['title'] ); ?></td>
                    </tr>
                    <?php endforeach; else : ?>
                    <tr>
                        <td colspan="4" style="padding: 40px; text-align: center; color: #64748b;">
                            Aucune erreur de génération de contenu enregistrée.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div style="padding: 15px 20px; background: #fff; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field( 'wasgo_clear_logs', 'wasgo_logs_nonce' ); ?>
                    <button type="submit" name="wasgo_clear_content_error_logs" class="button" onclick="return confirm('Vider les erreurs de contenu ?');">
                        Vider les erreurs de contenu
                    </button>
                </form>
                <div style="color: #64748b; font-size: 12px;">
                    Affichage des 50 erreurs les plus récentes (WC_Logger)
                </div>
            </div>
        </div>
        <?php
    }
}
