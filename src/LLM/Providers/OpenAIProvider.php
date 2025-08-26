<?php

namespace Jinx\LLM\Providers;

/**
 * OpenAI API provider
 */
class OpenAIProvider extends AbstractProvider {
    
    const API_BASE = 'https://api.openai.com/v1';
    
    public function getName() {
        return 'openai';
    }
    
    protected function getDefaultConfig() {
        return array(
            'chat_model' => 'gpt-4o-mini',
            'embedding_model' => 'text-embedding-3-small',
            'max_tokens' => 1024,
            'temperature' => 0.8,
            'timeout' => 30,
        );
    }
    
    public function validateApiKey() {
        return !empty($this->api_key) && strpos($this->api_key, 'sk-') === 0;
    }
    
    public function complete($prompt, $format = 'text') {
        $body = array(
            'model' => $this->config['chat_model'],
            'messages' => array(
                array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                array('role' => 'user', 'content' => $prompt),
            ),
            'max_tokens' => $this->config['max_tokens'],
            'temperature' => $this->config['temperature'],
        );
        
        if ($format === 'json_object') {
            $body['response_format'] = array('type' => 'json_object');
        }
        
        $response = $this->makeRequest(
            self::API_BASE . '/chat/completions',
            $body,
            array('Authorization' => 'Bearer ' . $this->api_key)
        );
        
        if (!$response['success']) {
            return false;
        }
        
        return $response['data']['choices'][0]['message']['content'] ?? false;
    }
    
    public function embed($text, $dimensions = null) {
        $body = array(
            'model' => $this->config['embedding_model'],
            'input' => $text,
            'encoding_format' => 'float',
        );
        
        if ($dimensions) {
            $body['dimensions'] = $dimensions;
        }
        
        $response = $this->makeRequest(
            self::API_BASE . '/embeddings',
            $body,
            array('Authorization' => 'Bearer ' . $this->api_key)
        );
        
        if (!$response['success']) {
            return false;
        }
        
        return $response['data']['data'][0]['embedding'] ?? false;
    }
}
