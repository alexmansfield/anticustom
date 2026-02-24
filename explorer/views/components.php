<?php
/**
 * Components View — Preview Area
 *
 * Injects component registry data for the panel (loaded via playground.js)
 * and renders the preview area that reads from Alpine.store('componentPreview').
 */

$components = scan_components(anti_components_dir());

// Sample props for each component (initial values when selected)
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
        'icon' => 'A',
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

// Build component registry for JS
$componentData = [];
foreach ($components as $name => $comp) {
    $schema = $comp['schema'];
    $hasChildren = isset($schema['children']);

    $componentData[$name] = [
        'label'       => $comp['label'],
        'category'    => $schema['category'] ?? 'other',
        'fields'      => $schema['fields'] ?? [],
        'hasChildren' => $hasChildren,
        'sampleProps' => $samples[$name] ?? [],
    ];
}

// Sort by display order: layout -> content -> interactive -> data-display
$categoryOrder = ['layout' => 0, 'content' => 1, 'interactive' => 2, 'data-display' => 3, 'other' => 4];
uksort($componentData, function ($a, $b) use ($componentData, $categoryOrder) {
    $catA = $categoryOrder[$componentData[$a]['category']] ?? 99;
    $catB = $categoryOrder[$componentData[$b]['category']] ?? 99;
    return $catA - $catB ?: strcmp($a, $b);
});
// Pre-render the initially selected component (read from cookie, fall back to first)
$preRenderName = $_COOKIE['antiExplorer_selectedComponent'] ?? null;
if (!$preRenderName || !isset($componentData[$preRenderName])) {
    $preRenderName = array_key_first($componentData);
}

$preRenderDefaults = get_default_props($components[$preRenderName]['schema']);
$preRenderProps = array_merge($preRenderDefaults, $samples[$preRenderName] ?? []);

ob_start();
anti_component($preRenderName, $preRenderProps);
$preRenderHtml = ob_get_clean();

$preRenderStylesDir = $components[$preRenderName]['path'] . '/styles';
$preRenderCss = '';
if (file_exists($preRenderStylesDir . '/_base.css')) {
    $preRenderCss .= file_get_contents($preRenderStylesDir . '/_base.css') . "\n";
}
if (file_exists($preRenderStylesDir . '/default.css')) {
    $preRenderCss .= file_get_contents($preRenderStylesDir . '/default.css');
}
?>

<script>
window.__antiComponents = <?php echo json_encode($componentData, JSON_UNESCAPED_UNICODE); ?>;
window.__antiInitialPreview = {
    component: <?php echo json_encode($preRenderName); ?>,
    componentName: <?php echo json_encode($componentData[$preRenderName]['label']); ?>,
    html: <?php echo json_encode($preRenderHtml); ?>,
    css: <?php echo json_encode($preRenderCss); ?>
};
</script>

<!-- Preview area: reads from Alpine.store('componentPreview') set by playground.js -->
<div class="anti-playground__preview" x-data>
    <div class="anti-playground__preview-header"
         x-show="$store.componentPreview.componentName">
        <span class="anti-playground__preview-title"
              x-text="$store.componentPreview.componentName + ' Preview'"></span>
        <div class="anti-playground__source-toggles">
            <button class="anti-playground__source-toggle"
                    :class="{ 'is-active': !$store.componentPreview.sourceView }"
                    @click="$store.componentPreview.sourceView = null">
                Preview
            </button>
            <button class="anti-playground__source-toggle"
                    :class="{ 'is-active': $store.componentPreview.sourceView === 'html' }"
                    @click="$store.componentPreview.toggleSource('html')">
                HTML
            </button>
            <button class="anti-playground__source-toggle"
                    :class="{ 'is-active': $store.componentPreview.sourceView === 'css' }"
                    @click="$store.componentPreview.toggleSource('css')">
                CSS
            </button>
        </div>
    </div>

    <template x-if="!$store.componentPreview.componentName">
        <div class="anti-playground__empty">Select a component to preview</div>
    </template>

    <template x-if="$store.componentPreview.componentName && !$store.componentPreview.sourceView">
        <div class="anti-playground__render">
            <div x-show="$store.componentPreview.loading" class="anti-playground__loading"></div>
            <div x-html="$store.componentPreview.html"></div>
        </div>
    </template>

    <template x-if="$store.componentPreview.componentName && $store.componentPreview.sourceView === 'html'">
        <pre class="anti-playground__source" x-text="$store.componentPreview.html"></pre>
    </template>

    <template x-if="$store.componentPreview.componentName && $store.componentPreview.sourceView === 'css'">
        <pre class="anti-playground__source" x-text="$store.componentPreview.css"></pre>
    </template>
</div>
