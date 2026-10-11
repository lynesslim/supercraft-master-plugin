# SuperVault Server (`library.supercraft.my`) Inspection & Repair Guide

This guide explains how to audit, repair, and prevent sanitized code and stripped `<script>` / `<style>` tags in components stored on `library.supercraft.my`.

---

## 1. Why Did `<script>` and `<style>` Get Stripped?

In WordPress, `wp_kses_post()` strips `<script>` and `<style>` tags by default because non-admin requests or nonces lack `unfiltered_html` capability. 
When a template is pushed via the REST API (`/component/create`), if:
1. `wp_kses_post()` or `sanitize_text_field()` is applied to `elements_json`, or
2. `wp_insert_post()` runs without `kses_remove_filters()`, or
3. The REST API user context is not set to an administrator (`wp_set_current_user(1)`),

WordPress strips the outer `<script>` and `<style>` wrappers from native Elementor HTML widgets (`widgetType === 'html'`), leaving raw CSS and JS text inside `settings.html`. When imported, browsers render that code as visible text on the page.

---

## 2. Immediate Client-Side Auto-Healing (Already Active in v1.2.2)

In **Supercraft Master Plugin v1.2.2**, two-layer instant auto-healing is active:
1. **On Import/Insert**: `assets/editor-connector.js` inspects any inserted template. If an HTML widget has CSS or JS without wrapping tags, it reconstructs `<style>` and `<script>` tags automatically in memory before placing it on the canvas.
2. **On Proxy Download**: `supercraft-master-plugin.php` runs `scmp_restore_stripped_html_widget()` on incoming JSON.

**Users inserting components from SuperVault will no longer see broken code on their pages even if the server database has not been patched yet.**

---

## 3. Restoring Previous Components in the SuperVault Database

To permanently repair the existing components in `library.supercraft.my`'s database:

### Option A: Via WP-CLI on Hostinger (Recommended - 1 Command)
SSH into Hostinger / the server hosting `library.supercraft.my` and navigate to your WordPress root:

1. **Dry Run (Preview changes)**:
   ```bash
   wp eval-file wp-content/plugins/supercraft-master-plugin/scripts/repair-supervault-html-widgets.php --dry-run
   ```

2. **Execute Live Repair**:
   ```bash
   wp eval-file wp-content/plugins/supercraft-master-plugin/scripts/repair-supervault-html-widgets.php
   ```

### Option B: Via PHP CLI / Script
If you do not have WP-CLI:
1. Upload `scripts/repair-supervault-html-widgets.php` to your WordPress root directory on `library.supercraft.my`.
2. Run via SSH terminal:
   ```bash
   php repair-supervault-html-widgets.php
   ```
3. Delete the script file from the root directory after completion.

---

## 4. Preventing Future Stripping on `library.supercraft.my`

In the custom plugin or theme file on `library.supercraft.my` that registers `/wp-json/supervault/v1/component/create`:

### A. REST Route Parameter Sanitization
Ensure `elements_json` does **NOT** use `sanitize_text_field` or `wp_kses_post`:

```php
register_rest_route('supervault/v1', '/component/create', [
    'methods'  => 'POST',
    'callback' => 'supervault_handle_create_component',
    'args'     => [
        'elements_json' => [
            'required' => true,
            // Do NOT add sanitize_callback => 'sanitize_text_field' or 'wp_kses_post'
        ],
    ],
]);
```

### B. Disable KSES & Set Admin Context When Saving
Inside the callback function (`supervault_handle_create_component`):

```php
function supervault_handle_create_component($request) {
    // 1. Temporarily elevate to admin context so unfiltered_html is respected
    $admin_user = get_user_by('email', get_option('admin_email')) ?: get_user_by('id', 1);
    if ($admin_user) {
        wp_set_current_user($admin_user->ID);
    }

    // 2. Disable KSES filters to preserve <script> and <style>
    if (function_exists('kses_remove_filters')) {
        kses_remove_filters();
    }

    // 3. Get raw elements without WordPress stripping
    $elements_json = $request->get_param('elements_json');

    // 4. Create post
    $post_id = wp_insert_post([
        'post_title'   => sanitize_text_field($request->get_param('title')),
        'post_type'    => 'elementor_library', // or your custom post type
        'post_status'  => 'publish',
        'post_author'  => $admin_user ? $admin_user->ID : 1,
    ]);

    // 5. Save Elementor data directly using wp_slash
    if (is_array($elements_json)) {
        update_post_meta($post_id, '_elementor_data', wp_slash(wp_json_encode($elements_json)));
    } elseif (is_string($elements_json)) {
        update_post_meta($post_id, '_elementor_data', wp_slash($elements_json));
    }

    // 6. Restore KSES filters
    if (function_exists('kses_init_filters')) {
        kses_init_filters();
    }

    return rest_ensure_response(['success' => true, 'id' => $post_id]);
}
```

---

## 5. Audit Script

To verify all components across `library.supercraft.my` at any time:
```bash
php scripts/check-supervault-templates.php <LICENSE_KEY> [SITE_DOMAIN]
```
