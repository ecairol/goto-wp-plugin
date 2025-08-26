<?php
/*
Plugin Name: Jinx
Description: Quickly find and navigate to any WordPress Admin screen using AI-powered search. Cmd + J / Ctrl + J.
Version: 0.2.0
Author: ecairol
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('JINX_PLUGIN_FILE', __FILE__);
define('JINX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JINX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JINX_VERSION', '0.2.0');

// Load autoloader
require_once JINX_PLUGIN_DIR . 'src/Core/Autoloader.php';

// Register autoloader
$autoloader = new \Jinx\Core\Autoloader();
$autoloader->register();

// Initialize plugin
add_action('plugins_loaded', function() {
    \Jinx\Core\Plugin::getInstance();
});

// Activation/deactivation hooks
register_activation_hook(__FILE__, function() {
    \Jinx\Core\Plugin::getInstance()->onActivation();
});

register_deactivation_hook(__FILE__, function() {
    \Jinx\Core\Plugin::getInstance()->onDeactivation();
});
