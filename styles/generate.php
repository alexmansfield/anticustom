<?php
/**
 * CSS Variable Generator
 *
 * Reads defaults.json (or a custom token file) and outputs a complete CSS
 * file with :root variables and [data-colorway] blocks.
 *
 * Emission model (palette-model M1, ADRs 0015–0028):
 *   - Open ordered sets: presence in `sizes` is membership; there is no
 *     `enabled` gate. A step a spec omits simply does not emit.
 *   - Key-identity naming: the emitted variable is exactly `--{key}` (the
 *     spec author owns the namespace; the generator adds no prefix of its own).
 *   - Anchor-as-origin: a scale family's `default` step is its anchor; that
 *     step's `value` is the scale origin, and every other step is
 *     `anchorValue * ratio^(position − anchorPosition)`. There is no `baseSize`.
 *   - Bare aliases: every scale family emits `--space`/`--text` pointing at its
 *     anchor, and every pick-one family emits `--border`/`--radius`/`--shadow`
 *     pointing at its designated default — the chained-fallback targets
 *     components degrade to (ADR 0017 / 0024).
 *
 * Usage: php styles/generate.php [path/to/tokens.json] [--output path/to/output.css]
 */

$defaultPath = __DIR__ . '/defaults.json';
$path = $defaultPath;
$outputPath = null;

// Parse arguments
for ($i = 1; $i < ($argc ?? 1); $i++) {
    if (($argv[$i] ?? '') === '--output' && isset($argv[$i + 1])) {
        $outputPath = $argv[++$i];
    } elseif (!str_starts_with($argv[$i] ?? '', '--')) {
        $path = $argv[$i];
    }
}

if (!file_exists($path)) {
    fprintf(STDERR, "Error: File not found: %s\n", $path);
    exit(1);
}

$json = file_get_contents($path);
$tokens = json_decode($json, true);

if ($tokens === null) {
    fprintf(STDERR, "Error: Invalid JSON in %s\n", $path);
    exit(1);
}

// ============================================================================
// Color conversion helpers
// ============================================================================

function hex_to_hsl(string $hex): array {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;

    if ($max === $min) {
        return [0, 0, $l * 100];
    }

    $d = $max - $min;
    $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

    if ($max === $r) {
        $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
    } elseif ($max === $g) {
        $h = (($b - $r) / $d + 2) / 6;
    } else {
        $h = (($r - $g) / $d + 4) / 6;
    }

    return [round($h * 360, 1), round($s * 100, 1), round($l * 100, 1)];
}

function hsl_to_hex(float $h, float $s, float $l): string {
    $h /= 360;
    $s /= 100;
    $l /= 100;

    if ($s == 0) {
        $r = $g = $b = $l;
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $r = hue_to_rgb($p, $q, $h + 1/3);
        $g = hue_to_rgb($p, $q, $h);
        $b = hue_to_rgb($p, $q, $h - 1/3);
    }

    return sprintf('#%02x%02x%02x',
        (int) round($r * 255),
        (int) round($g * 255),
        (int) round($b * 255)
    );
}

function hue_to_rgb(float $p, float $q, float $t): float {
    if ($t < 0) $t += 1;
    if ($t > 1) $t -= 1;
    if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
    if ($t < 1/2) return $q;
    if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
    return $p;
}

/**
 * Given a base hex color and a target lightness (0-100), return a new hex
 * color with the same hue and saturation but adjusted lightness.
 */
function color_shade(string $hex, float $targetLightness): string {
    [$h, $s, $l] = hex_to_hsl($hex);
    return hsl_to_hex($h, $s, $targetLightness);
}

// ============================================================================
// Interaction state helpers (hover/active)
// ============================================================================

const HOVER_LOWER_BOUND = 25;
const HOVER_UPPER_BOUND = 75;
const HOVER_SHIFT = 5;
const ACTIVE_SHIFT = 10;

/**
 * Compute hover and active hex variants for a given color.
 *
 * Shift direction depends on lightness band:
 *   0–25%  (dark)       → toward center (+)
 *   25–50% (mid-dark)   → toward edge (−)
 *   50–75% (mid-light)  → toward edge (+)
 *   75–100% (light)     → toward center (−)
 */
function color_interaction_shifts(string $hex): array {
    [$h, $s, $l] = hex_to_hsl($hex);

    if ($l <= HOVER_LOWER_BOUND) {
        $dir = 1;   // lighten
    } elseif ($l <= 50) {
        $dir = -1;  // darken
    } elseif ($l <= HOVER_UPPER_BOUND) {
        $dir = 1;   // lighten
    } else {
        $dir = -1;  // darken
    }

    $hoverL = max(0, min(100, $l + $dir * HOVER_SHIFT));
    $activeL = max(0, min(100, $l + $dir * ACTIVE_SHIFT));

    return [
        'hover'  => hsl_to_hex($h, $s, $hoverL),
        'active' => hsl_to_hex($h, $s, $activeL),
    ];
}

/**
 * Derive a hover/active value from a colorway token value.
 *
 * If the value is a var() reference, append -hover/-active to the variable name.
 * If the value is a raw hex, compute the shift directly.
 */
function colorway_derive_state(string $value, string $state): string {
    if (preg_match('/^var\(--(.+)\)$/', $value, $m)) {
        return "var(--{$m[1]}-{$state})";
    }
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        $shifts = color_interaction_shifts($value);
        return $shifts[$state];
    }
    return $value;
}

// ============================================================================
// Size-family emission helpers (open sets, key-identity, anchor-as-origin)
// ============================================================================

/**
 * Value of a scale step relative to the family anchor:
 *   anchorValue * ratio^(position − anchorPosition).
 * The anchor step itself (position === anchorPosition) resolves to anchorValue.
 */
function scale_value(float $anchorValue, float $ratio, int $position, int $anchorPosition = 0): float {
    return $anchorValue * pow($ratio, $position - $anchorPosition);
}

/**
 * Format a numeric px/coefficient value: trim trailing zeros, fold −0 to 0.
 */
function num($v): string {
    $s = rtrim(rtrim(sprintf('%.4f', (float) $v), '0'), '.');
    return ($s === '' || $s === '-0') ? '0' : $s;
}

/**
 * Read + validate the global viewport anchors (ADR 0018). The two viewport
 * widths must be distinct — an equal pair zeroes the fluid-slope denominator.
 * A missing block falls back to the factory pair.
 */
function read_viewport(array $tokens): array {
    $vp = $tokens['viewport'] ?? [];
    $mobile = (int) ($vp['mobile'] ?? 390);
    $desktop = (int) ($vp['desktop'] ?? 1440);
    if ($mobile === $desktop) {
        fprintf(STDERR, "Error: viewport anchors must be distinct (mobile === desktop === %d)\n", $mobile);
        exit(1);
    }
    return [$mobile, $desktop];
}

/**
 * Compile a px-length token from its two per-device anchors (ADR 0018).
 *
 * Equal anchors emit a plain static value; distinct anchors emit a clamp()
 * whose preferred middle term is the line through (vpMobile, mobileVal) and
 * (vpDesktop, desktopVal), computed here so the synced output is deterministic
 * and server-side verifiable. Outer bounds swap when mobile > desktop.
 *
 * $round true  → integer px (spacing, headings)
 * $round false → 1-decimal px (text), preserving the finer type steps
 */
function fluid_clamp(float $mobileVal, float $desktopVal, int $vpMobile, int $vpDesktop, bool $round): string {
    $m = $round ? round($mobileVal) : round($mobileVal, 1);
    $d = $round ? round($desktopVal) : round($desktopVal, 1);

    if ($m === $d) {
        return num($m) . 'px';
    }

    $min = min($m, $d);
    $max = max($m, $d);
    $slope = ($d - $m) / ($vpDesktop - $vpMobile);   // px per px of viewport width
    $intercept = $m - $slope * $vpMobile;            // px at viewport 0
    $vwCoeff = $slope * 100;                          // 100vw === viewport width

    $sign = $vwCoeff < 0 ? '-' : '+';
    $preferred = 'calc(' . num($intercept) . 'px ' . $sign . ' ' . num(abs($vwCoeff)) . 'vw)';

    return 'clamp(' . num($min) . 'px, ' . $preferred . ', ' . num($max) . 'px)';
}

/**
 * Per-size compiled px values for a scale family, keyed by size name, honoring
 * `mode` (ADR 0018):
 *   scale  → each size computed from the per-device anchor + ratio at its
 *            position; the anchor step (default) is the origin.
 *   custom → each size read straight from the `custom` store's {mobile,desktop}
 *            pair. An incomplete store is invalid data: every emitted size key
 *            must be present with both device values or generation errors —
 *            no silent backfill from scale math (protects ADR 0017's full set).
 *
 * Returns [sizeKey => [mobileVal, desktopVal]] in `sizes` order.
 */
function resolve_scale_sizes(array $family, string $label): array {
    $sizes = $family['sizes'] ?? [];
    $mode = $family['mode'] ?? 'scale';
    $anchorKey = $family['default'] ?? null;
    $anchorPos = (int) ($sizes[$anchorKey]['position'] ?? 0);

    $out = [];

    if ($mode === 'custom') {
        $store = $family['custom'] ?? [];
        foreach ($sizes as $key => $data) {
            $pair = $store[$key] ?? null;
            if (!is_array($pair) || !isset($pair['mobile'], $pair['desktop'])) {
                fprintf(STDERR, "Error: %s is mode:custom but the custom store is missing '%s' (both mobile and desktop required)\n", $label, $key);
                exit(1);
            }
            $out[$key] = [(float) $pair['mobile'], (float) $pair['desktop']];
        }
        return $out;
    }

    $scale = $family['scale'] ?? [];
    $mMob = (float) ($scale['mobile']['value'] ?? 16);
    $rMob = (float) ($scale['mobile']['ratio'] ?? 1);
    $mDesk = (float) ($scale['desktop']['value'] ?? 16);
    $rDesk = (float) ($scale['desktop']['ratio'] ?? 1);

    foreach ($sizes as $key => $data) {
        $pos = (int) ($data['position'] ?? 0);
        $out[$key] = [
            scale_value($mMob, $rMob, $pos, $anchorPos),
            scale_value($mDesk, $rDesk, $pos, $anchorPos),
        ];
    }
    return $out;
}

/**
 * Emit an open-set scale family (spacing / text / headings) as fluid clamps.
 *
 * Every key in `sizes` emits `--{keyPrefix}{key}` (presence is membership).
 * When $alias is non-null, a bare scale alias `--{alias}` is emitted pointing
 * at the anchor step (ADR 0024) — the chained-fallback target components
 * degrade to.
 */
function emit_scale_family(array $family, string $keyPrefix, ?string $alias, bool $round, array $viewport, string $label): array {
    [$vpMobile, $vpDesktop] = $viewport;
    $lines = [];
    $sizes = $family['sizes'] ?? [];
    $anchorKey = $family['default'] ?? null;

    foreach (resolve_scale_sizes($family, $label) as $key => [$mob, $desk]) {
        $value = fluid_clamp($mob, $desk, $vpMobile, $vpDesktop, $round);
        $lines[] = "    --{$keyPrefix}{$key}: {$value};";
    }

    if ($alias !== null && $anchorKey !== null && isset($sizes[$anchorKey])) {
        $lines[] = "    --{$alias}: var(--{$keyPrefix}{$anchorKey});";
    }

    return $lines;
}

/**
 * Emit derived per-level heading typography (ADR 0022).
 *
 * Scale mode derives line-height and letter-spacing from each level's *computed
 * size* via affine calc() forms (the `em` term tracks the fluid size for free),
 * plus one authored weight for all levels — the knobs live in `style`:
 *   line-height    = calc(1em + <leading>px)
 *   letter-spacing = calc(<slope>em + <constant>px)
 *   weight         = <weight>
 * Custom mode reads per-level blocks from `customStyle` instead; an incomplete
 * store is invalid data and errors, mirroring the size store's completeness
 * guarantee.
 */
function emit_heading_typography(array $family, string $label): array {
    $sizes = $family['sizes'] ?? [];
    $mode = $family['mode'] ?? 'scale';
    $lines = [];

    if ($mode === 'custom') {
        $store = $family['customStyle'] ?? [];
        foreach (array_keys($sizes) as $key) {
            $block = $store[$key] ?? null;
            if (!is_array($block) || !isset($block['lineHeight'], $block['letterSpacing'], $block['weight'])) {
                fprintf(STDERR, "Error: %s is mode:custom but customStyle is missing '%s' (lineHeight, letterSpacing, weight required)\n", $label, $key);
                exit(1);
            }
            $lines[] = "    --{$key}-line-height: " . num($block['lineHeight']) . ";";
            $ls = (float) $block['letterSpacing'];
            $lines[] = "    --{$key}-letter-spacing: " . num($ls) . "em;";
            $lines[] = "    --{$key}-weight: " . (int) $block['weight'] . ";";
        }
        return $lines;
    }

    $style = $family['style'] ?? [];
    $leading = (float) ($style['leading'] ?? 8);
    $slope = (float) ($style['letterSpacingSlope'] ?? 0);
    $const = (float) ($style['letterSpacingConstant'] ?? 0);
    $weight = (int) ($style['weight'] ?? 600);

    $lineHeight = 'calc(1em + ' . num($leading) . 'px)';
    $lsSign = $const < 0 ? '-' : '+';
    $letterSpacing = 'calc(' . num($slope) . 'em ' . $lsSign . ' ' . num(abs($const)) . 'px)';

    foreach (array_keys($sizes) as $key) {
        $lines[] = "    --{$key}-line-height: {$lineHeight};";
        $lines[] = "    --{$key}-letter-spacing: {$letterSpacing};";
        $lines[] = "    --{$key}-weight: {$weight};";
    }
    return $lines;
}

/**
 * Emit an open-set pick-one family (border / radius).
 *
 * Every key in `sizes` with a `value` emits `--{keyPrefix}{key}{unit}`. The
 * `default` step backs an always-emitted bare alias `--{alias}` (ADR 0017) so
 * `var(--border-x, var(--border))`-style fallbacks always resolve.
 */
function emit_pick_one_family(array $family, string $keyPrefix, string $alias, string $unit): array {
    $lines = [];
    $sizes = $family['sizes'] ?? [];

    foreach ($sizes as $key => $data) {
        if (!isset($data['value'])) continue;
        $lines[] = "    --{$keyPrefix}{$key}: {$data['value']}{$unit};";
    }

    $default = $family['default'] ?? null;
    if ($default !== null && isset($sizes[$default]['value'])) {
        $lines[] = "    --{$alias}: var(--{$keyPrefix}{$default});";
    }

    return $lines;
}

// ============================================================================
// Ramp-tier emission (ADR 0025 / 0019 / 0026)
// ============================================================================

/**
 * Emit the dense ramp tier: every source color crossed with every stop.
 *
 * Presence is membership — there is no `enabled` gate (ADR 0025). Each color
 * emits its bare source `--{color}` plus `--{color}-{stop}` for every stop in
 * the scale. A stop's value is resolved:
 *   pin wins   — a `pins[stop]` hex overrides the computed shade (ADR 0019);
 *   L100 / L0  — literal `#ffffff` / `#000000` endpoints (hue is powerless at
 *                the extremes, so the source hue can't be recovered — the
 *                special case triggers on the L value, not the stop name);
 *   otherwise  — the source's hue/chroma held at the stop's L (color_shade).
 *
 * No interaction states emit at this tier (ADR 0026): `-hover`/`-active` live
 * only at the palette tier, whose consumers are the sole internal reference set.
 */
function emit_ramp(array $colors, array $stops): array {
    $lines = [];
    foreach ($colors as $name => $data) {
        $hex = $data['value'] ?? null;
        if ($hex === null) continue;

        $lines[] = "    --{$name}: {$hex};";

        $pins = $data['pins'] ?? [];
        foreach ($stops as $stopName => $stopData) {
            if (!isset($stopData['value'])) continue;
            $L = (float) $stopData['value'];

            if (isset($pins[$stopName])) {
                $val = $pins[$stopName];
            } elseif ($L >= 100) {
                $val = '#ffffff';
            } elseif ($L <= 0) {
                $val = '#000000';
            } else {
                $val = color_shade($hex, $L);
            }

            $lines[] = "    --{$name}-{$stopName}: {$val};";
        }
    }
    return $lines;
}

// ============================================================================
// Output buffer — collect all lines, then write
// ============================================================================

$rootVars = [];
$colorwayBlocks = [];

// Global per-device viewport anchors (ADR 0018); the fluid clamps interpolate
// between these two widths. Validated distinct (divide-by-zero guard).
$viewport = read_viewport($tokens);

// ============================================================================
// Spacing (open scale family: --space-{k} + bare --space alias)
// ============================================================================

$rootVars[] = '    /* Spacing */';
foreach (emit_scale_family($tokens['spacing'] ?? [], 'space-', 'space', true, $viewport, 'spacing') as $line) {
    $rootVars[] = $line;
}

// ============================================================================
// Text sizes (open scale family: --text-{k} + bare --text alias)
// ============================================================================

$rootVars[] = '';
$rootVars[] = '    /* Text */';
foreach (emit_scale_family($tokens['typography']['text'] ?? [], 'text-', 'text', false, $viewport, 'typography.text') as $line) {
    $rootVars[] = $line;
}

// ============================================================================
// Heading sizes (symmetric open set: h{n} emits --h{n}, position orders the
// math — ADR 0027 amends 0024). No bare alias: h1–h6 are guaranteed present.
// Sizes compile to fluid clamps; per-level line-height/letter-spacing/weight
// are derived from the computed size (ADR 0022).
// ============================================================================

$rootVars[] = '';
$rootVars[] = '    /* Headings */';
foreach (emit_scale_family($tokens['typography']['headings'] ?? [], '', null, true, $viewport, 'typography.headings') as $line) {
    $rootVars[] = $line;
}
foreach (emit_heading_typography($tokens['typography']['headings'] ?? [], 'typography.headings') as $line) {
    $rootVars[] = $line;
}

// ============================================================================
// Radius (open pick-one family: --radius-{k} + bare --radius alias)
// ============================================================================

$rootVars[] = '';
$rootVars[] = '    /* Radius */';
foreach (emit_pick_one_family($tokens['radius'] ?? [], 'radius-', 'radius', 'px') as $line) {
    $rootVars[] = $line;
}

// ============================================================================
// Shadows (composite pick-one family: --shadow-{k} + bare --shadow alias).
// The seeded default is `none`, so the bare alias emits the literal `none`
// (a shadow step name would round-trip through a var(); "none" cannot).
// ============================================================================

$shadowFamily = $tokens['shadows'] ?? [];
$rootVars[] = '';
$rootVars[] = '    /* Shadows */';
foreach (($shadowFamily['sizes'] ?? []) as $key => $s) {
    $x = $s['x'] ?? 0;
    $y = $s['y'] ?? 0;
    $blur = $s['blur'] ?? 0;
    $spread = $s['spread'] ?? 0;
    $opacity = $s['opacity'] ?? 0.1;
    $rootVars[] = "    --shadow-{$key}: {$x}px {$y}px {$blur}px {$spread}px rgba(0, 0, 0, {$opacity});";
}
$shadowDefault = $shadowFamily['default'] ?? 'none';
if ($shadowDefault !== 'none' && isset($shadowFamily['sizes'][$shadowDefault])) {
    $rootVars[] = "    --shadow: var(--shadow-{$shadowDefault});";
} else {
    $rootVars[] = "    --shadow: none;";
}

// ============================================================================
// Borders (open pick-one family: --border-{k} + bare --border alias)
// ============================================================================

$rootVars[] = '';
$rootVars[] = '    /* Borders */';
foreach (emit_pick_one_family($tokens['borders'] ?? [], 'border-', 'border', 'px') as $line) {
    $rootVars[] = $line;
}

// ============================================================================
// Font weights
// ============================================================================

$rootVars[] = '';
$rootVars[] = '    /* Font weights */';
$rootVars[] = '    --font-weight-medium: 500;';

// ============================================================================
// Palette bridge (M1)
//
// The full palette shape (contrast steps, intents, state tier) lands in M3.
// M1 emits only the one guaranteed palette key — the surface slot — so the
// base-spec guarantee holds and the interface retirement's stale surface ref
// has a live token to point at.
// ============================================================================

$rootVars[] = '';
$rootVars[] = '    /* Palette bridge (M1 surface slot; full palette shape lands in M3) */';
$rootVars[] = '    --palette-surface: var(--colorway-base);';

// ============================================================================
// Ramp tier (ADR 0025/0019/0026): flat source colors × the stop scale, dense
// (presence-is-membership, no `enabled`), key-identity `--{color}-{stop}`, no
// interaction states — those emit only at the palette tier (ADR 0026, below).
// ============================================================================

$rampColors = $tokens['color']['colors'] ?? [];
$rampStops = $tokens['color']['stops'] ?? [];

$rootVars[] = '';
$rootVars[] = '    /* Colors (ramp tier) */';
foreach (emit_ramp($rampColors, $rampStops) as $line) {
    $rootVars[] = $line;
}

// ============================================================================
// Colorways (unchanged in M1 — renamed/remapped to the palette break in M3)
// ============================================================================

$colorways = $tokens['color']['colorways'] ?? [];

$colorwayTokens = ['base', 'hard-contrast', 'contrast', 'soft-contrast', 'accent'];

foreach ($colorways as $wayName => $wayData) {
    $lines = [];

    foreach ($colorwayTokens as $token) {
        $val = $wayData[$token] ?? null;
        if ($val === null) continue;

        $lines[] = "    --colorway-{$token}: {$val};";

        // Auto-derive hover/active (allow explicit override)
        foreach (['hover', 'active'] as $state) {
            $overrideKey = "{$token}-{$state}";
            if (isset($wayData[$overrideKey])) {
                $lines[] = "    --colorway-{$overrideKey}: {$wayData[$overrideKey]};";
            } else {
                $lines[] = "    --colorway-{$overrideKey}: " . colorway_derive_state($val, $state) . ";";
            }
        }
    }

    if (!empty($lines)) {
        $selector = ($wayName === 'default')
            ? ':root'
            : "[data-colorway=\"{$wayName}\"]";
        $colorwayBlocks[] = "{$selector} {";
        foreach ($lines as $line) {
            $colorwayBlocks[] = $line;
        }
        $colorwayBlocks[] = '}';
        $colorwayBlocks[] = '';
    }
}

// ============================================================================
// Output
// ============================================================================

$output = '';
$output .= "/**\n";
$output .= " * Generated CSS Variables\n";
$output .= " * Source: " . basename($path) . "\n";
$output .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= " *\n";
$output .= " * Do not edit directly — regenerate with: php styles/generate.php\n";
$output .= " */\n\n";

$output .= ":root {\n";
$output .= implode("\n", $rootVars) . "\n";
$output .= "}\n";

if (!empty($colorwayBlocks)) {
    $output .= "\n/* Colorways */\n\n";
    $output .= implode("\n", $colorwayBlocks) . "\n";
}

// Named-style classes (fields/styles.json) — appended here so production
// stylesheets carry the same .anti-style-* classes as the explorer
// (explorer_get_token_css() buffers this script's output)
require_once dirname(__DIR__) . '/fields/sanitize.php';
$output .= "\n" . anti_field_css();

if ($outputPath) {
    file_put_contents($outputPath, $output);
    fprintf(STDERR, "Written to %s\n", $outputPath);
} else {
    echo $output;
}
