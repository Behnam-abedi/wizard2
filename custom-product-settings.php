<?php
/**
 * Plugin Name: تنظیمات سفارشی محصولات
 * Description: افزونه‌ای برای مدیریت دسته‌بندی و محصولات با قابلیت دراگ اند دراپ
 * Version: 1.0.0
 * Author: Your Name
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class Custom_Product_Settings {
    
    public function __construct() {
        // اضافه کردن منوی تنظیمات به وردپرس
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // اضافه کردن اسکریپت‌ها و استایل‌ها
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // ذخیره تنظیمات
        add_action('wp_ajax_save_product_settings', array($this, 'save_settings'));
        
        // اضافه کردن متابکس به صفحه محصول ووکامرس
        add_action('add_meta_boxes', array($this, 'add_product_metabox'));
        
        // ذخیره انتخاب کاربر
        add_action('woocommerce_process_product_meta', array($this, 'save_product_selection'));
        
        // اضافه کردن کد جاوااسکریپت به صفحه محصول
        add_action('wp_footer', array($this, 'product_page_script'));
    }
    
    /**
     * اضافه کردن منوی تنظیمات به وردپرس
     */
    public function add_admin_menu() {
        add_menu_page(
            'تنظیمات محصولات',
            'تنظیمات محصولات',
            'manage_options',
            'custom-product-settings',
            array($this, 'settings_page'),
            'dashicons-list-view',
            30
        );
    }
    
    /**
     * اضافه کردن اسکریپت‌ها و استایل‌ها
     */
    public function enqueue_scripts($hook) {
        if ($hook != 'toplevel_page_custom-product-settings') {
            return;
        }
        
        // jQuery UI برای قابلیت دراگ اند دراپ
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
        wp_enqueue_script('jquery-ui-sortable');
        
        // اسکریپت و استایل سفارشی
        wp_enqueue_style('custom-product-settings-style', plugin_dir_url(__FILE__) . 'css/admin-style.css');
        wp_enqueue_script('custom-product-settings-script', plugin_dir_url(__FILE__) . 'js/admin-script.js', array('jquery', 'jquery-ui-sortable'), '1.0.0', true);
        
        // اضافه کردن متغیرهای لازم برای استفاده در جاوااسکریپت
        wp_localize_script('custom-product-settings-script', 'customProductSettings', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom-product-settings-nonce')
        ));
    }
    
    /**
     * صفحه تنظیمات
     */
    public function settings_page() {
        // دریافت تنظیمات ذخیره شده
        $settings = get_option('custom_product_settings', array());
        ?>
        <div class="wrap">
            <h1>تنظیمات محصولات</h1>
            
            <div class="settings-container">
                <div class="settings-sidebar">
                    <button class="button add-category">افزودن دسته‌بندی جدید</button>
                    <button class="button add-product">افزودن محصول جدید</button>
                    <button class="button save-settings">ذخیره تنظیمات</button>
                </div>
                
                <div class="settings-content">
                    <div class="nested-sortable" id="product-settings-container">
                        <?php
                        if (!empty($settings)) {
                            $this->display_settings_items($settings);
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- قالب برای آیتم دسته‌بندی -->
        <script type="text/html" id="category-item-template">
            <div class="item category-item" data-type="category" data-id="{{id}}">
                <div class="item-header">
                    <span class="item-type">دسته‌بندی</span>
                    <input type="text" class="item-name" value="{{name}}" placeholder="نام دسته‌بندی">
                    <div class="item-actions">
                        <span class="dashicons dashicons-move"></span>
                        <span class="dashicons dashicons-trash delete-item"></span>
                    </div>
                </div>
                <div class="item-children"></div>
            </div>
        </script>
        
        <!-- قالب برای آیتم محصول -->
        <script type="text/html" id="product-item-template">
            <div class="item product-item" data-type="product" data-id="{{id}}">
                <div class="item-header">
                    <span class="item-type">محصول</span>
                    <input type="text" class="item-name" value="{{name}}" placeholder="نام محصول">
                    <input type="number" class="item-price" value="{{price}}" placeholder="قیمت محصول">
                    <div class="item-actions">
                        <span class="dashicons dashicons-move"></span>
                        <span class="dashicons dashicons-trash delete-item"></span>
                    </div>
                </div>
            </div>
        </script>
        <?php
    }
    
    /**
     * نمایش آیتم‌های تنظیمات
     */
    private function display_settings_items($items, $parent_id = 0) {
        foreach ($items as $item) {
            if ($item['parent_id'] == $parent_id) {
                if ($item['type'] == 'category') {
                    ?>
                    <div class="item category-item" data-type="category" data-id="<?php echo esc_attr($item['id']); ?>">
                        <div class="item-header">
                            <span class="item-type">دسته‌بندی</span>
                            <input type="text" class="item-name" value="<?php echo esc_attr($item['name']); ?>" placeholder="نام دسته‌بندی">
                            <div class="item-actions">
                                <span class="dashicons dashicons-move"></span>
                                <span class="dashicons dashicons-trash delete-item"></span>
                            </div>
                        </div>
                        <div class="item-children">
                            <?php $this->display_settings_items($items, $item['id']); ?>
                        </div>
                    </div>
                    <?php
                } else if ($item['type'] == 'product') {
                    ?>
                    <div class="item product-item" data-type="product" data-id="<?php echo esc_attr($item['id']); ?>">
                        <div class="item-header">
                            <span class="item-type">محصول</span>
                            <input type="text" class="item-name" value="<?php echo esc_attr($item['name']); ?>" placeholder="نام محصول">
                            <input type="number" class="item-price" value="<?php echo esc_attr($item['price']); ?>" placeholder="قیمت محصول">
                            <div class="item-actions">
                                <span class="dashicons dashicons-move"></span>
                                <span class="dashicons dashicons-trash delete-item"></span>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
        }
    }
    
    /**
     * ذخیره تنظیمات با اجکس
     */
    public function save_settings() {
        // بررسی امنیتی
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom-product-settings-nonce')) {
            wp_send_json_error('خطای امنیتی');
        }
        
        // بررسی دسترسی
        if (!current_user_can('manage_options')) {
            wp_send_json_error('شما دسترسی لازم را ندارید');
        }
        
        // دریافت و ذخیره داده‌ها
        $settings = isset($_POST['settings']) ? json_decode(stripslashes($_POST['settings']), true) : array();
        
        update_option('custom_product_settings', $settings);
        
        wp_send_json_success('تنظیمات با موفقیت ذخیره شد');
    }
    
    /**
     * اضافه کردن متابکس به صفحه محصول ووکامرس
     */
    public function add_product_metabox() {
        add_meta_box(
            'custom_product_settings_box',
            'تنظیمات محصول سفارشی',
            array($this, 'display_product_metabox'),
            'product',
            'normal',
            'high'
        );
    }
    
    /**
     * نمایش متابکس تنظیمات محصول
     */
    public function display_product_metabox($post) {
        // دریافت تنظیمات ذخیره شده
        $settings = get_option('custom_product_settings', array());
        
        // دریافت انتخاب‌های ذخیره شده برای این محصول
        $selected_options = get_post_meta($post->ID, '_custom_product_options', true);
        
        wp_nonce_field('custom_product_settings_save', 'custom_product_settings_nonce');
        
        echo '<div class="custom-product-options-container">';
        $this->display_options_tree($settings, 0, $selected_options);
        echo '</div>';
    }
    
    /**
     * نمایش درخت گزینه‌ها
     */
    private function display_options_tree($items, $parent_id = 0, $selected_options = array()) {
        echo '<ul class="options-list">';
        foreach ($items as $item) {
            if ($item['parent_id'] == $parent_id) {
                if ($item['type'] == 'category') {
                    echo '<li class="option-category">';
                    echo '<strong>' . esc_html($item['name']) . '</strong>';
                    $this->display_options_tree($items, $item['id'], $selected_options);
                    echo '</li>';
                } else if ($item['type'] == 'product') {
                    $checked = in_array($item['id'], (array)$selected_options) ? 'checked' : '';
                    echo '<li class="option-product">';
                    echo '<label>';
                    echo '<input type="radio" name="custom_product_option" value="' . esc_attr($item['id']) . '" ' . $checked . '> ';
                    echo esc_html($item['name']) . ' - ' . wc_price($item['price']);
                    echo '</label>';
                    echo '</li>';
                }
            }
        }
        echo '</ul>';
    }
    
    /**
     * ذخیره انتخاب کاربر
     */
    public function save_product_selection($post_id) {
        // بررسی نانس
        if (!isset($_POST['custom_product_settings_nonce']) || !wp_verify_nonce($_POST['custom_product_settings_nonce'], 'custom_product_settings_save')) {
            return;
        }
        
        // بررسی اتوسیو
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // بررسی دسترسی
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // ذخیره گزینه انتخاب شده
        if (isset($_POST['custom_product_option'])) {
            update_post_meta($post_id, '_custom_product_options', sanitize_text_field($_POST['custom_product_option']));
        } else {
            delete_post_meta($post_id, '_custom_product_options');
        }
    }
    
    /**
     * اضافه کردن کد جاوااسکریپت به صفحه محصول
     */
    public function product_page_script() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        $product_id = $product->get_id();
        $selected_option_id = get_post_meta($product_id, '_custom_product_options', true);
        
        if (!$selected_option_id) {
            return;
        }
        
        $settings = get_option('custom_product_settings', array());
        $selected_item = null;
        
        foreach ($settings as $item) {
            if ($item['id'] == $selected_option_id) {
                $selected_item = $item;
                break;
            }
        }
        
        if (!$selected_item) {
            return;
        }
        
        // نمایش گزینه‌های محصول در صفحه
        echo '<div class="custom-product-options">';
        echo '<h3>گزینه‌های موجود</h3>';
        $this->display_frontend_options($settings, 0, $selected_option_id);
        echo '</div>';
        
        // کد جاوااسکریپت برای تغییر قیمت محصول
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // تنظیمات قیمت‌ها
            var productOptions = <?php echo json_encode($settings); ?>;
            
            // تغییر قیمت بر اساس انتخاب کاربر
            $('input[name="custom_product_selection"]').on('change', function() {
                var optionId = $(this).val();
                
                // پیدا کردن آیتم انتخاب شده
                var selectedItem = null;
                $.each(productOptions, function(index, item) {
                    if (item.id == optionId) {
                        selectedItem = item;
                        return false;
                    }
                });
                
                if (selectedItem && selectedItem.type == 'product') {
                    // بروزرسانی قیمت نمایشی
                    var price = parseFloat(selectedItem.price);
                    $('.product .price .amount').text(price.toLocaleString() + ' تومان');
                    
                    // بروزرسانی فیلد مخفی قیمت (برای فرم سبد خرید)
                    $('input[name="custom_price"]').val(price);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * نمایش گزینه‌های محصول در صفحه فرانت
     */
    private function display_frontend_options($items, $parent_id = 0, $selected_option_id = '') {
        echo '<ul class="front-options-list">';
        foreach ($items as $item) {
            if ($item['parent_id'] == $parent_id) {
                if ($item['type'] == 'category') {
                    echo '<li class="front-option-category">';
                    echo '<strong>' . esc_html($item['name']) . '</strong>';
                    $this->display_frontend_options($items, $item['id'], $selected_option_id);
                    echo '</li>';
                } else if ($item['type'] == 'product') {
                    $checked = ($item['id'] == $selected_option_id) ? 'checked' : '';
                    echo '<li class="front-option-product">';
                    echo '<label>';
                    echo '<input type="radio" name="custom_product_selection" value="' . esc_attr($item['id']) . '" ' . $checked . '> ';
                    echo esc_html($item['name']) . ' - ' . wc_price($item['price']);
                    echo '</label>';
                    echo '</li>';
                }
            }
        }
        echo '</ul>';
    }
}

// فایل‌های اضافی
// require_once plugin_dir_path(__FILE__) . 'includes/admin-scripts.php';
// require_once plugin_dir_path(__FILE__) . 'includes/admin-styles.php';

// راه‌اندازی کلاس اصلی
$custom_product_settings = new Custom_Product_Settings();