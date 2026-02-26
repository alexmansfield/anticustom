<?php
/**
 * Intro Component
 *
 * Introductory text block with optional eyebrow, title, and subtitle.
 * Only renders elements that have content.
 *
 * Tag logic:
 * - Eyebrow present → eyebrow is <h2>, title and subtitle are <p>
 * - Eyebrow absent  → title promotes to <h2>, subtitle is <p>
 *
 * Props:
 * @var string $eyebrow  Optional eyebrow label (renders first)
 * @var string $title     Main title text
 * @var string $subtitle  Supporting text below the title
 * @var string $align     Text alignment: inherit|left|center|right
 * @var string $size      Size variant: s|m|l
 */

// Extract props with defaults
$eyebrow  = $props['eyebrow'] ?? '';
$title    = $props['title'] ?? '';
$subtitle = $props['subtitle'] ?? '';
$align    = $props['align'] ?? 'center';
$size     = $props['size'] ?? 'm';

// Nothing to render if all fields are empty
if (empty($eyebrow) && empty($title) && empty($subtitle)) {
    return;
}

// Build data attributes (omit data-align for "inherit" so it inherits from parent)
$attrs = anti_attrs([
    'data-align' => $align !== 'inherit' ? $align : null,
    'data-size'  => $size,
]);

// Interface styles (padding, border, shadow)
$interfaceCss = anti_interface_css($props);

// Determine title tag: h2 if eyebrow is absent, p if eyebrow is present
$title_tag = !empty($eyebrow) ? 'p' : 'h2';
?>

<div class="anti-intro" <?php echo $attrs; ?><?php echo $interfaceCss !== '' ? ' style="' . attr_escape($interfaceCss) . '"' : ''; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
    <?php if (!empty($eyebrow)) : ?>
        <h2 class="anti-intro__eyebrow"><?php echo html_escape($eyebrow); ?></h2>
    <?php endif; ?>

    <?php if (!empty($title)) : ?>
        <<?php echo $title_tag; ?> class="anti-intro__title"><?php echo html_escape($title); ?></<?php echo $title_tag; ?>>
    <?php endif; ?>

    <?php if (!empty($subtitle)) : ?>
        <p class="anti-intro__subtitle"><?php echo html_escape($subtitle); ?></p>
    <?php endif; ?>
</div>
