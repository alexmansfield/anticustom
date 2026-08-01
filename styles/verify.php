<?php
/**
 * Token Generator Verification Script
 *
 * The generator-output seam for the palette-model emission model (M1). Shells
 * `styles/generate.php` against defaults.json and small fixture token files and
 * asserts on the emitted CSS string — external behavior — rather than on the
 * internal shape of PHP helpers (scale_value(), the emit_* functions) that this
 * milestone is deliberately churning. The generator is a pure function from a
 * token JSON file to a CSS string; this tests it at that boundary.
 *
 * Run: php styles/verify.php
 *
 * Assertion style mirrors fields/verify.php: labelled OK/FAIL lines, a pass/fail
 * count, and exit(1) on any failure.
 */

$passed = 0;
$failed = 0;
$errors = [];

/** Shell the generator against a token file and capture the emitted CSS. */
function generate_css(?string $tokenPath = null): string
{
    $script = escapeshellarg(__DIR__ . '/generate.php');
    $cmd = 'php ' . $script;
    if ($tokenPath !== null) {
        $cmd .= ' ' . escapeshellarg($tokenPath);
    }
    return (string) shell_exec($cmd . ' 2>/dev/null');
}

/** Shell the generator and capture [combined output, exit code] — for the
 *  invalid-data fixtures whose whole point is that generation must fail. */
function generate_result(?string $tokenPath = null): array
{
    $script = escapeshellarg(__DIR__ . '/generate.php');
    $cmd = 'php ' . $script;
    if ($tokenPath !== null) {
        $cmd .= ' ' . escapeshellarg($tokenPath);
    }
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    return [implode("\n", $out), $code];
}

function fixture(string $name): string
{
    return __DIR__ . '/fixtures/' . $name;
}

/** Parse the set of emitted custom-property names (without the leading `--`). */
function emitted_keys(string $css): array
{
    preg_match_all('/^\s*--([a-z0-9-]+)\s*:/mi', $css, $m);
    return array_fill_keys($m[1], true);
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed, $errors;

    $dots = str_repeat('.', max(2, 44 - strlen($label)));
    echo "{$label} {$dots} " . ($ok ? 'OK' : 'FAIL') . "\n";

    if ($ok) {
        $passed++;
    } else {
        $failed++;
        $errors[] = $detail !== '' ? "{$label}: {$detail}" : $label;
    }
}

$css  = generate_css();               // defaults.json
$keys = emitted_keys($css);

// ─────────────────────────────────────────────────
// Spec-token presence (1.4): the guaranteed base-spec key set always emits
// ─────────────────────────────────────────────────
$specKeys = [
    // spacing spec steps xs–xl
    'space-xs', 'space-s', 'space-m', 'space-l', 'space-xl',
    // text spec steps s/m/l
    'text-s', 'text-m', 'text-l',
    // six symmetric headings
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    // pick-one bare aliases + scale bare aliases
    'border', 'radius', 'shadow', 'space', 'text',
    // color's single M1 touchpoint
    'palette-surface',
];
foreach ($specKeys as $k) {
    check("spec token --{$k} present", isset($keys[$k]), 'not emitted');
}

// ─────────────────────────────────────────────────
// Key-identity emission (1.1): names are exactly `--{key}`, no generator prefix
// ─────────────────────────────────────────────────
check('headings emit --h{n}', isset($keys['h1']) && isset($keys['h6']));
check('no legacy --heading-{n} prefix', strpos($css, '--heading-') === false,
    'generator still prefixes heading tokens');
check('no vestigial --anti-border-* set', strpos($css, '--anti-border') === false,
    '--anti-* interface vars still emitted');

// ─────────────────────────────────────────────────
// Bare aliases (1.5): present, and --shadow resolves to the seeded `none`
// ─────────────────────────────────────────────────
check('--space alias → anchor', preg_match('/--space:\s*var\(--space-m\)/', $css) === 1);
check('--text alias → anchor', preg_match('/--text:\s*var\(--text-m\)/', $css) === 1);
check('--border alias → default', preg_match('/--border:\s*var\(--border-m\)/', $css) === 1);
check('--radius alias → default', preg_match('/--radius:\s*var\(--radius-m\)/', $css) === 1);
check('--shadow alias resolves to none', preg_match('/--shadow:\s*none\s*;/', $css) === 1,
    'shadow default should emit the literal none');

// ─────────────────────────────────────────────────
// .anti-interface retirement (1.0): no interface apparatus in the output
// ─────────────────────────────────────────────────
check('.anti-interface block gone', strpos($css, '.anti-interface') === false,
    'centralized interface rule still emitted');

// ─────────────────────────────────────────────────
// Custom tokens ride the same ramp (user story 14): nothing dropped
// ─────────────────────────────────────────────────
check('custom --space-xxs present', isset($keys['space-xxs']));
check('custom --space-xxl present', isset($keys['space-xxl']));
check('custom --text-xs present', isset($keys['text-xs']));
check('custom --text-xl present', isset($keys['text-xl']));

// ─────────────────────────────────────────────────
// Open-set behavior: a spec listing only { s, m, l } emits exactly those
// ─────────────────────────────────────────────────
$openCss  = generate_css(__DIR__ . '/fixtures/open-set.json');
$openKeys = emitted_keys($openCss);
check('open-set emits listed --space-s', isset($openKeys['space-s']));
check('open-set emits listed --space-m', isset($openKeys['space-m']));
check('open-set emits listed --space-l', isset($openKeys['space-l']));
check('open-set omits absent --space-xs', !isset($openKeys['space-xs']),
    'absent step should not emit (presence is membership)');
check('open-set omits absent --space-xxl', !isset($openKeys['space-xxl']));
check('open-set still emits --space alias', isset($openKeys['space']));

// ─────────────────────────────────────────────────
// Fallback-presence guard (1.7): every component chained fallback
// `var(--{family}-{step}, var(--{alias}))` resolves — the alias tail is always
// an emitted key. Scans the real component CSS against the default emission.
// ─────────────────────────────────────────────────
$aliasFamilies = ['space', 'text', 'border', 'radius', 'shadow'];
$componentCss = '';
foreach (glob(dirname(__DIR__) . '/components/*/styles/*.css') as $file) {
    $componentCss .= file_get_contents($file) . "\n";
}

// No component may still reference the retired --heading-{n} names.
check('components reference --h{n}, not --heading-{n}',
    !preg_match('/var\(\s*--heading-\d/', $componentCss),
    'a component still references a legacy --heading-{n} token');

// Every chained fallback tail (the alias) must be an emitted key.
preg_match_all(
    '/var\(\s*--(?:' . implode('|', $aliasFamilies) . ')-[a-z0-9]+\s*,\s*var\(\s*--(' . implode('|', $aliasFamilies) . ')\s*\)\s*\)/i',
    $componentCss,
    $chains
);
$chainCount = count($chains[0]);
$unresolved = [];
foreach (array_unique($chains[1]) as $alias) {
    if (!isset($keys[$alias])) {
        $unresolved[] = "--{$alias}";
    }
}
check("fallback chains resolve to an emitted alias ({$chainCount} found)",
    empty($unresolved),
    'unresolved chain aliases: ' . implode(', ', $unresolved));

// Guard has teeth: a family with no `default` emits no alias, so a chain that
// bottoms out there would resolve to nothing — verify would catch it.
$brokenCss  = generate_css(__DIR__ . '/fixtures/no-space-alias.json');
$brokenKeys = emitted_keys($brokenCss);
check('guard teeth: missing --space alias is detectable',
    !isset($brokenKeys['space']) && isset($brokenKeys['space-l']),
    'no-default fixture should drop the alias while keeping members');

// ═════════════════════════════════════════════════
// M2 — Mode system + per-device fluid clamps (ADR 0018)
// ═════════════════════════════════════════════════

// Distinct per-device anchors emit a fluid clamp; the middle term is exactly
// resolvable server-side (this is the deterministic-output guarantee).
check('distinct anchors emit a fluid clamp',
    preg_match('/--space-m:\s*clamp\(/', $css) === 1,
    'a scale token with distinct device anchors should compile to clamp()');
check('clamp interpolates between device anchors (space-m)',
    strpos($css, '--space-m: clamp(12px, calc(10.5143px + 0.381vw), 16px);') !== false,
    'space-m clamp does not match the resolved line through the viewport anchors');

// Equal per-device anchors collapse to a static value, not a degenerate clamp.
$staticCss = generate_css(fixture('static-anchor.json'));
check('equal anchors emit a static value',
    preg_match('/--space-m:\s*16px\s*;/', $staticCss) === 1 && strpos($staticCss, '--space-m: clamp(') === false,
    'equal device anchors should emit a plain px value, no clamp');

// Store-completeness guard: mode:custom with a missing size key is invalid data —
// generation errors rather than backfilling from scale math (protects ADR 0017).
[$incompleteOut, $incompleteCode] = generate_result(fixture('custom-incomplete.json'));
check('incomplete custom store is a generate error',
    $incompleteCode !== 0 && strpos($incompleteOut, "missing 'l'") !== false,
    'a mode:custom family missing a size key must fail generation');

// A complete custom store generates and reads the store (not the scale math).
[$completeOut, $completeCode] = generate_result(fixture('custom-complete.json'));
check('complete custom store generates', $completeCode === 0, $completeOut);
check('custom store value is read verbatim (space-l)',
    strpos($completeOut, '--space-l: clamp(18px, calc(15.7714px + 0.5714vw), 24px);') !== false,
    'custom-mode size should read the {mobile,desktop} pair from the store');

// Distinct-viewport guard: equal viewport anchors zero the slope denominator.
[$vpOut, $vpCode] = generate_result(fixture('viewport-equal.json'));
check('equal viewport anchors are a generate error',
    $vpCode !== 0 && strpos($vpOut, 'viewport anchors must be distinct') !== false,
    'equal mobile/desktop viewport should fail generation');

// ═════════════════════════════════════════════════
// M2 — Derived heading typography (ADR 0022)
// ═════════════════════════════════════════════════

// Scale mode derives line-height/letter-spacing as calc() forms keyed to the
// computed size (the em term tracks the fluid size), plus one authored weight.
check('heading line-height is derived calc (leading)',
    strpos($css, '--h1-line-height: calc(1em + 8px);') !== false,
    'scale-mode line-height should be calc(1em + <leading>px)');
check('heading letter-spacing is derived affine calc',
    strpos($css, '--h1-letter-spacing: calc(-0.022em + 0.35px);') !== false,
    'scale-mode letter-spacing should be calc(<slope>em + <constant>px)');
check('heading weight is single authored value',
    strpos($css, '--h1-weight: 600;') !== false && strpos($css, '--h6-weight: 600;') !== false,
    'scale mode carries one weight for all levels');
check('every heading level emits derived typography',
    substr_count($css, '-line-height: calc(1em + 8px);') === 6,
    'all six levels should carry the derived line-height');

// Heading sizes resolve exactly server-side at both device anchors.
check('h1 size resolves at both anchors',
    strpos($css, '--h1: clamp(114px, calc(90.6px + 6vw), 177px);') !== false,
    'h1 clamp should match the scale computation at the mobile/desktop anchors');
check('h6 (anchor step) size resolves',
    strpos($css, '--h6: clamp(15px, calc(14.6286px + 0.0952vw), 16px);') !== false);

// Custom mode reads per-level blocks from customStyle instead of deriving.
$customHeadCss = generate_css(fixture('custom-headings.json'));
check('custom-mode heading reads customStyle line-height',
    strpos($customHeadCss, '--h1-line-height: 1.1;') !== false,
    'custom mode should emit the authored per-level line-height');
check('custom-mode heading reads customStyle weight',
    strpos($customHeadCss, '--h1-weight: 700;') !== false,
    'custom mode should emit the authored per-level weight');

// ─────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────
echo "\n{$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    echo "\n" . implode("\n", $errors) . "\n";
    exit(1);
}
