<?php
/**
 * Components View — Interactive Playground
 *
 * Schema-driven prop editor with live AJAX preview.
 * Replaces the static gallery with an interactive component workbench.
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

// Sort by display order: layout → content → interactive → data-display
$categoryOrder = ['layout' => 0, 'content' => 1, 'interactive' => 2, 'data-display' => 3, 'other' => 4];
uksort($componentData, function ($a, $b) use ($componentData, $categoryOrder) {
    $catA = $categoryOrder[$componentData[$a]['category']] ?? 99;
    $catB = $categoryOrder[$componentData[$b]['category']] ?? 99;
    return $catA - $catB ?: strcmp($a, $b);
});
?>

<script>
window.__antiComponents = <?php echo json_encode($componentData, JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="anti-playground" x-data="antiPlayground()">
    <!-- Sidebar: search, list, editor -->
    <div class="anti-playground__sidebar">
        <div class="anti-playground__search">
            <input type="text"
                   x-model="search"
                   placeholder="Search components...">
        </div>

        <div class="anti-playground__sidebar-scroll">
            <!-- Component list -->
            <div class="anti-playground__list">
                <template x-for="(comps, category) in filteredComponents()" :key="category">
                    <div>
                        <div class="anti-playground__category" x-text="category"></div>
                        <template x-for="comp in comps" :key="comp.name">
                            <button class="anti-playground__item"
                                    :class="{ 'is-active': selected === comp.name }"
                                    @click="selectComponent(comp.name)"
                                    x-text="comp.label">
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Prop editor -->
            <div class="anti-playground__editor" x-show="selected" x-cloak>
                <div class="anti-playground__editor-title">Properties</div>

                <template x-for="field in currentFields()" :key="field.name">
                    <div class="anti-playground__field">

                        <!-- Text input -->
                        <template x-if="field.type === 'text' || field.type === 'url'">
                            <div>
                                <label class="anti-playground__label" x-text="field.label"></label>
                                <input class="anti-playground__input"
                                       :type="field.type === 'url' ? 'url' : 'text'"
                                       x-model="props[field.name]"
                                       @input="scheduleRender()"
                                       :placeholder="field.description || ''">
                            </div>
                        </template>

                        <!-- Image URL -->
                        <template x-if="field.type === 'image'">
                            <div>
                                <label class="anti-playground__label" x-text="field.label + ' (URL)'"></label>
                                <input class="anti-playground__input"
                                       type="url"
                                       x-model="props[field.name]"
                                       @input="scheduleRender()"
                                       placeholder="Image URL">
                            </div>
                        </template>

                        <!-- Textarea -->
                        <template x-if="field.type === 'textarea'">
                            <div>
                                <label class="anti-playground__label" x-text="field.label"></label>
                                <textarea class="anti-playground__textarea"
                                          x-model="props[field.name]"
                                          @input="scheduleRender()"
                                          :placeholder="field.description || ''"></textarea>
                            </div>
                        </template>

                        <!-- Number -->
                        <template x-if="field.type === 'number'">
                            <div>
                                <label class="anti-playground__label" x-text="field.label"></label>
                                <input class="anti-playground__input"
                                       type="number"
                                       x-model="props[field.name]"
                                       @input="scheduleRender()">
                            </div>
                        </template>

                        <!-- Select -->
                        <template x-if="field.type === 'select'">
                            <div>
                                <label class="anti-playground__label" x-text="field.label"></label>
                                <select class="anti-playground__select"
                                        x-model="props[field.name]"
                                        @change="scheduleRender()">
                                    <template x-for="opt in field.options" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label"></option>
                                    </template>
                                </select>
                            </div>
                        </template>

                        <!-- Boolean -->
                        <template x-if="field.type === 'boolean'">
                            <label class="anti-playground__checkbox">
                                <input type="checkbox"
                                       x-model="props[field.name]"
                                       @change="scheduleRender()">
                                <span x-text="field.label"></span>
                            </label>
                        </template>

                        <!-- Colorway -->
                        <template x-if="field.type === 'colorway'">
                            <div>
                                <label class="anti-playground__label" x-text="field.label"></label>
                                <select class="anti-playground__select"
                                        x-model="props[field.name]"
                                        @change="scheduleRender()">
                                    <template x-for="opt in colorwayOptions" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label"></option>
                                    </template>
                                </select>
                            </div>
                        </template>

                        <!-- Buttongroup (spaces) -->
                        <template x-if="field.type === 'buttongroup'">
                            <div>
                                <label class="anti-playground__label" x-text="field.label"></label>
                                <select class="anti-playground__select"
                                        x-model="props[field.name]"
                                        @change="scheduleRender()">
                                    <template x-for="opt in spaceOptions" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label"></option>
                                    </template>
                                </select>
                            </div>
                        </template>

                    </div>
                </template>

                <!-- Children JSON editor -->
                <template x-if="hasChildren()">
                    <div class="anti-playground__field">
                        <label class="anti-playground__label">Children (JSON)</label>
                        <textarea class="anti-playground__textarea anti-playground__textarea--json"
                                  x-model="childrenJson"
                                  @input="scheduleRender()"></textarea>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Preview area -->
    <div class="anti-playground__preview">
        <div class="anti-playground__preview-header" x-show="selected">
            <span class="anti-playground__preview-title" x-text="selected ? components[selected]?.label + ' Preview' : ''"></span>
            <button class="anti-playground__source-toggle"
                    :class="{ 'is-active': showSource }"
                    @click="showSource = !showSource">
                Source
            </button>
        </div>

        <template x-if="!selected">
            <div class="anti-playground__empty">Select a component to preview</div>
        </template>

        <template x-if="selected && !showSource">
            <div class="anti-playground__render">
                <div x-show="loading" class="anti-playground__loading"></div>
                <div x-html="previewHtml"></div>
            </div>
        </template>

        <template x-if="selected && showSource">
            <pre class="anti-playground__source" x-text="previewHtml"></pre>
        </template>
    </div>
</div>
