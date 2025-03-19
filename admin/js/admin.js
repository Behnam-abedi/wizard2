/**
 * Admin JavaScript for Hierarchical Product Options
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize sortable for categories
        initSortable();
        
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
        
        // Tab navigation
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
        });
        
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
                    template = template.replace('{id}', weight.id)
                                     .replace('{name}', weight.name)
                                     .replace('{coefficient}', weight.coefficient);
                    
                    $container.append(template);
                });
                
                // Make weight items sortable
                $container.sortable({
                    update: function(event, ui) {
                        updateWeightOrder();
                    }
                });
            } else {
                $container.append('<p>' + hpo_data.strings.no_weights + '</p>');
            }
        }
        
        // Add new weight option
        $('#hpo-add-weight').on('click', function(e) {
            e.preventDefault();
            
            var productId = $('#hpo-weight-product').val();
            
            if (!productId) {
                alert(hpo_data.strings.select_product);
                return;
            }
            
            // Clone the template
            var template = $('#hpo-new-weight-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Set the product ID
            $form.find('[name="wc_product_id"]').val(productId);
            
            // Add to the page
            $('body').append($form);
            
            // Handle form submission
            $form.find('form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'hpo_add_weight',
                    nonce: hpo_data.nonce,
                    name: $(this).find('[name="name"]').val(),
                    coefficient: $(this).find('[name="coefficient"]').val(),
                    wc_product_id: $(this).find('[name="wc_product_id"]').val()
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
    });
    
})(jQuery); 