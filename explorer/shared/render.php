<?php
/**
 * AJAX Render Endpoint
 *
 * Accepts a component name and props via POST, renders the component,
 * and returns the HTML fragment.
 *
 * POST /shared/render.php
 * Content-Type: application/json
 * Body: { "component": "button", "props": { "text": "Hello" } }
 *
 * Response: rendered HTML (Content-Type: text/html)
 */

require_once __DIR__ . '/../../components/render.php';
anti_components_dir(__DIR__ . '/../../components');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['component'])) {
    http_response_code(400);
    echo 'Missing component name';
    exit;
}

$componentName = $input['component'];
$props = $input['props'] ?? [];

// Validate component exists
$known = scan_components(anti_components_dir());
if (!isset($known[$componentName])) {
    http_response_code(404);
    echo 'Unknown component: ' . htmlspecialchars($componentName);
    exit;
}

// Render component and return HTML
ob_start();
anti_component($componentName, $props);
$html = ob_get_clean();

header('Content-Type: text/html; charset=UTF-8');
echo $html;
