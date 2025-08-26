<?php

namespace Jinx\Core;

/**
 * Configuration manager for Jinx plugin
 */
class Config {
    
    private static $instance = null;
    private $config = array();
    
    private function __construct() {
        $this->loadConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load configuration from WordPress options
     */
    private function loadConfig() {
        $this->config = array(
            'llm' => array(
                'api_key' => get_option('jinx_llm_api_key', ''),
                'service' => get_option('jinx_llm_service', 'openai'),
            ),
            'pinecone' => array(
                'api_key' => get_option('jinx_pinecone_api_key', ''),
                'server_url' => get_option('jinx_pinecone_server_url', ''),
            ),
            'embeddings' => array(
                'enabled' => get_option('jinx_use_embeddings', false),
                'dimensions' => 512, // Fixed for now
            ),
            'search' => array(
                'min_query_length' => 3,
                'llm_delay' => 400, // ms
                'max_results' => 10,
            ),
        );
    }
    
    /**
     * Get configuration value
     */
    public function get($key, $default = null) {
        return $this->getNestedValue($this->config, $key, $default);
    }
    
    /**
     * Set configuration value
     */
    public function set($key, $value) {
        $this->setNestedValue($this->config, $key, $value);
    }
    
    /**
     * Check if LLM is configured
     */
    public function isLLMConfigured() {
        $api_key = $this->get('llm.api_key');
        $service = $this->get('llm.service');
        return !empty($api_key) && !empty($service) && $service !== 'none';
    }
    
    /**
     * Check if Pinecone is configured
     */
    public function isPineconeConfigured() {
        $api_key = $this->get('pinecone.api_key');
        $server_url = $this->get('pinecone.server_url');
        return !empty($api_key) && !empty($server_url);
    }
    
    /**
     * Check if embeddings should be used
     */
    public function useEmbeddings() {
        return $this->get('embeddings.enabled') && $this->isPineconeConfigured();
    }
    
    /**
     * Get nested array value using dot notation
     */
    private function getNestedValue($array, $key, $default = null) {
        if (strpos($key, '.') === false) {
            return isset($array[$key]) ? $array[$key] : $default;
        }
        
        $keys = explode('.', $key);
        $current = $array;
        
        foreach ($keys as $k) {
            if (!is_array($current) || !isset($current[$k])) {
                return $default;
            }
            $current = $current[$k];
        }
        
        return $current;
    }
    
    /**
     * Set nested array value using dot notation
     */
    private function setNestedValue(&$array, $key, $value) {
        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return;
        }
        
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = array();
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }
    
    /**
     * Refresh configuration from database
     */
    public function refresh() {
        $this->loadConfig();
    }
}
