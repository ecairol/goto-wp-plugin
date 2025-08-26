<?php

namespace Jinx\Core;

use Jinx\Admin\UI\AdminBar;
use Jinx\Admin\UI\Scripts;

/**
 * Main plugin class - orchestrates all components
 */
class Plugin {
    
    private static $instance = null;
    private $config;
    
    private function __construct() {
        $this->config = Config::getInstance();
        $this->init();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize plugin components
     */
    private function init() {
        // Initialize admin components (using legacy classes for now)
        if (is_admin()) {
            // TODO: Migrate to new SettingsManager
            require_once JINX_PLUGIN_DIR . 'includes/class-settings.php';
            
            // TODO: Migrate to new MenuScanner  
            require_once JINX_PLUGIN_DIR . 'includes/class-menu-scanner.php';
            
            // TODO: Migrate to new LLM classes
            require_once JINX_PLUGIN_DIR . 'includes/class-llm.php';
            require_once JINX_PLUGIN_DIR . 'includes/class-embeddings.php';
            
            new Scripts();
        }
        
        // Initialize UI components
        new AdminBar();
        
        // Initialize API (using legacy class for now)
        require_once JINX_PLUGIN_DIR . 'includes/class-api.php';
        
        // Hook plugin lifecycle events
        $this->registerHooks();
    }
    
    /**
     * Register WordPress hooks
     */
    private function registerHooks() {
        // Add settings link to plugin list
        add_filter(
            'plugin_action_links_' . plugin_basename(JINX_PLUGIN_FILE),
            array($this, 'addSettingsLink')
        );
        
        // Plugin activation/deactivation hooks are handled in main file
    }
    
    /**
     * Add settings link to plugin actions
     */
    public function addSettingsLink($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=jinx-settings') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Plugin activation handler
     */
    public function onActivation() {
        // Scan menus on activation using legacy scanner
        require_once JINX_PLUGIN_DIR . 'includes/class-menu-scanner.php';
        \Jinx_Menu_Scanner::scan_and_store_menus();
    }
    
    /**
     * Plugin deactivation handler
     */
    public function onDeactivation() {
        // Cleanup if needed
    }
    
    /**
     * Get plugin configuration
     */
    public function getConfig() {
        return $this->config;
    }
}