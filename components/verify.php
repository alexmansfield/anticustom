<?php
/**
 * Component Verification Script
 *
 * Renders every component with default/test props and checks for errors.
 * Run: php components/verify.php
 */

require_once __DIR__ . '/render.php';
anti_components_dir(__DIR__);

$passed = 0;
$failed = 0;
$errors = [];

/**
 * Verify a component renders correctly.
 */
function verify(string $name, array $props, string $expected_class): string
{
    ob_start();
    $error = null;

    set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$error) {
        $error = "{$errstr} in {$errfile}:{$errline}";
    });

    try {
        anti_component($name, $props);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    restore_error_handler();
    $output = ob_get_clean();

    if ($error) {
        return "ERROR: {$error}";
    }

    if (empty(trim($output))) {
        return "ERROR: Empty output";
    }

    if (strpos($output, $expected_class) === false) {
        return "ERROR: Expected class '{$expected_class}' not found in output";
    }

    return 'OK';
}

// ─────────────────────────────────────────────────
// Test: Button
// ─────────────────────────────────────────────────
$result = verify('button', ['text' => 'Test Button', 'url' => '#'], 'anti-button');
echo "button .......... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "button: {$result}"; }

// ─────────────────────────────────────────────────
// Test: Intro
// ─────────────────────────────────────────────────
$result = verify('intro', ['title' => 'Hello World', 'size' => 'm'], 'anti-intro');
echo "intro ........... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "intro: {$result}"; }

// ─────────────────────────────────────────────────
// Test: Badge (renders with base class)
// ─────────────────────────────────────────────────
$result = verify('badge', ['text' => 'Active', 'variant' => 'success'], 'anti-badge');
echo "badge ........... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "badge: {$result}"; }

// ─────────────────────────────────────────────────
// Test: Hero (with children)
// ─────────────────────────────────────────────────
$result = verify('hero', [
    'alignment' => 'center',
    'size' => 'lg',
    'children' => [
        ['type' => 'intro', 'props' => ['title' => 'Hero Title', 'size' => 'l']],
        ['type' => 'button', 'props' => ['text' => 'Get Started', 'url' => '#']],
    ],
], 'anti-hero');
echo "hero ............ {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "hero: {$result}"; }

// Verify hero composition — children should render inside
ob_start();
anti_component('hero', [
    'children' => [
        ['type' => 'intro', 'props' => ['title' => 'Composition Test']],
        ['type' => 'button', 'props' => ['text' => 'CTA Button', 'url' => '#']],
    ],
]);
$hero_html = ob_get_clean();

$composition_ok = strpos($hero_html, 'anti-intro') !== false && strpos($hero_html, 'anti-button') !== false;
$comp_result = $composition_ok ? 'OK' : 'ERROR: Child components not found in hero output';
echo "hero/compose .... {$comp_result}\n";
if ($composition_ok) $passed++; else { $failed++; $errors[] = "hero/compose: {$comp_result}"; }

// ─────────────────────────────────────────────────
// Test: Table (with data and badge delegation)
// ─────────────────────────────────────────────────
$result = verify('table', [
    'columns' => [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'email', 'label' => 'Email'],
        [
            'label' => 'Status',
            'component' => [
                'name' => 'badge',
                'props' => ['text' => '{status}', 'variant' => '{variant}'],
            ],
        ],
    ],
    'data' => [
        ['id' => 1, 'name' => 'Alice', 'email' => 'alice@test.com', 'status' => 'Active', 'variant' => 'success'],
        ['id' => 2, 'name' => 'Bob', 'email' => 'bob@test.com', 'status' => 'Inactive', 'variant' => 'neutral'],
    ],
], 'anti-table');
echo "table ........... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "table: {$result}"; }

// Verify table delegation — badge should render inside table cells
ob_start();
anti_component('table', [
    'columns' => [
        ['label' => 'Status', 'component' => ['name' => 'badge', 'props' => ['text' => '{status}']]],
    ],
    'data' => [
        ['id' => 1, 'status' => 'Active'],
    ],
]);
$table_html = ob_get_clean();

$delegation_ok = strpos($table_html, 'anti-badge') !== false;
$del_result = $delegation_ok ? 'OK' : 'ERROR: Badge not found in table cell output';
echo "table/delegate .. {$del_result}\n";
if ($delegation_ok) $passed++; else { $failed++; $errors[] = "table/delegate: {$del_result}"; }

// Test empty state
$result = verify('table', [
    'columns' => [['key' => 'name', 'label' => 'Name']],
    'data' => [],
    'empty_title' => 'Nothing here',
], 'anti-table__empty');
echo "table/empty ..... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "table/empty: {$result}"; }

// ─────────────────────────────────────────────────
// Test: Code Block
// ─────────────────────────────────────────────────
$result = verify('code-block', [
    'code' => '<?php echo "Hello"; ?>',
    'language' => 'php',
    'title' => 'Example',
], 'anti-code-block');
echo "code-block ...... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "code-block: {$result}"; }

// Verify code is HTML-escaped
ob_start();
anti_component('code-block', ['code' => '<script>alert("xss")</script>']);
$code_html = ob_get_clean();

$escaped_ok = strpos($code_html, '<script>') === false && strpos($code_html, '&lt;script&gt;') !== false;
$esc_result = $escaped_ok ? 'OK' : 'ERROR: Code content not properly escaped';
echo "code-block/esc .. {$esc_result}\n";
if ($escaped_ok) $passed++; else { $failed++; $errors[] = "code-block/esc: {$esc_result}"; }

// Test line numbers
$result = verify('code-block', [
    'code' => "line 1\nline 2\nline 3",
    'line_numbers' => true,
], 'anti-code-block--has-line-numbers');
echo "code-block/lines  {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "code-block/lines: {$result}"; }

// ─────────────────────────────────────────────────
// Test: Input
// ─────────────────────────────────────────────────
$result = verify('input', ['label' => 'Full Name', 'name' => 'full_name', 'type' => 'text'], 'anti-input');
echo "input ........... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "input: {$result}"; }

// Test error state
$result = verify('input', ['label' => 'Email', 'name' => 'email', 'error_text' => 'Invalid email'], 'anti-input--error');
echo "input/error ..... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "input/error: {$result}"; }

// Test required
ob_start();
anti_component('input', ['label' => 'Required Field', 'name' => 'req', 'required' => true]);
$input_html = ob_get_clean();
$req_ok = strpos($input_html, 'aria-required="true"') !== false && strpos($input_html, 'anti-input__required') !== false;
$req_result = $req_ok ? 'OK' : 'ERROR: Required attributes not found';
echo "input/required .. {$req_result}\n";
if ($req_ok) $passed++; else { $failed++; $errors[] = "input/required: {$req_result}"; }

// ─────────────────────────────────────────────────
// Test: Textarea
// ─────────────────────────────────────────────────
$result = verify('textarea', ['label' => 'Message', 'name' => 'message', 'rows' => 4], 'anti-textarea');
echo "textarea ........ {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "textarea: {$result}"; }

$result = verify('textarea', ['label' => 'Bio', 'name' => 'bio', 'error_text' => 'Too short'], 'anti-textarea--error');
echo "textarea/error .. {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "textarea/error: {$result}"; }

// ─────────────────────────────────────────────────
// Test: Select (all display modes)
// ─────────────────────────────────────────────────
$select_options = [
    ['value' => 'a', 'label' => 'Option A'],
    ['value' => 'b', 'label' => 'Option B'],
    ['value' => 'c', 'label' => 'Option C'],
];

$result = verify('select', ['display' => 'dropdown', 'label' => 'Pick one', 'name' => 'choice', 'options' => $select_options], 'anti-select--dropdown');
echo "select/dropdown . {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "select/dropdown: {$result}"; }

$result = verify('select', ['display' => 'radio', 'label' => 'Pick one', 'name' => 'choice_r', 'options' => $select_options], 'anti-select--radio');
echo "select/radio .... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "select/radio: {$result}"; }

$result = verify('select', ['display' => 'checkbox', 'label' => 'Pick many', 'name' => 'choice_c', 'options' => $select_options], 'anti-select--checkbox');
echo "select/checkbox . {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "select/checkbox: {$result}"; }

$result = verify('select', ['display' => 'button-group', 'label' => 'Pick one', 'name' => 'choice_bg', 'options' => $select_options, 'value' => 'b'], 'anti-select--button-group');
echo "select/btngroup . {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "select/btngroup: {$result}"; }

// Verify radio uses fieldset/legend
ob_start();
anti_component('select', ['display' => 'radio', 'label' => 'Group', 'name' => 'grp', 'options' => $select_options]);
$radio_html = ob_get_clean();
$fieldset_ok = strpos($radio_html, '<fieldset') !== false && strpos($radio_html, '<legend') !== false;
$fs_result = $fieldset_ok ? 'OK' : 'ERROR: Fieldset/legend not found in radio mode';
echo "select/fieldset . {$fs_result}\n";
if ($fieldset_ok) $passed++; else { $failed++; $errors[] = "select/fieldset: {$fs_result}"; }

// ─────────────────────────────────────────────────
// Test: Boolean (both display modes)
// ─────────────────────────────────────────────────
$result = verify('boolean', ['display' => 'checkbox', 'label' => 'Agree', 'name' => 'agree'], 'anti-boolean--checkbox');
echo "boolean/check ... {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "boolean/check: {$result}"; }

$result = verify('boolean', ['display' => 'switch', 'label' => 'Notifications', 'name' => 'notify'], 'anti-boolean--switch');
echo "boolean/switch .. {$result}\n";
if ($result === 'OK') $passed++; else { $failed++; $errors[] = "boolean/switch: {$result}"; }

// Verify hidden native checkbox exists
ob_start();
anti_component('boolean', ['display' => 'switch', 'label' => 'Test', 'name' => 'test_bool']);
$bool_html = ob_get_clean();
$native_ok = strpos($bool_html, 'type="checkbox"') !== false && strpos($bool_html, 'anti-boolean__control') !== false;
$nat_result = $native_ok ? 'OK' : 'ERROR: Hidden native checkbox not found';
echo "boolean/native .. {$nat_result}\n";
if ($native_ok) $passed++; else { $failed++; $errors[] = "boolean/native: {$nat_result}"; }

// ─────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────
echo "\n" . str_repeat('─', 40) . "\n";
echo "Passed: {$passed}  Failed: {$failed}\n";

if (!empty($errors)) {
    echo "\nFailures:\n";
    foreach ($errors as $err) {
        echo "  - {$err}\n";
    }
}

exit($failed > 0 ? 1 : 0);
