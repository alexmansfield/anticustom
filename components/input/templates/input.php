<?php
/**
 * Input Component
 *
 * Text-based form field with label, helper text, and error state.
 * Handles text, email, password, number, tel, and url types.
 *
 * @package Anticustom
 *
 * Props:
 * @var string $type        Input type: text|email|password|number|tel|url
 * @var string $label       Label text
 * @var string $name        Form field name
 * @var string $placeholder Placeholder text
 * @var string $value       Current value (runtime)
 * @var string $helper_text Hint text below the input
 * @var string $error_text  Error message (runtime)
 * @var bool   $required    Whether field is required
 * @var bool   $disabled    Whether field is disabled
 * @var string $size        Input size: s|m|l
 * @var string $colorway    Color scheme override
 * @var string $class       Additional CSS classes
 */

// Extract props with defaults
$type        = $props['type'] ?? 'text';
$label       = $props['label'] ?? 'Label';
$name        = $props['name'] ?? '';
$placeholder = $props['placeholder'] ?? '';
$value       = $props['value'] ?? '';
$helper_text = $props['helper_text'] ?? '';
$error_text  = $props['error_text'] ?? '';
$required    = $props['required'] ?? false;
$disabled    = $props['disabled'] ?? false;
$size        = $props['size'] ?? 'm';
$colorway    = $props['colorway'] ?? 'inherit';
$class       = $props['class'] ?? '';

// Generate unique ID for label/input association
$id = $name ? 'input-' . $name : 'input-' . uniqid();
$has_error = !empty($error_text);

// Build colorway attribute
$colorway_attr = (!empty($colorway) && $colorway !== 'inherit')
    ? ' data-colorway="' . attr_escape($colorway) . '"'
    : '';

// Build CSS classes
$classes = anti_classes([
    'anti-input'              => true,
    "anti-input--{$size}"     => true,
    'anti-input--error'       => $has_error,
    'anti-input--disabled'    => $disabled,
    $class                    => !empty($class),
]);

// Build aria-describedby referencing helper and/or error
$described_by = [];
if (!empty($helper_text)) $described_by[] = $id . '-helper';
if ($has_error) $described_by[] = $id . '-error';
$describedby_attr = !empty($described_by) ? ' aria-describedby="' . attr_escape(implode(' ', $described_by)) . '"' : '';
?>
<div class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
    <label class="anti-input__label" for="<?php echo attr_escape($id); ?>">
        <?php echo html_escape($label); ?>
<?php if ($required) : ?>
        <span class="anti-input__required" aria-hidden="true">*</span>
<?php endif; ?>
    </label>
    <input
        class="anti-input__control"
        type="<?php echo attr_escape($type); ?>"
        id="<?php echo attr_escape($id); ?>"
        name="<?php echo attr_escape($name); ?>"
<?php if (!empty($placeholder)) : ?>
        placeholder="<?php echo attr_escape($placeholder); ?>"
<?php endif; ?>
<?php if (!empty($value)) : ?>
        value="<?php echo attr_escape($value); ?>"
<?php endif; ?>
<?php if ($required) : ?>
        aria-required="true"
<?php endif; ?>
<?php if ($has_error) : ?>
        aria-invalid="true"
<?php endif; ?>
<?php if ($disabled) : ?>
        disabled
<?php endif; ?>
<?php echo $describedby_attr; ?>
    />
<?php if (!empty($helper_text)) : ?>
    <p class="anti-input__helper" id="<?php echo attr_escape($id . '-helper'); ?>"><?php echo html_escape($helper_text); ?></p>
<?php endif; ?>
<?php if ($has_error) : ?>
    <p class="anti-input__error" id="<?php echo attr_escape($id . '-error'); ?>" role="alert"><?php echo html_escape($error_text); ?></p>
<?php endif; ?>
</div>
