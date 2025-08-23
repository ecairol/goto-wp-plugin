<?php
// Handles the Jinx plugin settings page
require_once plugin_dir_path(__DIR__) . 'includes/class-menu-scanner.php';
class Jinx_Settings {

	public function __construct() {
		// Add settings menu (only once, on admin_menu)
		add_action('admin_menu', array($this, 'add_settings_page'));
		// Register settings
		add_action('admin_init', array($this, 'register_settings'));
		// Handle manual rescan (does not register menu)
		add_action('admin_init', array($this, 'handle_manual_rescan'), 20);
	}

	// Add the settings page under Settings menu
	public function add_settings_page() {
		// Only add the page if it doesn't already exist
		global $admin_page_hooks;
		if (!isset($admin_page_hooks['jinx-settings'])) {
			add_options_page(
				'Jinx Settings',
				'Jinx',
				'manage_options',
				'jinx-settings',
				array($this, 'render_settings_page')
			);
		}
	}

	// Register settings, sections, and fields
	public function register_settings() {
		register_setting('jinx_settings_group', 'jinx_llm_api_key');
		register_setting('jinx_settings_group', 'jinx_llm_service');
		register_setting('jinx_settings_group', 'jinx_pinecone_api_key');
		register_setting('jinx_settings_group', 'jinx_pinecone_server_url');
		register_setting('jinx_settings_group', 'jinx_use_embeddings');

		add_settings_section(
			'jinx_main_section',
			'Jinx Configuration',
			null,
			'jinx-settings'
		);

		add_settings_field(
			'jinx_llm_service',
			'LLM Service',
			array($this, 'llm_service_field_callback'),
			'jinx-settings',
			'jinx_main_section'
		);

		add_settings_field(
			'jinx_llm_api_key',
			'LLM API Key',
			array($this, 'api_key_field_callback'),
			'jinx-settings',
			'jinx_main_section'
		);

		add_settings_field(
			'jinx_use_embeddings',
			'Use Embeddings Search',
			array($this, 'use_embeddings_field_callback'),
			'jinx-settings',
			'jinx_main_section'
		);

		add_settings_field(
			'jinx_pinecone_api_key',
			'Pinecone API Key',
			array($this, 'pinecone_api_key_field_callback'),
			'jinx-settings',
			'jinx_main_section'
		);

		add_settings_field(
			'jinx_pinecone_server_url',
			'Pinecone Server URL',
			array($this, 'pinecone_server_url_field_callback'),
			'jinx-settings',
			'jinx_main_section'
		);
	}

	// Handle manual rescan button
	public function handle_manual_rescan() {
		if (
			isset($_POST['jinx_rescan_menus']) &&
			current_user_can('manage_options') &&
			check_admin_referer('jinx_settings_group-options')
		) {
			Jinx_Menu_Scanner::scan_and_store_menus();
			
			// Check if Pinecone is configured to provide appropriate feedback
			$pinecone_api_key = get_option('jinx_pinecone_api_key');
			$pinecone_server_url = get_option('jinx_pinecone_server_url');
			$pinecone_configured = !empty($pinecone_api_key) && !empty($pinecone_server_url);
			
			add_action('admin_notices', function() use ($pinecone_configured) {
				$message = 'Admin menus have been rescanned and updated.';
				if ($pinecone_configured) {
					$message .= ' Menu embeddings have been processed and sent to Pinecone.';
				}
				echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
			});
		}
	}

	// Render the settings page
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>Jinx Settings</h1>
			<div style="background:#f8f9fa;border:1px solid #e5e5e5;padding:16px 20px;margin-bottom:20px;border-radius:6px;max-width:700px;">
				<strong>Instructions:</strong><br>
				Use <b>Jinx</b> to quickly find and navigate to any WordPress admin screen.<br>
				Open the search modal by clicking the <b>Jinx</b> button in the admin bar, or by pressing <b>Cmd+J</b> (Mac) or <b>Ctrl+J</b> (Windows/Linux) anywhere in the admin.<br>
				Start typing to search for admin screens, then click a result to go directly to that screen.<br>
				You can rescan menus at any time if you install or remove plugins.
			</div>
			<form method="post" action="options.php">
				<?php
				settings_fields('jinx_settings_group');
				do_settings_sections('jinx-settings');
				submit_button();
				?>
			</form>
			<form method="post" style="margin-top:20px;">
				<?php wp_nonce_field('jinx_settings_group-options'); ?>
				<?php submit_button('Rescan Menus', 'secondary', 'jinx_rescan_menus'); ?>
			</form>

			<hr>
			<h2>Source Menu Data</h2>
			<p>This is the data that Jinx uses to search for admin screens. It is stored in the database and can be rescanned at any time. It is also auto-generated when a plugin is activated or deactivated.</p>

			<details>
				<summary><strong>Raw Menu Data</strong></summary>
				<pre style="max-height:400px;overflow:auto;background:#f7f7f7;padding:10px;border:1px solid #ccc;">
				<?php
				$menus = get_option('jinx_admin_menus');
				print_r($menus);
				?>
				</pre>
			</details>

			<details>
				<summary><strong>Menus as CSV</strong></summary>
				<pre style="max-height:400px;overflow:auto;background:#f7f7f7;padding:10px;border:1px solid #ccc;">
					<?php
					$csv_data = get_option('jinx_admin_menus_csv');
					echo $csv_data;
					?>
				</pre>
			</details>
			
		</div>
		<?php
	}

	// API Key field callback
	public function api_key_field_callback() {
		$api_key = esc_attr(get_option('jinx_llm_api_key'));
		echo "<input type='password' name='jinx_llm_api_key' value='$api_key' class='regular-text' />";
	}

	// LLM Service selector callback
	public function llm_service_field_callback() {
		$selected = esc_attr(get_option('jinx_llm_service', 'openai'));
		?>
		<select name="jinx_llm_service">
			<option value="none" <?php selected($selected, 'none'); ?>>None</option>
			<option value="openai" <?php selected($selected, 'openai'); ?>>OpenAI</option>
			<option value="gemini" <?php selected($selected, 'gemini'); ?>>Gemini</option>
		</select>
		<?php
	}

	// Pinecone API Key field callback
	public function pinecone_api_key_field_callback() {
		$api_key = esc_attr(get_option('jinx_pinecone_api_key'));
		echo "<input type='password' name='jinx_pinecone_api_key' value='$api_key' class='regular-text' placeholder='Your Pinecone API Key' />";
		echo "<p class='description'>Optional: Used for vector search functionality</p>";
	}

	// Pinecone Server URL field callback
	public function pinecone_server_url_field_callback() {
		$server_url = esc_attr(get_option('jinx_pinecone_server_url'));
		echo "<input type='url' name='jinx_pinecone_server_url' value='$server_url' class='regular-text' placeholder='https://your-index-xxxxx.svc.pinecone.io' />";
		echo "<p class='description'>Optional: Your Pinecone index server URL</p>";
	}

	// Use Embeddings toggle callback
	public function use_embeddings_field_callback() {
		$use_embeddings = get_option('jinx_use_embeddings', false);
		$checked = $use_embeddings ? 'checked="checked"' : '';
		echo "<label class='description'>";
		echo "<input type='checkbox' name='jinx_use_embeddings' value='1' $checked />";
		echo "Use semantic search with Pinecone embeddings. Requires both Pinecone and LLM service configuration above.</p>";
	}
}

// Initialize settings page
if (is_admin()) {
	new Jinx_Settings();
} 