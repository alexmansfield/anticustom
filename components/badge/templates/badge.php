<?php
/**
 * Badge Component
 *
 * Inline label for status, category, or count display.
 *
 * Props:
 * @var string $text     Badge text content (required)
 * @var string $variant  Semantic colorway: default|info|success|warning|danger
 * @var string $class    Additional CSS class(es)
 */

$text    = $props['text'] ?? '';
$variant = $props['variant'] ?? 'default';
$class   = $props['class'] ?? '';

if (empty($text)) {
    return;
}

$classes = anti_classes([
    'anti-badge'             => true,
    $class                   => !empty($class),
]);

// Variants map to the auto-generated semantic colorways
$colorway_attr = $variant !== 'default'
    ? ' data-colorway="' . attr_escape($variant) . '"'
    : '';
?>

<span class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?>><?php echo html_escape($text); ?></span>
