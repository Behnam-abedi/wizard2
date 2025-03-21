<?php
/**
 * Shortcodes for Hierarchical Product Options
 *
 * @since      1.0.0
 */
class HPO_Shortcodes {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Register shortcodes
        add_shortcode('hpo_order_button', array($this, 'order_button_shortcode'));
        
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Register AJAX handlers
        add_action('wp_ajax_hpo_load_products', array($this, 'ajax_load_products'));
        add_action('wp_ajax_nopriv_hpo_load_products', array($this, 'ajax_load_products'));
        
        add_action('wp_ajax_hpo_load_product_details', array($this, 'ajax_load_product_details'));
        add_action('wp_ajax_nopriv_hpo_load_product_details', array($this, 'ajax_load_product_details'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'hpo-shortcode-styles',
            HPO_PLUGIN_URL . 'public/css/shortcodes.css',
            array(),
            HPO_VERSION
        );
        
        wp_enqueue_script(
            'hpo-shortcode-scripts',
            HPO_PLUGIN_URL . 'public/js/shortcodes.js',
            array('jquery'),
            HPO_VERSION,
            true
        );
        
        wp_localize_script(
            'hpo-shortcode-scripts',
            'hpoAjax',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hpo_ajax_nonce')
            )
        );
    }
    
    /**
     * Order button shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function order_button_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'button_text' => 'ثبت سفارش',
                'button_class' => 'hpo-order-button',
            ),
            $atts,
            'hpo_order_button'
        );
        
        ob_start();
        ?>
        <div class="hpo-order-button-container">
            <button class="<?php echo esc_attr($atts['button_class']); ?>" id="hpo-order-button">
                <?php echo esc_html($atts['button_text']); ?>
            </button>
        </div>
        
        <div class="hpo-popup-overlay" id="hpo-popup-overlay" style="display: none;">
            <div class="hpo-popup-container">
                <div class="hpo-popup-header">
                    <h3>انتخاب محصول</h3>
                    <span class="hpo-popup-close" id="hpo-popup-close">&times;</span>
                </div>
                <div class="hpo-popup-content">
                    <div class="hpo-product-list" id="hpo-product-list">
                        <!-- Products will be loaded here via AJAX -->
                        <div class="hpo-loading">در حال بارگذاری...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="hpo-product-details-popup" id="hpo-product-details-popup" style="display: none;">
            <div class="hpo-popup-container">
                <div class="hpo-popup-header">
                    <h3 id="hpo-product-title">جزئیات محصول</h3>
                    <span class="hpo-popup-close" id="hpo-product-details-close">&times;</span>
                </div>
                <div class="hpo-popup-content">
                    <div id="hpo-product-details-content">
                        <!-- Product details will be loaded here via AJAX -->
                        <div class="hpo-loading">در حال بارگذاری...</div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for loading products
     */
    public function ajax_load_products() {
        check_ajax_referer('hpo_ajax_nonce', 'nonce');
        
        global $wpdb;
        $db = new Hierarchical_Product_Options_DB();
        
        // Get all products that have hierarchical options
        $assignments = $db->get_category_product_assignments();
        $product_ids = array();
        
        foreach ($assignments as $assignment) {
            if (!in_array($assignment->wc_product_id, $product_ids)) {
                $product_ids[] = $assignment->wc_product_id;
            }
        }
        
        ob_start();
        
        if (empty($product_ids)) {
            echo '<p>هیچ محصولی با گزینه‌های سلسله مراتبی یافت نشد.</p>';
        } else {
            echo '<div class="hpo-products-grid">';
            
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                
                if ($product) {
                    ?>
                    <div class="hpo-product-item" data-product-id="<?php echo esc_attr($product_id); ?>">
                        <div class="hpo-product-image">
                            <?php echo $product->get_image('thumbnail'); ?>
                        </div>
                        <div class="hpo-product-info">
                            <h4><?php echo esc_html($product->get_name()); ?></h4>
                            <div class="hpo-product-price">
                                <?php echo $product->get_price_html(); ?>
                            </div>
                            <button class="hpo-select-product" data-product-id="<?php echo esc_attr($product_id); ?>">
                                انتخاب
                            </button>
                        </div>
                    </div>
                    <?php
                }
            }
            
            echo '</div>';
        }
        
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX handler for loading product details
     */
    public function ajax_load_product_details() {
        check_ajax_referer('hpo_ajax_nonce', 'nonce');
        
        if (empty($_POST['product_id'])) {
            wp_send_json_error(array('message' => 'شناسه محصول نامعتبر است.'));
            return;
        }
        
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => 'محصول یافت نشد.'));
            return;
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        // Get all categories for this product (both parent and child)
        $categories = $db->get_categories_for_product($product_id);
        
        // Organize categories into hierarchical structure
        $parent_categories = array();
        $child_categories = array();
        
        foreach ($categories as $category) {
            if ($category->parent_id == 0) {
                $parent_categories[] = $category;
            } else {
                $child_categories[$category->parent_id][] = $category;
            }
        }
        
        // Get weight options
        $weights = $db->get_weights_for_product($product_id);
        
        ob_start();
        ?>
        <div class="hpo-product-details">
            <div class="hpo-product-main-info">
                <div class="hpo-product-image">
                    <?php echo $product->get_image('medium'); ?>
                </div>
                <div class="hpo-product-summary">
                    <h2><?php echo esc_html($product->get_name()); ?></h2>
                    <div class="hpo-product-price">
                        <?php echo $product->get_price_html(); ?>
                    </div>
                    <div class="hpo-product-description">
                        <?php echo wpautop($product->get_short_description()); ?>
                    </div>
                </div>
            </div>
            
            <form class="hpo-product-options-form">
                <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                <input type="hidden" name="hpo_base_price" value="<?php echo esc_attr($product->get_price()); ?>">
                
                <?php if (!empty($parent_categories)): ?>
                <div class="hpo-option-section">
                    <h3>گزینه‌های محصول</h3>
                    <div class="hpo-options-list">
                        <?php foreach ($parent_categories as $parent): ?>
                        <div class="hpo-category">
                            <h4><?php echo esc_html($parent->name); ?></h4>
                            <div class="hpo-products">
                                <?php 
                                // Get products for parent category
                                $parent_products = $db->get_products_by_category($parent->id);
                                foreach ($parent_products as $opt_product): 
                                ?>
                                <div class="hpo-product-option">
                                    <label>
                                        <input type="radio" name="hpo_option[<?php echo esc_attr($parent->id); ?>]" 
                                               value="<?php echo esc_attr($opt_product->id); ?>" 
                                               data-price="<?php echo esc_attr($opt_product->price); ?>">
                                        <?php echo esc_html($opt_product->name); ?>
                                        <span class="hpo-option-price">(<?php echo wc_price($opt_product->price); ?>)</span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php 
                            // Display child categories for this parent
                            if (isset($child_categories[$parent->id]) && !empty($child_categories[$parent->id])): 
                            ?>
                            <div class="hpo-child-categories">
                                <?php foreach ($child_categories[$parent->id] as $child): ?>
                                <div class="hpo-child-category">
                                    <h5><?php echo esc_html($child->name); ?></h5>
                                    <div class="hpo-products">
                                        <?php 
                                        // Get products for child category
                                        $child_products = $db->get_products_by_category($child->id);
                                        foreach ($child_products as $child_product): 
                                        ?>
                                        <div class="hpo-product-option">
                                            <label>
                                                <input type="radio" name="hpo_option[<?php echo esc_attr($child->id); ?>]" 
                                                       value="<?php echo esc_attr($child_product->id); ?>" 
                                                       data-price="<?php echo esc_attr($child_product->price); ?>">
                                                <?php echo esc_html($child_product->name); ?>
                                                <span class="hpo-option-price">(<?php echo wc_price($child_product->price); ?>)</span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($weights)): ?>
                <div class="hpo-option-section">
                    <h3>گزینه‌های وزن</h3>
                    <div class="hpo-weight-options">
                        <?php foreach ($weights as $weight): ?>
                        <div class="hpo-weight-option">
                            <label>
                                <input type="radio" name="hpo_weight" 
                                       value="<?php echo esc_attr($weight->id); ?>" 
                                       data-coefficient="<?php echo esc_attr($weight->coefficient); ?>">
                                <?php echo esc_html($weight->name); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="hpo-option-section">
                    <h3>گزینه‌های آسیاب</h3>
                    <div class="hpo-grinding-options">
                        <div class="hpo-grinding-toggle">
                            <label>
                                <input type="radio" name="hpo_grinding" value="whole" checked>
                                دانه کامل (بدون آسیاب)
                            </label>
                            <label>
                                <input type="radio" name="hpo_grinding" value="ground">
                                آسیاب شده
                            </label>
                        </div>
                        
                        <div class="hpo-grinding-machines" style="display:none;">
                            <label for="hpo-grinding-machine">انتخاب دستگاه آسیاب:</label>
                            <select name="hpo_grinding_machine" id="hpo-grinding-machine">
                                <option value="">-- انتخاب دستگاه آسیاب --</option>
                                <?php 
                                $grinders = $db->get_all_grinders();
                                foreach ($grinders as $grinder): 
                                ?>
                                <option value="<?php echo esc_attr($grinder->id); ?>" 
                                        data-price="<?php echo esc_attr($grinder->price); ?>">
                                    <?php echo esc_html($grinder->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="hpo-option-section">
                    <h3>توضیحات اضافی</h3>
                    <textarea name="hpo_customer_notes" rows="3" placeholder="اگر توضیح خاصی در مورد سفارش خود دارید، اینجا بنویسید..."></textarea>
                </div>
                
                <div class="hpo-quantity-section">
                    <h3>تعداد</h3>
                    <div class="hpo-quantity-input">
                        <button type="button" class="hpo-quantity-minus">-</button>
                        <input type="number" name="quantity" value="1" min="1" max="99">
                        <button type="button" class="hpo-quantity-plus">+</button>
                    </div>
                </div>
                
                <div class="hpo-total-price">
                    <span class="hpo-total-label">قیمت نهایی:</span>
                    <span class="hpo-total-value" id="hpo-total-price"><?php echo wc_price($product->get_price()); ?></span>
                </div>
                
                <div class="hpo-add-to-cart">
                    <button type="submit" class="hpo-add-to-cart-button">افزودن به سبد خرید</button>
                </div>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(array(
            'html' => $html,
            'product_title' => $product->get_name()
        ));
    }
}

// Initialize the shortcodes
new HPO_Shortcodes(); 