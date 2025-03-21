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
        
        // Register AJAX handler for adding to cart
        add_action('wp_ajax_hpo_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_hpo_add_to_cart', array($this, 'ajax_add_to_cart'));
        
        // Cart item display and price filters
        add_filter('woocommerce_get_item_data', array($this, 'add_cart_item_custom_data'), 100, 2);
        add_filter('woocommerce_cart_item_price', array($this, 'update_cart_item_price'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'update_cart_item_price'), 10, 3);
        add_filter('woocommerce_before_calculate_totals', array($this, 'calculate_cart_item_prices'), 10, 1);
        
        // Filter to change price display format for our popups
        add_filter('woocommerce_get_price_html', array($this, 'format_price_html'), 100, 2);
        
        // Ensure our custom data is preserved and products with options don't get merged in cart
        add_filter('woocommerce_add_cart_item_data', array($this, 'prevent_cart_merging'), 10, 2);
        add_filter('woocommerce_add_cart_item', array($this, 'setup_cart_item'), 10, 1);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        
        // Add filter to modify the product name in cart
        add_filter('woocommerce_cart_item_name', array($this, 'modify_cart_item_name'), 10, 3);
        
        // Remove default WooCommerce grinding metadata from cart items
        add_filter('woocommerce_display_item_meta', array($this, 'remove_grinding_metadata'), 10, 3);
        
        // Override product price in cart display
        add_filter('woocommerce_widget_cart_item_quantity', array($this, 'modify_mini_cart_quantity'), 10, 3);
        add_filter('woocommerce_cart_item_quantity', array($this, 'modify_cart_item_quantity'), 10, 3);
        add_filter('woocommerce_cart_item_quantity_display', array($this, 'modify_cart_item_quantity_display'), 10, 3);
        
        // Directly filter the price HTML
        add_filter('woocommerce_product_get_price', array($this, 'filter_product_price'), 99, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'filter_product_price'), 99, 2);
        
        // Add custom CSS for cart display
        add_action('wp_head', array($this, 'add_custom_cart_css'));
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
                                        <span class="hpo-option-price">(<?php echo number_format($opt_product->price); ?> تومان)</span>
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
                                                <span class="hpo-option-price">(<?php echo number_format($child_product->price); ?> تومان)</span>
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
                                    <?php echo esc_html($grinder->name); ?> (<?php echo number_format($grinder->price); ?> تومان)
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
                    <span class="hpo-total-value" id="hpo-total-price"><?php echo number_format($product->get_price()); ?> تومان</span>
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
    
    /**
     * AJAX handler for adding products to cart with custom options
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('hpo_ajax_nonce', 'nonce');
        
        if (empty($_POST['hpo_data'])) {
            wp_send_json_error(array('message' => 'داده‌های نامعتبر.'));
            return;
        }
        
        // Parse the JSON data
        $data = json_decode(stripslashes($_POST['hpo_data']), true);
        
        if (!is_array($data) || empty($data['product_id'])) {
            wp_send_json_error(array('message' => 'داده‌های نامعتبر.'));
            return;
        }
        
        $product_id = absint($data['product_id']);
        $quantity = isset($data['quantity']) ? absint($data['quantity']) : 1;
        
        // Get product to get base price
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => 'محصول یافت نشد.'));
            return;
        }
        
        $base_price = $product->get_price();
        $total_price = $base_price;
        
        // Prepare the cart item data
        $cart_item_data = array(
            'hpo_custom_data' => array(
                'options' => array(),
                'weight' => array(),
                'grinding' => '',
                'grinding_machine' => array(),
                'customer_notes' => '',
                'base_price' => $base_price,
                'calculated_price' => 0,
                'price_per_unit' => 0
            ),
            // Generate a unique key to prevent merging
            'unique_key' => md5(microtime() . rand())
        );
        
        // Add options data if available
        if (!empty($data['hpo_options']) && is_array($data['hpo_options'])) {
            foreach ($data['hpo_options'] as $category_id => $option) {
                $cart_item_data['hpo_custom_data']['options'][] = array(
                    'category_id' => $category_id,
                    'option_id' => $option['id'],
                    'name' => $option['name'],
                    'price' => $option['price']
                );
                // Add option price to total
                $total_price += floatval($option['price']);
            }
        }
        
        // Add weight data if available
        if (!empty($data['hpo_weight'])) {
            $cart_item_data['hpo_custom_data']['weight'] = array(
                'id' => $data['hpo_weight']['id'],
                'name' => $data['hpo_weight']['name'],
                'coefficient' => $data['hpo_weight']['coefficient']
            );
            
            // Apply weight coefficient
            $coefficient = floatval($data['hpo_weight']['coefficient']);
            if ($coefficient > 0) {
                $total_price *= $coefficient;
            }
        }
        
        // Add grinding data if available
        if (!empty($data['hpo_grinding'])) {
            $cart_item_data['hpo_custom_data']['grinding'] = $data['hpo_grinding'];
            
            if ($data['hpo_grinding'] === 'ground' && !empty($data['hpo_grinding_machine'])) {
                $cart_item_data['hpo_custom_data']['grinding_machine'] = array(
                    'id' => $data['hpo_grinding_machine']['id'],
                    'name' => $data['hpo_grinding_machine']['name'],
                    'price' => $data['hpo_grinding_machine']['price']
                );
                
                // Add grinding machine price
                $total_price += floatval($data['hpo_grinding_machine']['price']);
            }
        }
        
        // Add customer notes if available
        if (!empty($data['hpo_customer_notes'])) {
            $cart_item_data['hpo_custom_data']['customer_notes'] = sanitize_textarea_field($data['hpo_customer_notes']);
        }
        
        // Store the price per unit and calculated price
        $cart_item_data['hpo_custom_data']['price_per_unit'] = $total_price;
        $cart_item_data['hpo_custom_data']['calculated_price'] = $total_price;
        
        // Remove any existing items with the same product ID and options before adding
        $cart = WC()->cart->get_cart();
        foreach ($cart as $cart_item_key => $cart_item) {
            if (isset($cart_item['product_id']) && $cart_item['product_id'] == $product_id && isset($cart_item['hpo_custom_data'])) {
                WC()->cart->remove_cart_item($cart_item_key);
            }
        }
        
        // Important: Set the product price to our calculated price before adding to cart
        // This ensures that WooCommerce will use this price for the item
        $product->set_price($total_price);
        
        // Add the product to the cart
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
        
        if ($cart_item_key) {
            wp_send_json_success(array(
                'message' => 'محصول با موفقیت به سبد خرید اضافه شد.',
                'cart_item_key' => $cart_item_key
            ));
        } else {
            wp_send_json_error(array('message' => 'خطا در افزودن محصول به سبد خرید.'));
        }
    }
    
    /**
     * Add custom data to cart item
     *
     * @param array $item_data Array of item data
     * @param array $cart_item Cart item data
     * @return array Modified item data
     */
    public function add_cart_item_custom_data($item_data, $cart_item) {
        // First, remove any default WooCommerce metadata that we handle ourselves
        foreach ($item_data as $key => $data) {
            if (isset($data['key']) && ($data['key'] === 'Grinding' || $data['key'] === 'Weight' || $data['key'] === 'گزینه' || 
                $data['key'] === 'وزن' || $data['key'] === 'آسیاب' || $data['key'] === 'وضعیت آسیاب' || 
                $data['key'] === 'قیمت محاسبه شده' || $data['key'] === 'grinding_display' || $data['key'] === 'weight_display')) {
                unset($item_data[$key]);
            }
        }
        
        // Then add our custom data in the exact format requested
        if (isset($cart_item['hpo_custom_data'])) {
            $custom_data = $cart_item['hpo_custom_data'];
            
            // We don't add the product options here anymore since they're shown in the product name
            
            // 1. Weight options
            if (!empty($custom_data['weight'])) {
                $item_data[] = array(
                    'key' => 'وزن',
                    'value' => $custom_data['weight']['name'],
                    'display' => $custom_data['weight']['name']
                );
            }
            
            // 2. Grinding status
            if (!empty($custom_data['grinding'])) {
                if ($custom_data['grinding'] === 'whole') {
                    $item_data[] = array(
                        'key' => 'وضعیت آسیاب',
                        'value' => 'بدون آسیاب',
                        'display' => 'بدون آسیاب'
                    );
                } else if ($custom_data['grinding'] === 'ground' && !empty($custom_data['grinding_machine'])) {
                    $grinding_text = 'آسیاب شود برای: ' . $custom_data['grinding_machine']['name'];
                    $item_data[] = array(
                        'key' => 'وضعیت آسیاب',
                        'value' => $grinding_text,
                        'display' => $grinding_text
                    );
                }
            }
            
            // 3. Customer notes (if any)
            if (!empty($custom_data['customer_notes'])) {
                $item_data[] = array(
                    'key' => 'توضیحات',
                    'value' => $custom_data['customer_notes'],
                    'display' => $custom_data['customer_notes']
                );
            }
        }
        
        // Clean up the array by reindexing
        return array_values($item_data);
    }
    
    /**
     * Calculate cart item prices
     *
     * @param WC_Cart $cart Cart object
     */
    public function calculate_cart_item_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Ensure this runs only once
        remove_filter('woocommerce_before_calculate_totals', array($this, 'calculate_cart_item_prices'), 10);
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['hpo_custom_data'])) {
                $custom_data = $cart_item['hpo_custom_data'];
                
                // If we have a price_per_unit, use it
                if (isset($custom_data['price_per_unit']) && $custom_data['price_per_unit'] > 0) {
                    // Set the price to the per-unit price so WooCommerce can calculate properly
                    $cart_item['data']->set_price($custom_data['price_per_unit']);
                    continue;
                }
                
                // If we have a pre-calculated price, use it
                if (isset($custom_data['calculated_price']) && $custom_data['calculated_price'] > 0) {
                    // Force update the product price using direct property access
                    // This ensures the price is always set correctly
                    $cart_item['data']->set_price($custom_data['calculated_price']);
                    continue;
                }
                
                // Otherwise calculate the price
                $base_price = isset($custom_data['base_price']) && $custom_data['base_price'] > 0 
                    ? $custom_data['base_price'] 
                    : $cart_item['data']->get_price();
                
                $total_price = $base_price;
                
                // Add option prices
                if (!empty($custom_data['options'])) {
                    foreach ($custom_data['options'] as $option) {
                        $total_price += floatval($option['price']);
                    }
                }
                
                // Apply weight coefficient
                if (!empty($custom_data['weight']) && isset($custom_data['weight']['coefficient'])) {
                    $coefficient = floatval($custom_data['weight']['coefficient']);
                    if ($coefficient > 0) {
                        $total_price *= $coefficient;
                    }
                }
                
                // Add grinding machine price
                if (!empty($custom_data['grinding']) && $custom_data['grinding'] === 'ground' 
                    && !empty($custom_data['grinding_machine']) && isset($custom_data['grinding_machine']['price'])) {
                    $total_price += floatval($custom_data['grinding_machine']['price']);
                }
                
                // Set the new price
                $cart_item['data']->set_price($total_price);
                
                // Store the calculated price and per-unit price for next time
                $cart->cart_contents[$cart_item_key]['hpo_custom_data']['calculated_price'] = $total_price;
                $cart->cart_contents[$cart_item_key]['hpo_custom_data']['price_per_unit'] = $total_price;
            }
        }
        
        // Re-add the filter for next time
        add_filter('woocommerce_before_calculate_totals', array($this, 'calculate_cart_item_prices'), 10, 1);
    }
    
    /**
     * Update cart item price
     *
     * @param string $price Cart item price
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified cart item price
     */
    public function update_cart_item_price($price, $cart_item, $cart_item_key) {
        // Get the price from custom data or product data
        $price_value = 0;
        
        if (isset($cart_item['hpo_custom_data']) && isset($cart_item['hpo_custom_data']['price_per_unit'])) {
            $price_value = $cart_item['hpo_custom_data']['price_per_unit'];
        } elseif (isset($cart_item['data'])) {
            $price_value = $cart_item['data']->get_price();
        }
        
        // Only update if we have a valid price
        if ($price_value > 0) {
            // Format the price with the exact HTML structure WooCommerce expects
            return sprintf(
                '<span class="woocommerce-Price-amount amount"><bdi>%s&nbsp;<span class="woocommerce-Price-currencySymbol">تومان</span></bdi></span>',
                number_format($price_value)
            );
        }
        
        return $price;
    }
    
    /**
     * Filter to change price display format for our popups
     *
     * @param string $price Price HTML
     * @param WC_Product $product Product object
     * @return string Modified price HTML
     */
    public function format_price_html($price, $product) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return $price;
        }
        
        if ($product->get_price() > 0) {
            $price_value = $product->get_price();
            return number_format($price_value) . ' تومان';
        }
        return $price;
    }
    
    /**
     * Ensure our custom data is preserved and products with options don't get merged in cart
     *
     * @param array $cart_item_data Cart item data
     * @param int $product_id Product ID
     * @return array Modified cart item data
     */
    public function prevent_cart_merging($cart_item_data, $product_id) {
        if (isset($cart_item_data['hpo_custom_data'])) {
            $cart_item_data['unique_key'] = md5(json_encode($cart_item_data) . microtime());
        }
        return $cart_item_data;
    }
    
    /**
     * Get cart item from session
     *
     * @param array $cart_item Cart item data
     * @param array $values Cart item values
     * @return array Modified cart item data
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['hpo_custom_data'])) {
            $cart_item['hpo_custom_data'] = $values['hpo_custom_data'];
        }
        return $cart_item;
    }
    
    /**
     * Setup cart item
     *
     * @param array $cart_item Cart item data
     * @return array Modified cart item data
     */
    public function setup_cart_item($cart_item) {
        // We don't map to sanitize_text_field anymore as it flattens arrays
        // Just ensure we have the correct data structure
        return $cart_item;
    }
    
    /**
     * Modify cart item name
     *
     * @param string $name Cart item name
     * @param array $cart_item Cart item data
     * @param array $cart_item_key Cart item key
     * @return string Modified cart item name
     */
    public function modify_cart_item_name($name, $cart_item, $cart_item_key) {
        if (isset($cart_item['hpo_custom_data']) && isset($cart_item['hpo_custom_data']['options'])) {
            $options = array();
            foreach ($cart_item['hpo_custom_data']['options'] as $option) {
                $options[] = $option['name'];
            }
            $name .= ' (' . implode(', ', $options) . ')';
        }
        return $name;
    }
    
    /**
     * Remove grinding metadata from cart item
     *
     * @param string $html The metadata HTML
     * @param array $item The cart item
     * @param array $args Arguments for the display
     * @return string Modified metadata HTML
     */
    public function remove_grinding_metadata($html, $item, $args) {
        // This is a simple approach to remove the Grinding metadata
        // We search for Grinding: Whole (No Grinding) or similar patterns
        $html = preg_replace('/<[^>]+>Grinding:.*?<\/[^>]+>/', '', $html);
        return $html;
    }
    
    /**
     * Modify mini cart quantity
     *
     * @param string $quantity Cart item quantity
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified cart item quantity
     */
    public function modify_mini_cart_quantity($quantity, $cart_item, $cart_item_key) {
        if (isset($cart_item['hpo_custom_data']) && isset($cart_item['hpo_custom_data']['price_per_unit']) && $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
            $price = $cart_item['hpo_custom_data']['price_per_unit'];
            $actual_quantity = $cart_item['quantity'];
            
            // Format: quantity × price تومان
            return $actual_quantity . ' × ' . number_format($price) . ' تومان';
        }
        return $quantity;
    }
    
    /**
     * Modify cart item quantity
     *
     * @param string $quantity Cart item quantity
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified cart item quantity
     */
    public function modify_cart_item_quantity($quantity_html, $cart_item, $cart_item_key) {
        // Only modify our custom products
        if (isset($cart_item['hpo_custom_data']) && isset($cart_item['hpo_custom_data']['price_per_unit']) && $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
            $price = $cart_item['hpo_custom_data']['price_per_unit'];
            $actual_quantity = $cart_item['quantity'];
            
            // Completely replace the default quantity HTML with our custom format
            return '<span class="hpo-custom-quantity">' . $actual_quantity . ' × ' . number_format($price) . ' تومان</span>';
        }
        
        return $quantity_html;
    }
    
    /**
     * Modify cart item quantity display
     *
     * @param string $quantity_html Quantity HTML
     * @param object $product Product object
     * @param array $cart_item_data Cart item data
     * @return string Modified quantity HTML
     */
    public function modify_cart_item_quantity_display($quantity_html, $product, $cart_item_data) {
        // Check if this is one of our custom products
        if (isset($cart_item_data['hpo_custom_data']) && isset($cart_item_data['hpo_custom_data']['price_per_unit']) && $cart_item_data['hpo_custom_data']['price_per_unit'] > 0) {
            $price = $cart_item_data['hpo_custom_data']['price_per_unit'];
            $actual_quantity = $cart_item_data['quantity'];
            
            // Create a custom display format
            return '<span class="hpo-custom-quantity">' . $actual_quantity . ' × ' . number_format($price) . ' تومان</span>';
        }
        
        return $quantity_html;
    }
    
    /**
     * Add custom CSS for cart display
     */
    public function add_custom_cart_css() {
        ?>
        <style type="text/css">
            /* Hide the default price display and replace with our custom one */
            .woocommerce-mini-cart span.quantity {
                display: block !important;
            }
            
            /* Custom styling for our quantity display */
            span.hpo-custom-quantity {
                display: block;
                font-weight: bold;
                margin-top: 5px;
            }
            
            /* Fix the product price in cart by ensuring the woocommerce-Price-amount shows our custom price */
            .woocommerce-cart .woocommerce-Price-amount.amount {
                display: none;
            }
            
            /* Show our custom price format instead */
            span.hpo-custom-quantity {
                display: block !important;
            }
            
            /* Style the span so it looks like the WooCommerce price */
            .hpo-custom-quantity {
                font-weight: bold;
                color: #333;
            }

            /* Hide the add to cart button in loop products */
            .add_to_cart_button {
                display: none;
            }
        </style>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Function to update price display
                function updateCartPriceDisplay() {
                    // Check if we're on a cart page
                    if ($('.woocommerce-cart-form').length > 0) {
                        // Find all custom quantity elements and extract their price
                        $('.hpo-custom-quantity').each(function() {
                            var priceText = $(this).text();
                            var priceMatch = priceText.match(/(\d+) × ([\d,]+) تومان/);
                            
                            if (priceMatch && priceMatch.length >= 3) {
                                var quantity = parseInt(priceMatch[1]);
                                var unitPrice = priceMatch[2].replace(/,/g, '');
                                var totalPrice = quantity * parseInt(unitPrice);
                                
                                // Find the nearby price amount and update it
                                var $row = $(this).closest('tr');
                                $row.find('.product-price .woocommerce-Price-amount').html('<span class="woocommerce-Price-currencySymbol"></span>' + numberWithCommas(unitPrice) + ' تومان');
                                $row.find('.product-subtotal .woocommerce-Price-amount').html('<span class="woocommerce-Price-currencySymbol"></span>' + numberWithCommas(totalPrice) + ' تومان');
                            }
                        });
                    }
                }
                
                // Helper function to format numbers with commas
                function numberWithCommas(x) {
                    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                }
                
                // Run on page load
                updateCartPriceDisplay();
                
                // Run whenever cart is updated
                $(document.body).on('updated_cart_totals', updateCartPriceDisplay);
                $(document.body).on('wc_fragments_refreshed', updateCartPriceDisplay);
            });
        </script>
        <?php
    }

    /**
     * Filter product price to ensure it's correct in cart
     *
     * @param float $price The product price
     * @param object $product The product object
     * @return float Modified price
     */
    public function filter_product_price($price, $product) {
        // Only apply on cart/checkout pages
        if (is_cart() || is_checkout()) {
            // Get the cart
            $cart = WC()->cart;
            if (!$cart) {
                return $price;
            }
            
            // Get the product ID
            $product_id = $product->get_id();
            
            // Check each cart item
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                // Find matching product
                if ($cart_item['product_id'] == $product_id || 
                    (isset($cart_item['variation_id']) && $cart_item['variation_id'] == $product_id)) {
                    
                    // If we have custom data with a calculated price, use it
                    if (isset($cart_item['hpo_custom_data']) && 
                        isset($cart_item['hpo_custom_data']['price_per_unit']) && 
                        $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                        
                        // Return our custom calculated price
                        return $cart_item['hpo_custom_data']['price_per_unit'];
                    }
                }
            }
        }
        
        return $price;
    }
}

// Initialize the shortcodes
new HPO_Shortcodes(); 