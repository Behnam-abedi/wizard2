<div class="hpo-admin-panel">
    <h2><?php echo esc_html__('Product Weight Options', 'hierarchical-product-options'); ?></h2>
    
    <p><?php echo esc_html__('Define weight options for products. Each weight option has a name (e.g., "100 grams") and a coefficient. The product price will be multiplied by this coefficient when this weight is selected.', 'hierarchical-product-options'); ?></p>
    
    <form id="hpo-weights-form">
        <div class="hpo-form-row">
            <label for="hpo-weight-product"><?php echo esc_html__('WooCommerce Product', 'hierarchical-product-options'); ?></label>
            <select id="hpo-weight-product" name="wc_product_id">
                <option value=""><?php echo esc_html__('Select a product...', 'hierarchical-product-options'); ?></option>
                <?php
                $products = wc_get_products(array('limit' => -1));
                foreach ($products as $product): ?>
                <option value="<?php echo esc_attr($product->get_id()); ?>"><?php echo esc_html($product->get_name()); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="hpo-weights-container" style="display: none;">
            <h3><?php echo esc_html__('Weight Options for Selected Product', 'hierarchical-product-options'); ?></h3>

            <div class="hpo-weight-list">
                <!-- Weight options will be populated via JavaScript -->
            </div>

            <div class="hpo-form-row">
                <button type="button" id="hpo-add-weight" class="button button-secondary"><?php echo esc_html__('Add Weight Option', 'hierarchical-product-options'); ?></button>
            </div>
        </div>
    </form>
</div> 