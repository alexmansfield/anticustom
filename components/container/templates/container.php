<?php
/**
 * Container Component
 *
 * A structural layout wrapper using CSS Grid or Flexbox.
 * Purely structural — no background, padding, or visual styling.
 * Typical composition: section > container > child components.
 *
 * Props:
 * @var string $layout         Layout mode: grid|flex
 * @var string $columns        Column count: 1|2|3|4|5|6|custom (grid only)
 * @var string $custom_columns CSS grid-template-columns value (grid custom only)
 * @var string $direction      Flex direction: row|column (flex only)
 * @var string $wrap           Flex wrap: wrap|nowrap (flex only)
 * @var string $gap            Gap size: none|xxs|xs|s|m|l|xl|xxl
 * @var string $align_items    Align items: start|center|stretch|end
 * @var string $justify        Justify content: start|center|end|between|around (flex only)
 * @var array  $children       Child components to render
 */

// Extract props with defaults
$layout         = $props['layout'] ?? 'grid';
$columns        = $props['columns'] ?? '3';
$custom_columns = $props['custom_columns'] ?? '';
$direction      = $props['direction'] ?? 'row';
$wrap           = $props['wrap'] ?? 'wrap';
$gap            = $props['gap'] ?? 'm';
$align_items    = $props['align_items'] ?? 'stretch';
$justify        = $props['justify'] ?? 'start';
$children       = $props['children'] ?? [];

// Build data attributes
$classes = 'anti-container';
$attrs = anti_attrs([
    'data-layout'    => $layout,
    'data-columns'   => ($layout === 'grid' && $columns !== 'custom') ? $columns : false,
    'data-direction' => ($layout === 'flex') ? $direction : false,
    'data-wrap'      => ($layout === 'flex') ? $wrap : false,
    'data-gap'       => ($gap !== 'none') ? $gap : false,
    'data-align'     => $align_items,
    'data-justify'   => ($layout === 'flex') ? $justify : false,
]);

// Build inline style for custom grid columns
$inline_style = '';
if ($layout === 'grid' && $columns === 'custom' && !empty($custom_columns)) {
    $inline_style = ' style="grid-template-columns: ' . attr_escape($custom_columns) . ';"';
}
?>

<div class="<?php echo $classes; ?>" <?php echo $attrs; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?><?php echo $inline_style; ?>>
    <?php if (!empty($children)) : ?>
        <?php render_components($children); ?>
    <?php endif; ?>
</div>
