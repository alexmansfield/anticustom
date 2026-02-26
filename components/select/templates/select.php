<?php
/**
 * Select Component
 *
 * Pick-from-options field with three display modes:
 * dropdown (<select>), radio group, and checkbox group.
 *
 * @package Anticustom
 *
 * Props:
 * @var string $display     Display mode: dropdown|radio|checkbox
 * @var string $label       Label/legend text
 * @var string $name        Form field name
 * @var array  $options     Array of { value, label } objects
 * @var string $value       Selected value (runtime)
 * @var string $placeholder Placeholder for dropdown mode
 * @var string $helper_text Hint text below the field
 * @var string $error_text  Error message (runtime)
 * @var bool   $required    Whether field is required
 * @var bool   $disabled    Whether field is disabled
 * @var string $size        Size: s|m|l
 * @var string $colorway    Color scheme override
 * @var string $class       Additional CSS classes
 */

// Extract props with defaults
$display     = $props['display'] ?? 'dropdown';
$label       = $props['label'] ?? 'Label';
$name        = $props['name'] ?? '';
$options     = $props['options'] ?? [];
$value       = $props['value'] ?? '';
$placeholder = $props['placeholder'] ?? '';
$helper_text = $props['helper_text'] ?? '';
$error_text  = $props['error_text'] ?? '';
$required    = $props['required'] ?? false;
$disabled    = $props['disabled'] ?? false;
$size        = $props['size'] ?? 'm';
$colorway    = $props['colorway'] ?? 'inherit';
$class       = $props['class'] ?? '';

// Generate unique ID
$id = $name ? 'select-' . $name : 'select-' . uniqid();
$has_error = !empty($error_text);

// Build colorway attribute
$colorway_attr = (!empty($colorway) && $colorway !== 'inherit')
    ? ' data-colorway="' . attr_escape($colorway) . '"'
    : '';

// Build CSS classes
$classes = anti_classes([
    'anti-select'                 => true,
    "anti-select--{$display}"     => true,
    "anti-select--{$size}"        => true,
    'anti-select--error'          => $has_error,
    'anti-select--disabled'       => $disabled,
    $class                        => !empty($class),
]);

// Build aria-describedby
$described_by = [];
if (!empty($helper_text)) $described_by[] = $id . '-helper';
if ($has_error) $described_by[] = $id . '-error';
$describedby_attr = !empty($described_by) ? ' aria-describedby="' . attr_escape(implode(' ', $described_by)) . '"' : '';

// Ensure options is an array (may come as JSON string from explorer)
if (is_string($options)) {
    $options = json_decode($options, true) ?? [];
}
?>
<?php if ($display === 'dropdown') : ?>
<div class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?>>
    <label class="anti-select__label" for="<?php echo attr_escape($id); ?>">
        <?php echo html_escape($label); ?>
<?php if ($required) : ?>
        <span class="anti-select__required" aria-hidden="true">*</span>
<?php endif; ?>
    </label>
    <select
        class="anti-select__control"
        id="<?php echo attr_escape($id); ?>"
        name="<?php echo attr_escape($name); ?>"
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
    >
<?php if (!empty($placeholder)) : ?>
        <option value="" disabled <?php echo empty($value) ? 'selected' : ''; ?>><?php echo html_escape($placeholder); ?></option>
<?php endif; ?>
<?php foreach ($options as $option) : ?>
        <option value="<?php echo attr_escape($option['value'] ?? ''); ?>"<?php echo ($value === ($option['value'] ?? '')) ? ' selected' : ''; ?>><?php echo html_escape($option['label'] ?? $option['value'] ?? ''); ?></option>
<?php endforeach; ?>
    </select>
<?php if (!empty($helper_text)) : ?>
    <p class="anti-select__helper" id="<?php echo attr_escape($id . '-helper'); ?>"><?php echo html_escape($helper_text); ?></p>
<?php endif; ?>
<?php if ($has_error) : ?>
    <p class="anti-select__error" id="<?php echo attr_escape($id . '-error'); ?>" role="alert"><?php echo html_escape($error_text); ?></p>
<?php endif; ?>
</div>
<?php elseif ($display === 'button-group') : ?>
<fieldset class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?><?php echo $describedby_attr; ?><?php if ($has_error) : ?> aria-invalid="true"<?php endif; ?>>
    <legend class="anti-select__legend">
        <?php echo html_escape($label); ?>
<?php if ($required) : ?>
        <span class="anti-select__required" aria-hidden="true">*</span>
<?php endif; ?>
    </legend>
    <input type="hidden" name="<?php echo attr_escape($name); ?>" value="<?php echo attr_escape($value); ?>">
    <div class="anti-select__btngroup" role="group" aria-label="<?php echo attr_escape($label); ?>">
<?php foreach ($options as $i => $option) :
    $option_value = $option['value'] ?? '';
    $is_active = ($value === $option_value);
    $btn_classes = anti_classes([
        'anti-select__btn' => true,
        'is-active'        => $is_active,
    ]);
?>
        <button type="button"
                class="<?php echo attr_escape($btn_classes); ?>"
                data-value="<?php echo attr_escape($option_value); ?>"
<?php if ($is_active) : ?>
                aria-pressed="true"
<?php else : ?>
                aria-pressed="false"
<?php endif; ?>
<?php if ($disabled) : ?>
                disabled
<?php endif; ?>
        ><?php echo html_escape($option['label'] ?? $option_value); ?></button>
<?php endforeach; ?>
    </div>
<?php if (!empty($helper_text)) : ?>
    <p class="anti-select__helper" id="<?php echo attr_escape($id . '-helper'); ?>"><?php echo html_escape($helper_text); ?></p>
<?php endif; ?>
<?php if ($has_error) : ?>
    <p class="anti-select__error" id="<?php echo attr_escape($id . '-error'); ?>" role="alert"><?php echo html_escape($error_text); ?></p>
<?php endif; ?>
</fieldset>
<?php else : ?>
<?php
    // Radio or Checkbox group — use fieldset/legend
    $input_type = ($display === 'radio') ? 'radio' : 'checkbox';
    $input_name = ($display === 'checkbox') ? $name . '[]' : $name;
?>
<fieldset class="<?php echo attr_escape($classes); ?>"<?php echo $colorway_attr; ?><?php echo !empty($editable) ? ' ' . $editable : ''; ?><?php echo $describedby_attr; ?><?php if ($has_error) : ?> aria-invalid="true"<?php endif; ?>>
    <legend class="anti-select__legend">
        <?php echo html_escape($label); ?>
<?php if ($required) : ?>
        <span class="anti-select__required" aria-hidden="true">*</span>
<?php endif; ?>
    </legend>
    <div class="anti-select__options">
<?php foreach ($options as $i => $option) :
    $option_id = $id . '-' . $i;
    $option_value = $option['value'] ?? '';
    $is_checked = ($display === 'checkbox')
        ? (is_array($value) ? in_array($option_value, $value) : $value === $option_value)
        : ($value === $option_value);
?>
        <label class="anti-select__option" for="<?php echo attr_escape($option_id); ?>">
            <input
                type="<?php echo $input_type; ?>"
                class="anti-select__input"
                id="<?php echo attr_escape($option_id); ?>"
                name="<?php echo attr_escape($input_name); ?>"
                value="<?php echo attr_escape($option_value); ?>"
<?php if ($is_checked) : ?>
                checked
<?php endif; ?>
<?php if ($disabled) : ?>
                disabled
<?php endif; ?>
            />
            <span class="anti-select__option-label"><?php echo html_escape($option['label'] ?? $option_value); ?></span>
        </label>
<?php endforeach; ?>
    </div>
<?php if (!empty($helper_text)) : ?>
    <p class="anti-select__helper" id="<?php echo attr_escape($id . '-helper'); ?>"><?php echo html_escape($helper_text); ?></p>
<?php endif; ?>
<?php if ($has_error) : ?>
    <p class="anti-select__error" id="<?php echo attr_escape($id . '-error'); ?>" role="alert"><?php echo html_escape($error_text); ?></p>
<?php endif; ?>
</fieldset>
<?php endif; ?>
