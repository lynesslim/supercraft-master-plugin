# SuperVault Server (`library.supercraft.my`) Inspection & Repair Guide

This guide explains how to check and fix sanitized code in components stored on `library.supercraft.my`.

---

## 1. Quick Audit via API (Using the auditor script)

You can run the audit script directly from terminal with your Supercraft license key:

```bash
php "scripts/check-supervault-templates.php" <YOUR_LICENSE_KEY> [YOUR_DOMAIN]
```

Or via WP-CLI on any connected WordPress site:
```bash
wp eval-file wp-content/plugins/supercraft-master-plugin/scripts/check-supervault-templates.php
```

The script will fetch every component from `library.supercraft.my`, traverse its element tree, and report any components that contain `&#039;`, `&#39;`, `&quot;`, `&amp;`, or other HTML entities in their `js`, `css`, or `html` code.

---

## 2. Direct MySQL / phpMyAdmin Check on Hostinger

If you have access to phpMyAdmin or MySQL on the Hostinger server for `library.supercraft.my`:

### Step 1: Detect Corrupted Templates
Run this query to find all template records containing encoded single quotes:
```sql
SELECT post_id, meta_key, SUBSTRING(meta_value, 1, 300) AS sample
FROM wp_postmeta 
WHERE meta_key = '_elementor_data' 
  AND (meta_value LIKE '%&#039;%' OR meta_value LIKE '%&#39;%');
```

Also check if any components store JSON in other postmeta keys (e.g., `elements_json` or `component_elements`):
```sql
SELECT post_id, meta_key, SUBSTRING(meta_value, 1, 300) AS sample
FROM wp_postmeta 
WHERE (meta_key LIKE '%element%' OR meta_key LIKE '%json%')
  AND (meta_value LIKE '%&#039;%' OR meta_value LIKE '%&#39;%');
```

### Step 2: In-Place Database Fix (Optional)
To replace `&#039;` and `&#39;` back to literal single quotes `'` across all templates in the database:
```sql
-- Fix &#039;
UPDATE wp_postmeta 
SET meta_value = REPLACE(meta_value, '&#039;', '\'')
WHERE meta_key = '_elementor_data' 
  AND meta_value LIKE '%&#039;%';

-- Fix &#39;
UPDATE wp_postmeta 
SET meta_value = REPLACE(meta_value, '&#39;', '\'')
WHERE meta_key = '_elementor_data' 
  AND meta_value LIKE '%&#39;%';
```

---

## 3. Permanent Fix in the SuperVault Server Code (`library.supercraft.my`)

Look in the plugin or theme file that registers `/wp-json/supervault/v1/component/create`:

### A. Check REST Route Registration
Ensure `elements_json` does **NOT** have a sanitization callback like `sanitize_text_field` or `wp_kses_post`:
```php
// ❌ WRONG - This converts quotes into &#039; and breaks JSON:
'elements_json' => [
    'required' => true,
    'sanitize_callback' => 'sanitize_text_field',
]

// ✅ CORRECT:
'elements_json' => [
    'required' => true,
    // Do not apply string sanitizers to JSON payloads
]
```

### B. Disable KSES when Saving Template Posts
When saving the template post in WordPress:
```php
// Remove WordPress KSES filters so quotes aren't entity-encoded
kses_remove_filters();

$post_id = wp_insert_post([
    'post_title'  => $title,
    'post_type'   => 'elementor_library', // or custom post type
    'post_status' => 'publish',
]);

// Always use wp_slash when updating _elementor_data in postmeta
update_post_meta($post_id, '_elementor_data', wp_slash(wp_json_encode($elements_data)));

// Restore KSES filters
kses_init_filters();
```
