<?php
/**
 * Textarea Component
 *
 * Multi-line text input with label, helper text, and error state.
 *
 * @package Anticustom
 *
 * Props:
 * @var string $label       Label text
 * @var string $name        Form field name
 * @var string $placeholder Placeholder text
 * @var string $value       Current value (runtime)
 * @var string $helper_text Hint text below the textarea
 * @var string $error_text  Error message (runtime)
 * @var int    $rows        Number of visible rows
 * @var string $resize      Resize mode: none|vertical|horizontal|both
 * @var bool   $required    Whether field is required
 * @var bool   $disabled    Whether field is disabled
 * @var string $colorway    Color scheme override
 * @var string $class       Additional CSS classes
 */

// Extract props with defaults
$label       = $props['label'] ?? 'Label';
$name        = $props['name'] ?? '';
$placeholder = $props['placeholder'] ?? '';
$value       = $props['value'] ?? '';
$helper_text = $props['helper_text'] ?? '';
$error_text  = $props['error_text'] ?? '';
$rows        = (int) ($props['rows'] ?? 4);
$resize      = $props['resize'] ?? 'vertical';
$required    = $props['required'] ?? false;
$disabled    = $props['disabled'] ?? false;
$colorway    = $props['colorway'] ?? 'inherit';
$class       = $props['class'] ?? '';

// Generate unique ID
$id = $name ? 'textarea-' . $name : 'textarea-' . uniqid();
$has_error = !empty($error_text);

// Build colorway attribute
$colorway_attr = (!empty($colorway) && $colorway !== 'inherit')
    ? ' data-colorway="' . attr_escape($colorway) . '"'
    : '';

// Build CSS classes
$classes = anti_classes([
    'anti-textarea'              => true,
    'anti-textarea--error'       => $has_error,
    'anti-textarea--disabled'    => $disabled,
    $class                       => !empty($class),
]);

// Build aria-describedby
$described_by = [];
if (!empty($helper_text)) $described_by[] = $id . '-helper';
if ($has_error) $described_by[] = $id . '-error';
$describedby_attr = !empty($described_by) ? ' aria-describedby="' . attr_escape(implode(' ', $described_by)) . '"' : '';
?>
<div class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
    <label class="anti-textarea__label" for="<?php echo attr_escape($id); ?>">
        <?php echo html_escape($label); ?>
<?php if ($required) : ?>
        <span class="anti-textarea__required" aria-hidden="true">*</span>
<?php endif; ?>
    </label>
    <textarea
        class="anti-textarea__control"
        id="<?php echo attr_escape($id); ?>"
        name="<?php echo attr_escape($name); ?>"
        rows="<?php echo $rows; ?>"
        style="resize: <?php echo attr_escape($resize); ?>"
<?php if (!empty($placeholder)) : ?>
        placeholder="<?php echo attr_escape($placeholder); ?>"
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
    ><?php echo html_escape($value); ?></textarea>
<?php if (!empty($helper_text)) : ?>
    <p class="anti-textarea__helper" id="<?php echo attr_escape($id . '-helper'); ?>"><?php echo html_escape($helper_text); ?></p>
<?php endif; ?>
<?php if ($has_error) : ?>
    <p class="anti-textarea__error" id="<?php echo attr_escape($id . '-error'); ?>" role="alert"><?php echo html_escape($error_text); ?></p>
<?php endif; ?>
</div>
