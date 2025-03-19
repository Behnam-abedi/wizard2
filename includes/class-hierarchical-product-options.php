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
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_order_meta_in_admin'), 10, 1);
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
            
            $item->add_meta_data('_hpo_grinding', $grinding_data);
        }

        // Save calculated price
        if (isset($values['hpo_calculated_price'])) {
            $item->add_meta_data('_hpo_calculated_price', $values['hpo_calculated_price']);
        }
    }

    /**
     * Display order meta data in admin order page
     *
     * @param WC_Order $order
     */
    public function display_order_meta_in_admin($order) {
        echo '<div class="hpo-order-options">';
        echo '<h3>' . __('Product Options Details', 'hierarchical-product-options') . '</h3>';
        
        foreach ($order->get_items() as $item_id => $item) {
            echo '<div class="hpo-order-item-options">';
            echo '<h4>' . $item->get_name() . '</h4>';
            
            // Display categories
            $categories = $item->get_meta('_hpo_categories');
            if (!empty($categories)) {
                echo '<p><strong>' . __('Selected Categories:', 'hierarchical-product-options') . '</strong></p>';
                echo '<ul>';
                foreach ($categories as $category) {
                    echo '<li>' . esc_html($category['name']);
                    if (isset($category['price']) && $category['price'] > 0) {
                        echo ' (' . wc_price($category['price']) . ')';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            
            // Display products
            $products = $item->get_meta('_hpo_products');
            if (!empty($products)) {
                echo '<p><strong>' . __('Selected Products:', 'hierarchical-product-options') . '</strong></p>';
                echo '<ul>';
                foreach ($products as $product) {
                    echo '<li>' . esc_html($product['name']);
                    if (isset($product['price']) && $product['price'] > 0) {
                        echo ' (' . wc_price($product['price']) . ')';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            
            // Display weight
            $weight = $item->get_meta('_hpo_weight');
            if (!empty($weight)) {
                echo '<p><strong>' . __('Weight Option:', 'hierarchical-product-options') . '</strong> ';
                echo esc_html($weight['name']) . ' (×' . $weight['coefficient'] . ')</p>';
            }
            
            // Display grinding
            $grinding = $item->get_meta('_hpo_grinding');
            if (!empty($grinding)) {
                echo '<p><strong>' . __('Grinding Option:', 'hierarchical-product-options') . '</strong> ';
                if ($grinding['type'] === 'ground') {
                    echo __('Ground', 'hierarchical-product-options');
                    if (!empty($grinding['machine'])) {
                        echo ' - ' . esc_html($grinding['machine']['name']);
                        if ($grinding['machine']['price'] > 0) {
                            echo ' (' . wc_price($grinding['machine']['price']) . ')';
                        }
                    }
                } else {
                    echo __('Whole (No Grinding)', 'hierarchical-product-options');
                }
                echo '</p>';
            }
            
            // Display calculated price
            $calculated_price = $item->get_meta('_hpo_calculated_price');
            if (!empty($calculated_price)) {
                echo '<p><strong>' . __('Final Unit Price:', 'hierarchical-product-options') . '</strong> ';
                echo wc_price($calculated_price) . '</p>';
            }
            
            echo '</div>';
            echo '<hr>';
        }
        echo '</div>';
        
        // Add some CSS to style the output
        ?>
        <style>
            .hpo-order-options {
                margin: 20px 0;
                padding: 15px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .hpo-order-options h3 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .hpo-order-item-options {
                margin: 15px 0;
            }
            .hpo-order-item-options h4 {
                color: #23282d;
                margin: 0 0 10px;
            }
            .hpo-order-item-options ul {
                margin: 5px 0 15px 20px;
                list-style: disc;
            }
            .hpo-order-item-options p {
                margin: 5px 0;
            }
            .hpo-order-item-options strong {
                color: #23282d;
            }
        </style>
        <?php
    }
} 