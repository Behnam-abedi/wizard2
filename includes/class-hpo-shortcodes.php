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
        // Add shortcodes
        add_shortcode('hpo_order_button', array($this, 'order_button_shortcode'));
        
        // Register AJAX actions
        add_action('wp_ajax_hpo_load_products', array($this, 'ajax_load_products'));
        add_action('wp_ajax_nopriv_hpo_load_products', array($this, 'ajax_load_products'));
        add_action('wp_ajax_hpo_load_product_details', array($this, 'ajax_load_product_details'));
        add_action('wp_ajax_nopriv_hpo_load_product_details', array($this, 'ajax_load_product_details'));
        add_action('wp_ajax_hpo_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_hpo_add_to_cart', array($this, 'ajax_add_to_cart'));
        
        // Add cart hooks
        add_filter('woocommerce_get_item_data', array($this, 'add_cart_item_custom_data'), 100, 2);
        add_filter('woocommerce_cart_item_price', array($this, 'update_cart_item_price'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'update_cart_item_price'), 10, 3);
        add_filter('woocommerce_before_calculate_totals', array($this, 'calculate_cart_item_prices'), 10, 1);
        
        // Add for price display
        add_filter('woocommerce_get_price_html', array($this, 'format_price_html'), 100, 2);
        
        // Prevent cart item merging
        add_filter('woocommerce_add_cart_item_data', array($this, 'prevent_cart_merging'), 10, 2);
        add_filter('woocommerce_add_cart_item', array($this, 'setup_cart_item'), 10, 1);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        
        // Cart item display
        add_filter('woocommerce_cart_item_name', array($this, 'modify_cart_item_name'), 10, 3);
        add_filter('woocommerce_display_item_meta', array($this, 'remove_grinding_metadata'), 10, 3);
        
        // Cart item quantity display
        add_filter('woocommerce_widget_cart_item_quantity', array($this, 'modify_mini_cart_quantity'), 10, 3);
        add_filter('woocommerce_cart_item_quantity', array($this, 'modify_cart_item_quantity'), 10, 3);
        add_filter('woocommerce_cart_item_quantity_display', array($this, 'modify_cart_item_quantity_display'), 10, 3);
        
        // Filter product prices
        add_filter('woocommerce_product_get_price', array($this, 'filter_product_price'), 99, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'filter_product_price'), 99, 2);
        
        // Order item meta data
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_meta'), 10, 4);
        
        // Add a hook to ensure our price calculation runs even when cached
        add_action('woocommerce_cart_loaded_from_session', array($this, 'fix_cart_after_session_load'), 30);
        
        // Make sure cart fragments get the correct prices
        add_filter('woocommerce_cart_fragment_name', function($name) {
            return $name . '_hpo_custom';
        });
        
        // Add CSS/JS to pages
        add_action('wp_footer', array($this, 'add_custom_cart_css'));
        
        // Ensure scripts are loaded
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
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
        wp_enqueue_script(
            'hpo-scroll-scripts',
            HPO_PLUGIN_URL . 'public/js/scroll.js',
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
                <div class="hpo-back-button" id="hpo-popup-close">
                    <span>بستن</span>
                </div>

                    
                    <div class="hpo-header-price">
                        <i class="hpo-cart-icon"></i>
                        <span class="hpo-total-value" id="hpo-total-price">0 تومان</span>
                    </div>
                </div>
                <div class="hpo-popup-content">
                    <div class="hpo-product-list" id="hpo-product-list">
                        <span class="hpo-product-list-title">محصولات</span>
                        <!-- Products will be loaded here via AJAX -->
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
        $product_descriptions = array();
        
        foreach ($assignments as $assignment) {
            if (!in_array($assignment->wc_product_id, $product_ids)) {
                $product_ids[] = $assignment->wc_product_id;
                // Store the description if available
                if (!empty($assignment->short_description)) {
                    $product_descriptions[$assignment->wc_product_id] = $assignment->short_description;
                }
            }
        }
        
        ob_start();
        
        if (empty($product_ids)) {
            echo '<p>هیچ محصولی با گزینه‌های سلسله مراتبی یافت نشد.</p>';
        } else {
            // اضافه کردن متن راهنما با استایل خاص در بالای صفحه محصولات
            echo '<div class="hpo-product-intro-message">
<p>یک دسته قهوه را انتخاب کنید و برای دیدن گزینه‌های بیشتر، «مرحله بعد» را بزنید.</p>            </div>';
            
            echo '<div class="hpo-products-grid">';
            
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                
                if ($product) {
                    ?>
                    <div class="hpo-product-item" data-product-id="<?php echo esc_attr($product_id); ?>">
                        <div class="hpo-product-info">
                            <h4><?php echo esc_html($product->get_name()); ?></h4>
                            <?php
                            // Use our custom short description if available, otherwise use product short description
                            if (isset($product_descriptions[$product_id])) {
                                echo '<div class="hpo-product-description">' . esc_html($product_descriptions[$product_id]) . '</div>';
                            } else {
                                $description = $product->get_short_description();
                                if (!empty($description)) {
                                    echo '<div class="hpo-product-description">' . wp_kses_post($description) . '</div>';
                                } else {
                                    echo '<div class="hpo-product-description">بدون توضیحات</div>';
                                }
                            }
                            ?>
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
        $category_paths = array(); // Store category paths for display
        
        // Get the product name for the category paths
        $product_name = $product->get_name();
        
        foreach ($categories as $category) {
            if ($category->parent_id == 0) {
                $parent_categories[] = $category;
                // For parent categories, add product name before category name
                $category_paths[$category->id] = $product_name . ' > ' . $category->name;
            } else {
                $child_categories[$category->parent_id][] = $category;
                
                // Get all parent categories to build the full path
                $parents = $db->get_parent_categories($category->id);
                
                // Build path from product name to deepest parent to current category
                $parent_names = array($product_name); // Start with product name
                
                if (!empty($parents)) {
                    foreach (array_reverse($parents) as $parent) {
                        $parent_names[] = $parent->name;
                    }
                }
                $parent_names[] = $category->name;
                $path = implode(' > ', $parent_names);
                
                $category_paths[$category->id] = $path;
            }
        }
        
        // Get weight options
        $weights = $db->get_weights_for_product($product_id);
        
        ob_start();
        ?>
        <div class="hpo-product-details">

            
            <form class="hpo-product-options-form">
                <span class="header-title"><?php echo esc_attr($product_name) ?></span>
                <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                <input type="hidden" name="hpo_base_price" value="<?php echo esc_attr($product->get_price()); ?>">
                <div class="description-title">
                    <span>در لیست زیر می‌توانید محصول موردنظر خود را از دسته قهوه‌های <?php echo esc_attr($product_name) ?> انتخاب کرده و سپس وزن و وضعیت آسیاب موردنظر را برای ثبت سفارش تعیین کنید.</span>
                </div>
                <?php if (!empty($parent_categories)): ?>
                <div class="hpo-option-section">
                    <!-- <h3>گزینه‌های محصول</h3> -->
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
                                    <div class="hpo-product-option-header">
                                        <?php echo esc_html($category_paths[$parent->id]); ?>

                                    </div>
                                    
                                    <label>
                                        <div class="hpo-product-option-name-price">
                                            <input type="radio" name="hpo_option[<?php echo esc_attr($parent->id); ?>]" 
                                                   value="<?php echo esc_attr($opt_product->id); ?>" 
                                                   data-price="<?php echo esc_attr($opt_product->price); ?>">
                                            <span class="hpo-option-name"><?php echo esc_html($opt_product->name); ?></span>
                                        </div>
                                        <span class="hpo-option-price"><?php echo number_format($opt_product->price); ?> تومان</span>
                                    </label>
                                    <div class="hpo-product-option-description">
                                        <?php echo !empty($opt_product->description) ? esc_html($opt_product->description) : '<span class="no-description">بدون توضیحات</span>'; ?>
                                    </div>
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
                                            <div class="hpo-product-option-header">
                                                <?php echo esc_html($category_paths[$child->id]); ?>
                                            </div>
                                            <label>
                                                <div class="hpo-product-option-name-price">
                                                    <input type="radio" name="hpo_option[<?php echo esc_attr($child->id); ?>]" 
                                                           value="<?php echo esc_attr($child_product->id); ?>" 
                                                           data-price="<?php echo esc_attr($child_product->price); ?>">
                                                    <span class="hpo-option-name"><?php echo esc_html($child_product->name); ?></span>
                                                </div>
                                                <span class="hpo-option-price"><?php echo number_format($child_product->price); ?> تومان</span>
                                            </label>
                                            <div class="hpo-product-option-description">
                                                <?php echo !empty($child_product->description) ? esc_html($child_product->description) : '<span class="no-description">بدون توضیحات</span>'; ?>
                                            </div>
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
                
                <div class="hpo-weight-section" id="weight-section">
                    <h3>انتخاب وزن محصول</h3>
                    <span class="description-weight-grinding">از بین یکی از گزینه های زیر وزن سفارش مورد نظر خود را انتخاب کنید</span>
                    <div class="hpo-weight-grid">
                        <?php foreach ($weights as $weight): ?>
                        <div class="hpo-weight-item">
                            <label>
                                <input type="radio" name="hpo_weight" 
                                       value="<?php echo esc_attr($weight->id); ?>" 
                                       data-coefficient="<?php echo esc_attr($weight->coefficient); ?>">
                                <span class="hpo-weight-text"><?php echo esc_html($weight->name); ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="hpo-option-section hpo-grinding-options-section">
                    <h3>وضعیت آسیاب</h3>
                    <span class="description-weight-grinding">در صورتی که مایل به آسیاب شدن محصول مورد نظر هستید دستگاه اسپرسو ساز خود را برای ما مشخص کنید</span>

                    <div class="hpo-grinding-options">
                        <div class="hpo-grinding-toggle">
                            <div class="hpo-toggle-container" data-active="whole">
                                <div class="hpo-toggle-slider"></div>
                                <div class="hpo-toggle-option whole active" data-value="whole">بدون آسیاب</div>
                                <div class="hpo-toggle-option ground" data-value="ground">آسیاب</div>
                            </div>
                            <input type="hidden" name="hpo_grinding" value="whole">
                        </div>
                        
                        <div class="hpo-grinding-machines">
                            <label for="hpo-grinding-machine">دستگاه خود را از لیست زیر انتخاب کنید</label>
                            <select name="hpo_grinding_machine" id="hpo-grinding-machine">
                                <option value="">دستگاه خود را انتخاب کنید</option>
                                <?php 
                                $grinders = $db->get_all_grinders();
                                foreach ($grinders as $grinder): 
                                    // Ensure price is a numeric value
                                    $grinder_price = floatval($grinder->price);
                                ?>
                                <option value="<?php echo esc_attr($grinder->id); ?>" 
                                        data-price="<?php echo esc_attr($grinder_price); ?>">
                                    <?php echo esc_html($grinder->name); ?> (<?php echo number_format($grinder_price); ?> تومان)
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

                </div>
                
                <!-- <div class="hpo-total-price">
                    <span class="hpo-total-label">قیمت نهایی:</span>
                    <span class="hpo-total-value" id="hpo-total-price"><?php echo number_format($product->get_price()); ?> تومان</span>
                </div> -->
                
                <div class="hpo-add-to-cart">
                    <button type="submit" class="hpo-add-to-cart-button">افزودن به سبد خرید</button>
                    <div class="hpo-quantity-input">
                        <button type="button" class="hpo-quantity-plus">+</button>
                        <input type="number" name="quantity" value="1" min="1" max="99" readonly>
                        <button type="button" class="hpo-quantity-minus">-</button>
                        <!-- <h3>تعداد</h3> -->

                    </div>
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
        
        // Use the pre-calculated price if provided
        if (!empty($data['hpo_calculated_price'])) {
            $total_price = floatval($data['hpo_calculated_price']);
            if ($total_price <= 0) {
                // Fallback to base price if calculated price is invalid
                $total_price = floatval($product->get_price());
            }
        } else {
            // Manually calculate the total price
            $base_price = floatval($product->get_price());
            $total_price = $base_price;
            
            // Add options prices
            if (!empty($data['hpo_options']) && is_array($data['hpo_options'])) {
                foreach ($data['hpo_options'] as $option) {
                    if (isset($option['price'])) {
                        $total_price += floatval($option['price']);
                    }
                }
            }
            
            // Apply weight coefficient
            if (!empty($data['hpo_weight']) && isset($data['hpo_weight']['coefficient'])) {
                $coefficient = floatval($data['hpo_weight']['coefficient']);
                if ($coefficient > 0) {
                    $total_price *= $coefficient;
                }
            }
            
            // Add grinding machine price
            if (!empty($data['hpo_grinding']) && $data['hpo_grinding'] === 'ground' 
                && !empty($data['hpo_grinding_machine']) && isset($data['hpo_grinding_machine']['price'])) {
                $total_price += floatval($data['hpo_grinding_machine']['price']);
            }
        }
        
        // Ensure price is valid
        $total_price = max(1, $total_price); // Minimum price of 1 to avoid zero price
        
        // Prepare the cart item data
        $cart_item_data = array(
            'hpo_custom_data' => array(
                'options' => array(),
                'weight' => array(),
                'grinding' => '',
                'grinding_machine' => array(),
                'customer_notes' => '',
                'base_price' => floatval($product->get_price()),
                'calculated_price' => $total_price,
                'price_per_unit' => $total_price, // Set both price fields to the same value
                'custom_price' => $total_price    // Added a third field for redundancy
            ),
        );
        
        // Add options data if available
        if (!empty($data['hpo_options']) && is_array($data['hpo_options'])) {
            foreach ($data['hpo_options'] as $category_id => $option) {
                $cart_item_data['hpo_custom_data']['options'][] = array(
                    'category_id' => $category_id,
                    'option_id' => $option['id'],
                    'name' => $option['name'],
                    'price' => floatval($option['price'])
                );
            }
        }
        
        // Add weight data if available
        if (!empty($data['hpo_weight'])) {
            $cart_item_data['hpo_custom_data']['weight'] = array(
                'id' => $data['hpo_weight']['id'],
                'name' => $data['hpo_weight']['name'],
                'coefficient' => floatval($data['hpo_weight']['coefficient'])
            );
        }
        
        // Add grinding data if available
        if (!empty($data['hpo_grinding'])) {
            $cart_item_data['hpo_custom_data']['grinding'] = $data['hpo_grinding'];
            
            if ($data['hpo_grinding'] === 'ground' && !empty($data['hpo_grinding_machine'])) {
                $cart_item_data['hpo_custom_data']['grinding_machine'] = array(
                    'id' => $data['hpo_grinding_machine']['id'],
                    'name' => $data['hpo_grinding_machine']['name'],
                    'price' => floatval($data['hpo_grinding_machine']['price'])
                );
            }
        }
        
        // Add customer notes if available
        if (!empty($data['hpo_customer_notes'])) {
            $cart_item_data['hpo_custom_data']['customer_notes'] = sanitize_textarea_field($data['hpo_customer_notes']);
        }
        
        // Create a unique key for this specific product configuration
        $unique_key = md5(
            $product_id . '_' . 
            json_encode(isset($data['hpo_options']) ? $data['hpo_options'] : []) . '_' . 
            json_encode(isset($data['hpo_weight']) ? $data['hpo_weight'] : []) . '_' . 
            $data['hpo_grinding'] . '_' . 
            json_encode(isset($data['hpo_grinding_machine']) ? $data['hpo_grinding_machine'] : [])
        );
        $cart_item_data['unique_key'] = $unique_key;
        
        // Set a meta key in the cart item data to flag this as a custom price item
        $cart_item_data['_hpo_custom_price_item'] = 'yes';
        
        // Set the price everywhere it might be needed
        $cart_item_data['data_price'] = $total_price;
        $cart_item_data['hpo_total_price'] = $total_price;
        
        // Important: Set the product price to our calculated price before adding to cart
        // This ensures that WooCommerce will use this price for the item
        $product->set_price($total_price);
        
        // Also update the _price meta to ensure consistency
        $product->update_meta_data('_price', $total_price);
        
        // Add the product to the cart
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
        
        if ($cart_item_key) {
            // Get the cart item and update its price directly
            $cart_contents = WC()->cart->get_cart_contents();
            if (isset($cart_contents[$cart_item_key])) {
                // Set the price directly on the product object
                $cart_contents[$cart_item_key]['data']->set_price($total_price);
                
                // Update the meta data
                $cart_contents[$cart_item_key]['data']->update_meta_data('_price', $total_price);
                $cart_contents[$cart_item_key]['data']->update_meta_data('_hpo_custom_price', $total_price);
                
                // Ensure our custom data is also stored in the cart item
                $cart_contents[$cart_item_key]['hpo_custom_data']['calculated_price'] = $total_price;
                $cart_contents[$cart_item_key]['hpo_custom_data']['price_per_unit'] = $total_price;
                $cart_contents[$cart_item_key]['hpo_custom_data']['custom_price'] = $total_price;
            }
            
            // Force WooCommerce to recalculate totals
            WC()->cart->calculate_totals();
            
            // Generate HTML for success popup
            ob_start();
            ?>
            <div class="hpo-success-popup-overlay">
                <div class="hpo-success-popup">
                    <div class="hpo-success-icon">
                        <svg viewBox="0 0 24 24" width="50" height="50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22 11.0857V12.0057C21.9988 14.1621 21.3005 16.2604 20.0093 17.9875C18.7182 19.7147 16.9033 20.9782 14.8354 21.5896C12.7674 22.201 10.5573 22.1276 8.53447 21.3803C6.51168 20.633 4.78465 19.2518 3.61096 17.4428C2.43727 15.6338 1.87979 13.4938 2.02168 11.342C2.16356 9.19029 2.99721 7.14205 4.39828 5.5028C5.79935 3.86354 7.69279 2.72111 9.79619 2.24587C11.8996 1.77063 14.1003 1.98806 16.07 2.86572" stroke="#25AE88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M22 4L12 14.01L9 11.01" stroke="#25AE88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3>سفارش با موفقیت به سبد خرید اضافه شد</h3>
                    <div class="hpo-success-actions">
                        <button class="hpo-repeat-order-btn">سفارش جدید</button>
                        <button class="hpo-checkout-btn">پرداخت سفارش</button>
                        <button class="hpo-close-success-btn">بستن</button>
                    </div>
                </div>
            </div>
            <script>
            console.log("HPO: Product added to cart with ID: <?php echo esc_js($product_id); ?>");
            console.log("HPO: Calculated price: <?php echo esc_js($total_price); ?>");
            console.log("HPO: Cart item key: <?php echo esc_js($cart_item_key); ?>");
            </script>
            <?php
            $success_popup_html = ob_get_clean();
            
            wp_send_json_success(array(
                'message' => 'محصول با موفقیت به سبد خرید اضافه شد.',
                'cart_item_key' => $cart_item_key,
                'price' => $total_price,
                'success_popup_html' => $success_popup_html
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
        
        // Debug info
        $debug_info = [];
        
        // Ensure this runs only once
        remove_filter('woocommerce_before_calculate_totals', array($this, 'calculate_cart_item_prices'), 10);
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Skip if this is not our custom item
            if (!isset($cart_item['hpo_custom_data'])) {
                continue;
            }
            
            $custom_data = $cart_item['hpo_custom_data'];
            $price_updated = false;
            $final_price = 0;
            
            // Priority 1: Check if we have a price_per_unit
            if (isset($custom_data['price_per_unit']) && $custom_data['price_per_unit'] > 0) {
                $final_price = floatval($custom_data['price_per_unit']);
                $price_updated = true;
                $debug_info[] = "Cart item {$cart_item_key}: Using price_per_unit: {$final_price}";
            } 
            // Priority 2: Check calculated_price 
            else if (isset($custom_data['calculated_price']) && $custom_data['calculated_price'] > 0) {
                $final_price = floatval($custom_data['calculated_price']);
                $price_updated = true;
                $debug_info[] = "Cart item {$cart_item_key}: Using calculated_price: {$final_price}";
            }
            // Priority 3: Check custom_price
            else if (isset($custom_data['custom_price']) && $custom_data['custom_price'] > 0) {
                $final_price = floatval($custom_data['custom_price']);
                $price_updated = true;
                $debug_info[] = "Cart item {$cart_item_key}: Using custom_price: {$final_price}";
            }
            // Priority 4: Check direct cart item price properties
            else if (isset($cart_item['data_price']) && $cart_item['data_price'] > 0) {
                $final_price = floatval($cart_item['data_price']);
                $price_updated = true;
                $debug_info[] = "Cart item {$cart_item_key}: Using data_price: {$final_price}";
            }
            else if (isset($cart_item['hpo_total_price']) && $cart_item['hpo_total_price'] > 0) {
                $final_price = floatval($cart_item['hpo_total_price']);
                $price_updated = true;
                $debug_info[] = "Cart item {$cart_item_key}: Using hpo_total_price: {$final_price}";
            }
            
            // If we haven't found a price yet, calculate it from scratch
            if (!$price_updated) {
                $debug_info[] = "Cart item {$cart_item_key}: Calculating price from scratch";
                $base_price = 0;
                
                // Get base price
                if (isset($custom_data['base_price']) && floatval($custom_data['base_price']) > 0) {
                    $base_price = floatval($custom_data['base_price']);
                } else {
                    // Fallback to the product's regular price if base_price is not set
                    $base_price = floatval($cart_item['data']->get_regular_price());
                }
                
                // Ensure we have a valid base price
                $base_price = max(0, $base_price);
                $final_price = $base_price;
                $debug_info[] = "   Base price: {$base_price}";
                
                // Add option prices
                if (!empty($custom_data['options'])) {
                    $options_total = 0;
                    foreach ($custom_data['options'] as $option) {
                        if (isset($option['price'])) {
                            $option_price = floatval($option['price']);
                            if ($option_price > 0) {
                                $options_total += $option_price;
                            }
                        }
                    }
                    $final_price += $options_total;
                    $debug_info[] = "   Added options: +{$options_total}";
                }
                
                // Apply weight coefficient
                if (!empty($custom_data['weight']) && isset($custom_data['weight']['coefficient'])) {
                    $coefficient = floatval($custom_data['weight']['coefficient']);
                    if ($coefficient > 0) {
                        $before_coef = $final_price;
                        $final_price *= $coefficient;
                        $debug_info[] = "   Applied weight coefficient {$coefficient}: {$before_coef} -> {$final_price}";
                    }
                }
                
                // Add grinding machine price
                if (!empty($custom_data['grinding']) && $custom_data['grinding'] === 'ground' 
                    && !empty($custom_data['grinding_machine']) && isset($custom_data['grinding_machine']['price'])) {
                    $grinding_price = floatval($custom_data['grinding_machine']['price']);
                    if ($grinding_price > 0) {
                        $final_price += $grinding_price;
                        $debug_info[] = "   Added grinding price: +{$grinding_price}";
                    }
                }
            }
            
            // Ensure final price is valid
            $final_price = max(1, $final_price);
            
            // Set the price everywhere it might be needed for redundancy
            
            // 1. Set price directly on the product object
            $cart_item['data']->set_price($final_price);
            $cart_item['data']->update_meta_data('_price', $final_price);
            $cart_item['data']->update_meta_data('_hpo_custom_price', $final_price);
            
            // 2. Update our custom data fields
            $cart->cart_contents[$cart_item_key]['hpo_custom_data']['calculated_price'] = $final_price;
            $cart->cart_contents[$cart_item_key]['hpo_custom_data']['price_per_unit'] = $final_price;
            $cart->cart_contents[$cart_item_key]['hpo_custom_data']['custom_price'] = $final_price;
            
            // 3. Set direct cart item properties
            $cart->cart_contents[$cart_item_key]['data_price'] = $final_price;
            $cart->cart_contents[$cart_item_key]['hpo_total_price'] = $final_price;
            $cart->cart_contents[$cart_item_key]['_hpo_custom_price_item'] = 'yes';
            
            $debug_info[] = "Final price set to: {$final_price}";
        }
        
        // Add a script to log debug info to console
        add_action('wp_footer', function() use ($debug_info) {
            if (empty($debug_info)) return;
            echo '<script>
                console.log("HPO DEBUG - Price Calculation --------");
                ' . implode("\n", array_map(function($info) { 
                    return 'console.log("' . esc_js($info) . '");'; 
                }, $debug_info)) . '
            </script>';
        }, 100);
        
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
        // Debug info
        $debug_items = [];
        $debug_items[] = "HPO Update Cart Item Price for key: {$cart_item_key}";
        
        // Priority for price sources:
        // 1. Custom data price_per_unit
        // 2. Custom data calculated_price
        // 3. Custom data custom_price
        // 4. Direct cart item properties
        // 5. Product object price
        // 6. Meta data
        $price_value = 0;
        
        // Priority 1: Custom data price_per_unit
        if (isset($cart_item['hpo_custom_data']) && 
            isset($cart_item['hpo_custom_data']['price_per_unit']) && 
            $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
            
            $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
            $debug_items[] = "Using price_per_unit: {$price_value}";
        } 
        // Priority 2: Custom data calculated_price
        elseif (isset($cart_item['hpo_custom_data']) && 
                isset($cart_item['hpo_custom_data']['calculated_price']) && 
                $cart_item['hpo_custom_data']['calculated_price'] > 0) {
            
            $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
            $debug_items[] = "Using calculated_price: {$price_value}";
        }
        // Priority 3: Custom data custom_price
        elseif (isset($cart_item['hpo_custom_data']) && 
                isset($cart_item['hpo_custom_data']['custom_price']) && 
                $cart_item['hpo_custom_data']['custom_price'] > 0) {
            
            $price_value = floatval($cart_item['hpo_custom_data']['custom_price']);
            $debug_items[] = "Using custom_price: {$price_value}";
        }
        // Priority 4: Direct cart item properties
        elseif (isset($cart_item['data_price']) && $cart_item['data_price'] > 0) {
            $price_value = floatval($cart_item['data_price']);
            $debug_items[] = "Using data_price: {$price_value}";
        }
        elseif (isset($cart_item['hpo_total_price']) && $cart_item['hpo_total_price'] > 0) {
            $price_value = floatval($cart_item['hpo_total_price']);
            $debug_items[] = "Using hpo_total_price: {$price_value}";
        }
        // Priority 5: Product object price
        elseif (isset($cart_item['data'])) {
            $price_value = floatval($cart_item['data']->get_price());
            $debug_items[] = "Using product price: {$price_value}";
            
            // If product price is still zero, try to get it from meta
            if ($price_value <= 0) {
                // Try _hpo_custom_price first
                $price_meta = $cart_item['data']->get_meta('_hpo_custom_price');
                if ($price_meta && floatval($price_meta) > 0) {
                    $price_value = floatval($price_meta);
                    $debug_items[] = "Using _hpo_custom_price meta: {$price_value}";
                } else {
                    // Then try _price
                    $price_meta = $cart_item['data']->get_meta('_price');
                    if ($price_meta && floatval($price_meta) > 0) {
                        $price_value = floatval($price_meta);
                        $debug_items[] = "Using _price meta: {$price_value}";
                    }
                }
            }
        }
        
        // Only update if we have a valid price
        if ($price_value > 0) {
            // If we're on a cart page, update the data object to ensure consistent pricing
            if (is_cart() || is_checkout()) {
                WC()->cart->cart_contents[$cart_item_key]['data']->set_price($price_value);
                WC()->cart->cart_contents[$cart_item_key]['data']->update_meta_data('_price', $price_value);
                $debug_items[] = "Updated product object with price: {$price_value}";
                
                // Also update our custom data for consistency
                if (isset(WC()->cart->cart_contents[$cart_item_key]['hpo_custom_data'])) {
                    WC()->cart->cart_contents[$cart_item_key]['hpo_custom_data']['price_per_unit'] = $price_value;
                    WC()->cart->cart_contents[$cart_item_key]['hpo_custom_data']['calculated_price'] = $price_value;
                    WC()->cart->cart_contents[$cart_item_key]['hpo_custom_data']['custom_price'] = $price_value;
                }
            }
            
            // Format the price with the exact HTML structure WooCommerce expects
            $formatted_price = sprintf(
                '<span class="woocommerce-Price-amount amount"><bdi>%s&nbsp;<span class="woocommerce-Price-currencySymbol">تومان</span></bdi></span>',
                number_format($price_value)
            );
            
            // Debug log
            $debug_items[] = "Returning formatted price for {$price_value}";
            $debug_str = implode(" | ", $debug_items);
            
            // Output debug info
            add_action('wp_footer', function() use ($debug_str) {
                echo '<script>console.log("' . esc_js($debug_str) . '");</script>';
            }, 999);
            
            return $formatted_price;
        }
        
        // Fallback to default price format if our price is zero or invalid
        $debug_items[] = "Fallback to default price: {$price}";
        $debug_str = implode(" | ", $debug_items);
        
        // Output debug info for fallback
        add_action('wp_footer', function() use ($debug_str) {
            echo '<script>console.log("' . esc_js($debug_str) . '");</script>';
        }, 999);
        
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
            // Create a unique key based on product ID and selected options
            $options = isset($cart_item_data['hpo_custom_data']['options']) ? $cart_item_data['hpo_custom_data']['options'] : [];
            $weight = isset($cart_item_data['hpo_custom_data']['weight']) ? $cart_item_data['hpo_custom_data']['weight'] : [];
            $grinding = isset($cart_item_data['hpo_custom_data']['grinding']) ? $cart_item_data['hpo_custom_data']['grinding'] : 'whole';
            $grinding_machine = isset($cart_item_data['hpo_custom_data']['grinding_machine']) ? $cart_item_data['hpo_custom_data']['grinding_machine'] : [];
            
            $unique_key = md5(
                $product_id . '_' . 
                json_encode($options) . '_' . 
                json_encode($weight) . '_' . 
                $grinding . '_' . 
                json_encode($grinding_machine)
            );
            
            $cart_item_data['unique_key'] = $unique_key;
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
            
            // Get the price with priority order
            $price = 0;
            if (isset($values['hpo_custom_data']['price_per_unit']) && $values['hpo_custom_data']['price_per_unit'] > 0) {
                $price = floatval($values['hpo_custom_data']['price_per_unit']);
            } elseif (isset($values['hpo_custom_data']['calculated_price']) && $values['hpo_custom_data']['calculated_price'] > 0) {
                $price = floatval($values['hpo_custom_data']['calculated_price']);
            } elseif (isset($values['hpo_custom_data']['custom_price']) && $values['hpo_custom_data']['custom_price'] > 0) {
                $price = floatval($values['hpo_custom_data']['custom_price']);
            }
            
            // Apply the custom price to the product object if we have a valid price
            if ($price > 0 && isset($cart_item['data'])) {
                // Set the price directly on the product object
                $cart_item['data']->set_price($price);
                
                // Update the meta data
                $cart_item['data']->update_meta_data('_price', $price);
                $cart_item['data']->update_meta_data('_hpo_custom_price', $price);
                
                // Set important flags
                $cart_item['_hpo_custom_price_item'] = 'yes';
                $cart_item['data_price'] = $price;
                $cart_item['hpo_total_price'] = $price;
                
                // Log to console for debugging
                add_action('wp_footer', function() use ($price, $cart_item) {
                    echo '<script>console.log("HPO: Session item restored with price: ' . esc_js($price) . '");</script>';
                }, 99);
            }
            
            // If the object has a unique key, preserve it
            if (isset($values['unique_key'])) {
                $cart_item['unique_key'] = $values['unique_key'];
            }
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
        // Apply the custom price to the product object if we have custom data
        if (isset($cart_item['hpo_custom_data']) && !empty($cart_item['data'])) {
            // Check if we have a calculated price
            if (isset($cart_item['hpo_custom_data']['price_per_unit']) && 
                $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                
                $calculated_price = floatval($cart_item['hpo_custom_data']['price_per_unit']);
                // Set the price directly on the product object
                $cart_item['data']->set_price($calculated_price);
            }
        }
        
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
        // Get the price using the same priority as update_cart_item_price
        $price_value = 0;
        
        if (isset($cart_item['hpo_custom_data'])) {
            if (isset($cart_item['hpo_custom_data']['price_per_unit']) && 
                $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
            } elseif (isset($cart_item['hpo_custom_data']['calculated_price']) && 
                     $cart_item['hpo_custom_data']['calculated_price'] > 0) {
                $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
            }
        }
        
        // If no custom price found, use the product price
        if ($price_value <= 0 && isset($cart_item['data'])) {
            $price_value = floatval($cart_item['data']->get_price());
            
            // If product price is still zero, try to get it from meta
            if ($price_value <= 0) {
                $price_meta = $cart_item['data']->get_meta('_price');
                if ($price_meta) {
                    $price_value = floatval($price_meta);
                }
            }
        }
        
        if ($price_value > 0) {
            $actual_quantity = $cart_item['quantity'];
            
            // Format: quantity × price تومان
            return $actual_quantity . ' × ' . number_format($price_value) . ' تومان';
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
        // Get the price using the same priority as other methods
        $price_value = 0;
        
        if (isset($cart_item['hpo_custom_data'])) {
            if (isset($cart_item['hpo_custom_data']['price_per_unit']) && 
                $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
            } elseif (isset($cart_item['hpo_custom_data']['calculated_price']) && 
                     $cart_item['hpo_custom_data']['calculated_price'] > 0) {
                $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
            }
        }
        
        // If no custom price found, use the product price
        if ($price_value <= 0 && isset($cart_item['data'])) {
            $price_value = floatval($cart_item['data']->get_price());
            
            // If product price is still zero, try to get it from meta
            if ($price_value <= 0) {
                $price_meta = $cart_item['data']->get_meta('_price');
                if ($price_meta) {
                    $price_value = floatval($price_meta);
                }
            }
        }
        
        if ($price_value > 0) {
            $actual_quantity = $cart_item['quantity'];
            
            // Completely replace the default quantity HTML with our custom format
            return '<span class="hpo-custom-quantity">' . $actual_quantity . ' × ' . number_format($price_value) . ' تومان</span>';
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
        // Get the price using the same priority as other methods
        $price_value = 0;
        
        if (isset($cart_item_data['hpo_custom_data'])) {
            if (isset($cart_item_data['hpo_custom_data']['price_per_unit']) && 
                $cart_item_data['hpo_custom_data']['price_per_unit'] > 0) {
                $price_value = floatval($cart_item_data['hpo_custom_data']['price_per_unit']);
            } elseif (isset($cart_item_data['hpo_custom_data']['calculated_price']) && 
                     $cart_item_data['hpo_custom_data']['calculated_price'] > 0) {
                $price_value = floatval($cart_item_data['hpo_custom_data']['calculated_price']);
            }
        }
        
        // If no custom price is found, try to get it from the product
        if ($price_value <= 0 && $product) {
            $price_value = floatval($product->get_price());
            
            // If product price is still zero, try to get it from meta
            if ($price_value <= 0) {
                $price_meta = $product->get_meta('_price');
                if ($price_meta) {
                    $price_value = floatval($price_meta);
                }
            }
        }
        
        if ($price_value > 0) {
            $actual_quantity = isset($cart_item_data['quantity']) ? $cart_item_data['quantity'] : 1;
            
            // Create a custom display format
            return '<span class="hpo-custom-quantity">' . $actual_quantity . ' × ' . number_format($price_value) . ' تومان</span>';
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
                display: inline-block !important;
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
                console.log("HPO Cart Price Fixer loaded");
                
                // Function to safely parse price text to number
                function parsePrice(priceText) {
                    // Remove all non-digit characters and parse as integer
                    if (!priceText) return 0;
                    let digits = priceText.replace(/[^\d]/g, '');
                    return digits ? parseInt(digits) : 0;
                }

                // Helper function to format numbers with commas
                function numberWithCommas(x) {
                    // Make sure x is a valid number
                    x = parseFloat(x) || 0;
                    return Math.round(x).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                }
                
                // Function to get price from quantity element
                function getPriceFromQuantity(quantityText) {
                    if (!quantityText) return 0;
                    
                    // Try to extract price from format: "2 × 125,000 تومان"
                    var priceMatch = quantityText.match(/(\d+)\s*×\s*([\d,]+)\s*تومان/);
                    if (priceMatch && priceMatch[2]) {
                        var unitPrice = parsePrice(priceMatch[2]);
                        return unitPrice;
                    }
                    
                    return 0;
                }
                
                // Fix mini cart prices
                function fixMiniCartPrices() {
                    console.log("HPO: Fixing mini cart prices");
                    $('.widget_shopping_cart_content .mini_cart_item').each(function() {
                        var $item = $(this);
                        var $priceElement = $item.find('.woocommerce-Price-amount');
                        var $quantityElement = $item.find('.quantity');
                        
                        if ($quantityElement.length > 0) {
                            var quantityText = $quantityElement.text();
                            var unitPrice = getPriceFromQuantity(quantityText);
                            
                            if (unitPrice > 0 && $priceElement.length > 0) {
                                console.log("HPO Mini Cart: " + 
                                    $item.find('.product-title').text() + " - Setting price to " + unitPrice);
                                
                                $priceElement.html('<bdi>' + numberWithCommas(unitPrice) + 
                                    '&nbsp;<span class="woocommerce-Price-currencySymbol">تومان</span></bdi>');
                            }
                        }
                    });
                }
                
                // Fix main cart prices
                function fixMainCartPrices() {
                    console.log("HPO: Fixing main cart prices");
                    $('.woocommerce-cart-form__cart-item').each(function() {
                        var $row = $(this);
                        var $customQuantity = $row.find('.hpo-custom-quantity');
                        var productName = $row.find('.product-name').text();
                        
                        if ($customQuantity.length > 0) {
                            var quantityText = $customQuantity.text();
                            var unitPrice = getPriceFromQuantity(quantityText);
                            
                            if (unitPrice > 0) {
                                var quantity = parseInt($row.find('input.qty').val()) || 1;
                                var total = unitPrice * quantity;
                                
                                console.log("HPO Main Cart: " + productName + 
                                    " - Setting unit price to " + unitPrice + 
                                    ", quantity " + quantity + 
                                    ", total " + total);
                                
                                // Update unit price
                                $row.find('.product-price .woocommerce-Price-amount').html('<bdi>' + 
                                    numberWithCommas(unitPrice) + '&nbsp;<span class="woocommerce-Price-currencySymbol">تومان</span></bdi>');
                                
                                // Update subtotal
                                $row.find('.product-subtotal .woocommerce-Price-amount').html('<bdi>' + 
                                    numberWithCommas(total) + '&nbsp;<span class="woocommerce-Price-currencySymbol">تومان</span></bdi>');
                                
                                // Force update data attributes that might be used by WooCommerce
                                $row.find('.product-price').attr('data-price', unitPrice);
                                $row.find('.product-subtotal').attr('data-subtotal', total);
                            }
                        }
                    });
                    
                    // Also update cart totals if needed
                    setTimeout(function() {
                        var needRecalculate = false;
                        $('.cart-subtotal .woocommerce-Price-amount, .order-total .woocommerce-Price-amount').each(function() {
                            var currentTotal = parsePrice($(this).text());
                            if (currentTotal <= 0) {
                                needRecalculate = true;
                            }
                        });
                        
                        if (needRecalculate) {
                            console.log("HPO: Cart totals need recalculation");
                            // Trigger WooCommerce's update event
                            $(document.body).trigger('update_checkout');
                        }
                    }, 500);
                }
                
                // Fix Woodmart theme cart widget
                function fixWoodmartCartWidget() {
                    console.log("HPO: Fixing Woodmart cart widget");
                    $('body > div.cart-widget-side .widget_shopping_cart_content li').each(function() {
                        var $item = $(this);
                        var $priceElement = $item.find('.woocommerce-Price-amount');
                        var $quantityElement = $item.find('.quantity');
                        
                        if ($quantityElement.length > 0 && $priceElement.length > 0) {
                            var quantityText = $quantityElement.text();
                            var unitPrice = getPriceFromQuantity(quantityText);
                            
                            if (unitPrice > 0) {
                                console.log("HPO Woodmart Widget: Setting price to " + unitPrice);
                                $priceElement.html('<bdi>' + numberWithCommas(unitPrice) + 
                                    '&nbsp;<span class="woocommerce-Price-currencySymbol">تومان</span></bdi>');
                            }
                        }
                    });
                }
                
                // Master function to fix all cart prices
                function fixAllCartPrices() {
                    console.log("HPO: Running cart price fixer");
                    
                    // Fix mini cart in header
                    fixMiniCartPrices();
                    
                    // Fix main cart page items
                    if (document.body.classList.contains('woocommerce-cart')) {
                        fixMainCartPrices();
                    }
                    
                    // Fix Woodmart specific elements
                    fixWoodmartCartWidget();
                    
                    // Add a custom event that any custom theme can hook into
                    $(document).trigger('hpo_prices_updated');
                }

                // Set up event listeners for cart updates
                
                // Run the fixes when cart/fragments are updated
                $(document.body).on('updated_cart_totals updated_checkout wc_fragments_refreshed added_to_cart wc_fragments_loaded', function(e) {
                    console.log("HPO: Cart update event: " + e.type);
                    setTimeout(fixAllCartPrices, 100);
                });
                
                // Set up mutation observer to detect DOM changes in the cart
                if (window.MutationObserver && document.querySelector('.woocommerce-cart-form, .widget_shopping_cart_content')) {
                    console.log("HPO: Setting up mutation observer");
                    var cartObserver = new MutationObserver(function(mutations) {
                        console.log("HPO: DOM changes detected in cart");
                        fixAllCartPrices();
                    });
                    
                    var cartElements = document.querySelectorAll('.woocommerce-cart-form, .widget_shopping_cart_content');
                    for (var i = 0; i < cartElements.length; i++) {
                        cartObserver.observe(cartElements[i], { 
                            childList: true, 
                            subtree: true 
                        });
                    }
                }
                
                // Wait for DOM and run immediately as well
                setTimeout(fixAllCartPrices, 300);
                
                // Also when the mini cart is opened
                $(document).on('click', '.woodmart-cart-icon, .cart-contents', function() {
                    console.log("HPO: Cart widget opened");
                    setTimeout(fixAllCartPrices, 300);
                });
                
                // Also when adding to cart
                $(document).on('click', '.hpo-add-to-cart-button', function() {
                    console.log("HPO: Add to cart button clicked");
                    setTimeout(fixAllCartPrices, 500);
                });
                
                // Also run on page load
                $(window).on('load', function() {
                    console.log("HPO: Window loaded, fixing prices");
                    setTimeout(fixAllCartPrices, 300);
                });
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
            // Debug info
            $debug = "HPO Filter Product Price: Product #{$product->get_id()}, Initial price: {$price}";
            
            // Get the cart
            $cart = WC()->cart;
            if (!$cart) {
                return floatval($price);
            }
            
            // Get the product ID
            $product_id = $product->get_id();
            
            // Check each cart item
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                // Find matching product
                if ($cart_item['product_id'] == $product_id || 
                    (isset($cart_item['variation_id']) && $cart_item['variation_id'] == $product_id)) {
                    
                    // Priority 1: Custom data price_per_unit
                    if (isset($cart_item['hpo_custom_data']) && 
                        isset($cart_item['hpo_custom_data']['price_per_unit']) &&
                        $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                        
                        $custom_price = floatval($cart_item['hpo_custom_data']['price_per_unit']);
                        $debug .= ", Found price_per_unit: {$custom_price}";
                        return $custom_price;
                    }
                    
                    // Priority 2: Custom data calculated_price
                    if (isset($cart_item['hpo_custom_data']) && 
                        isset($cart_item['hpo_custom_data']['calculated_price']) &&
                        $cart_item['hpo_custom_data']['calculated_price'] > 0) {
                        
                        $custom_price = floatval($cart_item['hpo_custom_data']['calculated_price']);
                        $debug .= ", Found calculated_price: {$custom_price}";
                        return $custom_price;
                    }
                    
                    // Priority 3: Custom data custom_price
                    if (isset($cart_item['hpo_custom_data']) && 
                        isset($cart_item['hpo_custom_data']['custom_price']) &&
                        $cart_item['hpo_custom_data']['custom_price'] > 0) {
                        
                        $custom_price = floatval($cart_item['hpo_custom_data']['custom_price']);
                        $debug .= ", Found custom_price: {$custom_price}";
                        return $custom_price;
                    }
                    
                    // Priority 4: Direct cart item properties
                    if (isset($cart_item['data_price']) && $cart_item['data_price'] > 0) {
                        $custom_price = floatval($cart_item['data_price']);
                        $debug .= ", Found data_price: {$custom_price}";
                        return $custom_price;
                    }
                    
                    if (isset($cart_item['hpo_total_price']) && $cart_item['hpo_total_price'] > 0) {
                        $custom_price = floatval($cart_item['hpo_total_price']);
                        $debug .= ", Found hpo_total_price: {$custom_price}";
                        return $custom_price;
                    }
                    
                    // Priority 5: Product object price from cart item
                    if (isset($cart_item['data']) && method_exists($cart_item['data'], 'get_price')) {
                        $product_price = floatval($cart_item['data']->get_price());
                        if ($product_price > 0) {
                            $debug .= ", Found cart_item data price: {$product_price}";
                            return $product_price;
                        }
                    }
                    
                    // Priority 6: Meta data
                    $price_meta = $product->get_meta('_hpo_custom_price');
                    if ($price_meta && floatval($price_meta) > 0) {
                        $meta_price = floatval($price_meta);
                        $debug .= ", Found _hpo_custom_price meta: {$meta_price}";
                        return $meta_price;
                    }
                    
                    $price_meta = $product->get_meta('_price');
                    if ($price_meta && floatval($price_meta) > 0) {
                        $meta_price = floatval($price_meta);
                        $debug .= ", Found _price meta: {$meta_price}";
                        return $meta_price;
                    }
                }
            }
            
            // Add debug info
            $debug .= ", No custom price found, using original: {$price}";
            add_action('wp_footer', function() use ($debug) {
                echo '<script>console.log("' . esc_js($debug) . '");</script>';
            }, 999);
        }
        
        // Always return a valid price
        return floatval($price);
    }

    /**
     * Save order item meta for the shortcode cart items
     *
     * @param object $item The order item
     * @param string $cart_item_key The cart item key
     * @param array $values The cart item values
     * @param WC_Order $order The order object
     */
    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['hpo_custom_data'])) {
            $custom_data = $values['hpo_custom_data'];
            
            // Save selected product options in the same format as the main plugin
            if (!empty($custom_data['options'])) {
                $item->add_meta_data('_hpo_products', $custom_data['options']);
            }
            
            // Save weight option
            if (!empty($custom_data['weight'])) {
                $weight_data = array(
                    'name' => $custom_data['weight']['name'],
                    'coefficient' => isset($custom_data['weight']['coefficient']) ? $custom_data['weight']['coefficient'] : 1
                );
                $item->add_meta_data('_hpo_weight', $weight_data);
            }
            
            // Save grinding option
            if (!empty($custom_data['grinding'])) {
                $grinding_data = array(
                    'type' => $custom_data['grinding']
                );
                
                if ($custom_data['grinding'] === 'ground' && !empty($custom_data['grinding_machine'])) {
                    $grinding_data['machine'] = array(
                        'name' => $custom_data['grinding_machine']['name'],
                        'price' => $custom_data['grinding_machine']['price']
                    );
                }
                
                $item->add_meta_data('_hpo_grinder', $grinding_data);
            }
            
            // Save customer notes
            if (!empty($custom_data['customer_notes'])) {
                $item->add_meta_data('_hpo_customer_notes', $custom_data['customer_notes']);
            }
            
            // Save calculated price
            if (isset($custom_data['calculated_price'])) {
                $item->add_meta_data('_hpo_calculated_price', $custom_data['calculated_price']);
            } else if (isset($custom_data['price_per_unit'])) {
                $item->add_meta_data('_hpo_calculated_price', $custom_data['price_per_unit']);
            }
        }
    }

    /**
     * Fix cart item prices after loading from session
     *
     * @param WC_Cart $cart The cart object
     */
    public function fix_cart_after_session_load($cart) {
        // Check if the cart is loaded
        if (!$cart || empty($cart->get_cart())) {
            return;
        }
        
        // Debug info
        $debug_info = ["HPO: Cart loaded from session, fixing prices..."];
        
        // Force update of all prices
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['hpo_custom_data'])) {
                $price_value = 0;
                
                // Get price with priority order
                if (isset($cart_item['hpo_custom_data']['price_per_unit']) && 
                    $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                    $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
                    $debug_info[] = "Item {$cart_item_key}: Using price_per_unit: {$price_value}";
                } elseif (isset($cart_item['hpo_custom_data']['calculated_price']) && 
                    $cart_item['hpo_custom_data']['calculated_price'] > 0) {
                    $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
                    $debug_info[] = "Item {$cart_item_key}: Using calculated_price: {$price_value}";
                } elseif (isset($cart_item['hpo_custom_data']['custom_price']) && 
                    $cart_item['hpo_custom_data']['custom_price'] > 0) {
                    $price_value = floatval($cart_item['hpo_custom_data']['custom_price']);
                    $debug_info[] = "Item {$cart_item_key}: Using custom_price: {$price_value}";
                }
                
                // If we have a valid price, apply it
                if ($price_value > 0) {
                    // Set the price directly on the product object
                    $cart->cart_contents[$cart_item_key]['data']->set_price($price_value);
                    
                    // Update the meta data
                    $cart->cart_contents[$cart_item_key]['data']->update_meta_data('_price', $price_value);
                    $cart->cart_contents[$cart_item_key]['data']->update_meta_data('_hpo_custom_price', $price_value);
                    
                    // Also update our custom data for consistency
                    $cart->cart_contents[$cart_item_key]['hpo_custom_data']['price_per_unit'] = $price_value;
                    $cart->cart_contents[$cart_item_key]['hpo_custom_data']['calculated_price'] = $price_value;
                    $cart->cart_contents[$cart_item_key]['hpo_custom_data']['custom_price'] = $price_value;
                    
                    // Set direct cart item properties
                    $cart->cart_contents[$cart_item_key]['data_price'] = $price_value;
                    $cart->cart_contents[$cart_item_key]['hpo_total_price'] = $price_value;
                    $cart->cart_contents[$cart_item_key]['_hpo_custom_price_item'] = 'yes';
                    
                    $debug_info[] = "Item price fixed to: {$price_value}";
                }
            }
        }
        
        // Add debug info to page
        add_action('wp_footer', function() use ($debug_info) {
            echo '<script>console.log(' . json_encode($debug_info) . ');</script>';
        }, 999);
        
        // Force cart recalculation
        $cart->calculate_totals();
    }
}

// Initialize the shortcodes
new HPO_Shortcodes(); 