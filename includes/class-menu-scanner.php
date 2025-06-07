<?php
// Handles scanning and mapping of WordPress admin menus
class GoToAI_Menu_Scanner {
    /**
     * Scan all admin menus and submenus and store them in a WordPress option
     */
    public static function scan_and_store_menus() {
        global $menu, $submenu;
        // Ensure menus are loaded
        if ( ! function_exists('wp_get_current_user') ) {
            require_once(ABSPATH . 'wp-includes/pluggable.php');
        }
        // Force menu population
        if ( ! is_admin() ) return;

        $menus = array();
        foreach ($menu as $item) {
            $menu_slug = isset($item[2]) ? $item[2] : '';
            $url = '';
            if ($menu_slug) {
                $url = (strpos($menu_slug, '.php') !== false)
                    ? admin_url($menu_slug)
                    : admin_url('admin.php?page=' . $menu_slug);
            }
            $menus[$menu_slug] = array(
                'title' => isset($item[0]) ? wp_strip_all_tags($item[0]) : '',
                'slug'  => $menu_slug,
                'parent'=> null,
                'url'   => $url,
                'children' => array(),
            );
            if (isset($submenu[$menu_slug])) {
                foreach ($submenu[$menu_slug] as $subitem) {
                    $sub_slug = isset($subitem[2]) ? $subitem[2] : '';
                    $sub_url = '';
                    if ($sub_slug) {
                        $sub_url = (strpos($sub_slug, '.php') !== false)
                            ? admin_url($sub_slug)
                            : admin_url('admin.php?page=' . $sub_slug);
                    }
                    $menus[$menu_slug]['children'][] = array(
                        'title' => isset($subitem[0]) ? wp_strip_all_tags($subitem[0]) : '',
                        'slug'  => $sub_slug,
                        'parent'=> $menu_slug,
                        'url'   => $sub_url,
                    );
                }
            }
        }
        update_option('goto_ai_admin_menus', $menus);
    }

    /**
     * Hook to scan menus when a plugin is activated or deactivated
     */
    public static function hook_plugin_changes() {
        add_action('activated_plugin', array(__CLASS__, 'scan_and_store_menus'));
        add_action('deactivated_plugin', array(__CLASS__, 'scan_and_store_menus'));
    }
}

// Scan menus on plugin activation
register_activation_hook(__FILE__, array('GoToAI_Menu_Scanner', 'scan_and_store_menus'));
// Hook to plugin changes
GoToAI_Menu_Scanner::hook_plugin_changes();

// TODO: Implement menu scanning logic
