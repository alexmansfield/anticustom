<?php
/**
 * Stats Component
 *
 * Displays numerical metrics with labels.
 *
 * Props:
 * @var string $value       The main number/metric (required)
 * @var string $label       Description of the metric (required)
 * @var string $prefix      Text before value (e.g., $)
 * @var string $suffix      Text after value (e.g., +, %)
 * @var string $description Additional context
 * @var string $colorway    Color scheme: inherit|default|base|primary|secondary
 */

// Extract props with defaults
$value       = $props['value'] ?? '500';
$label       = $props['label'] ?? 'Happy Customers';
$prefix      = $props['prefix'] ?? '';
$suffix      = $props['suffix'] ?? '+';
$description = $props['description'] ?? '';
$colorway    = $props['colorway'] ?? 'inherit';

// Build CSS classes
$classes = anti_classes([
    'anti-stats' => true,
]);

// Build data attributes (skip if 'inherit' - let it inherit from parent)
$colorway_attr = (!empty($colorway) && $colorway !== 'inherit')
    ? ' data-colorway="' . attr_escape($colorway) . '"'
    : '';
?>

<div class="<?php echo attr_escape($classes); ?>"<?php echo !empty($editable) ? ' ' . $editable : ''; ?><?php echo $colorway_attr; ?>>
    <div class="anti-stats__value">
        <?php if (!empty($prefix)) : ?>
            <span class="anti-stats__prefix"><?php echo html_escape($prefix); ?></span>
        <?php endif; ?>
        <span class="anti-stats__number"><?php echo html_escape($value); ?></span>
        <?php if (!empty($suffix)) : ?>
            <span class="anti-stats__suffix"><?php echo html_escape($suffix); ?></span>
        <?php endif; ?>
    </div>

    <div class="anti-stats__label">
        <?php echo html_escape($label); ?>
    </div>

    <?php if (!empty($description)) : ?>
        <p class="anti-stats__description">
            <?php echo html_escape($description); ?>
        </p>
    <?php endif; ?>
</div>
