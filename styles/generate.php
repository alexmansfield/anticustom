<?php
/**
 * CSS Variable Generator
 *
 * Reads defaults.json (or a custom token file) and outputs a complete CSS
 * file with :root variables and [data-colorway] blocks.
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
// Scale calculation helper
// ============================================================================

/**
 * Calculate a scale value: base * scale^position.
 * Rounds to 1 decimal place for clean output.
 */
function scale_value(float $base, float $scale, int $position): float {
    return round($base * pow($scale, $position), 1);
}

// ============================================================================
// Output buffer — collect all lines, then write
// ============================================================================

$rootVars = [];
$colorwayBlocks = [];

// ============================================================================
// Spacing
// ============================================================================

$spacing = $tokens['spacing'] ?? [];
$spaceBase = $spacing['baseSize'] ?? 16;
$spaceScale = $spacing['scale'] ?? 1.5;
$spacePositions = ['xxs' => -3, 'xs' => -2, 's' => -1, 'm' => 0, 'l' => 1, 'xl' => 2, 'xxl' => 3];

$rootVars[] = '    /* Spacing */';
foreach ($spacePositions as $size => $pos) {
    $sizeData = $spacing['sizes'][$size] ?? [];
    if (!empty($sizeData['override']) && isset($sizeData['value'])) {
        $val = $sizeData['value'];
    } else {
        $val = round(scale_value($spaceBase, $spaceScale, $pos));
    }
    $rootVars[] = "    --space-{$size}: {$val}px;";
}

// ============================================================================
// Text sizes
// ============================================================================

$text = $tokens['typography']['text'] ?? [];
$textBase = $text['baseSize'] ?? 16;
$textScale = $text['scale'] ?? 1.125;
$textPositions = ['xs' => -2, 's' => -1, 'm' => 0, 'l' => 1, 'xl' => 2];

$rootVars[] = '';
$rootVars[] = '    /* Text */';
foreach ($textPositions as $size => $pos) {
    $sizeData = $text['sizes'][$size] ?? [];
    if (!empty($sizeData['override']) && isset($sizeData['value'])) {
        $val = $sizeData['value'];
    } else {
        $val = scale_value($textBase, $textScale, $pos);
    }
    $rootVars[] = "    --text-{$size}: {$val}px;";

    // Optional sub-properties from overrides
    if (!empty($sizeData['override'])) {
        if (isset($sizeData['lineHeight'])) {
            $rootVars[] = "    --text-{$size}-line-height: {$sizeData['lineHeight']};";
        }
        if (isset($sizeData['weight']) && $sizeData['weight'] !== 400) {
            $rootVars[] = "    --text-{$size}-weight: {$sizeData['weight']};";
        }
    }
}

// ============================================================================
// Heading sizes (inverted: h1 is largest)
// ============================================================================

$headings = $tokens['typography']['headings'] ?? [];
$headingBase = $headings['baseSize'] ?? 16;
$headingScale = $headings['scale'] ?? 1.618;
// h6=0, h5=1, h4=2, h3=3, h2=4, h1=5
$headingPositions = [6 => 0, 5 => 1, 4 => 2, 3 => 3, 2 => 4, 1 => 5];

$rootVars[] = '';
$rootVars[] = '    /* Headings */';
foreach ($headingPositions as $level => $pos) {
    $key = "h{$level}";
    $sizeData = $headings['sizes'][$key] ?? [];
    if (!empty($sizeData['override']) && isset($sizeData['value'])) {
        $val = $sizeData['value'];
    } else {
        $val = round(scale_value($headingBase, $headingScale, $pos));
    }
    $rootVars[] = "    --heading-{$level}: {$val}px;";

    // Sub-properties from overrides
    if (isset($sizeData['lineHeight'])) {
        $rootVars[] = "    --heading-{$level}-line-height: {$sizeData['lineHeight']};";
    }
    if (isset($sizeData['letterSpacing']) && $sizeData['letterSpacing'] != 0) {
        $rootVars[] = "    --heading-{$level}-letter-spacing: {$sizeData['letterSpacing']}em;";
    }
    if (isset($sizeData['weight'])) {
        $rootVars[] = "    --heading-{$level}-weight: {$sizeData['weight']};";
    }
}

// ============================================================================
// Radius
// ============================================================================

$radius = $tokens['radius']['sizes'] ?? [];
$radiusOrder = ['xs', 's', 'm', 'l', 'xl', 'full'];

$rootVars[] = '';
$rootVars[] = '    /* Radius */';
foreach ($radiusOrder as $size) {
    $data = $radius[$size] ?? [];
    if (isset($data['enabled']) && !$data['enabled']) continue;
    $val = $data['value'] ?? null;
    if ($val !== null) {
        $unit = ($size === 'full') ? 'px' : 'px';
        $rootVars[] = "    --radius-{$size}: {$val}px;";
    }
}

// ============================================================================
// Shadows (composite values)
// ============================================================================

$shadows = $tokens['shadows'] ?? [];
$shadowOrder = ['xs', 's', 'm', 'l', 'xl'];

$rootVars[] = '';
$rootVars[] = '    /* Shadows */';
foreach ($shadowOrder as $size) {
    $s = $shadows[$size] ?? null;
    if ($s === null) continue;
    if (isset($s['enabled']) && !$s['enabled']) continue;
    $x = $s['x'] ?? 0;
    $y = $s['y'] ?? 0;
    $blur = $s['blur'] ?? 0;
    $spread = $s['spread'] ?? 0;
    $opacity = $s['opacity'] ?? 0.1;
    $rootVars[] = "    --shadow-{$size}: {$x}px {$y}px {$blur}px {$spread}px rgba(0, 0, 0, {$opacity});";
}

// ============================================================================
// Borders
// ============================================================================

$borders = $tokens['borders']['sizes'] ?? [];
$borderOrder = ['s', 'm', 'l'];

$rootVars[] = '';
$rootVars[] = '    /* Borders */';
foreach ($borderOrder as $size) {
    $data = $borders[$size] ?? [];
    if (isset($data['enabled']) && !$data['enabled']) continue;
    $val = $data['value'] ?? null;
    if ($val !== null) {
        $rootVars[] = "    --border-{$size}: {$val}px;";
    }
}

// ============================================================================
// Font weights
// ============================================================================

$rootVars[] = '';
$rootVars[] = '    /* Font weights */';
$rootVars[] = '    --font-weight-medium: 500;';

// ============================================================================
// Colors + hue variants
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
// Colorways
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

$output .= "\n/* Interface — centralized application of interface properties */\n";
$output .= ".anti-interface {\n";
$output .= "    padding: var(--anti-padding);\n";
$output .= "    border: var(--anti-border-width) solid var(--colorway-soft-contrast, currentColor);\n";
$output .= "    border-radius: var(--anti-border-radius);\n";
$output .= "    box-shadow: var(--anti-shadow);\n";
$output .= "}\n";

if (!empty($colorwayBlocks)) {
    $output .= "\n/* Colorways */\n\n";
    $output .= implode("\n", $colorwayBlocks) . "\n";
}

if ($outputPath) {
    file_put_contents($outputPath, $output);
    fprintf(STDERR, "Written to %s\n", $outputPath);
} else {
    echo $output;
}
