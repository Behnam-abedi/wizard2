<?php
/**
 * The main plugin class
 *
 * @since      1.0.0
 */
class Hierarchical_Product_Options {

    /**
     * Initialize the plugin
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        
        // Display options on the order-received page - use only one hook for reliability
        add_action('woocommerce_order_item_meta_end', array($this, 'display_order_item_options'), 10, 4);
        
        // Make sure our CSS is loaded on all WooCommerce pages, especially the thank you page
        add_action('wp_enqueue_scripts', array($this, 'enqueue_thank_you_page_styles'));
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     */
    private function load_dependencies() {
        // Admin class
        require_once HPO_PLUGIN_DIR . 'admin/class-hierarchical-product-options-admin.php';
        
        // Database class
        require_once HPO_PLUGIN_DIR . 'includes/class-hierarchical-product-options-db.php';
    }

    /**
     * Register all of the hooks related to the admin area functionality
     *
     * @since    1.0.0
     */
    private function define_admin_hooks() {
        $plugin_admin = new Hierarchical_Product_Options_Admin();
        
        // Add menu
        add_action('admin_menu', array($plugin_admin, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($plugin_admin, 'register_settings'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts'));
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     *
     * @since    1.0.0
     */
    private function define_public_hooks() {
        // Add product options to product page
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_product_options'));
        
        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        
        // Handle price adjustments
        add_filter('woocommerce_get_price_html', array($this, 'adjust_price_display'), 10, 2);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_option_data_to_cart'), 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        
        // Display options in cart
        add_filter('woocommerce_get_item_data', array($this, 'add_cart_item_data'), 10, 2);
        
        // Update prices in cart
        add_filter('woocommerce_cart_item_price', array($this, 'update_product_price'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'update_product_price'), 10, 3);
        
        // Mini-cart specific hooks
        add_filter('woocommerce_widget_cart_item_quantity', array($this, 'update_mini_cart_price'), 10, 3);
        
        // This critical filter ensures the correct price is used for order totals
        add_filter('woocommerce_before_calculate_totals', array($this, 'before_calculate_totals'), 10, 1);
        
        // Save order item meta data
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_meta'), 10, 4);

        // Display order item meta in admin
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        
        // Hide products with hierarchical options from shop and other archives
        add_filter('woocommerce_product_query_tax_query', array($this, 'hide_hierarchical_products_from_shop'), 20, 1);
        
        // Exclude products from search results
        add_filter('pre_get_posts', array($this, 'exclude_products_from_search'), 10);
        
        // Hide products from related products
        add_filter('woocommerce_related_products', array($this, 'exclude_from_related_products'), 10, 3);
        
        // Redirect single product pages of hierarchical products to home
        add_action('template_redirect', array($this, 'redirect_hierarchical_product_pages'));
    }

    /**
     * Display the hierarchical product options on the product page
     */
    public function display_product_options() {
        global $product;
        
        // Get product options for this product
        $db = new Hierarchical_Product_Options_DB();
        $options = $db->get_options_for_product($product->get_id());
        
        if (!empty($options)) {
            // Template for displaying options will be created
            include HPO_PLUGIN_DIR . 'public/partials/product-options-display.php';
        }
    }

    /**
     * Enqueue public-facing stylesheets
     */
    public function enqueue_styles() {
        // Only load on product pages
        if (!is_product()) {
            return;
        }
        
        wp_enqueue_style(
            'hierarchical-product-options',
            HPO_PLUGIN_URL . 'public/css/public.css',
            array(),
            HPO_VERSION
        );
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function run() {
        // Initialize the database on plugin activation
        register_activation_hook(HPO_PLUGIN_DIR . 'hierarchical-product-options.php', array($this, 'activate'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $db = new Hierarchical_Product_Options_DB();
        $db->create_tables();
    }

    /**
     * Adjust price display in product page
     */
    public function adjust_price_display($price, $product) {
        // Only modify on product page
        if (!is_product() || !is_single($product->get_id())) {
            return $price;
        }
        
        // Check if this product has options
        $db = new Hierarchical_Product_Options_DB();
        $options = $db->get_options_for_product($product->get_id());
        
        if (empty($options)) {
            return $price;
        }
        
        // Add a wrapper to make it easier to update with JavaScript
        return '<span class="hpo-price-wrapper">' . $price . '</span>';
    }

    /**
     * Add selected options to cart item data
     * 
     * @param array $cart_item_data
     * @param int $product_id
     * @param int $variation_id
     * @return array
     */
    public function add_option_data_to_cart($cart_item_data, $product_id, $variation_id) {
        // Store selected options in cart item
        if (isset($_POST['hpo_selected_categories'])) {
            $cart_item_data['hpo_categories'] = json_decode(stripslashes($_POST['hpo_selected_categories']), true);
        }
        
        if (isset($_POST['hpo_selected_products'])) {
            $cart_item_data['hpo_products'] = json_decode(stripslashes($_POST['hpo_selected_products']), true);
        }
        
        // Handle weight option
        if (isset($_POST['hpo_weight'])) {
            $weight_id = absint($_POST['hpo_weight']);
            
            $db = new Hierarchical_Product_Options_DB();
            $weight = $db->get_weight($weight_id);
            
            if ($weight) {
                $cart_item_data['hpo_weight'] = array(
                    'id' => $weight->id,
                    'name' => $weight->name,
                    'coefficient' => $weight->coefficient
                );
            }
        }
        
        // Handle grinding option
        if (isset($_POST['hpo_grinding']) && $_POST['hpo_grinding'] === 'ground') {
            $cart_item_data['hpo_grinding'] = 'ground';
            
            // Store grinding machine data if selected
            if (isset($_POST['hpo_grinding_machine']) && !empty($_POST['hpo_grinding_machine'])) {
                $grinder_id = absint($_POST['hpo_grinding_machine']);
                
                $db = new Hierarchical_Product_Options_DB();
                $grinder = $db->get_grinder($grinder_id);
                
                if ($grinder) {
                    $cart_item_data['hpo_grinding_machine'] = array(
                        'id' => $grinder->id,
                        'name' => $grinder->name,
                        'price' => $grinder->price
                    );
                }
            }
        } else {
            $cart_item_data['hpo_grinding'] = 'whole';
        }
        
        // Store customer notes if provided
        if (isset($_POST['hpo_customer_notes']) && !empty($_POST['hpo_customer_notes'])) {
            $cart_item_data['hpo_customer_notes'] = sanitize_textarea_field($_POST['hpo_customer_notes']);
        }
        
        // Capture the calculated price at the moment of adding to cart
        if (isset($_POST['hpo_calculated_price']) && !empty($_POST['hpo_calculated_price'])) {
            $cart_item_data['hpo_calculated_price'] = floatval($_POST['hpo_calculated_price']);
        }
        
        // Always generate a unique key for this cart item to prevent merging in WooCommerce
        // This ensures each addition to cart is treated as a separate item
        $cart_item_data['unique_key'] = md5(microtime() . rand());
        
        return $cart_item_data;
    }
    
    /**
     * Get cart item from session
     * 
     * @param array $cart_item
     * @param array $values
     * @return array
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['hpo_categories'])) {
            $cart_item['hpo_categories'] = $values['hpo_categories'];
        }
        
        if (isset($values['hpo_products'])) {
            $cart_item['hpo_products'] = $values['hpo_products'];
        }
        
        if (isset($values['hpo_weight'])) {
            $cart_item['hpo_weight'] = $values['hpo_weight'];
        }
        
        if (isset($values['hpo_grinding'])) {
            $cart_item['hpo_grinding'] = $values['hpo_grinding'];
            
            if (isset($values['hpo_grinding_machine'])) {
                $cart_item['hpo_grinding_machine'] = $values['hpo_grinding_machine'];
            }
        }
        
        if (isset($values['hpo_calculated_price'])) {
            $cart_item['hpo_calculated_price'] = $values['hpo_calculated_price'];
        }
        
        if (isset($values['unique_key'])) {
            $cart_item['unique_key'] = $values['unique_key'];
        }
        
        return $cart_item;
    }
    
    /**
     * Change cart item price
     * 
     * @param array $cart_item
     * @return array
     */
    public function change_cart_item_price($cart_item) {
        if (empty($cart_item['data'])) {
            return $cart_item;
        }
        
        // If we have a calculated price from when the item was added to cart, use that
        if (isset($cart_item['hpo_calculated_price']) && $cart_item['hpo_calculated_price'] > 0) {
            $calculated_price = (float)$cart_item['hpo_calculated_price'];
            // Make sure we're using the correct decimal format for toman/rial
            $calculated_price = round($calculated_price);
            $cart_item['data']->set_price($calculated_price);
            return $cart_item;
        }
        
        // Get product price
        $product = $cart_item['data'];
        $base_price = floatval($product->get_price());
        $total_price = $base_price;
        
        // Add selected category prices
        if (!empty($cart_item['hpo_categories'])) {
            foreach ($cart_item['hpo_categories'] as $category) {
                if (isset($category['price'])) {
                    $total_price += floatval($category['price']);
                }
            }
        }
        
        // Add selected product prices
        if (!empty($cart_item['hpo_products'])) {
            foreach ($cart_item['hpo_products'] as $option_product) {
                if (isset($option_product['price'])) {
                    $total_price += floatval($option_product['price']);
                }
            }
        }
        
        // Apply weight coefficient if selected
        if (!empty($cart_item['hpo_weight']) && isset($cart_item['hpo_weight']['coefficient'])) {
            $total_price *= floatval($cart_item['hpo_weight']['coefficient']);
        }
        
        // Add grinding machine price if selected
        if (isset($cart_item['hpo_grinding']) && $cart_item['hpo_grinding'] === 'ground' && 
            !empty($cart_item['hpo_grinding_machine']) && isset($cart_item['hpo_grinding_machine']['price'])) {
            $total_price += floatval($cart_item['hpo_grinding_machine']['price']);
        }
        
        // Round the price for toman/rial (no decimal places)
        $total_price = round($total_price);
        
        // Set the new price
        $product->set_price($total_price);
        
        return $cart_item;
    }
    
    /**
     * Add custom item data to cart display
     * 
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function add_cart_item_data($item_data, $cart_item) {
        // Add selected categories
        if (!empty($cart_item['hpo_categories'])) {
            $categories_names = array();
            
            foreach ($cart_item['hpo_categories'] as $category) {
                if (isset($category['name'])) {
                    $categories_names[] = $category['name'];
                }
            }
            
            if (!empty($categories_names)) {
                $item_data[] = array(
                    'key' => __('Categories', 'hierarchical-product-options'),
                    'value' => implode(', ', $categories_names)
                );
            }
        }
        
        // Add selected products
        if (!empty($cart_item['hpo_products'])) {
            $products_names = array();
            
            foreach ($cart_item['hpo_products'] as $product) {
                if (isset($product['name'])) {
                    $products_names[] = $product['name'];
                }
            }
            
            if (!empty($products_names)) {
                $item_data[] = array(
                    'key' => __('Products', 'hierarchical-product-options'),
                    'value' => implode(', ', $products_names)
                );
            }
        }
        
        // Add selected weight
        if (!empty($cart_item['hpo_weight'])) {
            $item_data[] = array(
                'key' => __('Weight', 'hierarchical-product-options'),
                'value' => $cart_item['hpo_weight']['name']
            );
        }
        
        // Add grinding information
        if (isset($cart_item['hpo_grinding'])) {
            if ($cart_item['hpo_grinding'] === 'ground') {
                $grinding_value = __('Ground', 'hierarchical-product-options');
                
                if (!empty($cart_item['hpo_grinding_machine'])) {
                    $grinding_value .= ' - ' . $cart_item['hpo_grinding_machine']['name'];
                }
                
                $item_data[] = array(
                    'key' => __('Grinding', 'hierarchical-product-options'),
                    'value' => $grinding_value
                );
            } else {
                $item_data[] = array(
                    'key' => __('Grinding', 'hierarchical-product-options'),
                    'value' => __('Whole (No Grinding)', 'hierarchical-product-options')
                );
            }
        }
        
        return $item_data;
    }

    /**
     * Update the product price before cart totals are calculated
     */
    public function before_calculate_totals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Avoid infinite loops
        static $run = 0;
        if ($run > 5) return;
        $run++;
        
        // Loop through cart items
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['data'])) {
                continue;
            }
            
            // If we have a calculated price from when the item was added to cart, use that
            if (isset($cart_item['hpo_calculated_price']) && $cart_item['hpo_calculated_price'] > 0) {
                $calculated_price = (float)$cart_item['hpo_calculated_price'];
                // Make sure we're using the correct decimal format for toman/rial
                $calculated_price = round($calculated_price);
                $cart_item['data']->set_price($calculated_price);
                
                // Store the price in WooCommerce's price meta to ensure it persists
                $cart_item['data']->update_meta_data('_price', $calculated_price);
                $cart_item['data']->update_meta_data('_regular_price', $calculated_price);
                $cart_item['data']->save_meta_data();
                
                continue;
            }
            
            // Calculate price based on options
            $total_price = $this->calculate_item_price($cart_item);
            
            // Set the new price
            $cart_item['data']->set_price($total_price);
            
            // Store the calculated price in cart item data for future reference
            $cart_item['hpo_calculated_price'] = $total_price;
            
            // Store the price in WooCommerce's price meta to ensure it persists
            $cart_item['data']->update_meta_data('_price', $total_price);
            $cart_item['data']->update_meta_data('_regular_price', $total_price);
            $cart_item['data']->save_meta_data();
            
            // Update the cart item in session
            WC()->session->set('cart_' . $cart_item_key, $cart_item);
        }
    }

    /**
     * Update product price display in cart
     * 
     * @param string $price
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function update_product_price($price, $cart_item, $cart_item_key) {
        if (empty($cart_item['data'])) {
            return $price;
        }
        
        // First try to get the calculated price from cart item data
        if (isset($cart_item['hpo_calculated_price']) && $cart_item['hpo_calculated_price'] > 0) {
            $current_price = (float)$cart_item['hpo_calculated_price'];
        } else {
            // Fallback to the price stored in product meta
            $current_price = $cart_item['data']->get_meta('_price');
            if (!$current_price) {
                $current_price = $cart_item['data']->get_price();
            }
        }
        
        // Make sure we're using the correct decimal format for toman/rial
        $current_price = round($current_price);
        
        // Format the price with WooCommerce's currency formatter
        return wc_price($current_price);
    }

    /**
     * Update mini-cart price
     * 
     * @param string $price_html
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function update_mini_cart_price($price_html, $cart_item, $cart_item_key) {
        if (empty($cart_item['data'])) {
            return $price_html;
        }
        
        // First try to get the calculated price from cart item data
        if (isset($cart_item['hpo_calculated_price']) && $cart_item['hpo_calculated_price'] > 0) {
            $current_price = (float)$cart_item['hpo_calculated_price'];
        } else {
            // Fallback to the price stored in product meta
            $current_price = $cart_item['data']->get_meta('_price');
            if (!$current_price) {
                $current_price = $cart_item['data']->get_price();
            }
        }
        
        // Make sure we're using the correct decimal format for toman/rial
        $current_price = round($current_price);
        
        // Get the quantity
        $quantity = $cart_item['quantity'];
        
        // Format the price with WooCommerce's currency formatter
        $price = wc_price($current_price);
        
        // Return the quantity and the calculated price in the mini-cart format
        return $quantity . ' × ' . $price;
    }

    /**
     * Calculate the price for a cart item based on selected options
     * 
     * @param array $cart_item
     * @return float
     */
    private function calculate_item_price($cart_item) {
        if (empty($cart_item['data'])) {
            return 0;
        }
        
        // Get product price
        $product = $cart_item['data'];
        $base_price = floatval($product->get_price());
        $total_price = $base_price;
        
        // Add selected category prices
        if (!empty($cart_item['hpo_categories'])) {
            foreach ($cart_item['hpo_categories'] as $category) {
                if (isset($category['price'])) {
                    $total_price += floatval($category['price']);
                }
            }
        }
        
        // Add selected product prices
        if (!empty($cart_item['hpo_products'])) {
            foreach ($cart_item['hpo_products'] as $option_product) {
                if (isset($option_product['price'])) {
                    $total_price += floatval($option_product['price']);
                }
            }
        }
        
        // Apply weight coefficient if selected
        if (!empty($cart_item['hpo_weight']) && isset($cart_item['hpo_weight']['coefficient'])) {
            $total_price *= floatval($cart_item['hpo_weight']['coefficient']);
        }
        
        // Add grinding machine price if selected
        if (isset($cart_item['hpo_grinding']) && $cart_item['hpo_grinding'] === 'ground' && 
            !empty($cart_item['hpo_grinding_machine']) && isset($cart_item['hpo_grinding_machine']['price'])) {
            $total_price += floatval($cart_item['hpo_grinding_machine']['price']);
        }
        
        // Round the price for toman/rial (no decimal places)
        return round($total_price);
    }

    /**
     * Save order item meta data during checkout
     *
     * @param WC_Order_Item_Product $item
     * @param string $cart_item_key
     * @param array $values
     * @param WC_Order $order
     */
    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        // Check if this is from our custom shortcode with hpo_custom_data
        if (isset($values['hpo_custom_data'])) {
            $custom_data = $values['hpo_custom_data'];
            
            // Save selected product options (from shortcode)
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
        // Original code for the standard product options meta data
        else if (isset($values['hpo_categories'])) {
            // Save selected categories
            if (!empty($values['hpo_categories'])) {
                $categories_data = array();
                foreach ($values['hpo_categories'] as $category) {
                    if (isset($category['name'])) {
                        $categories_data[] = array(
                            'name' => $category['name'],
                            'price' => isset($category['price']) ? $category['price'] : 0
                        );
                    }
                }
                $item->add_meta_data('_hpo_categories', $categories_data);
            }

            // Save selected products
            if (!empty($values['hpo_products'])) {
                $products_data = array();
                foreach ($values['hpo_products'] as $product) {
                    if (isset($product['name'])) {
                        $products_data[] = array(
                            'name' => $product['name'],
                            'price' => isset($product['price']) ? $product['price'] : 0
                        );
                    }
                }
                $item->add_meta_data('_hpo_products', $products_data);
            }

            // Save weight option
            if (!empty($values['hpo_weight'])) {
                $weight_data = array(
                    'name' => $values['hpo_weight']['name'],
                    'coefficient' => $values['hpo_weight']['coefficient']
                );
                $item->add_meta_data('_hpo_weight', $weight_data);
            }

            // Save grinding option
            if (isset($values['hpo_grinding'])) {
                $grinding_data = array(
                    'type' => $values['hpo_grinding']
                );
                
                if ($values['hpo_grinding'] === 'ground' && !empty($values['hpo_grinding_machine'])) {
                    $grinding_data['machine'] = array(
                        'name' => $values['hpo_grinding_machine']['name'],
                        'price' => $values['hpo_grinding_machine']['price']
                    );
                }
                
                $item->add_meta_data('_hpo_grinder', $grinding_data);
            }

            // Save customer notes
            if (!empty($values['hpo_customer_notes'])) {
                $item->add_meta_data('_hpo_customer_notes', $values['hpo_customer_notes']);
            }

            // Save calculated price
            if (isset($values['hpo_calculated_price'])) {
                $item->add_meta_data('_hpo_calculated_price', $values['hpo_calculated_price']);
            }
        }
    }

    /**
     * Add meta box to order page
     */
    public function add_order_meta_box() {
        add_meta_box(
            'hpo_order_options',
            'جزئیات آپشن‌های محصولات',
            array($this, 'display_order_meta_in_admin'),
            'shop_order',
            'normal',
            'high'
        );
    }

    /**
     * Display order meta data in admin order page
     *
     * @param WP_Post $post
     */
    public function display_order_meta_in_admin($post) {
        $order = wc_get_order($post->ID);
        if (!$order) return;

        ?>
        <div class="hpo-order-details">
            <table class="widefat">
                <thead>
                    <tr>
                        <th class="row-title">نام محصول</th>
                        <th>نوع قهوه</th>
                        <th>مقدار</th>
                        <th>وضعیت آسیاب</th>
                        <th>تعداد</th>
                        <th>توضیحات مشتری</th>
                        <th>قیمت نهایی (هر واحد)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item_id => $item): ?>
                    <tr>
                        <td class="row-title">
                            <label><?php echo esc_html($item->get_name()); ?></label>
                        </td>
                        <td>
                            <?php
                            $products = $item->get_meta('_hpo_products');
                            if (!empty($products)) {
                                $product_names = array();
                                foreach ($products as $product) {
                                    // Check if this is from the shortcode format
                                    if (isset($product['category_id'])) {
                                        $product_names[] = $product['name'];
                                    } 
                                    // Standard format
                                    else if (isset($product['name'])) {
                                        $product_names[] = $product['name'];
                                    }
                                }
                                echo implode(' + ', $product_names);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $weight = $item->get_meta('_hpo_weight');
                            if (!empty($weight)) {
                                if (isset($weight['name'])) {
                                    echo esc_html($weight['name']);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $grinder = $item->get_meta('_hpo_grinder');
                            if (!empty($grinder)) {
                                if (isset($grinder['type']) && $grinder['type'] === 'ground') {
                                    echo '<span class="grinding-status ground">آسیاب شده</span>';
                                    if (!empty($grinder['machine'])) {
                                        echo '<br><span class="machine-name">دستگاه: ' . esc_html($grinder['machine']['name']) . '</span>';
                                    }
                                } else {
                                    echo '<span class="grinding-status whole">آسیاب نشده</span>';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $quantity = $item->get_quantity();
                            echo esc_html($quantity);
                            ?>
                        </td>
                        <td>
                            <?php
                            $customer_notes = $item->get_meta('_hpo_customer_notes');
                            if (!empty($customer_notes)) {
                                echo '<div class="customer-notes">' . nl2br(esc_html($customer_notes)) . '</div>';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $calculated_price = $item->get_meta('_hpo_calculated_price');
                            if (!empty($calculated_price)) {
                                echo '<strong class="final-price">' . wc_price($calculated_price) . '</strong>';
                            } else {
                                echo wc_price($item->get_subtotal() / $quantity);
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <style>
            .hpo-order-details {
                margin: 15px 0;
            }
            .hpo-order-details table {
                border-collapse: collapse;
                width: 100%;
                background: white;
                border-radius: 8px;
                overflow: hidden;
            }
            .hpo-order-details th {
                background: #f0f0f1;
                padding: 12px;
                text-align: right;
                font-weight: 600;
                color: #1d2327;
                border-bottom: 2px solid #c3c4c7;
            }
            .hpo-order-details td {
                padding: 12px;
                border-bottom: 1px solid #f0f0f1;
                vertical-align: top;
                direction: rtl;  /* یا ltr، ولی بهتر است پیش‌فرض rtl باشد */
                unicode-bidi: plaintext;
                text-align: right;
            }
            .hpo-order-details tr:hover {
                background: #f8f9fa;
            }
            .hpo-order-details .row-title {
                font-weight: 600;
                color: #2271b1;
            }
            .grinding-status {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 0.9em;
            }
            .grinding-status.ground {
                background: #edf7ed;
                color: #1e4620;
            }
            .grinding-status.whole {
                background: #fff4e5;
                color: #663c00;
            }
            .machine-name {
                display: inline-block;
                margin-top: 5px;
                font-size: 0.9em;
                color: #50575e;
            }
            .final-price {
                color: #2271b1;
                font-size: 1.1em;
            }
            .customer-notes {
                background-color: #f8f9fa;
                padding: 8px;
                border-radius: 4px;
                font-size: 0.9em;
                color: #50575e;
                max-width: 300px;
                white-space: pre-wrap;
            }
        </style>
        <?php
    }

    /**
     * Display product options, grinding options, and weight options in order-received page
     * 
     * @param int $item_id Order item ID
     * @param WC_Order_Item $item Order item object
     * @param WC_Order $order Order object
     * @param bool $plain_text Whether to use plain text
     */
    public function display_order_item_options($item_id, $item, $order, $plain_text = false) {
        // Only on frontend, not emails
        if (is_admin() || $plain_text) {
            return;
        }

        // Check if this is the order-received page or my-account/view-order page
        if (!is_checkout() && !is_account_page()) {
            global $wp;
            $is_order_received = isset($wp->query_vars['order-received']) || 
                                (isset($_GET['key']) && isset($_GET['order'])) || 
                                is_wc_endpoint_url('order-received');
                                
            if (!$is_order_received) {
                return;
            }
        }

        $categories_data = $item->get_meta('_hpo_categories');
        $products_data = $item->get_meta('_hpo_products');
        $weight_data = $item->get_meta('_hpo_weight');
        $grinder_data = $item->get_meta('_hpo_grinder');
        $customer_notes = $item->get_meta('_hpo_customer_notes');

        if (empty($categories_data) && empty($products_data) && empty($weight_data) && empty($grinder_data) && empty($customer_notes)) {
            return;
        }

        echo '<div class="hpo-order-item-options">';
        
        // Display product options (categories)
        if (!empty($categories_data)) {
            echo '<div class="hpo-option-section">';
            echo '<h4>آپشن‌های محصول</h4>';
            echo '<ul class="hpo-order-options-list">';
            
            foreach ($categories_data as $category) {
                echo '<li>';
                echo '<span class="hpo-option-name">' . esc_html($category['name']) . '</span>';
                
                if (!empty($category['price']) && $category['price'] > 0) {
                    echo ' <span class="hpo-option-price">(+' . wc_price($category['price']) . ')</span>';
                }
                
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        // Display product options selected
        if (!empty($products_data)) {
            echo '<div class="hpo-option-section">';
            echo '<h4>محصولات انتخاب شده</h4>';
            echo '<ul class="hpo-order-options-list">';
            
            foreach ($products_data as $product) {
                echo '<li>';
                echo '<span class="hpo-option-name">' . esc_html($product['name']) . '</span>';
                
                if (!empty($product['price']) && $product['price'] > 0) {
                    echo ' <span class="hpo-option-price">(+' . wc_price($product['price']) . ')</span>';
                }
                
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        // Display weight option
        if (!empty($weight_data)) {
            echo '<div class="hpo-option-section">';
            echo '<h4>وزن انتخابی</h4>';
            echo '<ul class="hpo-order-options-list">';
            echo '<li>';
            echo '<span class="hpo-option-name">' . esc_html($weight_data['name']) . '</span>';
            
            if (isset($weight_data['coefficient']) && $weight_data['coefficient'] != 1) {
                echo ' <span class="hpo-option-coefficient">(ضریب: ' . esc_html($weight_data['coefficient']) . ')</span>';
            }
            
            echo '</li>';
            echo '</ul>';
            echo '</div>';
        }
        
        // Display grinder option
        if (!empty($grinder_data)) {
            echo '<div class="hpo-option-section">';
            echo '<h4>نوع آسیاب</h4>';
            echo '<ul class="hpo-order-options-list">';
            
            if ($grinder_data['type'] === 'ground') {
                echo '<li>';
                echo '<span class="grinding-status-badge ground">آسیاب شده</span>';
                echo '</li>';
                
                if (!empty($grinder_data['machine'])) {
                    echo '<li>';
                    echo '<span class="hpo-option-name">دستگاه آسیاب: ' . esc_html($grinder_data['machine']['name']) . '</span>';
                    
                    if (!empty($grinder_data['machine']['price']) && $grinder_data['machine']['price'] > 0) {
                        echo ' <span class="hpo-option-price">(+' . wc_price($grinder_data['machine']['price']) . ')</span>';
                    }
                    
                    echo '</li>';
                }
            } else {
                echo '<li>';
                echo '<span class="grinding-status-badge whole">دانه کامل (بدون آسیاب)</span>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        // Display customer notes
        if (!empty($customer_notes)) {
            echo '<div class="hpo-option-section">';
            echo '<h4>توضیحات سفارش</h4>';
            echo '<div class="hpo-customer-notes">';
            echo '<p>' . nl2br(esc_html($customer_notes)) . '</p>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Display order options after the order details table
     *
     * @param WC_Order $order
     */
    public function display_order_options_after_table($order) {
        // Display options for each item
        foreach ($order->get_items() as $item_id => $item) {
            $categories_data = $item->get_meta('_hpo_categories');
            $products_data = $item->get_meta('_hpo_products');
            $weight_data = $item->get_meta('_hpo_weight');
            $grinder_data = $item->get_meta('_hpo_grinder');
            $customer_notes = $item->get_meta('_hpo_customer_notes');
            
            // Skip if no option data
            if (empty($categories_data) && empty($products_data) && empty($weight_data) && empty($grinder_data) && empty($customer_notes)) {
                continue;
            }
            
            echo '<div class="hpo-item-options-wrapper" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
            echo '<h3 style="margin-top:0;">' . esc_html($item->get_name()) . ' - ' . esc_html__('Selected Options', 'hierarchical-product-options') . '</h3>';
            
            echo '<div class="hpo-order-item-options">';
            
            // Display product options (categories)
            if (!empty($categories_data)) {
                echo '<h4>' . esc_html__('Product Options', 'hierarchical-product-options') . '</h4>';
                echo '<ul class="hpo-order-options-list">';
                
                foreach ($categories_data as $category) {
                    echo '<li>';
                    echo '<span class="hpo-option-name">' . esc_html($category['name']) . '</span>';
                    
                    if (!empty($category['price']) && $category['price'] > 0) {
                        echo ' <span class="hpo-option-price">(+' . wc_price($category['price']) . ')</span>';
                    }
                    
                    echo '</li>';
                }
                
                echo '</ul>';
            }
            
            // Display product options selected
            if (!empty($products_data)) {
                echo '<h4>' . esc_html__('Selected Products', 'hierarchical-product-options') . '</h4>';
                echo '<ul class="hpo-order-options-list">';
                
                foreach ($products_data as $product) {
                    echo '<li>';
                    echo '<span class="hpo-option-name">' . esc_html($product['name']) . '</span>';
                    
                    if (!empty($product['price']) && $product['price'] > 0) {
                        echo ' <span class="hpo-option-price">(+' . wc_price($product['price']) . ')</span>';
                    }
                    
                    echo '</li>';
                }
                
                echo '</ul>';
            }
            
            // Display weight option
            if (!empty($weight_data)) {
                echo '<h4>' . esc_html__('Weight Option', 'hierarchical-product-options') . '</h4>';
                echo '<ul class="hpo-order-options-list">';
                echo '<li><span class="hpo-option-name">' . esc_html($weight_data['name']) . '</span>';
                
                if (isset($weight_data['coefficient']) && $weight_data['coefficient'] != 1) {
                    echo ' <span class="hpo-option-coefficient">(' . esc_html__('Coefficient:', 'hierarchical-product-options') . ' ' . esc_html($weight_data['coefficient']) . ')</span>';
                }
                
                echo '</li>';
                echo '</ul>';
            }
            
            // Display grinder option
            if (!empty($grinder_data)) {
                echo '<h4>' . esc_html__('Grinding Option', 'hierarchical-product-options') . '</h4>';
                echo '<ul class="hpo-order-options-list">';
                
                if ($grinder_data['type'] === 'ground') {
                    echo '<li><span class="hpo-option-name">' . esc_html__('Ground Coffee', 'hierarchical-product-options') . '</span>';
                    
                    if (!empty($grinder_data['machine'])) {
                        echo '<li><span class="hpo-option-name">' . esc_html__('Grinding Machine:', 'hierarchical-product-options') . ' ' . esc_html($grinder_data['machine']['name']) . '</span>';
                        
                        if (!empty($grinder_data['machine']['price']) && $grinder_data['machine']['price'] > 0) {
                            echo ' <span class="hpo-option-price">(+' . wc_price($grinder_data['machine']['price']) . ')</span>';
                        }
                        
                        echo '</li>';
                    }
                } else {
                    echo '<li><span class="hpo-option-name">' . esc_html__('Whole Bean (Not Ground)', 'hierarchical-product-options') . '</span></li>';
                }
                
                echo '</ul>';
            }
            
            // Display customer notes
            if (!empty($customer_notes)) {
                echo '<h4>' . esc_html__('Customer Notes', 'hierarchical-product-options') . '</h4>';
                echo '<div class="hpo-customer-notes">';
                echo '<p>' . esc_html($customer_notes) . '</p>';
                echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Display order options summary after the full order details table
     *
     * @param WC_Order $order
     */
    public function display_order_summary_after_table($order) {
        // Regular display section
        echo '<div class="hpo-order-summary-section" style="margin-top: 30px;">';
        echo '<h2>' . esc_html__('Order Options Summary', 'hierarchical-product-options') . '</h2>';
        
        $has_options = false;
        
        foreach ($order->get_items() as $item_id => $item) {
            $categories_data = $item->get_meta('_hpo_categories');
            $products_data = $item->get_meta('_hpo_products');
            $weight_data = $item->get_meta('_hpo_weight');
            $grinder_data = $item->get_meta('_hpo_grinder');
            $customer_notes = $item->get_meta('_hpo_customer_notes');
            
            if (empty($categories_data) && empty($products_data) && empty($weight_data) && empty($grinder_data) && empty($customer_notes)) {
                continue;
            }
            
            $has_options = true;
            
            echo '<div class="hpo-summary-item" style="margin-bottom: 25px; padding: 15px; background: #f9f9f9; border-radius: 5px; border: 1px solid #eee;">';
            echo '<h3>' . esc_html($item->get_name()) . '</h3>';
            
            echo '<table class="hpo-options-table" style="width: 100%; border-collapse: collapse;">';
            echo '<tr>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #eee;">' . esc_html__('Option Type', 'hierarchical-product-options') . '</th>';
            echo '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #eee;">' . esc_html__('Selection', 'hierarchical-product-options') . '</th>';
            echo '</tr>';
            
            // Categories
            if (!empty($categories_data)) {
                echo '<tr>';
                echo '<td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>' . esc_html__('Product Options', 'hierarchical-product-options') . '</strong></td>';
                echo '<td style="padding: 8px; border-bottom: 1px solid #eee;">';
                
                $category_labels = array();
                foreach ($categories_data as $category) {
                    $label = esc_html($category['name']);
                    if (!empty($category['price']) && $category['price'] > 0) {
                        $label .= ' <span style="color:#777; font-size:90%;">(+' . wc_price($category['price']) . ')</span>';
                    }
                    $category_labels[] = $label;
                }
                echo implode(', ', $category_labels);
                
                echo '</td>';
                echo '</tr>';
            }
            
            // Products
            if (!empty($products_data)) {
                echo '<tr>';
                echo '<td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>' . esc_html__('Selected Products', 'hierarchical-product-options') . '</strong></td>';
                echo '<td style="padding: 8px; border-bottom: 1px solid #eee;">';
                
                $product_labels = array();
                foreach ($products_data as $product) {
                    $label = esc_html($product['name']);
                    if (!empty($product['price']) && $product['price'] > 0) {
                        $label .= ' <span style="color:#777; font-size:90%;">(+' . wc_price($product['price']) . ')</span>';
                    }
                    $product_labels[] = $label;
                }
                echo implode(', ', $product_labels);
                
                echo '</td>';
                echo '</tr>';
            }
            
            // Weight
            if (!empty($weight_data)) {
                echo '<tr>';
                echo '<td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>' . esc_html__('Weight Option', 'hierarchical-product-options') . '</strong></td>';
                echo '<td style="padding: 8px; border-bottom: 1px solid #eee;">';
                
                echo esc_html($weight_data['name']);
                if (isset($weight_data['coefficient']) && $weight_data['coefficient'] != 1) {
                    echo ' <span style="color:#777; font-size:90%;">(' . esc_html__('Coefficient:', 'hierarchical-product-options') . ' ' . esc_html($weight_data['coefficient']) . ')</span>';
                }
                
                echo '</td>';
                echo '</tr>';
            }
            
            // Grinding
            if (!empty($grinder_data)) {
                echo '<tr>';
                echo '<td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>' . esc_html__('Grinding Option', 'hierarchical-product-options') . '</strong></td>';
                echo '<td style="padding: 8px; border-bottom: 1px solid #eee;">';
                
                if ($grinder_data['type'] === 'ground') {
                    echo esc_html__('Ground Coffee', 'hierarchical-product-options');
                    
                    if (!empty($grinder_data['machine'])) {
                        echo ' - ' . esc_html__('Machine:', 'hierarchical-product-options') . ' ' . esc_html($grinder_data['machine']['name']);
                        if (!empty($grinder_data['machine']['price']) && $grinder_data['machine']['price'] > 0) {
                            echo ' <span style="color:#777; font-size:90%;">(+' . wc_price($grinder_data['machine']['price']) . ')</span>';
                        }
                    }
                } else {
                    echo esc_html__('Whole Bean (Not Ground)', 'hierarchical-product-options');
                }
                
                echo '</td>';
                echo '</tr>';
            }
            
            // Notes
            if (!empty($customer_notes)) {
                echo '<tr>';
                echo '<td style="padding: 8px;"><strong>' . esc_html__('Customer Notes', 'hierarchical-product-options') . '</strong></td>';
                echo '<td style="padding: 8px;">';
                echo '<div style="background-color: rgba(255, 255, 255, 0.7); padding: 10px; border-radius: 3px; border-left: 3px solid #7fb85c; font-style: italic; color: #555;">';
                echo esc_html($customer_notes);
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            echo '</div>';
        }
        
        if (!$has_options) {
            echo '<p>' . esc_html__('No custom options were selected for this order.', 'hierarchical-product-options') . '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Enqueue CSS styles for the thank you page
     */
    public function enqueue_thank_you_page_styles() {
        // Load on all WooCommerce pages, including the Thank You page
        if (is_woocommerce() || is_checkout() || is_account_page() || is_wc_endpoint_url()) {
            wp_enqueue_style(
                'hierarchical-product-options-public',
                HPO_PLUGIN_URL . 'public/css/public.css',
                array(),
                HPO_VERSION
            );
            
            // Add some inline CSS to ensure our styles are applied
            $additional_css = '
                .hpo-order-item-options {
                    display: block !important;
                    visibility: visible !important;
                    margin-top: 15px;
                    border: 1px solid #f0f0f0;
                    padding: 15px;
                    border-radius: 8px;
                    background-color: #fcfcfc;
                    font-family: Tahoma, Vazir, IRANSans, Arial;
                    direction: rtl;
                    text-align: right;
                }
                
                .woocommerce-order-details {
                    position: relative;
                }
                
                /* Order received page improvements */
                .woocommerce-order-received .woocommerce-order {
                    direction: rtl;
                }
                
                /* Table styles for order details */
                .woocommerce-table--order-details {
                    direction: rtl;
                    border-radius: 8px !important;
                    overflow: hidden;
                    border: none !important;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                
                .woocommerce-table--order-details thead {
                    background-color: #f8f8f8;
                }
                
                .woocommerce-table--order-details th,
                .woocommerce-table--order-details td {
                    padding: 12px 15px !important;
                    border-top: none !important;
                    border-right: none !important;
                    border-left: none !important;
                    border-bottom: 1px solid #f0f0f0 !important;
                }
                
                .woocommerce-table--order-details tfoot {
                    background-color: #fcfcfc;
                }
                
                .woocommerce-table--order-details tfoot tr:last-child {
                    background-color: #f7fdff;
                    font-weight: bold;
                    color: #2271b1;
                }
                
                .woocommerce-table--order-details tfoot tr:last-child th,
                .woocommerce-table--order-details tfoot tr:last-child td {
                    border-bottom: none !important;
                    font-size: 1.1em;
                }
                
                /* Heading styles */
                .woocommerce-order-received h2 {
                    text-align: right;
                    font-family: Tahoma, Vazir, IRANSans, Arial;
                    border-right: 4px solid #2271b1;
                    padding-right: 10px;
                    margin: 30px 0 20px;
                    color: #333;
                    font-size: 18px;
                }
                
                /* Order overview boxes */
                .woocommerce-order-overview {
                    direction: rtl;
                    text-align: right;
                    display: flex !important;
                    flex-wrap: wrap;
                    background: #fcfcfc;
                    border-radius: 8px;
                    padding: 0 !important;
                    margin: 0 0 30px 0 !important;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                
                .woocommerce-order-overview li {
                    flex: 1;
                    padding: 15px !important;
                    margin: 0 !important;
                    border: none !important;
                    border-left: 1px solid #f0f0f0 !important;
                    border-bottom: 1px solid #f0f0f0 !important;
                    min-width: 200px;
                    box-sizing: border-box;
                }
                
                .woocommerce-order-overview li:last-child {
                    border-left: none !important;
                }
                
                .woocommerce-order-overview li strong {
                    display: block;
                    margin-top: 5px;
                    color: #2271b1;
                }
                
                /* Thankyou message */
                .woocommerce-thankyou-order-received {
                    background-color: #edf7ed;
                    color: #1e4620;
                    padding: 15px 20px;
                    border-radius: 8px;
                    text-align: center;
                    margin-bottom: 30px !important;
                    font-size: 16px;
                    direction: rtl;
                }
                
                /* Improve options display */
                .hpo-option-section {
                    margin-bottom: 0px;
                }
                
                .hpo-option-section h4 {
                    color: #333;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 8px;
                    margin-bottom: 10px;
                    font-size: 14px;
                    font-weight: 600;
                }
                
                .hpo-order-options-list {
                    list-style: none;
                    padding: 0;
                    margin: 0 0 15px 0;
                }
                
                .hpo-order-options-list li {
                    margin-bottom: 5px;
                    display: flex;
                    justify-content: space-between;
                }
                
                .hpo-option-name {
                    font-weight: normal;
                }
                
                .hpo-option-price {
                    color: #777;
                }
                
                .hpo-customer-notes {
                    background-color: #f9f9f9;
                    padding: 10px;
                    border-radius: 6px;
                    border-right: 3px solid #7fb85c;
                    font-style: italic;
                    color: #555;
                }
                
                .hpo-customer-notes p {
                    margin: 0;
                }
                
                /* Status badges */
                .grinding-status-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 13px;
                }
                
                .grinding-status-badge.ground {
                    background-color: #edf7ed;
                    color: #1e4620;
                }
                
                .grinding-status-badge.whole {
                    background-color: #fff4e5;
                    color: #663c00;
                }
                
                /* RTL specific adjustments */
                .woocommerce-table--order-details tfoot th,
                .woocommerce-table--order-details tbody th,
                .woocommerce-table--order-details thead th {
                    text-align: right;
                }
                
                .woocommerce-table--order-details td {
                    text-align: left;
                }
                
                /* Additional styles for product name */
                .woocommerce-table--order-details .product-name {
                    font-weight: 600;
                }
                
                /* For quantity and price columns in the product table */
                .woocommerce-table--order-details .product-total {
                    font-weight: bold;
                    color: #2271b1;
                }
                
                /* Make it look nice on mobile */
                @media (max-width: 768px) {
                    .woocommerce-table--order-details td,
                    .woocommerce-table--order-details th {
                        text-align: right;
                    }
                    
                    .hpo-order-options-list li {
                        flex-direction: column;
                    }
                    
                    .hpo-option-price {
                        margin-top: 3px;
                    }
                    
                    .woocommerce-order-overview li {
                        width: 100%;
                        flex: none;
                        border-left: none !important;
                    }
                    
                    .woocommerce-order-received h2 {
                        font-size: 16px;
                    }
                }
            ';
            wp_add_inline_style('hierarchical-product-options-public', $additional_css);
        }
    }

    /**
     * Hide products with hierarchical options from shop and other archives
     *
     * @param array $tax_query
     * @return array
     */
    public function hide_hierarchical_products_from_shop($tax_query) {
        if (is_admin()) {
            return $tax_query;
        }

        // Get all products that have hierarchical options
        $product_ids = $this->get_hierarchical_product_ids();
        
        if (!empty($product_ids)) {
            // Exclude these products from the query
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field' => 'name',
                'terms' => 'exclude-from-catalog',
                'operator' => 'IN'
            );
            
            // Use post__not_in for direct ID exclusion
            add_filter('woocommerce_product_query_post_not_in', function($ids) use ($product_ids) {
                return array_merge($ids, $product_ids);
            }, 10);
        }

        return $tax_query;
    }

    /**
     * Redirect single product pages of hierarchical products to home
     */
    public function redirect_hierarchical_product_pages() {
        if (is_product()) {
            global $post;
            $product_id = $post->ID;
            
            // Get all hierarchical product IDs
            $hierarchical_product_ids = $this->get_hierarchical_product_ids();
            
            // Check if the current product has hierarchical options
            if (in_array($product_id, $hierarchical_product_ids)) {
                wp_redirect(home_url());
                exit;
            }
        }
    }

    /**
     * Exclude products from search results
     *
     * @param WP_Query $query
     */
    public function exclude_products_from_search($query) {
        if (is_admin() || !is_search()) {
            return;
        }

        $product_ids = array();
        $assignments = $this->get_hierarchical_product_ids();
        if (!empty($assignments)) {
            $product_ids = array_merge($product_ids, $assignments);
        }

        if (!empty($product_ids)) {
            $query->set('post__not_in', $product_ids);
        }
    }

    /**
     * Exclude products from related products
     *
     * @param array $related_products
     * @param int $product_id
     * @param int $limit
     * @return array
     */
    public function exclude_from_related_products($related_products, $product_id, $limit) {
        $product_ids = array();
        $assignments = $this->get_hierarchical_product_ids();
        if (!empty($assignments)) {
            $product_ids = array_merge($product_ids, $assignments);
        }

        if (!empty($product_ids)) {
            $related_products = array_diff($related_products, $product_ids);
        }

        return $related_products;
    }

    /**
     * Get hierarchical product IDs
     *
     * @return array
     */
    private function get_hierarchical_product_ids() {
        $db = new Hierarchical_Product_Options_DB();
        $assignments = $db->get_category_product_assignments();
        $product_ids = array();
        foreach ($assignments as $assignment) {
            $product_ids[] = $assignment->wc_product_id;
        }
        return $product_ids;
    }
} 