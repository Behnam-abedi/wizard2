/**
 * Admin JavaScript for Hierarchical Product Options
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize sortable for categories
        initSortable();
        
        // Tab navigation - make sure this is properly active
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            // Hide all tab content
            $('.tab-content').hide();
            
            // Show the selected tab content
            var target = $(this).attr('href');
            $(target).show();
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            console.log('Tab clicked:', target);
        });
        
        // Make sure the correct tab is shown initially
        var hash = window.location.hash;
        if (hash) {
            $('.nav-tab[href="' + hash + '"]').trigger('click');
        } else {
            // Default tab
            $('.nav-tab:first').trigger('click');
        }
        
        // Add new category
        $('.hpo-add-category').on('click', function(e) {
            e.preventDefault();
            
            // Clone the template
            var template = $('#hpo-new-category-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Add to the page
            $('body').append($form);
            
            // Handle form submission
            $form.find('form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'hpo_add_category',
                    nonce: hpo_data.nonce,
                    name: $(this).find('[name="name"]').val(),
                    parent_id: $(this).find('[name="parent_id"]').val()
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // Reload the page to show the new category
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            // Handle cancel
            $form.find('.hpo-cancel').on('click', function() {
                $form.remove();
            });
        });
        
        // Add new product
        $('.hpo-add-product').on('click', function(e) {
            e.preventDefault();
            
            // Clone the template
            var template = $('#hpo-new-product-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Add to the page
            $('body').append($form);
            
            // Handle form submission
            $form.find('form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'hpo_add_product',
                    nonce: hpo_data.nonce,
                    name: $(this).find('[name="name"]').val(),
                    price: $(this).find('[name="price"]').val(),
                    category_id: $(this).find('[name="category_id"]').val()
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // Reload the page to show the new product
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            // Handle cancel
            $form.find('.hpo-cancel').on('click', function() {
                $form.remove();
            });
        });
        
        // Add sub-category
        $(document).on('click', '.hpo-add-subcategory', function(e) {
            e.preventDefault();
            
            var parentId = $(this).data('parent-id');
            
            // Clone the template
            var template = $('#hpo-new-category-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Set the parent ID
            $form.find('[name="parent_id"]').val(parentId);
            
            // Add to the page
            $('body').append($form);
            
            // Handle form submission
            $form.find('form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'hpo_add_category',
                    nonce: hpo_data.nonce,
                    name: $(this).find('[name="name"]').val(),
                    parent_id: $(this).find('[name="parent_id"]').val()
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // Reload the page to show the new category
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            // Handle cancel
            $form.find('.hpo-cancel').on('click', function() {
                $form.remove();
            });
        });
        
        // Add product to category
        $(document).on('click', '.hpo-add-product-to-category', function(e) {
            e.preventDefault();
            
            var categoryId = $(this).data('category-id');
            
            // Clone the template
            var template = $('#hpo-new-product-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Set the category ID
            $form.find('[name="category_id"]').val(categoryId);
            
            // Add to the page
            $('body').append($form);
            
            // Handle form submission
            $form.find('form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'hpo_add_product',
                    nonce: hpo_data.nonce,
                    name: $(this).find('[name="name"]').val(),
                    price: $(this).find('[name="price"]').val(),
                    category_id: $(this).find('[name="category_id"]').val()
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // Reload the page to show the new product
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            // Handle cancel
            $form.find('.hpo-cancel').on('click', function() {
                $form.remove();
            });
        });
        
        // Edit category
        $(document).on('click', '.hpo-edit-category', function(e) {
            e.preventDefault();
            
            var categoryId = $(this).data('id');
            var categoryName = $(this).closest('.hpo-item-header').find('.hpo-item-name').text();
            
            // Clone the template
            var template = $('#hpo-new-category-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Set the values
            $form.find('[name="name"]').val(categoryName);
            $form.find('button[type="submit"]').text(hpo_data.strings.save);
            
            // Add to the page
            $('body').append($form);
            
            // Handle form submission
            $form.find('form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'hpo_update_category',
                    nonce: hpo_data.nonce,
                    id: categoryId,
                    name: $(this).find('[name="name"]').val(),
                    parent_id: $(this).find('[name="parent_id"]').val()
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // Reload the page to show the changes
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            // Handle cancel
            $form.find('.hpo-cancel').on('click', function() {
                $form.remove();
            });
        });
        
        // Edit product
        $(document).on('click', '.hpo-edit-product', function(e) {
            e.preventDefault();
            
            var productId = $(this).data('id');
            var productName = $(this).closest('.hpo-item-header').find('.hpo-item-name').text();
            var productPrice = $(this).closest('.hpo-item-header').find('.hpo-item-price').text().replace(/[^0-9.]/g, '');
            var categoryId = $(this).closest('.hpo-products-list').data('category-id');
            
            // Clone the template
            var template = $('#hpo-new-product-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Set the values
            $form.find('[name="name"]').val(productName);
            $form.find('[name="price"]').val(productPrice);
            $form.find('[name="category_id"]').val(categoryId);
            $form.find('button[type="submit"]').text(hpo_data.strings.save);
            
            // Add to the page
            $('body').append($form);
            
            // Handle form submission
            $form.find('form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'hpo_update_product',
                    nonce: hpo_data.nonce,
                    id: productId,
                    name: $(this).find('[name="name"]').val(),
                    price: $(this).find('[name="price"]').val(),
                    category_id: $(this).find('[name="category_id"]').val()
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // Reload the page to show the changes
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            // Handle cancel
            $form.find('.hpo-cancel').on('click', function() {
                $form.remove();
            });
        });
        
        // Delete category
        $(document).on('click', '.hpo-delete-category', function(e) {
            e.preventDefault();
            
            if (!confirm(hpo_data.strings.confirm_delete)) {
                return;
            }
            
            var categoryId = $(this).data('id');
            
            var data = {
                action: 'hpo_delete_category',
                nonce: hpo_data.nonce,
                id: categoryId
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // Reload the page to show the changes
                    location.reload();
                } else {
                    alert(response.data);
                }
            });
        });
        
        // Delete product
        $(document).on('click', '.hpo-delete-product', function(e) {
            e.preventDefault();
            
            if (!confirm(hpo_data.strings.confirm_delete)) {
                return;
            }
            
            var productId = $(this).data('id');
            
            var data = {
                action: 'hpo_delete_product',
                nonce: hpo_data.nonce,
                id: productId
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // Reload the page to show the changes
                    location.reload();
                } else {
                    alert(response.data);
                }
            });
        });
        
        // Handle product assignment form
        $('#hpo-assign-form').on('submit', function(e) {
            e.preventDefault();
            
            var wc_product_id = $(this).find('[name="wc_product_id"]').val();
            var category_ids = [];
            
            $(this).find('[name="category_ids[]"]:checked').each(function() {
                category_ids.push($(this).val());
            });
            
            if (!wc_product_id) {
                alert('Please select a product');
                return;
            }
            
            var data = {
                action: 'hpo_assign_product_categories',
                nonce: hpo_data.nonce,
                wc_product_id: wc_product_id,
                category_ids: category_ids
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    alert('Categories assigned successfully!');
                } else {
                    alert(response.data);
                }
            });
        });
        
        /**
         * Initialize sortable functionality
         */
        function initSortable() {
            // Make categories sortable
            $('#hpo-sortable-categories, .hpo-categories-list').sortable({
                handle: '.hpo-drag-handle',
                items: '> li',
                placeholder: 'hpo-sortable-placeholder',
                connectWith: '.hpo-categories-list',
                update: function(event, ui) {
                    // Skip if this is not the receiving list
                    if (this !== ui.item.parent()[0]) {
                        return;
                    }
                    
                    var item = ui.item;
                    var itemId = item.data('id');
                    var newParentId = 0;
                    var sortOrder = item.index();
                    
                    // If the item is in a sub-list, get the parent ID
                    var $parentList = item.closest('.hpo-categories-list');
                    if ($parentList.closest('.hpo-category-item').length) {
                        newParentId = $parentList.closest('.hpo-category-item').data('id');
                    }
                    
                    // Update the order via AJAX
                    updateItemOrder('category', itemId, newParentId, sortOrder);
                }
            });
            
            // Make products sortable
            $('.hpo-products-list').sortable({
                handle: '.hpo-drag-handle',
                items: '> li',
                placeholder: 'hpo-sortable-placeholder',
                connectWith: '.hpo-products-list',
                update: function(event, ui) {
                    // Skip if this is not the receiving list
                    if (this !== ui.item.parent()[0]) {
                        return;
                    }
                    
                    var item = ui.item;
                    var itemId = item.data('id');
                    var newCategoryId = item.closest('.hpo-products-list').data('category-id');
                    var sortOrder = item.index();
                    
                    // Update the order via AJAX
                    updateItemOrder('product', itemId, newCategoryId, sortOrder);
                }
            });
        }
        
        /**
         * Update item order via AJAX
         */
        function updateItemOrder(itemType, itemId, parentId, sortOrder) {
            var data = {
                action: 'hpo_update_order',
                nonce: hpo_data.nonce,
                item_type: itemType,
                item_id: itemId,
                parent_id: parentId,
                sort_order: sortOrder
            };
            
            $.post(hpo_data.ajax_url, data);
        }
        
        // Weight options functionality
        $('#hpo-weight-product').on('change', function() {
            var productId = $(this).val();
            
            if (productId) {
                // Show the weights container
                $('#hpo-weights-container').show();
                
                // Load weight options for this product
                loadWeights(productId);
            } else {
                // Hide the weights container
                $('#hpo-weights-container').hide();
            }
        });
        
        // Load weights for a product
        function loadWeights(productId) {
            var data = {
                action: 'hpo_get_weights',
                nonce: hpo_data.nonce,
                wc_product_id: productId
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    renderWeights(response.data);
                } else {
                    alert(response.data);
                }
            });
        }
        
        // Render weights
        function renderWeights(weights) {
            var $container = $('.hpo-weight-list');
            $container.empty();
            
            if (weights && weights.length > 0) {
                $.each(weights, function(index, weight) {
                    var template = $('#hpo-weight-template').html();
                    template = template.replace(/{id}/g, weight.id)
                                     .replace(/{name}/g, weight.name)
                                     .replace(/{coefficient}/g, weight.coefficient);
                    
                    $container.append(template);
                });
                
                // Make weight items sortable
                $container.sortable({
                    update: function(event, ui) {
                        updateWeightOrder();
                    }
                });
            } else {
                // Use a default message if the string is not available
                var noWeightsMessage = hpo_data.strings && hpo_data.strings.no_weights ? 
                                      hpo_data.strings.no_weights : 'No weight options found for this product.';
                $container.append('<p>' + noWeightsMessage + '</p>');
            }
        }
        
        // Add new weight option
        $('#hpo-add-weight').on('click', function(e) {
            e.preventDefault();
            
            var productId = $('#hpo-weight-product').val();
            console.log('Selected product ID:', productId);
            
            if (!productId) {
                // Use a default message if the string is not available
                var selectProductMessage = hpo_data.strings && hpo_data.strings.select_product ? 
                                         hpo_data.strings.select_product : 'Please select a product first.';
                alert(selectProductMessage);
                return;
            }
            
            // Clone the template
            var template = $('#hpo-new-weight-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Set the product ID
            $form.find('[name="wc_product_id"]').val(productId);
            console.log('Setting product ID in form:', productId);
            
            // Add to the page
            $('body').append($form);
            
            // Handle form submission
            $form.find('form').on('submit', function(e) {
                e.preventDefault();
                console.log('Form submitted');
                
                var name = $(this).find('[name="name"]').val();
                var coefficient = $(this).find('[name="coefficient"]').val();
                var wc_product_id = $(this).find('[name="wc_product_id"]').val();
                
                console.log('Form data:', {
                    name: name,
                    coefficient: coefficient,
                    wc_product_id: wc_product_id
                });
                
                var data = {
                    action: 'hpo_add_weight',
                    nonce: hpo_data.nonce,
                    name: name,
                    coefficient: coefficient,
                    wc_product_id: wc_product_id
                };
                
                console.log('Sending AJAX request:', data);
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    console.log('AJAX response:', response);
                    if (response && response.success) {
                        // Reload the weights
                        loadWeights(productId);
                        
                        // Close the modal
                        $form.remove();
                    } else {
                        var errorMsg = (response && response.data) ? response.data : 'Error adding weight option: No response data';
                        console.error('Error:', errorMsg);
                        alert(errorMsg);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX request failed:', status, error);
                    console.error('Response text:', xhr.responseText);
                    
                    // Try to parse the response if it's JSON
                    var responseData = '';
                    try {
                        if (xhr.responseText) {
                            var jsonResponse = JSON.parse(xhr.responseText);
                            if (jsonResponse && jsonResponse.data) {
                                responseData = jsonResponse.data;
                            }
                        }
                    } catch (e) {
                        responseData = xhr.responseText || 'Unknown error';
                    }
                    
                    alert('Failed to add weight option: ' + responseData);
                });
            });
            
            // Handle cancel button
            $form.find('.hpo-cancel').on('click', function() {
                $form.remove();
            });
        });
        
        // Edit weight option
        $(document).on('click', '.hpo-edit-weight', function(e) {
            e.preventDefault();
            
            var weightId = $(this).closest('.hpo-weight-item').data('id');
            var weightName = $(this).closest('.hpo-weight-header').find('.hpo-weight-name').text();
            var weightCoefficient = $(this).closest('.hpo-weight-header').find('.hpo-weight-coefficient').text().replace(/[^0-9.]/g, '');
            var productId = $('#hpo-weight-product').val();
            
            // Clone the template
            var template = $('#hpo-new-weight-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Set the values
            $form.find('[name="name"]').val(weightName);
            $form.find('[name="coefficient"]').val(weightCoefficient);
            $form.find('[name="wc_product_id"]').val(productId);
            $form.find('h3').text(hpo_data.strings.edit_weight);
            $form.find('button[type="submit"]').text(hpo_data.strings.save);
            
            // Add to the page
            $('body').append($form);
            
            // Handle form submission
            $form.find('form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'hpo_update_weight',
                    nonce: hpo_data.nonce,
                    id: weightId,
                    name: $(this).find('[name="name"]').val(),
                    coefficient: $(this).find('[name="coefficient"]').val()
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // Reload the weights
                        loadWeights(productId);
                        
                        // Close the modal
                        $form.remove();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            // Handle cancel button
            $form.find('.hpo-cancel').on('click', function() {
                $form.remove();
            });
        });
        
        // Delete weight option
        $(document).on('click', '.hpo-delete-weight', function(e) {
            e.preventDefault();
            
            if (!confirm(hpo_data.strings.confirm_delete_weight)) {
                return;
            }
            
            var weightId = $(this).closest('.hpo-weight-item').data('id');
            var productId = $('#hpo-weight-product').val();
            
            var data = {
                action: 'hpo_delete_weight',
                nonce: hpo_data.nonce,
                id: weightId
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // Reload the weights
                    loadWeights(productId);
                } else {
                    alert(response.data);
                }
            });
        });
        
        // Update weight order
        function updateWeightOrder() {
            var items = [];
            
            $('.hpo-weight-item').each(function() {
                items.push($(this).data('id'));
            });
            
            var data = {
                action: 'hpo_reorder_weights',
                nonce: hpo_data.nonce,
                items: items
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (!response.success) {
                    alert(response.data);
                }
            });
        }

        // Rebuild database tables
        $('#hpo-rebuild-tables').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to rebuild the database tables? This will not delete your data, but it will ensure all required tables exist.')) {
                return;
            }
            
            var $button = $(this);
            var $result = $('#hpo-rebuild-result');
            
            $button.prop('disabled', true).text('Working...');
            $result.show().html('<p>Rebuilding tables...</p>');
            
            var data = {
                action: 'hpo_rebuild_tables',
                nonce: hpo_data.nonce
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                $button.prop('disabled', false).text('Rebuild Database Tables');
                
                if (response.success) {
                    var successMsg = response.data || 'Tables rebuilt successfully!';
                    // Format the response data for better readability
                    if (typeof successMsg === 'string' && successMsg.includes('\n')) {
                        successMsg = successMsg.replace(/\n/g, '<br>');
                    }
                    $result.html('<div style="color: green; background: #f0f8f0; padding: 10px; border: 1px solid #d0e0d0; border-radius: 4px;">' + successMsg + '</div>');
                } else {
                    var errorMsg = response.data || 'Unknown error';
                    $result.html('<p style="color: red; background: #fff0f0; padding: 10px; border: 1px solid #e0d0d0; border-radius: 4px;">Error: ' + errorMsg + '</p>');
                }
            }).fail(function(xhr, status, error) {
                $button.prop('disabled', false).text('Rebuild Database Tables');
                $result.html('<p style="color: red; background: #fff0f0; padding: 10px; border: 1px solid #e0d0d0; border-radius: 4px;">Error: Could not complete the request. ' + (status || '') + ' ' + (error || '') + '</p>');
                console.error('AJAX error:', xhr.responseText);
            });
        });
    });
    
})(jQuery); 