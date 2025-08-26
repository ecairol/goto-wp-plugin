<?php

namespace Jinx\Search;

use Jinx\Core\Config;
use Jinx\Search\Strategies\LLMSearchStrategy;
use Jinx\Search\Strategies\EmbeddingSearchStrategy;
use Jinx\Search\Strategies\LocalSearchStrategy;

/**
 * Unified search engine with strategy pattern
 */
class SearchEngine {
    
    private static $instance = null;
    private $config;
    private $strategies = array();
    
    private function __construct() {
        $this->config = Config::getInstance();
        $this->initStrategies();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize search strategies
     */
    private function initStrategies() {
        $this->strategies = array(
            'local' => new LocalSearchStrategy(),
            'llm' => new LLMSearchStrategy(),
            'embedding' => new EmbeddingSearchStrategy(),
        );
    }
    
    /**
     * Perform search using the best available strategy
     */
    public function search($query, $menu_data = null) {
        // Always provide local results first
        $local_results = $this->strategies['local']->search($query, $menu_data);
        
        // Determine which AI strategy to use
        $ai_strategy = $this->selectAIStrategy();
        
        if (!$ai_strategy) {
            return $local_results;
        }
        
        // Get AI-enhanced results
        $ai_results = $this->strategies[$ai_strategy]->search($query, $menu_data);
        
        // Merge and deduplicate results
        return $this->mergeResults($local_results, $ai_results);
    }
    
    /**
     * Select the best AI strategy based on configuration
     */
    private function selectAIStrategy() {
        // Prefer embeddings if configured and enabled
        if ($this->config->useEmbeddings()) {
            return 'embedding';
        }
        
        // Fall back to LLM if configured
        if ($this->config->isLLMConfigured()) {
            return 'llm';
        }
        
        return null;
    }
    
    /**
     * Merge local and AI results, removing duplicates
     */
    private function mergeResults($local_results, $ai_results) {
        if (empty($ai_results)) {
            return $local_results;
        }
        
        // Create a map of URLs to avoid duplicates
        $url_map = array();
        $merged = array();
        
        // Add local results first
        foreach ($local_results as $result) {
            $url = $result['url'];
            if (!isset($url_map[$url])) {
                $url_map[$url] = true;
                $merged[] = $result;
            }
        }
        
        // Add AI results that aren't duplicates
        foreach ($ai_results as $result) {
            $url = $result['url'];
            if (!isset($url_map[$url])) {
                $url_map[$url] = true;
                $merged[] = $result;
            }
        }
        
        return $merged;
    }
    
    /**
     * Get search strategy by name
     */
    public function getStrategy($name) {
        return isset($this->strategies[$name]) ? $this->strategies[$name] : null;
    }
}
