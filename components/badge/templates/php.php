<?php
/**
 * Badge Component
 *
 * Inline label for status, category, or count display.
 *
 * Props:
 * @var string $text     Badge text content (required)
 * @var string $variant  Semantic variant: neutral|success|warning|danger|info
 * @var string $size     Badge size: s|m
 * @var string $class    Additional CSS class(es)
 */

$text    = $props['text'] ?? '';
$variant = $props['variant'] ?? 'neutral';
$size    = $props['size'] ?? 'm';
$class   = $props['class'] ?? '';

if (empty($text)) {
    return;
}

$classes = anti_classes([
    'anti-badge'                => true,
    "anti-badge--{$variant}"    => true,
    "anti-badge--{$size}"       => true,
    $class                      => !empty($class),
]);
?>

<span class="<?php echo attr_escape($classes); ?>"><?php echo html_escape($text); ?></span>
