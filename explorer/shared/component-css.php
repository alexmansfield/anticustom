<?php
/**
 * Component CSS Endpoint
 *
 * Returns the concatenated CSS (_base.css + default.css) for a single component.
 *
 * GET /shared/component-css.php?name=button
 * Response: CSS text (Content-Type: text/css)
 */

$name = $_GET['name'] ?? '';
if (!$name) {
    http_response_code(400);
    echo 'Missing component name';
    exit;
}

// Check component directly instead of scanning all components
$componentDir = __DIR__ . '/../../components/' . $name;
if (!is_dir($componentDir)) {
    http_response_code(404);
    echo 'Unknown component: ' . htmlspecialchars($name);
    exit;
}

$stylesDir = $componentDir . '/styles';
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
