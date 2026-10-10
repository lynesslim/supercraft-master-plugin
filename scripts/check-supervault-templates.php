<?php
/**
 * SuperVault Template Code Sanitization Auditor
 *
 * This script checks all components stored on library.supercraft.my
 * to detect if any code (JavaScript, CSS, HTML) was corrupted by HTML entity
 * sanitization (e.g. single quotes converted to &#039; or &#39;).
 *
 * Usage:
 *   1. Standalone CLI:
 *      php scripts/check-supervault-templates.php <LICENSE_KEY> [SITE_DOMAIN]
 *
 *   2. Via WP-CLI (inside WordPress root):
 *      wp eval-file wp-content/plugins/supercraft-master-plugin/scripts/check-supervault-templates.php
 */

$is_wp = defined('ABSPATH');

if ($is_wp) {
    $license_key = get_option('supercraft_master_license_key', '');
    $site_url = get_site_url();
} else {
    $license_key = isset($argv[1]) ? trim($argv[1]) : '';
    $site_url = isset($argv[2]) ? trim($argv[2]) : 'https://supercraft.my';
}

if (empty($license_key)) {
    echo "\n[ERROR] License key is required.\n";
    echo "Usage:\n";
    echo "  php " . (isset($argv[0]) ? $argv[0] : 'check-supervault-templates.php') . " <LICENSE_KEY> [SITE_DOMAIN]\n\n";
    exit(1);
}

$api_base = 'https://library.supercraft.my/wp-json/supervault/v1';

function sv_request($url, $license, $domain) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $license,
        'X-Supercraft-Domain: ' . $domain,
        'Accept: application/json',
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => $err];
    }
    $json = json_decode($res, true);
    if ($code < 200 || $code >= 300) {
        $msg = isset($json['message']) ? $json['message'] : ('HTTP ' . $code . ': ' . $res);
        return ['success' => false, 'error' => $msg];
    }
    return ['success' => true, 'data' => $json];
}

echo "========================================================\n";
echo " SuperVault Template Sanitization Auditor\n";
echo " Target API: {$api_base}\n";
echo " Domain:     {$site_url}\n";
echo "========================================================\n\n";

echo "Fetching components list from SuperVault...\n";
$comp_res = sv_request($api_base . '/components', $license_key, $site_url);

if (!$comp_res['success']) {
    echo "[FAILED] Could not retrieve components: " . $comp_res['error'] . "\n";
    exit(1);
}

$components = $comp_res['data'];
if (!is_array($components) || empty($components)) {
    echo "No components found or empty response.\n";
    exit(0);
}

$total = count($components);
echo "Found {$total} components. Scanning JSON templates for corrupted entities...\n\n";

$corrupted_count = 0;
$clean_count = 0;
$corrupted_details = [];

function check_element_for_entities($el, &$findings, $path = '') {
    if (!is_array($el)) return;

    if (isset($el['widgetType'])) {
        $widget = $el['widgetType'];
    } elseif (isset($el['elType'])) {
        $widget = $el['elType'];
    } else {
        $widget = 'element';
    }

    $current_path = $path ? "{$path} > {$widget}" : $widget;

    if (isset($el['settings']) && is_array($el['settings'])) {
        foreach (['js', 'css', 'html', 'custom_css'] as $prop) {
            if (isset($el['settings'][$prop]) && is_string($el['settings'][$prop])) {
                $content = $el['settings'][$prop];
                $matches = [];
                // Look for HTML entities like &#039;, &#39;, &quot;, &amp;, &lt;, &gt;
                if (preg_match_all('/&#[0-9]{2,5};|&[a-zA-Z]+;/', $content, $matches)) {
                    $unique_entities = array_unique($matches[0]);
                    // Filter out harmless non-code entities if any, but in JS/CSS &#039;, &#39;, &quot;, &amp; are critical
                    $critical = array_filter($unique_entities, function($e) {
                        return in_array($e, ['&#039;', '&#39;', '&apos;', '&quot;', '&amp;', '&lt;', '&gt;']);
                    });
                    if (!empty($critical)) {
                        // Find first snippet
                        $pos = strpos($content, reset($critical));
                        $start = max(0, $pos - 30);
                        $snippet = substr($content, $start, 80);
                        $findings[] = [
                            'path'     => $current_path,
                            'property' => $prop,
                            'entities' => array_values($critical),
                            'snippet'  => trim($snippet),
                        ];
                    }
                }
            }
        }
    }

    if (isset($el['elements']) && is_array($el['elements'])) {
        foreach ($el['elements'] as $child) {
            check_element_for_entities($child, $findings, $current_path);
        }
    }
}

foreach ($components as $idx => $comp) {
    $id = isset($comp['id']) ? $comp['id'] : (isset($comp['ID']) ? $comp['ID'] : null);
    $title = isset($comp['title']) ? $comp['title'] : (isset($comp['name']) ? $comp['name'] : "Component #{$id}");

    if (!$id) continue;

    $progress = sprintf("[%3d/%3d] Checking ID #%-5s - %-30s", ($idx + 1), $total, $id, substr($title, 0, 30));
    echo $progress;

    $json_res = sv_request($api_base . '/component/' . $id . '/json', $license_key, $site_url);

    if (!$json_res['success']) {
        echo " -> [SKIP: {$json_res['error']}]\n";
        continue;
    }

    $data = $json_res['data'];
    $elements = isset($data['elements']) ? $data['elements'] : (is_array($data) ? $data : [$data]);

    $findings = [];
    if (is_array($elements)) {
        foreach ($elements as $el) {
            check_element_for_entities($el, $findings);
        }
    }

    if (!empty($findings)) {
        $corrupted_count++;
        echo " -> [CORRUPTED: " . count($findings) . " issue(s)]\n";
        $corrupted_details[] = [
            'id'       => $id,
            'title'    => $title,
            'findings' => $findings,
        ];
    } else {
        $clean_count++;
        echo " -> [OK]\n";
    }
}

echo "\n========================================================\n";
echo " Scan Results Summary\n";
echo "========================================================\n";
echo " Total Components Scanned: {$total}\n";
echo " Clean Components:         {$clean_count}\n";
echo " Corrupted Components:     {$corrupted_count}\n";
echo "========================================================\n\n";

if ($corrupted_count > 0) {
    echo "CORRUPTED COMPONENTS BREAKDOWN:\n\n";
    foreach ($corrupted_details as $item) {
        echo "• [ID {$item['id']}] {$item['title']}\n";
        foreach ($item['findings'] as $f) {
            echo "   - Field:    {$f['path']} > {$f['property']}\n";
            echo "   - Entities: " . implode(', ', $f['entities']) . "\n";
            echo "   - Snippet:  \"" . $f['snippet'] . "\"\n";
        }
        echo "\n";
    }
} else {
    echo "All inspected components have clean, uncorrupted code!\n";
}
