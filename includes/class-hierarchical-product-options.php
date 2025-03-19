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
        if (isset($_POST['hpo_selected_option'])) {
            $option_id = absint($_POST['hpo_selected_option']);
            
            if ($option_id > 0) {
                $db = new Hierarchical_Product_Options_DB();
                $product = $db->get_product($option_id);
                
                if ($product) {
                    $cart_item_data['hpo_option'] = array(
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price
                    );
                }
            }
        }
        
        return $cart_item_data;
    }

    /**
     * Get cart item from session
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['hpo_option'])) {
            $cart_item['hpo_option'] = $values['hpo_option'];
        }
        
        return $cart_item;
    }

    /**
     * Change cart item price
     */
    public function change_cart_item_price($price, $cart_item, $cart_item_key) {
        if (isset($cart_item['hpo_option'])) {
            $settings = get_option('hpo_settings', array(
                'update_price' => 'yes'
            ));
            
            if ($settings['update_price'] === 'yes') {
                $price = wc_price($cart_item['hpo_option']['price']);
            }
        }
        
        return $price;
    }
} 