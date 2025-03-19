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
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY category_id (category_id)
        ) $charset_collate;";
        
        // Assignments table - links options to WooCommerce products
        $sql .= "CREATE TABLE $this->assignments_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wc_product_id mediumint(9) NOT NULL,
            category_id mediumint(9) NOT NULL,
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
     * @param int $sort_order Sort order
     * @return int New product ID
     */
    public function add_product($name, $price, $category_id, $sort_order = 0) {
        global $wpdb;
        
        $wpdb->insert(
            $this->products_table,
            array(
                'name' => $name,
                'price' => $price,
                'category_id' => $category_id,
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
     * Assign categories to a WooCommerce product
     *
     * @param int $wc_product_id WooCommerce product ID
     * @param array $category_ids Category IDs
     * @return bool Success
     */
    public function assign_categories_to_product($wc_product_id, $category_ids) {
        global $wpdb;
        
        // First delete existing assignments
        $wpdb->delete(
            $this->assignments_table,
            array('wc_product_id' => $wc_product_id)
        );
        
        // Add new assignments
        foreach ($category_ids as $category_id) {
            $wpdb->insert(
                $this->assignments_table,
                array(
                    'wc_product_id' => $wc_product_id,
                    'category_id' => $category_id
                )
            );
        }
        
        return true;
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
        
        // Debug log
        error_log('Inserting weight: ' . wp_json_encode([
            'name' => $name,
            'coefficient' => $coefficient,
            'wc_product_id' => $wc_product_id,
            'sort_order' => $sort_order
        ]));
        
        // Make sure the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->weights_table'") === $this->weights_table;
        if (!$table_exists) {
            error_log('Weights table does not exist, creating tables...');
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
            error_log('Failed to insert weight: ' . $wpdb->last_error);
            return 0;
        }
        
        $insert_id = $wpdb->insert_id;
        error_log('Inserted weight with ID: ' . $insert_id);
        
        return $insert_id;
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
        
        $result = $wpdb->insert(
            $this->grinders_table,
            array(
                'name' => $name,
                'price' => $price,
                'sort_order' => $sort_order
            )
        );
        
        if ($result === false) {
            error_log('Failed to insert grinder: ' . $wpdb->last_error);
            return 0;
        }
        
        return $wpdb->insert_id;
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
} 