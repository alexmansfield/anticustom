<?php
/**
 * Styles View — Design Token Reference
 *
 * Lists all CSS custom properties generated from the token system.
 */

// Build token rows from defaults.json + schema
$tokenRows = [];
$defaultsPath = dirname(__DIR__, 2) . '/styles/defaults.json';
$schemaPath = dirname(__DIR__, 2) . '/styles/tokens.schema.json';
$tokenData = json_decode(file_get_contents($defaultsPath), true);
$schema = json_decode(file_get_contents($schemaPath), true);
$schemaSizes = $schema['sizes'] ?? [];

// Spacing tokens
$spaceBase = $tokenData['spacing']['baseSize'] ?? 16;
$spaceScale = $tokenData['spacing']['scale'] ?? 1.5;
foreach (($schemaSizes['spacingSizes']['items'] ?? []) as $size => $def) {
    $sizeData = $tokenData['spacing']['sizes'][$size] ?? [];
    if (!empty($sizeData['enabled']) && isset($sizeData['value'])) {
        $val = $sizeData['value'];
    } elseif (isset($def['position'])) {
        $val = round($spaceBase * pow($spaceScale, $def['position']));
    } else {
        continue;
    }
    $tokenRows[] = ['id' => "space-{$size}", 'variable' => "--space-{$size}", 'category' => 'Spacing', 'default_value' => "{$val}px"];
}

// Typography — text sizes
$textBase = $tokenData['typography']['text']['baseSize'] ?? 16;
$textScale = $tokenData['typography']['text']['scale'] ?? 1.125;
foreach (($schemaSizes['textSizes']['items'] ?? []) as $size => $def) {
    $pos = $def['position'] ?? 0;
    $val = round($textBase * pow($textScale, $pos), 1);
    $tokenRows[] = ['id' => "text-{$size}", 'variable' => "--text-{$size}", 'category' => 'Typography', 'default_value' => "{$val}px"];
}

// Typography — heading sizes
$headingBase = $tokenData['typography']['headings']['baseSize'] ?? 16;
$headingScale = $tokenData['typography']['headings']['scale'] ?? 1.618;
foreach (($schemaSizes['headingLevels']['items'] ?? []) as $key => $def) {
    $pos = $def['position'] ?? 0;
    $cssKey = $def['cssKey'] ?? "heading-{$key}";
    $val = round($headingBase * pow($headingScale, $pos));
    $tokenRows[] = ['id' => $cssKey, 'variable' => "--{$cssKey}", 'category' => 'Typography', 'default_value' => "{$val}px"];
}

// Colors
foreach ($tokenData['color']['sections'] ?? [] as $section) {
    foreach ($section['colors'] ?? [] as $name => $colorData) {
        if (isset($colorData['color'])) {
            $tokenRows[] = ['id' => $name, 'variable' => "--{$name}", 'category' => 'Colors', 'default_value' => $colorData['color']];
        }
    }
}

// Borders
foreach ($tokenData['borders']['sizes'] ?? [] as $size => $data) {
    if (isset($data['value'])) {
        $tokenRows[] = ['id' => "border-{$size}", 'variable' => "--border-{$size}", 'category' => 'Borders', 'default_value' => "{$data['value']}px"];
    }
}

// Shadows
foreach ($tokenData['shadows'] ?? [] as $size => $s) {
    $val = ($s['x'] ?? 0) . 'px ' . ($s['y'] ?? 0) . 'px ' . ($s['blur'] ?? 0) . 'px ' . ($s['spread'] ?? 0) . 'px rgba(0,0,0,' . ($s['opacity'] ?? 0.1) . ')';
    $tokenRows[] = ['id' => "shadow-{$size}", 'variable' => "--shadow-{$size}", 'category' => 'Shadows', 'default_value' => $val];
}

// Radius
foreach ($tokenData['radius']['sizes'] ?? [] as $size => $data) {
    if (isset($data['value'])) {
        $tokenRows[] = ['id' => "radius-{$size}", 'variable' => "--radius-{$size}", 'category' => 'Radius', 'default_value' => "{$data['value']}px"];
    }
}
?>

<?php
anti_component('section', [
    'padding_top' => 'xl',
    'padding_bottom' => 'xl',
    'gap' => 'l',
    'children' => [
        [
            'type' => 'intro',
            'props' => [
                'title' => 'Design Tokens',
                'size' => 'l',
            ],
        ],
        [
            'type' => 'container',
            'props' => [
                'children' => [
                    [
                        'type' => 'table',
                        'props' => [
                            'columns' => [
                                ['key' => 'variable', 'label' => 'Variable'],
                                ['key' => 'category', 'label' => 'Category'],
                                ['key' => 'default_value', 'label' => 'Default'],
                            ],
                            'data' => $tokenRows,
                            'row_key' => 'id',
                        ],
                    ],
                ],
            ],
        ],
    ],
]);
