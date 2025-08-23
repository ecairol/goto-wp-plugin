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
		// Get CSV version of menu data for potentially faster LLM processing
		$csv_menu_data = get_option('jinx_admin_menus_csv');
		
		// Fallback to generating CSV from menu_list if not available
		if (!$csv_menu_data) {
			$csv_menu_data = self::convert_to_csv($menu_list);
		}

		$prompt = "**ROLE & CONTEXT:**\n"
			. "You are Jinx, a specialized AI assistant for the WordPress admin. Your task is to act as a smart search filter for a list of admin menu pages. You must be fast, accurate, and concise.\n\n"
			. "**MAIN INSTRUCTION:**\n"
			. "You will be given a CSV table of admin menu items with columns: title, url, parent.\n"
			. "Based on the 'USER_QUERY', you must return a JSON array containing only the relevant menu items from the CSV. Each returned object must have 'title' and 'url' fields exactly as they appear in the CSV.\n\n"
			. "**CRITICAL RULES FOR FILTERING:**\n"
			. "1.  **Strict Adherence to CSV:** This is the most important rule. You MUST ONLY return items that exist in the CSV table above. Do NOT invent, hallucinate, or suggest items that are not in the table, even if they seem plausible.\n"
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
			. "- Query: 'people' -> [{\"title\": \"Users\", \"url\": \"...\"}, {\"title\": \"WooCommerce > Customers\", \"url\": \"...\"}]\n"
			. "- Query: 'write a new article' -> [{\"title\": \"Posts > Add New\", \"url\": \"...\"}]\n"
			. "- Query: 'pulgins' -> [{\"title\": \"Plugins\", \"url\": \"...\"}]\n"
			. "- Query: 'campaña' -> [{\"title\": \"Campaigns\", \"url\": \"...\"}]\n"
			. "- Query: 'a non-existent page'\n"
			. "- Based on the list, you would return: []\n\n"
			. "---\n"
			. "**RESPONSE FORMAT:**\n"
			. "Your response MUST be a valid JSON array and nothing else. No introductory text, no explanations, no apologies. If no items match, return `{items:[]}`.\n\n"
			. "{\"items\": [{\"title\": \"...\", \"url\": \"...\"}, ...]}\n\n"
			. "**AVAILABLE_MENU_ITEMS (CSV):**\n"
			. "The following is a CSV table of admin menu items with columns: title, url, parent of the available menu items. This is the list of items that you can choose from.\n"
			. $csv_menu_data . "\n\n"
			. "USER_QUERY: '" . $query . "'";

		// Call LLM API
		
		$response      = self::call_llm( $prompt, 'json_object' );
		
		// Convert JSON to PHP array, and return sub-items
		$response_json = json_decode($response, true);
		return $response_json['items'];

		// $response_json = json_encode($response);

		// if ( empty( $response_json ) ) {
		// 	return [
		// 		[
		// 			'title' => $response['error'] . ' ' . $response['message'],
		// 			'url' => '',
		// 		]
		// 	];
		// }

		// return $response_json;
	}

	/**
	 * Convert menu array to CSV format (fallback)
	 */
	private static function convert_to_csv($menu_list) {
		$csv_lines = array();
		$csv_lines[] = 'title,url,parent'; // CSV header
		
		foreach ($menu_list as $item) {
			$parent = isset($item['parent']) && $item['parent'] ? $item['parent'] : '';
			$csv_lines[] = '"' . str_replace('"', '""', $item['title']) . '","' . 
						   str_replace('"', '""', $item['url']) . '","' . 
						   str_replace('"', '""', $parent) . '"';
		}
		
		return implode("\n", $csv_lines);
	}

	private static function call_llm( $prompt, $format = 'text' ) {
		$api_key  = get_option('jinx_llm_api_key');
		$provider = get_option('jinx_llm_service', 'openai');

		if ( ! $api_key || ! $provider ) {
			return [
				'error' => 'LLM not configured properly. Check your API key.',
				'message' => ''
			];
		}
		
		if ( $provider === 'openai' ) {
			$response = self::call_openai( $api_key, $prompt, $format );
		} elseif ( $provider === 'gemini' ) {
			$response = self::call_gemini( $api_key, $prompt, $format );
		} else {
			return [
				'error' => 'Unsupported LLM provider: ' . $provider,
				'message' => ''
			];
		}
		
		if ( ! $response || is_wp_error($response) ) {
			return [
				'error' => 'API error with service ' . $provider,
				'message' => is_wp_error($response) ? $response->get_error_message() : 'Unknown error'
			];
		}

		return $response;
	}

	private static function call_openai($api_key, $prompt, $format = 'text') {
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
				'temperature' => 0.8,
				'response_format' => array(
					'type' => $format,
				),
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

	private static function call_gemini($api_key, $prompt, $format = 'text') {
		$response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $api_key, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => json_encode(array(
				'contents' => array(
					array(
						'parts' => array(
							array('text' => $prompt)
						)
					)
				),
				'generationConfig' => array(
					'temperature' => 0.5,
					'maxOutputTokens' => 1024,
					'responseMimeType' => $format,
				)
			)),
			'timeout' => 15,
		));

		if (is_wp_error($response)) {
			return false;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
			return $data['candidates'][0]['content']['parts'][0]['text'];
		}

		return false;
	}

	// Future: add other providers here
} 