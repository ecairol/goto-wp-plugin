<?php
// Handles integration with LLM services (OpenAI, Gemini, etc.)
class GoToAI_LLM {
	/**
	 * Search using LLM (dummy implementation for now)
	 * @param string $query
	 * @param array $menu_list
	 * @return array
	 */
	public static function search($query, $menu_list) {
		// Dummy: return a fake suggestion for demonstration
		return [
			[
				'title' => 'LLM Suggestion: ' . ucfirst($query),
				'url'   => '',
				'slug'  => '',
				'parent'=> '',
			]
		];
	}
} 