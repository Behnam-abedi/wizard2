<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @since      1.0.0
 */
class Hierarchical_Product_Options_Admin {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Initialize AJAX handlers
        $this->ajax_handlers();
    }

    /**
     * Register the admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Hierarchical Product Options', 'hierarchical-product-options'),
            __('Product Options', 'hierarchical-product-options'),
            'manage_options',
            'hierarchical-product-options',
            array($this, 'display_admin_page'),
            'dashicons-menu-alt',
            30
        );
        
        add_submenu_page(
            'hierarchical-product-options',
            __('Settings', 'hierarchical-product-options'),
            __('Settings', 'hierarchical-product-options'),
            'manage_options',
            'hierarchical-product-options-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('hierarchical-product-options-settings-group', 'hpo_settings');
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'hierarchical-product-options') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'hierarchical-product-options-admin',
            HPO_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            HPO_VERSION
        );
        
        // JS
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
        
        wp_enqueue_script(
            'hierarchical-product-options-admin',
            HPO_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'),
            HPO_VERSION,
            true
        );
        
        // Localize the script with data
        wp_localize_script(
            'hierarchical-product-options-admin',
            'hpo_data',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hpo_nonce'),
                'strings' => array(
                    'confirm_delete_category' => __('Are you sure you want to delete this category? All subcategories and products will also be deleted.', 'hierarchical-product-options'),
                    'confirm_delete_product' => __('Are you sure you want to delete this product?', 'hierarchical-product-options'),
                    'product_name' => __('Product Name', 'hierarchical-product-options'),
                    'product_price' => __('Product Price', 'hierarchical-product-options'),
                    'save' => __('Save Changes', 'hierarchical-product-options'),
                    'cancel' => __('Cancel', 'hierarchical-product-options'),
                    'update_order' => __('Order updated successfully!', 'hierarchical-product-options'),
                    'rebuild_success' => __('Database tables rebuilt successfully!', 'hierarchical-product-options'),
                    'rebuild_error' => __('An error occurred during table rebuild.', 'hierarchical-product-options'),
                    'weight_name' => __('Weight Name', 'hierarchical-product-options'),
                    'weight_coefficient' => __('Weight Coefficient', 'hierarchical-product-options'),
                    'add_weight' => __('Add Weight Option', 'hierarchical-product-options'),
                    'no_weights' => __('No weight options found for this product.', 'hierarchical-product-options'),
                    'select_product' => __('Please select a product first.', 'hierarchical-product-options'),
                    'edit_weight' => __('Edit Weight Option', 'hierarchical-product-options'),
                    'select_required' => __('Please select both a product and a category', 'hierarchical-product-options'),
                    'confirm_delete_assignment' => __('Are you sure you want to delete this assignment?', 'hierarchical-product-options')
                )
            )
        );
    }

    /**
     * Display the main admin page
     */
    public function display_admin_page() {
        $db = new Hierarchical_Product_Options_DB();
        $categories = $db->get_categories();
        $products = $db->get_products();
        
        // Build hierarchical structure for display
        $category_map = array();
        $top_level_categories = array();
        
        foreach ($categories as $category) {
            $category_map[$category->id] = $category;
            $category->children = array();
            $category->products = array();
            
            if ($category->parent_id == 0) {
                $top_level_categories[] = $category;
            }
        }
        
        // Build hierarchy
        foreach ($categories as $category) {
            if ($category->parent_id > 0 && isset($category_map[$category->parent_id])) {
                $category_map[$category->parent_id]->children[] = $category;
            }
        }
        
        // Assign products to categories
        foreach ($products as $product) {
            if (isset($category_map[$product->category_id])) {
                $category_map[$product->category_id]->products[] = $product;
            }
        }
        
        include HPO_PLUGIN_DIR . 'admin/partials/admin-display.php';
    }

    /**
     * Display the settings page
     */
    public function display_settings_page() {
        $settings = get_option('hpo_settings', array());
        include HPO_PLUGIN_DIR . 'admin/partials/settings-display.php';
    }
    
    /**
     * Process AJAX requests
     */
    public function ajax_handlers() {
        add_action('wp_ajax_hpo_add_category', array($this, 'ajax_add_category'));
        add_action('wp_ajax_hpo_update_category', array($this, 'ajax_update_category'));
        add_action('wp_ajax_hpo_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_hpo_add_product', array($this, 'ajax_add_product'));
        add_action('wp_ajax_hpo_update_product', array($this, 'ajax_update_product'));
        add_action('wp_ajax_hpo_delete_product', array($this, 'ajax_delete_product'));
        add_action('wp_ajax_hpo_update_order', array($this, 'ajax_update_order'));
        add_action('wp_ajax_hpo_assign_product_categories', array($this, 'ajax_assign_product_categories'));
        add_action('wp_ajax_hpo_delete_assignment', array($this, 'ajax_delete_assignment'));
        add_action('wp_ajax_hpo_add_weight', array($this, 'ajax_add_weight'));
        add_action('wp_ajax_hpo_update_weight', array($this, 'ajax_update_weight'));
        add_action('wp_ajax_hpo_delete_weight', array($this, 'ajax_delete_weight'));
        add_action('wp_ajax_hpo_reorder_weights', array($this, 'ajax_reorder_weights'));
        add_action('wp_ajax_hpo_get_weights', array($this, 'ajax_get_weights'));
        add_action('wp_ajax_hpo_rebuild_tables', array($this, 'ajax_rebuild_tables'));
        add_action('wp_ajax_hpo_add_grinder', array($this, 'ajax_add_grinder'));
        add_action('wp_ajax_hpo_update_grinder', array($this, 'ajax_update_grinder'));
        add_action('wp_ajax_hpo_delete_grinder', array($this, 'ajax_delete_grinder'));
        add_action('wp_ajax_hpo_reorder_grinders', array($this, 'ajax_reorder_grinders'));
        add_action('wp_ajax_hpo_get_grinders', array($this, 'ajax_get_grinders'));
    }
    
    /**
     * AJAX: Add a new category
     */
    public function ajax_add_category() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        
        if (empty($name)) {
            wp_send_json_error(__('Category name is required', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $id = $db->add_category($name, $parent_id);
        
        if ($id) {
            wp_send_json_success(array(
                'id' => $id,
                'name' => $name,
                'parent_id' => $parent_id
            ));
        } else {
            wp_send_json_error(__('Failed to add category', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Update category
     */
    public function ajax_update_category() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        
        if (empty($id) || empty($name)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        $result = $db->update_category($id, array(
            'name' => $name,
            'parent_id' => $parent_id
        ));
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to update category', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Delete category
     */
    public function ajax_delete_category() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $result = $db->delete_category($id);
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete category', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Add product
     */
    public function ajax_add_product() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if (empty($name) || empty($category_id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $id = $db->add_product($name, $price, $category_id);
        
        if ($id) {
            wp_send_json_success(array(
                'id' => $id,
                'name' => $name,
                'price' => $price,
                'category_id' => $category_id
            ));
        } else {
            wp_send_json_error(__('Failed to add product', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Update product
     */
    public function ajax_update_product() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if (empty($id) || empty($name) || empty($category_id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        $result = $db->update_product($id, array(
            'name' => $name,
            'price' => $price,
            'category_id' => $category_id
        ));
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to update product', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Delete product
     */
    public function ajax_delete_product() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $result = $db->delete_product($id);
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete product', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Update item order
     */
    public function ajax_update_order() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $item_type = isset($_POST['item_type']) ? sanitize_text_field($_POST['item_type']) : '';
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
        
        if (empty($item_type) || empty($item_id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        if ($item_type === 'category') {
            $result = $db->update_category($item_id, array(
                'parent_id' => $parent_id,
                'sort_order' => $sort_order
            ));
        } else if ($item_type === 'product') {
            $result = $db->update_product($item_id, array(
                'category_id' => $parent_id,
                'sort_order' => $sort_order
            ));
        } else {
            $result = false;
        }
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to update order', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Assign product categories
     */
    public function ajax_assign_product_categories() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $wc_product_id = isset($_POST['wc_product_id']) ? intval($_POST['wc_product_id']) : 0;
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if (empty($wc_product_id) || empty($category_id)) {
            wp_send_json_error(__('Please select both a product and a category', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $result = $db->assign_categories_to_product($wc_product_id, $category_id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to assign category', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Delete category-product assignment
     */
    public function ajax_delete_assignment() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $wc_product_id = isset($_POST['wc_product_id']) ? intval($_POST['wc_product_id']) : 0;
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if (empty($wc_product_id) || empty($category_id)) {
            wp_send_json_error(__('Invalid assignment data', 'hierarchical-product-options'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'hpo_product_assignments';
        
        $result = $wpdb->delete(
            $table,
            array(
                'wc_product_id' => $wc_product_id,
                'category_id' => $category_id
            )
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete assignment', 'hierarchical-product-options'));
        }
    }

    /**
     * AJAX: Add weight option
     */
    public function ajax_add_weight() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
            return;
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $coefficient = isset($_POST['coefficient']) ? floatval($_POST['coefficient']) : 1;
        $wc_product_id = isset($_POST['wc_product_id']) ? intval($_POST['wc_product_id']) : 0;
        
        // Log request data for debugging
        error_log('Weight add request: ' . wp_json_encode([
            'name' => $name,
            'coefficient' => $coefficient,
            'wc_product_id' => $wc_product_id
        ]));
        
        if (empty($name) || empty($wc_product_id)) {
            wp_send_json_error(__('Invalid data: Name or product ID is empty', 'hierarchical-product-options'));
            return;
        }
        
        try {
            global $wpdb;
            $db = new Hierarchical_Product_Options_DB();
            
            // Check if the weights table exists
            $table_name = $wpdb->prefix . 'hpo_weights';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            
            if (!$table_exists) {
                // Table doesn't exist, create it
                error_log('Weights table does not exist, creating...');
                $db->create_tables();
            }
            
            // Add the weight option
            $id = $db->add_weight($name, $coefficient, $wc_product_id);
            
            if ($id) {
                error_log('Weight added successfully: ' . $id);
                wp_send_json_success(array(
                    'id' => $id,
                    'name' => $name,
                    'coefficient' => $coefficient,
                    'wc_product_id' => $wc_product_id
                ));
            } else {
                $last_error = $wpdb->last_error;
                error_log('Failed to add weight option: ' . $last_error);
                wp_send_json_error(__('Failed to add weight option: Database error', 'hierarchical-product-options') . ' - ' . $last_error);
            }
        } catch (Exception $e) {
            error_log('Exception while adding weight: ' . $e->getMessage());
            wp_send_json_error(__('Exception occurred: ', 'hierarchical-product-options') . $e->getMessage());
        }
        
        // Safety exit to ensure a response is always sent
        wp_send_json_error(__('Unknown error occurred', 'hierarchical-product-options'));
    }
    
    /**
     * AJAX: Update weight option
     */
    public function ajax_update_weight() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $coefficient = isset($_POST['coefficient']) ? floatval($_POST['coefficient']) : 1;
        
        if (empty($id) || empty($name)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        $result = $db->update_weight($id, array(
            'name' => $name,
            'coefficient' => $coefficient
        ));
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to update weight option', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Delete weight option
     */
    public function ajax_delete_weight() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $result = $db->delete_weight($id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete weight option', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Reorder weight options
     */
    public function ajax_reorder_weights() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        
        if (empty($items) || !is_array($items)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        foreach ($items as $index => $id) {
            $db->update_weight($id, array('sort_order' => $index));
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Get weights for a product
     */
    public function ajax_get_weights() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $wc_product_id = isset($_POST['wc_product_id']) ? intval($_POST['wc_product_id']) : 0;
        
        if (empty($wc_product_id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $weights = $db->get_weights_for_product($wc_product_id);
        
        wp_send_json_success($weights);
    }

    /**
     * AJAX: Rebuild database tables
     */
    public function ajax_rebuild_tables() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'hierarchical-product-options')));
            return;
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        try {
            global $wpdb;
            
            // Get current table status
            $weights_table = $wpdb->prefix . 'hpo_weights';
            $grinders_table = $wpdb->prefix . 'hpo_grinders';
            
            $weights_exists = $wpdb->get_var("SHOW TABLES LIKE '{$weights_table}'") === $weights_table;
            $grinders_exists = $wpdb->get_var("SHOW TABLES LIKE '{$grinders_table}'") === $grinders_table;
            
            $status_report = "Table status before rebuild:\n";
            $status_report .= "Weights table exists: " . ($weights_exists ? 'Yes' : 'No') . "\n";
            $status_report .= "Grinders table exists: " . ($grinders_exists ? 'Yes' : 'No') . "\n";
            
            if ($weights_exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$weights_table}");
                $status_report .= "Weights table record count: {$count}\n";
            }
            
            if ($grinders_exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$grinders_table}");
                $status_report .= "Grinders table record count: {$count}\n";
            }
            
            // Create/recreate tables
            $db->create_tables();
            
            // Get new table status
            $weights_exists = $wpdb->get_var("SHOW TABLES LIKE '{$weights_table}'") === $weights_table;
            $grinders_exists = $wpdb->get_var("SHOW TABLES LIKE '{$grinders_table}'") === $grinders_table;
            
            $status_report .= "\nTable status after rebuild:\n";
            $status_report .= "Weights table exists: " . ($weights_exists ? 'Yes' : 'No') . "\n";
            $status_report .= "Grinders table exists: " . ($grinders_exists ? 'Yes' : 'No') . "\n";
            
            if ($weights_exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$weights_table}");
                $status_report .= "Weights table record count: {$count}\n";
            }
            
            if ($grinders_exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$grinders_table}");
                $status_report .= "Grinders table record count: {$count}\n";
                
                // Try to insert a test record in grinders table
                $test_grinder_result = $db->add_grinder('Test Grinder', 1.0, 0);
                $status_report .= "Test grinder insert result: " . ($test_grinder_result ? "Success (ID: {$test_grinder_result})" : "Failed") . "\n";
                
                if ($test_grinder_result) {
                    // Clean up test record
                    $wpdb->delete($grinders_table, ['id' => $test_grinder_result]);
                }
            }
            
            if ($weights_exists) {
                // Try to insert a test record
                $test_weight_result = $db->add_weight('Test Weight', 1.0, 1, 0);
                $status_report .= "Test weight insert result: " . ($test_weight_result ? "Success (ID: {$test_weight_result})" : "Failed") . "\n";
                
                if ($test_weight_result) {
                    // Clean up test record
                    $wpdb->delete($weights_table, ['id' => $test_weight_result]);
                }
            }
            
            error_log($status_report);
            wp_send_json_success($status_report);
        } catch (Exception $e) {
            error_log('Exception during table rebuild: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for adding a new grinder
     */
    public function ajax_add_grinder() {
        // Check nonce for security
        check_ajax_referer('hpo_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'hierarchical-product-options')));
            return;
        }
        
        // Debug log
        error_log('Starting ajax_add_grinder...');

        // Validate required fields
        if (empty($_POST['name'])) {
            error_log('Error: Name is required');
            wp_send_json_error(array('message' => __('Grinder name is required.', 'hierarchical-product-options')));
            return;
        }
        
        // Sanitize input
        $name = sanitize_text_field($_POST['name']);
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
        
        error_log('Validated inputs - Name: ' . $name . ', Price: ' . $price);
        
        // Add to database
        $db = new Hierarchical_Product_Options_DB();
        
        // Debug log - Check DB connection
        error_log('DB class initialized. About to add grinder');
        
        try {
            $grinder_id = $db->add_grinder($name, $price);
            
            if ($grinder_id) {
                error_log('Successfully added grinder with ID: ' . $grinder_id);
                
                $grinder = $db->get_grinder($grinder_id);
                
                if ($grinder) {
                    error_log('Successfully retrieved new grinder');
                    wp_send_json_success(array(
                        'message' => __('Grinder option added successfully.', 'hierarchical-product-options'),
                        'grinder' => $grinder
                    ));
                } else {
                    error_log('Failed to retrieve new grinder');
                    wp_send_json_error(array('message' => __('Could not retrieve the newly added grinder.', 'hierarchical-product-options')));
                }
            } else {
                error_log('Failed to add grinder. Insert returned false or 0.');
                wp_send_json_error(array('message' => __('Failed to add grinder option.', 'hierarchical-product-options')));
            }
        } catch (Exception $e) {
            error_log('Exception while adding grinder: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An error occurred while adding the grinder option.', 'hierarchical-product-options')));
        }
        
        // This should not be reached, but just in case
        error_log('End of ajax_add_grinder reached without sending response');
        wp_die();
    }
    
    /**
     * AJAX handler for updating a grinder
     */
    public function ajax_update_grinder() {
        // Check nonce for security
        check_ajax_referer('hpo_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'hierarchical-product-options')));
            return;
        }
        
        // Debug log
        error_log('Starting ajax_update_grinder...');
        
        // Validate required fields
        if (empty($_POST['id'])) {
            error_log('Error: Grinder ID is required');
            wp_send_json_error(array('message' => __('Grinder ID is required.', 'hierarchical-product-options')));
            return;
        }
        
        if (empty($_POST['name'])) {
            error_log('Error: Grinder name is required');
            wp_send_json_error(array('message' => __('Grinder name is required.', 'hierarchical-product-options')));
            return;
        }
        
        // Sanitize input
        $id = absint($_POST['id']);
        $name = sanitize_text_field($_POST['name']);
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
        
        error_log('Updating grinder with ID: ' . $id . ', Name: ' . $name . ', Price: ' . $price);
        
        // Update database
        $db = new Hierarchical_Product_Options_DB();
        
        $data = array(
            'name' => $name,
            'price' => $price
        );
        
        try {
            $result = $db->update_grinder($id, $data);
            
            if ($result !== false) {
                error_log('Successfully updated grinder');
                
                $grinder = $db->get_grinder($id);
                
                if ($grinder) {
                    wp_send_json_success(array(
                        'message' => __('Grinder option updated successfully.', 'hierarchical-product-options'),
                        'grinder' => $grinder
                    ));
                } else {
                    wp_send_json_error(array('message' => __('Failed to retrieve the updated grinder.', 'hierarchical-product-options')));
                }
            } else {
                error_log('Failed to update grinder');
                wp_send_json_error(array('message' => __('Failed to update grinder option.', 'hierarchical-product-options')));
            }
        } catch (Exception $e) {
            error_log('Exception while updating grinder: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An error occurred while updating the grinder option.', 'hierarchical-product-options')));
        }
        
        // This should not be reached, but just in case
        wp_die();
    }
    
    /**
     * AJAX handler for deleting a grinder
     */
    public function ajax_delete_grinder() {
        // Check nonce for security
        check_ajax_referer('hpo_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'hierarchical-product-options')));
            return;
        }
        
        // Debug log
        error_log('Starting ajax_delete_grinder...');
        
        // Validate required fields
        if (empty($_POST['id'])) {
            error_log('Error: Grinder ID is required');
            wp_send_json_error(array('message' => __('Grinder ID is required.', 'hierarchical-product-options')));
            return;
        }
        
        // Sanitize input
        $id = absint($_POST['id']);
        
        error_log('Deleting grinder with ID: ' . $id);
        
        // Delete from database
        $db = new Hierarchical_Product_Options_DB();
        
        try {
            $result = $db->delete_grinder($id);
            
            if ($result) {
                error_log('Successfully deleted grinder');
                wp_send_json_success(array('message' => __('Grinder option deleted successfully.', 'hierarchical-product-options')));
            } else {
                error_log('Failed to delete grinder');
                wp_send_json_error(array('message' => __('Failed to delete grinder option.', 'hierarchical-product-options')));
            }
        } catch (Exception $e) {
            error_log('Exception while deleting grinder: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An error occurred while deleting the grinder option.', 'hierarchical-product-options')));
        }
        
        // This should not be reached, but just in case
        wp_die();
    }
    
    /**
     * AJAX: Reorder grinder options
     */
    public function ajax_reorder_grinders() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
            return;
        }
        
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        
        if (empty($items) || !is_array($items)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
            return;
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        foreach ($items as $index => $id) {
            $db->update_grinder($id, array('sort_order' => $index));
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for getting all grinder options
     */
    public function ajax_get_grinders() {
        // Check nonce for security
        check_ajax_referer('hpo_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'hierarchical-product-options')));
            return;
        }
        
        // Debug log
        error_log('Starting ajax_get_grinders...');
        
        // Get from database
        $db = new Hierarchical_Product_Options_DB();
        
        try {
            $grinders = $db->get_all_grinders();
            
            if ($grinders !== false) {
                error_log('Successfully retrieved ' . count($grinders) . ' grinders');
                wp_send_json_success($grinders);
            } else {
                error_log('Failed to retrieve grinders');
                wp_send_json_error(array('message' => __('Failed to retrieve grinder options.', 'hierarchical-product-options')));
            }
        } catch (Exception $e) {
            error_log('Exception while retrieving grinders: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An error occurred while retrieving grinder options.', 'hierarchical-product-options')));
        }
        
        // This should not be reached, but just in case
        wp_die();
    }

    /**
     * Render categories recursively
     */
    public function render_categories_recursive($categories) {
        foreach ($categories as $category): ?>
        <li class="hpo-category-item" data-id="<?php echo esc_attr($category->id); ?>">
            <div class="hpo-item-header">
                <span class="hpo-drag-handle dashicons dashicons-menu"></span>
                <span class="hpo-item-name"><?php echo esc_html($category->name); ?></span>
                <div class="hpo-item-actions">
                    <button class="hpo-add-subcategory button-small" data-parent-id="<?php echo esc_attr($category->id); ?>">
                        <span class="dashicons dashicons-plus"></span> <?php echo esc_html__('Sub-category', 'hierarchical-product-options'); ?>
                    </button>
                    <button class="hpo-add-product-to-category button-small" data-category-id="<?php echo esc_attr($category->id); ?>">
                        <span class="dashicons dashicons-plus"></span> <?php echo esc_html__('Product', 'hierarchical-product-options'); ?>
                    </button>
                    <button class="hpo-edit-category button-small" data-id="<?php echo esc_attr($category->id); ?>">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button class="hpo-delete-category button-small" data-id="<?php echo esc_attr($category->id); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            
            <?php if (!empty($category->products)): ?>
            <ul class="hpo-products-list" data-category-id="<?php echo esc_attr($category->id); ?>">
                <?php foreach ($category->products as $product): ?>
                <li class="hpo-product-item" data-id="<?php echo esc_attr($product->id); ?>">
                    <div class="hpo-item-header">
                        <span class="hpo-drag-handle dashicons dashicons-menu"></span>
                        <span class="hpo-item-name"><?php echo esc_html($product->name); ?></span>
                        <span class="hpo-item-price"><?php echo wc_price($product->price); ?></span>
                        <div class="hpo-item-actions">
                            <button class="hpo-edit-product button-small" data-id="<?php echo esc_attr($product->id); ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button class="hpo-delete-product button-small" data-id="<?php echo esc_attr($product->id); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            
            <?php if (!empty($category->children)): ?>
            <ul class="hpo-categories-list">
                <?php $this->render_categories_recursive($category->children); ?>
            </ul>
            <?php endif; ?>
        </li>
        <?php
        endforeach;
    }
} 