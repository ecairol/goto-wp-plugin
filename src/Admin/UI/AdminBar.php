<?php

namespace Jinx\Admin\UI;

/**
 * Admin bar integration
 */
class AdminBar {
    
    public function __construct() {
        add_action('admin_bar_menu', array($this, 'addSearchButton'), 100);
    }
    
    /**
     * Add Jinx search button to admin bar
     */
    public function addSearchButton($wp_admin_bar) {
        $wp_admin_bar->add_node(array(
            'id'    => 'jinx-modal-btn',
            'title' => 'Jinx',
            'href'  => '#',
            'meta'  => array('title' => 'Open Jinx Search Modal'),
        ));
    }
}
