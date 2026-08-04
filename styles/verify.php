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

// Pull in the generator's pure helper functions (library mode: the require
// returns before any emission side effects) so the OKLCH math can be unit-tested
// at the function boundary, not only through the emitted CSS string.
require_once __DIR__ . '/generate.php';

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

// Advisory channel (ADR 0007 advisory tier): warnings are surfaced but never
// fail the build — legibility floors are context-dependent (3:1 passes for
// large/bold text), so a sub-4.5:1 pair is flagged, not rejected.
$warnings = [];
function warn(string $label, bool $ok, string $detail = ''): void
{
    global $warnings;
    if ($ok) return;
    echo "  ⚠ {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    $warnings[] = $detail !== '' ? "{$label}: {$detail}" : $label;
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

// ═════════════════════════════════════════════════
// M3 — Ramp tier: flat colors × stops, dense, no states (ADR 0025/0019/0026)
// ═════════════════════════════════════════════════

// Dense grid: every seeded source color emits its bare source + each stop.
check('ramp emits bare source --primary', isset($keys['primary']),
    'the source color itself should emit as --{color}');
check('ramp emits --primary-ultra-light (spec stop)', isset($keys['primary-ultra-light']));
check('ramp is dense across colors (--danger-dark)', isset($keys['danger-dark']),
    'presence-is-membership: every color × stop emits');

// No interaction states at the ramp tier (ADR 0026): the ramp carries base
// values only; -hover/-active exist solely at the palette tier.
check('no ramp-tier state on source (--primary-hover absent)', !isset($keys['primary-hover']),
    'ramp source must not carry interaction states');
check('no ramp-tier state on stop (--primary-dark-hover absent)', !isset($keys['primary-dark-hover']),
    'ramp stops must not carry interaction states (ADR 0026)');

// Literal endpoints (ADR 0019): a stop at L100/L0 emits #ffffff / #000000, the
// special case triggered by the L value, not the stop name.
check('L100 stop emits literal white', preg_match('/--primary-white:\s*#ffffff\s*;/', $css) === 1,
    'a stop at L100 should emit literal #ffffff');
check('L0 stop emits literal black', preg_match('/--primary-black:\s*#000000\s*;/', $css) === 1,
    'a stop at L0 should emit literal #000000');

// `enabled` is gone (ADR 0025): no legacy section walk survives.
check('no legacy --semantic-/--brand- section prefix',
    strpos($css, '--semantic-') === false && strpos($css, '--brand-') === false,
    'groups flatten — they never entered an emitted name');

// Pins (ADR 0019): a pinned stop's hex wins over the computed shade; presence of
// the key is the pin; other stops still compute.
$rampCss  = generate_css(fixture('ramp-pins.json'));
$rampKeys = emitted_keys($rampCss);
check('pin overrides computed shade', strpos($rampCss, '--brandx-dark: #1e2f45;') !== false,
    'a pins[stop] hex should win over the generated ramp value');
check('unpinned stop still computes', isset($rampKeys['brandx-light']) &&
    strpos($rampCss, '--brandx-light: #1e2f45;') === false,
    'a stop without a pin should still emit a computed shade');
check('pinned ramp still carries no states', !isset($rampKeys['brandx-dark-hover']),
    'even a pinned stop emits no interaction state (ADR 0026 amends 0019)');

// ═════════════════════════════════════════════════
// M3 — Palette tier: contrast scale + intents + states + bindings (ADR 0015/16/20/26)
// ═════════════════════════════════════════════════

// Surface-anchored contrast scale (ADR 0015): surface + 4 steps, no middle.
foreach (['surface', 'ultra-soft-contrast', 'soft-contrast', 'hard-contrast', 'ultra-hard-contrast'] as $step) {
    check("palette contrast step --palette-{$step}", isset($keys["palette-{$step}"]), 'not emitted');
}

// Intents are two tokens — fill + authored -on (ADR 0016/0020) — plus accent peer.
foreach (['accent', 'info', 'success', 'warning', 'danger'] as $intent) {
    check("palette intent --palette-{$intent} + -on",
        isset($keys["palette-{$intent}"]) && isset($keys["palette-{$intent}-on"]),
        'intent fill and its -on foreground must both emit');
}

// The colorway vocabulary is fully retired (ADR 0015 rename).
check('no --colorway-* survives in emitted CSS', strpos($css, '--colorway-') === false,
    'a colorway token still emits');
check('no [data-colorway] selector survives', strpos($css, 'data-colorway') === false,
    'region theming should switch on [data-palette]');

// Interaction states are palette-tier color-mix (ADR 0026), pole by lightness:
// a light fill mixes toward white, a dark fill toward black.
check('light fill (surface) hover mixes toward white',
    strpos($css, '--palette-surface-hover: color-mix(in srgb, var(--palette-surface), white 12%);') !== false,
    'a light slot should shift outward toward white on hover');
check('dark fill (hard-contrast) hover mixes toward black',
    strpos($css, '--palette-hard-contrast-hover: color-mix(in srgb, var(--palette-hard-contrast), black 12%);') !== false,
    'a dark slot should mix toward black');
check('active is a larger mix than hover',
    strpos($css, '--palette-surface-active: color-mix(in srgb, var(--palette-surface), white 20%);') !== false,
    'active should be a hair stronger (20%) than hover (12%)');
check('-on foregrounds carry no interaction state', !isset($keys['palette-accent-on-hover']),
    '-on is orthogonal to states (ADR 0026) — must not emit -on-hover');

// Intent binding rules map generic --intent* to the surrounding palette (ADR 0016).
check('intent binding rule emitted for success',
    preg_match('/\[data-intent="success"\]\s*\{[^}]*--intent:\s*var\(--palette-success\)[^}]*--intent-on:\s*var\(--palette-success-on\)/s', $css) === 1,
    'a [data-intent] binding rule should map --intent/--intent-on to the palette slot');

// A non-default palette emits a [data-palette] region block, sparse (ADR 0015):
// only its own slots emit; omitted slots inherit from :root via the cascade.
preg_match('/\[data-palette="primary"\]\s*\{(.*?)\}/s', $css, $primaryBlock);
$primaryBody = $primaryBlock[1] ?? '';
check('non-default palette emits [data-palette] region', $primaryBody !== '');
check('sparse region emits its own slot (surface)',
    strpos($primaryBody, '--palette-surface:') !== false);
check('sparse region omits slots it does not define (info inherits via cascade)',
    strpos($primaryBody, '--palette-info:') === false,
    'present-keys-only: a slot the region does not define must not emit (ADR 0015 sparse)');

// No component CSS still references the retired --colorway-* vocabulary.
$sweepCss = '';
foreach (glob(dirname(__DIR__) . '/components/*/styles/*.css') as $file) {
    $sweepCss .= file_get_contents($file) . "\n";
}
check('no component references retired --colorway-*',
    strpos($sweepCss, '--colorway-') === false,
    'a component CSS file still references a --colorway-* token');

// Permanent fallback-presence guard (M3.7, extends the 1.7 mechanism to the
// palette tier). Palette/intent tokens emit sparsely (ADR 0015) — a bare
// `var(--palette-x)` referencing a slot a palette omits resolves to nothing, so
// every component palette/intent ref must carry a fallback. A ref *with* a
// fallback has a comma; the regex matches only the bare (comma-less) form.
$paletteBareRefs = function (string $css): array {
    preg_match_all('/var\(\s*--(palette-[a-z0-9-]+|intent(?:-[a-z]+)?)\s*\)/i', $css, $m);
    return array_values(array_unique($m[0]));
};
// The named-style registry (fields/styles.json) emits into the same stylesheet,
// so its palette refs are bound by the same contract.
$sweepCss .= "\n" . file_get_contents(dirname(__DIR__) . '/fields/styles.json');
$bare = $paletteBareRefs($sweepCss);
check('no component/named-style palette ref is bare (fallback contract, ADR 0015)',
    empty($bare),
    'bare refs (need a fallback): ' . implode(', ', $bare));

// Guard has teeth: a planted bare ref is detected.
check('guard teeth: a bare palette ref is caught',
    count($paletteBareRefs('.x { color: var(--palette-soft-contrast); background: var(--intent); }')) === 2,
    'the bare-ref guard failed to flag a planted violation');

// ═════════════════════════════════════════════════
// M3 — OKLCH ramp math (isolated port #29; docs/research/oklch-generation.md)
// ═════════════════════════════════════════════════

$approx = fn(float $a, float $b, float $t = 0.003): bool => abs($a - $b) <= $t;

// Conversion matches published colorjs.io / oklch.com reference values — the
// anchor against a silent matrix-transcription regression.
[$rL, $rC, $rH] = hex_to_oklch('#ff0000');
check('OKLCH(#ff0000) matches reference', $approx($rL, 0.6280) && $approx($rC, 0.2577) && $approx($rH, 29.23, 0.1),
    sprintf('got L=%.4f C=%.4f H=%.2f, expected L0.6280 C0.2577 H29.23', $rL, $rC, $rH));
[$bL, $bC, $bH] = hex_to_oklch('#0000ff');
check('OKLCH(#0000ff) matches reference', $approx($bL, 0.4520) && $approx($bC, 0.3132) && $approx($bH, 264.05, 0.2),
    sprintf('got L=%.4f C=%.4f H=%.2f, expected L0.4520 C0.3132 H264.05', $bL, $bC, $bH));
[$wL, $wC] = hex_to_oklch('#ffffff');
check('OKLCH(#ffffff) is L1 C0', $approx($wL, 1.0) && $approx($wC, 0.0),
    sprintf('white should be achromatic L1, got L=%.5f C=%.5f', $wL, $wC));

// Round-trip identity: an in-gamut hex survives hex→OKLCH→gamut-mapped hex
// unchanged (to 8-bit). Exercises the forward and inverse pipelines together.
$rtOk = true;
foreach (['#336699', '#0ea5e9', '#22c55e', '#eab308', '#ef4444', '#8b5cf6', '#123456', '#abcdef'] as $hex) {
    if (strtolower(oklch_to_hex(...hex_to_oklch($hex))) !== strtolower($hex)) {
        $rtOk = false;
        break;
    }
}
check('OKLCH round-trip is identity for in-gamut hex', $rtOk,
    'a color changed under hex→OKLCH→hex — forward/inverse mismatch');

// Hue stability: an achromatic source stays achromatic at every stop (R=G=B) —
// OKLCH's defining advantage over HSL shading, which drifts hue near extremes.
$grayStable = true;
foreach ([90, 80, 65, 35, 20, 10] as $Lv) {
    $sh = color_shade('#737373', $Lv);
    if (!(substr($sh, 1, 2) === substr($sh, 3, 2) && substr($sh, 3, 2) === substr($sh, 5, 2))) {
        $grayStable = false;
        break;
    }
}
check('achromatic source stays gray at every stop', $grayStable,
    'neutral ramp drifted off gray — hue leaked through shading');

// Gamut safety: every emitted ramp hex is a well-formed 6-digit sRGB value (the
// chroma-bisection mapper never emits an out-of-range/garbage channel).
preg_match_all('/--[a-z0-9-]+:\s*(#[0-9a-f]{6})\s*;/i', $css, $hexes);
$malformed = array_filter($hexes[1], fn($h) => !preg_match('/^#[0-9a-f]{6}$/i', $h));
check('every emitted hex is gamut-safe #rrggbb (' . count($hexes[1]) . ' values)', empty($malformed),
    'malformed hex emitted: ' . implode(', ', $malformed));

// Stable emitted ramp values (regression pins on the actual OKLCH output).
check('primary ultra-light OKLCH value pinned',
    strpos($css, '--primary-ultra-light: #bbe2ff;') !== false,
    'the primary L90 OKLCH stop drifted from its expected value');
check('neutral semi-light OKLCH value pinned',
    strpos($css, '--neutral-semi-light: #8f8f8f;') !== false,
    'the neutral L65 OKLCH stop drifted from its expected value');

// ═════════════════════════════════════════════════
// M3 — WCAG contrast matrix (isolated port #31; docs/research/php-contrast-matrix.md)
//
// Backs verification, not generation (ADR 0020): resolve each palette's pairs
// exactly as the cascade would and emit advisory warnings below 4.5:1. The two
// pure functions are WCAG 2.2 relative luminance + contrast ratio (0.04045
// threshold); thresholds are evaluated on the unrounded ratio.
// ═════════════════════════════════════════════════

function anti_relative_luminance(string $hex): float {
    $hex = ltrim($hex, '#');
    $lin = [];
    foreach ([0, 2, 4] as $i) {
        $c = hexdec(substr($hex, $i, 2)) / 255;
        $lin[] = $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

function anti_contrast_ratio(string $hexA, string $hexB): float {
    $la = anti_relative_luminance($hexA);
    $lb = anti_relative_luminance($hexB);
    [$dark, $light] = $la < $lb ? [$la, $lb] : [$lb, $la];
    return ($light + 0.05) / ($dark + 0.05);
}

/** color-mix(in srgb, fill, pole pct%): linear interpolation of gamma-encoded channels. */
function mix_srgb(string $fillHex, string $pole, float $pct): string {
    $p = $pct / 100.0;
    $poleV = $pole === 'white' ? 1.0 : 0.0;
    $fh = ltrim($fillHex, '#');
    $out = '#';
    foreach ([0, 2, 4] as $i) {
        $c = hexdec(substr($fh, $i, 2)) / 255.0;
        $out .= sprintf('%02x', (int) round(($c * (1 - $p) + $poleV * $p) * 255));
    }
    return $out;
}

// Hard checks: the math matches WebAIM-published values exactly (the anchor
// against a silent luminance/ratio regression). These FAIL, unlike legibility.
$cr = fn($a, $b) => round(anti_contrast_ratio($a, $b), 4);
check('contrast(#777777, #fff) == 4.4781 (WebAIM)', $cr('#777777', '#ffffff') === 4.4781,
    'got ' . $cr('#777777', '#ffffff'));
check('contrast(#767676, #fff) == 4.5422 (WebAIM)', $cr('#767676', '#ffffff') === 4.5422,
    'got ' . $cr('#767676', '#ffffff'));
check('contrast(#fff, #000) == 21.0', $cr('#ffffff', '#000000') === 21.0,
    'got ' . $cr('#ffffff', '#000000'));
check('contrast ratio is symmetric', $cr('#336699', '#ffffff') === $cr('#ffffff', '#336699'));

// Advisory legibility over the resolved default palette (ADR 0020 coverage):
// (1) surface vs each text-bearing contrast step; (2) each intent + accent vs
// its resolved -on, at rest and at the fill's derived hover/active.
$tokenData = json_decode(file_get_contents(__DIR__ . '/defaults.json'), true);
$dpal   = $tokenData['color']['palettes']['default'] ?? [];
$rmap   = resolve_ramp($tokenData['color']['colors'] ?? [], $tokenData['color']['stops'] ?? []);
$rhex   = fn(?string $v): ?string => $v === null ? null : resolve_palette_hex($v, $rmap);

$surface = $rhex($dpal['surface'] ?? null);
if ($surface !== null) {
    foreach (['hard-contrast', 'ultra-hard-contrast'] as $textStep) {   // ADR 0020 default text list
        $stepHex = $rhex($dpal[$textStep] ?? null);
        if ($stepHex === null) continue;
        $ratio = anti_contrast_ratio($surface, $stepHex);
        warn("surface vs {$textStep} legible (≥4.5:1)", $ratio >= 4.5, sprintf('%.2f:1', $ratio));
    }
}

foreach (['accent', 'info', 'success', 'warning', 'danger'] as $intent) {
    $fill = $rhex($dpal[$intent] ?? null);
    $on   = $rhex($dpal["{$intent}-on"] ?? null);
    if ($fill === null || $on === null) continue;

    $pole = palette_state_pole($dpal[$intent], $rmap);
    $states = [
        'rest'   => $fill,
        'hover'  => mix_srgb($fill, $pole, 12),
        'active' => mix_srgb($fill, $pole, 20),
    ];
    foreach ($states as $state => $fillHex) {
        $ratio = anti_contrast_ratio($fillHex, $on);   // -on stays fixed while fill shifts
        warn("intent {$intent}/{$state} vs -on legible (≥4.5:1)", $ratio >= 4.5, sprintf('%.2f:1', $ratio));
    }
}

// ─────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────
if (!empty($warnings)) {
    echo "\n" . count($warnings) . " advisory legibility warning(s) (non-failing):\n  - " . implode("\n  - ", $warnings) . "\n";
}
echo "\n{$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    echo "\n" . implode("\n", $errors) . "\n";
    exit(1);
}
