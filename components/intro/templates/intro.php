<?php
/**
 * Intro Component
 *
 * Introductory text block with optional eyebrow, title, and subtitle.
 * Only renders elements that have content.
 *
 * Tag logic:
 * - Eyebrow present → eyebrow is the heading, title and subtitle are <p>
 * - Eyebrow absent  → title promotes to the heading, subtitle is <p>
 *
 * The heading element's tag is the `level` field (h1–h6, or p). `level` sets
 * the tag of whichever element is promoted to the heading, not a fixed one, so
 * it composes with eyebrow-promotion. `p` keeps the heading styling (the class
 * carries it) while leaving the document outline.
 *
 * Props:
 * @var string $eyebrow  Optional eyebrow label (renders first)
 * @var string $title     Main title text
 * @var string $subtitle  Supporting text below the title
 * @var string $level     Heading tag for the promoted element: h1–h6|p
 * @var string $align     Text alignment: inherit|left|center|right
 * @var string $size      Size variant: s|m|l
 * @var string $palette  Color scheme: inherit|default|base|primary|secondary
 */

// Extract props with defaults
$eyebrow  = $props['eyebrow'] ?? '';
$title    = $props['title'] ?? '';
$subtitle = $props['subtitle'] ?? '';
$level    = anti_heading_level($props['level'] ?? 'h2', 'h2');
$align    = $props['align'] ?? 'center';
$size     = $props['size'] ?? 'm';
$palette = $props['palette'] ?? 'inherit';

// Nothing to render if all fields are empty
if (empty($eyebrow) && empty($title) && empty($subtitle)) {
    return;
}

// Build data attributes (omit data-align for "inherit" so it inherits from parent)
$attrs = anti_attrs([
    'data-palette' => (!empty($palette) && $palette !== 'inherit') ? $palette : false,
    'data-align'    => $align !== 'inherit' ? $align : null,
    'data-size'     => $size !== 'm' ? $size : null,
]);

// The promoted heading takes $level; the demoted sibling stays <p>.
// Eyebrow, when present, is the heading; otherwise the title is.
$eyebrow_tag = $level;
$title_tag   = !empty($eyebrow) ? 'p' : $level;

$classes = anti_classes([
    'anti-intro' => true,
]);
?>

<div class="<?php echo $classes; ?>" <?php echo $attrs; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
    <?php if (!empty($eyebrow)) : ?>
        <<?php echo $eyebrow_tag; ?> class="anti-intro__eyebrow"><?php echo anti_field_html($props, 'eyebrow'); ?></<?php echo $eyebrow_tag; ?>>
    <?php endif; ?>

    <?php if (!empty($title)) : ?>
        <<?php echo $title_tag; ?> class="anti-intro__title"><?php echo anti_field_html($props, 'title'); ?></<?php echo $title_tag; ?>>
    <?php endif; ?>

    <?php if (!empty($subtitle)) : ?>
        <p class="anti-intro__subtitle"><?php echo anti_field_html($props, 'subtitle'); ?></p>
    <?php endif; ?>
</div>
