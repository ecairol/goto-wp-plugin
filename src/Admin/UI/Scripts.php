<?php

namespace Jinx\Admin\UI;

use Jinx\Core\Config;

/**
 * Script and style management
 */
class Scripts {
    
    private $config;
    
    public function __construct() {
        $this->config = Config::getInstance();
        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueScripts() {
        // Enqueue styles
        wp_enqueue_style(
            'jinx-styles',
            JINX_PLUGIN_URL . 'assets/styles.css',
            array(),
            JINX_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'jinx-modal',
            JINX_PLUGIN_URL . 'js/modal.js',
            array('jquery'),
            JINX_VERSION,
            true
        );
        
        // Localize script with configuration
        wp_localize_script('jinx-modal', 'Jinx', array(
            'apiUrl' => rest_url('jinx/v1/menus'),
            'nonce'  => wp_create_nonce('wp_rest'),
            'llmService' => $this->config->get('llm.service'),
            'config' => array(
                'minQueryLength' => $this->config->get('search.min_query_length'),
                'llmDelay' => $this->config->get('search.llm_delay'),
                'maxResults' => $this->config->get('search.max_results'),
            ),
        ));
    }
}
