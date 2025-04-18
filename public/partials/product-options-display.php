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

// Get the product base price
global $product;
$base_price = $product ? $product->get_price() : 0;
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
    
    <?php
    // Get weight options for this product
    $db = new Hierarchical_Product_Options_DB();
    $weights = $db->get_weights_for_product(get_the_ID());
    
    if (!empty($weights)): ?>
    <div class="hpo-options-section hpo-weight-options">
        <h4><?php echo esc_html__('Weight Options', 'hierarchical-product-options'); ?></h4>
        <div class="hpo-weight-radio-buttons">
            <?php foreach ($weights as $weight) : ?>
                <div class="hpo-weight-option">
                    <label>
                        <input type="radio" name="hpo_weight" value="<?php echo esc_attr($weight->id); ?>" 
                               data-name="<?php echo esc_attr($weight->name); ?>" 
                               data-coefficient="<?php echo esc_attr($weight->coefficient); ?>">
                        <?php echo esc_html($weight->name); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Grinding Options -->
    <div class="hpo-options-section hpo-grinding-options">
        <h4><?php echo esc_html__('Grinding Options', 'hierarchical-product-options'); ?></h4>
        <div class="hpo-grinding-toggle">
            <label>
                <input type="radio" name="hpo_grinding" value="whole" checked>
                <?php echo esc_html__('Whole (No Grinding)', 'hierarchical-product-options'); ?>
            </label>
            <label>
                <input type="radio" name="hpo_grinding" value="ground">
                <?php echo esc_html__('Ground', 'hierarchical-product-options'); ?>
            </label>
        </div>
        
        <div class="hpo-grinding-machines" style="display:none;">
            <label for="hpo-grinding-machine"><?php echo esc_html__('Select Grinding Machine', 'hierarchical-product-options'); ?></label>
            <select name="hpo_grinding_machine" id="hpo-grinding-machine">
                <option value=""><?php echo esc_html__('-- Select a grinding machine --', 'hierarchical-product-options'); ?></option>
                <?php 
                $db = new Hierarchical_Product_Options_DB();
                $grinders = $db->get_all_grinders();
                foreach ($grinders as $grinder) : ?>
                    <option value="<?php echo esc_attr($grinder->id); ?>" data-price="<?php echo esc_attr($grinder->price); ?>">
                        <?php echo esc_html($grinder->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="hpo-selected-options">
        <input type="hidden" name="hpo_selected_option" id="hpo-selected-option" value="">
        <input type="hidden" name="hpo_selected_weight" id="hpo-selected-weight" value="">
        <input type="hidden" name="hpo_base_price" id="hpo-base-price" value="<?php echo esc_attr($base_price); ?>">
        <input type="hidden" name="hpo_calculated_price" id="hpo-calculated-price" value="">
    </div>

    <!-- اضافه کردن فیلد توضیحات -->
    <div class="hpo-customer-notes">
        <h4>توضیحات اضافی</h4>
        <textarea name="hpo_customer_notes" id="hpo-customer-notes" rows="3" placeholder="اگر توضیح خاصی در مورد سفارش خود دارید، اینجا بنویسید..."></textarea>
    </div>
</div>

<style>
    .hpo-customer-notes {
        margin: 20px 0;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .hpo-customer-notes h4 {
        margin: 0 0 10px;
        color: #2271b1;
        font-size: 14px;
    }
    .hpo-customer-notes textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        resize: vertical;
        font-family: inherit;
    }
    .hpo-customer-notes textarea:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
        outline: none;
    }
</style>

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
    
    // Initialize variables
    var selectedOptions = [];
    var selectedWeight = null;
    var selectedGrindingMachine = null;
    var quantity = 1;
    
    // Get base product price from WooCommerce
    var baseProductPrice = parseFloat($('#hpo-base-price').val());
    
    // Handle product options selection
    $('.hpo-product-option').on('change', function() {
        var optionId = $(this).val();
        var optionPrice = parseFloat($(this).data('price'));
        var optionName = $(this).data('name');
        var isChecked = $(this).prop('checked');
        var categoryId = $(this).attr('name').match(/\[(\d+)\]/)[1];
        
        // For form submission
        $('#hpo-selected-option').val(optionId);
        
        // Remove any previous selection from this category
        selectedOptions = selectedOptions.filter(function(option) {
            return option.categoryId !== categoryId;
        });
        
        // Add new selection if checked
        if (isChecked) {
            selectedOptions.push({
                id: optionId,
                price: optionPrice,
                name: optionName,
                categoryId: categoryId
            });
        }
        
        // Update product price
        updateProductPrice();
    });
    
    // Handle weight options selection
    $('input[name="hpo_weight"]').on('change', function() {
        var weightId = $(this).val();
        var coefficient = parseFloat($(this).data('coefficient'));
        var weightName = $(this).data('name');
        
        // Store the selected weight for form submission
        $('#hpo-selected-weight').val(weightId);
        
        // Replace the previous selection with the new one
        selectedWeight = {
            id: weightId,
            coefficient: coefficient,
            name: weightName
        };
        
        // Update product price
        updateProductPrice();
    });
    
    // Handle grinding option selection
    $('input[name="hpo_grinding"]').on('change', function() {
        var grindingOption = $(this).val();
        
        if (grindingOption === 'ground') {
            $('.hpo-grinding-machines').show();
            $('#hpo-grinding-machine').prop('required', true);
        } else {
            $('.hpo-grinding-machines').hide();
            $('#hpo-grinding-machine').val('').prop('required', false);
            selectedGrindingMachine = null;
            updateProductPrice();
        }
    });
    
    // Handle grinding machine selection
    $('#hpo-grinding-machine').on('change', function() {
        var grinderId = $(this).val();
        
        if (grinderId) {
            var $selectedOption = $(this).find('option:selected');
            selectedGrindingMachine = {
                id: grinderId,
                name: $selectedOption.text(),
                price: $selectedOption.data('price')
            };
        } else {
            selectedGrindingMachine = null;
        }
        
        updateProductPrice();
    });
    
    // Handle quantity changes
    $('.hpo-quantity-decrease').on('click', function() {
        var $input = $('input.qty, .hpo-quantity-input');
        var currentValue = parseInt($input.val(), 10);
        if (currentValue > 1) {
            $input.val(currentValue - 1).trigger('change');
            quantity = currentValue - 1;
            updateProductPrice();
        }
    });
    
    $('.hpo-quantity-increase').on('click', function() {
        var $input = $('input.qty, .hpo-quantity-input');
        var currentValue = parseInt($input.val(), 10);
        $input.val(currentValue + 1).trigger('change');
        quantity = currentValue + 1;
        updateProductPrice();
    });
    
    $('input.qty, .hpo-quantity-input').on('change', function() {
        var currentValue = parseInt($(this).val(), 10);
        if (currentValue < 1) {
            $(this).val(1);
            currentValue = 1;
        }
        quantity = currentValue;
        updateProductPrice();
    });
    
    // Unified function to update product price based on all selections
    function updateProductPrice() {
        // Calculate total price from all selected options
        var totalOptionsPrice = 0;
        for (var i = 0; i < selectedOptions.length; i++) {
            var price = parseFloat(selectedOptions[i].price);
            if (!isNaN(price)) {
                totalOptionsPrice += price;
            }
        }
        
        // Calculate base price + options
        var calculatedPrice = baseProductPrice + totalOptionsPrice;
        
        // Apply weight coefficient if selected
        if (selectedWeight !== null) {
            var coefficient = parseFloat(selectedWeight.coefficient);
            if (!isNaN(coefficient) && coefficient > 0) {
                calculatedPrice = calculatedPrice * coefficient;
            }
        }
        
        // Add grinding machine price if selected
        if (selectedGrindingMachine) {
            var grindingPrice = parseFloat(selectedGrindingMachine.price);
            if (!isNaN(grindingPrice)) {
                calculatedPrice += grindingPrice;
            }
        }
        
        // Round the price for toman format - no decimals
        calculatedPrice = Math.round(calculatedPrice);
        
        // Apply quantity
        var totalPriceWithQuantity = calculatedPrice * quantity;
        
        // Format price with WooCommerce currency format
        var formattedPrice = '<?php echo get_woocommerce_currency_symbol(); ?>' + numberWithCommas(totalPriceWithQuantity.toFixed(0));
        
        // Update only the main product price, not option prices
        $('.single-product div.product p.price .woocommerce-Price-amount, .single-product div.product .woocommerce-variation-price .woocommerce-Price-amount').html(formattedPrice);
        
        // Store the calculated single unit price (without quantity) for the cart
        // Store as an integer for toman
        $('#hpo-calculated-price').val(calculatedPrice);
    }
    
    // Helper function to format numbers with commas for thousands
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Add form validation before adding to cart
    $('form.cart').on('submit', function(e) {
        // Check if grinding is selected and a machine is chosen
        if ($('input[name="hpo_grinding"]:checked').val() === 'ground' && !$('#hpo-grinding-machine').val()) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Please select a grinding machine', 'hierarchical-product-options')); ?>');
            return false;
        }
        
        // Prepare the selected options for submission
        var selectedOptionsArray = [];
        selectedOptions.forEach(function(option) {
            selectedOptionsArray.push({
                id: option.id,
                name: option.name,
                price: parseFloat(option.price),
                categoryId: option.categoryId
            });
        });
        
        // Convert the array to JSON and set in a hidden field
        var selectedOptionsJson = JSON.stringify(selectedOptionsArray);
        var hiddenField = $('<input type="hidden" name="hpo_selected_products" />').val(selectedOptionsJson);
        $(this).append(hiddenField);
        
        // Create a hidden field for categories if needed
        var selectedCategoriesArray = [];
        selectedOptions.forEach(function(option) {
            selectedCategoriesArray.push({
                id: option.categoryId,
                name: $('input[name="hpo_product_option[' + option.categoryId + ']"]:checked').closest('.hpo-products-options').data('category-name'),
                price: 0
            });
        });
        var selectedCategoriesJson = JSON.stringify(selectedCategoriesArray);
        var categoriesField = $('<input type="hidden" name="hpo_selected_categories" />').val(selectedCategoriesJson);
        $(this).append(categoriesField);
        
        // Make sure we have the calculated price
        if (!$('#hpo-calculated-price').val()) {
            updateProductPrice();
        }
        
        // Final check on pricing to ensure it's an integer for toman
        var finalPrice = $('#hpo-calculated-price').val();
        finalPrice = Math.round(parseFloat(finalPrice));
        $('#hpo-calculated-price').val(finalPrice);
        
        return true;
    });
});
</script>

<!-- HTML پاپ‌آپ موفقیت که بعد از افزودن محصول به سبد خرید نمایش داده می‌شود -->
<div id="hpo-success-popup-template" style="display: none;">
    <div class="hpo-success-popup-overlay">
        <div class="hpo-success-popup">
            <div class="hpo-success-icon">
                <svg viewBox="0 0 24 24" width="50" height="50" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22 11.0857V12.0057C21.9988 14.1621 21.3005 16.2604 20.0093 17.9875C18.7182 19.7147 16.9033 20.9782 14.8354 21.5896C12.7674 22.201 10.5573 22.1276 8.53447 21.3803C6.51168 20.633 4.78465 19.2518 3.61096 17.4428C2.43727 15.6338 1.87979 13.4938 2.02168 11.342C2.16356 9.19029 2.99721 7.14205 4.39828 5.5028C5.79935 3.86354 7.69279 2.72111 9.79619 2.24587C11.8996 1.77063 14.1003 1.98806 16.07 2.86572" stroke="#25AE88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M22 4L12 14.01L9 11.01" stroke="#25AE88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3>محصول با موفقیت به سبد خرید اضافه شد</h3>
            <div class="hpo-success-actions">
                <button class="hpo-repeat-order-btn">تکرار سفارش</button>
                <button class="hpo-checkout-btn">پرداخت سفارش</button>
                <button class="hpo-close-success-btn">بستن</button>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Helper function to render category content
 */
function hpo_render_category_content($category, $settings) {
    // Render products
    if (!empty($category->products)): ?>
        <div class="hpo-products-options" data-category-id="<?php echo esc_attr($category->id); ?>" data-category-name="<?php echo esc_attr($category->name); ?>">
            <?php foreach ($category->products as $product): ?>
            <div class="hpo-product-option-wrapper">
                <label>
                    <input type="radio" name="hpo_product_option[<?php echo esc_attr($category->id); ?>]" class="hpo-product-option" 
                           value="<?php echo esc_attr($product->id); ?>"
                           data-price="<?php echo esc_attr(floatval($product->price)); ?>"
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