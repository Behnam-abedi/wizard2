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
            <select name="hpo_grinding_machine" id="hpo-grinding-machine" required>
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

    <div class="hpo-product-info">
        <div class="hpo-current-price" data-base-price="<?php echo esc_attr($base_price); ?>">
            <?php echo wc_price($base_price); ?>
        </div>
        <div class="hpo-quantity-control">
            <button type="button" class="hpo-quantity-decrease">-</button>
            <input type="number" name="quantity" value="1" min="1" class="hpo-quantity-input">
            <button type="button" class="hpo-quantity-increase">+</button>
        </div>
    </div>

    <div class="hpo-selected-options">
        <input type="hidden" name="hpo_selected_option" id="hpo-selected-option" value="">
        <input type="hidden" name="hpo_selected_weight" id="hpo-selected-weight" value="">
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
    var selectedWeight = null; // Changed from array to single object for radio buttons
    
    // Initialize on page load - get base product price from a hidden field we'll add
    var baseProductPrice = <?php echo floatval(get_post_meta(get_the_ID(), '_price', true)); ?>;
    console.log("Actual product base price from WooCommerce:", baseProductPrice);
    
    // Debug: Let's inspect how prices are stored in data attributes
    console.log("Checking all product options for price data:");
    $('input.hpo-product-option').each(function() {
        var rawPrice = $(this).data('price');
        var optionName = $(this).data('name');
        console.log("Option:", optionName, "- Raw price data:", rawPrice, "- Type:", typeof rawPrice);
    });
    
    // Handle product options selection (both radio buttons and checkboxes)
    $('input.hpo-product-option').on('change', function() {
        console.log("Option changed:", this);
        var optionId = $(this).val();
        
        // Get the raw price data and ensure proper parsing
        var rawPrice = $(this).data('price');
        console.log("Raw price data:", rawPrice, "- Type:", typeof rawPrice);
        
        // Ensure proper parsing of price data (handle both number and string formats)
        var optionPrice;
        if (typeof rawPrice === 'string') {
            // For string prices, remove any non-numeric characters except digits and decimal point
            optionPrice = parseFloat(rawPrice.replace(/[^\d.]/g, ''));
        } else {
            // For numeric prices, just use the value directly
            optionPrice = parseFloat(rawPrice);
        }
        
        console.log("Parsed option price:", optionPrice);
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
    
    // Handle weight options selection (now radio buttons)
    $('input.hpo-weight-option').on('change', function() {
        console.log("Weight option changed:", this);
        var weightId = $(this).val();
        var coefficient = parseFloat($(this).data('coefficient'));
        var weightName = $(this).data('name');
        
        // Since it's a radio button, it's always checked when this event fires
        // Store the selected weight for form submission
        $('#hpo-selected-weight').val(weightId);
        
        // Replace the previous selection with the new one (single weight only)
        selectedWeight = {
            id: weightId,
            coefficient: coefficient,
            name: weightName
        };
        
        console.log("Selected weight:", selectedWeight);
        
        // Update product price if enabled
        if ($('.hpo-options-container').data('update-price') === 'yes') {
            updateProductPrice();
        }
    });
    
    // Function to update product price based on all selected options and weight
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
        
        // Apply quantity
        calculatedPrice = calculatedPrice * quantity;
        
        // Update all WooCommerce price displays on the page
        $('.woocommerce-variation-price .amount, .woocommerce-Price-amount.amount, .price .amount').each(function() {
            $(this).html('<?php echo get_woocommerce_currency_symbol(); ?>' + numberWithCommas(calculatedPrice.toFixed(0)));
        });
    }
    
    // Helper function to format numbers with commas for thousands
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    var basePriceElement = $('.hpo-current-price');
    var basePrice = parseFloat(basePriceElement.data('base-price'));
    var selectedCategories = [];
    var selectedProducts = [];
    var selectedGrindingMachine = null;
    var quantity = 1;
    
    function updateProductPrice() {
        var totalPrice = basePrice;
        var selectedCategoriesPrice = 0;
        var selectedProductsPrice = 0;
        var weightMultiplier = 1;
        var grindingPrice = 0;
        
        // Calculate price from selected categories
        $.each(selectedCategories, function(i, category) {
            selectedCategoriesPrice += parseFloat(category.price || 0);
        });
        
        // Calculate price from selected products
        $.each(selectedProducts, function(i, product) {
            selectedProductsPrice += parseFloat(product.price || 0);
        });
        
        // Apply weight multiplier if a weight is selected
        if (selectedWeight) {
            weightMultiplier = parseFloat(selectedWeight.coefficient || 1);
        }
        
        // Add grinding machine price if selected
        if (selectedGrindingMachine) {
            grindingPrice = parseFloat(selectedGrindingMachine.price || 0);
        }
        
        // Calculate total price
        totalPrice = (totalPrice + selectedCategoriesPrice + selectedProductsPrice) * weightMultiplier;
        totalPrice += grindingPrice;
        totalPrice = totalPrice * quantity;
        
        // Update the price display
        basePriceElement.html(formatPrice(totalPrice));
        
        console.log('Price update calculation:', {
            basePrice: basePrice,
            categoriesPrice: selectedCategoriesPrice,
            productsPrice: selectedProductsPrice,
            weightMultiplier: weightMultiplier,
            grindingPrice: grindingPrice,
            quantity: quantity,
            totalPrice: totalPrice
        });
    }
    
    // Format price according to WooCommerce settings
    function formatPrice(price) {
        return '<?php echo get_woocommerce_currency_symbol(); ?>' + parseFloat(price).toFixed(2);
    }
    
    // Handle category selection
    $('.hpo-category-option input[type="checkbox"]').on('change', function() {
        var $this = $(this);
        var categoryId = $this.val();
        var categoryName = $this.data('name');
        var categoryPrice = $this.data('price');
        
        if ($this.prop('checked')) {
            selectedCategories.push({
                id: categoryId,
                name: categoryName,
                price: categoryPrice
            });
        } else {
            selectedCategories = selectedCategories.filter(function(category) {
                return category.id !== categoryId;
            });
        }
        
        console.log('Selected categories:', selectedCategories);
        updateProductPrice();
    });
    
    // Handle product selection
    $('.hpo-product-option input[type="checkbox"]').on('change', function() {
        var $this = $(this);
        var productId = $this.val();
        var productName = $this.data('name');
        var productPrice = $this.data('price');
        
        if ($this.prop('checked')) {
            selectedProducts.push({
                id: productId,
                name: productName,
                price: productPrice
            });
        } else {
            selectedProducts = selectedProducts.filter(function(product) {
                return product.id !== productId;
            });
        }
        
        console.log('Selected products:', selectedProducts);
        updateProductPrice();
    });
    
    // Handle weight selection
    $('.hpo-weight-option input[type="radio"]').on('change', function() {
        var $this = $(this);
        var weightId = $this.val();
        var weightName = $this.data('name');
        var coefficient = $this.data('coefficient');
        
        selectedWeight = {
            id: weightId,
            name: weightName,
            coefficient: coefficient
        };
        
        console.log('Selected weight:', selectedWeight);
        updateProductPrice();
    });
    
    // Handle grinding option selection
    $('input[name="hpo_grinding"]').on('change', function() {
        var grindingOption = $(this).val();
        
        if (grindingOption === 'ground') {
            $('.hpo-grinding-machines').show();
            // Make grinding machine selection required
            $('#hpo-grinding-machine').prop('required', true);
        } else {
            $('.hpo-grinding-machines').hide();
            // Reset grinding machine selection
            $('#hpo-grinding-machine').val('').prop('required', false);
            selectedGrindingMachine = null;
            updateProductPrice();
        }
    });
    
    // Handle grinding machine selection
    $('#hpo-grinding-machine').on('change', function() {
        var $this = $(this);
        var grinderId = $this.val();
        
        if (grinderId) {
            var $selectedOption = $this.find('option:selected');
            selectedGrindingMachine = {
                id: grinderId,
                name: $selectedOption.text(),
                price: $selectedOption.data('price')
            };
        } else {
            selectedGrindingMachine = null;
        }
        
        console.log('Selected grinding machine:', selectedGrindingMachine);
        updateProductPrice();
    });
    
    // Handle quantity changes
    $('.hpo-quantity-decrease').on('click', function() {
        var $input = $('.hpo-quantity-input');
        var currentValue = parseInt($input.val(), 10);
        if (currentValue > 1) {
            $input.val(currentValue - 1);
            quantity = currentValue - 1;
            updateProductPrice();
        }
    });
    
    $('.hpo-quantity-increase').on('click', function() {
        var $input = $('.hpo-quantity-input');
        var currentValue = parseInt($input.val(), 10);
        $input.val(currentValue + 1);
        quantity = currentValue + 1;
        updateProductPrice();
    });
    
    $('.hpo-quantity-input').on('change', function() {
        var $input = $(this);
        var currentValue = parseInt($input.val(), 10);
        if (currentValue < 1) {
            $input.val(1);
            currentValue = 1;
        }
        quantity = currentValue;
        updateProductPrice();
    });
    
    // Add form validation before adding to cart
    $('form.cart').on('submit', function(e) {
        // Check if grinding is selected and a machine is chosen
        if ($('input[name="hpo_grinding"]:checked').val() === 'ground' && !$('#hpo-grinding-machine').val()) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Please select a grinding machine', 'hierarchical-product-options')); ?>');
            return false;
        }
        return true;
    });
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