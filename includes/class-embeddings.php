<?php
// Handles vector embeddings and Pinecone integration
require_once plugin_dir_path(__FILE__) . 'class-llm.php';

class Jinx_Embeddings {
	
	/**
	 * Process menu data and send to Pinecone if configured
	 * @param array $menu_list The menu data from jinx_admin_menus
	 * @return bool Success status
	 */
	public static function process_menus_to_pinecone($menu_list) {
		$pinecone_api_key = get_option('jinx_pinecone_api_key');
		$pinecone_server_url = get_option('jinx_pinecone_server_url');
		
		// Check if Pinecone is configured
		if (empty($pinecone_api_key) || empty($pinecone_server_url)) {
			return false;
		}
		
		// Clear existing vectors first (optional - you might want to keep this or make it configurable)
		self::clear_pinecone_vectors();
		
		$vectors_to_upsert = array();
		
		// Process each menu item
		foreach ($menu_list as $menu_item) {
			$vector_data = self::create_vector_from_menu_item($menu_item);
			if ($vector_data) {
				$vectors_to_upsert[] = $vector_data;
			}
		}
		
		// Batch upsert to Pinecone
		if (!empty($vectors_to_upsert)) {
			return self::upsert_vectors_to_pinecone($vectors_to_upsert);
		}
		
		return true;
	}
	
	/**
	 * Create vector data from a single menu item
	 * @param array $menu_item Single menu item with title, url, parent
	 * @return array|false Vector data ready for Pinecone or false on failure
	 */
	private static function create_vector_from_menu_item($menu_item) {
		// Create the text to embed - combine parent and title if parent exists
		$text_to_embed = '';
		$metadata_title = '';
		
		if (!empty($menu_item['parent']) && $menu_item['parent'] !== $menu_item['title']) {
			$text_to_embed = $menu_item['parent'] . ' > ' . $menu_item['title'];
			$metadata_title = $menu_item['parent'] . ' ' . $menu_item['title'];
		} else {
			$text_to_embed = $menu_item['title'];
			$metadata_title = $menu_item['title'];
		}
		
		// Get embedding from LLM
		$embedding = self::get_embedding($text_to_embed);
		if (!$embedding) {
			return false;
		}
		
		// Create unique ID for this menu item
		$vector_id = 'menu_' . md5($menu_item['url']);
		
		// Prepare vector data for Pinecone
		return array(
			'id' => $vector_id,
			'values' => $embedding,
			'metadata' => array(
				'title' => $metadata_title,
				'url' => $menu_item['url'],
				'type' => 'wordpress_menu'
			)
		);
	}
	
	/**
	 * Get embedding for text using the configured LLM service
	 * @param string $text Text to embed
	 * @return array|false Embedding vector or false on failure
	 */
	private static function get_embedding($text) {
		$api_key = get_option('jinx_llm_api_key');
		$provider = get_option('jinx_llm_service', 'openai');
		
		if (!$api_key || !$provider) {
			return false;
		}
		
		if ($provider === 'openai') {
			return self::get_openai_embedding($api_key, $text);
		} elseif ($provider === 'gemini') {
			return self::get_gemini_embedding($api_key, $text);
		}
		
		return false;
	}
	
	/**
	 * Get embedding from OpenAI
	 * @param string $api_key OpenAI API key
	 * @param string $text Text to embed
	 * @return array|false Embedding vector or false on failure
	 */
	private static function get_openai_embedding($api_key, $text) {
		$response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body' => json_encode(array(
				'model' => 'text-embedding-3-small',
				'input' => $text,
				'encoding_format' => 'float',
				'dimensions' => 512
			)),
			'timeout' => 30,
		));
		
		if (is_wp_error($response)) {
			return false;
		}
		
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		
		if (isset($data['data'][0]['embedding'])) {
			return $data['data'][0]['embedding'];
		}
		
		return false;
	}
	
	/**
	 * Get embedding from Gemini
	 * @param string $api_key Gemini API key
	 * @param string $text Text to embed
	 * @return array|false Embedding vector or false on failure
	 */
	private static function get_gemini_embedding($api_key, $text) {
		$response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key=' . $api_key, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => json_encode(array(
				'model' => 'models/text-embedding-004',
				'content' => array(
					'parts' => array(
						array('text' => $text)
					)
				)
			)),
			'timeout' => 30,
		));
		
		if (is_wp_error($response)) {
			return false;
		}
		
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		
		if (isset($data['embedding']['values'])) {
			$embedding = $data['embedding']['values'];
			// Truncate to 512 dimensions to match OpenAI and Pinecone index
			return array_slice($embedding, 0, 512);
		}
		
		return false;
	}
	
	/**
	 * Upsert vectors to Pinecone
	 * @param array $vectors Array of vector data to upsert
	 * @return bool Success status
	 */
	private static function upsert_vectors_to_pinecone($vectors) {
		$pinecone_api_key = get_option('jinx_pinecone_api_key');
		$pinecone_server_url = get_option('jinx_pinecone_server_url');
		
		// Ensure server URL ends with /vectors/upsert
		$upsert_url = rtrim($pinecone_server_url, '/') . '/vectors/upsert';
		
		$response = wp_remote_post($upsert_url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Api-Key' => $pinecone_api_key,
			),
			'body' => json_encode(array(
				'vectors' => $vectors
			)),
			'timeout' => 60,
		));
		
		if (is_wp_error($response)) {
			error_log('Pinecone upsert error: ' . $response->get_error_message());
			return false;
		}
		
		$status_code = wp_remote_retrieve_response_code($response);
		if ($status_code !== 200) {
			$body = wp_remote_retrieve_body($response);
			error_log('Pinecone upsert failed with status ' . $status_code . ': ' . $body);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Clear all vectors from Pinecone (optional cleanup)
	 * @return bool Success status
	 */
	private static function clear_pinecone_vectors() {
		$pinecone_api_key = get_option('jinx_pinecone_api_key');
		$pinecone_server_url = get_option('jinx_pinecone_server_url');
		
		// Delete all vectors with metadata filter for wordpress_menu type
		$delete_url = rtrim($pinecone_server_url, '/') . '/vectors/delete';
		
		$response = wp_remote_request($delete_url, array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'Api-Key' => $pinecone_api_key,
			),
			'body' => json_encode(array(
				'filter' => array(
					'type' => array('$eq' => 'wordpress_menu')
				)
			)),
			'timeout' => 30,
		));
		
		// Don't fail the whole process if cleanup fails
		if (is_wp_error($response)) {
			error_log('Pinecone cleanup warning: ' . $response->get_error_message());
		}
		
		return true;
	}
	
	/**
	 * Search Pinecone for similar vectors (for future use)
	 * @param string $query Search query
	 * @param int $top_k Number of results to return
	 * @return array|false Search results or false on failure
	 */
	public static function search_pinecone($query, $top_k = 10) {
		$pinecone_api_key = get_option('jinx_pinecone_api_key');
		$pinecone_server_url = get_option('jinx_pinecone_server_url');
		
		if (empty($pinecone_api_key) || empty($pinecone_server_url)) {
			return false;
		}
		
		// Get embedding for the query
		$query_embedding = self::get_embedding($query);
		if (!$query_embedding) {
			return false;
		}
		
		// Search Pinecone
		$query_url = rtrim($pinecone_server_url, '/') . '/query';
		
		$response = wp_remote_post($query_url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Api-Key' => $pinecone_api_key,
			),
			'body' => json_encode(array(
				'vector' => $query_embedding,
				'topK' => $top_k,
				'includeMetadata' => true,
				'filter' => array(
					'type' => array('$eq' => 'wordpress_menu')
				)
			)),
			'timeout' => 30,
		));
		
		if (is_wp_error($response)) {
			return false;
		}
		
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		
		if (isset($data['matches'])) {
			// Convert to format expected by the frontend
			$results = array();
			foreach ($data['matches'] as $match) {
				if (isset($match['metadata'])) {
					$results[] = array(
						'title' => $match['metadata']['title'],
						'url' => $match['metadata']['url']
					);
				}
			}
			return $results;
		}
		
		return false;
	}
}
