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
 * Emit an open-set scale family (spacing / text / headings).
 *
 * Every key in `sizes` emits `--{keyPrefix}{key}` (presence is membership).
 * The `default` step is the anchor: its `value` is the origin and every other
 * step scales off it by `ratio^(position − anchorPosition)`. When $alias is
 * non-null, a bare scale alias `--{alias}` is emitted pointing at the anchor
 * step (ADR 0024) — the chained-fallback target components degrade to.
 *
 * $round true  → integer px (spacing, headings)
 * $round false → 1-decimal px (text), preserving the finer type steps
 */
function emit_scale_family(array $family, string $keyPrefix, ?string $alias, bool $round): array {
    $lines = [];
    $sizes = $family['sizes'] ?? [];
    $ratio = (float) ($family['ratio'] ?? 1);
    $anchorKey = $family['default'] ?? null;
    $anchor = ($anchorKey !== null) ? ($sizes[$anchorKey] ?? []) : [];
    $anchorValue = (float) ($anchor['value'] ?? 16);
    $anchorPos = (int) ($anchor['position'] ?? 0);

    foreach ($sizes as $key => $data) {
        $pos = (int) ($data['position'] ?? 0);
        $val = scale_value($anchorValue, $ratio, $pos, $anchorPos);
        $val = $round ? round($val) : round($val, 1);
        $lines[] = "    --{$keyPrefix}{$key}: {$val}px;";
    }

    if ($alias !== null && $anchorKey !== null && isset($sizes[$anchorKey])) {
        $lines[] = "    --{$alias}: var(--{$keyPrefix}{$anchorKey});";
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
// Output buffer — collect all lines, then write
// ============================================================================

$rootVars = [];
$colorwayBlocks = [];

// ============================================================================
// Spacing (open scale family: --space-{k} + bare --space alias)
// ============================================================================

$rootVars[] = '    /* Spacing */';
foreach (emit_scale_family($tokens['spacing'] ?? [], 'space-', 'space', true) as $line) {
    $rootVars[] = $line;
}

// ============================================================================
// Text sizes (open scale family: --text-{k} + bare --text alias)
// ============================================================================

$rootVars[] = '';
$rootVars[] = '    /* Text */';
foreach (emit_scale_family($tokens['typography']['text'] ?? [], 'text-', 'text', false) as $line) {
    $rootVars[] = $line;
}

// ============================================================================
// Heading sizes (symmetric open set: h{n} emits --h{n}, position orders the
// math — ADR 0027 amends 0024). No bare alias: h1–h6 are guaranteed present.
// Per-level line-height/letter-spacing/weight are migrated in defaults.json as
// the (inactive) custom-store seed but not emitted until M2 (ADR 0022).
// ============================================================================

$rootVars[] = '';
$rootVars[] = '    /* Headings */';
foreach (emit_scale_family($tokens['typography']['headings'] ?? [], '', null, true) as $line) {
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
// Colors + hue variants (unchanged in M1 — the color shape change lands in M3)
// ============================================================================

$colorSections = $tokens['color']['sections'] ?? [];
$hues = $tokens['color']['hues'] ?? [];

$rootVars[] = '';
$rootVars[] = '    /* Colors */';

// Collect all enabled colors
$enabledColors = [];
foreach ($colorSections as $sectionId => $section) {
    foreach (($section['colors'] ?? []) as $name => $colorData) {
        if (!empty($colorData['enabled']) && isset($colorData['color'])) {
            $enabledColors[$name] = $colorData['color'];
        }
    }
}

foreach ($enabledColors as $name => $hex) {
    $rootVars[] = "    --{$name}: {$hex};";
    $shifts = color_interaction_shifts($hex);
    $rootVars[] = "    --{$name}-hover: {$shifts['hover']};";
    $rootVars[] = "    --{$name}-active: {$shifts['active']};";

    // Generate hue variants + their hover/active
    foreach ($hues as $hueName => $hueData) {
        if (!isset($hueData['value'])) continue;
        if (isset($hueData['enabled']) && !$hueData['enabled']) continue;
        $shade = color_shade($hex, $hueData['value']);
        $rootVars[] = "    --{$name}-{$hueName}: {$shade};";
        $shadeShifts = color_interaction_shifts($shade);
        $rootVars[] = "    --{$name}-{$hueName}-hover: {$shadeShifts['hover']};";
        $rootVars[] = "    --{$name}-{$hueName}-active: {$shadeShifts['active']};";
    }
}

// ============================================================================
// Colorways (unchanged in M1 — renamed/remapped to the palette break in M3)
// ============================================================================

$colorways = $tokens['color']['colorways'] ?? [];

// Auto-generate colorways for enabled semantic colors
$semanticColors = $colorSections['semantic']['colors'] ?? [];
foreach ($semanticColors as $name => $colorData) {
    if (!empty($colorData['enabled']) && !isset($colorways[$name])) {
        $colorways[$name] = [
            'base' => "var(--{$name}-ultra-light)",
            'hard-contrast' => "var(--{$name}-dark)",
            'contrast' => "var(--{$name})",
            'soft-contrast' => "var(--{$name}-light)",
            'accent' => "var(--{$name})",
        ];
    }
}

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
