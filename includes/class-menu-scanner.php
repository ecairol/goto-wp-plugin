<?php
// Handles scanning and mapping of WordPress admin menus
require_once plugin_dir_path(__FILE__) . 'class-embeddings.php';

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
		// e.g. "Plugins 2" or "Comments1" becomes "Plugins" or "Comments"
		$cleaned_title = preg_replace( '/\s*\d+$/', '', $text_title );

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
		
		// Also store as CSV for potentially faster LLM processing
		$csv_data = self::convert_menus_to_csv($menus);
		update_option('jinx_admin_menus_csv', $csv_data);
		
		// Process menus for Pinecone if configured
		self::process_embeddings($menus);
	}
	
	/**
	 * Convert menu array to CSV format for LLM processing
	 */
	private static function convert_menus_to_csv($menus) {
		$csv_lines = array();
		$csv_lines[] = 'title,url,parent'; // CSV header
		
		foreach ($menus as $menu) {
			// Add parent menu
			$csv_lines[] = '"' . str_replace('"', '""', $menu['title']) . '","' . 
						   str_replace('"', '""', $menu['url']) . '",""';
			
			// Add children
			if (!empty($menu['children'])) {
				foreach ($menu['children'] as $child) {
					$csv_lines[] = '"' . str_replace('"', '""', $child['title']) . '","' . 
								   str_replace('"', '""', $child['url']) . '","' . 
								   str_replace('"', '""', $menu['title']) . '"';
				}
			}
		}
		
		return implode("\n", $csv_lines);
	}
	
	/**
	 * Process menus for embeddings and send to Pinecone if configured and enabled
	 * @param array $menus The scanned menu data
	 */
	private static function process_embeddings($menus) {
		// Skip if embeddings are disabled
		$use_embeddings = get_option('jinx_use_embeddings', false);
		if (!$use_embeddings) {
			error_log('Jinx: Embeddings processing skipped (disabled in settings)');
			return;
		}
		// Convert menu structure to flat list for embeddings
		$menu_list = array();
		
		foreach ($menus as $menu) {
			// Add parent menu
			$menu_list[] = array(
				'title' => $menu['title'],
				'url' => $menu['url'],
				'parent' => ''
			);
			
			// Add children with parent reference
			if (!empty($menu['children'])) {
				foreach ($menu['children'] as $child) {
					$menu_list[] = array(
						'title' => $child['title'],
						'url' => $child['url'],
						'parent' => $menu['title']
					);
				}
			}
		}
		
		// Send to Pinecone if configured
		$success = Jinx_Embeddings::process_menus_to_pinecone($menu_list);
		
		// Log success/failure for debugging
		if ($success) {
			error_log('Jinx: Successfully processed ' . count($menu_list) . ' menu items to Pinecone');
		} else {
			error_log('Jinx: Pinecone processing skipped (not configured or failed)');
		}
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
