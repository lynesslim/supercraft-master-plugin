# Supercraft Plugins: License Verification System Specifications

This document outlines the license verification and domain registration workflow implemented in individual Supercraft plugins. Use this specification to replicate, consolidate, and handle the verification process at the Master Plugin level.

---

## 1. Architecture Overview

Currently, each individual Supercraft plugin (e.g., `supercraft-superanimation`) manages its own validation status, local options, and remote API calls. When consolidating to a Master Plugin, you will want to centralize:
1. **License & Embed Code Storage**: Storing the license details globally or checking a single unified license.
2. **API Communication**: Performing the remote validations.
3. **Helper Function**: Offering a global function like `supercraft_is_validated()` (or equivalent filters) that sub-plugins can query to toggle features on or off.

---

## 2. Options & Database Schema

In individual plugins, the following WordPress options are used to track status:

| Option Name | Type | Description |
| :--- | :--- | :--- |
| `supercraft_embed_code` | `string` | The unique user license/embed token. |
| `supercraft_validation_status` | `string` | The local verification status. Values: `'valid'`, `'invalid'`, `'not_set'`. |
| `supercraft_last_validated` | `string` | MySQL format timestamp of the last successful verification check. |

*For the Master Plugin, you should prefix/nest these under a unified namespace (e.g., `supercraft_master_license_key`, `supercraft_master_status`) so all child plugins reference a single master database option.*

---

## 3. Remote Verification APIs

All API communications are made using JSON payloads. Timeout is configured to `15` seconds.

### 3.1 Validate Embed Code (Registration)
Used when a user submits an embed code to validate it and register the current domain.

* **Endpoint**: `https://superapp.supercraft.my/api/public/check-license`
* **Method**: `POST`
* **Headers**: 
  * `Content-Type: application/json`
* **Payload**:
  ```json
  {
    "embed_code": "USER_EMBED_CODE_HERE",
    "plugin_name": "supercraft-superanimation",
    "domain": "https://example.com"
  }
  ```
  *(Note: `domain` is populated dynamically using WordPress `get_site_url()`)*
* **Expected Response**:
  * Status code: `200` (success range `200-399`)
  * Body:
    ```json
    {
      "valid": true
    }
    ```

---

### 3.2 Unlink Registration (Deregistration)
Used when a user decides to "Unlink" or deactivate their embed code on the site, freeing up the domain slot on the licensing server.

* **Endpoint**: `https://superapp.supercraft.my/api/public/validate-embed/delete-registration`
* **Method**: `DELETE`
* **Headers**: 
  * `Content-Type: application/json`
* **Payload**:
  ```json
  {
    "embed_code": "USER_EMBED_CODE_HERE",
    "plugin_name": "supercraft-superanimation"
  }
  ```
* **Expected Response**:
  * Status code: `200` (success range `200-399`)

---

## 4. Local Validation Rules & Overrides

### 4.1 Local Override Constant
To allow developers or environments to bypass validation (e.g., local development), a PHP constant check is supported:
```php
if (defined('SUPERCRAFT_ALLOW_UNVALIDATED') && SUPERCRAFT_ALLOW_UNVALIDATED) {
    return true; // Bypasses API validation
}
```

### 4.2 Endpoint Overrides
For testing and staging endpoints, the plugins check for global constants:
* **Validation Override**: `SUPERCRAFT_VALIDATION_ENDPOINT`
* **Unlink Override**: `SUPERCRAFT_DELETE_REGISTRATION_ENDPOINT`
* **Plugin Identifier Override**: `SUPERCRAFT_PLUGIN_NAME` (default: `'supercraft-superanimation'`)

---

## 5. Plugin Behavior Based on Validation

When validation returns `false` (i.e. status is `'invalid'` or `'not_set'`), child plugins perform the following restrictions:

1. **Frontend Assets**: Do not enqueue the main scripts and styles (e.g., `animation-preset-plugin.js`, `animation-preset-plugin.css`).
2. **Elementor Controls**: Prevent registering control panels or widget configurations inside the Elementor editor.
3. **Elementor Widget Rendering**: Suppress adding layout attributes or custom animation triggers during HTML render.
4. **Admin Dashboard Notices**: Display warning banners notifying the administrator that the plugin is inactive due to an invalid/missing license.

---

## 6. Centralization & Master Plugin Integration

To centralize license checking without breaking standalone plugin instances, a decoupled **WordPress Filter Hook** is the selected integration method.

### Integration Standard: WordPress Filter
The child plugin applies a filter hook before checking local validation status. If the Master Plugin is active, it intercepts this filter to return the global validation state.

* **Child Plugin Implementation (`supercraft_is_validated`):**
  ```php
  function supercraft_is_validated() {
      if (defined('SUPERCRAFT_ALLOW_UNVALIDATED') && SUPERCRAFT_ALLOW_UNVALIDATED) {
          return true;
      }
      
      // Default to checking local options, but allow the Master Plugin to filter/override it
      $local_status = get_option('supercraft_validation_status', 'not_set') === 'valid';
      return apply_filters('supercraft_is_plugin_validated', $local_status, 'supercraft-superanimation');
  }
  ```

* **Master Plugin Hook Implementation:**
  The Master Plugin developer must register a callback for this filter to override child validation statuses.
  ```php
  add_filter('supercraft_is_plugin_validated', function($is_valid, $plugin_slug) {
      // Query global master status instead of the child's local option
      $master_status = get_option('supercraft_master_validation_status', 'not_set');
      
      if ($master_status === 'valid') {
          return true;
      }
      
      return $is_valid;
  }, 10, 2);
  ```


