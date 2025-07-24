<?php
// Handles scanning and mapping of WordPress admin menus
class Jinx_Menu_Scanner {

	/**
	 * Cleans a menu title by removing update counts.
	 * e.g. "Plugins 2" => "Plugins"
	 *
	 * @param string $title The original menu title with HTML.
	 * @return string The cleaned title.
	 */
	private static function clean_menu_title( $title ) {
		// First, get the raw text without any HTML.
		$text_title = wp_strip_all_tags( $title );

		// Remove trailing numbers (and surrounding space) which are typically update/notification counts.
		// e.g. "Plugins 2" becomes "Plugins"
		$cleaned_title = preg_replace( '/\s+\d+$/', '', $text_title );

		return trim( $cleaned_title );
	}

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
				'title' => isset($item[0]) ? self::clean_menu_title($item[0]) : '',
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
						'title' => isset($subitem[0]) ? self::clean_menu_title($subitem[0]) : '',
						'slug'  => $sub_slug,
						'parent'=> $menu_slug,
						'url'   => $sub_url,
					);
				}
			}
		}
		update_option('jinx_admin_menus', $menus);
	}
	
	public static function mark_for_rescan() {
		// Set an option that tells us to rescan menus on the next admin request.
		update_option( 'jinx_admin_menu_rescan_pending', true );
	}

	/**
	 * If a rescan has been marked as pending, run it now (late in admin_menu)
	 */
	public static function maybe_rescan() {
		if ( get_option( 'jinx_admin_menu_rescan_pending' ) ) {
			self::scan_and_store_menus();
			delete_option( 'jinx_admin_menu_rescan_pending' );
		}
	}
	
	/**
	* Hook to scan menus when a plugin is activated or deactivated
	*/
	public static function hook_plugin_changes() {
		// Instead of rescanning immediately (menus are not yet updated), mark a rescan
		add_action( 'activated_plugin', array( __CLASS__, 'mark_for_rescan' ) );
		add_action( 'deactivated_plugin', array( __CLASS__, 'mark_for_rescan' ) );
	}
}

// Scan menus on plugin activation
register_activation_hook(__FILE__, array('Jinx_Menu_Scanner', 'scan_and_store_menus'));
// Hook to plugin changes
Jinx_Menu_Scanner::hook_plugin_changes();

// After all other plugins have registered their admin menus, check if a rescan is required.
add_action( 'admin_menu', array( 'Jinx_Menu_Scanner', 'maybe_rescan' ), 9999 );

// TODO: Implement menu scanning logic
