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
            
            var $form = $(this);
            var $submitButton = $form.find('button[type="submit"]');
            var originalText = $submitButton.text();
            
            var wc_product_id = $('#hpo-wc-product').val();
            var category_id = $('#hpo-category-select').val();
            
            if (!wc_product_id || !category_id) {
                alert(hpo_data.strings.select_required || 'لطفاً محصول و دسته‌بندی را انتخاب کنید.');
                return;
            }
            
            // نمایش لودینگ
            $submitButton.prop('disabled', true).text('در حال ذخیره...');
            
            var data = {
                action: 'hpo_assign_product_categories',
                nonce: hpo_data.nonce,
                wc_product_id: wc_product_id,
                category_id: category_id
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // پاک کردن فرم
                    $form[0].reset();
                    $('.hpo-child-info').remove();
                    
                    // نمایش پیام موفقیت
                    showSuccessMessage('دسته‌بندی با موفقیت به محصول اختصاص داده شد.');
                    
                    // بارگذاری مجدد جدول تخصیص‌ها
                    refreshAssignmentsTable();
                } else {
                    alert(response.data);
                }
                
                // بازگرداندن دکمه به حالت اولیه
                $submitButton.prop('disabled', false).text(originalText);
            });
        });
        
        // تابع نمایش پیام موفقیت
        function showSuccessMessage(message) {
            var $message = $('<div class="hpo-success-message">' + message + '</div>');
            $('.hpo-admin-panel').first().prepend($message);
            
            setTimeout(function() {
                $message.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
        // تابع بارگذاری مجدد جدول تخصیص‌ها
        function refreshAssignmentsTable() {
            var $table = $('#hpo-assignments-list').closest('.hpo-current-assignments');
            $table.addClass('hpo-loading');
            
            setTimeout(function() {
                location.reload();
            }, 500);
        }
        
        // Delete assignment
        $(document).on('click', '.hpo-delete-assignment', function(e) {
            e.preventDefault();
            
            if (!confirm(hpo_data.strings.confirm_delete_assignment)) {
                return;
            }
            
            var $button = $(this);
            var categoryId = $button.data('category-id');
            var productId = $button.data('product-id');
            
            // نمایش لودینگ
            var $row = $button.closest('tr');
            $row.addClass('hpo-loading');
            $button.prop('disabled', true);
            
            var data = {
                action: 'hpo_delete_assignment',
                nonce: hpo_data.nonce,
                category_id: categoryId,
                product_id: productId
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // پاک کردن لودینگ و حذف ردیف
                    $row.removeClass('hpo-loading').fadeOut(300, function() {
                        $(this).remove();
                        
                        // اگر آخرین ردیف برای این محصول بود، کل ردیف محصول را حذف کن
                        var $productRows = $('tr[data-product-id="' + productId + '"]');
                        if ($productRows.length === 0) {
                            location.reload(); // ریفرش کامل اگر آخرین ردیف بود
                        }
                    });
                } else {
                    // نمایش خطا و پاک کردن لودینگ
                    alert(response.data);
                    $row.removeClass('hpo-loading');
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Edit assignment description
        $(document).on('click', '.hpo-edit-description', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var productId = $button.data('product-id');
            var description = $button.data('description');
            
            // اگر "برای افزودن توضیحات کلیک کنید" بود، آن را خالی کنیم
            if (description === hpo_data.strings.click_to_add_desc || description === 'برای افزودن توضیحات کلیک کنید') {
                description = '';
            }
            
            // ایجاد مدال ویرایش توضیحات
            var $modal = $('<div class="hpo-modal-overlay"></div>');
            var $content = $('<div class="hpo-modal-content"></div>');
            $content.append('<h3>' + hpo_data.strings.edit_description + '</h3>');
            
            var $form = $('<form></form>');
            $form.append('<div class="hpo-form-row"><label for="hpo-description">' + hpo_data.strings.description + '</label></div>');
            $form.append('<div class="hpo-form-row"><textarea id="hpo-description" name="description" rows="3" maxlength="53" dir="rtl">' + description + '</textarea></div>');
            $form.append('<div class="hpo-limit-info">حداکثر 53 کاراکتر مجاز است</div>');
            $form.append('<div class="hpo-form-row hpo-form-actions"><button type="submit" class="button button-primary">' + hpo_data.strings.save + '</button> <button type="button" class="button hpo-cancel">' + hpo_data.strings.cancel + '</button></div>');
            
            $content.append($form);
            $modal.append($content);
            $('body').append($modal);
            
            // تنظیم محدودیت کاراکتر
            var $textarea = $modal.find('textarea');
            var $limitInfo = $modal.find('.hpo-limit-info');
            
            // Focus on textarea after modal is shown
            setTimeout(function() {
                $textarea.focus();
            }, 100);
            
            $textarea.on('input', function() {
                var maxLength = parseInt($(this).attr('maxlength'));
                var currentLength = $(this).val().length;
                var remaining = maxLength - currentLength;
                
                if (remaining <= 10) {
                    $limitInfo.addClass('length-warning');
                } else {
                    $limitInfo.removeClass('length-warning');
                }
                
                $limitInfo.text('تعداد کاراکتر باقیمانده: ' + remaining);
            }).trigger('input');
            
            // Close on Escape key
            $(document).on('keydown.hpo_modal', function(e) {
                if (e.keyCode === 27) { // Escape key
                    $modal.remove();
                    $(document).off('keydown.hpo_modal');
                }
            });
            
            // ارسال فرم
            $form.on('submit', function(e) {
                e.preventDefault();
                
                var newDescription = $textarea.val();
                var $descText = $button.prev('.hpo-desc-text');
                
                // نمایش لودینگ
                $button.prop('disabled', true);
                $descText.text('در حال بارگذاری...').addClass('loading');
                
                var data = {
                    action: 'hpo_update_product_description',
                    nonce: hpo_data.nonce,
                    product_id: productId,
                    description: newDescription
                };
                
                $.post(hpo_data.ajax_url, data, function(response) {
                    if (response.success) {
                        // بستن مدال
                        $modal.remove();
                        $(document).off('keydown.hpo_modal');
                        
                        // آپدیت نمایش توضیحات
                        if (newDescription) {
                            $descText.text(newDescription).removeClass('no-description');
                            $button.data('description', newDescription);
                        } else {
                            $descText.text(hpo_data.strings.click_to_add_desc || 'برای افزودن توضیحات کلیک کنید').addClass('no-description');
                            $button.data('description', '');
                        }
                        
                        // حذف لودینگ
                        $descText.removeClass('loading');
                        $button.prop('disabled', false);
                        
                        // Show success message
                        showSuccessMessage(hpo_data.strings.description_updated || 'توضیحات با موفقیت بروزرسانی شد');
                    } else {
                        // نمایش خطا
                        alert(response.data);
                        $descText.removeClass('loading');
                        $button.prop('disabled', false);
                    }
                });
            });
            
            // لغو
            $modal.find('.hpo-cancel').on('click', function() {
                $modal.remove();
                $(document).off('keydown.hpo_modal');
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
                tolerance: 'pointer',
                start: function(e, ui) {
                    // Add class to the item being sorted
                    ui.item.addClass('hpo-sorting');
                    
                    // Set placeholder height to match item
                    ui.placeholder.height(ui.item.outerHeight());
                },
                stop: function(e, ui) {
                    // Remove sorting class
                    ui.item.removeClass('hpo-sorting');
                },
                update: function(event, ui) {
                    // Skip if this is not the receiving list
                    if (this !== ui.item.parent()[0]) {
                        return;
                    }
                    
                    var $list = $(this);
                    var newParentId = 0;
                    
                    // If the list is in a sub-list, get the parent ID
                    if ($list.closest('.hpo-category-item').length) {
                        newParentId = $list.closest('.hpo-category-item').data('id');
                    }
                    
                    // Add loading indicator to the list
                    $list.addClass('hpo-loading');
                    
                    // Get all items in the list to update their sort order
                    var items = [];
                    $list.children('li').each(function(index) {
                        var $item = $(this);
                        var itemId = $item.data('id');
                        
                        if (itemId) {
                            items.push({
                                id: itemId,
                                parent_id: newParentId,
                                sort_order: index
                            });
                        }
                    });
                    
                    // Update all items at once
                    updateMultipleSortOrders('categories', items, function() {
                        $list.removeClass('hpo-loading');
                    });
                }
            });
            
            // Make products sortable
            $('.hpo-products-list').sortable({
                handle: '.hpo-drag-handle',
                items: '> li',
                placeholder: 'hpo-sortable-placeholder',
                connectWith: '.hpo-products-list',
                tolerance: 'pointer',
                start: function(e, ui) {
                    // Add class to the item being sorted
                    ui.item.addClass('hpo-sorting');
                    
                    // Set placeholder height to match item
                    ui.placeholder.height(ui.item.outerHeight());
                },
                stop: function(e, ui) {
                    // Remove sorting class
                    ui.item.removeClass('hpo-sorting');
                },
                update: function(event, ui) {
                    // Skip if this is not the receiving list
                    if (this !== ui.item.parent()[0]) {
                        return;
                    }
                    
                    var $list = $(this);
                    var categoryId = $list.data('category-id');
                    
                    // Add loading indicator to the list
                    $list.addClass('hpo-loading');
                    
                    // Get all items in the list to update their sort order
                    var items = [];
                    $list.children('li').each(function(index) {
                        var $item = $(this);
                        var itemId = $item.data('id');
                        
                        if (itemId) {
                            items.push({
                                id: itemId,
                                category_id: categoryId,
                                sort_order: index
                            });
                        }
                    });
                    
                    // Update all items at once
                    updateMultipleSortOrders('products', items, function() {
                        $list.removeClass('hpo-loading');
                    });
                }
            });
            
            // Make assignments sortable
            $('#hpo-assignments-list').sortable({
                handle: '.hpo-drag-handle',
                items: 'tr',
                axis: 'y',
                placeholder: 'hpo-sortable-placeholder',
                helper: function(e, tr) {
                    var $originals = tr.children();
                    var $helper = tr.clone();
                    $helper.children().each(function(index) {
                        $(this).width($originals.eq(index).width());
                    });
                    return $helper;
                },
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.height());
                },
                update: function(event, ui) {
                    // نمایش لودینگ
                    var $table = $('#hpo-assignments-list');
                    $table.addClass('hpo-loading');
                    
                    // Get the sorted assignment IDs
                    var assignmentIds = [];
                    $('#hpo-assignments-list tr').each(function() {
                        assignmentIds.push($(this).data('id'));
                    });
                    
                    // Update the orders via AJAX
                    var data = {
                        action: 'hpo_reorder_assignments',
                        nonce: hpo_data.nonce,
                        assignments: assignmentIds
                    };
                    
                    $.post(hpo_data.ajax_url, data, function(response) {
                        // پایان لودینگ
                        $table.removeClass('hpo-loading');
                        
                        if (!response.success) {
                            alert('خطا در به‌روزرسانی ترتیب: ' + response.data);
                        } else {
                            // نمایش پیام موفقیت
                            showSuccessMessage('ترتیب دسته‌بندی‌ها با موفقیت بروزرسانی شد.');
                        }
                    });
                }
            });
            
            // Make category sortable within each product
            $('.hpo-categories-sortable').sortable({
                handle: '.hpo-drag-handle',
                items: 'li',
                placeholder: 'hpo-sortable-placeholder',
                update: function(event, ui) {
                    // Skip if this is not the receiving list
                    if (this !== ui.item.parent()[0]) {
                        return;
                    }
                    
                    var $list = $(this);
                    var productId = $list.data('product-id');
                    $list.closest('td').addClass('hpo-loading');
                    
                    // Get the sorted category IDs
                    var sortData = [];
                    $list.find('li').each(function(index) {
                        sortData.push({
                            id: $(this).data('id'),
                            categoryId: $(this).data('category-id'),
                            position: index
                        });
                    });
                    
                    // Update the orders via AJAX
                    var data = {
                        action: 'hpo_reorder_product_categories',
                        nonce: hpo_data.nonce,
                        product_id: productId,
                        categories: sortData
                    };
                    
                    $.post(hpo_data.ajax_url, data, function(response) {
                        $list.closest('td').removeClass('hpo-loading');
                        
                        if (!response.success) {
                            alert('خطا در به‌روزرسانی ترتیب: ' + response.data);
                        } else {
                            // نمایش پیام موفقیت
                            showSuccessMessage('ترتیب دسته‌بندی‌ها با موفقیت بروزرسانی شد.');
                        }
                    });
                }
            });
        }
        
        /**
         * Update multiple items sort order via AJAX
         */
        function updateMultipleSortOrders(tableType, items, callback) {
            var data = {
                action: 'hpo_update_multiple_sort_orders',
                nonce: hpo_data.nonce,
                table_type: tableType,
                items: items
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    showSuccessMessage(response.data);
                } else {
                    alert('خطا در به‌روزرسانی ترتیب: ' + response.data);
                }
                
                if (typeof callback === 'function') {
                    callback();
                }
            });
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

        // Rebuild tables button
        $('#hpo-rebuild-tables').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(hpo_data.strings.confirm_rebuild_tables)) {
                return;
            }
            
            var $button = $(this);
            var $result = $('#hpo-rebuild-result');
            
            $button.prop('disabled', true).text(hpo_data.strings.rebuilding_tables || 'Rebuilding tables...');
            $result.html('<p>' + (hpo_data.strings.rebuilding_please_wait || 'Please wait...') + '</p>').show();
            
            var data = {
                action: 'hpo_rebuild_tables',
                nonce: hpo_data.nonce
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                $button.prop('disabled', false).text(hpo_data.strings.rebuild_tables || 'Rebuild Database Tables');
                
                if (response.success) {
                    $result.html('<p class="success">' + response.data.message + '</p>');
                } else {
                    $result.html('<p class="error">' + (response.data || 'Error rebuilding tables.') + '</p>');
                }
            });
        });
        
        // Initialize assignments sort order button
        $('#hpo-init-assignments-sort').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to initialize sort order for all category assignments? This will set the default order for all your existing assignments.')) {
                return;
            }
            
            var $button = $(this);
            var $result = $('#hpo-init-assignments-result');
            
            $button.prop('disabled', true).text('Initializing sort order...');
            $result.html('<p>Please wait...</p>').show();
            
            var data = {
                action: 'hpo_init_assignments_sort_order',
                nonce: hpo_data.nonce
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                $button.prop('disabled', false).text('Initialize Assignment Sort Order');
                
                if (response.success) {
                    $result.html('<p class="success">' + response.data.message + '</p>');
                } else {
                    $result.html('<p class="error">' + (response.data || 'Error initializing sort order.') + '</p>');
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

        // Delete all assignments for a product
        $(document).on('click', '.hpo-delete-product-assignments', function(e) {
            e.preventDefault();
            
            if (!confirm(hpo_data.strings.confirm_delete_product_assignments || 'آیا مطمئن هستید که می‌خواهید همه دسته‌بندی‌های این محصول را حذف کنید؟')) {
                return;
            }
            
            var $button = $(this);
            var productId = $button.data('product-id');
            var $row = $button.closest('tr');
            
            // نمایش لودینگ
            $row.addClass('hpo-loading');
            $button.prop('disabled', true);
            
            var data = {
                action: 'hpo_delete_product_assignments',
                nonce: hpo_data.nonce,
                product_id: productId
            };
            
            $.post(hpo_data.ajax_url, data, function(response) {
                if (response.success) {
                    // حذف ردیف و نمایش پیام موفقیت
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        showSuccessMessage('تمام دسته‌بندی‌های محصول با موفقیت حذف شدند.');
                    });
                } else {
                    // نمایش خطا
                    alert(response.data);
                    $row.removeClass('hpo-loading');
                    $button.prop('disabled', false);
                }
            });
        });
    });
    
})(jQuery); 