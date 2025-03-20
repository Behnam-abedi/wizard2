<?php
/**
 * Plugin Name: Hierarchical Product Options
 * Plugin URI: https://example.com/plugins/hierarchical-product-options/
 * Description: Create hierarchical categories and product options with prices that affect the main product price.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: hierarchical-product-options
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('HPO_VERSION', '1.0.0');
define('HPO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HPO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main plugin class
require_once HPO_PLUGIN_DIR . 'includes/class-hierarchical-product-options.php';

// Initialize the plugin
function run_hierarchical_product_options() {
    $plugin = new Hierarchical_Product_Options();
    $plugin->run();
}
run_hierarchical_product_options();

// Debug code to verify the hook is registered
add_action('wp_footer', function() {
    if (is_checkout() || is_wc_endpoint_url('order-received') || is_account_page()) {
        echo '<!-- HPO Debug: wp_footer triggered on checkout/order page -->';
        echo '<!-- HPO Debug: Hook woocommerce_order_item_meta_end has ' . 
             (has_action('woocommerce_order_item_meta_end') ? 'actions' : 'no actions') . ' -->';
    }
}); 