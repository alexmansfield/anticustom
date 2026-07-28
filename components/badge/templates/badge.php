<?php
/**
 * Badge Component
 *
 * Inline label for status, category, or count display.
 *
 * Props:
 * @var string $text     Badge text content (required)
 * @var string $class    Additional CSS class(es)
 */

$text  = $props['text'] ?? '';
$class = $props['class'] ?? '';

if (empty($text)) {
    return;
}

$classes = anti_classes([
    'anti-badge'             => true,
    $class                   => !empty($class),
]);
?>

<span class="<?php echo attr_escape($classes); ?>"><?php echo html_escape($text); ?></span>
