/**
 * Admin JavaScript for Hierarchical Product Options
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize sortable for categories
        initSortable();
        
        // حداکثر تعداد کاراکتر مجاز برای توضیحات
        var maxDescriptionLength = 53;
        
        // اعمال محدودیت کاراکتر برای تمامی فیلدهای توضیحات
        $(document).on('input', 'textarea[maxlength]', function() {
            var maxLength = parseInt($(this).attr('maxlength'));
            var currentLength = $(this).val().length;
            var remaining = maxLength - currentLength;
            
            // نمایش تعداد کاراکتر باقیمانده
            var $limitInfo = $(this).next('.hpo-limit-info');
            if ($limitInfo.length > 0) {
                if (remaining <= 10) {
                    $limitInfo.addClass('length-warning');
                } else {
                    $limitInfo.removeClass('length-warning');
                }
                $limitInfo.text('تعداد کاراکتر باقیمانده: ' + remaining);
            }
            
            // محدودیت تعداد کاراکتر
            if (currentLength >= maxLength) {
                // قطع متن اضافی
                $(this).val($(this).val().substring(0, maxLength));
            }
        });
        
        // اعمال محدودیت هنگام لود صفحه
        $('textarea[maxlength]').each(function() {
            $(this).trigger('input');
        });
        
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
        
        // بروزرسانی لیست دسته‌بندی‌ها
        function refreshCategorySelect() {
            var data = {
                action: 'hpo_get_fresh_categories',
                nonce: hpo_data.nonce
            };
            
            // نمایش وضعیت بارگذاری
            $('#hpo-category-select').prop('disabled', true)
                .html('<option value="">در حال بارگذاری...</option>');
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success && response.data.categories) {
                    var categories = response.data.categories;
                    var options = '<option value="">' + (hpo_data.strings.select_parent_category || 'یک دسته‌بندی مادر انتخاب کنید...') + '</option>';
                    
                    categories.forEach(function(category) {
                        options += '<option value="' + category.id + '">' + category.name + '</option>';
                    });
                    
                    $('#hpo-category-select').html(options).prop('disabled', false);
                } else {
                    $('#hpo-category-select').html('<option value="">خطا در بارگذاری</option>').prop('disabled', false);
                }
            });
        }

        // افزودن دسته‌بندی جدید
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
                        // بروزرسانی لیست‌های دسته‌بندی و بارگذاری مجدد صفحه
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

        // حذف دسته‌بندی
        $(document).on('click', '.hpo-delete-category', function(e) {
            e.preventDefault();
            
            var categoryId = $(this).data('id');
            
            if (!confirm(hpo_data.strings.confirm_delete_category)) {
                return;
            }
            
            var data = {
                action: 'hpo_delete_category',
                nonce: hpo_data.nonce,
                id: categoryId
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // بروزرسانی تمام لیست‌های دسته‌بندی در صفحه
                    location.reload();
                } else {
                    alert(response.data);
                }
            });
        });

        // فراخوانی تابع بروزرسانی لیست دسته‌بندی‌ها هنگام بارگذاری صفحه
        if ($('#tab-categories').length > 0) {
            refreshCategorySelect();
        }
        
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
                    category_id: $(this).find('[name="category_id"]').val(),
                    description: $(this).find('[name="description"]').val()
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
        
        // بهبود عملکرد انتخاب دسته‌بندی‌ها برای اساین محصول
        $(document).on('change', '#hpo-category-select', function() {
            var selectedCategoryId = $(this).val();
            
            if (selectedCategoryId) {
                // پاک کردن کش دسته‌بندی‌های قبلی
                var data = {
                    action: 'hpo_refresh_categories',
                    nonce: hpo_data.nonce,
                    category_id: selectedCategoryId
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // اگر دسته‌بندی مادر انتخاب شده، فرزندان را هم اضافه کن
                        if (response.data.children && response.data.children.length > 0) {
                            var childInfo = '<div class="hpo-child-info">' + 
                                            '<p>این دسته‌بندی شامل ' + response.data.children.length + 
                                            ' زیرمجموعه است که به طور خودکار انتخاب می‌شوند.</p></div>';
                            $('#hpo-category-select').after(childInfo);
                        } else {
                            $('.hpo-child-info').remove();
                        }
                    }
                });
            } else {
                $('.hpo-child-info').remove();
            }
        });
        
        // حذف بخش توضیحات از بخش Product Assignment
        // این کد باعث می‌شود که فیلد توضیحات در بخش Product Assignment نمایش داده نشود
        $('#hpo-assign-form').find('.hpo-form-row:has(#hpo-product-description)').remove();
        
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
                    category_id: $(this).find('[name="category_id"]').val(),
                    description: $(this).find('[name="description"]').val()
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
            var productDescription = $(this).closest('.hpo-product-item').find('.hpo-item-description').text();
            
            // اگر "بدون توضیحات" بود، آن را خالی کنیم
            if (productDescription.trim() === 'No description' || productDescription.trim() === 'بدون توضیحات') {
                productDescription = '';
            }
            
            var categoryId = $(this).closest('.hpo-products-list').data('category-id');
            
            // Clone the template
            var template = $('#hpo-new-product-template').html();
            var $form = $('<div class="hpo-modal-overlay"></div>').html(template);
            
            // Set the values
            $form.find('[name="name"]').val(productName);
            $form.find('[name="price"]').val(productPrice);
            $form.find('[name="description"]').val(productDescription);
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
                    category_id: $(this).find('[name="category_id"]').val(),
                    description: $(this).find('[name="description"]').val()
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
        
        // اساین خودکار دسته‌بندی‌ها
        $(document).on('submit', '#hpo-assign-form', function(e) {
            e.preventDefault();
            
            var wc_product_id = $('#hpo-wc-product').val();
            var category_id = $('#hpo-category-select').val();
            
            if (!wc_product_id || !category_id) {
                alert(hpo_data.strings.select_required);
                return;
            }
            
            var data = {
                action: 'hpo_assign_product_categories',
                nonce: hpo_data.nonce,
                wc_product_id: wc_product_id,
                category_id: category_id
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // پاک کردن فرم و بارگذاری مجدد صفحه
                    $('#hpo-assign-form')[0].reset();
                    $('.hpo-child-info').remove();
                    location.reload();
                } else {
                    alert(response.data);
                }
            });
        });
        
        // Delete assignment
        $(document).on('click', '.hpo-delete-assignment', function(e) {
            e.preventDefault();
            
            if (!confirm(hpo_data.strings.confirm_delete_assignment)) {
                return;
            }
            
            var categoryId = $(this).data('category-id');
            var productId = $(this).data('product-id');
            var $row = $(this).closest('tr');
            
            var data = {
                action: 'hpo_delete_assignment',
                nonce: hpo_data.nonce,
                category_id: categoryId,
                wc_product_id: productId
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // Get the number of remaining rows for this product
                    var rowCount = $row.siblings('tr').length + 1;
                    
                    // If this was the only category for the product, reload the page
                    if (rowCount === 1) {
                        location.reload();
                    } else {
                        // Otherwise, adjust the rowspan of the product cell and remove just this row
                        var $productCell = $row.siblings('tr:first').find('td:first');
                        if ($productCell.length === 0) {
                            $productCell = $row.find('td:first');
                            
                            // If we're removing the first row, we need to move the product cell to the next row
                            if ($productCell.attr('rowspan')) {
                                var $nextRow = $row.next('tr');
                                if ($nextRow.length) {
                                    var $newCell = $('<td></td>').attr('rowspan', parseInt($productCell.attr('rowspan')) - 1);
                                    $newCell.html($productCell.html());
                                    $nextRow.prepend($newCell);
                                }
                            }
                        } else if ($productCell.attr('rowspan')) {
                            $productCell.attr('rowspan', parseInt($productCell.attr('rowspan')) - 1);
                        }
                        
                        // Remove the row
                        $row.remove();
                    }
                } else {
                    alert(response.data);
                }
            });
        });
        
        // ویرایش توضیحات
        $(document).on('click', '.hpo-edit-description', function(e) {
            e.preventDefault();
            
            var $wrapper = $(this).closest('.hpo-product-description-wrapper');
            var $descText = $wrapper.find('.hpo-desc-text');
            var currentDescription = $descText.text().trim();
            var assignmentId = $(this).data('id');
            var productId = $(this).data('product-id');
            
            // Replace text with textarea for editing
            $descText.hide();
            $(this).hide();
            
            var $editForm = $('<div class="hpo-inline-edit">' +
                '<textarea maxlength="53" rows="2">' + currentDescription + '</textarea>' +
                '<div class="hpo-limit-info">حداکثر 53 کاراکتر مجاز است</div>' +
                '<div class="hpo-desc-actions">' +
                '<button class="button hpo-save-desc" type="button">ذخیره</button> ' +
                '<button class="button hpo-cancel-desc" type="button">انصراف</button>' +
                '</div></div>');
            
            $wrapper.append($editForm);
            
            // Focus the textarea
            $editForm.find('textarea').focus().trigger('input');
            
            // Handle save button
            $editForm.find('.hpo-save-desc').on('click', function() {
                var newDescription = $editForm.find('textarea').val();
                
                // Send AJAX request to update
                var data = {
                    action: 'hpo_update_assignment_description',
                    nonce: hpo_data.nonce,
                    assignment_id: assignmentId,
                    product_id: productId,
                    description: newDescription
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // Update the text and show it again
                        $descText.text(newDescription);
                        if (newDescription === '') {
                            $descText.addClass('no-description').text('برای افزودن توضیحات کلیک کنید');
                        } else {
                            $descText.removeClass('no-description');
                        }
                        
                        $descText.show();
                        $('.hpo-edit-description').show();
                        $editForm.remove();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            // Handle cancel button
            $editForm.find('.hpo-cancel-desc').on('click', function() {
                $descText.show();
                $('.hpo-edit-description').show();
                $editForm.remove();
            });
        });
        
        // محدودیت کاراکتر برای ویرایش توضیحات اساین
        $(document).on('input', '.hpo-inline-edit textarea', function() {
            var maxLength = 53;
            var currentLength = $(this).val().length;
            var remaining = maxLength - currentLength;
            
            // نمایش تعداد کاراکتر باقیمانده
            var $limitInfo = $(this).next('.hpo-limit-info');
            if (remaining <= 10) {
                $limitInfo.addClass('length-warning');
            } else {
                $limitInfo.removeClass('length-warning');
            }
            
            $limitInfo.text('تعداد کاراکتر باقیمانده: ' + remaining);
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
            
            var $button = $(this);
            var $result = $('#hpo-rebuild-result');
            
            $button.prop('disabled', true).text('در حال بازسازی...');
            $result.hide();
            
            var data = {
                action: 'hpo_rebuild_tables',
                nonce: hpo_data.nonce
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                $button.prop('disabled', false).text('بازسازی جداول پایگاه داده');
                
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p>' + hpo_data.strings.rebuild_success + '</p></div>').show();
                } else {
                    $result.html('<div class="notice notice-error inline"><p>' + hpo_data.strings.rebuild_error + '</p></div>').show();
                }
            });
        });

        // Grinder Management
        // Load all grinders on tab open
        $('.nav-tab[href="#tab-grinders"]').on('click', function(e) {
            loadGrinders();
        });

        // Load grinders
        function loadGrinders() {
            var data = {
                action: 'hpo_get_grinders',
                nonce: hpo_data.nonce
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    renderGrinders(response.data);
                } else {
                    // Check if response.data is an object with a message property
                    var errorMessage = response.data;
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    console.error('AJAX error response:', response);
                    alert(errorMessage || 'Error loading grinders');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                console.error('Response text:', xhr.responseText);
                alert('Failed to load grinders: ' + error);
            });
        }

        // Render grinders in the table
        function renderGrinders(grinders) {
            var $container = $('#hpo-grinders-list');
            $container.empty();
            
            if (grinders && grinders.length > 0) {
                $.each(grinders, function(index, grinder) {
                    var $row = $('<tr data-id="' + grinder.id + '" class="hpo-grinder-row">');
                    $row.append('<td class="hpo-handle-column"><span class="hpo-drag-handle dashicons dashicons-menu"></span></td>');
                    $row.append('<td>' + grinder.id + '</td>');
                    $row.append('<td class="hpo-grinder-name">' + grinder.name + '</td>');
                    $row.append('<td class="hpo-grinder-price">' + grinder.price + '</td>');
                    
                    var $actions = $('<td class="hpo-actions-column">');
                    $actions.append('<button type="button" class="button button-small hpo-edit-grinder">' + (hpo_data.strings.edit || 'Edit') + '</button> ');
                    $actions.append('<button type="button" class="button button-small hpo-delete-grinder">' + (hpo_data.strings.delete || 'Delete') + '</button>');
                    
                    $row.append($actions);
                    $container.append($row);
                });
                
                // Make grinder rows sortable
                $container.sortable({
                    handle: '.hpo-drag-handle',
                    update: function(event, ui) {
                        updateGrinderOrder();
                    }
                });
            } else {
                $container.append('<tr class="hpo-empty-row"><td colspan="5">' + (hpo_data.strings.no_grinders || 'No grinder options found') + '</td></tr>');
            }
        }

        // Add new grinder
        $('#hpo-add-grinder-form').on('submit', function(e) {
            e.preventDefault();
            
            var name = $('#hpo-grinder-name').val();
            var price = $('#hpo-grinder-price').val();
            
            if (!name) {
                alert(hpo_data.strings.grinder_name_required || 'Grinder name is required');
                return;
            }
            
            var data = {
                action: 'hpo_add_grinder',
                nonce: hpo_data.nonce,
                name: name,
                price: price
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // Clear the form
                    $('#hpo-grinder-name').val('');
                    $('#hpo-grinder-price').val('0');
                    
                    // Reload grinders
                    loadGrinders();
                } else {
                    // Check if response.data is an object with a message property
                    var errorMessage = response.data;
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    console.error('AJAX error response:', response);
                    alert(errorMessage || 'Error adding grinder');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                console.error('Response text:', xhr.responseText);
                alert('Failed to add grinder: ' + error);
            });
        });

        // Edit grinder
        $(document).on('click', '.hpo-edit-grinder', function(e) {
            e.preventDefault();
            
            var $row = $(this).closest('tr');
            var grinderId = $row.data('id');
            var grinderName = $row.find('.hpo-grinder-name').text();
            var grinderPrice = $row.find('.hpo-grinder-price').text();
            
            // Create edit form
            var $editForm = $('<div class="hpo-modal-overlay">' +
                '<div class="hpo-modal">' +
                    '<h3>' + (hpo_data.strings.edit_grinder || 'Edit Grinder Option') + '</h3>' +
                    '<form id="hpo-edit-grinder-form">' +
                        '<div class="form-field">' +
                            '<label for="hpo-edit-grinder-name">' + (hpo_data.strings.name || 'Name') + '</label>' +
                            '<input type="text" id="hpo-edit-grinder-name" name="name" value="' + grinderName + '" required>' +
                        '</div>' +
                        '<div class="form-field">' +
                            '<label for="hpo-edit-grinder-price">' + (hpo_data.strings.price || 'Price') + '</label>' +
                            '<input type="number" id="hpo-edit-grinder-price" name="price" step="0.01" min="0" value="' + grinderPrice + '">' +
                        '</div>' +
                        '<div class="form-field">' +
                            '<button type="submit" class="button button-primary">' + (hpo_data.strings.save || 'Save') + '</button>' +
                            '<button type="button" class="button hpo-cancel">' + (hpo_data.strings.cancel || 'Cancel') + '</button>' +
                        '</div>' +
                    '</form>' +
                '</div>' +
            '</div>');
            
            // Add to the page
            $('body').append($editForm);
            
            // Handle form submission
            $editForm.find('form').on('submit', function(e) {
                e.preventDefault();
                
                var data = {
                    action: 'hpo_update_grinder',
                    nonce: hpo_data.nonce,
                    id: grinderId,
                    name: $('#hpo-edit-grinder-name').val(),
                    price: $('#hpo-edit-grinder-price').val()
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // Remove the edit form
                        $editForm.remove();
                        
                        // Reload grinders
                        loadGrinders();
                    } else {
                        // Check if response.data is an object with a message property
                        var errorMessage = response.data;
                        if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        console.error('AJAX error response:', response);
                        alert(errorMessage || 'Error updating grinder');
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX request failed:', status, error);
                    console.error('Response text:', xhr.responseText);
                    alert('Failed to update grinder: ' + error);
                });
            });
            
            // Handle cancel button
            $editForm.find('.hpo-cancel').on('click', function() {
                $editForm.remove();
            });
        });

        // Delete grinder
        $(document).on('click', '.hpo-delete-grinder', function(e) {
            e.preventDefault();
            
            if (!confirm(hpo_data.strings.confirm_delete_grinder || 'Are you sure you want to delete this grinder option?')) {
                return;
            }
            
            var grinderId = $(this).closest('tr').data('id');
            
            var data = {
                action: 'hpo_delete_grinder',
                nonce: hpo_data.nonce,
                id: grinderId
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // Reload grinders
                    loadGrinders();
                } else {
                    // Check if response.data is an object with a message property
                    var errorMessage = response.data;
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    console.error('AJAX error response:', response);
                    alert(errorMessage || 'Error deleting grinder');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                console.error('Response text:', xhr.responseText);
                alert('Failed to delete grinder: ' + error);
            });
        });

        // Update grinder order
        function updateGrinderOrder() {
            var items = [];
            
            $('.hpo-grinder-row').each(function() {
                items.push($(this).data('id'));
            });
            
            var data = {
                action: 'hpo_reorder_grinders',
                nonce: hpo_data.nonce,
                items: items
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (!response.success) {
                    // Check if response.data is an object with a message property
                    var errorMessage = response.data;
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    console.error('AJAX error response:', response);
                    alert(errorMessage || 'Error reordering grinders');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                console.error('Response text:', xhr.responseText);
                alert('Failed to reorder grinders: ' + error);
            });
        }

        // پاکسازی داده‌های ناسازگار
        $('#hpo-clean-data').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#hpo-clean-result');
            
            $button.prop('disabled', true).text('در حال پاکسازی...');
            $result.hide();
            
            var data = {
                action: 'hpo_clean_inconsistent_data',
                nonce: hpo_data.nonce
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                $button.prop('disabled', false).text('پاکسازی داده‌های ناسازگار');
                
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>').show();
                } else {
                    $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>').show();
                }
            });
        });
    });
    
})(jQuery); 