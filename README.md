# GoTo AI

GoTo AI helps WordPress admins quickly find and navigate to any admin screen using an AI-powered search modal. No more clicking through endless menus—just search and go!

## Features
- Search for any admin screen by keyword
- Modal opens via admin bar button or keyboard shortcut (Cmd+K / Ctrl+K)
- Instant filtering of all admin and plugin menu items
- Keyboard navigation (up/down/enter) in the modal
- Secure REST API endpoint for menu data
- Settings page for API key, LLM provider, and manual menu rescan
- Debug view of scanned menu structure

## Screenshot

![GoTo AI Modal Screenshot](assets/screenshot-1.png)

## Installation
1. Upload the plugin files to the `/wp-content/plugins/goto-ai` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > GoTo AI** to configure the plugin and rescan menus if needed.

## Usage
- Click the **GoTo AI** button in the WordPress admin bar, or press **Cmd+K** (Mac) / **Ctrl+K** (Windows/Linux) anywhere in the admin.
- Start typing to search for admin screens. Use the arrow keys to navigate results and press Enter to go to a screen.
- To update the menu list after installing/removing plugins, use the **Rescan Menus** button on the settings page.

## Development
- Menu scanning logic is in `includes/class-menu-scanner.php`.
- Modal UI logic is in `js/modal.js` and styled in `assets/styles.css`.
- REST API endpoint is in `includes/class-api.php`.
- Settings page is in `includes/class-settings.php`.
- Debug output of scanned menus is shown on the settings page.

## Changelog

### 0.1.0
- Initial release.

## License
GPLv2 or later 