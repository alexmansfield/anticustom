<?php
/**
 * Card Component (Standalone PHP)
 *
 * Copied from anticustom-components.
 * Feel free to modify - this is YOUR code now.
 *
 * @package Anticustom
 *
 * Props:
 * @var string $title          Card title (required)
 * @var string $description    Card body text
 * @var string $image          Image URL
 * @var string $image_alt      Image alt text for accessibility
 * @var string $icon           Icon name or emoji
 * @var string $link_url       Makes card clickable
 * @var string $link_text      CTA link text
 * @var string $image_position Image placement: top|left|right
 * @var string $image_ratio    Aspect ratio: auto|16:9|4:3|square
 */

// Extract props with defaults
$title          = $props['title'] ?? 'Card Title';
$description    = $props['description'] ?? '';
$image          = $props['image'] ?? '';
$image_alt      = $props['image_alt'] ?? '';
$icon           = $props['icon'] ?? '';
$link_url       = $props['link_url'] ?? '';
$link_text      = $props['link_text'] ?? 'Learn More';
$image_position = $props['image_position'] ?? 'top';
$image_ratio    = $props['image_ratio'] ?? '16:9';

// Fix icons that were saved as unicode escape sequences
if (!empty($icon) && preg_match('/^u[0-9a-fA-F]{4,5}$/', $icon)) {
    $icon = json_decode('"\\u' . substr($icon, 1) . '"') ?: $icon;
}
// Handle surrogate pairs for emoji
if (!empty($icon) && preg_match('/^ud[89ab][0-9a-fA-F]{2}ud[c-f][0-9a-fA-F]{2}$/i', $icon)) {
    $high = substr($icon, 0, 5);
    $low = substr($icon, 5, 5);
    $icon = json_decode('"\\' . $high . '\\' . $low . '"') ?: $icon;
}

// Determine if we have visual content
$has_image = !empty($image);
$has_icon  = !empty($icon) && !$has_image;
$has_link  = !empty($link_url);

// Build CSS classes
$classes = anti_classes([
    'anti-card'                        => true,
    "anti-card--img-{$image_position}" => $has_image,
    "anti-card--ratio-{$image_ratio}"  => $has_image && $image_ratio !== 'auto',
    'anti-card--has-icon'              => $has_icon,
    'anti-card--clickable'             => $has_link,
]);

// Wrapper element based on whether it's a link
$tag = $has_link ? 'a' : 'div';
$link_attr = $has_link ? ' href="' . url_escape($link_url) . '"' : '';
?>

<<?php echo $tag; ?> class="<?php echo attr_escape($classes); ?>"<?php echo $link_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
    <?php if ($has_image) : ?>
        <div class="anti-card__image">
            <img
                src="<?php echo url_escape($image); ?>"
                alt="<?php echo attr_escape($image_alt ?: $title); ?>"
                loading="lazy"
            />
        </div>
    <?php endif; ?>

    <div class="anti-card__content">
        <?php if ($has_icon) : ?>
            <div class="anti-card__icon">
                <?php echo html_escape($icon); ?>
            </div>
        <?php endif; ?>

        <h3 class="anti-card__title">
            <?php echo html_escape($title); ?>
        </h3>

        <?php if (!empty($description)) : ?>
            <p class="anti-card__description">
                <?php echo html_escape($description); ?>
            </p>
        <?php endif; ?>

        <?php if ($has_link && !empty($link_text)) : ?>
            <span class="anti-card__link">
                <?php echo html_escape($link_text); ?>
                <svg class="anti-card__arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        <?php endif; ?>
    </div>
</<?php echo $tag; ?>>
