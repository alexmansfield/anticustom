# OKLCH ramp generation in PHP: conversion math, gamut mapping, output format

Research for ticket #29 (part of map #26). OKLCH as the working space is already
decided (color-system-spec.md §6.9, §5); this documents the **how**, from primary
sources: the CSS Color 4 spec and its sample code, Björn Ottosson's OKLab posts,
and the colorjs.io / culori sources.

**Bottom line:** implement direct sRGB ↔ OKLCH conversion in PHP using Ottosson's
2021-updated matrices (~60 lines), gamut-map at generation time with the
CSS Color 4 chroma-bisection algorithm (JND 0.02, deltaEOK), and keep emitting
**sRGB hex** — OKLCH stays an internal working space, exactly as HSL is today.

---

## 1. PHP-side OKLCH ↔ sRGB conversion

### 1a. Pipeline

```
hex ──> sRGB [0,1] ──> linear sRGB ──> LMS ──> cbrt ──> OKLab ──> OKLCH
                (gamma decode)   (M1)              (M2)      (polar)
```

and the exact inverse on the way out (polar→rect, M2⁻¹, cube, M1⁻¹, gamma
encode, ×255, round).

### 1b. sRGB transfer function (gamma)

From the CSS Color 4 sample code (`conversions.js`, verbatim constants)
[[css-conv]](https://drafts.csswg.org/css-color-4/conversions.js):

Decode (`lin_sRGB`), per channel `v` in [0,1]:

- `abs(v) <= 0.04045` → `v / 12.92`
- else → `sign(v) * ((abs(v) + 0.055) / 1.055) ^ 2.4`

Encode (`gam_sRGB`):

- `abs(v) > 0.0031308` → `sign(v) * (1.055 * abs(v)^(1/2.4) − 0.055)`
- else → `12.92 * v`

(The sign-preserving form matters only for out-of-gamut negatives mid-algorithm;
keep it — the gamut mapper below probes out-of-range values.)

### 1c. Matrices

Ottosson defines OKLab **directly from linear sRGB** — two 3×3 matrices with a
cube-root nonlinearity between them
[[ottosson-oklab]](https://bottosson.github.io/posts/oklab/):

```
M1 (linear sRGB → LMS):
  +0.4122214708  +0.5363325363  +0.0514459929
  +0.2119034982  +0.6806995451  +0.1073969566
  +0.0883024619  +0.2817188376  +0.6299787005

l' = cbrt(l),  m' = cbrt(m),  s' = cbrt(s)

M2 (LMS' → OKLab):
  +0.2104542553  +0.7936177850  −0.0040720468
  +1.9779984951  −2.4285922050  +0.4505937099
  +0.0259040371  +0.7827717662  −0.8086757660
```

Ottosson's post notes: *"The matrices were updated 2021-01-25. The new matrices
have been derived using a higher precision sRGB matrix and with exactly matching
D65 values."* Use the post's current values (above), not pre-2021 copies floating
around the web.

The CSS Color 4 sample code routes through **XYZ D65** instead
(`XYZ_to_OKLab` / `OKLab_to_XYZ`, matrices given to 16 decimal places)
[[css-conv]](https://drafts.csswg.org/css-color-4/conversions.js) — because CSS
must convert between many spaces and XYZ is its hub. For a generator that only
ever touches sRGB, the direct Ottosson route is simpler (no XYZ leg), is the
defining source of the color space, and agrees with the XYZ route to well below
8-bit quantization. The inverse OKLab→LMS' matrix from the CSS sample code
(usable directly, first column exactly 1.0):

```
OKLab → LMS':
  1.0   +0.3963377773761749   +0.2158037573099136
  1.0   −0.1055613458156586   −0.0638541728258133
  1.0   −0.0894841775298119   −1.2914855480194092
```

(For M1⁻¹ either invert numerically once and hard-code, or take Ottosson's
published inverse from the post's reference implementation.)

### 1d. OKLab ↔ OKLCH

`C = sqrt(a² + b²)`, `H = atan2(b, a)` in degrees (+360 if negative);
`a = C·cos(H)`, `b = C·sin(H)`
[[ottosson-oklab]](https://bottosson.github.io/posts/oklab/). The CSS sample
code sets hue to `NaN` ("powerless") when `C <= 0.000004`
[[css-conv]](https://drafts.csswg.org/css-color-4/conversions.js) — the same
powerless-hue fact the spec already documents in §3d (why runtime hue recovery
from `#fff` fails). Our generator works *forward* from the source hue, so it
never needs to read hue back out of a near-achromatic stop.

### 1e. Precision

- PHP floats are IEEE-754 doubles — same as the JS reference implementations;
  round-trip error is orders of magnitude below the 1/255 quantization of hex
  output. No arbitrary-precision math needed.
- Copy matrix coefficients at full published precision (10 places from Ottosson,
  16 from CSS sample code); don't retype truncated versions.
- Note the scale change: OKLCH L is **0–1** (HSL L was 0–100). Every lightness
  constant in the generator migrates — `hues[*].value` targets, the
  `HOVER_LOWER_BOUND`/`HOVER_UPPER_BOUND` bands and ±5/±10 shifts in
  `color_interaction_shifts`. OKLab L is perceptually uniform, so the shift
  constants should be *re-tuned by eye*, not just divided by 100 (a fixed ΔL in
  OKLab is a visually even step everywhere on the ramp — that's the point of the
  migration; the old HSL constants were tuned against HSL's non-uniform L).

### 1f. What replaces what in `styles/generate.php`

| Today (HSL) | After (OKLCH) |
| --- | --- |
| `hex_to_hsl()` | `hex_to_oklch()` (decode → M1 → cbrt → M2 → polar) |
| `hsl_to_hex()` + `hue_to_rgb()` | `oklch_to_hex()` (rect → M2⁻¹ → cube → M1⁻¹ → encode), **ending in a gamut-map step** |
| `color_shade($hex, $L)` — same H/S, new L | same shape: hold C and H, set L, gamut-map |
| `color_interaction_shifts()` — L±5/±10 in bands | same band logic on OKLab L (0–1 scale, re-tuned constants) |
| `colorway_derive_state()` hex regex | unchanged — still sees hex in and hex out |

---

## 2. Gamut mapping

Setting L on a fixed (C, H) — which is exactly what `color_shade` does — routinely
leaves sRGB, especially near the L extremes and for saturated hues. Some mapping
step is mandatory; the options:

### Option A — per-channel clip

Clamp each linear-RGB channel to [0,1]. Trivial, but Ottosson's gamut-clipping
post demonstrates it causes **hue distortion and detail loss** ("details in
flowers are gone")
[[ottosson-clip]](https://bottosson.github.io/posts/gamutclipping/); a ramp built
this way visibly bends hue near its saturated stops — defeating the "no hue
drift" goal that motivated OKLCH (spec §3d). Rejected.

### Option B — plain chroma bisection ("keep L and H, shrink C")

Binary-search C between 0 and the original value until the color is in gamut.
This is culori's `clampChroma` (default mode `lch`, resolution `range / 2^13`,
loop `while (end − start > resolution)`)
[[culori]](https://github.com/Evercoder/culori/blob/main/src/clamp.js).
~15 lines, deterministic, preserves L and H exactly. Weakness: near the gamut
edge it can shave more chroma than a human would notice as necessary.

### Option C — CSS Color 4 binary-search with local MINDE (the spec algorithm)

CSS Color 4 §13 ("CSS Gamut Mapping" / binary-search with local MINDE) specifies:
work in OKLCH; return **white** if L ≥ 1 and **black** if L ≤ 0; otherwise
binary-search chroma in [0, C₀], and at each step compare the candidate against
its channel-clipped version using **deltaEOK** (Euclidean distance in OKLab);
if the clipped color is within the just-noticeable difference of the candidate,
accept the clip ("local MINDE"). Constants, verbatim from colorjs.io's
implementation of the spec algorithm (colorjs.io is maintained by the spec's
editors): `JND = 0.02`, `ε = 0.0001`
[[colorjs-togamut]](https://github.com/color-js/color.js/blob/main/src/toGamut.js),
[[css4-gmap]](https://drafts.csswg.org/css-color-4/#css-gamut-mapping).
culori's `toGamut` implements the same thing ("The default arguments … correspond
to the gamut mapping algorithm defined in CSS Color Level 4", `jnd = 0.02`,
`delta = differenceEuclidean('oklch')`)
[[culori]](https://github.com/Evercoder/culori/blob/main/src/clamp.js). This is
Option B plus a cheap early-accept: it keeps a bit more chroma at the boundary
by allowing an imperceptible (≤ JND) clip. deltaEOK is one line
(`sqrt(ΔL² + Δa² + Δb²)`).

### Option D — Ottosson adaptive projection

Ottosson's own recommendation for image work: project toward an adaptive point
on the L axis (`adaptive L₀, α = 0.05`), trading a little lightness for chroma
[[ottosson-clip]](https://bottosson.github.io/posts/gamutclipping/). Best-looking
for photographs, but it moves L — bad for a token generator whose invariants
(spec §4) are stated in terms of L (contrast floor, perceptible ΔL). Not a fit.

### What implementations actually do

- **colorjs.io**: `toGamut({method: "css"})` = Option C; also offers `clip`
  [[colorjs-togamut]](https://github.com/color-js/color.js/blob/main/src/toGamut.js).
- **culori**: `clampChroma` = Option B; `toGamut` = Option C
  [[culori]](https://github.com/Evercoder/culori/blob/main/src/clamp.js).
- **Browsers**: none implement the spec algorithm today — engines **clip**
  out-of-gamut colors at paint time, and `@supports` can't detect this
  [[interop-443]](https://github.com/web-platform-tests/interop/issues/443),
  [[colorjs-gmap-docs]](https://colorjs.io/docs/gamut-mapping). This is a strong
  reason to gamut-map **in the generator**, not to lean on the browser.

### Recommendation

**Option C**, the CSS Color 4 binary-search with local MINDE (JND 0.02,
ε 0.0001). It is only ~25 lines on top of the conversion functions (Option B
plus a three-line deltaEOK early-exit), it is the standard both reference
libraries ship as their default, it preserves L and H exactly (protecting the
§4 invariants), and it makes the PHP generator's output match what
colorjs.io/culori would produce — useful for verification. If during
implementation the MINDE step proves fiddly, plain bisection (Option B) is an
acceptable fallback with slightly more conservative chroma; the difference is at
most ~JND by construction.

---

## 3. Output format: hex vs `oklch()`

Facts:

- `oklch()` is Baseline ("available across browsers since May 2023")
  [[mdn-oklch]](https://developer.mozilla.org/en-US/docs/Web/CSS/color_value/oklch);
  first versions Chrome/Edge 111, Firefox 113, Safari 15.4; ~91.6% global
  support on caniuse [[caniuse]](https://caniuse.com/mdn-css_types_color_oklch).
- Custom properties are value-agnostic — every `var(--…)` consumer in
  `components/*/styles/*.css` works identically with either format.
- If the generator emitted out-of-gamut `oklch()` and left mapping to the
  browser, today's engines would **channel-clip** (Option A, the hue-distorting
  one), and inconsistently as spec work lands
  [[interop-443]](https://github.com/web-platform-tests/interop/issues/443).
- Repo-specific hex dependencies: `colorway_derive_state()` matches raw values
  with `/^#[0-9a-fA-F]{6}$/` (`styles/generate.php:159`); the explorer's Coloris
  picker (`explorer/vendor/coloris.min.js`) only speaks hex/rgb/hsl; token
  inputs in `styles/defaults.json` are hex.
- The only thing `oklch()` output would buy is wide-gamut (P3) stops — which
  this token system doesn't produce, and which would then *need* the browser's
  (currently clip-only) mapping on sRGB displays.

### Recommendation

**Emit hex, exactly as today.** OKLCH becomes the internal working space
(replacing HSL), the gamut mapper guarantees every emitted stop is in sRGB, and
the output contract — 6-digit hex in CSS variables — is unchanged. Zero migration
for consumers: component CSS, `colorway_derive_state`, the Coloris picker, and
`defaults.json` all keep working. Rendering is deterministic and identical across
all browsers including pre-2023 ones. Revisit `oklch()` output only if/when the
system wants P3 ramps; that is an output-layer switch (one `sprintf` site plus
the state-derivation regex), not a rearchitecture, since all math already happens
in OKLCH.

---

## Sources

- [ottosson-oklab] Björn Ottosson, *A perceptual color space for image
  processing* — <https://bottosson.github.io/posts/oklab/> (matrices as updated
  2021-01-25)
- [ottosson-clip] Björn Ottosson, *sRGB gamut clipping* —
  <https://bottosson.github.io/posts/gamutclipping/>
- [css-conv] CSS Color 4 sample conversion code —
  <https://drafts.csswg.org/css-color-4/conversions.js>
- [css4-gmap] CSS Color 4, *CSS Gamut Mapping* (binary search with local MINDE)
  — <https://drafts.csswg.org/css-color-4/#css-gamut-mapping>
- [colorjs-togamut] colorjs.io `src/toGamut.js` (spec editors' implementation;
  JND = 0.02, ε = 0.0001) —
  <https://github.com/color-js/color.js/blob/main/src/toGamut.js>
- [colorjs-gmap-docs] colorjs.io gamut-mapping docs —
  <https://colorjs.io/docs/gamut-mapping>
- [culori] culori `src/clamp.js` (`clampChroma`, `toGamut`) —
  <https://github.com/Evercoder/culori/blob/main/src/clamp.js>
- [mdn-oklch] MDN, `oklch()` —
  <https://developer.mozilla.org/en-US/docs/Web/CSS/color_value/oklch>
- [caniuse] Can I Use, `oklch()` —
  <https://caniuse.com/mdn-css_types_color_oklch>
- [interop-443] web-platform-tests/interop#443, *Gamut mapping* (browsers clip;
  no engine implements the spec algorithm) —
  <https://github.com/web-platform-tests/interop/issues/443>
