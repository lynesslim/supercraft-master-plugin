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
 *    wp eval-file repair-supervault-html-widgets.php dry-run
 *
 * 2. Execute live repair:
 *    wp eval-file repair-supervault-html-widgets.php
 */

if (!defined('ABSPATH')) {
    if (file_exists(__DIR__ . '/wp-load.php')) {
        require_once __DIR__ . '/wp-load.php';
    } elseif (file_exists(dirname(__DIR__, 4) . '/wp-load.php')) {
        require_once dirname(__DIR__, 4) . '/wp-load.php';
    } elseif (file_exists(dirname(__DIR__, 3) . '/wp-load.php')) {
        require_once dirname(__DIR__, 3) . '/wp-load.php';
    } else {
        echo "[ERROR] WordPress environment (wp-load.php) not found.\n";
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

/**
 * Reconstructs missing <style> and <script> tags in Elementor HTML widgets.
 */
function sv_heal_html_widget($content) {
    if (!is_string($content) || trim($content) === '') {
        return $content;
    }

    $has_style = stripos($content, '<style') !== false;
    $has_script = stripos($content, '<script') !== false;
    if ($has_style && $has_script) {
        return $content;
    }

    $raw = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // JavaScript start entry points
    $js_regex = '/(?:\n|^)[ \t]*(\(function\s*\(|document\.addEventListener|window\.addEventListener|\$\(document\)\.ready|jQuery\(document\)\.ready)/';
    $js_part = '';
    $non_js_part = $raw;

    if (!$has_script && preg_match($js_regex, $raw, $matches, PREG_OFFSET_CAPTURE)) {
        $js_offset = $matches[0][1];
        $non_js_part = substr($raw, 0, $js_offset);
        $js_part = trim(substr($raw, $js_offset));
    }

    $css_part = '';
    $html_part = '';

    if (!$has_style) {
        $trimmed_non_js = trim($non_js_part);
        // Case A: Starts with HTML tag, followed by CSS
        if (preg_match('/^\s*<[a-z][a-z0-9]*\b/i', $trimmed_non_js)) {
            if (preg_match('/(<\/([a-z][a-z0-9]*)>\s*)((?:\/\*[\s\S]*?\*\/\s*)*(\.[a-zA-Z0-9_-]+|\#[a-zA-Z0-9_-]+|@media|@keyframes)\s*\{)/i', $trimmed_non_js, $m, PREG_OFFSET_CAPTURE)) {
                $css_start = $m[3][1];
                $html_part = trim(substr($trimmed_non_js, 0, $css_start));
                $css_part = trim(substr($trimmed_non_js, $css_start));
            } else {
                $html_part = $trimmed_non_js;
            }
        } else {
            // Case B: Starts with CSS (or pure CSS). Check if HTML follows.
            if (preg_match('/(?:\n|^)[ \t]*(?:<!--[\s\S]*?-->\s*)*<[a-z][a-z0-9]*\b[^>]*>/i', $trimmed_non_js, $m, PREG_OFFSET_CAPTURE)) {
                $html_start = $m[0][1];
                $candidate_css = trim(substr($trimmed_non_js, 0, $html_start));
                if (strpos($candidate_css, '{') !== false && strpos($candidate_css, '}') !== false) {
                    $css_part = $candidate_css;
                    $html_part = trim(substr($trimmed_non_js, $html_start));
                } else {
                    $html_part = $trimmed_non_js;
                }
            } else {
                if (strpos($trimmed_non_js, '{') !== false && strpos($trimmed_non_js, '}') !== false) {
                    $css_part = $trimmed_non_js;
                } else {
                    $html_part = $trimmed_non_js;
                }
            }
        }
    } else {
        $html_part = $non_js_part;
    }

    $reconstructed = [];
    if ($css_part !== '') {
        $reconstructed[] = "<style>\n" . $css_part . "\n</style>";
    }
    if ($html_part !== '') {
        $reconstructed[] = $html_part;
    }
    if ($js_part !== '') {
        $reconstructed[] = "<script>\n" . $js_part . "\n</script>";
    }

    return !empty($reconstructed) ? implode("\n\n", $reconstructed) : $content;
}

function sv_traverse_and_heal(&$elements, &$healed_count) {
    if (!is_array($elements)) return;

    if (isset($elements[0])) {
        foreach ($elements as &$child) {
            sv_traverse_and_heal($child, $healed_count);
        }
        return;
    }

    if (isset($elements['settings']) && is_array($elements['settings'])) {
        // Also clean entities in all code fields
        foreach (['js', 'css', 'html', 'custom_css'] as $prop) {
            if (isset($elements['settings'][$prop]) && is_string($elements['settings'][$prop])) {
                $orig = $elements['settings'][$prop];
                $elements['settings'][$prop] = html_entity_decode($orig, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if (isset($elements['widgetType']) && $elements['widgetType'] === 'html' && isset($elements['settings']['html'])) {
            $current_html = $elements['settings']['html'];
            $healed_html = sv_heal_html_widget($current_html);
            if ($healed_html !== $current_html) {
                $elements['settings']['html'] = $healed_html;
                $healed_count++;
            }
        }
    }

    if (isset($elements['elements']) && is_array($elements['elements'])) {
        foreach ($elements['elements'] as &$child) {
            sv_traverse_and_heal($child, $healed_count);
        }
    }
}

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
