<?php

namespace Jinx\Core;

/**
 * Simple PSR-4 autoloader for Jinx plugin
 */
class Autoloader {
    
    private $prefix;
    private $base_dir;
    
    public function __construct($prefix = 'Jinx\\', $base_dir = null) {
        $this->prefix = $prefix;
        $this->base_dir = $base_dir ?: plugin_dir_path(dirname(dirname(__FILE__))) . 'src/';
    }
    
    /**
     * Register the autoloader
     */
    public function register() {
        spl_autoload_register(array($this, 'loadClass'));
    }
    
    /**
     * Load a class file
     */
    public function loadClass($class) {
        // Check if class uses our namespace prefix
        $len = strlen($this->prefix);
        if (strncmp($this->prefix, $class, $len) !== 0) {
            return;
        }
        
        // Get the relative class name
        $relative_class = substr($class, $len);
        
        // Replace namespace separators with directory separators
        $file = $this->base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
}
