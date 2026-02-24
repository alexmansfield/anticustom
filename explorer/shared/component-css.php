<?php
/**
 * Component CSS Endpoint
 *
 * Returns CSS for component(s) using the specified style.
 *
 * GET /shared/component-css.php?name=button              — single component (base + style)
 * GET /shared/component-css.php?name=button&style=plato  — single component, explicit style
 * GET /shared/component-css.php?all=1&style=aristotle    — ALL components (base + style)
 *
 * Response: CSS text (Content-Type: text/css)
 */

// Sanitize style name: lowercase alphanumeric + hyphens only
$style = preg_replace('/[^a-z0-9-]/', '', $_GET['style'] ?? 'plato') ?: 'plato';
$all   = !empty($_GET['all']);

header('Content-Type: text/css; charset=UTF-8');

if ($all) {
    // Return CSS for every component in the given style
    require_once __DIR__ . '/../../components/render.php';
    anti_components_dir(__DIR__ . '/../../components');
    require_once __DIR__ . '/css.php';
    echo explorer_get_component_css($style);
    exit;
}

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
$baseFile  = $stylesDir . '/_base.css';
$styleFile = $stylesDir . '/' . $style . '.css';

// split=1: return JSON with individual file contents
if (!empty($_GET['split'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $result = [];
    if (file_exists($baseFile)) {
        $result['_base.css'] = file_get_contents($baseFile);
    }
    if (file_exists($styleFile)) {
        $result[basename($styleFile)] = file_get_contents($styleFile);
    }
    echo json_encode($result);
    exit;
}

$css = '';
if (file_exists($baseFile)) {
    $css .= file_get_contents($baseFile) . "\n";
}
if (file_exists($styleFile)) {
    $css .= file_get_contents($styleFile);
}

echo $css;
