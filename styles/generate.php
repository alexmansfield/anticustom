<?php
/**
 * CSS Variable Generator
 *
 * Reads defaults.json (or a custom token file) and outputs a complete CSS
 * file with :root variables and [data-palette] / [data-intent] blocks.
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
// Spec loading (ADR 0023 — the spec is a file; the site declares which it follows)
//
// A site's `spec` key names a published spec in styles/specs/. A spec lists the
// guaranteed token keys (the emitted `--{key}` set) and their default labels;
// it carries names, never values (ADR 0027 key-identity). Emission stays
// presence-is-membership — the generator does NOT gate on the spec — but these
// helpers expose the guaranteed set so verify (conformance) and the editor can
// read one source of truth instead of a hardcoded list.
// ============================================================================

function spec_dir(): string {
    return __DIR__ . '/specs';
}

/** Load a spec by name ("base" → styles/specs/base.json). Null if absent/invalid. */
function load_spec(string $name, ?string $dir = null): ?array {
    $path = ($dir ?? spec_dir()) . '/' . $name . '.json';
    if (!is_file($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

/** The guaranteed token keys of a site's followed spec (the `--{key}` set). Empty if none. */
function spec_token_keys(array $tokens, ?string $dir = null): array {
    $name = $tokens['spec'] ?? null;
    if ($name === null) return [];
    $spec = load_spec($name, $dir);
    return $spec ? array_keys($spec['tokens'] ?? []) : [];
}

/**
 * The mechanical extends rule (ADR 0023). At publish time a successor that
 * retains *every* token of the seed it evolved from **extends** it — skins
 * following the seed stay guaranteed under the successor. Returns the stamp
 * "{name}@{version}" when the seed is fully retained, or null when any promised
 * token was dropped (a break: the successor makes no promise to the seed's skins).
 * Immutability, not this check, is what makes a published version trustworthy;
 * this only computes the inheritance edge.
 */
function spec_extends_stamp(array $draftTokens, ?array $seed): ?string {
    if ($seed === null) return null;
    foreach (array_keys($seed['tokens'] ?? []) as $k) {
        if (!array_key_exists($k, $draftTokens)) return null;   // dropped a promised token
    }
    return ($seed['name'] ?? '') . '@' . ($seed['version'] ?? '');
}

// ============================================================================
// OKLCH working space (ADR 0025 ramp math; #29 / docs/research/oklch-generation.md)
//
// sRGB is converted to OKLCH for ramp generation because OKLab lightness is
// perceptually uniform — a fixed ΔL is a visually even step everywhere on the
// ramp, and holding chroma+hue while moving L produces no hue drift (the
// failure mode HSL shading has near saturated/extreme shades). OKLCH stays an
// *internal* working space: every value emitted is still 6-digit sRGB hex,
// gamut-mapped at generation time (browsers only channel-clip, which distorts
// hue), so the output contract is identical to the old HSL path.
//
// Matrices are Ottosson's 2021-01-25 set (linear sRGB ↔ OKLab, direct, no XYZ
// leg) at full published precision; the inverse LMS→linear matrix is Ottosson's
// published reference inverse. Copied verbatim — do not retype truncated.
// ============================================================================

/** Sign-preserving cube root (LMS can go negative mid-gamut-map). */
function cbrt_signed(float $x): float {
    return $x < 0 ? -pow(-$x, 1 / 3) : pow($x, 1 / 3);
}

/** sRGB transfer function, per channel in [0,1] (sign-preserving). */
function srgb_decode(float $v): float {
    $s = $v < 0 ? -1 : 1;
    $v = abs($v);
    return $s * ($v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4));
}
function srgb_encode(float $v): float {
    $s = $v < 0 ? -1 : 1;
    $v = abs($v);
    return $s * ($v > 0.0031308 ? 1.055 * pow($v, 1 / 2.4) - 0.055 : 12.92 * $v);
}

/** Linear sRGB → OKLab (M1, cube-root, M2). */
function linear_to_oklab(float $r, float $g, float $b): array {
    $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
    $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
    $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;

    $l_ = cbrt_signed($l);
    $m_ = cbrt_signed($m);
    $s_ = cbrt_signed($s);

    return [
        0.2104542553 * $l_ + 0.7936177850 * $m_ - 0.0040720468 * $s_,
        1.9779984951 * $l_ - 2.4285922050 * $m_ + 0.4505937099 * $s_,
        0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.8086757660 * $s_,
    ];
}

/** OKLCH (L 0–1, C, H°) → linear sRGB (may fall outside [0,1] — out of gamut). */
function oklch_to_linear(float $L, float $C, float $H): array {
    $hr = deg2rad($H);
    $a = $C * cos($hr);
    $b = $C * sin($hr);

    // OKLab → LMS' (inverse M2)
    $l_ = $L + 0.3963377773761749 * $a + 0.2158037573099136 * $b;
    $m_ = $L - 0.1055613458156586 * $a - 0.0638541728258133 * $b;
    $s_ = $L - 0.0894841775298119 * $a - 1.2914855480194092 * $b;

    // cube, then LMS → linear sRGB (inverse M1)
    $l = $l_ ** 3;
    $m = $m_ ** 3;
    $s = $s_ ** 3;

    return [
         4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
        -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
        -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
    ];
}

/** hex → OKLCH [L 0–1, C, H°]. */
function hex_to_oklch(string $hex): array {
    $hex = ltrim($hex, '#');
    $r = srgb_decode(hexdec(substr($hex, 0, 2)) / 255);
    $g = srgb_decode(hexdec(substr($hex, 2, 2)) / 255);
    $b = srgb_decode(hexdec(substr($hex, 4, 2)) / 255);

    [$L, $a, $bb] = linear_to_oklab($r, $g, $b);
    $C = sqrt($a * $a + $bb * $bb);
    $H = rad2deg(atan2($bb, $a));
    if ($H < 0) $H += 360;

    return [$L, $C, $H];
}

/** True when an OKLCH triple lands inside the sRGB cube (small tolerance). */
function oklch_in_gamut(float $L, float $C, float $H): bool {
    foreach (oklch_to_linear($L, $C, $H) as $c) {
        if ($c < -0.0001 || $c > 1.0001) return false;
    }
    return true;
}

/** Channel-clip an OKLCH triple into sRGB and format as 6-digit hex. */
function oklch_clip_to_hex(float $L, float $C, float $H): string {
    $enc = [];
    foreach (oklch_to_linear($L, $C, $H) as $c) {
        $enc[] = max(0.0, min(1.0, srgb_encode($c)));
    }
    return sprintf('#%02x%02x%02x',
        (int) round($enc[0] * 255),
        (int) round($enc[1] * 255),
        (int) round($enc[2] * 255)
    );
}

/**
 * OKLCH → in-gamut sRGB hex via the CSS Color 4 gamut-mapping algorithm
 * (binary-search chroma with local MINDE, JND 0.02 / ε 0.0001 — the constants
 * both colorjs.io and culori ship as their spec default). L and H are preserved
 * exactly; only chroma is reduced, and only until the channel-clipped candidate
 * is within a just-noticeable deltaEOK of the search point.
 */
function oklch_to_hex(float $L, float $C, float $H): string {
    if ($L >= 1.0) return '#ffffff';
    if ($L <= 0.0) return '#000000';
    if (oklch_in_gamut($L, $C, $H)) return oklch_clip_to_hex($L, $C, $H);

    $JND = 0.02;
    $EPS = 0.0001;
    $hr = deg2rad($H);

    $min = 0.0;
    $max = $C;
    $minInGamut = true;
    $chroma = $C;

    while (($max - $min) > $EPS) {
        $chroma = ($min + $max) / 2.0;

        if ($minInGamut && oklch_in_gamut($L, $chroma, $H)) {
            $min = $chroma;
            continue;
        }

        // deltaEOK between the clipped candidate and the (out-of-gamut) search point
        $clippedLin = oklch_to_linear($L, $chroma, $H);
        $clipped = [];
        foreach ($clippedLin as $c) {
            $clipped[] = srgb_decode(max(0.0, min(1.0, srgb_encode($c))));
        }
        [$cl, $ca, $cb] = linear_to_oklab($clipped[0], $clipped[1], $clipped[2]);
        $E = sqrt(
            ($cl - $L) ** 2
            + ($ca - $chroma * cos($hr)) ** 2
            + ($cb - $chroma * sin($hr)) ** 2
        );

        if ($E < $JND) {
            if (($JND - $E) < $EPS) return oklch_clip_to_hex($L, $chroma, $H);
            $minInGamut = false;
            $min = $chroma;
        } else {
            $max = $chroma;
        }
    }

    return oklch_clip_to_hex($L, $chroma, $H);
}

/**
 * Given a base hex color and a target lightness (0–100), return a new hex color
 * holding the source's OKLCH chroma and hue but at the target lightness,
 * gamut-mapped into sRGB. The 0–100 stop value maps to OKLCH L by /100.
 */
function color_shade(string $hex, float $targetLightness): string {
    [, $C, $H] = hex_to_oklch($hex);
    return oklch_to_hex($targetLightness / 100.0, $C, $H);
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

    $resolved = resolve_scale_sizes($family, $label);
    foreach (array_keys($sizes) as $key) {
        if (!isset($resolved[$key])) continue;
        [$mob, $desk] = $resolved[$key];
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
function resolve_ramp(array $colors, array $stops): array {
    $map = [];
    foreach ($colors as $name => $data) {
        $hex = $data['value'] ?? null;
        if ($hex === null) continue;

        $map[$name] = $hex;

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

            $map["{$name}-{$stopName}"] = $val;
        }
    }
    return $map;
}

function emit_ramp(array $colors, array $stops): array {
    $map = resolve_ramp($colors, $stops);
    $lines = [];
    foreach ($colors as $name => $data) {
        if (!isset($map[$name])) continue;
        $lines[] = "    --{$name}: {$map[$name]};";
        foreach ($stops as $stopName => $_) {
            if (isset($map["{$name}-{$stopName}"])) {
                $lines[] = "    --{$name}-{$stopName}: {$map["{$name}-{$stopName}"]};";
            }
        }
    }
    return $lines;
}

// ============================================================================
// Palette-tier emission (ADR 0015 / 0016 / 0020 / 0026)
//
// A palette is a data-driven set of slots: the surface-anchored contrast scale
// plus hued intents. The generator does not hardcode a slot list — it iterates
// each palette's own keys (ADR 0015). A key `X-on` / `X-hover` / `X-active`
// whose prefix `X` is also a key is an *attribute* of slot X (an authored `-on`,
// or a hand-authored state override per the ADR 0012 hybrid hatch); every other
// key is a fill slot. Interaction states are generator-authored `color-mix()`
// off the resolved role color (ADR 0026) unless the data overrides them.
// ============================================================================

const STATE_MIX = ['hover' => 12, 'active' => 20];

/** The fill slots of a palette (keys that are not an -on/-hover/-active of another key). */
function palette_slots(array $palette): array {
    $slots = [];
    foreach ($palette as $key => $_) {
        if (preg_match('/^(.*)-(on|hover|active)$/', $key, $m) && isset($palette[$m[1]])) {
            continue;
        }
        $slots[] = $key;
    }
    return $slots;
}

/** Resolve a palette value (`#hex` or `var(--rampKey)`) to a hex, or null. */
function resolve_palette_hex(string $value, array $rampMap): ?string {
    $value = trim($value);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) return $value;
    if (preg_match('/^var\(\s*--([a-z0-9-]+)\s*\)$/i', $value, $m)) return $rampMap[$m[1]] ?? null;
    return null;
}

/**
 * Pick the color-mix pole for a slot's interaction state (ADR 0026, v1 rule):
 * shift outward by lightness — a light fill (resolved OKLCH L > 0.5) mixes
 * toward white, a dark fill toward black. Unresolvable values default to black.
 * (Chroma-floor reversal is deferred to the state-derivation effort.)
 */
function palette_state_pole(string $value, array $rampMap): string {
    $hex = resolve_palette_hex($value, $rampMap);
    if ($hex === null) return 'black';
    [$L] = hex_to_oklch($hex);
    return $L > 0.5 ? 'white' : 'black';
}

/**
 * Emit one palette block, sparse (ADR 0015): only the palette's *own* slots
 * emit — a `[data-palette]` region omitting a slot inherits it from the default
 * palette through the CSS cascade (and, for a slot no palette defines, the
 * component's `var()` fallback catches it — the mandatory contract enforced by
 * the M3.7 guard). `-on` emits when authored; `-hover`/`-active` emit a data
 * override if present, else a generator color-mix (ADR 0026).
 */
function emit_palette_block(array $palette, array $rampMap): array {
    $lines = [];
    foreach (palette_slots($palette) as $slot) {
        $value = $palette[$slot];

        $lines[] = "    --palette-{$slot}: {$value};";

        if (isset($palette["{$slot}-on"])) {
            $lines[] = "    --palette-{$slot}-on: {$palette["{$slot}-on"]};";
        }

        foreach (STATE_MIX as $state => $pct) {
            if (isset($palette["{$slot}-{$state}"])) {
                $lines[] = "    --palette-{$slot}-{$state}: {$palette["{$slot}-{$state}"]};";
            } else {
                $pole = palette_state_pole($value, $rampMap);
                $lines[] = "    --palette-{$slot}-{$state}: color-mix(in srgb, var(--palette-{$slot}), {$pole} {$pct}%);";
            }
        }
    }
    return $lines;
}

/**
 * Emit one `[data-intent="X"]` binding rule per intent (a slot with an authored
 * `-on` sibling — the partner key is the signal that a slot is a fill, ADR 0020).
 * The rule maps the generic `--intent*` names an element like a badge references
 * to the surrounding palette's role tokens, so an intent stays inside whatever
 * palette contains it (ADR 0016).
 */
function emit_intent_bindings(array $default): array {
    $lines = [];
    foreach (palette_slots($default) as $slot) {
        if (!isset($default["{$slot}-on"])) continue;
        $lines[] = "[data-intent=\"{$slot}\"] {";
        $lines[] = "    --intent: var(--palette-{$slot});";
        $lines[] = "    --intent-on: var(--palette-{$slot}-on);";
        $lines[] = "    --intent-hover: var(--palette-{$slot}-hover);";
        $lines[] = "    --intent-active: var(--palette-{$slot}-active);";
        $lines[] = '}';
        $lines[] = '';
    }
    return $lines;
}

// ============================================================================
// Library mode: when this file is `require`d (by a test/verify harness) rather
// than run directly, stop here — expose the pure helper functions above without
// the emission side effects below. `get_included_files()[0]` is always the
// entry script, so a mismatch means we were included, not run.
//
// Exception: the explorer includes this file to capture its output (see
// explorer/shared/css.php) and deliberately sets $argv[0] = 'generate.php'
// to request emission. Honor that spoof so server-side token CSS still works;
// verify.php's bare `require` leaves $argv[0] = 'verify.php' and stays silent.
// ============================================================================

$anti_is_entry = realpath(get_included_files()[0] ?? '') === realpath(__FILE__);
$anti_emit_requested = basename($GLOBALS['argv'][0] ?? '') === 'generate.php';
if (!$anti_is_entry && !$anti_emit_requested) {
    return;
}

// ============================================================================
// Output buffer — collect all lines, then write
// ============================================================================

$rootVars = [];
$paletteBlocks = [];

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
// Ramp tier (ADR 0025/0019/0026): flat source colors × the stop scale, dense
// (presence-is-membership, no `enabled`), key-identity `--{color}-{stop}`, no
// interaction states — those emit only at the palette tier (ADR 0026, below).
// ============================================================================

$rampColors = $tokens['color']['colors'] ?? [];
$rampStops = $tokens['color']['stops'] ?? [];
$rampMap = resolve_ramp($rampColors, $rampStops);   // for palette state-pole resolution

$rootVars[] = '';
$rootVars[] = '    /* Colors (ramp tier) */';
foreach (emit_ramp($rampColors, $rampStops) as $line) {
    $rootVars[] = $line;
}

// ============================================================================
// Palette tier (ADR 0015/0016/0020/0026): data-driven surface-anchored contrast
// scale + hued intents. The default palette lands at :root; others at
// [data-palette="X"]. Interaction states are palette-tier color-mix (ADR 0026);
// intents also emit a [data-intent="X"] binding rule. Dense for now — M3.5/3.6
// add component fallbacks then flip to sparse.
// ============================================================================

$palettes = $tokens['color']['palettes'] ?? [];
$defaultPalette = $palettes['default'] ?? [];

foreach ($palettes as $paletteName => $paletteData) {
    $lines = emit_palette_block($paletteData, $rampMap);
    if (empty($lines)) continue;

    $selector = ($paletteName === 'default') ? ':root' : "[data-palette=\"{$paletteName}\"]";
    $paletteBlocks[] = "{$selector} {";
    foreach ($lines as $line) {
        $paletteBlocks[] = $line;
    }
    $paletteBlocks[] = '}';
    $paletteBlocks[] = '';
}

// One binding rule per intent, so an element carrying data-intent picks up the
// surrounding palette's role tokens through the generic --intent* names.
foreach (emit_intent_bindings($defaultPalette) as $line) {
    $paletteBlocks[] = $line;
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

if (!empty($paletteBlocks)) {
    $output .= "\n/* Palettes */\n\n";
    $output .= implode("\n", $paletteBlocks) . "\n";
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
