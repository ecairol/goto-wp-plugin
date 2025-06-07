<?php
// Handles REST API endpoints for GoTo AI
class GoToAI_API {
	public static function register_routes() {
		register_rest_route('goto-ai/v1', '/menus', array(
			'methods'  => 'GET',
			'callback' => array(__CLASS__, 'get_menus'),
			'permission_callback' => function() {
				return current_user_can('manage_options');
			},
		));
	}

	public static function get_menus($request) {
		$menus = get_option('goto_ai_admin_menus');
		return rest_ensure_response($menus);
	}
}

add_action('rest_api_init', array('GoToAI_API', 'register_routes')); 