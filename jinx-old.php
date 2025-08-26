<?php
/*
Plugin Name: Jinx
Description: Quickly find and navigate to any WordPress Admin screen using AI-powered search. Cmd + J / Ctrl + J.
Version: 0.1.0
Author: ecairol
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Include core files
require_once plugin_dir_path(__FILE__) . 'includes/class-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-menu-scanner.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-llm.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-embeddings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-api.php';
// ... 

// Add Settings link to plugin list
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=jinx-settings') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Enqueue modal JS and CSS in admin
add_action('admin_enqueue_scripts', function() {
	wp_enqueue_style('jinx-styles', plugin_dir_url(__FILE__) . 'assets/styles.css');
	wp_enqueue_script('jinx-modal', plugin_dir_url(__FILE__) . 'js/modal.js', array('jquery'), null, true);
	wp_localize_script('jinx-modal', 'Jinx', array(
		'apiUrl' => rest_url('jinx/v1/menus'),
		'nonce'  => wp_create_nonce('wp_rest'),
		'llmService' => get_option('jinx_llm_service', ''),
	));
});

// Add a button to the admin bar to open the modal
add_action('admin_bar_menu', function($wp_admin_bar) {
	$wp_admin_bar->add_node(array(
		'id'    => 'jinx-modal-btn',
		'title' => 'Jinx',
		'href'  => '#',
		'meta'  => array('title' => 'Open Jinx Search Modal'),
	));
}, 100); 