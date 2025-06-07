<?php
/*
Plugin Name: GoTo AI
Description: Quickly find and navigate to any WordPress admin screen using AI-powered search.
Version: 0.1.0
Author: ecairol
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Include core files
require_once plugin_dir_path(__FILE__) . 'includes/class-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-menu-scanner.php';
// ... 

// Add Settings link to plugin list
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=goto-ai-settings') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}); 