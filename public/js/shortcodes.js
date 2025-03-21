jQuery(document).ready(function($) {
    // Order button click - open product selection popup
    $('#hpo-order-button').on('click', function() {
        $('#hpo-popup-overlay').fadeIn(300);
        
        // Load products via AJAX
        $.ajax({
            url: hpoAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hpo_load_products',
                nonce: hpoAjax.nonce
            },
            beforeSend: function() {
                $('#hpo-product-list').html('<div class="hpo-loading">در حال بارگذاری...</div>');
            },
            success: function(response) {
                if (response.success) {
                    $('#hpo-product-list').html(response.data.html);
                    initProductSelection();
                } else {
                    $('#hpo-product-list').html('<p>خطا در بارگذاری محصولات.</p>');
                }
            },
            error: function() {
                $('#hpo-product-list').html('<p>خطا در ارتباط با سرور.</p>');
            }
        });
    });
    
    // Close product list popup
    $('#hpo-popup-close').on('click', function() {
        $('#hpo-popup-overlay').fadeOut(300);
    });
    
    // Close product details popup
    $('#hpo-product-details-close').on('click', function() {
        $('#hpo-product-details-popup').fadeOut(300);
    });
    
    // Initialize product selection functionality
    function initProductSelection() {
        $('.hpo-select-product').on('click', function() {
            var productId = $(this).data('product-id');
            loadProductDetails(productId);
        });
    }
    
    // Load product details
    function loadProductDetails(productId) {
        $.ajax({
            url: hpoAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hpo_load_product_details',
                nonce: hpoAjax.nonce,
                product_id: productId
            },
            beforeSend: function() {
                $('#hpo-product-details-content').html('<div class="hpo-loading">در حال بارگذاری...</div>');
                $('#hpo-product-title').text('جزئیات محصول');
                $('#hpo-product-details-popup').fadeIn(300);
            },
            success: function(response) {
                if (response.success) {
                    $('#hpo-product-details-content').html(response.data.html);
                    $('#hpo-product-title').text(response.data.product_title);
                    
                    // Initialize product options form
                    initProductOptionsForm();
                } else {
                    $('#hpo-product-details-content').html('<p>' + response.data.message + '</p>');
                }
            },
            error: function() {
                $('#hpo-product-details-content').html('<p>خطا در ارتباط با سرور.</p>');
            }
        });
    }
    
    // Initialize product options form
    function initProductOptionsForm() {
        // Toggle grinding options
        $('input[name="hpo_grinding"]').on('change', function() {
            if ($(this).val() === 'ground') {
                $('.hpo-grinding-machines').slideDown(200);
            } else {
                $('.hpo-grinding-machines').slideUp(200);
                $('#hpo-grinding-machine').val('');
            }
            updateTotalPrice();
        });
        
        // Quantity plus/minus buttons
        $('.hpo-quantity-plus').on('click', function() {
            var input = $(this).siblings('input[name="quantity"]');
            var value = parseInt(input.val());
            if (value < 99) {
                input.val(value + 1);
                updateTotalPrice();
            }
        });
        
        $('.hpo-quantity-minus').on('click', function() {
            var input = $(this).siblings('input[name="quantity"]');
            var value = parseInt(input.val());
            if (value > 1) {
                input.val(value - 1);
                updateTotalPrice();
            }
        });
        
        // Update price when options change
        $('input[name^="hpo_option"], input[name="hpo_weight"], select[name="hpo_grinding_machine"]').on('change', function() {
            updateTotalPrice();
            
            // Handle visual selection for product options
            if ($(this).attr('name').startsWith('hpo_option')) {
                // Uncheck all other product options in the entire option section
                $('.hpo-option-section input[name^="hpo_option"]').not(this).prop('checked', false);
                
                // Remove selected class from all product options
                $('.hpo-product-option').removeClass('selected');
                
                // Add selected class to the current option
                $(this).closest('.hpo-product-option').addClass('selected');
            }
            
            // Handle visual selection for weight options
            if ($(this).attr('name') === 'hpo_weight') {
                // Remove selected class from all weight options
                $('.hpo-weight-option').removeClass('selected');
                
                // Add selected class to the selected weight option
                $(this).closest('.hpo-weight-option').addClass('selected');
            }
        });
        
        // Handle form submission
        $('.hpo-product-options-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var productId = form.find('input[name="product_id"]').val();
            
            // Create data object to collect all selections
            var formData = {
                'product_id': productId,
                'quantity': form.find('input[name="quantity"]').val(),
                'add-to-cart': productId,
                'hpo_options': {},
                'hpo_weight': '',
                'hpo_grinding': form.find('input[name="hpo_grinding"]:checked').val(),
                'hpo_grinding_machine': '',
                'hpo_customer_notes': form.find('textarea[name="hpo_customer_notes"]').val()
            };
            
            // Get selected options
            var selectedOption = form.find('input[name^="hpo_option"]:checked');
            if (selectedOption.length) {
                var categoryId = selectedOption.attr('name').match(/\[(\d+)\]/)[1];
                var optionId = selectedOption.val();
                var optionName = selectedOption.closest('label').text().trim();
                var optionPrice = selectedOption.data('price');
                
                formData.hpo_options[categoryId] = {
                    id: optionId,
                    name: optionName,
                    price: optionPrice
                };
            }
            
            // Get selected weight
            var selectedWeight = form.find('input[name="hpo_weight"]:checked');
            if (selectedWeight.length) {
                formData.hpo_weight = {
                    id: selectedWeight.val(),
                    name: selectedWeight.closest('label').text().trim(),
                    coefficient: selectedWeight.data('coefficient')
                };
            }
            
            // Get grinding machine if grinding is selected
            if (formData.hpo_grinding === 'ground') {
                var grindingMachine = form.find('select[name="hpo_grinding_machine"]');
                if (grindingMachine.val()) {
                    formData.hpo_grinding_machine = {
                        id: grindingMachine.val(),
                        name: grindingMachine.find('option:selected').text().trim(),
                        price: grindingMachine.find('option:selected').data('price')
                    };
                }
            }
            
            // Convert the data object to a string for AJAX
            var serializedData = $.param({
                'action': 'hpo_add_to_cart',
                'nonce': hpoAjax.nonce,
                'hpo_data': JSON.stringify(formData)
            });
            
            // Add to cart via AJAX
            $.ajax({
                url: hpoAjax.ajaxUrl,
                type: 'POST',
                data: serializedData,
                success: function(response) {
                    if (response.success) {
                        // Close popups
                        $('#hpo-product-details-popup').fadeOut(300);
                        $('#hpo-popup-overlay').fadeOut(300);
                        
                        // Show success message
                        $('body').append('<div class="hpo-success-message">محصول با موفقیت به سبد خرید اضافه شد.</div>');
                        
                        // Update mini cart
                        $(document.body).trigger('wc_fragment_refresh');
                        
                        // Remove message after 3 seconds
                        setTimeout(function() {
                            $('.hpo-success-message').fadeOut(300, function() {
                                $(this).remove();
                            });
                        }, 3000);
                    } else {
                        alert(response.data.message || 'خطا در افزودن محصول به سبد خرید.');
                    }
                },
                error: function() {
                    alert('خطا در افزودن محصول به سبد خرید.');
                }
            });
        });
    }
    
    // Calculate and update the total price
    function updateTotalPrice() {
        var form = $('.hpo-product-options-form');
        var basePrice = parseFloat(form.find('input[name="hpo_base_price"]').val());
        var quantity = parseInt(form.find('input[name="quantity"]').val());
        var totalPrice = basePrice;
        
        // Add product option price (now just one selected option)
        var selectedOption = form.find('input[name^="hpo_option"]:checked');
        if (selectedOption.length) {
            totalPrice += parseFloat(selectedOption.data('price'));
        }
        
        // Apply weight coefficient
        var weightOption = form.find('input[name="hpo_weight"]:checked');
        if (weightOption.length) {
            var coefficient = parseFloat(weightOption.data('coefficient'));
            totalPrice *= coefficient;
        }
        
        // Add grinding price
        if (form.find('input[name="hpo_grinding"]:checked').val() === 'ground') {
            var grindingMachine = form.find('select[name="hpo_grinding_machine"]');
            var selectedOption = grindingMachine.find('option:selected');
            
            if (selectedOption.val()) {
                totalPrice += parseFloat(selectedOption.data('price'));
            }
        }
        
        // Multiply by quantity
        totalPrice *= quantity;
        
        // Format the price
        var formattedPrice = new Intl.NumberFormat('fa-IR', {
            style: 'decimal',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(totalPrice);
        
        // Update the displayed price
        $('#hpo-total-price').text(formattedPrice + ' تومان');
    }
}); 