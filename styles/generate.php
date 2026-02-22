<?php
/**
 * CSS Variable Generator
 *
 * Reads a design token JSON file and outputs CSS custom properties.
 * Based on DesignToken::toCssVariables() from the antisloth reference.
 *
 * Usage: php styles/generate.php [path/to/tokens.json]
 */

$defaultPath = __DIR__ . '/defaults.json';
$path = $argv[1] ?? $defaultPath;

if (!file_exists($path)) {
    fprintf(STDERR, "Error: File not found: %s\n", $path);
    exit(1);
}

$json = file_get_contents($path);
$tokens = json_decode($json, true);

if ($tokens === null) {
    fprintf(STDERR, "Error: Invalid JSON in %s\n", $path);
    exit(1);
}

echo ":root {\n";

foreach ($tokens as $category => $values) {
    if (!is_array($values)) {
        continue;
    }
    foreach ($values as $name => $data) {
        $value = is_array($data) ? ($data['value'] ?? '') : $data;
        if ($value !== '' && $value !== null) {
            echo "    --{$category}-{$name}: {$value};\n";
        }
    }
}

echo "}\n";
