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
        });
        
        // Handle form submission
        $('.hpo-product-options-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            var productId = $('input[name="product_id"]').val();
            
            // Add to cart via AJAX
            $.ajax({
                url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'),
                type: 'POST',
                data: formData + '&add-to-cart=' + productId,
                success: function(response) {
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
        
        // Add product option prices from both parent and child categories
        form.find('input[name^="hpo_option"]:checked').each(function() {
            totalPrice += parseFloat($(this).data('price'));
        });
        
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
        
        // Format the price (basic formatting, you may need to adjust based on your needs)
        var formattedPrice = new Intl.NumberFormat('fa-IR', {
            style: 'currency',
            currency: 'IRR',
            minimumFractionDigits: 0
        }).format(totalPrice);
        
        // Update the display
        $('#hpo-total-price').text(formattedPrice);
    }
}); 