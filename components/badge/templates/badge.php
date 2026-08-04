<?php
/**
 * Badge Component
 *
 * Inline label for status, category, or count display.
 *
 * Props:
 * @var string $text     Badge text content (required)
 * @var string $variant  Semantic intent: default|info|success|warning|danger
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

// Variants bind to palette intents (ADR 0016): data-intent picks up the
// surrounding palette's --intent/--intent-on tokens. Default carries no intent
// and falls back to the neutral surface/hard-contrast pair in CSS.
$intent_attr = $variant !== 'default'
    ? ' data-intent="' . attr_escape($variant) . '"'
    : '';
?>

<span class="<?php echo attr_escape($classes); ?>"<?php echo $intent_attr; ?>><?php echo html_escape($text); ?></span>
