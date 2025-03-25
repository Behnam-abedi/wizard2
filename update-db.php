<?php
// Include WordPress
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

// Include our plugin
require_once('hierarchical-product-options.php');

// Create DB instance
$db = new Hierarchical_Product_Options_DB();

// Update tables
$db->create_tables();

echo "Database tables updated successfully!";
?> 