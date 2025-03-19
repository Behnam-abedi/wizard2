<?php
/**
 * Display product options on the product page
 *
 * @since      1.0.0
 */
$settings = get_option('hpo_settings', array(
    'display_mode' => 'accordion',
    'update_price' => 'yes',
    'price_display' => 'next_to_option'
));
?>
<div class="hpo-options-container" data-update-price="<?php echo esc_attr($settings['update_price']); ?>">
    <h3><?php echo esc_html__('Product Options', 'hierarchical-product-options'); ?></h3>
    
    <?php if ($settings['display_mode'] === 'accordion'): ?>
    
    <div class="hpo-accordion">
        <?php foreach ($options as $category): ?>
        <div class="hpo-accordion-item">
            <div class="hpo-accordion-header">
                <span class="hpo-accordion-title"><?php echo esc_html($category->name); ?></span>
                <span class="hpo-accordion-icon"></span>
            </div>
            <div class="hpo-accordion-content">
                <?php hpo_render_category_content($category, $settings); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php elseif ($settings['display_mode'] === 'tabs'): ?>
    
    <div class="hpo-tabs">
        <div class="hpo-tabs-header">
            <?php foreach ($options as $index => $category): ?>
            <div class="hpo-tab-button <?php echo $index === 0 ? 'active' : ''; ?>" data-tab="tab-<?php echo esc_attr($category->id); ?>">
                <?php echo esc_html($category->name); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="hpo-tabs-content">
            <?php foreach ($options as $index => $category): ?>
            <div class="hpo-tab-content <?php echo $index === 0 ? 'active' : ''; ?>" id="tab-<?php echo esc_attr($category->id); ?>">
                <?php hpo_render_category_content($category, $settings); ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php else: // Flat list ?>
    
    <div class="hpo-flat-list">
        <?php foreach ($options as $category): ?>
        <div class="hpo-category-section">
            <h4><?php echo esc_html($category->name); ?></h4>
            <?php hpo_render_category_content($category, $settings); ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
    
    <div class="hpo-selected-options">
        <input type="hidden" name="hpo_selected_option" id="hpo-selected-option" value="">
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Accordion functionality
    $('.hpo-accordion-header').on('click', function() {
        $(this).next('.hpo-accordion-content').slideToggle();
        $(this).parent().toggleClass('active');
    });
    
    // Tab functionality
    $('.hpo-tab-button').on('click', function() {
        var tabId = $(this).data('tab');
        
        $('.hpo-tab-button').removeClass('active');
        $(this).addClass('active');
        
        $('.hpo-tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });
    
    // Show first accordion item by default
    $('.hpo-accordion-item:first-child .hpo-accordion-content').show();
    $('.hpo-accordion-item:first-child').addClass('active');
    
    // Handle radio button selection
    $('.hpo-product-option').on('change', function() {
        var optionId = $(this).val();
        var optionPrice = $(this).data('price');
        var optionName = $(this).data('name');
        
        $('#hpo-selected-option').val(optionId);
        
        // Update product price if enabled
        if ($('.hpo-options-container').data('update-price') === 'yes') {
            updateProductPrice(optionPrice);
        }
    });
    
    // Function to update product price
    function updateProductPrice(price) {
        // Get the original price
        var originalPrice = $('input[name="hpo_original_price"]').val();
        if (!originalPrice) {
            // Store original price first time - properly extract numeric value
            var priceText = $('<?php echo apply_filters('hpo_price_selector', '.price .amount'); ?>').first().text();
            console.log("Original price text:", priceText);
            
            // Remove all non-numeric characters except digits and decimal point
            originalPrice = priceText.replace(/[^\d.]/g, '');
            console.log("Cleaned price:", originalPrice);
            
            originalPrice = parseFloat(originalPrice);
            console.log("Parsed original price:", originalPrice);
            
            // Store the original price for future reference
            $('body').append('<input type="hidden" name="hpo_original_price" value="' + originalPrice + '">');
        } else {
            originalPrice = parseFloat(originalPrice);
        }
        
        // Parse the price parameter as float to ensure proper addition
        price = parseFloat(price);
        console.log("Option price:", price);
        
        // Make sure both values are numbers before adding
        if (!isNaN(originalPrice) && !isNaN(price)) {
            // Update displayed price - ADD the option price to the original price
            var newPrice = originalPrice + price;
            console.log("New calculated price:", newPrice);
            
            // Ensure we have a properly formatted number with 0 decimal places for Tomans
            // Format price with WooCommerce currency format
            var formattedPrice = '<?php echo get_woocommerce_currency_symbol(); ?>' + numberWithCommas(newPrice.toFixed(0));
            
            // Update price
            $('<?php echo apply_filters('hpo_price_selector', '.price .amount'); ?>').html(formattedPrice);
        } else {
            console.log("Invalid price values - originalPrice:", originalPrice, "optionPrice:", price);
        }
    }
    
    // Helper function to format numbers with commas for thousands
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
});
</script>

<?php
/**
 * Helper function to render category content
 */
function hpo_render_category_content($category, $settings) {
    // Render products
    if (!empty($category->products)): ?>
        <div class="hpo-products-options" data-category-id="<?php echo esc_attr($category->id); ?>">
            <?php foreach ($category->products as $product): ?>
            <div class="hpo-product-option-wrapper">
                <label>
                    <input type="radio" name="hpo_product_option" class="hpo-product-option" 
                           value="<?php echo esc_attr($product->id); ?>"
                           data-price="<?php echo esc_attr($product->price); ?>"
                           data-name="<?php echo esc_attr($product->name); ?>">
                    <span class="hpo-option-name"><?php echo esc_html($product->name); ?></span>
                    <?php if ($settings['price_display'] === 'next_to_option'): ?>
                    <span class="hpo-option-price"><?php echo wc_price($product->price); ?></span>
                    <?php endif; ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; 
    
    // Render subcategories
    if (!empty($category->children)): ?>
        <div class="hpo-subcategories">
            <?php foreach ($category->children as $subcategory): ?>
            <div class="hpo-subcategory">
                <h5><?php echo esc_html($subcategory->name); ?></h5>
                <?php hpo_render_category_content($subcategory, $settings); ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif;
} 