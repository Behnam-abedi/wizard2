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
        
        // Localize script with data
        wp_localize_script(
            'hierarchical-product-options-admin',
            'hpo_data',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hpo_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'hierarchical-product-options'),
                    'category_name' => __('Category Name', 'hierarchical-product-options'),
                    'product_name' => __('Product Name', 'hierarchical-product-options'),
                    'product_price' => __('Product Price', 'hierarchical-product-options'),
                    'save' => __('Save', 'hierarchical-product-options'),
                    'cancel' => __('Cancel', 'hierarchical-product-options'),
                    'confirm_delete_category' => __('Are you sure you want to delete this category? All subcategories and products will also be deleted.', 'hierarchical-product-options'),
                    'confirm_delete_product' => __('Are you sure you want to delete this product?', 'hierarchical-product-options'),
                    'confirm_delete_weight' => __('Are you sure you want to delete this weight option?', 'hierarchical-product-options')
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
        add_action('wp_ajax_hpo_add_weight', array($this, 'ajax_add_weight'));
        add_action('wp_ajax_hpo_update_weight', array($this, 'ajax_update_weight'));
        add_action('wp_ajax_hpo_delete_weight', array($this, 'ajax_delete_weight'));
        add_action('wp_ajax_hpo_reorder_weights', array($this, 'ajax_reorder_weights'));
        add_action('wp_ajax_hpo_get_weights', array($this, 'ajax_get_weights'));
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
        $category_ids = isset($_POST['category_ids']) ? array_map('intval', (array) $_POST['category_ids']) : array();
        
        if (empty($wc_product_id)) {
            wp_send_json_error(__('Invalid product', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $result = $db->assign_categories_to_product($wc_product_id, $category_ids);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to assign categories', 'hierarchical-product-options'));
        }
    }

    /**
     * AJAX: Add weight option
     */
    public function ajax_add_weight() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'hierarchical-product-options'));
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $coefficient = isset($_POST['coefficient']) ? floatval($_POST['coefficient']) : 1;
        $wc_product_id = isset($_POST['wc_product_id']) ? intval($_POST['wc_product_id']) : 0;
        
        if (empty($name) || empty($wc_product_id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $id = $db->add_weight($name, $coefficient, $wc_product_id);
        
        if ($id) {
            wp_send_json_success(array(
                'id' => $id,
                'name' => $name,
                'coefficient' => $coefficient,
                'wc_product_id' => $wc_product_id
            ));
        } else {
            wp_send_json_error(__('Failed to add weight option', 'hierarchical-product-options'));
        }
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