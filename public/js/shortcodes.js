jQuery(document).ready(function($) {
    // Add body class when popup is open to prevent background scrolling
    function lockBodyScroll() {
        $('body').addClass('hpo-popup-open');
    }
    
    function unlockBodyScroll() {
        $('body').removeClass('hpo-popup-open');
    }
    
    // Order button click - open product selection popup
    $('#hpo-order-button').on('click', function() {
        $('#hpo-popup-overlay').fadeIn(300);
        lockBodyScroll();
        
        // Initialize popup height
        setTimeout(adjustPopupHeight, 100);
        
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
        unlockBodyScroll();
    });
    
    // Initialize product selection functionality
    function initProductSelection() {
        // Unbind previous event handlers to prevent duplicates
        $('.hpo-product-item').off('click');
        $(document).off('click', '.hpo-next-step');
        
        // Reset selection
        $('.hpo-product-item').removeClass('selected');
        
        // Create new local variable for product ID
        let selectedProductId = null;
        
        // Add the next step button in disabled state
        if (!$('.hpo-next-step').length) {
            $('#hpo-product-list').append('<button class="hpo-next-step disabled">مرحله بعد</button>');
        } else {
            $('.hpo-next-step').addClass('disabled');
        }

        // Handle product item click
        $('.hpo-product-item').on('click', function() {
            // Remove selection from other products
            $('.hpo-product-item').removeClass('selected');
            // Add selection to clicked product
            $(this).addClass('selected');
            // Store the selected product ID
            selectedProductId = $(this).data('product-id');
            
            // Enable the next step button
            $('.hpo-next-step').removeClass('disabled');
        });

        // Handle next step button click
        $(document).on('click', '.hpo-next-step', function() {
            // Only proceed if not disabled
            if(!$(this).hasClass('disabled')) {
                if (selectedProductId) {
                    loadProductDetails(selectedProductId);
                }
            }
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
                // Show loading in the same popup
                $('#hpo-product-list').html('<div class="hpo-loading">در حال بارگذاری...</div>');
                $('.hpo-popup-header h3').text('جزئیات محصول');
            },
            success: function(response) {
                if (response.success) {
                    // Update the content in the same popup
                    $('#hpo-product-list').html(response.data.html);
                    $('.hpo-popup-header h3').text(response.data.product_title);
                    
                    // Add back button
                    if (!$('.hpo-back-button').length) {
                        $('.hpo-popup-header').prepend('<button class="hpo-back-button">بازگشت</button>');
                    }
                    
                    // Reset scroll position to top after content change
                    $('.hpo-popup-content').scrollTop(0);
                    
                    // Initialize product options form
                    initProductOptionsForm();
                } else {
                    $('#hpo-product-list').html('<p>' + response.data.message + '</p>');
                }
            },
            error: function() {
                $('#hpo-product-list').html('<p>خطا در ارتباط با سرور.</p>');
            }
        });
    }

    // Handle back button click
    $(document).on('click', '.hpo-back-button', function() {
        // Add loading indicator before starting the AJAX request
        $('#hpo-product-list').html('<div class="hpo-loading">در حال بارگذاری...</div>');
        
        // Update header before AJAX request to provide immediate feedback
        $('.hpo-popup-header h3').text('انتخاب محصول');
        
        // Remove back button immediately to prevent double clicks
        $(this).remove();
        
        // Reload the products list
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
                    
                    // Update header and remove back button
                    $('.hpo-popup-header h3').text('انتخاب محصول');
                    $('.hpo-back-button').remove();
                    
                    // Reinitialize product selection with clean state
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
    
    // Initialize product options form
    function initProductOptionsForm() {
        // Disable add to cart button initially
        const $addToCartButton = $('.hpo-add-to-cart-button');
        $addToCartButton.prop('disabled', true).addClass('disabled');
        
        // Get sections
        const $productOptionsSection = $('.hpo-option-section:not(.hpo-grinding-options-section)');
        const $weightSection = $('.hpo-weight-section');
        const $grindingSection = $('.hpo-grinding-options-section');
        
        // Initially disable weight and grinding sections
        $weightSection.addClass('disabled-section');
        $grindingSection.addClass('disabled-section');
        
        // Function to validate all selections and enable/disable sections and add-to-cart button
        function validateSelections() {
            // Check if a product option is selected
            const hasProductOption = $('input[name^="hpo_option"]:checked').length > 0;
            
            // Check if a weight option is selected
            const hasWeight = $('input[name="hpo_weight"]:checked').length > 0;
            
            // Check grinding selection
            const grindingValue = $('input[name="hpo_grinding"]').val();
            let grindingValid = true;
            
            // If grinding is set to ground, check if a grinding machine is selected
            if (grindingValue === 'ground') {
                const grindingMachine = $('select[name="hpo_grinding_machine"]').val();
                grindingValid = grindingMachine !== '' && grindingMachine !== null;
            }
            
            // Enable/disable weight section based on product option selection
            if (hasProductOption) {
                $weightSection.removeClass('disabled-section');
            } else {
                $weightSection.addClass('disabled-section');
            }
            
            // Enable/disable grinding section based on weight selection
            if (hasProductOption && hasWeight) {
                $grindingSection.removeClass('disabled-section');
            } else {
                $grindingSection.addClass('disabled-section');
            }
            
            // Enable add to cart button only if all selections are valid
            if (hasProductOption && hasWeight && grindingValid) {
                $addToCartButton.prop('disabled', false).removeClass('disabled');
            } else {
                $addToCartButton.prop('disabled', true).addClass('disabled');
            }
        }

        // Toggle grinding options
        $('input[name="hpo_grinding"]').on('change', function() {
            if ($(this).val() === 'ground') {
                $('.hpo-grinding-machines').slideDown(200);
            } else {
                $('.hpo-grinding-machines').slideUp(200);
                $('#hpo-grinding-machine').val('');
            }
            updateTotalPrice();
            validateSelections();
        });
        
        // Handle grinding machine selection
        $('select[name="hpo_grinding_machine"]').on('change', function() {
            console.log('Grinding machine selection changed');
            console.log('Selected value:', $(this).val());
            console.log('Selected option price:', $(this).find('option:selected').data('price'));
            updateTotalPrice();
            validateSelections();
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
            validateSelections();
            
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
        
        // Handle section clicks when disabled
        $('.disabled-section').on('click', function(e) {
            // If the section is disabled, show a message to complete previous steps
            if ($(this).hasClass('disabled-section')) {
                e.preventDefault();
                e.stopPropagation();
                
                // Determine which section this is
                if ($(this).hasClass('hpo-weight-section')) {
                    alert('لطفا ابتدا گزینه محصول را انتخاب کنید.');
                } else if ($(this).hasClass('hpo-grinding-options-section')) {
                    alert('لطفا ابتدا گزینه وزن را انتخاب کنید.');
                }
                return false;
            }
        });
        
        // Disable inputs in disabled sections
        function updateInputStates() {
            // Disable all inputs in disabled sections
            $('.disabled-section input, .disabled-section select').prop('disabled', true);
            
            // Enable all inputs in enabled sections
            $('.hpo-option-section:not(.disabled-section) input, .hpo-option-section:not(.disabled-section) select, .hpo-weight-section:not(.disabled-section) input').prop('disabled', false);
        }
        
        // Handle grinding toggle option click
        $(document).on('click', '.hpo-toggle-option', function() {
            // Check if the section is disabled
            if ($(this).closest('.disabled-section').length) {
                alert('لطفا ابتدا گزینه وزن را انتخاب کنید.');
                return false;
            }
            
            // After toggle action, validate selections again
            setTimeout(validateSelections, 100);
        });
        
        // Run initial validation and update input states
        validateSelections();
        updateInputStates();
        
        // Update input states when validation changes
        $(document).on('click', 'input[type="radio"], select', function() {
            setTimeout(updateInputStates, 100);
        });
        
        // Handle form submission
        $('.hpo-product-options-form').on('submit', function(e) {
            e.preventDefault();
            
            // Extra validation before submission
            if ($addToCartButton.prop('disabled')) {
                return false;
            }
            
            var form = $(this);
            var productId = form.find('input[name="product_id"]').val();
            var basePrice = parseFloat(form.find('input[name="hpo_base_price"]').val()) || 0;
            var quantity = parseInt(form.find('input[name="quantity"]').val()) || 1;
            
            // Get the total calculated price
            var calculatedPrice = form.data('calculated-price') || 0;
            
            // Create data object to collect all selections
            var formData = {
                'product_id': productId,
                'quantity': quantity,
                'add-to-cart': productId,
                'base_price': basePrice,
                'total_price': calculatedPrice,
                'hpo_options': {},
                'hpo_weight': '',
                'hpo_grinding': form.find('input[name="hpo_grinding"]').val(),
                'hpo_grinding_machine': '',
                'hpo_customer_notes': form.find('textarea[name="hpo_customer_notes"]').val()
            };
            
            // Get selected options
            var selectedOption = form.find('input[name^="hpo_option"]:checked');
            if (selectedOption.length) {
                var categoryId = selectedOption.attr('name').match(/\[(\d+)\]/)[1];
                var optionId = selectedOption.val();
                var optionName = selectedOption.closest('label').text().trim();
                var optionPrice = parseFloat(selectedOption.data('price')) || 0;
                
                formData.hpo_options[categoryId] = {
                    id: optionId,
                    name: optionName,
                    price: optionPrice
                };
            }
            
            // Get selected weight
            var selectedWeight = form.find('input[name="hpo_weight"]:checked');
            if (selectedWeight.length) {
                var weightName = selectedWeight.closest('label').text().trim();
                var weightCoefficient = parseFloat(selectedWeight.data('coefficient')) || 1;
                
                formData.hpo_weight = {
                    id: selectedWeight.val(),
                    name: weightName,
                    coefficient: weightCoefficient
                };
            }
            
            // Get grinding machine if grinding is selected
            if (formData.hpo_grinding === 'ground') {
                var grindingMachine = form.find('select[name="hpo_grinding_machine"]');
                var selectedGrinder = grindingMachine.find('option:selected');
                
                if (selectedGrinder.val()) {
                    var grinderPrice = parseFloat(selectedGrinder.data('price')) || 0;
                    
                    formData.hpo_grinding_machine = {
                        id: selectedGrinder.val(),
                        name: selectedGrinder.text().trim(),
                        price: grinderPrice
                    };
                }
            }
            
            // For debugging - can be removed in production
            console.log('Submitting data to cart:');
            console.log(formData);
            
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
                        unlockBodyScroll();
                        
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
        
        // Ensure popup height is correct when content changes
        adjustPopupHeight();
    }
    
    // Calculate and update the total price
    function updateTotalPrice() {
        // Get form and base values
        var form = $('.hpo-product-options-form');
        var basePrice = parseFloat(form.find('input[name="hpo_base_price"]').val()) || 0;
        var quantity = parseInt(form.find('input[name="quantity"]').val()) || 1;
        
        console.log('Starting price calculation:');
        console.log('Base price:', basePrice);
        
        // Step 1: Start with base price
        var calculatedPrice = basePrice;
        
        // Step 2: Add selected product option price
        var selectedOption = form.find('input[name^="hpo_option"]:checked');
        if (selectedOption.length) {
            var optionPrice = parseFloat(selectedOption.data('price')) || 0;
            calculatedPrice += optionPrice;
            console.log('Option price:', optionPrice);
            console.log('Price after adding option:', calculatedPrice);
        }
        
        // Step 3: Apply weight coefficient (if selected)
        var weightOption = form.find('input[name="hpo_weight"]:checked');
        if (weightOption.length) {
            var coefficient = parseFloat(weightOption.data('coefficient')) || 1;
            calculatedPrice *= coefficient;
            console.log('Weight coefficient:', coefficient);
            console.log('Price after applying weight coefficient:', calculatedPrice);
        }
        
        // Step 4: Add grinding price (if applicable)
        var grindingValue = form.find('input[name="hpo_grinding"]').val();
        console.log('Grinding value:', grindingValue);
        
        if (grindingValue === 'ground') {
            var grindingSelect = form.find('select[name="hpo_grinding_machine"]');
            var selectedGrinder = grindingSelect.find('option:selected');
            
            if (selectedGrinder.length && selectedGrinder.val()) {
                // Get the price directly from the data attribute
                var grindingPrice = selectedGrinder.data('price');
                
                // Convert to a proper number
                grindingPrice = parseFloat(grindingPrice) || 0;
                
                console.log('Selected grinder:', selectedGrinder.text());
                console.log('Grinding price (raw):', selectedGrinder.data('price'));
                console.log('Grinding price (parsed):', grindingPrice);
                
                // Add to total
                calculatedPrice += grindingPrice;
                console.log('Price after adding grinding:', calculatedPrice);
            } else {
                console.log('No grinding machine selected or invalid selection');
            }
        }
        
        // Step 5: Multiply by quantity
        var totalPrice = calculatedPrice * quantity;
        console.log('Quantity:', quantity);
        console.log('Final price:', totalPrice);
        
        // Format the price with Farsi numerals
        var formattedPrice = new Intl.NumberFormat('fa-IR', {
            style: 'decimal',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(totalPrice);
        
        console.log('Formatted price:', formattedPrice);
        
        // Update the displayed price
        $('#hpo-total-price').text(formattedPrice + ' تومان');
        
        // Store the calculated price for easier access
        form.data('calculated-price', totalPrice);
    }

    // Handle grinding toggle
    function initGrindingToggle() {
        // Ensure grinding machine select changes trigger price updates
        $(document).on('change', 'select[name="hpo_grinding_machine"]', function() {
            console.log('Grinding machine changed to:', $(this).find('option:selected').text());
            console.log('Grinding machine price:', $(this).find('option:selected').data('price'));
            updateTotalPrice();
        });

        $(document).on('click', '.hpo-toggle-option', function() {
            const $this = $(this);
            const $toggleContainer = $this.closest('.hpo-toggle-container');
            const $options = $toggleContainer.find('.hpo-toggle-option');
            const $input = $toggleContainer.closest('.hpo-grinding-toggle').find('input[name="hpo_grinding"]');
            const $grindingMachines = $toggleContainer.closest('.hpo-grinding-options').find('.hpo-grinding-machines');
            const isGround = $this.hasClass('ground');
            
            // Update toggle container state
            $toggleContainer.attr('data-active', isGround ? 'ground' : 'whole');
            
            // Update active states
            $options.removeClass('active');
            $this.addClass('active');
            
            // Update hidden input and trigger price update
            $input.val(isGround ? 'ground' : 'whole');
            
            // Show/hide grinding machines with animation
            if (isGround) {
                $grindingMachines.addClass('show');
                setTimeout(function() {
                    $grindingMachines.find('select').focus();
                }, 300);
            } else {
                $grindingMachines.removeClass('show');
                // Reset grinding machine selection when switching to whole beans
                const $grindingSelect = $('#hpo-grinding-machine');
                if ($grindingSelect.length) {
                    $grindingSelect.val('');
                }
            }
            
            // Update price calculation after toggle
            setTimeout(updateTotalPrice, 100);
        });

        // Initialize the toggle state on page load
        $('.hpo-grinding-toggle').each(function() {
            const $input = $(this).find('input[name="hpo_grinding"]');
            const $toggleContainer = $(this).find('.hpo-toggle-container');
            const $options = $toggleContainer.find('.hpo-toggle-option');
            const $grindingMachines = $(this).closest('.hpo-grinding-options').find('.hpo-grinding-machines');
            const currentValue = $input.val();
            
            // Set initial toggle container state
            $toggleContainer.attr('data-active', currentValue);
            
            if (currentValue === 'ground') {
                $options.filter('.ground').addClass('active');
                $options.filter('.whole').removeClass('active');
                $grindingMachines.addClass('show');
            } else {
                $options.filter('.whole').addClass('active');
                $options.filter('.ground').removeClass('active');
                $grindingMachines.removeClass('show');
            }
        });
    }
    
    // Initialize grinding toggle when document is ready
    initGrindingToggle();
    
    // Also initialize grinding toggle after AJAX content is loaded
    $(document).on('ajaxComplete', function(event, xhr, settings) {
        if (settings.url && settings.url.indexOf('hpo_load_product_details') !== -1) {
            setTimeout(initGrindingToggle, 100);
        }
    });

    // Add event listeners for handling device orientation changes
    $(window).on('resize orientationchange', function() {
        if ($('#hpo-popup-overlay').is(':visible')) {
            adjustPopupHeight();
        }
    });
    
    // Function to adjust popup height based on viewport
    function adjustPopupHeight() {
        var windowHeight = $(window).height();
        var headerHeight = $('.hpo-popup-header').outerHeight();
        $('.hpo-popup-content').css('height', 'calc(100% - ' + headerHeight + 'px)');
        
        // Scroll to active section if any
        if ($('.hpo-option-section:not(.disabled-section)').length) {
            setTimeout(function() {
                $('.hpo-popup-content').scrollTop(0);
            }, 100);
        }
    }
}); 