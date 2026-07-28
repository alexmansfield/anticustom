<?php
/**
 * Hero Component
 *
 * A prominent header section for landing pages. The headline, subtitle,
 * and call-to-action fields live directly on the hero's own schema and
 * render straight from $props — hero no longer composes intro/button
 * children through slots (flattened; may be re-composited if fixed slots
 * land in a form that fits).
 *
 * Tag logic (mirrors intro):
 * - Eyebrow present → eyebrow is <h2>, title is <p>
 * - Eyebrow absent  → title promotes to <h2>
 *
 * Props:
 * @var string $eyebrow               Optional eyebrow label above the title
 * @var string $title                 Main headline
 * @var string $subtitle              Supporting text below the title
 * @var string $primary_cta_text      Primary call-to-action label (empty hides it)
 * @var string $primary_cta_url       Primary call-to-action URL
 * @var string $primary_cta_variant   Primary button style: solid|outline|ghost
 * @var string $secondary_cta_text    Secondary call-to-action label (empty hides it)
 * @var string $secondary_cta_url     Secondary call-to-action URL
 * @var string $secondary_cta_variant Secondary button style: solid|outline|ghost
 * @var string $alignment             Text alignment: left|center|right
 * @var string $size                  Hero size: sm|md|lg
 * @var string $background_image      Optional background image URL
 * @var string $colorway              Color scheme
 */

// Extract props with defaults
$eyebrow               = $props['eyebrow'] ?? '';
$title                 = $props['title'] ?? '';
$subtitle              = $props['subtitle'] ?? '';
$primary_cta_text      = $props['primary_cta_text'] ?? '';
$primary_cta_url       = $props['primary_cta_url'] ?? '';
$primary_cta_variant   = $props['primary_cta_variant'] ?? 'solid';
$secondary_cta_text    = $props['secondary_cta_text'] ?? '';
$secondary_cta_url     = $props['secondary_cta_url'] ?? '';
$secondary_cta_variant = $props['secondary_cta_variant'] ?? 'outline';
$alignment             = $props['alignment'] ?? 'center';
$size                  = $props['size'] ?? 'lg';
$background_image      = $props['background_image'] ?? '';
$colorway              = $props['colorway'] ?? 'default';

// Determine title tag: h2 if eyebrow is absent, p if eyebrow is present
$title_tag = !empty($eyebrow) ? 'p' : 'h2';

// Buttons match the hero scale: small heroes take medium buttons
$cta_size = $size === 'sm' ? 'm' : 'l';

// Build CSS classes
$classes = anti_classes([
    'anti-hero'                    => true,
    "anti-hero--{$alignment}"      => true,
    "anti-hero--{$size}"           => $size !== 'lg',
]);

$primary_classes = anti_classes([
    'anti-button'                          => true,
    "anti-button--{$primary_cta_variant}"  => true,
    "anti-button--{$cta_size}"             => $cta_size !== 'm',
    'anti-button--shadow'                  => true,
]);

$secondary_classes = anti_classes([
    'anti-button'                           => true,
    "anti-button--{$secondary_cta_variant}" => true,
    "anti-button--{$cta_size}"              => $cta_size !== 'm',
]);

// Build data attributes (skip if 'inherit' - let it inherit from parent)
$attrs = anti_attrs([
    'data-colorway' => (!empty($colorway) && $colorway !== 'inherit') ? $colorway : false,
]);
?>

<section class="<?php echo attr_escape($classes); ?>"<?php echo !empty($editable) ? ' ' . $editable : ''; ?> <?php echo $attrs; ?><?php if (!empty($background_image)) : ?> style="background-image: url(<?php echo url_escape($background_image); ?>);"<?php endif; ?>>
    <div class="anti-hero__container">
        <div class="anti-hero__content">
            <?php if (!empty($eyebrow) || !empty($title) || !empty($subtitle)) : ?>
                <div class="anti-intro" data-size="l">
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
            <?php endif; ?>

            <?php if (!empty($primary_cta_text) || !empty($secondary_cta_text)) : ?>
                <div class="anti-hero__actions">
                    <?php if (!empty($primary_cta_text)) : ?>
                        <?php if (!empty($primary_cta_url)) : ?>
                            <a href="<?php echo url_escape($primary_cta_url); ?>" class="<?php echo attr_escape($primary_classes); ?>" data-colorway="primary">
                                <?php echo html_escape($primary_cta_text); ?>
                            </a>
                        <?php else : ?>
                            <button type="button" class="<?php echo attr_escape($primary_classes); ?>" data-colorway="primary">
                                <?php echo html_escape($primary_cta_text); ?>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($secondary_cta_text)) : ?>
                        <?php if (!empty($secondary_cta_url)) : ?>
                            <a href="<?php echo url_escape($secondary_cta_url); ?>" class="<?php echo attr_escape($secondary_classes); ?>" data-colorway="primary">
                                <?php echo html_escape($secondary_cta_text); ?>
                            </a>
                        <?php else : ?>
                            <button type="button" class="<?php echo attr_escape($secondary_classes); ?>" data-colorway="primary">
                                <?php echo html_escape($secondary_cta_text); ?>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
