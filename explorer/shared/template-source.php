<?php
/**
 * Template Source Endpoint
 *
 * Returns the raw PHP template source for a given component.
 *
 * GET /shared/template-source.php?name=button
 *
 * Response: raw PHP source (Content-Type: text/plain)
 */

require_once __DIR__ . '/../../components/render.php';
anti_components_dir(__DIR__ . '/../../components');

$name = preg_replace('/[^a-z0-9-]/', '', $_GET['name'] ?? '');
if (!$name) {
    http_response_code(400);
    echo 'Missing component name';
    exit;
}

$templateFile = anti_components_dir() . '/' . $name . '/templates/' . $name . '.php';
if (!file_exists($templateFile)) {
    http_response_code(404);
    echo 'Template not found';
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
echo file_get_contents($templateFile);
