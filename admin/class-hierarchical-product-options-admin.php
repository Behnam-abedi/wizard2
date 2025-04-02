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
            __('گزینه‌های سلسله مراتبی محصولات', 'hierarchical-product-options'),
            __('تنظیمات HPO', 'hierarchical-product-options'),
            'manage_options',
            'hierarchical-product-options',
            array($this, 'display_admin_page'),
            'dashicons-list-view',
            25
        );
        
        add_submenu_page(
            'hierarchical-product-options',
            __('تنظیمات', 'hierarchical-product-options'),
            __('تنظیمات', 'hierarchical-product-options'),
            'manage_options',
            'hierarchical-product-options-settings',
            array($this, 'display_settings_page')
        );
        
        // Check if database needs upgrade
        $this->maybe_upgrade_database();
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
                    'confirm_delete_category' => __('آیا مطمئن هستید که می‌خواهید این دسته‌بندی را حذف کنید؟ تمام زیرمجموعه‌ها و محصولات نیز حذف خواهند شد.', 'hierarchical-product-options'),
                    'confirm_delete_product' => __('آیا مطمئن هستید که می‌خواهید این محصول را حذف کنید؟', 'hierarchical-product-options'),
                    'product_name' => __('نام محصول', 'hierarchical-product-options'),
                    'product_price' => __('قیمت محصول', 'hierarchical-product-options'),
                    'save' => __('ذخیره تغییرات', 'hierarchical-product-options'),
                    'cancel' => __('انصراف', 'hierarchical-product-options'),
                    'update_order' => __('ترتیب با موفقیت به‌روز شد!', 'hierarchical-product-options'),
                    'rebuild_success' => __('جداول پایگاه داده با موفقیت بازسازی شدند!', 'hierarchical-product-options'),
                    'rebuild_error' => __('خطایی هنگام بازسازی جداول رخ داد.', 'hierarchical-product-options'),
                    'weight_name' => __('نام وزن', 'hierarchical-product-options'),
                    'weight_coefficient' => __('ضریب وزن', 'hierarchical-product-options'),
                    'add_weight' => __('افزودن گزینه وزن', 'hierarchical-product-options'),
                    'no_weights' => __('هیچ گزینه وزنی برای این محصول یافت نشد.', 'hierarchical-product-options'),
                    'select_product' => __('لطفاً ابتدا یک محصول انتخاب کنید.', 'hierarchical-product-options'),
                    'edit_weight' => __('ویرایش گزینه وزن', 'hierarchical-product-options'),
                    'select_required' => __('لطفاً هم یک محصول و هم یک دسته‌بندی انتخاب کنید', 'hierarchical-product-options'),
                    'confirm_delete_assignment' => __('آیا مطمئن هستید که می‌خواهید این تخصیص را حذف کنید؟', 'hierarchical-product-options'),
                    'select_category' => __('یک دسته‌بندی انتخاب کنید...', 'hierarchical-product-options'),
                    'select_parent_category' => __('یک دسته‌بندی مادر انتخاب کنید...', 'hierarchical-product-options'),
                    'loading' => __('در حال بارگذاری...', 'hierarchical-product-options'),
                    'loading_error' => __('خطا در بارگذاری', 'hierarchical-product-options'),
                    'category_not_found' => __('دسته‌بندی یافت نشد', 'hierarchical-product-options')
                )
            )
        );
    }

    /**
     * Display the admin page
     * Updated to ensure fresh data is used
     */
    public function display_admin_page() {
        // Clear cache first to ensure fresh data
        delete_transient('hpo_categories');
        delete_transient('hpo_category_assignments');
        
        $db = new Hierarchical_Product_Options_DB();
        $categories = $db->get_categories();
        $products = $db->get_products();
        
        // Get all assignments
        $assignments = $db->get_category_product_assignments();
        
        // Clean up any orphaned assignments (where category no longer exists)
        $valid_category_ids = array_map(function($category) {
            return $category->id;
        }, $categories);
        
        if (!empty($valid_category_ids)) {
            global $wpdb;
            $assignments_table = $wpdb->prefix . 'hpo_product_assignments';
            
            // Use NOT IN to find assignments with invalid category IDs
            $valid_category_ids_str = implode(',', array_map('intval', $valid_category_ids));
            if (!empty($valid_category_ids_str)) {
                $wpdb->query("DELETE FROM $assignments_table WHERE category_id NOT IN ($valid_category_ids_str)");
            }
        }
        
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
        add_action('wp_ajax_hpo_update_assignment_description', array($this, 'ajax_update_assignment_description'));
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
        add_action('wp_ajax_hpo_refresh_categories', array($this, 'ajax_refresh_categories'));
        add_action('wp_ajax_hpo_get_fresh_categories', array($this, 'ajax_get_fresh_categories'));
        add_action('wp_ajax_hpo_clean_inconsistent_data', array($this, 'ajax_clean_inconsistent_data'));
        add_action('wp_ajax_hpo_reorder_assignments', array($this, 'ajax_reorder_assignments'));
        add_action('wp_ajax_hpo_init_assignments_sort_order', array($this, 'ajax_init_assignments_sort_order'));
        add_action('wp_ajax_hpo_update_product_description', array($this, 'ajax_update_product_description'));
        add_action('wp_ajax_hpo_reorder_product_categories', array($this, 'ajax_reorder_product_categories'));
        add_action('wp_ajax_hpo_delete_product_assignments', array($this, 'ajax_delete_product_assignments'));
        add_action('wp_ajax_hpo_update_multiple_sort_orders', array($this, 'ajax_update_multiple_sort_orders'));
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
     * Updated to ensure all related records and cache are properly cleared
     */
    public function ajax_delete_category() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($id)) {
            wp_send_json_error(__('شناسه دسته‌بندی نامعتبر است', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        // Get all child categories first
        $child_categories = $this->get_all_child_categories($id);
        $all_categories_to_delete = array_merge([$id], $child_categories);
        
        // Delete all product assignments for these categories
        foreach ($all_categories_to_delete as $category_id) {
            $db->delete_product_assignments_by_category($category_id);
        }
        
        // Delete all child categories first
        foreach ($child_categories as $child_id) {
            $db->delete_category($child_id);
        }
        
        // Now delete the main category
        $result = $db->delete_category($id);
        
        if ($result !== false) {
            // Clear all caches
            delete_transient('hpo_category_assignments');
            delete_transient('hpo_categories');
            wp_cache_flush();
            
            wp_send_json_success();
        } else {
            wp_send_json_error(__('خطا در حذف دسته‌بندی', 'hierarchical-product-options'));
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
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        // محدود کردن طول توضیحات به 53 کاراکتر
        $description = mb_substr($description, 0, 53);
        
        if (empty($name) || empty($category_id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $id = $db->add_product($name, $price, $category_id, $description);
        
        if ($id) {
            wp_send_json_success(array(
                'id' => $id,
                'name' => $name,
                'price' => $price,
                'category_id' => $category_id,
                'description' => $description
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
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        // محدود کردن طول توضیحات به 53 کاراکتر
        $description = mb_substr($description, 0, 53);
        
        if (empty($id) || empty($name) || empty($category_id)) {
            wp_send_json_error(__('Invalid data', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        $result = $db->update_product($id, array(
            'name' => $name,
            'price' => $price,
            'category_id' => $category_id,
            'description' => $description
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
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $item_type = isset($_POST['item_type']) ? sanitize_text_field($_POST['item_type']) : '';
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
        
        if (empty($item_type) || empty($item_id)) {
            wp_send_json_error(__('اطلاعات نامعتبر', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        if ($item_type === 'category') {
            $result = $db->update_category($item_id, array(
                'parent_id' => $parent_id,
                'sort_order' => $sort_order
            ));
            
            // Clear cache
            delete_transient('hpo_categories');
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
            wp_send_json_error(__('خطا در به‌روزرسانی ترتیب', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Assign product to categories
     * Updated to verify category exists before assigning
     * Works with only parent categories shown in dropdown
     */
    public function ajax_assign_product_categories() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $wc_product_id = isset($_POST['wc_product_id']) ? intval($_POST['wc_product_id']) : 0;
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        if (empty($wc_product_id) || empty($category_id)) {
            wp_send_json_error(__('اطلاعات نامعتبر', 'hierarchical-product-options'));
        }
        
        // Check if product still exists
        $product = wc_get_product($wc_product_id);
        if (!$product) {
            wp_send_json_error(__('محصول یافت نشد', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        // Check if category exists
        $category = $db->get_category($category_id);
        if (!$category) {
            wp_send_json_error(__('دسته‌بندی انتخاب شده وجود ندارد', 'hierarchical-product-options'));
        }
        
        // Check if this assignment already exists
        $existing = $db->get_category_product_assignment($wc_product_id, $category_id);
        if ($existing) {
            wp_send_json_error(__('این محصول قبلاً به این دسته‌بندی اختصاص داده شده است', 'hierarchical-product-options'));
        }
        
        // Assign the main category
        $result = $db->assign_product_to_category($wc_product_id, $category_id, $description);
        
        if (!$result) {
            wp_send_json_error(__('خطا در اختصاص محصول به دسته‌بندی', 'hierarchical-product-options'));
        }
        
        // Get all child categories and assign them too
        $child_categories = $this->get_all_child_categories($category_id);
        
        foreach ($child_categories as $child_id) {
            // First check if child category still exists
            $child_category = $db->get_category($child_id);
            if (!$child_category) {
                continue; // Skip if category doesn't exist
            }
            
            // Skip if already assigned
            $existing = $db->get_category_product_assignment($wc_product_id, $child_id);
            if (!$existing) {
                $db->assign_product_to_category($wc_product_id, $child_id, $description);
            }
        }
        
        // Clear any cache
        delete_transient('hpo_category_assignments');
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Refresh categories list
     * Gets fresh data about categories and their children
     */
    public function ajax_refresh_categories() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if (empty($category_id)) {
            wp_send_json_error(__('شناسه دسته‌بندی نامعتبر است', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        $category = $db->get_category($category_id);
        
        if (!$category) {
            wp_send_json_error(__('دسته‌بندی یافت نشد', 'hierarchical-product-options'));
        }
        
        // Get all child categories
        $child_categories = $this->get_all_child_categories($category_id);
        
        wp_send_json_success(array(
            'category' => $category,
            'children' => $child_categories
        ));
    }
    
    /**
     * Get all child categories recursively
     * 
     * @param int $parent_id The parent category ID
     * @return array Array of child category IDs
     */
    private function get_all_child_categories($parent_id) {
        $db = new Hierarchical_Product_Options_DB();
        $all_categories = $db->get_categories();
        
        $children = array();
        
        foreach ($all_categories as $category) {
            if ($category->parent_id == $parent_id) {
                $children[] = $category->id;
                
                // Recursively get children of this child
                $grand_children = $this->get_all_child_categories($category->id);
                $children = array_merge($children, $grand_children);
            }
        }
        
        return $children;
    }
    
    /**
     * AJAX: Delete assignment
     * Updated to ensure cache is cleared and remove all child categories
     */
    public function ajax_delete_assignment() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $wc_product_id = isset($_POST['wc_product_id']) ? intval($_POST['wc_product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if (empty($wc_product_id) || empty($category_id)) {
            wp_send_json_error(__('اطلاعات نامعتبر', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        // Get all child categories
        $child_categories = $this->get_all_child_categories($category_id);
        
        // Delete assignments for all child categories
        foreach ($child_categories as $child_id) {
            $db->delete_category_product_assignment($wc_product_id, $child_id);
        }
        
        // Delete the main assignment
        $result = $db->delete_category_product_assignment($wc_product_id, $category_id);
        
        if ($result) {
            // Clear any cache
            delete_transient('hpo_category_assignments');
            
            wp_send_json_success();
        } else {
            wp_send_json_error(__('خطا در حذف تخصیص', 'hierarchical-product-options'));
        }
    }

    /**
     * AJAX callback to update assignment description
     */
    public function ajax_update_assignment_description() {
        // Verify AJAX request
        check_ajax_referer('hpo_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('شما اجازه دسترسی به این عملیات را ندارید.');
        }
        
        // Get and validate assignment_id
        $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';
        
        // Ensure assignment_id was provided
        if (!$assignment_id || !$product_id) {
            wp_send_json_error('شناسه اساین یا محصول معتبر نیست.');
        }
        
        // Make sure description is not longer than 53 characters
        if (mb_strlen($description) > 53) {
            $description = mb_substr($description, 0, 53);
        }
        
        global $wpdb;
        $table_name = $this->get_assignments_table_name();
        
        // بررسی وجود ستون short_description
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'short_description'");
        
        // اگر ستون وجود نداشت، آن را اضافه کنیم
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN `short_description` varchar(53) DEFAULT '' AFTER `wc_product_id`");
        }
        
        // بررسی اینکه آیا اساین وجود دارد یا خیر
        $assignment = $this->get_assignment($assignment_id, $product_id);
        if (!$assignment) {
            wp_send_json_error('اساین مورد نظر یافت نشد.');
        }
        
        // Update ALL assignments for this product
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name} SET short_description = %s WHERE wc_product_id = %d",
                $description,
                $product_id
            )
        );
        
        // Clear cache
        $this->clear_cache();
        
        wp_send_json_success();
    }
    
    /**
     * Get a specific assignment
     * 
     * @param int $assignment_id
     * @param int $product_id
     * @return object|null
     */
    private function get_assignment($assignment_id, $product_id) {
        global $wpdb;
        $table_name = $this->get_assignments_table_name();
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND wc_product_id = %d",
            $assignment_id,
            $product_id
        );
        
        return $wpdb->get_row($query);
    }

    /**
     * Get assignments table name
     * 
     * @return string
     */
    private function get_assignments_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'hpo_product_assignments';
    }

    /**
     * Clear cache of assignments
     */
    private function clear_cache() {
        delete_transient('hpo_category_assignments');
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
                $db->create_tables();
            }
            
            // Add the weight option
            $id = $db->add_weight($name, $coefficient, $wc_product_id);
            
            if ($id) {
                wp_send_json_success(array(
                    'id' => $id,
                    'name' => $name,
                    'coefficient' => $coefficient,
                    'wc_product_id' => $wc_product_id
                ));
            } else {
                $last_error = $wpdb->last_error;
                wp_send_json_error(__('Failed to add weight option: Database error', 'hierarchical-product-options') . ' - ' . $last_error);
            }
        } catch (Exception $e) {
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
            
            wp_send_json_success($status_report);
        } catch (Exception $e) {
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

        // Validate required fields
        if (empty($_POST['name'])) {
            wp_send_json_error(array('message' => __('Grinder name is required.', 'hierarchical-product-options')));
            return;
        }
        
        // Sanitize input
        $name = sanitize_text_field($_POST['name']);
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
        
        // Add to database
        $db = new Hierarchical_Product_Options_DB();
        
        try {
            $grinder_id = $db->add_grinder($name, $price);
            
            if ($grinder_id) {
                $grinder = $db->get_grinder($grinder_id);
                
                if ($grinder) {
                    wp_send_json_success(array(
                        'message' => __('Grinder option added successfully.', 'hierarchical-product-options'),
                        'grinder' => $grinder
                    ));
                } else {
                    wp_send_json_error(array('message' => __('Could not retrieve the newly added grinder.', 'hierarchical-product-options')));
                }
            } else {
                wp_send_json_error(array('message' => __('Failed to add grinder option.', 'hierarchical-product-options')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('An error occurred while adding the grinder option.', 'hierarchical-product-options')));
        }
        
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
        
        // Validate required fields
        if (empty($_POST['id'])) {
            wp_send_json_error(array('message' => __('Grinder ID is required.', 'hierarchical-product-options')));
            return;
        }
        
        if (empty($_POST['name'])) {
            wp_send_json_error(array('message' => __('Grinder name is required.', 'hierarchical-product-options')));
            return;
        }
        
        // Sanitize input
        $id = absint($_POST['id']);
        $name = sanitize_text_field($_POST['name']);
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
        
        // Update database
        $db = new Hierarchical_Product_Options_DB();
        
        $data = array(
            'name' => $name,
            'price' => $price
        );
        
        try {
            $result = $db->update_grinder($id, $data);
            
            if ($result !== false) {
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
                wp_send_json_error(array('message' => __('Failed to update grinder option.', 'hierarchical-product-options')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('An error occurred while updating the grinder option.', 'hierarchical-product-options')));
        }
        
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
        
        // Validate required fields
        if (empty($_POST['id'])) {
            wp_send_json_error(array('message' => __('Grinder ID is required.', 'hierarchical-product-options')));
            return;
        }
        
        // Sanitize input
        $id = absint($_POST['id']);
        
        // Delete from database
        $db = new Hierarchical_Product_Options_DB();
        
        try {
            $result = $db->delete_grinder($id);
            
            if ($result) {
                wp_send_json_success(array('message' => __('Grinder option deleted successfully.', 'hierarchical-product-options')));
            } else {
                wp_send_json_error(array('message' => __('Failed to delete grinder option.', 'hierarchical-product-options')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('An error occurred while deleting the grinder option.', 'hierarchical-product-options')));
        }
        
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
        
        // Get from database
        $db = new Hierarchical_Product_Options_DB();
        
        try {
            $grinders = $db->get_all_grinders();
            
            if ($grinders !== false) {
                wp_send_json_success($grinders);
            } else {
                wp_send_json_error(array('message' => __('Failed to retrieve grinder options.', 'hierarchical-product-options')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('An error occurred while retrieving grinder options.', 'hierarchical-product-options')));
        }
        
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
                    <?php if (!empty(trim($product->description))): ?>
                    <div class="hpo-item-description"><?php echo esc_html($product->description); ?></div>
                    <?php else: ?>
                    <div class="hpo-item-description no-description"><?php echo esc_html__('بدون توضیحات', 'hierarchical-product-options'); ?></div>
                    <?php endif; ?>
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

    /**
     * Check if database needs an upgrade and perform it
     */
    public function maybe_upgrade_database() {
        $db_version = get_option('hpo_db_version', '1.0');
        
        // If the version is less than the current version, upgrade
        if (version_compare($db_version, '1.1', '<')) {
            global $wpdb;
            $products_table = $wpdb->prefix . 'hpo_products';
            
            // Check if description column exists
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$products_table} LIKE 'description'");
            
            if (empty($column_exists)) {
                // Add description column
                $wpdb->query("ALTER TABLE {$products_table} ADD COLUMN `description` text DEFAULT '' AFTER `category_id`");
            }
            
            // Update the version
            update_option('hpo_db_version', '1.1');
        }
    }

    /**
     * AJAX: Get fresh categories list
     * Gets a completely fresh list of categories from the database
     * Updated to show only parent categories
     */
    public function ajax_get_fresh_categories() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        // Clear any cache first
        delete_transient('hpo_categories');
        
        $db = new Hierarchical_Product_Options_DB();
        
        // Clean up orphaned assignments
        global $wpdb;
        $assignments_table = $wpdb->prefix . 'hpo_product_assignments';
        $categories_table = $wpdb->prefix . 'hpo_categories';
        
        // Delete assignments where category doesn't exist
        $wpdb->query("
            DELETE FROM $assignments_table 
            WHERE category_id NOT IN (SELECT id FROM $categories_table)
        ");
        
        // Get fresh categories - ONLY PARENT CATEGORIES (parent_id = 0)
        $categories = $db->get_parent_categories_only();
        
        wp_send_json_success(array(
            'categories' => $categories
        ));
    }

    /**
     * AJAX: Clean inconsistent data
     * Removes orphaned assignments and clears all caches
     */
    public function ajax_clean_inconsistent_data() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        global $wpdb;
        $db = new Hierarchical_Product_Options_DB();
        
        // Get tables
        $assignments_table = $wpdb->prefix . 'hpo_product_assignments';
        $categories_table = $wpdb->prefix . 'hpo_categories';
        $products_table = $wpdb->prefix . 'hpo_products';
        
        // 1. Remove assignments where category doesn't exist
        $result1 = $wpdb->query("
            DELETE FROM $assignments_table 
            WHERE category_id NOT IN (SELECT id FROM $categories_table)
        ");
        
        // 2. Remove assignments where WooCommerce product doesn't exist
        $result2 = $wpdb->query("
            DELETE FROM $assignments_table 
            WHERE wc_product_id NOT IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'product' AND post_status = 'publish'
            )
        ");
        
        // 3. Remove products where category doesn't exist
        $result3 = $wpdb->query("
            DELETE FROM $products_table 
            WHERE category_id NOT IN (SELECT id FROM $categories_table)
        ");
        
        // 4. Clear all caches
        delete_transient('hpo_categories');
        delete_transient('hpo_category_assignments');
        delete_transient('hpo_products');
        wp_cache_flush();
        
        // Return results
        wp_send_json_success(array(
            'message' => sprintf(
                __('پاکسازی با موفقیت انجام شد: %d تخصیص دسته‌بندی حذف شد، %d تخصیص محصول ناسازگار حذف شد، %d محصول ناسازگار حذف شد.', 'hierarchical-product-options'),
                $result1,
                $result2,
                $result3
            )
        ));
    }

    /**
     * AJAX: Reorder category assignments
     */
    public function ajax_reorder_assignments() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $assignments = isset($_POST['assignments']) ? $_POST['assignments'] : array();
        
        if (empty($assignments) || !is_array($assignments)) {
            wp_send_json_error(__('اطلاعات نامعتبر', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        // Update each assignment's sort order
        foreach ($assignments as $index => $assignment_id) {
            $db->update_assignment_sort_order(intval($assignment_id), intval($index));
        }
        
        // Clear any cache
        delete_transient('hpo_category_assignments');
        
        wp_send_json_success();
    }

    /**
     * AJAX: Initialize sort order for existing category assignments
     * Sets sort_order for all parent category assignments
     */
    public function ajax_init_assignments_sort_order() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        global $wpdb;
        $db = new Hierarchical_Product_Options_DB();
        
        // Get all assignments and corresponding category info
        $assignments_table = $wpdb->prefix . 'hpo_product_assignments';
        $categories_table = $wpdb->prefix . 'hpo_categories';
        
        // Get unique WooCommerce product IDs that have assignments
        $product_ids = $wpdb->get_col("SELECT DISTINCT wc_product_id FROM $assignments_table");
        
        $count = 0;
        
        // For each product
        foreach ($product_ids as $product_id) {
            // Get parent category assignments for this product
            $parent_assignments = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT a.id, a.category_id FROM $assignments_table a
                    JOIN $categories_table c ON a.category_id = c.id
                    WHERE a.wc_product_id = %d AND c.parent_id = 0
                    ORDER BY a.id",
                    $product_id
                )
            );
            
            // Update sort order for each parent assignment
            foreach ($parent_assignments as $index => $assignment) {
                $result = $wpdb->update(
                    $assignments_table,
                    array('sort_order' => $index),
                    array('id' => $assignment->id)
                );
                
                if ($result !== false) {
                    $count++;
                }
            }
        }
        
        // Clear cache
        delete_transient('hpo_category_assignments');
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('%d تخصیص دسته‌بندی مرتب‌سازی شدند.', 'hierarchical-product-options'),
                $count
            )
        ));
    }

    /**
     * AJAX: Update product description
     */
    public function ajax_update_product_description() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        if (empty($product_id)) {
            wp_send_json_error(__('شناسه محصول نامعتبر است', 'hierarchical-product-options'));
        }
        
        global $wpdb;
        $db = new Hierarchical_Product_Options_DB();
        
        // جدول تخصیص‌ها
        $assignments_table = $wpdb->prefix . 'hpo_product_assignments';
        
        // دریافت تمام تخصیص‌های این محصول
        $assignments = $db->get_assignments_for_product($product_id);
        
        if (empty($assignments)) {
            wp_send_json_error(__('هیچ تخصیصی برای این محصول یافت نشد', 'hierarchical-product-options'));
        }
        
        // به‌روزرسانی توضیحات برای تمام تخصیص‌های این محصول
        $result = $wpdb->update(
            $assignments_table,
            array('short_description' => substr($description, 0, 53)),
            array('wc_product_id' => $product_id)
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('خطا در به‌روزرسانی توضیحات', 'hierarchical-product-options'));
        }
    }
    
    /**
     * AJAX: Reorder product categories
     */
    public function ajax_reorder_product_categories() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $categories = isset($_POST['categories']) ? $_POST['categories'] : array();
        
        if (empty($product_id) || empty($categories) || !is_array($categories)) {
            wp_send_json_error(__('اطلاعات نامعتبر', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        // به‌روزرسانی ترتیب هر تخصیص
        foreach ($categories as $item) {
            if (isset($item['id']) && isset($item['position'])) {
                $db->update_assignment_sort_order(intval($item['id']), intval($item['position']));
            }
        }
        
        // پاک کردن کش
        delete_transient('hpo_category_assignments');
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Delete all product assignments
     */
    public function ajax_delete_product_assignments() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (empty($product_id)) {
            wp_send_json_error(__('شناسه محصول نامعتبر است', 'hierarchical-product-options'));
        }
        
        global $wpdb;
        
        // حذف تمام تخصیص‌های این محصول
        $assignments_table = $wpdb->prefix . 'hpo_product_assignments';
        $result = $wpdb->delete($assignments_table, array('wc_product_id' => $product_id));
        
        if ($result !== false) {
            // پاک کردن کش
            delete_transient('hpo_category_assignments');
            wp_send_json_success();
        } else {
            wp_send_json_error(__('خطا در حذف تخصیص‌ها', 'hierarchical-product-options'));
        }
    }

    /**
     * AJAX: Update multiple items order at once
     */
    public function ajax_update_multiple_sort_orders() {
        check_ajax_referer('hpo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'hierarchical-product-options'));
        }
        
        $table_type = isset($_POST['table_type']) ? sanitize_text_field($_POST['table_type']) : '';
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        
        if (empty($table_type) || empty($items) || !is_array($items)) {
            wp_send_json_error(__('اطلاعات نامعتبر', 'hierarchical-product-options'));
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        $result = $db->update_multiple_sort_orders($table_type, $items);
        
        if ($result !== false) {
            wp_send_json_success(__('ترتیب آیتم‌ها با موفقیت بروزرسانی شد', 'hierarchical-product-options'));
        } else {
            wp_send_json_error(__('خطا در به‌روزرسانی ترتیب آیتم‌ها', 'hierarchical-product-options'));
        }
    }
} 