<?php
/**
 * Hero Component
 *
 * A prominent header section for landing pages with an intro child
 * for headline text and call-to-action buttons.
 *
 * Props:
 * @var string $alignment          Text alignment: left|center|right
 * @var string $size               Hero size: sm|md|lg
 * @var string $background_image   Optional background image URL
 * @var string $colorway           Color scheme
 * @var array  $children           Child components (intro + buttons)
 */

// Extract props with defaults
$alignment        = $props['alignment'] ?? 'center';
$size             = $props['size'] ?? 'lg';
$background_image = $props['background_image'] ?? '';
$colorway         = $props['colorway'] ?? 'default';

// Get children (intro + buttons)
$children = $props['children'] ?? [];
$hero_schema = anti_get_schema('hero');

// Find intro and button children by type
$intro_props = null;
$intro_index = null;
$primary_button = null;
$primary_index = null;
$secondary_button = null;
$secondary_index = null;

foreach ($children as $index => $child) {
    $type = $child['type'] ?? '';
    $child_props = resolve_child_props($hero_schema, $index, $child['props'] ?? []);

    if ($type === 'intro' && $intro_props === null) {
        $intro_props = $child_props;
        $intro_index = $index;
    } elseif ($type === 'button') {
        if (empty($child_props['text'])) continue;

        if ($primary_button === null) {
            $primary_button = $child_props;
            $primary_index = $index;
        } elseif ($secondary_button === null) {
            $secondary_button = $child_props;
            $secondary_index = $index;
        }
    }
}

// Build CSS classes
$classes = anti_classes([
    'anti-hero'                    => true,
    "anti-hero--{$alignment}"      => true,
    "anti-hero--{$size}"           => true,
    'anti-hero--has-bg'            => !empty($background_image),
]);

// Build data attributes (skip if 'inherit' - let it inherit from parent)
$data_attrs = '';
if (!empty($colorway) && $colorway !== 'inherit') {
    $data_attrs = 'data-colorway="' . attr_escape($colorway) . '"';
}

// Background style
$bg_style = !empty($background_image)
    ? 'background-image: url(' . url_escape($background_image) . ');'
    : '';
?>

<section class="<?php echo attr_escape($classes); ?>"<?php echo !empty($editable) ? ' ' . $editable : ''; ?> <?php echo $data_attrs; ?> <?php echo $bg_style ? 'style="' . attr_escape($bg_style) . '"' : ''; ?>>
    <div class="anti-hero__container">
        <div class="anti-hero__content">
            <?php if ($intro_props !== null) : ?>
                <?php anti_component('intro', array_merge($intro_props, [
                    '_child_index' => $intro_index,
                ])); ?>
            <?php endif; ?>

            <?php if ($primary_button || $secondary_button) : ?>
                <div class="anti-hero__actions">
                    <?php if ($primary_button) : ?>
                        <?php anti_component('button', array_merge($primary_button, [
                            'size'         => $size === 'sm' ? 'm' : 'l',
                            'shadow'       => true,
                            '_child_index' => $primary_index,
                        ])); ?>
                    <?php endif; ?>

                    <?php if ($secondary_button) : ?>
                        <?php anti_component('button', array_merge($secondary_button, [
                            'size'         => $size === 'sm' ? 'm' : 'l',
                            '_child_index' => $secondary_index,
                        ])); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
