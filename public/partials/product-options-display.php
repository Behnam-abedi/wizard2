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
    
    // Initialize selected options array to keep track of all selections
    var selectedOptions = [];
    
    // Initialize on page load - get base product price
    var baseProductPrice = 0;
    function initializeBasePrice() {
        var priceText = $('<?php echo apply_filters('hpo_price_selector', '.price .amount'); ?>').first().text();
        console.log("Original price text:", priceText);
        
        // Extract numeric value from price text, handling various formats
        var extractedPrice = priceText.replace(/[^\d.]/g, '');
        console.log("Cleaned price:", extractedPrice);
        
        baseProductPrice = parseFloat(extractedPrice);
        console.log("Base product price:", baseProductPrice);
        
        // Only check for the specific value 399 which is known to be a placeholder
        if (baseProductPrice === 399) {
            console.log("Detected exact placeholder price (399). Setting base price to 0");
            baseProductPrice = 0;
        }
    }
    
    // Run initialization on page load
    initializeBasePrice();
    
    // Handle all product options selection (both radio buttons and checkboxes)
    $('input.hpo-product-option').on('change', function() {
        console.log("Option changed:", this);
        var optionId = $(this).val();
        var optionPrice = parseFloat($(this).data('price'));
        var optionName = $(this).data('name');
        var isChecked = $(this).prop('checked');
        
        // For form submission - the last selected option
        $('#hpo-selected-option').val(optionId);
        
        // Handle the selected option in our tracking array
        if ($(this).attr('type') === 'radio') {
            // For radio buttons, replace any option from the same group
            var groupName = $(this).attr('name');
            console.log("Radio button changed, group:", groupName);
            
            // Remove previous selection from this group
            selectedOptions = selectedOptions.filter(function(option) {
                return option.group !== groupName;
            });
            
            // Add new selection
            if (isChecked) {
                selectedOptions.push({
                    id: optionId,
                    price: optionPrice,
                    name: optionName,
                    group: groupName
                });
            }
        } else {
            // For checkboxes, add or remove based on checked state
            console.log("Checkbox changed, checked:", isChecked);
            
            if (isChecked) {
                // Add this option if checked
                selectedOptions.push({
                    id: optionId,
                    price: optionPrice,
                    name: optionName
                });
            } else {
                // Remove this option if unchecked
                selectedOptions = selectedOptions.filter(function(option) {
                    return option.id !== optionId;
                });
            }
        }
        
        console.log("Current selected options:", selectedOptions);
        
        // Update product price if enabled
        if ($('.hpo-options-container').data('update-price') === 'yes') {
            updateProductPrice();
        }
    });
    
    // Function to update product price based on all selected options
    function updateProductPrice() {
        // Calculate total price from all selected options
        var totalOptionsPrice = 0;
        for (var i = 0; i < selectedOptions.length; i++) {
            var price = parseFloat(selectedOptions[i].price);
            if (!isNaN(price)) {
                totalOptionsPrice += price;
            }
        }
        console.log("Total options price:", totalOptionsPrice);
        
        // Calculate final price (base + options)
        var newPrice = baseProductPrice + totalOptionsPrice;
        console.log("New calculated price:", newPrice);
        
        // Ensure we have a properly formatted number with 0 decimal places for Tomans
        // Format price with WooCommerce currency format
        var formattedPrice = '<?php echo get_woocommerce_currency_symbol(); ?>' + numberWithCommas(newPrice.toFixed(0));
        
        // Update price on the page
        $('<?php echo apply_filters('hpo_price_selector', '.price .amount'); ?>').html(formattedPrice);
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