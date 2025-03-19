<?php
/**
 * Admin page display
 *
 * @since      1.0.0
 */
?>
<div class="wrap">
    <h1><?php echo esc_html__('Hierarchical Product Options', 'hierarchical-product-options'); ?></h1>
    
    <div class="hpo-admin-container">
        <div class="hpo-admin-panel">
            <h2><?php echo esc_html__('Categories and Products', 'hierarchical-product-options'); ?></h2>
            
            <div class="hpo-actions">
                <button class="button button-primary hpo-add-category"><?php echo esc_html__('Add Category', 'hierarchical-product-options'); ?></button>
                <button class="button hpo-add-product"><?php echo esc_html__('Add Product', 'hierarchical-product-options'); ?></button>
            </div>
            
            <div class="hpo-items-container">
                <?php if (empty($top_level_categories)): ?>
                <p><?php echo esc_html__('No categories found. Create your first category to get started.', 'hierarchical-product-options'); ?></p>
                <?php else: ?>
                <ul class="hpo-categories-list" id="hpo-sortable-categories">
                    <?php $this->render_categories_recursive($top_level_categories); ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="hpo-admin-panel">
            <h2><?php echo esc_html__('Assignments', 'hierarchical-product-options'); ?></h2>
            
            <p><?php echo esc_html__('Assign option categories to WooCommerce products.', 'hierarchical-product-options'); ?></p>
            
            <form id="hpo-assign-form">
                <div class="hpo-form-row">
                    <label for="hpo-wc-product"><?php echo esc_html__('WooCommerce Product', 'hierarchical-product-options'); ?></label>
                    <select id="hpo-wc-product" name="wc_product_id">
                        <option value=""><?php echo esc_html__('Select a product...', 'hierarchical-product-options'); ?></option>
                        <?php
                        $products = wc_get_products(array('limit' => -1));
                        foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->get_id()); ?>"><?php echo esc_html($product->get_name()); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="hpo-form-row">
                    <label><?php echo esc_html__('Assigned Categories', 'hierarchical-product-options'); ?></label>
                    <div class="hpo-checkbox-list">
                        <?php foreach ($categories as $category): ?>
                        <div class="hpo-checkbox-item">
                            <label>
                                <input type="checkbox" name="category_ids[]" value="<?php echo esc_attr($category->id); ?>">
                                <?php echo esc_html($category->name); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="hpo-form-row">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Assignments', 'hierarchical-product-options'); ?></button>
                </div>
            </form>
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
</div>

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