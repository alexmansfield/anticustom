<?php
/**
 * Components View — Component Gallery
 *
 * Loops through all discovered components and renders each
 * with sensible sample props.
 */

$components = scan_components(anti_components_dir());

// Sample props for each component
$samples = [
    'badge' => [
        'text' => 'Active',
        'variant' => 'success',
        'size' => 'm',
    ],
    'button' => [
        'text' => 'Click Me',
        'url' => '#',
        'variant' => 'solid',
        'size' => 'm',
    ],
    'card' => [
        'title' => 'Sample Card',
        'description' => 'This card demonstrates the elevated variant with icon, description text, and a call-to-action link.',
        'icon' => '&#9733;',
        'variant' => 'elevated',
        'link_text' => 'View Details',
        'link_url' => '#',
    ],
    'code-block' => [
        'code' => "<?php\n\$tokens = json_decode(file_get_contents('defaults.json'), true);\n\$spacing = \$tokens['spacing'];\necho \"Base size: \" . \$spacing['baseSize'] . \"px\";",
        'language' => 'php',
        'title' => 'Token Loading Example',
        'line_numbers' => true,
    ],
    'container' => [
        'layout' => 'grid',
        'columns' => '3',
        'gap' => 'm',
        'children' => [
            ['type' => 'badge', 'props' => ['text' => 'Item 1', 'variant' => 'info']],
            ['type' => 'badge', 'props' => ['text' => 'Item 2', 'variant' => 'success']],
            ['type' => 'badge', 'props' => ['text' => 'Item 3', 'variant' => 'warning']],
        ],
    ],
    'faq' => [
        'question' => 'How does the token system work?',
        'answer' => 'Design tokens are stored as JSON and compiled into CSS custom properties. Components reference these variables instead of hard-coded values, so changing a token updates every component that uses it.',
        'initially_open' => 'true',
        'variant' => 'bordered',
    ],
    'hero' => [
        'alignment' => 'center',
        'size' => 'sm',
        'colorway' => 'primary',
        'children' => [
            [
                'type' => 'intro',
                'props' => [
                    'title' => 'Hero Component',
                    'subtitle' => 'A prominent header section for landing pages.',
                    'align' => 'center',
                    'size' => 'm',
                ],
            ],
            [
                'type' => 'button',
                'props' => [
                    'text' => 'Primary Action',
                    'url' => '#',
                    'variant' => 'solid',
                    'colorway' => 'primary',
                ],
            ],
        ],
    ],
    'intro' => [
        'eyebrow' => 'Section Eyebrow',
        'title' => 'Intro Component',
        'subtitle' => 'Used as the heading block for sections. Supports eyebrow text, title, subtitle, and configurable alignment.',
        'align' => 'center',
        'size' => 'm',
    ],
    'section' => [
        'colorway' => 'default',
        'padding_top' => 'l',
        'padding_bottom' => 'l',
        'gap' => 'm',
        'children' => [
            [
                'type' => 'intro',
                'props' => [
                    'title' => 'Section Component',
                    'subtitle' => 'The root-level wrapper. Applies a colorway and renders children with configurable spacing.',
                    'align' => 'center',
                    'size' => 's',
                ],
            ],
        ],
    ],
    'stats' => [
        'value' => '2,847',
        'label' => 'Active Users',
        'prefix' => '',
        'suffix' => '+',
        'description' => 'Growing every day',
        'variant' => 'outline',
    ],
    'table' => [
        'columns' => [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'role', 'label' => 'Role'],
            [
                'label' => 'Status',
                'component' => [
                    'name' => 'badge',
                    'props' => ['text' => '{status}', 'variant' => '{variant}'],
                ],
            ],
        ],
        'data' => [
            ['id' => 1, 'name' => 'Alice Johnson', 'role' => 'Designer', 'status' => 'Active', 'variant' => 'success'],
            ['id' => 2, 'name' => 'Bob Smith', 'role' => 'Developer', 'status' => 'Away', 'variant' => 'warning'],
            ['id' => 3, 'name' => 'Carol White', 'role' => 'Manager', 'status' => 'Offline', 'variant' => 'neutral'],
        ],
        'row_key' => 'id',
    ],
    'testimonial' => [
        'quote' => 'The component system is incredibly flexible. Each component works standalone or composes with others seamlessly.',
        'author_name' => 'Jane Smith',
        'author_role' => 'Product Designer',
        'rating' => 5,
        'show_rating' => 'true',
        'variant' => 'default',
    ],
];

// Render order: layout components first, then content, then data-display
$order = ['section', 'container', 'hero', 'intro', 'card', 'button', 'badge', 'stats', 'testimonial', 'faq', 'code-block', 'table'];

foreach ($order as $name) {
    if (!isset($components[$name])) continue;
    $comp = $components[$name];
    $label = $comp['label'];
    $props = $samples[$name] ?? [];

    anti_component('section', [
        'colorway' => 'default',
        'padding_top' => 'l',
        'padding_bottom' => 'l',
        'gap' => 'm',
        'children' => [
            [
                'type' => 'intro',
                'props' => [
                    'eyebrow' => $comp['schema']['category'] ?? 'component',
                    'title' => $label,
                    'align' => 'left',
                    'size' => 's',
                ],
            ],
            [
                'type' => $name,
                'props' => $props,
            ],
        ],
    ]);
}
