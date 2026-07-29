# PHP WCAG contrast matrix: recovering the brand-guidelines precursor

Resolves ticket [#31](https://github.com/alexmansfield/anticustom/issues/31)
(part of map #26). Question raised by dossier
[#25](https://github.com/alexmansfield/anticustom/issues/25) item 6: the
brand-guidelines WordPress plugin computed WCAG pair contrast browser-free
(server-side PHP), making it the candidate reference for the contrast matrix
that the palette model's auto-`-on` luminance pick
([color-system-spec.md §2c](../color-system-spec.md)) depends on.

## 1. The precursor: found

**Repo: [`alexmansfield/brand-guidelines`](https://github.com/alexmansfield/brand-guidelines)**
(private GitHub, last pushed 2025-04-12). Plugin header "Brand Guidelines";
`brand-guidelines.php` + `inc/wcag2-color-contrast.php` +
`inc/color-convert.php`. It renders an "Accessibility Reference" — HTML
contrast tables over the site's Automatic.css palette
(`get_option('automatic_css_settings')`) — entirely server-side, no browser
APIs. All findings below are read from that source.

Local copies (verified byte-identical to the GitHub `wcag2-color-contrast.php`):
`~/Documents/brand-guidelines` (git checkout) and
`~/Local Sites/sandbox/app/public/wp-content/plugins/brand-guidelines`
(installed in the Local-by-Flywheel sandbox site).

### 1a. Algorithm

`inc/wcag2-color-contrast.php` is a lift of
[gdkraus/wcag2-color-contrast](https://github.com/gdkraus/wcag2-color-contrast)
(credited in a comment) and implements **WCAG 2.0 relative luminance + contrast
ratio exactly** — no APCA, no shortcuts:

- `calculateLuminosity($color)` — takes bare `RRGGBB` hex, normalizes each
  channel by 255, linearizes piecewise with threshold **0.03928** (the
  pre-May-2021 WCAG constant — see §2a; no practical effect), combines with
  `0.2126/0.7152/0.0722`.
- `calculateLuminosityRatio($c1, $c2)` — orders by luminance and returns
  `($lighter + 0.05) / ($darker + 0.05)`.

### 1b. API shape

- **Inputs:** bare 6-digit hex strings (`#` stripped by callers). No 3-digit
  hex, no validation, no alpha, sRGB assumed.
- **Pair evaluation:** `evaluateColorContrast($c1, $c2)` returns an
  associative array: `ratio` (float, unrounded) plus pass/fail strings for
  `levelAANormal` (≥ 4.5), `levelAALarge` / `levelAAMediumBold` (≥ 3),
  `levelAAANormal` (≥ 7), `levelAAALarge` / `levelAAAMediumBold` (≥ 4.5) —
  the full WCAG 2 threshold set, correctly applied to the *unrounded* ratio.
- **Matrix:** `contrast_table($colors, $color_variations)` — rows are all
  palette variations, columns the main colors; each cell
  (`contrast_td()`) shows the ratio rounded to 1 decimal for display plus a
  badge: `AAA` / `AA` / `AA18` (warning, large-text-only pass) / `FAIL`.
  Output is an HTML `<table>` string; there is **no pure data-structure
  matrix** — computation and rendering are fused.
- **Proto-`-on` pick:** cells and headers get an `invert` class (flip label to
  white) when the cell background's contrast against black falls below a magic
  cutoff — `< 3` in `contrast_table()`, `< 5` in `contrast_td()`. This is a
  single-candidate threshold heuristic with two inconsistent constants, not
  the spec §2c argmax-of-two-candidates rule.

### 1c. Gaps observed (relevant to anticustom's generator)

- **No alpha handling** — 6-digit hex only; a translucent color can't be
  expressed or composited.
- **sRGB only** — no other color-space input; palette variations are derived
  via integer-truncating HSL→RGB conversion
  (`inc/color-convert.php`, from SimonWaldherr/ColorConverter.php), which can
  be off by a rounding step from the CSS the site actually serves.
- **Old linearization threshold** (0.03928) — cosmetic; W3C says the change
  to 0.04045 has no practical effect (§2a).
- **HTML-fused output** — the matrix can't be reused as data (e.g. by an
  auto-`-on` picker or the explorer) without re-running string parsing.
- **Threshold duplication** — the 3/4.5/7 constants live in
  `evaluateColorContrast()` but ad-hoc `< 3` / `< 5` reappear inline for the
  invert heuristic.

## 2. The algorithm, from primary sources (WCAG 2.2)

The generator needs exactly two formulas, both normatively defined in WCAG 2.2.

### 2a. Relative luminance

Per the WCAG 2.2 glossary ([Relative luminance][rl]):

> the relative brightness of any point in a colorspace, normalized to 0 for
> darkest black and 1 for lightest white

For sRGB, with each 8-bit channel first normalized to `RsRGB = R8bit / 255`
(same for G, B), each channel is linearized piecewise:

```
if RsRGB <= 0.04045:  R = RsRGB / 12.92
else:                 R = ((RsRGB + 0.055) / 1.055) ^ 2.4
```

then combined:

```
L = 0.2126 * R + 0.7152 * G + 0.0722 * B
```

**Threshold note (0.04045 vs 0.03928):** before May 2021 WCAG used 0.03928,
taken from an older draft of the sRGB spec. The definition now reads 0.04045
and W3C states the change "has no practical effect on the calculations in the
context of these guidelines" — no 8-bit channel value falls between the two
cutoffs. Use 0.04045; either matches every published contrast checker to the
displayed precision. ([Source][rl])

### 2b. Contrast ratio

Per WCAG 2.2 ([SC 1.4.3][sc143] and the contrast-ratio glossary entry):

```
CR = (L1 + 0.05) / (L2 + 0.05)
```

where `L1` is the lighter color's relative luminance and `L2` the darker's.
Range: 1:1 to 21:1. The ratio is symmetric (order the operands by luminance),
so a pair matrix is symmetric and only the upper triangle need be computed.

### 2c. Thresholds (WCAG 2.2 success criteria)

| Criterion | Level | Threshold |
|---|---|---|
| [1.4.3 Contrast (Minimum)][sc143] | AA | text ≥ **4.5:1**; large text ≥ **3:1** |
| [1.4.6 Contrast (Enhanced)][sc146] | AAA | text ≥ **7:1**; large text ≥ **4.5:1** |
| [1.4.11 Non-text Contrast][sc1411] | AA | UI components / graphical objects ≥ **3:1** |

"Large scale" text is at least 18 point, or 14 point bold (or CJK equivalent).
([WCAG 2.2 glossary][wcag22])

### 2d. Reference PHP implementation shape

Slotting into `styles/generate.php` (which already has `hex_to_hsl()` /
`hsl_to_hex()` and takes 6-digit `#rrggbb` input throughout):

```php
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
```

The auto-`-on` pick (§2c of the spec) is then a two-candidate argmax:

```php
function anti_pick_on(string $fill, string $candA, string $candB): string {
    return anti_contrast_ratio($fill, $candA) >= anti_contrast_ratio($fill, $candB)
        ? $candA : $candB;
}
```

and a full matrix over a palette of N hexes is the symmetric N×N table of
`anti_contrast_ratio` values with pass/fail flags at 3.0 / 4.5 / 7.0.
Comparison against thresholds should use the unrounded ratio (round only for
display); WCAG conformance is defined on the ratio itself, not a rounded form.

## 3. Gaps to handle deliberately in anticustom's generator

- **Alpha / transparency.** WCAG contrast is defined between two opaque
  colors. A translucent foreground must be alpha-composited onto its backdrop
  (`c = a * fg + (1 - a) * bg`, per channel, in gamma-encoded sRGB, as browsers
  composite) *before* computing luminance — and the result then depends on
  what's behind it. Recommendation: keep the token pipeline opaque-only
  (6-digit hex), which is already `generate.php`'s input contract; if 8-digit
  hex ever enters, composite over `surface` first and document that assumption.
- **Non-sRGB input.** The WCAG formulas are defined for sRGB only. Dossier
  item 5 adopts **OKLCH as the working space for ramp generation** — that is
  compatible, but the contrast step must convert OKLCH → sRGB (gamut-mapped)
  first and measure the sRGB result, i.e. measure what actually ships in the
  CSS. Never feed OKLCH's own L into the WCAG formula; OKLab lightness is not
  WCAG relative luminance.
- **Near-threshold pairs.** Auto-`-on` picks the *better* of two candidates,
  which can still be a failing pair (e.g. a mid-tone orange fill where neither
  near-white nor near-black reaches 4.5:1). The matrix should therefore report
  the ratio, not just the winner, so the explorer/editor can surface "best
  available is 3.8:1 — override or adjust the fill."

## 4. Considered and deferred: APCA / WCAG 3

APCA (Accessible Perceptual Contrast Algorithm) is the candidate contrast
model for WCAG 3. It outputs **Lc values** (approx. −108…106) instead of
ratios, is **polarity-sensitive** (dark-on-light scores differently from
light-on-dark), and models perceptual effects the WCAG 2 ratio ignores.
Per its own repository it is a **work in progress / beta** aimed at "emerging
web standards," not current normative conformance ([SAPC-APCA][apca]); WCAG 3
remains a W3C draft. **Deferred:** anticustom targets WCAG 2.2, the only
version with legal/regulatory standing, and the 2.2 formulas are ~15 lines of
dependency-free PHP. Revisit if/when WCAG 3 reaches Candidate Recommendation.
The `anti_contrast_ratio()` seam keeps the scoring function swappable.

## 5. Recommendation

**Carry over the math, redo the shape.** The precursor proves the core point:
WCAG 2 pair contrast is trivially computable browser-free in PHP, and its
formula code (via gdkraus) is correct. But it should be a *reference*, not a
dependency — reimplement in `styles/generate.php`'s helper section (~20 lines,
§2d; verified against WebAIM-published values: `#777777`/white = 4.4781,
`#767676`/white = 4.5422, white/black = 21.0):

**Carry over:**
- The two pure functions' logic (luminance + ratio), updated to the 0.04045
  threshold.
- Evaluating thresholds against the unrounded ratio; rounding only for display.
- The badge ladder (AAA / AA / AA-large / FAIL) as the display vocabulary for
  the explorer's eventual contrast table.

**Redo:**
- **Matrix as data, not HTML.** Return a symmetric array
  `[$nameA][$nameB] => ['ratio' => float, 'passes' => [...]]`; rendering is a
  separate concern (explorer/antisloth can consume it).
- **`-on` pick as argmax, not threshold.** Replace the `< 3` / `< 5`
  invert heuristic with the spec §2c rule: compute both candidate ratios,
  take the max, and *keep the losing/winning ratios in the output* so
  sub-4.5:1 best-picks are surfaceable.
- **Measure the shipped color.** With OKLCH ramp generation (dossier item 5),
  convert to the final sRGB hex first, then measure — never OKLab L.
- Single source of truth for the 3 / 4.5 / 7 constants.

**Defer:** APCA/WCAG 3 (§4) and alpha compositing (keep the pipeline
opaque-hex; revisit only if 8-digit hex enters the token schema).

## Sources

- [`alexmansfield/brand-guidelines`](https://github.com/alexmansfield/brand-guidelines)
  (private) — `inc/wcag2-color-contrast.php`, `inc/color-convert.php`,
  `brand-guidelines.php` (read via GitHub API, 2026-07-29)
- [gdkraus/wcag2-color-contrast](https://github.com/gdkraus/wcag2-color-contrast)
  — upstream of the precursor's formula file (credited in its header comment)
- [WCAG 2.2, W3C Recommendation — relative luminance definition][rl]
  (formulas, 0.04045 threshold, May 2021 note)
- [WCAG 2.2 SC 1.4.3 Contrast (Minimum)][sc143],
  [SC 1.4.6 Contrast (Enhanced)][sc146],
  [SC 1.4.11 Non-text Contrast][sc1411] (thresholds, contrast-ratio formula)
- [Myndex SAPC-APCA repository][apca] (APCA status and output model)
- [docs/color-system-spec.md](../color-system-spec.md) §2c (auto-`-on` rule
  this matrix serves)
- Dossier [#25](https://github.com/alexmansfield/anticustom/issues/25)
  items 5–6

[rl]: https://www.w3.org/TR/WCAG22/relative-luminance.html
[wcag22]: https://www.w3.org/TR/WCAG22/
[sc143]: https://www.w3.org/TR/WCAG22/#contrast-minimum
[sc146]: https://www.w3.org/TR/WCAG22/#contrast-enhanced
[sc1411]: https://www.w3.org/TR/WCAG22/#non-text-contrast
[apca]: https://github.com/Myndex/SAPC-APCA
