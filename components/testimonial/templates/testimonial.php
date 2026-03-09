<?php
/**
 * Testimonial Component
 *
 * Displays customer feedback with attribution, avatar, and optional rating.
 *
 * Props:
 * @var string $quote        The testimonial text (required)
 * @var string $author_name  Name of the person (required)
 * @var string $author_role  Job title or company
 * @var string $avatar       Avatar image URL
 * @var int    $rating       Star rating 1-5
 * @var string $show_rating  Whether to show stars: "true"|"false"
 * @var string $colorway     Color scheme: inherit|default|base|primary|secondary
 */

// Extract props with defaults
$quote       = $props['quote'] ?? 'This product has completely transformed how we work.';
$author_name = $props['author_name'] ?? 'Jane Smith';
$author_role = $props['author_role'] ?? '';
$avatar      = $props['avatar'] ?? '';
$rating      = (int) ($props['rating'] ?? 5);
$show_rating = ($props['show_rating'] ?? 'true') === 'true';
$colorway    = $props['colorway'] ?? 'inherit';

// Clamp rating between 1-5
$rating = max(1, min(5, $rating));

// Build CSS classes
$classes = anti_classes([
    'anti-testimonial'              => true,
    'anti-testimonial--has-avatar'  => !empty($avatar),
]);

// Build colorway attribute (skip if 'inherit' - let it inherit from parent)
$colorway_attr = (!empty($colorway) && $colorway !== 'inherit')
    ? ' data-colorway="' . attr_escape($colorway) . '"'
    : '';
?>

<div class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
    <?php if ($show_rating) : ?>
        <div class="anti-testimonial__rating" aria-label="<?php echo attr_escape($rating); ?> out of 5 stars">
            <?php for ($i = 1; $i <= 5; $i++) : ?>
                <svg class="anti-testimonial__star <?php echo $i <= $rating ? 'anti-testimonial__star--filled' : ''; ?>"
                     width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 1L12.39 6.26L18 7.27L14 11.14L14.76 17L10 14.27L5.24 17L6 11.14L2 7.27L7.61 6.26L10 1Z"
                          fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <blockquote class="anti-testimonial__quote">
        <p><?php echo html_escape($quote); ?></p>
    </blockquote>

    <div class="anti-testimonial__author">
        <?php if (!empty($avatar)) : ?>
            <img
                class="anti-testimonial__avatar"
                src="<?php echo url_escape($avatar); ?>"
                alt="<?php echo attr_escape($author_name); ?>"
                loading="lazy"
            />
        <?php else : ?>
            <div class="anti-testimonial__avatar anti-testimonial__avatar--placeholder">
                <?php echo html_escape(mb_substr($author_name, 0, 1)); ?>
            </div>
        <?php endif; ?>

        <div class="anti-testimonial__author-info">
            <cite class="anti-testimonial__name"><?php echo html_escape($author_name); ?></cite>
            <?php if (!empty($author_role)) : ?>
                <span class="anti-testimonial__role"><?php echo html_escape($author_role); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
