<?php
/**
 * Plugin Name: Supercraft Master Plugin
 * Plugin URI:  https://supercraft.my
 * Description: Centralized license validation, onboarding, and plugin provisioning for the Supercraft ecosystem.
 * Version:     1.0.1
 * Author:      Supercraft
 * Author URI:  https://supercraft.my
 * License:     GPL v2 or later
 * Text Domain: supercraft-master
 */

defined('ABSPATH') || exit;

define('SCMP_VERSION', '1.0.1');
define('SCMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCMP_PLUGIN_URL', plugin_dir_url(__FILE__));

// ── GitHub Auto-Update Checker ─────────────────────────────────────────
if (file_exists(SCMP_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php')) {
    require_once SCMP_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
    $scmp_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/lynesslim/supercraft-master-plugin/',
        __FILE__,
        'supercraft-master-plugin'
    );
}

// ── Elementor Editor Assets ───────────────────────────────────────────

add_action('elementor/editor/after_enqueue_scripts', 'scmp_enqueue_editor_assets');
function scmp_enqueue_editor_assets() {
    wp_enqueue_style('scmp-editor-connector', SCMP_PLUGIN_URL . 'assets/editor-connector.css', [], SCMP_VERSION);
    wp_enqueue_script('scmp-editor-connector', SCMP_PLUGIN_URL . 'assets/editor-connector.js', ['jquery'], SCMP_VERSION, true);

    wp_localize_script('scmp-editor-connector', 'scmp', [
        'ajax_url'    => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('scmp_nonce'),
        'license_key' => get_option('supercraft_master_license_key', ''),
    ]);
}

// ── SuperVault Proxy ──────────────────────────────────────────────────

add_action('wp_ajax_scmp_supervault_proxy', 'scmp_ajax_supervault_proxy');
function scmp_ajax_supervault_proxy() {
    check_ajax_referer('scmp_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
    }

    $action_type = isset($_POST['action_type']) ? sanitize_text_field(wp_unslash($_POST['action_type'])) : '';

    $license_key = get_option('supercraft_master_license_key', '');
    $base_url    = 'https://library.supercraft.my/wp-json/supervault/v1';

    $headers = [
        'Authorization'          => 'Bearer ' . $license_key,
        'X-Supercraft-Domain'    => get_site_url(),
    ];

    switch ($action_type) {
        case 'categories':
            $cache_key = 'scmp_sv_categories';
            if (isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true') {
                delete_transient('scmp_sv_categories');
                delete_transient('scmp_sv_tags');
                delete_transient('scmp_sv_requirements');
            }
            $bypass_cache = (defined('WP_DEBUG') && WP_DEBUG);
            $cached_categories = $bypass_cache ? false : get_transient($cache_key);
            if (false !== $cached_categories) {
                wp_send_json_success($cached_categories);
            }
            $url = $base_url . '/categories';
            break;

        case 'tags':
            $cache_key = 'scmp_sv_tags';
            if (isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true') {
                delete_transient($cache_key);
            }
            $bypass_cache = (defined('WP_DEBUG') && WP_DEBUG);
            $cached_tags = $bypass_cache ? false : get_transient($cache_key);
            if (false !== $cached_tags) {
                wp_send_json_success($cached_tags);
            }
            $url = $base_url . '/tags';
            break;

        case 'requirements':
            $cache_key = 'scmp_sv_requirements';
            if (isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true') {
                delete_transient($cache_key);
            }
            $bypass_cache = (defined('WP_DEBUG') && WP_DEBUG);
            $cached_reqs = $bypass_cache ? false : get_transient($cache_key);
            if (false !== $cached_reqs) {
                wp_send_json_success($cached_reqs);
            }
            $url = $base_url . '/requirements';
            break;

        case 'components':
            $url = $base_url . '/components';
            $params = [];
            if (!empty($_POST['search'])) {
                $params['search'] = sanitize_text_field(wp_unslash($_POST['search']));
            }
            if (!empty($_POST['category'])) {
                $params['category'] = sanitize_text_field(wp_unslash($_POST['category']));
            }
            if (!empty($_POST['tag'])) {
                $params['tag'] = sanitize_text_field(wp_unslash($_POST['tag']));
            }
            if (!empty($params)) {
                $url = add_query_arg($params, $url);
            }
            break;

        case 'json':
            $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
            if (empty($id)) {
                wp_send_json_error(['message' => 'Component ID is required.']);
            }
            $url = $base_url . '/component/' . $id . '/json';
            break;

        default:
            wp_send_json_error(['message' => 'Invalid action type.']);
    }

    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => $headers,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Proxy error: ' . $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($data === null) {
        wp_send_json_error(['message' => 'Invalid response from SuperVault.']);
    }

    if ($action_type === 'categories') {
        set_transient('scmp_sv_categories', $data, 12 * HOUR_IN_SECONDS);
    }
    if ($action_type === 'tags') {
        set_transient('scmp_sv_tags', $data, 12 * HOUR_IN_SECONDS);
    }
    if ($action_type === 'requirements') {
        set_transient('scmp_sv_requirements', $data, 12 * HOUR_IN_SECONDS);
    }

    wp_send_json_success($data);
}

// ── Push to SuperVault AJAX ────────────────────────────────────────────

add_action('wp_ajax_scmp_push_to_supervault', 'scmp_ajax_push_to_supervault');
function scmp_ajax_push_to_supervault() {
    check_ajax_referer('scmp_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
    }

    $title         = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $preview_image = isset($_POST['preview_image']) ? esc_url_raw(wp_unslash($_POST['preview_image'])) : '';
    $categories    = isset($_POST['categories']) ? array_map('sanitize_text_field', wp_unslash($_POST['categories'])) : [];
    $tags          = isset($_POST['tags']) ? array_map('sanitize_text_field', wp_unslash($_POST['tags'])) : [];
    $requirements  = isset($_POST['requirements']) ? array_map('sanitize_text_field', wp_unslash($_POST['requirements'])) : [];
    $elements_json = isset($_POST['elements_json']) ? wp_unslash($_POST['elements_json']) : '';

    if (empty($title)) {
        wp_send_json_error(['message' => 'Title is required.']);
    }

    if (empty($elements_json)) {
        wp_send_json_error(['message' => 'Element data is required.']);
    }

    $elements_size = strlen($elements_json);
    if ($elements_size > 16 * 1024 * 1024) {
        wp_send_json_error(['message' => 'Payload too large. Maximum size is 16 MB.']);
    }

    $decoded = json_decode($elements_json, true);
    if ($decoded === null) {
        wp_send_json_error(['message' => 'Invalid element JSON.']);
    }

    $license_key = get_option('supercraft_master_license_key', '');
    if (empty($license_key)) {
        wp_send_json_error(['message' => 'License key not found. Please activate your license.']);
    }

    $api_url = 'https://library.supercraft.my/wp-json/supervault/v1/component/create';

    $payload = [
        'title'         => $title,
        'preview_image' => $preview_image,
        'thumbnail'     => $preview_image,
        'categories'    => $categories,
        'tags'          => $tags,
        'requirements'  => $requirements,
        'elements_json' => $decoded,
    ];

    $response = wp_remote_post($api_url, [
        'timeout' => 30,
        'headers' => [
            'Authorization'       => 'Bearer ' . $license_key,
            'X-Supercraft-Domain' => get_site_url(),
            'Content-Type'        => 'application/json',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Push failed: ' . $response->get_error_message()]);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    if ($response_code >= 200 && $response_code < 300 && isset($response_data['success']) && $response_data['success']) {
        wp_send_json_success(['message' => 'Component published to SuperVault.']);
    } else {
        $error_msg = isset($response_data['message']) ? $response_data['message'] : 'Unknown error from SuperVault API.';
        wp_send_json_error(['message' => $error_msg]);
    }
}

// ── Global Validation Filter ──────────────────────────────────────────

add_filter('supercraft_is_plugin_validated', function ($is_valid, $plugin_slug) {
    // 1. Check local environment override
    if (defined('SUPERCRAFT_ALLOW_UNVALIDATED') && SUPERCRAFT_ALLOW_UNVALIDATED) {
        return true;
    }

    // 2. Fallback to Master Plugin status database option
    $master_status = get_option('supercraft_master_validation_status', 'not_set');
    if ($master_status === 'valid') {
        return true;
    }
    return $is_valid;
}, 10, 2);

// ── Plugin Definitions ───────────────────────────────────────────────

function scmp_get_premium_plugins() {
    return [
        'supercraft-animation-plugin' => [
            'name'          => 'Supercraft Animation Plugin',
            'source'        => 'github',
            'zip_url'       => 'https://github.com/lynesslim/supercraft-animation-plugin',
            'target_folder' => 'supercraft-animation-plugin',
        ],
        'supercomponent-studio' => [
            'name'          => 'SuperComponent Studio',
            'source'        => 'github',
            'zip_url'       => 'https://github.com/lynesslim/supercomponent-studio',
            'target_folder' => 'supercomponent-studio',
        ],
        'proelements' => [
            'name'          => 'Pro Elements',
            'source'        => 'github',
            'zip_url'       => 'https://github.com/proelements/proelements',
            'target_folder' => 'pro-elements',
        ],
    ];
}

function scmp_get_wpdotorg_plugins() {
    return [
        'elementor'                       => 'Elementor',
        'skyboot-custom-icons-for-elementor' => 'Skyboot Custom Icons',
        'cimo-image-optimizer'            => 'CIMO Image Optimizer',
        'instant-images'                  => 'Instant Images',
        'marquee-addons-for-elementor'    => 'Marquee Addons for Elementor',
    ];
}

// ── Activation / Redirect ─────────────────────────────────────────────

register_activation_hook(__FILE__, 'scmp_activate');
function scmp_activate() {
    update_option('supercraft_master_validation_status', 'not_set');
    update_option('supercraft_master_license_key', '');
    update_option('scmp_redirect_to_onboarding', true);
    update_option('supercraft_master_onboarding_complete', 'no');
    delete_option('scmp_installed_plugins');
}

add_action('admin_init', 'scmp_redirect_to_onboarding');
function scmp_redirect_to_onboarding() {
    $status = get_option('supercraft_master_validation_status', 'not_set');
    if (
        get_option('scmp_redirect_to_onboarding', false) &&
        $status !== 'valid' &&
        !defined('DOING_AJAX') &&
        !isset($_GET['page']) // let first load happen
    ) {
        delete_option('scmp_redirect_to_onboarding');
        wp_redirect(admin_url('admin.php?page=supercraft-onboarding'));
        exit;
    }
}

// ── Admin Menu ───────────────────────────────────────────────────────

add_action('admin_menu', 'scmp_register_admin_pages');
function scmp_register_admin_pages() {
    $status = get_option('supercraft_master_validation_status', 'not_set');

    add_menu_page(
        'Supercraft Master',
        'Supercraft',
        'manage_options',
        'supercraft-dashboard',
        'scmp_render_dashboard',
        'dashicons-superhero',
        30
    );

    $onboarding_complete = get_option('supercraft_master_onboarding_complete', 'no');
    if ($onboarding_complete !== 'yes') {
        add_submenu_page(
            'supercraft-dashboard',
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'supercraft-onboarding',
            'scmp_render_onboarding'
        );
    }

    add_submenu_page(
        'supercraft-dashboard',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'supercraft-dashboard',
        'scmp_render_dashboard'
    );
}

function scmp_is_plugin_installed($slug) {
    $premium = scmp_get_premium_plugins();
    $folder_name = isset($premium[$slug]['target_folder']) ? $premium[$slug]['target_folder'] : $slug;

    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();
    foreach (array_keys($all_plugins) as $plugin_file) {
        if (dirname($plugin_file) === $folder_name || dirname($plugin_file) === $slug || $plugin_file === $slug) {
            return $plugin_file;
        }
    }
    return false;
}

// ── Enqueue Assets ───────────────────────────────────────────────────

add_action('admin_enqueue_scripts', 'scmp_enqueue_assets');
function scmp_enqueue_assets($hook) {
    if (strpos($hook, 'supercraft') === false) {
        return;
    }
    wp_enqueue_style('scmp-admin', SCMP_PLUGIN_URL . 'assets/admin.css', [], SCMP_VERSION);
    wp_enqueue_script('scmp-admin', SCMP_PLUGIN_URL . 'assets/admin.js', ['jquery'], SCMP_VERSION, true);
    $premium = scmp_get_premium_plugins();
    $wporg   = scmp_get_wpdotorg_plugins();

    $plugin_data = [];

    foreach ($premium as $slug => $info) {
        $installed = scmp_is_plugin_installed($slug) ? true : false;
        $plugin_data['premium'][] = ['slug' => $slug, 'name' => $info['name'], 'installed' => $installed];
    }
    foreach ($wporg as $slug => $name) {
        $installed = scmp_is_plugin_installed($slug) ? true : false;
        $plugin_data['standard'][] = ['slug' => $slug, 'name' => $name, 'installed' => $installed];
    }

    wp_localize_script('scmp-admin', 'scmp', [
        'ajax_url'    => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('scmp_nonce'),
        'plugin_data' => $plugin_data,
    ]);
}

// ── Onboarding Wizard ────────────────────────────────────────────────

function scmp_render_onboarding() {
    $step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
    ?>
    <div class="wrap scmp-onboarding">
        <div class="scmp-onboarding-card">
            <div class="scmp-steps-indicator">
                <span class="scmp-step-badge <?php echo $step >= 1 ? 'active' : ''; ?>">1</span>
                <span class="scmp-step-line"></span>
                <span class="scmp-step-badge <?php echo $step >= 2 ? 'active' : ''; ?>">2</span>
                <span class="scmp-step-line"></span>
                <span class="scmp-step-badge <?php echo $step >= 3 ? 'active' : ''; ?>">3</span>
            </div>

            <?php if ($step === 1) : ?>
                <div id="scmp-step-1" class="scmp-step">
                    <h1>License Verification</h1>
                    <p>Enter your license key to validate your Supercraft subscription.</p>
                    <form id="scmp-license-form">
                        <input type="text" id="scmp-license-key" class="regular-text" placeholder="Enter your license key" required />
                        <button type="submit" class="button button-primary button-hero">Verify &amp; Next</button>
                    </form>
                    <div id="scmp-license-message" class="scmp-message" style="display:none;"></div>
                </div>

            <?php elseif ($step === 2) : ?>
                <div id="scmp-step-2" class="scmp-step">
                    <h1>Plugin Selection</h1>
                    <p>Select the plugins you want to install.</p>
                    <div id="scmp-plugin-selection"></div>
                    <button id="scmp-install-selected" class="button button-primary button-hero">Install Selected Plugins</button>
                </div>

            <?php elseif ($step === 3) : ?>
                <div id="scmp-step-3" class="scmp-step">
                    <h1>Installation &amp; Activation</h1>
                    <p>Installing and activating your selected plugins...</p>

                    <div id="scmp-progress-container">
                        <ul id="scmp-plugin-list" class="scmp-plugin-list"></ul>
                        <div class="scmp-progress-bar-wrapper">
                            <div id="scmp-progress-bar" class="scmp-progress-bar" style="width:0%;"></div>
                        </div>
                        <p id="scmp-progress-text">Starting installation...</p>
                    </div>

                    <div id="scmp-install-complete" style="display:none;">
                        <p>All selected plugins installed and activated successfully!</p>
                        <a href="<?php echo admin_url('admin.php?page=supercraft-dashboard'); ?>" class="button button-primary button-hero">Go to Dashboard</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// ── Dashboard ────────────────────────────────────────────────────────

function scmp_render_dashboard() {
    $status = get_option('supercraft_master_validation_status', 'not_set');
    if ($status === 'valid') {
        update_option('supercraft_master_onboarding_complete', 'yes');
    }
    $installed = get_option('scmp_installed_plugins', []);
    ?>
    <div class="wrap scmp-dashboard">
        <h1>Supercraft Dashboard</h1>

        <div class="scmp-status-card <?php echo $status === 'valid' ? 'valid' : 'invalid'; ?>">
            <h3>License Status</h3>
            <p class="scmp-status-label">
                <?php echo $status === 'valid' ? 'Validated' : ($status === 'not_set' ? 'Not Activated' : 'Invalid'); ?>
            </p>
            <?php if ($status !== 'valid') : ?>
                <a href="<?php echo admin_url('admin.php?page=supercraft-onboarding'); ?>" class="button button-primary">Activate License</a>
            <?php endif; ?>
        </div>

        <h2>Installed Plugins</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Plugin</th>
                    <th>Status</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $all = array_merge(scmp_get_premium_plugins(), scmp_get_wpdotorg_plugins());
                foreach ($all as $slug => $info) :
                    $name = is_array($info) ? $info['name'] : $info;
                    $source = is_array($info) ? $info['source'] : 'wordpress.org';
                    
                    $plugin_file = scmp_is_plugin_installed($slug);
                    $is_installed = $plugin_file ? true : false;
                    $is_active = $plugin_file ? is_plugin_active($plugin_file) : false;
                ?>
                <tr>
                    <td><?php echo esc_html($name); ?></td>
                    <td>
                        <div class="scmp-status-container" id="scmp-status-<?php echo esc_attr($slug); ?>">
                            <?php if (!$is_installed) : ?>
                                <span class="scmp-status-dot missing"></span>
                                <span class="scmp-status-text">Not Installed</span>
                                <button class="button button-small scmp-dash-install" data-slug="<?php echo esc_attr($slug); ?>" style="margin-left: 10px;">Install &amp; Activate</button>
                            <?php elseif (!$is_active) : ?>
                                <span class="scmp-status-dot loading"></span>
                                <span class="scmp-status-text">Installed (Inactive)</span>
                                <button class="button button-small scmp-dash-install" data-slug="<?php echo esc_attr($slug); ?>" style="margin-left: 10px;">Activate</button>
                            <?php else : ?>
                                <span class="scmp-status-dot installed"></span>
                                <span class="scmp-status-text">Active</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo esc_html($source); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ── AJAX: License Verification ───────────────────────────────────────

add_action('wp_ajax_scmp_verify_license', 'scmp_ajax_verify_license');
function scmp_ajax_verify_license() {
    check_ajax_referer('scmp_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
    }

    $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
    if (empty($license_key)) {
        wp_send_json_error(['message' => 'License key is required.']);
    }

    $endpoint = defined('SUPERCRAFT_VALIDATION_ENDPOINT') ? SUPERCRAFT_VALIDATION_ENDPOINT : 'https://superapp.supercraft.my/api/public/check-license';
    $plugin_name = defined('SUPERCRAFT_PLUGIN_NAME') ? SUPERCRAFT_PLUGIN_NAME : 'supercraft-master-plugin';

    $response = wp_remote_post($endpoint, [
        'timeout' => 15,
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode([
            'embed_code'  => $license_key,
            'plugin_name' => $plugin_name,
            'domain'      => get_site_url(),
        ]),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Connection error: ' . $response->get_error_message()]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($code === 200 && isset($data['valid']) && $data['valid'] === true) {
        update_option('supercraft_master_license_key', $license_key);
        update_option('supercraft_master_validation_status', 'valid');
        delete_transient('scmp_sv_categories');
        delete_transient('scmp_sv_tags');
        delete_transient('scmp_sv_requirements');
        wp_send_json_success(['message' => 'License validated successfully.']);
    } else {
        $msg = isset($data['message']) ? $data['message'] : 'Invalid license key.';
        $debug_msg = $msg . ' [HTTP ' . $code . '] Raw body: ' . ($body ? $body : '(empty)');
        wp_send_json_error(['message' => $debug_msg]);
    }
}

// ── AJAX: Install Plugin ─────────────────────────────────────────────

add_action('wp_ajax_scmp_install_plugin', 'scmp_ajax_install_plugin');
function scmp_ajax_install_plugin() {
    check_ajax_referer('scmp_nonce', 'nonce');

    if (!current_user_can('install_plugins')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
    }

    $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
    if (empty($slug)) {
        wp_send_json_error(['message' => 'Plugin slug is required.']);
    }

    $premium = scmp_get_premium_plugins();
    $wporg   = scmp_get_wpdotorg_plugins();

    if (!defined('FS_METHOD')) {
        define('FS_METHOD', 'direct');
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    // ── WordPress.org install ──
    if (isset($wporg[$slug])) {
        // Check if already installed on system
        $plugin_file = scmp_is_plugin_installed($slug);
        if ($plugin_file) {
            activate_plugin($plugin_file);
            wp_send_json_success(['message' => "$slug is already installed and has been activated.", 'slug' => $slug]);
        }

        // Clean up empty/invalid directory if it exists
        $target_path = WP_PLUGIN_DIR . '/' . $slug;
        if (is_dir($target_path)) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                WP_Filesystem();
            }
            $wp_filesystem->delete($target_path, true);
        }

        $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
        if (is_wp_error($api)) {
            wp_send_json_error(['message' => $api->get_error_message()]);
        }

        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        $result   = $upgrader->install($api->download_link);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $plugin_file = $upgrader->plugin_info();
        if ($plugin_file) {
            activate_plugin($plugin_file);
        }

        $installed = get_option('scmp_installed_plugins', []);
        $installed[] = $slug;
        update_option('scmp_installed_plugins', $installed);

        wp_send_json_success(['message' => "$slug installed successfully.", 'slug' => $slug]);
    }

    // ── GitHub / Premium install ──
    if (isset($premium[$slug])) {
        // Check if already installed on system
        $plugin_file = scmp_is_plugin_installed($slug);
        if ($plugin_file) {
            activate_plugin($plugin_file);
            wp_send_json_success(['message' => "$slug is already installed and has been activated.", 'slug' => $slug]);
        }

        // Clean up empty/invalid directory if it exists
        $target_folder = $premium[$slug]['target_folder'];
        $target_path   = WP_PLUGIN_DIR . '/' . $target_folder;
        if (is_dir($target_path)) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                WP_Filesystem();
            }
            $wp_filesystem->delete($target_path, true);
        }

        $zip_url = $premium[$slug]['zip_url'];
        if (strpos($zip_url, 'github.com') !== false && !preg_match('/\.zip$/', $zip_url)) {
            $zip_url = trailingslashit($zip_url) . 'archive/refs/heads/main.zip';
        }

        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        $result   = $upgrader->install($zip_url);

        // Fallback to master branch if main branch zip fails
        if (is_wp_error($result) || $result === false || $result === null) {
            if (strpos($zip_url, '/heads/main.zip') !== false) {
                $zip_url = str_replace('/heads/main.zip', '/heads/master.zip', $zip_url);
                $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
                $result  = $upgrader->install($zip_url);
            }
        }

        if (is_wp_error($result)) {
            error_log('SCMP Install WP_Error: ' . $result->get_error_message());
            wp_send_json_error(['message' => $result->get_error_message()]);
        } elseif (!$result) {
            error_log('SCMP Install failed (returned false/null) for ZIP: ' . $zip_url);
            wp_send_json_error(['message' => 'Upgrader failed to install the plugin package.']);
        }

        // Rename extracted folder if GitHub appended "-main" or "-master" to the directory name
        $zip_base_url = $premium[$slug]['zip_url'];
        $repo_name = basename($zip_base_url);

        $target_folder = $premium[$slug]['target_folder'];
        $plugins_dir   = WP_PLUGIN_DIR;
        $main_folder   = $plugins_dir . '/' . $repo_name . '-main';
        $master_folder = $plugins_dir . '/' . $repo_name . '-master';
        $target_path   = $plugins_dir . '/' . $target_folder;

        if (!is_dir($target_path)) {
            if (is_dir($main_folder)) {
                rename($main_folder, $target_path);
            } elseif (is_dir($master_folder)) {
                rename($master_folder, $target_path);
            }
        }

        wp_clean_plugins_cache();

        $plugin_file = scmp_is_plugin_installed($slug);
        if ($plugin_file) {
            activate_plugin($plugin_file);
        }

        $installed = get_option('scmp_installed_plugins', []);
        $installed[] = $slug;
        update_option('scmp_installed_plugins', $installed);

        wp_send_json_success(['message' => "$slug installed successfully.", 'slug' => $slug]);
    }

    wp_send_json_error(['message' => "Unknown plugin slug: $slug"]);
}
