<?php
/**
 * Section Component
 *
 * Root-level wrapper that applies a colorway and renders children.
 *
 * Props:
 * @var string $colorway       Color scheme: default|base|primary|secondary
 * @var string $padding_top    Top padding: none|xxs|xs|s|m|l|xl|xxl
 * @var string $padding_bottom Bottom padding: none|xxs|xs|s|m|l|xl|xxl
 * @var string $gap            Gap between children: none|xxs|xs|s|m|l|xl|xxl
 * @var array  $children       Child components to render
 */

$colorway       = $props['colorway'] ?? 'default';
$padding_top    = $props['padding_top'] ?? 'xxl';
$padding_bottom = $props['padding_bottom'] ?? 'xxl';
$gap            = $props['gap'] ?? 'l';
$children       = $props['children'] ?? [];

$attrs = anti_attrs([
    'data-colorway'      => (!empty($colorway) && $colorway !== 'inherit') ? $colorway : false,
    'data-padding-top'   => ($padding_top !== 'none') ? $padding_top : false,
    'data-padding-bottom'=> ($padding_bottom !== 'none') ? $padding_bottom : false,
    'data-gap'           => ($gap !== 'none') ? $gap : false,
]);
?>

<section class="anti-section"<?php echo !empty($editable) ? ' ' . $editable : ''; ?> <?php echo $attrs; ?>>
    <div class="anti-section__inner">
        <?php if (!empty($children)) : ?>
            <?php render_components($children); ?>
        <?php endif; ?>
    </div>
</section>
