<?php
/**
 * Admin page display
 *
 * @since      1.0.0
 */
?>
<div class="wrap hpo-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <nav class="nav-tab-wrapper">
        <a href="#tab-categories" class="nav-tab nav-tab-active"><?php _e('دسته‌بندی‌ها', 'hierarchical-product-options'); ?></a>
        <a href="#tab-products" class="nav-tab"><?php _e('محصولات', 'hierarchical-product-options'); ?></a>
        <a href="#tab-weights" class="nav-tab"><?php _e('وزن‌ها', 'hierarchical-product-options'); ?></a>
        <a href="#tab-grinders" class="nav-tab"><?php _e('آسیاب‌ها', 'hierarchical-product-options'); ?></a>
        <a href="#tab-settings" class="nav-tab"><?php _e('تنظیمات', 'hierarchical-product-options'); ?></a>
    </nav>
    
    <div id="tab-categories" class="tab-content">
        <div class="hpo-admin-container">
            <div class="hpo-admin-panel">
                <h2><?php echo esc_html__('دسته‌بندی‌ها', 'hierarchical-product-options'); ?></h2>
                
                <div class="hpo-actions">
                    <button class="button button-primary hpo-add-category"><?php echo esc_html__('افزودن دسته‌بندی', 'hierarchical-product-options'); ?></button>
                </div>
                
                <div class="hpo-items-container">
                    <?php if (empty($top_level_categories)): ?>
                    <p><?php echo esc_html__('هیچ دسته‌بندی یافت نشد. اولین دسته‌بندی خود را ایجاد کنید.', 'hierarchical-product-options'); ?></p>
                    <?php else: ?>
                    <ul class="hpo-categories-list" id="hpo-sortable-categories">
                        <?php $this->render_categories_recursive($top_level_categories); ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="hpo-admin-panel">
                <h2><?php echo esc_html__('تخصیص محصول', 'hierarchical-product-options'); ?></h2>
                
                <p><?php echo esc_html__('دسته‌بندی‌ها را به محصولات ووکامرس اختصاص دهید.', 'hierarchical-product-options'); ?></p>
                <p><strong><?php echo esc_html__('توجه:', 'hierarchical-product-options'); ?></strong></p>
                <ul>
                    <li><?php echo esc_html__('هنگامی که یک دسته‌بندی والد را انتخاب می‌کنید، تمام دسته‌بندی‌های فرزند آن به طور خودکار به محصول اختصاص داده می‌شوند.', 'hierarchical-product-options'); ?></li>
                    <li><?php echo esc_html__('شما می‌توانید چندین دسته‌بندی را به یک محصول اختصاص دهید، به شرطی که در یک سلسله مراتب نباشند.', 'hierarchical-product-options'); ?></li>
                </ul>
                
                <form id="hpo-assign-form">
                    <div class="hpo-form-row">
                        <label for="hpo-wc-product"><?php echo esc_html__('محصول ووکامرس', 'hierarchical-product-options'); ?></label>
                        <select id="hpo-wc-product" name="wc_product_id" required>
                            <option value=""><?php echo esc_html__('یک محصول انتخاب کنید...', 'hierarchical-product-options'); ?></option>
                            <?php
                            $products = wc_get_products(array('limit' => -1));
                            foreach ($products as $product): ?>
                            <option value="<?php echo esc_attr($product->get_id()); ?>"><?php echo esc_html($product->get_name()); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="hpo-form-row">
                        <label for="hpo-category-select"><?php echo esc_html__('یک دسته‌بندی مادر انتخاب کنید', 'hierarchical-product-options'); ?></label>
                        <p class="description"><?php echo esc_html__('فقط دسته‌بندی‌های مادر نمایش داده می‌شوند. انتخاب یک دسته‌بندی مادر به طور خودکار تمام زیردسته‌های آن را نیز شامل می‌شود.', 'hierarchical-product-options'); ?></p>
                        <select id="hpo-category-select" name="category_id" required>
                            <option value=""><?php echo esc_html__('یک دسته‌بندی مادر انتخاب کنید...', 'hierarchical-product-options'); ?></option>
                            <?php foreach ($categories as $category): ?>
                            <?php if ($category->parent_id == 0): ?>
                            <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="hpo-form-row">
                        <button type="submit" class="button button-primary"><?php echo esc_html__('اختصاص به محصول', 'hierarchical-product-options'); ?></button>
                    </div>
                </form>
            </div>

            <div class="hpo-admin-panel">
                <h2><?php echo esc_html__('تخصیص‌های دسته‌بندی', 'hierarchical-product-options'); ?></h2>
                <div class="hpo-assignments-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('محصول ووکامرس', 'hierarchical-product-options'); ?></th>
                                <th><?php echo esc_html__('دسته‌بندی‌های اختصاص داده شده', 'hierarchical-product-options'); ?></th>
                                <th class="hpo-actions-column"><?php echo esc_html__('اقدامات', 'hierarchical-product-options'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $assignments = $db->get_category_product_assignments();
                            
                            if (empty($assignments)): 
                            ?>
                            <tr>
                                <td colspan="3"><?php echo esc_html__('هیچ تخصیص دسته‌بندی یافت نشد.', 'hierarchical-product-options'); ?></td>
                            </tr>
                            <?php 
                            else:
                                // Group assignments by product
                                $products = array();
                                foreach ($assignments as $assignment) {
                                    // Get category name - this was missing before
                                    $category = $db->get_category($assignment->category_id);
                                    if ($category) {
                                        $assignment->category_name = $category->name;
                                        $assignment->parent_id = $category->parent_id;
                                    } else {
                                        $assignment->category_name = __('نامشخص', 'hierarchical-product-options');
                                        $assignment->parent_id = 0;
                                    }
                                    
                                    if (!isset($products[$assignment->wc_product_id])) {
                                        $products[$assignment->wc_product_id] = array(
                                            'product' => wc_get_product($assignment->wc_product_id),
                                            'categories' => array()
                                        );
                                    }
                                    
                                    $is_child = isset($assignment->parent_id) && $assignment->parent_id > 0;
                                    
                                    // Only add parent categories or categories without parent to the main list
                                    if (!$is_child) {
                                        $products[$assignment->wc_product_id]['categories'][] = $assignment;
                                    }
                                }
                                
                                foreach ($products as $product_id => $data):
                                    if (!$data['product']) continue;
                                    
                                    foreach ($data['categories'] as $index => $assignment):
                            ?>
                            <tr>
                                <?php if ($index === 0): // Show product name only in the first row ?>
                                <td rowspan="<?php echo count($data['categories']); ?>">
                                    <?php echo esc_html($data['product']->get_name()); ?>
                                    <div class="hpo-product-description-wrapper">
                                        <?php 
                                        $description = !empty($data['categories'][0]->short_description) ? 
                                                      $data['categories'][0]->short_description : 
                                                      __('برای افزودن توضیحات کلیک کنید', 'hierarchical-product-options');
                                        
                                        $desc_class = empty($data['categories'][0]->short_description) ? 'hpo-desc-text no-description' : 'hpo-desc-text';
                                        ?>
                                        <div class="<?php echo esc_attr($desc_class); ?>"><?php echo esc_html($description); ?></div>
                                        <button class="button button-small hpo-edit-description" 
                                                data-id="<?php echo esc_attr($data['categories'][0]->id); ?>" 
                                                data-product-id="<?php echo esc_attr($data['categories'][0]->wc_product_id); ?>"
                                                data-description="<?php echo esc_attr($data['categories'][0]->short_description); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                    </div>
                                </td>
                                <?php endif; ?>
                                
                                <td>
                                    <?php 
                                        echo esc_html($assignment->category_name);
                                        
                                        // Get child categories for this parent
                                        $child_categories = array();
                                        foreach ($assignments as $child) {
                                            if (isset($child->parent_id) && $child->parent_id == $assignment->category_id) {
                                                $child_categories[] = $child->category_name;
                                            }
                                        }
                                        
                                        if (!empty($child_categories)) {
                                            echo '<div class="hpo-child-categories-list">';
                                            echo '<small>' . esc_html__('شامل:', 'hierarchical-product-options') . ' ';
                                            echo esc_html(implode('، ', $child_categories));
                                            echo '</small>';
                                            echo '</div>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <button class="button button-small hpo-delete-assignment" data-category-id="<?php echo esc_attr($assignment->category_id); ?>" data-product-id="<?php echo esc_attr($assignment->wc_product_id); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </td>
                            </tr>
                            <?php 
                                    endforeach;
                                endforeach;
                            endif; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div id="tab-products" class="tab-content" style="display: none;">
        <!-- Existing products tab content -->
        <!-- ... -->
    </div>
    
    <div id="tab-weights" class="tab-content" style="display: none;">
        <?php include 'admin-weights.php'; ?>
    </div>
    
    <div id="tab-grinders" class="tab-content" style="display: none;">
        <h2><?php _e('Manage Grinder Options', 'hierarchical-product-options'); ?></h2>
        <p><?php _e('Add and manage grinding machines with their associated costs.', 'hierarchical-product-options'); ?></p>

        <div class="hpo-admin-columns">
            <div class="hpo-main-column">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="hpo-handle-column"><?php _e('Order', 'hierarchical-product-options'); ?></th>
                            <th><?php _e('ID', 'hierarchical-product-options'); ?></th>
                            <th><?php _e('Name', 'hierarchical-product-options'); ?></th>
                            <th><?php _e('Price', 'hierarchical-product-options'); ?></th>
                            <th class="hpo-actions-column"><?php _e('Actions', 'hierarchical-product-options'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="hpo-grinders-list">
                        <tr class="hpo-empty-row">
                            <td colspan="5"><?php _e('No grinder options found', 'hierarchical-product-options'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="hpo-side-column">
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Add New Grinder Option', 'hierarchical-product-options'); ?></h2>
                    <div class="inside">
                        <form id="hpo-add-grinder-form">
                            <div class="form-field">
                                <label for="hpo-grinder-name"><?php _e('Name', 'hierarchical-product-options'); ?></label>
                                <input type="text" id="hpo-grinder-name" name="name" required>
                            </div>
                            <div class="form-field">
                                <label for="hpo-grinder-price"><?php _e('Price', 'hierarchical-product-options'); ?></label>
                                <input type="number" id="hpo-grinder-price" name="price" step="0.01" min="0" value="0">
                            </div>
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php _e('Add Grinder Option', 'hierarchical-product-options'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="tab-settings" class="tab-content" style="display: none;">
        <!-- Existing settings tab content -->
        <div class="hpo-admin-panel">
            <h2><?php echo esc_html__('Settings', 'hierarchical-product-options'); ?></h2>
            
            <div class="hpo-form-row">
                <h3><?php echo esc_html__('Troubleshooting', 'hierarchical-product-options'); ?></h3>
                <p><?php echo esc_html__('If you are experiencing issues with database tables, you can rebuild them here.', 'hierarchical-product-options'); ?></p>
                <button id="hpo-rebuild-tables" class="button button-secondary"><?php echo esc_html__('Rebuild Database Tables', 'hierarchical-product-options'); ?></button>
                <div id="hpo-rebuild-result" style="margin-top: 10px; display: none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Templates for adding new items -->
<div id="hpo-templates" style="display: none;">
    <div id="hpo-new-category-template">
        <form class="hpo-item-form">
            <div class="hpo-form-row">
                <label for="hpo-category-name"><?php echo esc_html__('Category Name', 'hierarchical-product-options'); ?></label>
                <input type="text" id="hpo-category-name" name="name" required>
            </div>
            
            <div class="hpo-form-row">
                <label for="hpo-category-parent"><?php echo esc_html__('Parent Category', 'hierarchical-product-options'); ?></label>
                <select id="hpo-category-parent" name="parent_id">
                    <option value="0"><?php echo esc_html__('None (Top Level)', 'hierarchical-product-options'); ?></option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="hpo-form-row">
                <button type="submit" class="button button-primary"><?php echo esc_html__('Add Category', 'hierarchical-product-options'); ?></button>
                <button type="button" class="button hpo-cancel"><?php echo esc_html__('Cancel', 'hierarchical-product-options'); ?></button>
            </div>
        </form>
    </div>
    
    <div id="hpo-new-product-template">
        <form class="hpo-item-form">
            <div class="hpo-form-row">
                <label for="hpo-product-name"><?php echo esc_html__('Product Name', 'hierarchical-product-options'); ?></label>
                <input type="text" id="hpo-product-name" name="name" required>
            </div>
            
            <div class="hpo-form-row">
                <label for="hpo-product-price"><?php echo esc_html__('Price', 'hierarchical-product-options'); ?></label>
                <input type="number" id="hpo-product-price" name="price" step="0.01" min="0" required>
            </div>
            
            <div class="hpo-form-row">
                <label for="hpo-product-description"><?php echo esc_html__('Description', 'hierarchical-product-options'); ?></label>
                <textarea id="hpo-product-description" name="description" rows="3" maxlength="53" placeholder="<?php echo esc_attr__('Enter product description (max 53 characters)', 'hierarchical-product-options'); ?>"></textarea>
                <div class="hpo-limit-info">حداکثر 53 کاراکتر مجاز است</div>
            </div>
            
            <div class="hpo-form-row">
                <label for="hpo-product-category"><?php echo esc_html__('Category', 'hierarchical-product-options'); ?></label>
                <select id="hpo-product-category" name="category_id" required>
                    <option value=""><?php echo esc_html__('Select a category...', 'hierarchical-product-options'); ?></option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="hpo-form-row">
                <button type="submit" class="button button-primary"><?php echo esc_html__('Add Product', 'hierarchical-product-options'); ?></button>
                <button type="button" class="button hpo-cancel"><?php echo esc_html__('Cancel', 'hierarchical-product-options'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Weight Option Template -->
<script type="text/template" id="hpo-weight-template">
    <div class="hpo-weight-item" data-id="{id}">
        <div class="hpo-weight-header">
            <span class="hpo-weight-name">{name}</span>
            <span class="hpo-weight-coefficient"><?php echo esc_html__('Coefficient:', 'hierarchical-product-options'); ?> {coefficient}</span>
            <div class="hpo-weight-actions">
                <a href="#" class="hpo-edit-weight"><?php echo esc_html__('Edit', 'hierarchical-product-options'); ?></a>
                <a href="#" class="hpo-delete-weight"><?php echo esc_html__('Delete', 'hierarchical-product-options'); ?></a>
            </div>
        </div>
    </div>
</script>

<!-- New Weight Option Form Template -->
<script type="text/template" id="hpo-new-weight-template">
    <div class="hpo-modal-content">
        <h3><?php echo esc_html__('Add Weight Option', 'hierarchical-product-options'); ?></h3>
        <form class="hpo-weight-form">
            <div class="hpo-form-row">
                <label for="weight-name"><?php echo esc_html__('Name', 'hierarchical-product-options'); ?></label>
                <input type="text" id="weight-name" name="name" required placeholder="<?php echo esc_attr__('e.g., 100 grams', 'hierarchical-product-options'); ?>">
            </div>
            <div class="hpo-form-row">
                <label for="weight-coefficient"><?php echo esc_html__('Coefficient', 'hierarchical-product-options'); ?></label>
                <input type="number" id="weight-coefficient" name="coefficient" step="0.01" min="0.01" value="1" required>
                <p class="description"><?php echo esc_html__('The product price will be multiplied by this value when this weight is selected.', 'hierarchical-product-options'); ?></p>
            </div>
            <input type="hidden" name="wc_product_id" value="">
            <div class="hpo-form-row">
                <button type="submit" class="button button-primary"><?php echo esc_html__('Add', 'hierarchical-product-options'); ?></button>
                <button type="button" class="button hpo-cancel"><?php echo esc_html__('Cancel', 'hierarchical-product-options'); ?></button>
            </div>
        </form>
    </div>
</script>

<?php
/**
 * Helper function to render categories recursively
 */
if (!method_exists($this, 'render_categories_recursive')) {
    function render_categories_recursive($categories) {
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
                <?php render_categories_recursive($category->children); ?>
            </ul>
            <?php endif; ?>
        </li>
        <?php
        endforeach;
    }
} 