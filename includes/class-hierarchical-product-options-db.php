<?php
/**
 * Database handling class
 *
 * @since      1.0.0
 */
class Hierarchical_Product_Options_DB {

    /**
     * Database table names
     */
    private $categories_table;
    private $products_table;
    private $assignments_table;
    private $weights_table;
    private $grinders_table;

    /**
     * Initialize the class
     */
    public function __construct() {
        global $wpdb;
        
        $this->categories_table = $wpdb->prefix . 'hpo_categories';
        $this->products_table = $wpdb->prefix . 'hpo_products';
        $this->assignments_table = $wpdb->prefix . 'hpo_product_assignments';
        $this->weights_table = $wpdb->prefix . 'hpo_weights';
        $this->grinders_table = $wpdb->prefix . 'hpo_grinders';
    }

    /**
     * Create the database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Categories table - stores category items
        $sql = "CREATE TABLE $this->categories_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            parent_id mediumint(9) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Products table - stores product items with prices
        $sql .= "CREATE TABLE $this->products_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            price decimal(10,2) NOT NULL,
            category_id mediumint(9) NOT NULL,
            description text DEFAULT '',
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY category_id (category_id)
        ) $charset_collate;";
        
        // Assignments table - links options to WooCommerce products
        $sql .= "CREATE TABLE $this->assignments_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wc_product_id mediumint(9) NOT NULL,
            category_id mediumint(9) NOT NULL,
            short_description varchar(53) DEFAULT '',
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY wc_product_id (wc_product_id)
        ) $charset_collate;";
        
        // Weights table
        $sql .= "CREATE TABLE $this->weights_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            coefficient decimal(10,4) NOT NULL DEFAULT 1,
            wc_product_id mediumint(9) NOT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY wc_product_id (wc_product_id)
        ) $charset_collate;";
        
        // Grinders table
        $sql .= "CREATE TABLE $this->grinders_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get all categories
     *
     * @return array Categories
     */
    public function get_categories() {
        global $wpdb;
        
        $sql = "SELECT * FROM $this->categories_table ORDER BY parent_id, sort_order";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get category by ID
     *
     * @param int $id Category ID
     * @return object Category
     */
    public function get_category($id) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM $this->categories_table WHERE id = %d", $id);
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Add a new category
     *
     * @param string $name Category name
     * @param int $parent_id Parent category ID (0 for top level)
     * @param int $sort_order Sort order
     * @return int New category ID
     */
    public function add_category($name, $parent_id = 0, $sort_order = 0) {
        global $wpdb;
        
        $wpdb->insert(
            $this->categories_table,
            array(
                'name' => $name,
                'parent_id' => $parent_id,
                'sort_order' => $sort_order
            )
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a category
     *
     * @param int $id Category ID
     * @param array $data Category data
     * @return bool Success
     */
    public function update_category($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $this->categories_table,
            $data,
            array('id' => $id)
        );
    }
    
    /**
     * Delete a category
     *
     * @param int $id Category ID
     * @return bool Success
     */
    public function delete_category($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->categories_table,
            array('id' => $id)
        );
    }
    
    /**
     * Get all product items
     *
     * @return array Product items
     */
    public function get_products() {
        global $wpdb;
        
        $sql = "SELECT * FROM $this->products_table ORDER BY category_id, sort_order";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get product items by category
     *
     * @param int $category_id Category ID
     * @return array Product items
     */
    public function get_products_by_category($category_id) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM $this->products_table WHERE category_id = %d ORDER BY sort_order", $category_id);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Add a new product item
     *
     * @param string $name Product name
     * @param float $price Product price
     * @param int $category_id Category ID
     * @param string $description Product description
     * @param int $sort_order Sort order
     * @return int New product ID
     */
    public function add_product($name, $price, $category_id, $description = '', $sort_order = 0) {
        global $wpdb;
        
        $wpdb->insert(
            $this->products_table,
            array(
                'name' => $name,
                'price' => $price,
                'category_id' => $category_id,
                'description' => $description,
                'sort_order' => $sort_order
            )
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a product item
     *
     * @param int $id Product ID
     * @param array $data Product data
     * @return bool Success
     */
    public function update_product($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $this->products_table,
            $data,
            array('id' => $id)
        );
    }
    
    /**
     * Delete a product item
     *
     * @param int $id Product ID
     * @return bool Success
     */
    public function delete_product($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->products_table,
            array('id' => $id)
        );
    }
    
    /**
     * Assign a product to a category
     *
     * @param int $wc_product_id WooCommerce product ID
     * @param int $category_id Category ID
     * @param string $short_description Short description
     * @return int New assignment ID
     */
    public function assign_product_to_category($wc_product_id, $category_id, $short_description = '') {
        global $wpdb;
        
        // Check if this assignment already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $this->assignments_table WHERE wc_product_id = %d AND category_id = %d",
                $wc_product_id,
                $category_id
            )
        );
        
        if ($existing) {
            // Update short description if provided
            if (!empty($short_description)) {
                $wpdb->update(
                    $this->assignments_table,
                    array('short_description' => substr($short_description, 0, 53)),
                    array('id' => $existing)
                );
            }
            return $existing;
        }
        
        // Get the highest sort_order for this product's categories
        $max_sort_order = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(sort_order) FROM $this->assignments_table WHERE wc_product_id = %d AND category_id IN (SELECT id FROM $this->categories_table WHERE parent_id = 0)",
                $wc_product_id
            )
        );
        
        // If no existing assignments or sort_order is null, start at 0
        $next_sort_order = (is_null($max_sort_order)) ? 0 : intval($max_sort_order) + 1;
        
        // Only set sort_order for parent categories (parent_id = 0)
        $is_parent = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $this->categories_table WHERE id = %d AND parent_id = 0",
                $category_id
            )
        );
        
        $sort_order = ($is_parent > 0) ? $next_sort_order : 0;
        
        $wpdb->insert(
            $this->assignments_table,
            array(
                'wc_product_id' => $wc_product_id,
                'category_id' => $category_id,
                'short_description' => substr($short_description, 0, 53),
                'sort_order' => $sort_order
            )
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get all child categories recursively for a parent category
     *
     * @param int $parent_id Parent category ID
     * @return array Child categories
     */
    public function get_child_categories($parent_id) {
        global $wpdb;
        
        $children = array();
        
        // Get direct children
        $sql = $wpdb->prepare(
            "SELECT * FROM $this->categories_table WHERE parent_id = %d",
            $parent_id
        );
        
        $direct_children = $wpdb->get_results($sql);
        
        foreach ($direct_children as $child) {
            $children[] = $child;
            // Get grandchildren recursively
            $grandchildren = $this->get_child_categories($child->id);
            if (!empty($grandchildren)) {
                $children = array_merge($children, $grandchildren);
            }
        }
        
        return $children;
    }
    
    /**
     * Get all parent categories for a given category
     *
     * @param int $category_id Category ID
     * @return array Parent categories
     */
    public function get_parent_categories($category_id) {
        global $wpdb;
        
        $parents = array();
        $current_category = $this->get_category($category_id);
        
        // Traverse up the hierarchy
        while ($current_category && $current_category->parent_id > 0) {
            $current_category = $this->get_category($current_category->parent_id);
            if ($current_category) {
                $parents[] = $current_category;
            }
        }
        
        return $parents;
    }
    
    /**
     * Get all category-product assignments with category and product names
     * 
     * @return array List of assignments with category and product info
     */
    public function get_category_product_assignments() {
        global $wpdb;
        
        $sql = "SELECT a.*, c.name as category_name, c.parent_id 
                FROM $this->assignments_table a
                JOIN $this->categories_table c ON a.category_id = c.id
                ORDER BY a.wc_product_id, c.parent_id, a.sort_order";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get product-category assignment by ID
     *
     * @param int $id Assignment ID
     * @return object Assignment
     */
    public function get_assignment($id) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM $this->assignments_table WHERE id = %d", $id);
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Update product-category assignment description
     *
     * @param int $id Assignment ID
     * @param string $short_description Short description (max 53 chars)
     * @return bool Success
     */
    public function update_assignment_description($id, $short_description) {
        global $wpdb;
        
        return $wpdb->update(
            $this->assignments_table,
            array('short_description' => substr($short_description, 0, 53)),
            array('id' => $id)
        );
    }
    
    /**
     * Get product-category assignments for a specific WooCommerce product
     *
     * @param int $wc_product_id WooCommerce product ID
     * @return array Assignments
     */
    public function get_assignments_for_product($wc_product_id) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM $this->assignments_table WHERE wc_product_id = %d", $wc_product_id);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get options for a WooCommerce product
     *
     * @param int $wc_product_id WooCommerce product ID
     * @return array Options structure
     */
    public function get_options_for_product($wc_product_id) {
        global $wpdb;
        
        // Get assigned categories
        $sql = $wpdb->prepare(
            "SELECT c.* FROM $this->categories_table c
             JOIN $this->assignments_table a ON c.id = a.category_id
             WHERE a.wc_product_id = %d
             ORDER BY c.parent_id, c.sort_order",
            $wc_product_id
        );
        
        $categories = $wpdb->get_results($sql);
        
        // Build hierarchical structure
        $options = array();
        $category_map = array();
        
        // First pass: create category map
        foreach ($categories as $category) {
            $category_map[$category->id] = $category;
            $category->children = array();
            $category->products = array();
            
            if ($category->parent_id == 0) {
                $options[] = $category;
            }
        }
        
        // Second pass: build hierarchy
        foreach ($categories as $category) {
            if ($category->parent_id > 0 && isset($category_map[$category->parent_id])) {
                $category_map[$category->parent_id]->children[] = $category;
            }
        }
        
        // Get products for each category
        foreach ($category_map as $category_id => $category) {
            $products = $this->get_products_by_category($category_id);
            $category->products = $products;
        }
        
        return $options;
    }

    /**
     * Get product by ID
     *
     * @param int $id Product ID
     * @return object Product
     */
    public function get_product($id) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM $this->products_table WHERE id = %d", $id);
        
        return $wpdb->get_row($sql);
    }

    /**
     * Get weights for a WooCommerce product
     *
     * @param int $wc_product_id WooCommerce product ID
     * @return array Weight options
     */
    public function get_weights_for_product($wc_product_id) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM $this->weights_table WHERE wc_product_id = %d ORDER BY sort_order", $wc_product_id);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Add a new weight option
     *
     * @param string $name Weight name/label
     * @param float $coefficient Price coefficient
     * @param int $wc_product_id WooCommerce product ID
     * @param int $sort_order Sort order
     * @return int New weight ID
     */
    public function add_weight($name, $coefficient, $wc_product_id, $sort_order = 0) {
        global $wpdb;
        
        // Make sure the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->weights_table'") === $this->weights_table;
        if (!$table_exists) {
            $this->create_tables();
        }
        
        $result = $wpdb->insert(
            $this->weights_table,
            array(
                'name' => $name,
                'coefficient' => $coefficient,
                'wc_product_id' => $wc_product_id,
                'sort_order' => $sort_order
            )
        );
        
        if ($result === false) {
            return 0;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a weight option
     *
     * @param int $id Weight ID
     * @param array $data Weight data
     * @return bool Success
     */
    public function update_weight($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $this->weights_table,
            $data,
            array('id' => $id)
        );
    }
    
    /**
     * Delete a weight option
     *
     * @param int $id Weight ID
     * @return bool Success
     */
    public function delete_weight($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->weights_table,
            array('id' => $id)
        );
    }
    
    /**
     * Get a specific weight option
     *
     * @param int $id Weight ID
     * @return object Weight option
     */
    public function get_weight($id) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM $this->weights_table WHERE id = %d", $id);
        
        return $wpdb->get_row($sql);
    }

    /**
     * Get all grinder options
     *
     * @return array Grinder options
     */
    public function get_all_grinders() {
        global $wpdb;
        
        $sql = "SELECT * FROM $this->grinders_table ORDER BY sort_order";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get grinder by ID
     *
     * @param int $id Grinder ID
     * @return object Grinder
     */
    public function get_grinder($id) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT * FROM $this->grinders_table WHERE id = %d", $id);
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Add a new grinder option
     *
     * @param string $name Grinder name
     * @param float $price Grinder price
     * @param int $sort_order Sort order
     * @return int New grinder ID
     */
    public function add_grinder($name, $price, $sort_order = 0) {
        global $wpdb;
        
        // Debug logging
        error_log('DB add_grinder called with: Name=' . $name . ', Price=' . $price);
        
        // Make sure the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->grinders_table'") === $this->grinders_table;
        error_log('Grinders table exists check: ' . ($table_exists ? 'Yes' : 'No'));
        
        if (!$table_exists) {
            error_log('Grinders table does not exist, creating tables...');
            $this->create_tables();
            
            // Check again after creation
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->grinders_table'") === $this->grinders_table;
            error_log('Grinders table exists after creation: ' . ($table_exists ? 'Yes' : 'No'));
            
            if (!$table_exists) {
                error_log('Failed to create grinders table');
                return 0;
            }
        }
        
        // Debug - show the table name being used
        error_log('Grinders table name: ' . $this->grinders_table);
        
        $data = array(
            'name' => $name,
            'price' => $price,
            'sort_order' => $sort_order
        );
        
        error_log('Attempting to insert grinder with data: ' . wp_json_encode($data));
        
        $result = $wpdb->insert(
            $this->grinders_table,
            $data
        );
        
        if ($result === false) {
            error_log('Failed to insert grinder: ' . $wpdb->last_error);
            return 0;
        }
        
        $insert_id = $wpdb->insert_id;
        error_log('Successfully inserted grinder with ID: ' . $insert_id);
        
        return $insert_id;
    }
    
    /**
     * Update a grinder option
     *
     * @param int $id Grinder ID
     * @param array $data Grinder data
     * @return bool Success
     */
    public function update_grinder($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $this->grinders_table,
            $data,
            array('id' => $id)
        );
    }
    
    /**
     * Delete a grinder option
     *
     * @param int $id Grinder ID
     * @return bool Success
     */
    public function delete_grinder($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->grinders_table,
            array('id' => $id)
        );
    }

    /**
     * Get all categories assigned to a WooCommerce product
     *
     * @param int $wc_product_id WooCommerce product ID
     * @return array All categories (both parent and child)
     */
    public function get_categories_for_product($wc_product_id) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT c.* FROM $this->categories_table c
             JOIN $this->assignments_table a ON c.id = a.category_id
             WHERE a.wc_product_id = %d
             ORDER BY c.parent_id, c.sort_order",
            $wc_product_id
        );
        
        return $wpdb->get_results($sql);
    }

    /**
     * Delete product assignments by category
     *
     * @param int $category_id Category ID
     * @return bool Success
     */
    public function delete_product_assignments_by_category($category_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->assignments_table,
            array('category_id' => $category_id)
        );
    }

    /**
     * Get a specific category-product assignment
     *
     * @param int $wc_product_id WooCommerce product ID
     * @param int $category_id Category ID
     * @return object Assignment or null if not found
     */
    public function get_category_product_assignment($wc_product_id, $category_id) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $this->assignments_table WHERE wc_product_id = %d AND category_id = %d",
            $wc_product_id,
            $category_id
        );
        
        return $wpdb->get_row($sql);
    }

    /**
     * Delete a specific category-product assignment
     *
     * @param int $wc_product_id WooCommerce product ID
     * @param int $category_id Category ID
     * @return bool Success
     */
    public function delete_category_product_assignment($wc_product_id, $category_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->assignments_table,
            array(
                'wc_product_id' => $wc_product_id,
                'category_id' => $category_id
            )
        );
    }

    /**
     * Get only parent categories (parent_id = 0)
     *
     * @return array Parent categories
     */
    public function get_parent_categories_only() {
        global $wpdb;
        
        $sql = "SELECT * FROM $this->categories_table WHERE parent_id = 0 ORDER BY sort_order";
        
        return $wpdb->get_results($sql);
    }

    /**
     * Update assignment sort order
     *
     * @param int $id Assignment ID
     * @param int $sort_order New sort order
     * @return bool Success
     */
    public function update_assignment_sort_order($id, $sort_order) {
        global $wpdb;
        
        return $wpdb->update(
            $this->assignments_table,
            array('sort_order' => $sort_order),
            array('id' => $id)
        );
    }
    
    /**
     * Get parent category assignments for a product
     * Only returns assignments for parent categories (parent_id = 0)
     *
     * @param int $wc_product_id WooCommerce product ID
     * @return array Parent category assignments
     */
    public function get_parent_assignments_for_product($wc_product_id) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT a.*, c.name as category_name 
            FROM $this->assignments_table a
            JOIN $this->categories_table c ON a.category_id = c.id
            WHERE a.wc_product_id = %d 
            AND c.parent_id = 0
            ORDER BY a.sort_order",
            $wc_product_id
        );
        
        return $wpdb->get_results($sql);
    }
} 