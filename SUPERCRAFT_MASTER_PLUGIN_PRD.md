# Product Requirements Document (PRD): Supercraft Master Plugin

This document specifies the requirements, functional specifications, and technical guidelines for building the **Supercraft Master Plugin**. This plugin serves as a centralized hub for license validation, domain registration, and automated plugin provisioning.

---

## 1. Product Overview

### 1.1 Goal
To streamline the setup of the Supercraft suite across all development and client sites. The Master Plugin will centralize the license validation process and provide an interactive, one-click onboarding wizard to download, install, and activate essential and companion plugins.

### 1.2 Key Objectives
* **Unified Onboarding:** Prompt users for validation upon initial installation.
* **Consolidated Validation:** Expose a single licensing validator to eliminate duplicate activation fields on child plugins.
* **Automated Provisioning:** Download and activate custom GitHub-hosted plugins and standard WordPress.org plugins based on user-selected toggles.

---

## 2. Onboarding Workflow (User Experience)

Upon activating the Supercraft Master Plugin, the user is redirected to a multi-step **Onboarding Wizard**.

```
[ Step 1: License Verification ]
              │
              ▼ (Success)
[ Step 2: Plugin Selection Toggle List ]
              │
              ▼ (Submit)
[ Step 3: Installation & Activation Progress Bar ]
              │
              ▼
[ Complete: Redirect to Master Dashboard ]
```

### Step 1: License Verification
* **UI Elements:** Embed Code (License Key) input field, validation status indicator, and a "Next" button.
* **Behavior:** Checks the embed code against the validation API endpoint (`https://superapp.supercraft.my/api/public/check-license`). Progress to Step 2 is blocked until a `valid` response is received.

### Step 2: Plugin Selection
* **UI Elements:** A checklist/toggle interface categorized into two groups (Supercraft Premium Plugins and Standard Companion Plugins).
* **Behavior:** By default, all recommended plugins are checked. The user can toggle off any plugin they do not want to install.

### Step 3: Automated Installation & Activation
* **UI Elements:** A circular progress wheel or status bar showing the download, extraction, and activation progress for each selected plugin in real-time.
* **Behavior:** Sequentially triggers the installation routine for each plugin and activates them programmatically. On completion, the user is redirected to the main Master Settings Dashboard.

---

## 3. Plugin Provisioning Inventory

The installation manager must support two distinct sources: custom GitHub repositories (requiring zip downloads) and the standard WordPress.org plugin directory.

### 3.1 Custom & Premium Plugins (GitHub Zip Installation)
These plugins must be fetched dynamically from GitHub. The installer should query the repository, fetch the main branch zip archive, and extract it to the WordPress plugins directory.

| Plugin Name | Repository URL | Target Folder Name |
| :--- | :--- | :--- |
| **Supercraft Animation Plugin** | `https://github.com/lynesslim/supercraft-animation-plugin` | `supercraft-animation-plugin` |
| **SuperComponent Studio** | `https://github.com/lynesslim/supercomponent-studio` | `supercomponent-studio` |
| **Pro Elements** | `https://github.com/proelements/proelements` | `proelements` |

### 3.2 Standard Companion Plugins (WordPress.org Installation)
These plugins are available on the official WordPress repository and should be downloaded using the standard WordPress Plugins API.

| Plugin Name | WordPress.org URL / Slug |
| :--- | :--- |
| **Elementor** | `elementor` |
| **Skyboot Custom Icons** | `skyboot-custom-icons-for-elementor` |
| **CIMO Image Optimizer** | `cimo-image-optimizer` |
| **Instant Images** | `instant-images` |
| **Marquee Addons for Elementor** | `marquee-addons-for-elementor` |

---

## 4. Technical Specifications

### 4.1 Global Filter Expose
To notify child plugins that validation is active, the Master Plugin must hook into `supercraft_is_plugin_validated`:

```php
add_filter('supercraft_is_plugin_validated', function($is_valid, $plugin_slug) {
    $master_status = get_option('supercraft_master_validation_status', 'not_set');
    if ($master_status === 'valid') {
        return true;
    }
    return $is_valid;
}, 10, 2);
```

### 4.2 Handling GitHub Zip Downloads
Since GitHub-hosted plugins are not available via standard WordPress repository searches, use `wp_remote_get()` to fetch the ZIP payload and use `unzip_file()` to install:

```php
function supercraft_install_github_plugin($repo_url, $folder_name) {
    // Construct the zip download URL (e.g., download master or main branch)
    $zip_url = rtrim($repo_url, '/') . '/archive/refs/heads/main.zip';
    
    // Download zip to temporary directory
    $temp_zip = download_url($zip_url);
    if (is_wp_error($temp_zip)) {
        return $temp_zip;
    }

    // Unzip to wp-content/plugins/
    $plugins_dir = WP_PLUGIN_DIR;
    $unzipped = unzip_file($temp_zip, $plugins_dir);
    
    // Cleanup temporary file
    @unlink($temp_zip);

    if (is_wp_error($unzipped)) {
        return $unzipped;
    }

    // Rename extraction folder if necessary to match the standard folder name
    // e.g. "supercraft-animation-plugin-main" -> "supercraft-animation-plugin"
    $extracted_folder = $plugins_dir . '/' . basename($repo_url) . '-main';
    $target_folder = $plugins_dir . '/' . $folder_name;
    if (file_exists($extracted_folder)) {
        rename($extracted_folder, $target_folder);
    }

    return true;
}
```

### 4.3 Handling WordPress.org Downloads
Use the native WordPress `plugins_api` and `Plugin_Upgrader` helpers to download and install standard plugins programmatically:

```php
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

function supercraft_install_wporg_plugin($slug) {
    $api = plugins_api('plugin_information', array(
        'slug' => $slug,
        'fields' => array('sections' => false)
    ));

    if (is_wp_error($api)) {
        return $api;
    }

    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->install($api->download_link);
    return $result;
}
```

### 4.4 Programmatic Activation
Once installed, programmatically activate the plugin:
```php
function supercraft_activate_plugin($plugin_path) {
    // E.g. $plugin_path = "supercraft-animation-plugin/supercraft-animations.php"
    if (!is_plugin_active($plugin_path)) {
        activate_plugin($plugin_path);
    }
}
```
