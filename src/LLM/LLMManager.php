<?php

namespace Jinx\LLM;

use Jinx\Core\Config;
use Jinx\LLM\Providers\OpenAIProvider;
use Jinx\LLM\Providers\GeminiProvider;

/**
 * LLM Manager - factory for LLM providers
 */
class LLMManager {
    
    private static $instance = null;
    private $config;
    private $provider = null;
    
    private function __construct() {
        $this->config = Config::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get the configured LLM provider
     */
    public function getProvider() {
        if ($this->provider === null) {
            $this->provider = $this->createProvider();
        }
        return $this->provider;
    }
    
    /**
     * Create provider instance based on configuration
     */
    private function createProvider() {
        $service = $this->config->get('llm.service');
        $api_key = $this->config->get('llm.api_key');
        
        if (empty($api_key) || empty($service)) {
            return null;
        }
        
        switch ($service) {
            case 'openai':
                return new OpenAIProvider($api_key);
                
            case 'gemini':
                return new GeminiProvider($api_key);
                
            default:
                return null;
        }
    }
    
    /**
     * Check if LLM is available
     */
    public function isAvailable() {
        $provider = $this->getProvider();
        return $provider !== null && $provider->validateApiKey();
    }
    
    /**
     * Generate text completion
     */
    public function complete($prompt, $format = 'text') {
        $provider = $this->getProvider();
        if (!$provider) {
            return false;
        }
        
        return $provider->complete($prompt, $format);
    }
    
    /**
     * Generate embeddings
     */
    public function embed($text, $dimensions = null) {
        $provider = $this->getProvider();
        if (!$provider) {
            return false;
        }
        
        return $provider->embed($text, $dimensions);
    }
    
    /**
     * Get available providers
     */
    public static function getAvailableProviders() {
        return array(
            'openai' => 'OpenAI',
            'gemini' => 'Google Gemini',
        );
    }
}
