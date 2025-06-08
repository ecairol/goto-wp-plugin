<?php
// Handles REST API endpoints for Jinx
class Jinx_API {
	public static function register_routes() {
		register_rest_route('jinx/v1', '/menus', array(
			'methods'  => 'GET',
			'callback' => array(__CLASS__, 'get_menus'),
			'permission_callback' => function() {
				return current_user_can('manage_options');
			},
		));

		register_rest_route('jinx/v1', '/search', array(
			'methods'  => 'POST',
			'callback' => array(__CLASS__, 'search'),
			// 'permission_callback' => function() {
			// 	return current_user_can('manage_options');
			// },
		));
	}

	public static function get_menus($request) {
		$menus = get_option('jinx_admin_menus');
		return rest_ensure_response($menus);
	}

	public static function search($request) {
		$query = sanitize_text_field($request->get_param('query'));
		$menus = get_option('jinx_admin_menus');
		$flat = self::flatten_menus($menus);

		// Local fuzzy search
		$local_results = array_values(array_filter($flat, function($item) use ($query) {
			return stripos($item['title'], $query) !== false;
		}));

		// LLM provider (dummy for now)
		$llm_results = Jinx_LLM::search($query, $flat);

		return rest_ensure_response([
			'local' => $local_results,
			'llm'   => $llm_results,
		]);
	}

	private static function flatten_menus($menus) {
		$items = [];
		if (!$menus) return $items;
		foreach ($menus as $menu) {
			$items[] = [
				'title' => $menu['title'],
				'url'   => $menu['url'],
				'slug'  => $menu['slug'],
				'parent'=> $menu['parent'],
			];
			if (!empty($menu['children'])) {
				foreach ($menu['children'] as $child) {
					$items[] = [
						'title' => $menu['title'] . ' > ' . $child['title'],
						'url'   => $child['url'],
						'slug'  => $child['slug'],
						'parent'=> $child['parent'],
					];
				}
			}
		}
		return $items;
	}
}

add_action('rest_api_init', array('Jinx_API', 'register_routes')); 