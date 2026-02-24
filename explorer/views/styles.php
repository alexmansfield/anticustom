<?php
/**
 * Styles View — Token Preview
 *
 * Renders a rich component showcase demonstrating how design tokens
 * affect real components. Uses anti_component() for everything.
 */

// Hero section — demonstrates primary colorway, spacing, typography
anti_component('hero', [
    'alignment' => 'center',
    'size' => 'lg',
    'colorway' => 'primary',
    'children' => [
        [
            'type' => 'intro',
            'props' => [
                'title' => 'Design Tokens',
                'subtitle' => 'A unified system of spacing, typography, colors, and borders that drives every component on this page.',
                'align' => 'center',
                'size' => 'l',
            ],
        ],
        [
            'type' => 'button',
            'props' => [
                'text' => 'Explore Components',
                'url' => '?tool=components',
                'variant' => 'solid',
                'colorway' => 'primary',
            ],
        ],
        [
            'type' => 'button',
            'props' => [
                'text' => 'View Source',
                'url' => '#',
                'variant' => 'outline',
                'colorway' => 'primary',
            ],
        ],
    ],
]);

// Card showcase — 3-column grid demonstrating variants
anti_component('section', [
    'colorway' => 'default',
    'padding_top' => 'xxl',
    'padding_bottom' => 'xxl',
    'gap' => 'l',
    'children' => [
        [
            'type' => 'intro',
            'props' => [
                'eyebrow' => 'Components',
                'title' => 'Card Variants',
                'subtitle' => 'Cards adapt to spacing, radius, and shadow tokens.',
                'align' => 'center',
                'size' => 'm',
            ],
        ],
        [
            'type' => 'container',
            'props' => [
                'layout' => 'grid',
                'columns' => '3',
                'gap' => 'l',
                'children' => [
                    [
                        'type' => 'card',
                        'props' => [
                            'title' => 'Elevated Card',
                            'description' => 'Uses shadow tokens for depth. Adjust shadow-m and shadow-l in the panel to see changes.',
                            'variant' => 'elevated',
                            'icon' => '&#9650;',
                            'link_text' => 'Learn More',
                            'link_url' => '#',
                        ],
                    ],
                    [
                        'type' => 'card',
                        'props' => [
                            'title' => 'Bordered Card',
                            'description' => 'Uses border tokens for structure. Change border widths and radius values to reshape.',
                            'variant' => 'bordered',
                            'icon' => '&#9632;',
                            'link_text' => 'Learn More',
                            'link_url' => '#',
                        ],
                    ],
                    [
                        'type' => 'card',
                        'props' => [
                            'title' => 'Flat Card',
                            'description' => 'Minimal style — no shadow, no border. Typography and spacing tokens define the feel.',
                            'variant' => 'flat',
                            'icon' => '&#9644;',
                            'link_text' => 'Learn More',
                            'link_url' => '#',
                        ],
                    ],
                ],
            ],
        ],
    ],
]);

// Stats row — demonstrates spacing and typography tokens
anti_component('section', [
    'colorway' => 'primary',
    'padding_top' => 'xl',
    'padding_bottom' => 'xl',
    'gap' => 'l',
    'children' => [
        [
            'type' => 'intro',
            'props' => [
                'title' => 'By the Numbers',
                'subtitle' => 'Stats use heading tokens for values and text tokens for labels.',
                'align' => 'center',
                'size' => 'm',
            ],
        ],
        [
            'type' => 'container',
            'props' => [
                'layout' => 'grid',
                'columns' => '3',
                'gap' => 'l',
                'children' => [
                    [
                        'type' => 'stats',
                        'props' => [
                            'value' => '11',
                            'label' => 'Components',
                            'suffix' => '',
                            'description' => 'Ready to use',
                            'variant' => 'ghost',
                        ],
                    ],
                    [
                        'type' => 'stats',
                        'props' => [
                            'value' => '48',
                            'label' => 'Design Tokens',
                            'suffix' => '+',
                            'description' => 'CSS variables generated',
                            'variant' => 'ghost',
                        ],
                    ],
                    [
                        'type' => 'stats',
                        'props' => [
                            'value' => '1.618',
                            'label' => 'Golden Ratio',
                            'prefix' => '',
                            'suffix' => '',
                            'description' => 'Default heading scale',
                            'variant' => 'ghost',
                        ],
                    ],
                ],
            ],
        ],
    ],
]);

// Token reference table data — built from defaults.json + schema positions
$tokenRows = [];
$defaultsPath = dirname(__DIR__, 2) . '/styles/defaults.json';
$schemaPath = dirname(__DIR__, 2) . '/styles/tokens.schema.json';
$tokenData = json_decode(file_get_contents($defaultsPath), true);
$schema = json_decode(file_get_contents($schemaPath), true);
$schemaSizes = $schema['sizes'] ?? [];

// Spacing tokens — positions from schema
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

// Typography — text sizes from schema
$textBase = $tokenData['typography']['text']['baseSize'] ?? 16;
$textScale = $tokenData['typography']['text']['scale'] ?? 1.125;
foreach (($schemaSizes['textSizes']['items'] ?? []) as $size => $def) {
    $pos = $def['position'] ?? 0;
    $val = round($textBase * pow($textScale, $pos), 1);
    $tokenRows[] = ['id' => "text-{$size}", 'variable' => "--text-{$size}", 'category' => 'Typography', 'default_value' => "{$val}px"];
}

// Typography — heading sizes from schema
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

// Testimonial + Badge showcase
anti_component('section', [
    'colorway' => 'default',
    'padding_top' => 'xxl',
    'padding_bottom' => 'xxl',
    'gap' => 'l',
    'children' => [
        [
            'type' => 'intro',
            'props' => [
                'eyebrow' => 'Token System',
                'title' => 'Everything is Connected',
                'subtitle' => 'Badges, testimonials, and every other component draw from the same token pool.',
                'align' => 'center',
                'size' => 'm',
            ],
        ],
        [
            'type' => 'container',
            'props' => [
                'layout' => 'flex',
                'direction' => 'row',
                'wrap' => 'wrap',
                'gap' => 's',
                'justify' => 'center',
                'children' => [
                    ['type' => 'badge', 'props' => ['text' => 'spacing', 'variant' => 'neutral']],
                    ['type' => 'badge', 'props' => ['text' => 'typography', 'variant' => 'info']],
                    ['type' => 'badge', 'props' => ['text' => 'colors', 'variant' => 'success']],
                    ['type' => 'badge', 'props' => ['text' => 'borders', 'variant' => 'warning']],
                    ['type' => 'badge', 'props' => ['text' => 'shadows', 'variant' => 'danger']],
                    ['type' => 'badge', 'props' => ['text' => 'radius', 'variant' => 'neutral']],
                ],
            ],
        ],
        [
            'type' => 'container',
            'props' => [
                'layout' => 'grid',
                'columns' => '2',
                'gap' => 'l',
                'children' => [
                    [
                        'type' => 'testimonial',
                        'props' => [
                            'quote' => 'The token system makes it incredibly easy to maintain visual consistency across the entire component library.',
                            'author_name' => 'Design Systems Lead',
                            'author_role' => 'Anticustom Team',
                            'rating' => 5,
                            'show_rating' => 'true',
                            'variant' => 'default',
                        ],
                    ],
                    [
                        'type' => 'testimonial',
                        'props' => [
                            'quote' => 'Change one token value and watch it cascade through every component on the page. That is the power of a unified design system.',
                            'author_name' => 'Frontend Developer',
                            'author_role' => 'Anticustom Team',
                            'rating' => 5,
                            'show_rating' => 'false',
                            'variant' => 'default',
                        ],
                    ],
                ],
            ],
        ],
    ],
]);

// Token reference table
anti_component('section', [
    'colorway' => 'default',
    'padding_top' => 'xxl',
    'padding_bottom' => 'xxl',
    'gap' => 'l',
    'children' => [
        [
            'type' => 'intro',
            'props' => [
                'eyebrow' => 'Reference',
                'title' => 'Token Variables',
                'subtitle' => 'All CSS custom properties generated from the token system. Edit values in the panel to see live changes.',
                'align' => 'center',
                'size' => 'm',
            ],
        ],
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
]);
