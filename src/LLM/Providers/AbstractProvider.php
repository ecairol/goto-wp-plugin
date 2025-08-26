<?php

namespace Jinx\LLM\Providers;

/**
 * Abstract base class for LLM providers
 */
abstract class AbstractProvider {
    
    protected $api_key;
    protected $config;
    
    public function __construct($api_key, $config = array()) {
        $this->api_key = $api_key;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }
    
    /**
     * Generate text completion
     */
    abstract public function complete($prompt, $format = 'text');
    
    /**
     * Generate embeddings
     */
    abstract public function embed($text, $dimensions = null);
    
    /**
     * Get provider name
     */
    abstract public function getName();
    
    /**
     * Get default configuration
     */
    abstract protected function getDefaultConfig();
    
    /**
     * Validate API key format
     */
    abstract public function validateApiKey();
    
    /**
     * Make HTTP request with error handling
     */
    protected function makeRequest($url, $body, $headers = array()) {
        $default_headers = array(
            'Content-Type' => 'application/json',
        );
        
        $headers = array_merge($default_headers, $headers);
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => $this->config['timeout'] ?? 30,
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if ($status_code !== 200) {
            return array(
                'success' => false,
                'error' => "HTTP {$status_code}: " . ($data['error']['message'] ?? $response_body),
            );
        }
        
        return array(
            'success' => true,
            'data' => $data,
        );
    }
}
