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
require_once plugin_dir_path(__FILE__) . 'includes/class-api.php';
// ... 

// Add Settings link to plugin list
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=goto-ai-settings') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Enqueue modal JS and CSS in admin
add_action('admin_enqueue_scripts', function() {
	wp_enqueue_style('goto-ai-styles', plugin_dir_url(__FILE__) . 'assets/styles.css');
	wp_enqueue_script('goto-ai-modal', plugin_dir_url(__FILE__) . 'js/modal.js', array('jquery'), null, true);
	wp_localize_script('goto-ai-modal', 'GoToAI', array(
		'apiUrl' => rest_url('goto-ai/v1/menus'),
		'nonce'  => wp_create_nonce('wp_rest'),
	));
});

// Add a button to the admin bar to open the modal
add_action('admin_bar_menu', function($wp_admin_bar) {
	$wp_admin_bar->add_node(array(
		'id'    => 'goto-ai-modal-btn',
		'title' => 'GoTo AI',
		'href'  => '#',
		'meta'  => array('title' => 'Open GoTo AI Search Modal'),
	));
}, 100); 