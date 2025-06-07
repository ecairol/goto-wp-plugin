# GoTo AI Plugin: Development Plan

## Overview
The GoTo AI plugin provides a powerful search modal for WordPress Admins, allowing them to quickly find and navigate to any admin screen by typing keywords or tasks. The search is powered by an LLM (OpenAI, Gemini, etc.) and intelligent keyword mapping, reducing the need to manually browse complex admin menus.

---

## Architecture

### 1. Menu Discovery & Mapping
- **Automatic Menu Scanning:**
  - On plugin install/activation, scan all registered admin menu items (using `global $menu` and `global $submenu`).
  - Re-scan when plugins are activated/deactivated (hook into `activated_plugin` and `deactivated_plugin`).
  - Provide a manual "Rescan Menus" button in the plugin settings for on-demand updates.
- **Plugin-Specific Links:**
  - Maintain a curated list of common plugin screens (WooCommerce, Jetpack, ACF, Yoast, etc.) for more accurate mapping and keyword association.

### 2. Plugin Settings Screen
- **API Key Field:** Secure field for storing the LLM API key (OpenAI, Gemini, etc.).
- **LLM Service Selector:** Dropdown to select between OpenAI and Gemini (and future providers).
- **Manual Rescan Button:** Button to trigger a menu rescan.
- **Custom Keyword Mapping:** (Optional, for future) Allow admins to add/edit keyword-to-URL mappings.

### 3. Backend
- **REST API Endpoint:** Receives search queries, returns best-matched admin URL.
- **LLM Integration:** Use selected LLM service and API key. Prompt engineering for best results. Fallback to keyword-based matching if LLM fails or is unavailable.
- **Menu Data Storage:** Store discovered menu items and mappings in the database (e.g., as an option or custom table).

### 4. Frontend
- **Modal UI:** Input field, suggestion dropdown, keyboard shortcut.
- **API Integration:** Send search queries to backend, handle results.
- **Redirect Logic:** On selection, redirect to the chosen admin screen.

---

## Implementation Steps

1. **Menu Scanning Logic**
   - Write PHP code to scan and store all admin menu items and submenus.
   - Hook into plugin activation/deactivation and provide a manual trigger.

2. **Settings Page**
   - Build a settings page with:
     - API key input (with secure storage)
     - LLM service selector
     - Manual rescan button

3. **Backend API**
   - REST endpoint for search queries.
   - LLM integration (OpenAI, Gemini).
   - Fallback keyword matching.

4. **Frontend Modal**
   - Modal with input and suggestions.
   - Keyboard shortcut.
   - API integration and redirect.

5. **Plugin-Specific Enhancements**
   - Curated mappings for popular plugins.


### File Structure

goto-ai/
├── goto-ai.php                # Main plugin file
├── readme.txt                 # WordPress plugin readme
├── plan.md                    # Project plan and notes
├── assets/
│   ├── icon.svg               # Plugin icon (optional)
│   └── styles.css             # Custom styles for modal/settings
├── includes/
│   ├── class-menu-scanner.php # Handles menu scanning and mapping
│   ├── class-settings.php     # Settings page logic
│   ├── class-api.php          # REST API endpoint logic
│   ├── class-llm.php          # LLM integration logic
│   └── helpers.php            # Utility functions
├── js/
│   ├── modal.js               # Frontend modal logic
│   └── admin.js               # Admin page JS (settings, etc.)
├── templates/
│   ├── modal.php              # Modal HTML template
│   └── settings.php           # Settings page template
└── languages/
    └── goto-ai.pot            # Translation template

---

## Best Practices & Notes

- **Security:**
  - Store API keys securely (use WordPress options API with proper sanitization).
  - Restrict settings page to admin users.
- **Extensibility:**
  - Use WordPress hooks/filters for other plugins to register their screens/keywords.
- **Performance:**
  - Cache menu mappings, only rescan when necessary.
- **Privacy:**
  - Warn users if queries are sent to external LLMs.

---

## Further Exploration (Future Enhancements)
- **Analytics:** Track most-used searches/screens.
- **Personalization:** Suggest screens based on user history.
- **Multi-site Support:** Handle network admin screens.
- **Internationalization:** Support for non-English queries.

---

## Next Steps
- Decide on LLM provider (OpenAI, Gemini, etc.).
- Prototype menu scanning and settings page.
- Build backend API and frontend modal.
- Test and iterate.
