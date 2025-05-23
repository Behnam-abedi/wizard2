<?php
/**
 * Shortcodes for Hierarchical Product Options
 *
 * @since      1.0.0
 */
class HPO_Shortcodes {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Add shortcodes
        add_shortcode('hpo_order_button', array($this, 'order_button_shortcode'));
        
        // Register AJAX actions
        add_action('wp_ajax_hpo_load_products', array($this, 'ajax_load_products'));
        add_action('wp_ajax_nopriv_hpo_load_products', array($this, 'ajax_load_products'));
        add_action('wp_ajax_hpo_load_product_details', array($this, 'ajax_load_product_details'));
        add_action('wp_ajax_nopriv_hpo_load_product_details', array($this, 'ajax_load_product_details'));
        add_action('wp_ajax_hpo_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_hpo_add_to_cart', array($this, 'ajax_add_to_cart'));
        
        // Add cart hooks
        add_filter('woocommerce_get_item_data', array($this, 'add_cart_item_custom_data'), 100, 2);
        add_filter('woocommerce_cart_item_price', array($this, 'update_cart_item_price'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'update_cart_item_subtotal'), 999, 3);
        add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'update_checkout_item_quantity'), 999, 3);
        add_filter('woocommerce_before_calculate_totals', array($this, 'calculate_cart_item_prices'), 10, 1);
        
        // Add for price display
        add_filter('woocommerce_get_price_html', array($this, 'format_price_html'), 100, 2);
        
        // Prevent cart item merging
        add_filter('woocommerce_add_cart_item_data', array($this, 'prevent_cart_merging'), 10, 2);
        add_filter('woocommerce_add_cart_item', array($this, 'setup_cart_item'), 10, 1);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        
        // Cart item display
        add_filter('woocommerce_cart_item_name', array($this, 'modify_cart_item_name'), 10, 3);
        add_filter('woocommerce_display_item_meta', array($this, 'remove_grinding_metadata'), 10, 3);
        
        // Cart item quantity display
        add_filter('woocommerce_widget_cart_item_quantity', array($this, 'modify_mini_cart_quantity'), 10, 3);
        add_filter('woocommerce_cart_item_quantity', array($this, 'modify_cart_item_quantity'), 10, 3);
        add_filter('woocommerce_cart_item_quantity_display', array($this, 'modify_cart_item_quantity_display'), 10, 3);
        
        // Filter product prices
        add_filter('woocommerce_product_get_price', array($this, 'filter_product_price'), 99, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'filter_product_price'), 99, 2);
        
        // Order review hooks - Very Important
        add_filter('woocommerce_order_formatted_line_subtotal', array($this, 'update_checkout_line_subtotal'), 999, 3);
        add_filter('woocommerce_cart_subtotal', array($this, 'update_cart_subtotal'), 999, 3);
        add_filter('woocommerce_order_subtotal_to_display', array($this, 'update_order_subtotal'), 999, 3);
        add_filter('woocommerce_calculated_total', array($this, 'update_calculated_total'), 999, 2);
        
        // Order item meta data
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_meta'), 10, 4);
        
        // Add a hook to ensure our price calculation runs even when cached
        add_action('woocommerce_cart_loaded_from_session', array($this, 'fix_cart_after_session_load'), 30);
        
        // Handle cart totals calculation
        add_action('woocommerce_after_calculate_totals', array($this, 'after_calculate_totals'), 20);
        
        // Fix coupon display in cart totals
        add_filter('woocommerce_coupon_get_discount_amount', array($this, 'fix_coupon_discount_amount'), 10, 5);
        
        // Fix the display of the coupon discount amount in cart
        add_filter('woocommerce_cart_totals_coupon_html', array($this, 'fix_coupon_display_in_cart'), 10, 3);
        
        // Make sure cart fragments get the correct prices
        add_filter('woocommerce_cart_fragment_name', function($name) {
            return $name . '_hpo_custom';
        });
        
        // Add checkout-specific hook for correct totals in checkout
        add_action('woocommerce_review_order_after_cart_contents', function() {
            if (WC()->cart) {
                $this->after_calculate_totals(WC()->cart);
            }
        }, 999);
        
        // Add CSS/JS to pages
        add_action('wp_footer', array($this, 'add_custom_cart_css'));
        
        // Ensure scripts are loaded
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'hpo-shortcode-styles',
            HPO_PLUGIN_URL . 'public/css/shortcodes.css',
            array(),
            HPO_VERSION
        );
        
        wp_enqueue_script(
            'hpo-shortcode-scripts',
            HPO_PLUGIN_URL . 'public/js/shortcodes.js',
            array('jquery'),
            HPO_VERSION,
            true
        );
        wp_enqueue_script(
            'hpo-scroll-scripts',
            HPO_PLUGIN_URL . 'public/js/scroll.js',
            HPO_VERSION,
            true
        );
        wp_localize_script(
            'hpo-shortcode-scripts',
            'hpoAjax',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hpo_ajax_nonce')
            )
        );
    }
    
    /**
     * Order button shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function order_button_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'button_text' => 'ثبت سفارش',
                'button_class' => 'hpo-order-button',
            ),
            $atts,
            'hpo_order_button'
        );
        
        ob_start();
        ?>
        <div class="hpo-order-button-container">
            <button class="<?php echo esc_attr($atts['button_class']); ?>" id="hpo-order-button">
                <?php echo esc_html($atts['button_text']); ?>
                <i class="hpo-cart-icon-button"></i>
            </button>
        </div>
        
        <div class="hpo-popup-overlay" id="hpo-popup-overlay" style="display: none;">
        
            <div class="hpo-popup-container">
                <div class="hpo-popup-header">
                <div class="hpo-back-button" id="hpo-popup-close">
                    <span></span>
                </div>

                    
                    <div class="hpo-header-price">
                        <i class="hpo-cart-icon"></i>
                        <span class="hpo-total-value" id="hpo-total-price">0 تومان</span>
                    </div>
                </div>
                <div class="hpo-popup-content">
                    <div class="hpo-product-list" id="hpo-product-list">
                        <span class="hpo-product-list-title">محصولات</span>
                        <!-- Products will be loaded here via AJAX -->
                        <div class="hpo-loading">در حال بارگذاری...</div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for loading products
     */
    public function ajax_load_products() {
        check_ajax_referer('hpo_ajax_nonce', 'nonce');
        
        global $wpdb;
        $db = new Hierarchical_Product_Options_DB();
        
        // Get all products that have hierarchical options
        $assignments = $db->get_category_product_assignments();
        $product_ids = array();
        $product_descriptions = array();
        
        foreach ($assignments as $assignment) {
            if (!in_array($assignment->wc_product_id, $product_ids)) {
                $product_ids[] = $assignment->wc_product_id;
                // Store the description if available
                if (!empty($assignment->short_description)) {
                    $product_descriptions[$assignment->wc_product_id] = $assignment->short_description;
                }
            }
        }
        
        ob_start();
        
        if (empty($product_ids)) {
            echo '<p>هیچ محصولی با گزینه‌های سلسله مراتبی یافت نشد.</p>';
        } else {
            // اضافه کردن متن راهنما با استایل خاص در بالای صفحه محصولات
            echo '<div class="hpo-product-intro-message">
<p>یک دسته قهوه را انتخاب کنید و برای دیدن گزینه‌های بیشتر، «مرحله بعد» را بزنید.</p>            </div>';
            
            echo '<div class="hpo-products-grid">';
            
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                
                if ($product) {
                    ?>
                    <div class="hpo-product-item" data-product-id="<?php echo esc_attr($product_id); ?>">
                        <div class="hpo-product-info">
                            <h4><?php echo esc_html($product->get_name()); ?></h4>
                            <?php
                            // Use our custom short description if available, otherwise use product short description
                            if (isset($product_descriptions[$product_id])) {
                                echo '<div class="hpo-product-description">' . esc_html($product_descriptions[$product_id]) . '</div>';
                            } else {
                                $description = $product->get_short_description();
                                if (!empty($description)) {
                                    echo '<div class="hpo-product-description">' . wp_kses_post($description) . '</div>';
                                } else {
                                    echo '<div class="hpo-product-description">بدون توضیحات</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <?php
                }
            }
            
            echo '</div>';
        }
        
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX handler for loading product details
     */
    public function ajax_load_product_details() {
        check_ajax_referer('hpo_ajax_nonce', 'nonce');
        
        if (empty($_POST['product_id'])) {
            wp_send_json_error(array('message' => 'شناسه محصول نامعتبر است.'));
            return;
        }
        
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => 'محصول یافت نشد.'));
            return;
        }
        
        $db = new Hierarchical_Product_Options_DB();
        
        // Get all categories for this product (both parent and child)
        $categories = $db->get_categories_for_product($product_id);
        
        // Organize categories into hierarchical structure
        $parent_categories = array();
        $child_categories = array();
        $category_paths = array(); // Store category paths for display
        
        // Get the product name for the category paths
        $product_name = $product->get_name();
        
        foreach ($categories as $category) {
            if ($category->parent_id == 0) {
                $parent_categories[] = $category;
                // For parent categories, add product name before category name
                $category_paths[$category->id] = $product_name . ' > ' . $category->name;
            } else {
                $child_categories[$category->parent_id][] = $category;
                
                // Get all parent categories to build the full path
                $parents = $db->get_parent_categories($category->id);
                
                // Build path from product name to deepest parent to current category
                $parent_names = array($product_name); // Start with product name
                
                if (!empty($parents)) {
                    foreach (array_reverse($parents) as $parent) {
                        $parent_names[] = $parent->name;
                    }
                }
                $parent_names[] = $category->name;
                $path = implode(' > ', $parent_names);
                
                $category_paths[$category->id] = $path;
            }
        }
        
        // Get parent category assignments ordered by sort_order
        $parent_assignments = $db->get_parent_assignments_for_product($product_id);
        
        // Reorder parent_categories based on parent_assignments sort_order
        if (!empty($parent_assignments)) {
            $ordered_parent_categories = array();
            $category_map = array();
            
            // First create a map of categories by id
            foreach ($parent_categories as $category) {
                $category_map[$category->id] = $category;
            }
            
            // Then create a new ordered array based on assignments
            foreach ($parent_assignments as $assignment) {
                if (isset($category_map[$assignment->category_id])) {
                    $ordered_parent_categories[] = $category_map[$assignment->category_id];
                }
            }
            
            // Replace the parent_categories array if we have ordered categories
            if (!empty($ordered_parent_categories)) {
                $parent_categories = $ordered_parent_categories;
            }
        }
        
        // Get weight options
        $weights = $db->get_weights_for_product($product_id);
        
        ob_start();
        ?>
        <div class="hpo-product-details">

            
            <form class="hpo-product-options-form">
                <span class="header-title"><?php echo esc_attr($product_name) ?></span>
                <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                <input type="hidden" name="hpo_base_price" value="<?php echo esc_attr($product->get_price()); ?>">
                <div class="description-title">
                    <span>در لیست زیر می‌توانید محصول موردنظر خود را از دسته قهوه‌های <?php echo esc_attr($product_name) ?> انتخاب کرده و سپس وزن و وضعیت آسیاب موردنظر را برای ثبت سفارش تعیین کنید.</span>
                </div>
                <?php if (!empty($parent_categories)): ?>
                <div class="hpo-option-section">
                    <!-- <h3>گزینه‌های محصول</h3> -->
                    <div class="hpo-options-list">
                        <?php foreach ($parent_categories as $parent): ?>
                        <div class="hpo-category">
                            <h4><?php echo esc_html($parent->name); ?></h4>
                            <div class="hpo-products">
                                <?php 
                                // Get products for parent category
                                $parent_products = $db->get_products_by_category_with_assignment_order($parent->id, $product_id);
                                foreach ($parent_products as $opt_product): 
                                ?>
                                <div class="hpo-product-option new-style-view">
                                    <div class="hpo-product-option-header" style="grid-area:item1">
                                        <?php echo esc_html($category_paths[$parent->id]); ?>

                                    </div>
                                    <div class="hpo-product-image" style="position: relative;grid-area:item2">
                                        <?php if (!empty($opt_product->image_url)) : ?>
                                            <div class="hpo-loader-container">
                                                <div class="hpo-loader"></div>
                                            </div>
                                            <img src="<?php echo esc_html($opt_product->image_url); ?>" alt=""
                                                onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                                                style="opacity: 0; transition: opacity 0.5s ease;">
                                        <?php else : ?>
                                            <div class="hpo-product-no-image">
                                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAAEw0lEQVR4nO1aW4gcRRTt+ECNLxAF3+9XjCg+kigq0Q/Rj4gKSoJfvj78WJGNGFEQRY2K+OOPkA9/jJgQ4ogYHyCCiBsj8YGgSAZ1obfrnOqZccHdZKPZjS03e9vtLD3VXT29s/uxFwp2Zqpv3XPvrVvnVm8QLMqiLBwBcAWAjSS/JtkBMAWAAHaSfMEYc3mwkCWO44tIfgDgX5JJtwHgIMntJC8IFppYax8luc8FIAfQGMmHgoUiAF71AZAzXkqSZMl8g1jvMPAfkiuNMTeViM7GeQNBcp3mezfj3tR5y0tG5sG+gxgZGTlbc7wbiAPtdvtMBbK9JJB9cRxf3FcgJBsFRn2SKcXOKjZrfNW3/UJyTdk0qVIIjDH39gUIgKEiY1qt1iU6d5cvEADfzDmIOI6vLmNMGIbHyXySf5Y0fhLAhyTXGmOWzjkQkptKpschY0hOFACYIrk5jaBIkiRHk1ylpb0BoJk5bCdI/gpgh7X2iUrsIEmSI8p62BhzrjwDwDhADAFYluoHcB6AV0i2PNLwIID3so4oFGvtVR65vkoj8mXOb/tJPpUkyZEpAJLbNDpe+4kzgMZKFwmSA2UVS9jVyNdmLWgBrMik0AYAe6sC4OG6pcyvKwQCYKuH4oaCX5n5bo+19kL5Poqic0j+UAcAHj4mCtOM5PceCveHYXiKOuAn3aCnyWdjzLWuvVNDZD4qikjsqXC97q3bZR+ojjsBjNdo9GqSt85OMWfzVlRKcxZhHMcnZByx2ldHgf4x0Str5Pz2nCsi3lUlrSTaPY7WnEJD6uArc3773BURL0MAfCslVqvTj3WC4PQYUAcP5qwNV0T+8AAxJeeOOmDDHIDY02w2jxFHkfwlZ/1JOcC7AfEhgJvlGTnh6zonOGPkX9KsqZMe6TLnQM88S6IRRdFl+sy2miMxIsQ10+t0q4BtV2o9VhLIjgx3qkw7ck7td4wxp6ruZSRDx/xmHVzr/jx6UnEI690k3g9m7Li7BHn9uCsQNe63As+NyyaUIeGt6H1qGq9J+xpN0/MBbCmp5+kiIK8XKPhM5hljbqlg/BtydZStNvK3MeZm4XkAJsvqs9be6AQim9h1BUTyGQ3/syUXlRQZkAima0hTZoy5B8DbvrSIyrDl7HIC0RC/71B0l875tMSCu1qt1hnq+SXyrLa7vdKYlwtBaHpd1y0q6aYE8HvBYo3h4eFjM2m4u0fjE11/UlqEUkDU42/lKUqpu4vOAPiZ5PEKeNAn91kMZEvgI6OjoyeTjGYr6nQ6J0mauM4PANcr2IfrAkBlw3IDGviKtfYG7b//VyYAhf84Uu8QK43j+PQ6KT2ndQ96g8iAeSBrdNrQdKs2wg40Gi/WCYLk7iRJjqoMRI1aS/JvBXKHAvkib0Fpc/X3nTWC6MhhGdQhUnkADIun1dDn8xZNK4qQv5pATMiBWQuIDJilAO5TQ5fn3cK32+0TFehYDXtiXO4AgrkWIW45BtymRaLXSERRFF0T9EOiKLq07srE6fFdGIZnBfPwes7nRY8rlfYCeLLn6lRVpH/Q9rQqAHnlsDW9GJ9X0eugd306RmGxQgC9uFO/RN5nkHxc/kNCrlGl+dIDtaWfG8q/VsxbCi3KogSl5T905j+mDjkasAAAAABJRU5ErkJggg==" alt="coffee-beans---v2">                                             </div>
                                        <?php endif; ?>
                                    </div>



                                    <label style="grid-area:item3">
                                        <div class="hpo-product-option-name-price">
                                            <input type="radio" name="hpo_option[<?php echo esc_attr($parent->id); ?>]" 
                                                   value="<?php echo esc_attr($opt_product->id); ?>" 
                                                   data-price="<?php echo esc_attr($opt_product->price); ?>">
                                            <span class="hpo-option-name"><?php echo esc_html($opt_product->name); ?></span>
                                        </div>
                                        <span class="hpo-option-price"><?php echo number_format($opt_product->price); ?> تومان</span>
                                    </label>
                                    <div class="hpo-product-option-description" style="grid-area:item4">
                                        <?php echo !empty($opt_product->description) ? esc_html($opt_product->description) : '<span class="no-description">بدون توضیحات</span>'; ?>
                                    </div>

                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php 
                            // Display child categories for this parent
                            if (isset($child_categories[$parent->id]) && !empty($child_categories[$parent->id])): 
                            ?>
                            <div class="hpo-child-categories">
                                <?php foreach ($child_categories[$parent->id] as $child): ?>
                                <div class="hpo-child-category">
                                    <h5><?php echo esc_html($child->name); ?></h5>
                                    <div class="hpo-products">
                                        <?php 
                                        // Get products for child category
                                        $child_products = $db->get_products_by_category_with_assignment_order($child->id, $product_id);
                                        foreach ($child_products as $child_product): 
                                        ?>
                                        <div class="hpo-product-option new-style-view">
                                            <div class="hpo-product-option-header" style="grid-area:item1">
                                                <?php echo esc_html($category_paths[$child->id]); ?>
                                            </div>
                                            <div class="hpo-product-image" style="position: relative;grid-area:item2">
                                             <?php if (!empty($child_product->image_url)) : ?>
                                            <div class="hpo-loader-container">
                                                <div class="hpo-loader"></div>
                                            </div>
                                            <img src="<?php echo esc_html($child_product->image_url); ?>" alt=""
                                                onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                                                style="opacity: 0; transition: opacity 0.5s ease;">
                                        <?php else : ?>
                                            <div class="hpo-product-no-image">
                                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAAEw0lEQVR4nO1aW4gcRRTt+ECNLxAF3+9XjCg+kigq0Q/Rj4gKSoJfvj78WJGNGFEQRY2K+OOPkA9/jJgQ4ogYHyCCiBsj8YGgSAZ1obfrnOqZccHdZKPZjS03e9vtLD3VXT29s/uxFwp2Zqpv3XPvrVvnVm8QLMqiLBwBcAWAjSS/JtkBMAWAAHaSfMEYc3mwkCWO44tIfgDgX5JJtwHgIMntJC8IFppYax8luc8FIAfQGMmHgoUiAF71AZAzXkqSZMl8g1jvMPAfkiuNMTeViM7GeQNBcp3mezfj3tR5y0tG5sG+gxgZGTlbc7wbiAPtdvtMBbK9JJB9cRxf3FcgJBsFRn2SKcXOKjZrfNW3/UJyTdk0qVIIjDH39gUIgKEiY1qt1iU6d5cvEADfzDmIOI6vLmNMGIbHyXySf5Y0fhLAhyTXGmOWzjkQkptKpschY0hOFACYIrk5jaBIkiRHk1ylpb0BoJk5bCdI/gpgh7X2iUrsIEmSI8p62BhzrjwDwDhADAFYluoHcB6AV0i2PNLwIID3so4oFGvtVR65vkoj8mXOb/tJPpUkyZEpAJLbNDpe+4kzgMZKFwmSA2UVS9jVyNdmLWgBrMik0AYAe6sC4OG6pcyvKwQCYKuH4oaCX5n5bo+19kL5Poqic0j+UAcAHj4mCtOM5PceCveHYXiKOuAn3aCnyWdjzLWuvVNDZD4qikjsqXC97q3bZR+ojjsBjNdo9GqSt85OMWfzVlRKcxZhHMcnZByx2ldHgf4x0Str5Pz2nCsi3lUlrSTaPY7WnEJD6uArc3773BURL0MAfCslVqvTj3WC4PQYUAcP5qwNV0T+8AAxJeeOOmDDHIDY02w2jxFHkfwlZ/1JOcC7AfEhgJvlGTnh6zonOGPkX9KsqZMe6TLnQM88S6IRRdFl+sy2miMxIsQ10+t0q4BtV2o9VhLIjgx3qkw7ck7td4wxp6ruZSRDx/xmHVzr/jx6UnEI690k3g9m7Li7BHn9uCsQNe63As+NyyaUIeGt6H1qGq9J+xpN0/MBbCmp5+kiIK8XKPhM5hljbqlg/BtydZStNvK3MeZm4XkAJsvqs9be6AQim9h1BUTyGQ3/syUXlRQZkAima0hTZoy5B8DbvrSIyrDl7HIC0RC/71B0l875tMSCu1qt1hnq+SXyrLa7vdKYlwtBaHpd1y0q6aYE8HvBYo3h4eFjM2m4u0fjE11/UlqEUkDU42/lKUqpu4vOAPiZ5PEKeNAn91kMZEvgI6OjoyeTjGYr6nQ6J0mauM4PANcr2IfrAkBlw3IDGviKtfYG7b//VyYAhf84Uu8QK43j+PQ6KT2ndQ96g8iAeSBrdNrQdKs2wg40Gi/WCYLk7iRJjqoMRI1aS/JvBXKHAvkib0Fpc/X3nTWC6MhhGdQhUnkADIun1dDn8xZNK4qQv5pATMiBWQuIDJilAO5TQ5fn3cK32+0TFehYDXtiXO4AgrkWIW45BtymRaLXSERRFF0T9EOiKLq07srE6fFdGIZnBfPwes7nRY8rlfYCeLLn6lRVpH/Q9rQqAHnlsDW9GJ9X0eugd306RmGxQgC9uFO/RN5nkHxc/kNCrlGl+dIDtaWfG8q/VsxbCi3KogSl5T905j+mDjkasAAAAABJRU5ErkJggg==" alt="coffee-beans---v2"> 
                                             
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                            <label style="grid-area:item3">
                                                <div class="hpo-product-option-name-price">
                                                    <input type="radio" name="hpo_option[<?php echo esc_attr($child->id); ?>]" 
                                                           value="<?php echo esc_attr($child_product->id); ?>" 
                                                           data-price="<?php echo esc_attr($child_product->price); ?>">
                                                    <span class="hpo-option-name"><?php echo esc_html($child_product->name); ?></span>
                                                </div>
                                                <span class="hpo-option-price"><?php echo number_format($child_product->price); ?> تومان</span>
                                            </label>
                                            <div class="hpo-product-option-description" style="grid-area:item4">
                                                <?php echo !empty($child_product->description) ? esc_html($child_product->description) : '<span class="no-description">بدون توضیحات</span>'; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="hpo-weight-section" id="weight-section">
                    <h3>انتخاب وزن محصول</h3>
                    <span class="description-weight-grinding">از بین یکی از گزینه های زیر وزن سفارش مورد نظر خود را انتخاب کنید</span>
                    <div class="hpo-weight-grid">
                        <?php foreach ($weights as $weight): ?>
                        <div class="hpo-weight-item">
                            <label>
                                <input type="radio" name="hpo_weight" 
                                       value="<?php echo esc_attr($weight->id); ?>" 
                                       data-coefficient="<?php echo esc_attr($weight->coefficient); ?>">
                                <span class="hpo-weight-text"><?php echo esc_html($weight->name); ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="hpo-option-section hpo-grinding-options-section">
                    <h3>وضعیت آسیاب</h3>
                    <span class="description-weight-grinding">در صورتی که مایل به آسیاب شدن محصول مورد نظر هستید دستگاه اسپرسو ساز خود را برای ما مشخص کنید</span>

                    <div class="hpo-grinding-options">
                        <div class="hpo-grinding-toggle">
                            <div class="hpo-toggle-container" data-active="whole">
                                <div class="hpo-toggle-slider"></div>
                                <div class="hpo-toggle-option whole active" data-value="whole">بدون آسیاب</div>
                                <div class="hpo-toggle-option ground" data-value="ground">آسیاب</div>
                            </div>
                            <input type="hidden" name="hpo_grinding" value="whole">
                        </div>
                        
                        <div class="hpo-grinding-machines">
                            <label for="hpo-grinding-machine">دستگاه خود را از لیست زیر انتخاب کنید</label>
                            <select name="hpo_grinding_machine" id="hpo-grinding-machine">
                                <option value="">دستگاه خود را انتخاب کنید</option>
                                <?php 
                                $grinders = $db->get_all_grinders();
                                foreach ($grinders as $grinder): 
                                    // Ensure price is a numeric value
                                    $grinder_price = floatval($grinder->price);
                                ?>
                                <option value="<?php echo esc_attr($grinder->id); ?>" 
                                        data-price="<?php echo esc_attr($grinder_price); ?>">
                                    <?php echo esc_html($grinder->name); ?> (<?php echo $grinder_price == 0 ? 'رایگان' : number_format($grinder_price) . ' تومان'; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="hpo-option-section">
                    <h3>توضیحات اضافی</h3>
                    <textarea name="hpo_customer_notes" rows="3" placeholder="اگر توضیح خاصی در مورد سفارش خود دارید، اینجا بنویسید..."></textarea>
                </div>
                
                <div class="hpo-quantity-section">

                </div>
                
                <!-- <div class="hpo-total-price">
                    <span class="hpo-total-label">قیمت نهایی:</span>
                    <span class="hpo-total-value" id="hpo-total-price"><?php echo number_format($product->get_price()); ?> تومان</span>
                </div> -->
                
                <div class="hpo-add-to-cart">
                    <button type="submit" class="hpo-add-to-cart-button">افزودن به سبد خرید</button>
                    <div class="hpo-quantity-input">
                        <button type="button" class="hpo-quantity-plus">+</button>
                        <input type="number" name="quantity" value="1" min="1" max="99" readonly>
                        <button type="button" class="hpo-quantity-minus">-</button>
                        <!-- <h3>تعداد</h3> -->

                    </div>
                </div>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(array(
            'html' => $html,
            'product_title' => $product->get_name()
        ));
    }
    
    /**
     * AJAX handler for adding products to cart with custom options
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('hpo_ajax_nonce', 'nonce');
        
        if (empty($_POST['hpo_data'])) {
            wp_send_json_error(array('message' => 'داده‌های نامعتبر.'));
            return;
        }
        
        // Parse the JSON data
        $data = json_decode(stripslashes($_POST['hpo_data']), true);
        
        if (!is_array($data) || empty($data['product_id'])) {
            wp_send_json_error(array('message' => 'داده‌های نامعتبر.'));
            return;
        }
        
        $product_id = absint($data['product_id']);
        $quantity = isset($data['quantity']) ? absint($data['quantity']) : 1;
        
        // Get product to get base price
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => 'محصول یافت نشد.'));
            return;
        }
        
        // Use the pre-calculated price if provided
        if (!empty($data['hpo_calculated_price'])) {
            $total_price = floatval($data['hpo_calculated_price']);
            if ($total_price <= 0) {
                // Fallback to base price if calculated price is invalid
                $total_price = floatval($product->get_price());
            }
        } else {
            // Manually calculate the total price
            $base_price = floatval($product->get_price());
            $total_price = $base_price;
            
            // Add options prices
            if (!empty($data['hpo_options']) && is_array($data['hpo_options'])) {
                foreach ($data['hpo_options'] as $option) {
                    if (isset($option['price'])) {
                        $total_price = floatval($option['price']); // Replace base price with option price
                    }
                }
            }
            
            // Apply weight coefficient
            if (!empty($data['hpo_weight']) && isset($data['hpo_weight']['coefficient'])) {
                $coefficient = floatval($data['hpo_weight']['coefficient']);
                if ($coefficient > 0) {
                    $total_price *= $coefficient;
                }
            }
            
            // Add grinding machine price
            if (!empty($data['hpo_grinding']) && $data['hpo_grinding'] === 'ground' 
                && !empty($data['hpo_grinding_machine']) && isset($data['hpo_grinding_machine']['price'])) {
                $total_price += floatval($data['hpo_grinding_machine']['price']);
            }
        }
        
        // Ensure price is valid
        $total_price = max(1, $total_price); // Minimum price of 1 to avoid zero price
        
        // Prepare the cart item data
        $cart_item_data = array(
            'hpo_custom_data' => array(
                'options' => array(),
                'weight' => array(),
                'grinding' => '',
                'grinding_machine' => array(),
                'customer_notes' => '',
                'base_price' => floatval($product->get_price()),
                'calculated_price' => $total_price,
                'price_per_unit' => $total_price,
                'custom_price' => $total_price
            ),
        );
        
        // Add options data if available
        if (!empty($data['hpo_options']) && is_array($data['hpo_options'])) {
            foreach ($data['hpo_options'] as $category_id => $option) {
                $cart_item_data['hpo_custom_data']['options'][] = array(
                    'category_id' => $category_id,
                    'option_id' => $option['id'],
                    'name' => $option['name'],
                    'price' => floatval($option['price'])
                );
            }
        }
        
        // Add weight data if available
        if (!empty($data['hpo_weight'])) {
            $cart_item_data['hpo_custom_data']['weight'] = array(
                'id' => $data['hpo_weight']['id'],
                'name' => $data['hpo_weight']['name'],
                'coefficient' => floatval($data['hpo_weight']['coefficient'])
            );
        }
        
        // Add grinding data if available
        if (!empty($data['hpo_grinding'])) {
            $cart_item_data['hpo_custom_data']['grinding'] = $data['hpo_grinding'];
            
            if ($data['hpo_grinding'] === 'ground' && !empty($data['hpo_grinding_machine'])) {
                $cart_item_data['hpo_custom_data']['grinding_machine'] = array(
                    'id' => $data['hpo_grinding_machine']['id'],
                    'name' => $data['hpo_grinding_machine']['name'],
                    'price' => floatval($data['hpo_grinding_machine']['price'])
                );
            }
        }
        
        // Add customer notes if available
        if (!empty($data['hpo_customer_notes'])) {
            $cart_item_data['hpo_custom_data']['customer_notes'] = sanitize_textarea_field($data['hpo_customer_notes']);
        }
        
        // Create a unique key for this specific product configuration
        $unique_key = md5(
            $product_id . '_' . 
            json_encode(isset($data['hpo_options']) ? $data['hpo_options'] : []) . '_' . 
            json_encode(isset($data['hpo_weight']) ? $data['hpo_weight'] : []) . '_' . 
            $data['hpo_grinding'] . '_' . 
            json_encode(isset($data['hpo_grinding_machine']) ? $data['hpo_grinding_machine'] : []) . '_' .
            $total_price . '_' .
            microtime(true) // Add timestamp to ensure uniqueness
        );
        $cart_item_data['unique_key'] = $unique_key;
        
        // Set a meta key in the cart item data to flag this as a custom price item
        $cart_item_data['_hpo_custom_price_item'] = 'yes';
        
        // Set the price everywhere it might be needed
        $cart_item_data['data_price'] = $total_price;
        $cart_item_data['hpo_total_price'] = $total_price;
        $cart_item_data['hpo_calculated_price'] = $total_price;
        
        // Important: Set the product price to our calculated price before adding to cart
        $product->set_price($total_price);
        
        // Add the product to the cart
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
        
        if ($cart_item_key) {
            // Get the cart item and update its price directly
            $cart_contents = WC()->cart->get_cart_contents();
            if (isset($cart_contents[$cart_item_key])) {
                // Set the price directly on the product object
                $cart_contents[$cart_item_key]['data']->set_price($total_price);
                
                // Update custom meta data
                $cart_contents[$cart_item_key]['data']->update_meta_data('_hpo_custom_price', $total_price);
                
                // Ensure our custom data is also stored in the cart item
                $cart_contents[$cart_item_key]['hpo_custom_data']['calculated_price'] = $total_price;
                $cart_contents[$cart_item_key]['hpo_custom_data']['price_per_unit'] = $total_price;
                $cart_contents[$cart_item_key]['hpo_custom_data']['custom_price'] = $total_price;
                $cart_contents[$cart_item_key]['data_price'] = $total_price;
                $cart_contents[$cart_item_key]['hpo_total_price'] = $total_price;
                $cart_contents[$cart_item_key]['hpo_calculated_price'] = $total_price;
                
                // Explicitly save to session
                WC()->session->set('cart_' . $cart_item_key, $cart_contents[$cart_item_key]);
            }
            
            // Force WooCommerce to recalculate totals
            WC()->cart->calculate_totals();
            
            // Generate HTML for success popup
            ob_start();
            ?>
            <div class="hpo-success-popup-overlay">
                <div class="hpo-success-popup">
                    <div class="hpo-success-icon">
                        <svg viewBox="0 0 24 24" width="50" height="50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22 11.0857V12.0057C21.9988 14.1621 21.3005 16.2604 20.0093 17.9875C18.7182 19.7147 16.9033 20.9782 14.8354 21.5896C12.7674 22.201 10.5573 22.1276 8.53447 21.3803C6.51168 20.633 4.78465 19.2518 3.61096 17.4428C2.43727 15.6338 1.87979 13.4938 2.02168 11.342C2.16356 9.19029 2.99721 7.14205 4.39828 5.5028C5.79935 3.86354 7.69279 2.72111 9.79619 2.24587C11.8996 1.77063 14.1003 1.98806 16.07 2.86572" stroke="#25AE88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M22 4L12 14.01L9 11.01" stroke="#25AE88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3>سفارش با موفقیت به سبد خرید اضافه شد</h3>
                    <div class="hpo-success-actions">
                        <button class="hpo-repeat-order-btn">سفارش جدید</button>
                        <button class="hpo-checkout-btn">پرداخت سفارش</button>
                        <button class="hpo-cart-btn">مشاهده سبد خرید</button>
                        <button class="hpo-close-success-btn">بستن</button>
                        
                    </div>
                </div>
            </div>

            <?php
            $success_popup_html = ob_get_clean();
            
            wp_send_json_success(array(
                'message' => 'محصول با موفقیت به سبد خرید اضافه شد.',
                'cart_item_key' => $cart_item_key,
                'price' => $total_price,
                'unique_key' => $unique_key,
                'success_popup_html' => $success_popup_html
            ));
        } else {
            wp_send_json_error(array('message' => 'خطا در افزودن محصول به سبد خرید.'));
        }
    }
    
    /**
     * Add custom data to cart item
     *
     * @param array $item_data Array of item data
     * @param array $cart_item Cart item data
     * @return array Modified item data
     */
    public function add_cart_item_custom_data($item_data, $cart_item) {
        // First, remove any default WooCommerce metadata that we handle ourselves
        foreach ($item_data as $key => $data) {
            if (isset($data['key']) && ($data['key'] === 'Grinding' || $data['key'] === 'Weight' || $data['key'] === 'گزینه' || 
                $data['key'] === 'وزن' || $data['key'] === 'آسیاب' || $data['key'] === 'وضعیت آسیاب' || 
                $data['key'] === 'قیمت محاسبه شده' || $data['key'] === 'grinding_display' || $data['key'] === 'weight_display')) {
                unset($item_data[$key]);
            }
        }
        
        // Then add our custom data in the exact format requested
        if (isset($cart_item['hpo_custom_data'])) {
            $custom_data = $cart_item['hpo_custom_data'];
            
            // We don't add the product options here anymore since they're shown in the product name
            
            // 1. Weight options
            if (!empty($custom_data['weight'])) {
                $item_data[] = array(
                    'key' => 'وزن',
                    'value' => $custom_data['weight']['name'],
                    'display' => $custom_data['weight']['name']
                );
            }
            
            // 2. Grinding status
            if (!empty($custom_data['grinding'])) {
                if ($custom_data['grinding'] === 'whole') {
                    $item_data[] = array(
                        'key' => 'وضعیت آسیاب',
                        'value' => 'بدون آسیاب',
                        'display' => 'بدون آسیاب'
                    );
                } else if ($custom_data['grinding'] === 'ground' && !empty($custom_data['grinding_machine'])) {
                    $grinding_text = 'آسیاب شود برای: ' . $custom_data['grinding_machine']['name'];
                    $item_data[] = array(
                        'key' => 'وضعیت آسیاب',
                        'value' => $grinding_text,
                        'display' => $grinding_text
                    );
                }
            }
            
            // 3. Customer notes (if any)
            if (!empty($custom_data['customer_notes'])) {
                $item_data[] = array(
                    'key' => 'توضیحات',
                    'value' => $custom_data['customer_notes'],
                    'display' => $custom_data['customer_notes']
                );
            }
        }
        
        // Clean up the array by reindexing
        return array_values($item_data);
    }
    
    /**
     * Calculate cart item prices
     *
     * @param WC_Cart $cart Cart object
     */
    public function calculate_cart_item_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Ensure this runs only once per page load but will run again on next page load
        static $has_run = false;
        if ($has_run) return;
        $has_run = true;
        
        // Count how many HPO items we have in the cart
        $hpo_item_count = 0;
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['hpo_custom_data'])) {
                $hpo_item_count++;
            }
        }
        
        // Store count in session for use in other methods
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('hpo_item_count', $hpo_item_count);
        }
        
        // Process each cart item
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Skip if this is not our custom item
            if (!isset($cart_item['hpo_custom_data'])) {
                continue;
            }
            
            $custom_data = $cart_item['hpo_custom_data'];
            $price_updated = false;
            $final_price = 0;
            
            // Priority 1: Check if we have a price_per_unit
            if (isset($custom_data['price_per_unit']) && $custom_data['price_per_unit'] > 0) {
                $final_price = floatval($custom_data['price_per_unit']);
                $price_updated = true;
            } 
            // Priority 2: Check calculated_price 
            else if (isset($custom_data['calculated_price']) && $custom_data['calculated_price'] > 0) {
                $final_price = floatval($custom_data['calculated_price']);
                $price_updated = true;
            }
            // Priority 3: Check custom_price
            else if (isset($custom_data['custom_price']) && $custom_data['custom_price'] > 0) {
                $final_price = floatval($custom_data['custom_price']);
                $price_updated = true;
            }
            // Priority 4: Check direct cart item price properties
            else if (isset($cart_item['data_price']) && $cart_item['data_price'] > 0) {
                $final_price = floatval($cart_item['data_price']);
                $price_updated = true;
            }
            else if (isset($cart_item['hpo_total_price']) && $cart_item['hpo_total_price'] > 0) {
                $final_price = floatval($cart_item['hpo_total_price']);
                $price_updated = true;
            }
            
            // If we found a price, update the product object
            if ($price_updated && $final_price > 0) {
                // Set the price directly - this is the proper way
                $cart_item['data']->set_price($final_price);
                
                // Store in custom meta (not internal meta)
                $cart_item['data']->update_meta_data('_hpo_custom_price', $final_price);
                
                // Store in cart item data for later use
                $cart->cart_contents[$cart_item_key]['hpo_custom_data']['calculated_price'] = $final_price;
                $cart->cart_contents[$cart_item_key]['hpo_custom_data']['price_per_unit'] = $final_price;
                $cart->cart_contents[$cart_item_key]['hpo_custom_data']['custom_price'] = $final_price;
                $cart->cart_contents[$cart_item_key]['data_price'] = $final_price;
                $cart->cart_contents[$cart_item_key]['hpo_total_price'] = $final_price;
                $cart->cart_contents[$cart_item_key]['line_total'] = $final_price * $cart_item['quantity'];
                $cart->cart_contents[$cart_item_key]['line_subtotal'] = $final_price * $cart_item['quantity'];
                
                // Make sure it's updated in the session
                if (function_exists('WC') && WC()->session) {
                    WC()->session->set('cart_' . $cart_item_key, $cart->cart_contents[$cart_item_key]);
                }
            }
        }
        
        // If we have HPO items, immediately run recalculation to ensure consistent pricing
        if ($hpo_item_count > 0) {
            // This will trigger our custom calculation hooks
            $cart->calculate_totals();
        }
    }
    
    /**
     * Update cart item price
     *
     * @param string $price Cart item price
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified cart item price
     */
    public function update_cart_item_price($price, $cart_item, $cart_item_key) {
        // Skip if this is not our custom item
        if (!isset($cart_item['hpo_custom_data'])) {
            return $price; // Return original price for regular WooCommerce products
        }
        
        // Priority for price sources:
        // 1. Custom data price_per_unit
        // 2. Custom data calculated_price
        // 3. Custom data custom_price
        // 4. Direct cart item properties
        // 5. Product object price
        // 6. Meta data
        $price_value = 0;
        
        // Priority 1: Custom data price_per_unit
        if (isset($cart_item['hpo_custom_data']) && 
            isset($cart_item['hpo_custom_data']['price_per_unit']) && 
            $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
            
            $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
        } 
        // Priority 2: Custom data calculated_price
        elseif (isset($cart_item['hpo_custom_data']) && 
                isset($cart_item['hpo_custom_data']['calculated_price']) && 
                $cart_item['hpo_custom_data']['calculated_price'] > 0) {
            
            $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
        }
        // Priority 3: Custom data custom_price
        elseif (isset($cart_item['hpo_custom_data']) && 
                isset($cart_item['hpo_custom_data']['custom_price']) && 
                $cart_item['hpo_custom_data']['custom_price'] > 0) {
            
            $price_value = floatval($cart_item['hpo_custom_data']['custom_price']);
        }
        // Priority 4: Direct cart item properties
        elseif (isset($cart_item['data_price']) && $cart_item['data_price'] > 0) {
            $price_value = floatval($cart_item['data_price']);
        }
        elseif (isset($cart_item['hpo_total_price']) && $cart_item['hpo_total_price'] > 0) {
            $price_value = floatval($cart_item['hpo_total_price']);
        }
        // Priority 5: Product object price
        elseif (isset($cart_item['data'])) {
            $price_value = floatval($cart_item['data']->get_price());
            
            // If product price is still zero, try to get it from meta
            if ($price_value <= 0) {
                // Try _hpo_custom_price first
                $price_meta = $cart_item['data']->get_meta('_hpo_custom_price');
                if ($price_meta && floatval($price_meta) > 0) {
                    $price_value = floatval($price_meta);
                } else {
                    // Then try _price
                    $price_meta = $cart_item['data']->get_meta('_price');
                    if ($price_meta && floatval($price_meta) > 0) {
                        $price_value = floatval($price_meta);
                    }
                }
            }
        }
        
        // Only update if we have a valid price
        if ($price_value > 0) {
            // If we're on a cart page, update the data object to ensure consistent pricing
            if (is_cart() || is_checkout()) {
                WC()->cart->cart_contents[$cart_item_key]['data']->set_price($price_value);
                
                // Only update our custom meta
                WC()->cart->cart_contents[$cart_item_key]['data']->update_meta_data('_hpo_custom_price', $price_value);
                
                // Also update our custom data for consistency
                if (isset(WC()->cart->cart_contents[$cart_item_key]['hpo_custom_data'])) {
                    WC()->cart->cart_contents[$cart_item_key]['hpo_custom_data']['price_per_unit'] = $price_value;
                    WC()->cart->cart_contents[$cart_item_key]['hpo_custom_data']['calculated_price'] = $price_value;
                    WC()->cart->cart_contents[$cart_item_key]['hpo_custom_data']['custom_price'] = $price_value;
                }
            }
            
            // Format the price with the exact HTML structure WooCommerce expects
            $formatted_price = sprintf(
                '<span class="woocommerce-Price-amount amount"><bdi>%s&nbsp;<span class="woocommerce-Price-currencySymbol">تومان</span></bdi></span>',
                number_format($price_value)
            );
            
            return $formatted_price;
        }
        
        // Fallback to default price format if our price is zero or invalid
        return $price;
    }
    
    /**
     * Filter to change price display format for our popups
     *
     * @param string $price Price HTML
     * @param WC_Product $product Product object
     * @return string Modified price HTML
     */
    public function format_price_html($price, $product) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return $price;
        }
        
        if ($product->get_price() > 0) {
            $price_value = $product->get_price();
            return number_format($price_value) . ' تومان';
        }
        return $price;
    }
    
    /**
     * Ensure our custom data is preserved and products with options don't get merged in cart
     *
     * @param array $cart_item_data Cart item data
     * @param int $product_id Product ID
     * @return array Modified cart item data
     */
    public function prevent_cart_merging($cart_item_data, $product_id) {
        if (isset($cart_item_data['hpo_custom_data'])) {
            // Create a unique key based on product ID and selected options
            $options = isset($cart_item_data['hpo_custom_data']['options']) ? $cart_item_data['hpo_custom_data']['options'] : [];
            $weight = isset($cart_item_data['hpo_custom_data']['weight']) ? $cart_item_data['hpo_custom_data']['weight'] : [];
            $grinding = isset($cart_item_data['hpo_custom_data']['grinding']) ? $cart_item_data['hpo_custom_data']['grinding'] : 'whole';
            $grinding_machine = isset($cart_item_data['hpo_custom_data']['grinding_machine']) ? $cart_item_data['hpo_custom_data']['grinding_machine'] : [];
            $price = 0;
            
            // Get the calculated price to include in the unique key
            if (isset($cart_item_data['hpo_custom_data']['price_per_unit'])) {
                $price = floatval($cart_item_data['hpo_custom_data']['price_per_unit']);
            } elseif (isset($cart_item_data['hpo_custom_data']['calculated_price'])) {
                $price = floatval($cart_item_data['hpo_custom_data']['calculated_price']);
            } elseif (isset($cart_item_data['hpo_custom_data']['custom_price'])) {
                $price = floatval($cart_item_data['hpo_custom_data']['custom_price']);
            }
            
            $unique_key = md5(
                $product_id . '_' . 
                json_encode($options) . '_' . 
                json_encode($weight) . '_' . 
                $grinding . '_' . 
                json_encode($grinding_machine) . '_' .
                // Add price to ensure uniqueness for different price configurations
                $price . '_' .
                // Add a timestamp to ensure uniqueness even for identical products
                microtime(true)
            );
            
            $cart_item_data['unique_key'] = $unique_key;
            
            // For debugging
            add_action('wp_footer', function() use ($unique_key, $product_id) {
                if (is_cart() || is_checkout() || wp_doing_ajax()) {
                    echo '<script>console.log("HPO: Generated unique key ' . esc_js($unique_key) . ' for product ' . esc_js($product_id) . '");</script>';
                }
            }, 99);
        }
        return $cart_item_data;
    }
    
    /**
     * Get cart item from session
     *
     * @param array $cart_item Cart item data
     * @param array $values Session data
     * @return array Modified cart item data
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['hpo_custom_data'])) {
            $cart_item['hpo_custom_data'] = $values['hpo_custom_data'];
            
            // Get the price with priority order
            $price = 0;
            if (isset($values['hpo_custom_data']['price_per_unit']) && $values['hpo_custom_data']['price_per_unit'] > 0) {
                $price = floatval($values['hpo_custom_data']['price_per_unit']);
            } elseif (isset($values['hpo_custom_data']['calculated_price']) && $values['hpo_custom_data']['calculated_price'] > 0) {
                $price = floatval($values['hpo_custom_data']['calculated_price']);
            } elseif (isset($values['hpo_custom_data']['custom_price']) && $values['hpo_custom_data']['custom_price'] > 0) {
                $price = floatval($values['hpo_custom_data']['custom_price']);
            }
            
            // Apply the custom price to the product object if we have a valid price
            if ($price > 0 && isset($cart_item['data'])) {
                // Set the price directly on the product object
                $cart_item['data']->set_price($price);
                
                // Don't update internal meta data directly
                // $cart_item['data']->update_meta_data('_price', $price);
                
                // Only update custom meta data
                $cart_item['data']->update_meta_data('_hpo_custom_price', $price);
                
                // Set important flags
                $cart_item['_hpo_custom_price_item'] = 'yes';
                $cart_item['data_price'] = $price;
                $cart_item['hpo_total_price'] = $price;
                
                // Make sure this is a reference to the correct values with a clean unique key
                if (isset($values['unique_key'])) {
                    $cart_item['unique_key'] = $values['unique_key'];
                } else {
                    // Generate a new unique key if missing
                    if (isset($cart_item['data']) && method_exists($cart_item['data'], 'get_id')) {
                        $product_id = $cart_item['data']->get_id();
                        $options = isset($cart_item['hpo_custom_data']['options']) ? $cart_item['hpo_custom_data']['options'] : [];
                        $weight = isset($cart_item['hpo_custom_data']['weight']) ? $cart_item['hpo_custom_data']['weight'] : [];
                        $grinding = isset($cart_item['hpo_custom_data']['grinding']) ? $cart_item['hpo_custom_data']['grinding'] : 'whole';
                        $grinding_machine = isset($cart_item['hpo_custom_data']['grinding_machine']) ? $cart_item['hpo_custom_data']['grinding_machine'] : [];
                        
                        $cart_item['unique_key'] = md5(
                            $product_id . '_' . 
                            json_encode($options) . '_' . 
                            json_encode($weight) . '_' . 
                            $grinding . '_' . 
                            json_encode($grinding_machine) . '_' .
                            // Add the price to ensure uniqueness when prices differ
                            $price
                        );
                    }
                }
                
                // Log to console for debugging
            }
            
            // If the object has a unique key, preserve it
            if (isset($values['unique_key'])) {
                $cart_item['unique_key'] = $values['unique_key'];
            }
        }
        return $cart_item;
    }
    
    /**
     * Setup cart item
     *
     * @param array $cart_item Cart item data
     * @return array Modified cart item data
     */
    public function setup_cart_item($cart_item) {
        // Apply the custom price to the product object if we have custom data
        if (isset($cart_item['hpo_custom_data']) && !empty($cart_item['data'])) {
            // Check if we have a calculated price
            if (isset($cart_item['hpo_custom_data']['price_per_unit']) && 
                $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                
                $calculated_price = floatval($cart_item['hpo_custom_data']['price_per_unit']);
                // Set the price directly on the product object
                $cart_item['data']->set_price($calculated_price);
            }
        }
        
        return $cart_item;
    }
    
    /**
     * Modify cart item name
     *
     * @param string $name Cart item name
     * @param array $cart_item Cart item data
     * @param array $cart_item_key Cart item key
     * @return string Modified cart item name
     */
    public function modify_cart_item_name($name, $cart_item, $cart_item_key) {
        if (isset($cart_item['hpo_custom_data']) && isset($cart_item['hpo_custom_data']['options'])) {
            $options = array();
            foreach ($cart_item['hpo_custom_data']['options'] as $option) {
                $options[] = $option['name'];
            }
            $name .= ' (' . implode(', ', $options) . ')';
        }
        return $name;
    }
    
    /**
     * Remove grinding metadata from cart item
     *
     * @param string $html The metadata HTML
     * @param array $item The cart item
     * @param array $args Arguments for the display
     * @return string Modified metadata HTML
     */
    public function remove_grinding_metadata($html, $item, $args) {
        // This is a simple approach to remove the Grinding metadata
        // We search for Grinding: Whole (No Grinding) or similar patterns
        $html = preg_replace('/<[^>]+>Grinding:.*?<\/[^>]+>/', '', $html);
        return $html;
    }
    
    /**
     * Modify mini cart quantity
     *
     * @param string $quantity Cart item quantity
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified cart item quantity
     */
    public function modify_mini_cart_quantity($quantity, $cart_item, $cart_item_key) {
        // Skip if this is not our custom item
        if (!isset($cart_item['hpo_custom_data'])) {
            return $quantity; // Return original quantity for regular WooCommerce products
        }
        
        // Get the price using the same priority as update_cart_item_price
        $price_value = 0;
        
        if (isset($cart_item['hpo_custom_data']['price_per_unit']) && 
            $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
            $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
        } elseif (isset($cart_item['hpo_custom_data']['calculated_price']) && 
                 $cart_item['hpo_custom_data']['calculated_price'] > 0) {
            $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
        }
        
        // If no custom price found, use the product price
        if ($price_value <= 0 && isset($cart_item['data'])) {
            $price_value = floatval($cart_item['data']->get_price());
            
            // If product price is still zero, try to get it from meta
            if ($price_value <= 0) {
                $price_meta = $cart_item['data']->get_meta('_price');
                if ($price_meta) {
                    $price_value = floatval($price_meta);
                }
            }
        }
        
        if ($price_value > 0) {
            $actual_quantity = $cart_item['quantity'];
            
            // Format: quantity × price تومان
            return $actual_quantity . ' × ' . number_format($price_value) . ' تومان';
        }
        
        return $quantity;
    }
    
    /**
     * Modify cart item quantity
     *
     * @param string $quantity_html Cart item quantity
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified cart item quantity
     */
    public function modify_cart_item_quantity($quantity_html, $cart_item, $cart_item_key) {
        // Skip if this is not our custom item
        if (!isset($cart_item['hpo_custom_data'])) {
            return $quantity_html; // Return original quantity for regular WooCommerce products
        }
        
        // Get the price using the same priority as other methods
        $price_value = 0;
        
        if (isset($cart_item['hpo_custom_data']['price_per_unit']) && 
            $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
            $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
        } elseif (isset($cart_item['hpo_custom_data']['calculated_price']) && 
                 $cart_item['hpo_custom_data']['calculated_price'] > 0) {
            $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
        }
        
        // If no custom price found, use the product price
        if ($price_value <= 0 && isset($cart_item['data'])) {
            $price_value = floatval($cart_item['data']->get_price());
            
            // If product price is still zero, try to get it from meta
            if ($price_value <= 0) {
                $price_meta = $cart_item['data']->get_meta('_price');
                if ($price_meta) {
                    $price_value = floatval($price_meta);
                }
            }
        }
        
        if ($price_value > 0) {
            $actual_quantity = $cart_item['quantity'];
            
            // Completely replace the default quantity HTML with our custom format
            return '<span class="hpo-custom-quantity">' . $actual_quantity . ' × ' . number_format($price_value) . ' تومان</span>';
        }
        
        return $quantity_html;
    }
    
    /**
     * Modify cart item quantity display
     *
     * @param string $quantity_html Quantity HTML
     * @param object $product Product object
     * @param array $cart_item_data Cart item data
     * @return string Modified quantity HTML
     */
    public function modify_cart_item_quantity_display($quantity_html, $product, $cart_item_data) {
        // Skip if this is not our custom item
        if (!isset($cart_item_data['hpo_custom_data'])) {
            return $quantity_html; // Return original quantity for regular WooCommerce products
        }
        
        // Get the price using the same priority as other methods
        $price_value = 0;
        
        if (isset($cart_item_data['hpo_custom_data']['price_per_unit']) && 
            $cart_item_data['hpo_custom_data']['price_per_unit'] > 0) {
            $price_value = floatval($cart_item_data['hpo_custom_data']['price_per_unit']);
        } elseif (isset($cart_item_data['hpo_custom_data']['calculated_price']) && 
                 $cart_item_data['hpo_custom_data']['calculated_price'] > 0) {
            $price_value = floatval($cart_item_data['hpo_custom_data']['calculated_price']);
        }
        
        // If no custom price is found, try to get it from the product
        if ($price_value <= 0 && $product) {
            $price_value = floatval($product->get_price());
            
            // If product price is still zero, try to get it from meta
            if ($price_value <= 0) {
                $price_meta = $product->get_meta('_price');
                if ($price_meta) {
                    $price_value = floatval($price_meta);
                }
            }
        }
        
        if ($price_value > 0) {
            $actual_quantity = isset($cart_item_data['quantity']) ? $cart_item_data['quantity'] : 1;
            
            // Create a custom display format
            return '<span class="hpo-custom-quantity">' . $actual_quantity . ' × ' . number_format($price_value) . ' تومان</span>';
        }
        
        return $quantity_html;
    }
    
    /**
     * Add custom CSS for cart display
     */
    public function add_custom_cart_css() {
        ?>
        <style type="text/css">
            /* Hide the default price display and replace with our custom one */
            .woocommerce-mini-cart span.quantity {
                display: block !important;
            }
            
            /* Custom styling for our quantity display */
            span.hpo-custom-quantity {
                display: block;
                font-weight: bold;
                margin-top: 5px;
            }
            
            /* Fix the product price in cart by ensuring the woocommerce-Price-amount shows our custom price */
            .woocommerce-cart .woocommerce-Price-amount.amount {
                display: inline-block !important;
            }
            
            /* Show our custom price format instead */
            span.hpo-custom-quantity {
                display: block !important;
            }
            
            /* Style the span so it looks like the WooCommerce price */
            .hpo-custom-quantity {
                font-weight: bold;
                color: #333;
            }

            /* Hide the add to cart button in loop products */
            .add_to_cart_button {
                display: none;
            }
            
            /* Make the quantity more visible in checkout */
            #order_review .product-quantity {
                font-weight: bold;
            }
            
            /* Fix cart discount display */
            tr.cart-discount td {
                text-align: right;
            }
            
            /* Make sure the amount has a minus sign */
            tr.cart-discount .woocommerce-Price-amount.amount:before {
                content: "-";
                display: inline-block;
                margin-right: 2px;
            }
            
        </style>
        <?php
    }

    /**
     * Filter product price to ensure it's correct in cart
     *
     * @param float $price The product price
     * @param object $product The product object
     * @return float Modified price
     */
    public function filter_product_price($price, $product) {
        // Only apply on cart/checkout pages
        if (is_cart() || is_checkout()) {
            // Get the cart
            $cart = WC()->cart;
            if (!$cart) {
                return floatval($price);
            }
            
            // Get the product ID
            $product_id = $product->get_id();
            
            // Check each cart item
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                // Only process items that were added through our plugin
                if (!isset($cart_item['hpo_custom_data'])) {
                    continue;
                }
                
                // Find matching product
                if ($cart_item['product_id'] == $product_id || 
                    (isset($cart_item['variation_id']) && $cart_item['variation_id'] == $product_id)) {
                    
                    // Priority 1: Custom data price_per_unit
                    if (isset($cart_item['hpo_custom_data']['price_per_unit']) &&
                        $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                        
                        $custom_price = floatval($cart_item['hpo_custom_data']['price_per_unit']);
                        return $custom_price;
                    }
                    
                    // Priority 2: Custom data calculated_price
                    if (isset($cart_item['hpo_custom_data']['calculated_price']) &&
                        $cart_item['hpo_custom_data']['calculated_price'] > 0) {
                        
                        $custom_price = floatval($cart_item['hpo_custom_data']['calculated_price']);
                        return $custom_price;
                    }
                    
                    // Priority 3: Custom data custom_price
                    if (isset($cart_item['hpo_custom_data']['custom_price']) &&
                        $cart_item['hpo_custom_data']['custom_price'] > 0) {
                        
                        $custom_price = floatval($cart_item['hpo_custom_data']['custom_price']);
                        return $custom_price;
                    }
                    
                    // Priority 4: Direct cart item properties
                    if (isset($cart_item['data_price']) && $cart_item['data_price'] > 0) {
                        $custom_price = floatval($cart_item['data_price']);
                        return $custom_price;
                    }
                    
                    if (isset($cart_item['hpo_total_price']) && $cart_item['hpo_total_price'] > 0) {
                        $custom_price = floatval($cart_item['hpo_total_price']);
                        return $custom_price;
                    }
                    
                    // Priority 5: Product object price from cart item
                    if (isset($cart_item['data']) && method_exists($cart_item['data'], 'get_price')) {
                        $product_price = floatval($cart_item['data']->get_price());
                        if ($product_price > 0) {
                            return $product_price;
                        }
                    }
                }
            }
        }
        
        // Always return the original price for regular products
        return floatval($price);
    }

    /**
     * Save order item meta for the shortcode cart items
     *
     * @param object $item The order item
     * @param string $cart_item_key The cart item key
     * @param array $values The cart item values
     * @param WC_Order $order The order object
     */
    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['hpo_custom_data'])) {
            $custom_data = $values['hpo_custom_data'];
            
            // Save selected product options in the same format as the main plugin
            if (!empty($custom_data['options'])) {
                $item->add_meta_data('_hpo_products', $custom_data['options']);
            }
            
            // Save weight option
            if (!empty($custom_data['weight'])) {
                $weight_data = array(
                    'name' => $custom_data['weight']['name'],
                    'coefficient' => isset($custom_data['weight']['coefficient']) ? $custom_data['weight']['coefficient'] : 1
                );
                $item->add_meta_data('_hpo_weight', $weight_data);
            }
            
            // Save grinding option
            if (!empty($custom_data['grinding'])) {
                $grinding_data = array(
                    'type' => $custom_data['grinding']
                );
                
                if ($custom_data['grinding'] === 'ground' && !empty($custom_data['grinding_machine'])) {
                    $grinding_data['machine'] = array(
                        'name' => $custom_data['grinding_machine']['name'],
                        'price' => $custom_data['grinding_machine']['price']
                    );
                }
                
                $item->add_meta_data('_hpo_grinder', $grinding_data);
            }
            
            // Save customer notes
            if (!empty($custom_data['customer_notes'])) {
                $item->add_meta_data('_hpo_customer_notes', $custom_data['customer_notes']);
            }
            
            // Save calculated price
            if (isset($custom_data['calculated_price'])) {
                $item->add_meta_data('_hpo_calculated_price', $custom_data['calculated_price']);
            } else if (isset($custom_data['price_per_unit'])) {
                $item->add_meta_data('_hpo_calculated_price', $custom_data['price_per_unit']);
            }
        }
    }

    /**
     * Fix cart item prices after loading from session
     *
     * @param WC_Cart $cart The cart object
     */
    public function fix_cart_after_session_load($cart) {
        // Check if the cart is loaded
        if (!$cart || empty($cart->get_cart())) {
            return;
        }
        
        $updated = false;
        
        // Force update of all prices
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Only process items added through our plugin
            if (!isset($cart_item['hpo_custom_data'])) {
                continue;
            }
            
            $price_value = 0;
            
            // Get price with priority order
            if (isset($cart_item['hpo_custom_data']['price_per_unit']) && 
                $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
            } elseif (isset($cart_item['hpo_custom_data']['calculated_price']) && 
                $cart_item['hpo_custom_data']['calculated_price'] > 0) {
                $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
            } elseif (isset($cart_item['hpo_custom_data']['custom_price']) && 
                $cart_item['hpo_custom_data']['custom_price'] > 0) {
                $price_value = floatval($cart_item['hpo_custom_data']['custom_price']);
            }
            
            // If we have a valid price, apply it
            if ($price_value > 0) {
                // Set the price directly on the product object
                $cart->cart_contents[$cart_item_key]['data']->set_price($price_value);
                
                // Only update custom meta (not internal _price)
                $cart->cart_contents[$cart_item_key]['data']->update_meta_data('_hpo_custom_price', $price_value);
                
                // Also update our custom data for consistency
                $cart->cart_contents[$cart_item_key]['hpo_custom_data']['price_per_unit'] = $price_value;
                $cart->cart_contents[$cart_item_key]['hpo_custom_data']['calculated_price'] = $price_value;
                $cart->cart_contents[$cart_item_key]['hpo_custom_data']['custom_price'] = $price_value;
                
                // Set direct cart item properties
                $cart->cart_contents[$cart_item_key]['data_price'] = $price_value;
                $cart->cart_contents[$cart_item_key]['hpo_total_price'] = $price_value;
                $cart->cart_contents[$cart_item_key]['_hpo_custom_price_item'] = 'yes';
                
                // Explicitly set in WooCommerce session to prevent lost updates
                if (function_exists('WC') && WC()->session) {
                    WC()->session->set('cart_' . $cart_item_key, $cart->cart_contents[$cart_item_key]);
                }
                
                $updated = true;
            }
        }
        
        // Only recalculate totals if we actually updated any prices
        if ($updated) {
            // Force cart recalculation
            $cart->calculate_totals();
        }
        
        // Add debugging to check if prices are correctly set
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', function() use ($cart) {
                if (is_cart() || is_checkout()) {
                    echo '<script>console.log("HPO Debug: Cart session loaded and prices fixed");</script>';
                    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                        if (isset($cart_item['hpo_custom_data'])) {
                            $price = isset($cart_item['data']) ? $cart_item['data']->get_price() : 0;
                            echo '<script>console.log("HPO Item ' . esc_js($cart_item_key) . ' price: ' . esc_js($price) . '");</script>';
                        }
                    }
                }
            }, 999);
        }
    }

    /**
     * After calculate totals, make sure cart totals are calculated correctly
     *
     * @param WC_Cart $cart The cart object
     */
    public function after_calculate_totals($cart) {
        static $is_calculating = false;
        
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // جلوگیری از فراخوانی بازگشتی
        if ($is_calculating) {
            return;
        }
        
        $is_calculating = true;
        
        try {
            // Only proceed if we have HPO items in the cart
            $has_hpo_items = false;
            $hpo_items_count = 0;
            
            foreach ($cart->get_cart() as $cart_item) {
                if (isset($cart_item['hpo_custom_data'])) {
                    $has_hpo_items = true;
                    $hpo_items_count++;
                }
            }
            
            if (!$has_hpo_items) {
                return;
            }
            
            // Calculate totals correctly for HPO items
            $subtotal = 0;
            
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $price = 0;
                $quantity = $cart_item['quantity'];
                
                if (isset($cart_item['hpo_custom_data'])) {
                    // Get price from HPO custom data
                    if (isset($cart_item['hpo_custom_data']['price_per_unit']) && 
                        $cart_item['hpo_custom_data']['price_per_unit'] > 0) {
                        $price = floatval($cart_item['hpo_custom_data']['price_per_unit']);
                    } elseif (isset($cart_item['hpo_custom_data']['calculated_price']) && 
                            $cart_item['hpo_custom_data']['calculated_price'] > 0) {
                        $price = floatval($cart_item['hpo_custom_data']['calculated_price']);
                    } elseif (isset($cart_item['hpo_custom_data']['custom_price']) && 
                            $cart_item['hpo_custom_data']['custom_price'] > 0) {
                        $price = floatval($cart_item['hpo_custom_data']['custom_price']);
                    }
                } else {
                    // For regular products, get price from product
                    $price = floatval($cart_item['data']->get_price());
                }
                
                $line_total = $price * $quantity;
                $subtotal += $line_total;
                
                // Make sure the line total is correctly set in the cart item
                $cart->cart_contents[$cart_item_key]['line_total'] = $line_total;
                $cart->cart_contents[$cart_item_key]['line_subtotal'] = $line_total;
            }
            
            // Set cart subtotal
            if ($subtotal > 0) {
                // We need to handle the cart subtotal correctly
                add_filter('woocommerce_cart_subtotal', function($cart_subtotal, $compound, $cart) use ($subtotal) {
                    // Format the subtotal with WooCommerce's currency format
                    return wc_price($subtotal);
                }, 999, 3);
                
                // If there's no shipping or fees, we can also set the total directly in the cart object
                if (count($cart->get_shipping_packages()) === 0 && count($cart->get_fees()) === 0) {
                    // Store the original subtotal so WooCommerce can calculate percentage discounts correctly
                    $cart->set_subtotal($subtotal);
                    $cart->set_cart_contents_total($subtotal);
                    
                    // Get applied coupons
                    $applied_coupons = $cart->get_applied_coupons();
                    $discount_total = 0;
                    
                    // Calculate discount manually for percentage coupons to ensure they're correctly applied
                    if (!empty($applied_coupons)) {
                        foreach ($applied_coupons as $coupon_code) {
                            $coupon = new WC_Coupon($coupon_code);
                            
                            if ($coupon->get_discount_type() === 'percent') {
                                // For percentage coupons, recalculate the discount based on our correct subtotal
                                $percent = $coupon->get_amount();
                                $discount_amount = ($subtotal * $percent) / 100;
                                $discount_total += $discount_amount;
                            } else {
                                // For non-percentage coupons, use WooCommerce's calculation
                                $discount_total = $cart->get_discount_total();
                                break; // If there's any fixed amount coupon, use WC's discount total
                            }
                        }
                    } else {
                        // No coupons applied
                        $discount_total = $cart->get_discount_total();
                    }
                    
                    // Apply the discount to the total
                    $total_after_discount = $subtotal - $discount_total;
                    
                    // Set the final total with discount applied
                    $cart->set_discount_total($discount_total);
                    $cart->set_total($total_after_discount);
                    
                    add_filter('woocommerce_cart_total', function($total) use ($total_after_discount) {
                        return wc_price($total_after_discount);
                    }, 999);
                    
                    // Add filter to ensure the discount total is displayed correctly
                    add_filter('woocommerce_cart_totals_coupon_html', function($coupon_html, $coupon, $discount_amount_html) use ($discount_total, $applied_coupons) {
                        if (count($applied_coupons) === 1) {
                            // Only modify if there's a single coupon applied
                            return '-' . wc_price($discount_total);
                        }
                        return $coupon_html;
                    }, 10, 3);
                }
            }
        } finally {
            $is_calculating = false;
        }
    }

    /**
     * Update checkout item quantity to also show the unit price
     *
     * @param string $quantity Quantity HTML
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified quantity HTML
     */
    public function update_checkout_item_quantity($quantity, $cart_item, $cart_item_key) {
        // Skip if this is not our custom item
        if (!isset($cart_item['hpo_custom_data'])) {
            return $quantity;
        }
        
        $actual_quantity = $cart_item['quantity'];
        if ($actual_quantity > 1) {
            // Make the quantity more visible
            return '<strong>' . $actual_quantity . 'x</strong> ' . $quantity;
        }
        
        return $quantity;
    }
    
    /**
     * Update the line subtotal displayed in checkout
     *
     * @param string $subtotal Formatted subtotal
     * @param array $item Line item data
     * @param WC_Order $order Order object
     * @return string Modified subtotal
     */
    public function update_checkout_line_subtotal($subtotal, $item, $order) {
        // Prevent fatal error if WC()->cart is not available (e.g. in emails or order admin)
        if (!function_exists('WC') || !WC()->cart) {
            return $subtotal;
        }
        
        // Try to get the cart item key from the item
        $product_id = $item->get_product_id();
        $found_item = null;
        $found_key = null;
        
        // Find the matching cart item
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] == $product_id) {
                $found_item = $cart_item;
                $found_key = $cart_item_key;
                break;
            }
        }
        
        // If found and it's our custom item, recalculate the subtotal
        if ($found_item && isset($found_item['hpo_custom_data'])) {
            $price = 0;
            
            // Get price with our priority order
            if (isset($found_item['hpo_custom_data']['price_per_unit'])) {
                $price = floatval($found_item['hpo_custom_data']['price_per_unit']);
            } elseif (isset($found_item['hpo_custom_data']['calculated_price'])) {
                $price = floatval($found_item['hpo_custom_data']['calculated_price']);
            } elseif (isset($found_item['hpo_custom_data']['custom_price'])) {
                $price = floatval($found_item['hpo_custom_data']['custom_price']);
            }
            
            // Calculate line total based on quantity
            if ($price > 0) {
                $quantity = $found_item['quantity'];
                $line_total = $price * $quantity;
                
                // Format using WooCommerce's currency formatter
                return wc_price($line_total);
            }
        }
        
        return $subtotal;
    }
    
    /**
     * Update cart subtotal in checkout
     *
     * @param string $cart_subtotal Formatted cart subtotal
     * @param bool $compound Whether subtotal includes compound taxes
     * @param WC_Cart $cart Cart object
     * @return string Modified cart subtotal
     */
    public function update_cart_subtotal($cart_subtotal, $compound, $cart) {
        // Calculate our own subtotal for all items
        $subtotal = 0;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $price = 0;
            $quantity = $cart_item['quantity'];
            
            // Get price based on our custom data if available
            if (isset($cart_item['hpo_custom_data'])) {
                if (isset($cart_item['hpo_custom_data']['price_per_unit'])) {
                    $price = floatval($cart_item['hpo_custom_data']['price_per_unit']);
                } elseif (isset($cart_item['hpo_custom_data']['calculated_price'])) {
                    $price = floatval($cart_item['hpo_custom_data']['calculated_price']);
                } elseif (isset($cart_item['hpo_custom_data']['custom_price'])) {
                    $price = floatval($cart_item['hpo_custom_data']['custom_price']);
                }
            } else {
                // For regular products, use the product price
                $price = floatval($cart_item['data']->get_price());
            }
            
            // Add to subtotal with quantity
            $subtotal += $price * $quantity;
        }
        
        // Only change if our subtotal is valid
        if ($subtotal > 0) {
            // Store the original subtotal so WooCommerce can properly calculate discount savings display
            if (!$compound) {
                $cart->subtotal = $subtotal;
            }
            return wc_price($subtotal);
        }
        
        return $cart_subtotal;
    }
    
    /**
     * Update order subtotal in checkout and thank you pages
     *
     * @param string $subtotal Formatted subtotal
     * @param WC_Order $order Order object
     * @param array $args Display args
     * @return string Modified subtotal
     */
    public function update_order_subtotal($subtotal, $order, $args) {
        // For checkout page, use the cart data
        if (is_checkout() && !is_wc_endpoint_url('order-received') && WC()->cart) {
            return $this->update_cart_subtotal($subtotal, false, WC()->cart);
        }
        
        return $subtotal;
    }
    
    /**
     * Update the calculated total in checkout
     *
     * @param float $total Calculated total
     * @param WC_Cart $cart Cart object
     * @return float Modified total
     */
    public function update_calculated_total($total, $cart) {
        static $is_calculating = false;
        
        // جلوگیری از فراخوانی‌های بازگشتی
        if ($is_calculating) {
            return $total;
        }
        
        $is_calculating = true;
        
        try {
            // Calculate our own subtotal
            $subtotal = 0;
            
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $price = 0;
                $quantity = $cart_item['quantity'];
                
                // Get price based on our custom data if available
                if (isset($cart_item['hpo_custom_data'])) {
                    if (isset($cart_item['hpo_custom_data']['price_per_unit'])) {
                        $price = floatval($cart_item['hpo_custom_data']['price_per_unit']);
                    } elseif (isset($cart_item['hpo_custom_data']['calculated_price'])) {
                        $price = floatval($cart_item['hpo_custom_data']['calculated_price']);
                    } elseif (isset($cart_item['hpo_custom_data']['custom_price'])) {
                        $price = floatval($cart_item['hpo_custom_data']['custom_price']);
                    }
                } else {
                    // For regular products, use the product price
                    $price = floatval($cart_item['data']->get_price());
                }
                
                // Add to subtotal with quantity
                $subtotal += $price * $quantity;
            }
            
            // If our subtotal is valid, add shipping, fees, and taxes to it
            if ($subtotal > 0) {
                $new_total = $subtotal;
                
                // Get applied coupons
                $applied_coupons = $cart->get_applied_coupons();
                $discount_total = 0;
                
                // Calculate discount manually for percentage coupons
                if (!empty($applied_coupons)) {
                    foreach ($applied_coupons as $coupon_code) {
                        $coupon = new WC_Coupon($coupon_code);
                        
                        if ($coupon->get_discount_type() === 'percent') {
                            // For percentage coupons, recalculate based on our correct subtotal
                            $percent = $coupon->get_amount();
                            $discount_amount = ($subtotal * $percent) / 100;
                            $discount_total += $discount_amount;
                        } else {
                            // For fixed amount coupons, use WooCommerce's calculation
                            $discount_total = $cart->get_discount_total();
                            break;
                        }
                    }
                } else {
                    // No coupons
                    $discount_total = $cart->get_discount_total();
                }
                
                // Apply the discount
                $new_total -= $discount_total;
                
                // Add other components
                $new_total += $cart->get_shipping_total();
                $new_total += $cart->get_fee_total();
                $new_total += $cart->get_total_tax();
                
                // Update discount total in cart for consistency
                $cart->set_discount_total($discount_total);
                
                // فقط اگر اختلاف قابل توجهی وجود داشته باشد، مقدار را تغییر دهیم
                if (abs($new_total - $total) > 1) {
                    return $new_total;
                }
            }
            
            return $total;
        } finally {
            $is_calculating = false;
        }
    }

    /**
     * Fix coupon discount amount for percentage coupons
     * 
     * @param float $discount
     * @param float $discounting_amount
     * @param object $cart_item
     * @param bool $single
     * @param WC_Coupon $coupon
     * @return float
     */
    public function fix_coupon_discount_amount($discount, $discounting_amount, $cart_item, $single, $coupon) {
        // Only fix percentage coupons
        if ($coupon->get_discount_type() !== 'percent') {
            return $discount;
        }
        
        // Check if we have HPO items in cart
        $hpo_item_count = 0;
        if (function_exists('WC') && WC()->session) {
            $hpo_item_count = (int) WC()->session->get('hpo_item_count', 0);
        }
        
        // Only handle when we have multiple HPO items (3 or more)
        if ($hpo_item_count < 3 || !isset($cart_item['hpo_custom_data'])) {
            return $discount;
        }
        
        // Get the correct price for this item
        $price = 0;
        if (isset($cart_item['hpo_custom_data']['price_per_unit'])) {
            $price = floatval($cart_item['hpo_custom_data']['price_per_unit']);
        } elseif (isset($cart_item['hpo_custom_data']['calculated_price'])) {
            $price = floatval($cart_item['hpo_custom_data']['calculated_price']);
        } elseif (isset($cart_item['hpo_custom_data']['custom_price'])) {
            $price = floatval($cart_item['hpo_custom_data']['custom_price']);
        }
        
        if ($price <= 0) {
            return $discount;
        }
        
        // Calculate line total
        $line_total = $price * $cart_item['quantity'];
        
        // Calculate the correct percentage discount
        $percentage = $coupon->get_amount();
        $correct_discount = ($line_total * $percentage) / 100;
        
        return $correct_discount;
    }

    /**
     * Fix the display of the coupon discount amount in cart
     *
     * @param string $coupon_html The coupon HTML
     * @param object $coupon The coupon object
     * @param string $discount_amount_html The discount amount HTML
     * @return string Modified coupon HTML
     */
    public function fix_coupon_display_in_cart($coupon_html, $coupon, $discount_amount_html) {
        // Only fix percentage coupons
        if ($coupon->get_discount_type() !== 'percent') {
            return $coupon_html;
        }
        
        // Calculate our own cart subtotal
        $subtotal = 0;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $price = 0;
            $quantity = $cart_item['quantity'];
            
            // Get price based on our custom data if available
            if (isset($cart_item['hpo_custom_data'])) {
                if (isset($cart_item['hpo_custom_data']['price_per_unit'])) {
                    $price = floatval($cart_item['hpo_custom_data']['price_per_unit']);
                } elseif (isset($cart_item['hpo_custom_data']['calculated_price'])) {
                    $price = floatval($cart_item['hpo_custom_data']['calculated_price']);
                } elseif (isset($cart_item['hpo_custom_data']['custom_price'])) {
                    $price = floatval($cart_item['hpo_custom_data']['custom_price']);
                }
            } else {
                // For regular products, use the product price
                $price = floatval($cart_item['data']->get_price());
            }
            
            // Add to subtotal with quantity
            $subtotal += $price * $quantity;
        }
        
        // Calculate correct discount amount
        $percent = $coupon->get_amount();
        $correct_discount = ($subtotal * $percent) / 100;
        
        // Format the discount amount to match WooCommerce standard format (no minus sign)
        $formatted_discount = wc_price($correct_discount, array('decimals' => 0));
        
        // For checkout page, keep the HTML structure but replace the amount
        if (is_checkout()) {
            // Find the existing amount in the format and replace it
            $pattern = '/<span class="woocommerce-Price-amount amount">(.*?)<\/span>/i';
            if (preg_match($pattern, $discount_amount_html, $matches)) {
                $new_discount_html = str_replace($matches[1], $formatted_discount, $discount_amount_html);
                return str_replace($discount_amount_html, $new_discount_html, $coupon_html);
            }
            
            // If we can't find the pattern, just return the formatted discount
            return $formatted_discount;
        }
        
        // For cart page, keep the HTML structure including the remove link
        $pattern = '/<span class="woocommerce-Price-amount amount">(.*?)<\/span>/i';
        if (preg_match($pattern, $discount_amount_html, $matches)) {
            $new_discount_html = str_replace($matches[1], $formatted_discount, $discount_amount_html);
            return str_replace($discount_amount_html, $new_discount_html, $coupon_html);
        }
        
        // If we couldn't match the pattern, use our CSS to handle the minus sign
        return '<span class="woocommerce-Price-amount amount">' . $formatted_discount . '</span>';
    }

    /**
     * Update cart item subtotal
     */
    public function update_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        $price_value = 0;
        
        if (isset($cart_item['hpo_custom_data'])) {
            // Get price for our custom products
            if (isset($cart_item['hpo_custom_data']['price_per_unit'])) {
                $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
            } elseif (isset($cart_item['hpo_custom_data']['calculated_price'])) {
                $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
            } elseif (isset($cart_item['hpo_custom_data']['custom_price'])) {
                $price_value = floatval($cart_item['hpo_custom_data']['custom_price']);
            }
        } else {
            // Get price for regular WooCommerce products
            if (isset($cart_item['data'])) {
                $price_value = floatval($cart_item['data']->get_price());
            }
        }

        if ($price_value > 0) {
            $quantity = isset($cart_item['quantity']) ? (int)$cart_item['quantity'] : 1;
            $total = $price_value * $quantity;

            // Update the cart item data to ensure consistency
            if (isset(WC()->cart->cart_contents[$cart_item_key])) {
                WC()->cart->cart_contents[$cart_item_key]['line_total'] = $total;
                WC()->cart->cart_contents[$cart_item_key]['line_subtotal'] = $total;
            }

            return wc_price($total);
        }

        return $subtotal;
    }

    /**
     * Update checkout line total
     */
    public function update_checkout_line_total($subtotal, $cart_item, $cart_item_key = '') {
        $price_value = 0;
        
        if (isset($cart_item['hpo_custom_data'])) {
            // Get price for our custom products
            if (isset($cart_item['hpo_custom_data']['price_per_unit'])) {
                $price_value = floatval($cart_item['hpo_custom_data']['price_per_unit']);
            } elseif (isset($cart_item['hpo_custom_data']['calculated_price'])) {
                $price_value = floatval($cart_item['hpo_custom_data']['calculated_price']);
            } elseif (isset($cart_item['hpo_custom_data']['custom_price'])) {
                $price_value = floatval($cart_item['hpo_custom_data']['custom_price']);
            }
        } else {
            // Get price for regular WooCommerce products
            if (isset($cart_item['data'])) {
                $price_value = floatval($cart_item['data']->get_price());
            }
        }

        if ($price_value > 0) {
            $quantity = isset($cart_item['quantity']) ? (int)$cart_item['quantity'] : 1;
            $total = $price_value * $quantity;

            // Update the cart item data
            if (isset(WC()->cart->cart_contents[$cart_item_key])) {
                WC()->cart->cart_contents[$cart_item_key]['line_total'] = $total;
                WC()->cart->cart_contents[$cart_item_key]['line_subtotal'] = $total;
                WC()->cart->cart_contents[$cart_item_key]['line_tax'] = 0;
                WC()->cart->cart_contents[$cart_item_key]['line_subtotal_tax'] = 0;
            }

            // Format with WooCommerce price format
            return sprintf(
                '<span class="woocommerce-Price-amount amount"><bdi>%s&nbsp;<span class="woocommerce-Price-currencySymbol">تومان</span></bdi></span>',
                number_format($total)
            );
        }

        return $subtotal;
    }
}

// Initialize the shortcodes
new HPO_Shortcodes(); 