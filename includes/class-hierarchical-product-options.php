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
        add_filter('woocommerce_cart_item_price', array($this, 'change_cart_item_price'), 10, 3);
        
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
     * Add option data to cart item
     */
    public function add_option_data_to_cart($cart_item_data, $product_id, $variation_id) {
        // Add product option
        if (isset($_POST['hpo_selected_option']) && !empty($_POST['hpo_selected_option'])) {
            $option_id = absint($_POST['hpo_selected_option']);
            
            if ($option_id > 0) {
                $db = new Hierarchical_Product_Options_DB();
                $product = $db->get_product($option_id);
                
                if ($product) {
                    // Get the base product price
                    $base_price = floatval(get_post_meta($product_id, '_price', true));
                    
                    // Store both the option details and the combined price
                    $cart_item_data['hpo_option'] = array(
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'base_price' => $base_price,
                        'total_price' => $base_price + floatval($product->price)
                    );
                }
            }
        }
        
        // Add weight options
        if (isset($_POST['hpo_selected_weight']) && !empty($_POST['hpo_selected_weight'])) {
            $weight_ids = explode(',', $_POST['hpo_selected_weight']);
            $weight_options = array();
            $coefficient_total = 1; // Start with 1 (neutral)
            
            if (!empty($weight_ids)) {
                $db = new Hierarchical_Product_Options_DB();
                
                foreach ($weight_ids as $weight_id) {
                    $weight_id = absint($weight_id);
                    if ($weight_id > 0) {
                        $weight = $db->get_weight($weight_id);
                        if ($weight) {
                            // Add to weight options
                            $weight_options[] = array(
                                'id' => $weight->id,
                                'name' => $weight->name,
                                'coefficient' => floatval($weight->coefficient)
                            );
                            
                            // Multiply coefficient
                            $coefficient_total *= floatval($weight->coefficient);
                        }
                    }
                }
                
                if (!empty($weight_options)) {
                    $cart_item_data['hpo_weights'] = array(
                        'options' => $weight_options,
                        'coefficient_total' => $coefficient_total
                    );
                }
            }
        }
        
        // Add a unique key to ensure WooCommerce treats this as a unique cart item
        $cart_item_data['unique_key'] = md5(microtime() . rand());
        
        return $cart_item_data;
    }

    /**
     * Get cart item from session
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['hpo_option'])) {
            $cart_item['hpo_option'] = $values['hpo_option'];
        }
        
        if (isset($values['hpo_weights'])) {
            $cart_item['hpo_weights'] = $values['hpo_weights'];
        }
        
        // If we have the unique key, copy it too
        if (isset($values['unique_key'])) {
            $cart_item['unique_key'] = $values['unique_key'];
        }
        
        return $cart_item;
    }

    /**
     * Change cart item price
     */
    public function change_cart_item_price($price, $cart_item, $cart_item_key) {
        if (isset($cart_item['hpo_option']) || isset($cart_item['hpo_weights'])) {
            $settings = get_option('hpo_settings', array(
                'update_price' => 'yes'
            ));
            
            if ($settings['update_price'] === 'yes') {
                $product_price = 0;
                
                // Calculate base price + options
                if (isset($cart_item['hpo_option']) && isset($cart_item['hpo_option']['total_price'])) {
                    $product_price = floatval($cart_item['hpo_option']['total_price']);
                } else {
                    // Fallback to base price if no option
                    $product_id = $cart_item['product_id'];
                    $product_price = floatval(get_post_meta($product_id, '_price', true));
                }
                
                // Apply weight coefficients if any
                if (isset($cart_item['hpo_weights']) && isset($cart_item['hpo_weights']['coefficient_total'])) {
                    $coefficient = floatval($cart_item['hpo_weights']['coefficient_total']);
                    if ($coefficient > 0) {
                        $product_price = $product_price * $coefficient;
                    }
                }
                
                $price = wc_price($product_price);
            }
        }
        
        return $price;
    }

    /**
     * Update the product price before cart totals are calculated
     */
    public function before_calculate_totals($cart) {
        // Loop through cart items
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Get the settings
            $settings = get_option('hpo_settings', array(
                'update_price' => 'yes'
            ));
            
            if ($settings['update_price'] === 'yes') {
                $product_price = 0;
                
                // Calculate base price + options
                if (isset($cart_item['hpo_option']) && isset($cart_item['hpo_option']['total_price'])) {
                    $product_price = floatval($cart_item['hpo_option']['total_price']);
                } else {
                    // Fallback to base price if no option
                    $product_id = $cart_item['product_id'];
                    $product_price = floatval(get_post_meta($product_id, '_price', true));
                }
                
                // Apply weight coefficients if any
                if (isset($cart_item['hpo_weights']) && isset($cart_item['hpo_weights']['coefficient_total'])) {
                    $coefficient = floatval($cart_item['hpo_weights']['coefficient_total']);
                    if ($coefficient > 0) {
                        $product_price = $product_price * $coefficient;
                    }
                }
                
                // Set the product price
                $cart_item['data']->set_price($product_price);
            }
        }
    }
} 