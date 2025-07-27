<?php
// Handles integration with LLM services (OpenAI, Gemini, etc.)
class Jinx_LLM {
	/**
	 * Search using LLM (OpenAI implementation)
	 * @param string $query
	 * @param array $menu_list
	 * @return array
	 */
	public static function search($query, $menu_list) {
		$api_key = get_option('jinx_llm_api_key');
		$provider = get_option('jinx_llm_service', 'openai');

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

		$json_menu_list = json_encode($menu_list);

		$prompt = "**ROLE & CONTEXT:**\n"
			. "You are Jinx, a specialized AI assistant for the WordPress admin. Your task is to act as a smart search filter for a list of admin menu pages. You must be fast, accurate, and concise.\n\n"
			. "**MASTER INSTRUCTION:**\n"
			. "You will be given a JSON array of objects named 'AVAILABLE_MENU_ITEMS'. Each object has a 'title' and a 'url'.\n"
			. "Based on the 'USER_QUERY', you must return a new, filtered JSON array containing only the objects from the original list that are relevant to the query. The structure of the returned objects must be identical to the input.\n\n"
			. "AVAILABLE_MENU_ITEMS:\n"
			. $json_menu_list . "\n\n"
			. "**CRITICAL RULES FOR FILTERING:**\n"
			. "1.  **Strict Adherence to List:** This is the most important rule. You MUST ONLY return items from the 'AVAILABLE MENU ITEMS' list. Do NOT invent, hallucinate, or suggest items that are not in the list, even if they seem plausible.\n"
			. "2.  **Broad Conceptual Matching:** Go beyond literal words to understand the user's *intent*. Match based on:\n"
			. "    - **Abstract Concepts:** Deconstruct high-level ideas. For 'money', think about what represents money in this system: sales, orders, payment gateways, reports, etc.\n"
			. "    - **Intent-to-Tool Mapping:** When users describe what they want to DO, find the WordPress tools/sections that enable that action. 'send an email' → email campaigns, subscribers; 'backup site' → export/backup tools; 'customize look' → themes, customizer.\n"
			. "    - **Synonyms & Categories:** ('photos', 'images') -> 'Media'\n"
			. "    - **User Roles & Groups:** ('people', 'clients') -> 'Users', 'Customers', 'Contacts'\n"
			. "    - **Business Functions:** ('sales', 'revenue') -> 'WooCommerce > Orders', 'WooCommerce > Reports'\n"
			. "    - **Typos, and different languages:** ('pulgins', 'usuario') -> 'Plugins', 'Users'\n"
			. "3.  **Be Thorough:** If a query could plausibly refer to multiple items, include all of them in the returned array.\n"
			. "4.  **Output Format:** Your response MUST be a valid JSON array and nothing else. No introductory text, no explanations, no apologies. If no items match, return an empty array `[]`.\n\n"
			. "**EXAMPLES:**\n"
			. "- Query: 'send an email' -> [{\"title\": \"Email Campaigns\", \"url\": \"...\"}, {\"title\": \"Subscribers\", \"url\": \"...\"}, {\"title\": \"Email Settings\", \"url\": \"...\"}]\n"
			. "- Query: 'money' -> [{\"title\": \"WooCommerce > Orders\", \"url\": \"...\"}, {\"title\": \"WooCommerce > Reports\", \"url\": \"...\"}, {\"title\": \"PayPal Settings\", \"url\": \"...\"}]\n"
			. "- Query: 'people' -> [{\"title\": \"Users\", ...}, {\"title\": \"WooCommerce > Customers\", ...}]\n"
			. "- Query: 'write a new article' -> [{\"title\": \"Posts > Add New\", ...}]\n"
			. "- Query: 'pulgins' -> [{\"title\": \"Plugins\", ...}]\n"
			. "- Query: 'campaña' -> [{\"title\": \"Campaigns\", ...}]\n"
			. "- Query: 'a non-existent page'\n"
			. "- Based on the list, you would return: []\n\n"
			. "---\n"
			. "USER_QUERY: '" . $query . "'";

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
		$response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body' => json_encode(array(
				'model' => 'gpt-4o-mini',
				'messages' => array(
					array('role' => 'system', 'content' => 'You are a helpful assistant.'),
					array('role' => 'user', 'content' => $prompt),
				),
				'max_tokens' => 1024,
				'temperature' => 0.5,
			)),
			'timeout' => 15,
		));

		if (is_wp_error($response)) {
			return false;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (isset($data['choices'][0]['message']['content'])) {
			return $data['choices'][0]['message']['content'];
		}

		return false;
	}

	// Future: add Gemini and other providers here
} 