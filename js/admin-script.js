jQuery(document).ready(function($) {
    // متغیرهای سراسری
    var nextId = 1;
    
    // تنظیم اولیه
    function init() {
        // پیدا کردن بزرگترین ID موجود برای ادامه دادن
        $("#product-settings-container .item").each(function() {
            var itemId = parseInt($(this).data('id'));
            if (itemId >= nextId) {
                nextId = itemId + 1;
            }
        });
        
        // فعال‌سازی قابلیت دراگ اند دراپ
        initSortable();
        
        // اتصال رویدادها
        bindEvents();
    }
    
    // فعال‌سازی قابلیت دراگ اند دراپ
    function initSortable() {
        $('.nested-sortable, .item-children').sortable({
            items: '.item',
            connectWith: '.nested-sortable, .item-children',
            handle: '.dashicons-move',
            placeholder: 'sortable-placeholder',
            tolerance: 'pointer',
            stop: function(event, ui) {
                // بروزرسانی ساختار والد-فرزند
                updateNestedStructure();
            }
        }).disableSelection();
    }
    
    // اتصال رویدادها
    function bindEvents() {
        // افزودن دسته‌بندی جدید
        $('.add-category').on('click', function() {
            addCategoryItem();
        });
        
        // افزودن محصول جدید
        $('.add-product').on('click', function() {
            addProductItem();
        });
        
        // حذف آیتم
        $(document).on('click', '.delete-item', function() {
            $(this).closest('.item').remove();
        });
        
        // ذخیره تنظیمات
        $('.save-settings').on('click', function() {
            saveSettings();
        });
    }
    
    // افزودن آیتم دسته‌بندی
    function addCategoryItem() {
        var template = $('#category-item-template').html();
        template = template.replace(/\{\{id\}\}/g, nextId);
        template = template.replace(/\{\{name\}\}/g, '');
        
        $('#product-settings-container').append(template);
        nextId++;
        
        // بروزرسانی قابلیت دراگ اند دراپ
        initSortable();
    }
    
    // افزودن آیتم محصول
    function addProductItem() {
        var template = $('#product-item-template').html();
        template = template.replace(/\{\{id\}\}/g, nextId);
        template = template.replace(/\{\{name\}\}/g, '');
        template = template.replace(/\{\{price\}\}/g, '0');
        
        $('#product-settings-container').append(template);
        nextId++;
    }
    
    // بروزرسانی ساختار والد-فرزند
    function updateNestedStructure() {
        // اضافه کردن کلاس به دسته‌بندی‌هایی که دارای فرزند هستند
        $('.category-item').each(function() {
            if ($(this).find('.item').length > 0) {
                $(this).addClass('has-children');
            } else {
                $(this).removeClass('has-children');
            }
        });
    }
    
    // جمع‌آوری داده‌ها برای ذخیره
    function collectData() {
        var items = [];
        
        // جمع‌آوری آیتم‌های سطح اول
        collectItems(items, $('#product-settings-container'), 0);
        
        return items;
    }
    
    // جمع‌آوری آیتم‌ها به صورت بازگشتی
    function collectItems(items, container, parentId) {
        container.children('.item').each(function() {
            var $item = $(this);
            var itemId = $item.data('id');
            var itemType = $item.data('type');
            var itemData = {
                id: itemId,
                type: itemType,
                parent_id: parentId
            };
            
            if (itemType === 'category') {
                itemData.name = $item.find('.item-name').val();
                
                // اضافه کردن آیتم به لیست
                items.push(itemData);
                
                // جمع‌آوری فرزندان
                collectItems(items, $item.find('.item-children'), itemId);
            } else if (itemType === 'product') {
                itemData.name = $item.find('.item-name').val();
                itemData.price = $item.find('.item-price').val();
                
                // اضافه کردن آیتم به لیست
                items.push(itemData);
            }
        });
    }
    
    // ذخیره تنظیمات
    function saveSettings() {
        var items = collectData();
        
        $.ajax({
            url: customProductSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_product_settings',
                nonce: customProductSettings.nonce,
                settings: JSON.stringify(items)
            },
            success: function(response) {
                if (response.success) {
                    alert('تنظیمات با موفقیت ذخیره شد.');
                } else {
                    alert('خطا در ذخیره تنظیمات: ' + response.data);
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور.');
            }
        });
    }
    
    // راه‌اندازی
    init();
});