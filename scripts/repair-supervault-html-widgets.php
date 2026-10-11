<?php
/**
 * SuperVault HTML Widget Restorer
 *
 * This script repairs all Elementor templates stored on library.supercraft.my
 * where WordPress KSES stripped <style> and <script> tags from native Elementor HTML widgets,
 * causing raw CSS and JavaScript code to render as visible text on the page.
 *
 * Usage on Hostinger / Server:
 *
 * 1. Dry run (preview without modifying database):
 *    wp eval-file repair-supervault-html-widgets.php --dry-run
 *
 * 2. Execute live repair:
 *    wp eval-file repair-supervault-html-widgets.php
 *
 * 3. Or place in WordPress root and run via CLI:
 *    php repair-supervault-html-widgets.php [--dry-run]
 */

if (!defined('ABSPATH')) {
    // If run directly via CLI in WordPress root
    if (file_exists(__DIR__ . '/wp-load.php')) {
        require_once __DIR__ . '/wp-load.php';
    } elseif (file_exists(dirname(__DIR__, 4) . '/wp-load.php')) {
        require_once dirname(__DIR__, 4) . '/wp-load.php';
    } elseif (file_exists(dirname(__DIR__, 3) . '/wp-load.php')) {
        require_once dirname(__DIR__, 3) . '/wp-load.php';
    } else {
        echo "[ERROR] WordPress environment (wp-load.php) not found.\n";
        echo "Please run via WP-CLI inside your WordPress root:\n";
        echo "  wp eval-file wp-content/plugins/supercraft-master-plugin/scripts/repair-supervault-html-widgets.php\n\n";
        exit(1);
    }
}

// Support arguments from both WP-CLI ($args) and CLI ($argv)
$all_args = array_merge($argv ?? [], $args ?? []);
$dry_run = in_array('--dry-run', $all_args) || in_array('dry-run', $all_args);

@ini_set('memory_limit', '512M');

echo "========================================================\n";
echo " SuperVault HTML Widget Restorer\n";
echo " Mode: " . ($dry_run ? "DRY-RUN (Preview only)" : "LIVE REPAIR (Writing to database)") . "\n";
echo " Site: " . get_site_url() . "\n";
echo "========================================================\n\n";

global $wpdb;

// Find only meta records that actually contain HTML widgets (filters down from thousands of posts)
$target_meta_rows = $wpdb->get_results("
    SELECT meta_id, post_id, meta_key 
    FROM {$wpdb->postmeta} 
    WHERE meta_key IN ('_elementor_data', 'elements_json', 'component_elements')
      AND (meta_value LIKE '%\"widgetType\":\"html\"%' OR meta_value LIKE '%widgetType%html%')
");

echo "Found " . count($target_meta_rows) . " template records containing HTML widgets.\n\n";

$repaired_posts = 0;
$repaired_widgets = 0;

if (!$dry_run && function_exists('kses_remove_filters')) {
    kses_remove_filters();
}

foreach ($target_meta_rows as $row) {
    // Fetch row individually to keep memory usage minimal
    $raw_val = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d",
        $row->meta_id
    ));

    if (empty($raw_val)) continue;

    $elements = json_decode($raw_val, true);
    if (!is_array($elements)) {
        unset($raw_val, $elements);
        continue;
    }

    $widget_count = 0;
    sv_traverse_and_heal($elements, $widget_count);

    if ($widget_count > 0) {
        $post_title = get_the_title($row->post_id) ?: "Post #{$row->post_id}";
        echo "[REPAIR] Post #{$row->post_id} ('{$post_title}'): Restored {$widget_count} stripped HTML widget(s) in meta '{$row->meta_key}'\n";

        if (!$dry_run) {
            $updated_json = wp_json_encode($elements);
            update_post_meta($row->post_id, $row->meta_key, wp_slash($updated_json));
            unset($updated_json);
        }

        $repaired_posts++;
        $repaired_widgets += $widget_count;
    }

    unset($raw_val, $elements);
}

if (!$dry_run && function_exists('kses_init_filters')) {
    kses_init_filters();
}

echo "\n========================================================\n";
echo " Scan Complete!\n";
echo " Posts Repaired:    {$repaired_posts}\n";
echo " Widgets Restored:  {$repaired_widgets}\n";
if ($dry_run) {
    echo " (DRY-RUN: No changes written. Run without dry-run to apply)\n";
} else {
    echo " All stripped widgets have been successfully restored in database!\n";
}
echo "========================================================\n";
