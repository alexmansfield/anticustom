<?php
/**
 * Button Component (Standalone PHP)
 *
 * Copied from anticustom-components.
 * Feel free to modify - this is YOUR code now.
 *
 * @package Anticustom
 *
 * Props:
 * @var string $text       Button text content (required)
 * @var string $url        Optional link URL - renders as <a> if provided
 * @var string $variant    Visual style: solid|outline|ghost
 * @var string $size       Button size: s|m|l
 * @var bool   $full_width Whether button should span full width
 * @var bool   $shadow     Whether to add shadow effect
 * @var string $colorway   Color scheme override
 */

// Extract props with defaults
$text       = $props['text'] ?? 'Click me';
$url        = $props['url'] ?? '';
$variant    = $props['variant'] ?? 'solid';
$size       = $props['size'] ?? 'm';
$full_width = $props['full_width'] ?? false;
$shadow     = $props['shadow'] ?? false;
$colorway   = $props['colorway'] ?? 'inherit';

// Build colorway attribute (skip if 'inherit' - let it inherit from parent)
$colorway_attr = (!empty($colorway) && $colorway !== 'inherit')
    ? ' data-colorway="' . attr_escape($colorway) . '"'
    : '';

// Build CSS classes
$classes = anti_classes([
    'anti-button'              => true,
    "anti-button--{$variant}"  => true,
    "anti-button--{$size}"     => true,
    'anti-button--full-width'  => $full_width,
    'anti-button--shadow'      => $shadow,
]);

// Render as link or button based on URL presence
if (!empty($url)) :
?>
    <a href="<?php echo url_escape($url); ?>" class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
        <?php echo html_escape($text); ?>
    </a>
<?php else : ?>
    <button type="button" class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
        <?php echo html_escape($text); ?>
    </button>
<?php endif; ?>
