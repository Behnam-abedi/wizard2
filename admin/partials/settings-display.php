<?php
/**
 * Settings page display
 *
 * @since      1.0.0
 */
?>
<div class="wrap">
    <h1><?php echo esc_html__('Hierarchical Product Options Settings', 'hierarchical-product-options'); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('hierarchical-product-options-settings-group');
        $settings = wp_parse_args($settings, array(
            'display_mode' => 'accordion',
            'update_price' => 'yes',
            'price_display' => 'next_to_option'
        ));
        ?>
        
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Display Mode', 'hierarchical-product-options'); ?></th>
                <td>
                    <select name="hpo_settings[display_mode]">
                        <option value="accordion" <?php selected($settings['display_mode'], 'accordion'); ?>>
                            <?php echo esc_html__('Accordion', 'hierarchical-product-options'); ?>
                        </option>
                        <option value="tabs" <?php selected($settings['display_mode'], 'tabs'); ?>>
                            <?php echo esc_html__('Tabs', 'hierarchical-product-options'); ?>
                        </option>
                        <option value="flat" <?php selected($settings['display_mode'], 'flat'); ?>>
                            <?php echo esc_html__('Flat List', 'hierarchical-product-options'); ?>
                        </option>
                    </select>
                    <p class="description"><?php echo esc_html__('How the options should be displayed on the product page.', 'hierarchical-product-options'); ?></p>
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Update Product Price', 'hierarchical-product-options'); ?></th>
                <td>
                    <select name="hpo_settings[update_price]">
                        <option value="yes" <?php selected($settings['update_price'], 'yes'); ?>>
                            <?php echo esc_html__('Yes', 'hierarchical-product-options'); ?>
                        </option>
                        <option value="no" <?php selected($settings['update_price'], 'no'); ?>>
                            <?php echo esc_html__('No', 'hierarchical-product-options'); ?>
                        </option>
                    </select>
                    <p class="description"><?php echo esc_html__('Whether to update the product price when a product option is selected.', 'hierarchical-product-options'); ?></p>
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Price Display', 'hierarchical-product-options'); ?></th>
                <td>
                    <select name="hpo_settings[price_display]">
                        <option value="next_to_option" <?php selected($settings['price_display'], 'next_to_option'); ?>>
                            <?php echo esc_html__('Next to option', 'hierarchical-product-options'); ?>
                        </option>
                        <option value="hide" <?php selected($settings['price_display'], 'hide'); ?>>
                            <?php echo esc_html__('Hide', 'hierarchical-product-options'); ?>
                        </option>
                    </select>
                    <p class="description"><?php echo esc_html__('How to display the price of each option.', 'hierarchical-product-options'); ?></p>
                </td>
            </tr>
        </table>
        
        <div class="hpo-form-row">
            <h3><?php echo esc_html__('رفع مشکلات', 'hierarchical-product-options'); ?></h3>
            <p><?php echo esc_html__('اگر با مشکلاتی در جداول پایگاه داده مواجه هستید، می‌توانید آنها را در اینجا بازسازی کنید.', 'hierarchical-product-options'); ?></p>
            <button id="hpo-rebuild-tables" class="button button-secondary"><?php echo esc_html__('بازسازی جداول پایگاه داده', 'hierarchical-product-options'); ?></button>
            <div id="hpo-rebuild-result" style="margin-top: 10px; display: none;"></div>
        </div>
        
        <div class="hpo-form-row" style="margin-top:30px; padding-top:20px; border-top:1px solid #eee;">
            <h3><?php echo esc_html__('پاکسازی داده‌های ناسازگار', 'hierarchical-product-options'); ?></h3>
            <p><?php echo esc_html__('اگر دسته‌بندی‌های حذف شده هنوز در لیست‌ها نمایش داده می‌شوند یا با مشکلات ناسازگاری داده مواجه هستید، می‌توانید داده‌های ناسازگار را پاکسازی کنید.', 'hierarchical-product-options'); ?></p>
            <button id="hpo-clean-data" class="button button-secondary"><?php echo esc_html__('پاکسازی داده‌های ناسازگار', 'hierarchical-product-options'); ?></button>
            <div id="hpo-clean-result" style="margin-top: 10px; display: none;"></div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div> 