<?php
// Handles integration with LLM services (OpenAI, Gemini, etc.)
class GoToAI_LLM {
	/**
	 * Search using LLM (OpenAI implementation)
	 * @param string $query
	 * @param array $menu_list
	 * @return array
	 */
	public static function search($query, $menu_list) {
		$api_key = get_option('goto_ai_api_key');
		$provider = get_option('goto_ai_llm_service', 'openai');

		if (!$api_key || $provider !== 'openai') {
			return [
				[
					'title' => 'LLM not configured or not OpenAI',
					'url' => '',
					'slug' => '',
					'parent' => '',
				]
			];
		}

		// Build prompt with instructions and examples
		$menu_lines = array_map(function($item) {
			return '- ' . $item['title'] . ($item['url'] ? ' (' . $item['url'] . ')' : '');
		}, $menu_list);
		$menu_str = implode("\n", $menu_lines);
		$prompt = "You are a WordPress admin assistant. Given this list of admin menu items:\n"
			. $menu_str . "\n\n"
			. "If a user types a query, return the most relevant menu item(s) and their URLs from the list as a JSON array of objects with 'title' and 'url'.\n"
			. "- Consider synonyms, related concepts, and common typos.\n"
			. "- If the query is in another language, return the best English menu item(s) that match the meaning.\n"
			. "- If more than one menu item is relevant, return all of them.\n"
			. "- Only return menu items from the provided list.\n\n"
			. "Example queries and expected results:\n"
			. "- Query: 'person' → [{\"title\": \"Users\", ...}, {\"title\": \"Users > Add User\", ...}]\n"
			. "- Query: 'usuario' → [{\"title\": \"Users\", ...}]\n"
			. "- Query: 'Store' → [{\"title\": \"WooCommerce > Orders\", ...}, {\"title\": \"WooCommerce > Products\", ...}]\n"
			. "- Query: 'tool' → [{\"title\": \"Tools\", ...}, {\"title\": \"Settings\", ...}]\n"
			. "- Query: 'Pulgins' → [{\"title\": \"Plugins\", ...}]\n"
			. "- Query: 'Photos' → [{\"title\": \"Media\", ...}, {\"title\": \"Media > Library\", ...}]\n\n"
			. "User query: '" . $query . "'";

		// Call OpenAI API
		$response = self::call_openai($api_key, $prompt);
		if (!$response) {
			return [
				[
					'title' => 'OpenAI API error',
					'url' => '',
					'slug' => '',
					'parent' => '',
				]
			];
		}

		// Parse response (expecting a JSON array)
		$matches = [];
		if (preg_match('/\[.*\]/s', $response, $json_match)) {
			$matches = json_decode($json_match[0], true);
		}
		if (!is_array($matches)) {
			$matches = [
				[
					'title' => 'Could not parse LLM response',
					'url' => '',
					'slug' => '',
					'parent' => '',
				]
			];
		}
		return $matches;
	}

	private static function call_openai($api_key, $prompt) {
		$endpoint = 'https://api.openai.com/v1/chat/completions';
		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $api_key,
		];
		$body = json_encode([
			'model' => 'gpt-3.5-turbo',
			'messages' => [
				['role' => 'system', 'content' => 'You are a helpful assistant.'],
				['role' => 'user', 'content' => $prompt],
			],
			'max_tokens' => 256,
			'temperature' => 0.2,
		]);

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		$result = curl_exec($ch);
		curl_close($ch);

		if (!$result) return false;
		$data = json_decode($result, true);
		if (!isset($data['choices'][0]['message']['content'])) return false;
		return $data['choices'][0]['message']['content'];
	}

	// Future: add Gemini and other providers here
} 