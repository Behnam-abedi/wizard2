# Hierarchical Product Options

A WordPress plugin that allows you to create hierarchical categories and product options with prices that affect the main product price.

## Features

- Create category and product items in a hierarchical structure
- Drag and drop interface for easy organization
- Assign categories to WooCommerce products
- Display options on product pages with different layout options
- Update product price based on selected option
- Options are included in cart and order data

## Installation

1. Upload the `hierarchical-product-options` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings and create your categories and products from the "Product Options" menu in the admin dashboard

## Usage

### Creating Categories and Products

1. Go to "Product Options" in the WordPress admin menu
2. Click "Add Category" to create a top-level category
3. Add sub-categories by clicking the "+ Sub-category" button on an existing category
4. Add products with prices by clicking the "+ Product" button in a category
5. Drag and drop categories and products to organize them

### Assigning to WooCommerce Products

1. In the "Product Options" admin page, go to the "Assignments" panel
2. Select a WooCommerce product from the dropdown
3. Check the categories you want to assign to that product
4. Click "Save Assignments"

### Frontend Display

The product options will automatically appear on the product page before the "Add to Cart" button. When a customer selects a product option, the price of the main product will be updated (if enabled in settings).

## Settings

Go to "Product Options > Settings" to configure:

- Display Mode: Choose between Accordion, Tabs, or Flat List
- Update Product Price: Enable or disable price updates
- Price Display: Show or hide prices next to options

## Advanced Customization

Developers can customize the plugin using the following filters:

- `hpo_price_selector`: Change the CSS selector used to update the price (default: '.price .amount')

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.0 or higher

## Support

For support, please create an issue in the GitHub repository or contact us through our website. 