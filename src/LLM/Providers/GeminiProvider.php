<?php

namespace Jinx\LLM\Providers;

/**
 * Google Gemini API provider
 */
class GeminiProvider extends AbstractProvider {
    
    const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';
    
    public function getName() {
        return 'gemini';
    }
    
    protected function getDefaultConfig() {
        return array(
            'chat_model' => 'gemini-1.5-flash-latest',
            'embedding_model' => 'text-embedding-004',
            'max_tokens' => 1024,
            'temperature' => 0.5,
            'timeout' => 30,
        );
    }
    
    public function validateApiKey() {
        return !empty($this->api_key);
    }
    
    public function complete($prompt, $format = 'text') {
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => $this->config['temperature'],
                'maxOutputTokens' => $this->config['max_tokens'],
            )
        );
        
        if ($format === 'json_object') {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }
        
        $url = self::API_BASE . '/models/' . $this->config['chat_model'] . ':generateContent?key=' . $this->api_key;
        
        $response = $this->makeRequest($url, $body);
        
        if (!$response['success']) {
            return false;
        }
        
        return $response['data']['candidates'][0]['content']['parts'][0]['text'] ?? false;
    }
    
    public function embed($text, $dimensions = null) {
        $body = array(
            'model' => 'models/' . $this->config['embedding_model'],
            'content' => array(
                'parts' => array(
                    array('text' => $text)
                )
            )
        );
        
        $url = self::API_BASE . '/models/' . $this->config['embedding_model'] . ':embedContent?key=' . $this->api_key;
        
        $response = $this->makeRequest($url, $body);
        
        if (!$response['success']) {
            return false;
        }
        
        $embedding = $response['data']['embedding']['values'] ?? false;
        
        // Truncate to specified dimensions if needed
        if ($embedding && $dimensions && count($embedding) > $dimensions) {
            $embedding = array_slice($embedding, 0, $dimensions);
        }
        
        return $embedding;
    }
}
