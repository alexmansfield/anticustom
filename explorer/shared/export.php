<?php
/**
 * CSS/JSON Export Endpoint
 *
 * Accepts POST with JSON token data and returns generated CSS or JSON.
 *
 * Usage:
 *   POST /shared/export.php?format=css  — returns generated CSS file
 *   POST /shared/export.php?format=json — returns JSON file
 */

$format = $_GET['format'] ?? 'css';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'POST required';
    exit;
}

$body = file_get_contents('php://input');
$tokens = json_decode($body, true);

if ($tokens === null) {
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}

if ($format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="tokens.json"');
    echo json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// CSS export: write tokens to temp file, run generate.php, return output
$tmpFile = tempnam(sys_get_temp_dir(), 'anti_tokens_');
file_put_contents($tmpFile, json_encode($tokens));

$generatorPath = dirname(__DIR__, 2) . '/styles/generate.php';

$savedArgv = $GLOBALS['argv'] ?? [];
$savedArgc = $GLOBALS['argc'] ?? 0;
$GLOBALS['argv'] = ['generate.php', $tmpFile];
$GLOBALS['argc'] = 2;

ob_start();
include $generatorPath;
$css = ob_get_clean();

$GLOBALS['argv'] = $savedArgv;
$GLOBALS['argc'] = $savedArgc;

unlink($tmpFile);

header('Content-Type: text/css');
header('Content-Disposition: attachment; filename="tokens.css"');
echo $css;
