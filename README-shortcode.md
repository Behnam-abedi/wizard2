# Hierarchical Product Options - Shortcode

This document explains how to use the "Order Now" button shortcode added to the Hierarchical Product Options plugin.

## Basic Usage

To add the "Order Now" button to any page or post, simply use this shortcode:

```
[hpo_order_button]
```

This will display a button with the text "ثبت سفارش" (Order Now). When clicked, it will open a popup with all products that have Hierarchical Product Options configured.

## Customization Options

You can customize the button text and CSS class:

```
[hpo_order_button button_text="سفارش قهوه" button_class="my-custom-button"]
```

### Parameters

- `button_text`: Changes the text displayed on the button (default: "ثبت سفارش")
- `button_class`: Adds a custom CSS class to the button, in addition to the default class (default: "hpo-order-button")

## How It Works

1. When a user clicks the "Order Now" button, a popup displays all products with Hierarchical Product Options.
2. The user can select a product to view its details.
3. A second popup shows the product with all available options:
   - Product name and price
   - Product options organized by categories
   - Weight options with coefficients
   - Grinding options
   - Additional notes field
   - Quantity controls
   - Add to cart button

4. When the user selects options, the price updates dynamically based on:
   - Base product price
   - Selected product options
   - Weight coefficient
   - Grinding options
   - Quantity

5. When "Add to Cart" is clicked, the product is added to the WooCommerce cart with all selected options.

## Requirements

- WooCommerce must be installed and activated
- Products must have Hierarchical Product Options configured

## Example Usage in a Template File

If you need to add the shortcode directly to a template file:

```php
<?php echo do_shortcode('[hpo_order_button]'); ?>
```

For a custom button with different text:

```php
<?php echo do_shortcode('[hpo_order_button button_text="خرید آنلاین قهوه"]'); ?>
```

## Styling

The shortcode includes its own CSS styles, but you can override them in your theme's stylesheet for further customization.

## Troubleshooting

If the popup doesn't display products:
1. Make sure you have products with Hierarchical Product Options configured
2. Check for JavaScript errors in the browser console
3. Verify that the AJAX requests are working properly 