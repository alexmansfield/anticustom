<?php
/**
 * Boolean Component
 *
 * True/false toggle with checkbox or switch display mode.
 * Label appears AFTER the control (unlike other form components).
 * Uses a hidden native checkbox with a visual indicator.
 *
 * @package Anticustom
 *
 * Props:
 * @var string $display     Display mode: checkbox|switch
 * @var string $label       Label text (shown after control)
 * @var string $name        Form field name
 * @var bool   $value       Whether checked (runtime)
 * @var string $helper_text Hint text below the control
 * @var string $error_text  Error message (runtime)
 * @var bool   $disabled    Whether field is disabled
 * @var string $colorway    Color scheme override
 * @var string $class       Additional CSS classes
 */

// Extract props with defaults
$display     = $props['display'] ?? 'checkbox';
$label       = $props['label'] ?? 'Label';
$name        = $props['name'] ?? '';
$value       = $props['value'] ?? false;
$helper_text = $props['helper_text'] ?? '';
$error_text  = $props['error_text'] ?? '';
$disabled    = $props['disabled'] ?? false;
$colorway    = $props['colorway'] ?? 'inherit';
$class       = $props['class'] ?? '';

// Generate unique ID
$id = $name ? 'boolean-' . $name : 'boolean-' . uniqid();
$has_error = !empty($error_text);

// Build colorway attribute
$colorway_attr = (!empty($colorway) && $colorway !== 'inherit')
    ? ' data-colorway="' . attr_escape($colorway) . '"'
    : '';

// Build CSS classes
$classes = anti_classes([
    'anti-boolean'                 => true,
    "anti-boolean--{$display}"     => true,
    'anti-boolean--error'          => $has_error,
    'anti-boolean--disabled'       => $disabled,
    $class                         => !empty($class),
]);

// Build aria-describedby
$described_by = [];
if (!empty($helper_text)) $described_by[] = $id . '-helper';
if ($has_error) $described_by[] = $id . '-error';
$describedby_attr = !empty($described_by) ? ' aria-describedby="' . attr_escape(implode(' ', $described_by)) . '"' : '';
?>
<div class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
    <label class="anti-boolean__field" for="<?php echo attr_escape($id); ?>">
        <input
            type="checkbox"
            class="anti-boolean__control"
            id="<?php echo attr_escape($id); ?>"
            name="<?php echo attr_escape($name); ?>"
<?php if ($value) : ?>
            checked
<?php endif; ?>
<?php if ($has_error) : ?>
            aria-invalid="true"
<?php endif; ?>
<?php if ($disabled) : ?>
            disabled
<?php endif; ?>
<?php echo $describedby_attr; ?>
        />
        <span class="anti-boolean__indicator" aria-hidden="true"></span>
        <span class="anti-boolean__label"><?php echo html_escape($label); ?></span>
    </label>
<?php if (!empty($helper_text)) : ?>
    <p class="anti-boolean__helper" id="<?php echo attr_escape($id . '-helper'); ?>"><?php echo html_escape($helper_text); ?></p>
<?php endif; ?>
<?php if ($has_error) : ?>
    <p class="anti-boolean__error" id="<?php echo attr_escape($id . '-error'); ?>" role="alert"><?php echo html_escape($error_text); ?></p>
<?php endif; ?>
</div>
