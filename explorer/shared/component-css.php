<?php
/**
 * Component CSS Endpoint
 *
 * Returns the concatenated CSS (_base.css + default.css) for a single component.
 *
 * GET /shared/component-css.php?name=button
 * Response: CSS text (Content-Type: text/css)
 */

require_once __DIR__ . '/../../components/render.php';

$name = $_GET['name'] ?? '';
if (!$name) {
    http_response_code(400);
    echo 'Missing component name';
    exit;
}

$baseDir = __DIR__ . '/../../components';
$components = scan_components($baseDir);

if (!isset($components[$name])) {
    http_response_code(404);
    echo 'Unknown component: ' . htmlspecialchars($name);
    exit;
}

$comp = $components[$name];
$stylesDir = $comp['path'] . '/styles';
$css = '';

$baseFile = $stylesDir . '/_base.css';
if (file_exists($baseFile)) {
    $css .= file_get_contents($baseFile) . "\n";
}

$defaultFile = $stylesDir . '/default.css';
if (file_exists($defaultFile)) {
    $css .= file_get_contents($defaultFile);
}

header('Content-Type: text/css; charset=UTF-8');
echo $css;
