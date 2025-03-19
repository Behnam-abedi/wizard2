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
        
        // This critical filter ensures the correct price is used for order totals
        add_filter('woocommerce_before_calculate_totals', array($this, 'before_calculate_totals'), 10, 1);
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
        
        // Generate a unique key for this cart item to prevent merging in WooCommerce
        if (!empty($cart_item_data['hpo_categories']) || !empty($cart_item_data['hpo_products']) || 
            !empty($cart_item_data['hpo_weight']) || !empty($cart_item_data['hpo_grinding_machine'])) {
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        
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
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }
        
        // Loop through cart items
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $this->change_cart_item_price($cart_item);
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
        
        // Just return the current product price which will reflect any changes
        // we've already made in change_cart_item_price() and before_calculate_totals()
        return $cart_item['data']->get_price_html();
    }
} 