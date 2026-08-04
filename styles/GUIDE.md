# Styles Tool Guide

## Purpose

The styles tool defines design tokens in JSON (`defaults.json`) and generates a CSS variables file from them via `generate.php`. Components reference these CSS variables; the token system ensures consistent spacing, typography, colors, and effects across all components.

The size families follow the **palette-model emission model** (ADRs 0015–0028):

- **Open ordered sets** — a family lists its steps in `sizes`; *presence is membership*. There is no `enabled` flag. A step a spec omits simply does not emit.
- **Key-identity naming** — the emitted variable is exactly `--{key}`. The spec author owns the namespace; the generator adds no prefix of its own.
- **Anchor-as-origin** — a scale family's `default` step is its anchor. Every other step is `anchorValue * ratio^(position − anchorPosition)`. There is no separate `baseSize`.
- **Per-device anchors + fluid clamps** (M2, ADR 0018) — each scale family carries a `mode: scale | custom` and *per-device* anchors under `scale.{mobile,desktop}.{value,ratio}`. Every px-length token compiles to a `clamp()` interpolating between the two anchors across a global `viewport` range — no media queries. Equal anchors collapse to a static value.
- **Derived heading typography** (M2, ADR 0022) — scale-mode headings derive `--h{n}-line-height` / `--h{n}-letter-spacing` as `calc()` forms keyed to the computed size (the `em` term tracks the fluid size for free), plus one authored `--h{n}-weight`.
- **Bare aliases** — each family emits a bare alias (`--space`, `--text`, `--border`, `--radius`, `--shadow`) that components fall back to, so an incomplete spec degrades gracefully instead of producing a broken `var()`.

## Quick Start

```bash
# Generate CSS to stdout
php styles/generate.php

# Generate to a file
php styles/generate.php --output dist/tokens.css

# Generate from custom tokens
php styles/generate.php path/to/custom-tokens.json --output dist/tokens.css

# Verify the emission (generator-output seam)
php styles/verify.php
```

## Viewport range (`viewport`)

A single global block sets the two widths every fluid scale interpolates between:

```json
"viewport": { "mobile": 390, "desktop": 1440 }
```

It lives outside the per-axis panels (ADR 0018) and must be **distinct** — equal anchors zero the fluid-slope denominator and are a generate error. Widening `desktop` flattens every clamp's slope at once.

## Token Categories

### Spacing (`--space-{key}` + bare `--space`)

Open scale family. Every key in `sizes` emits `--space-{key}` as a fluid `clamp()`. In `mode: scale`, each step is computed *per device* — `scale.{device}.value * scale.{device}.ratio^(position − anchorPosition)` — then the mobile and desktop results become the clamp bounds (rounded to an integer). At the factory viewport (390 → 1440) and anchors (mobile 12/1.5, desktop 16/1.5):

| Variable | Position | Mobile → Desktop | Emitted | Spec? |
|----------|----------|------------------|---------|-------|
| `--space-xxs` | -3 | 4 → 5px | `clamp(4px, …, 5px)` | custom |
| `--space-xs` | -2 | 5 → 7px | `clamp(5px, …, 7px)` | spec |
| `--space-s` | -1 | 8 → 11px | `clamp(8px, …, 11px)` | spec |
| `--space-m` | 0 (anchor) | 12 → 16px | `clamp(12px, …, 16px)` | spec |
| `--space-l` | +1 | 18 → 24px | `clamp(18px, …, 24px)` | spec |
| `--space-xl` | +2 | 27 → 36px | `clamp(27px, …, 36px)` | spec |
| `--space-xxl` | +3 | 41 → 54px | `clamp(41px, …, 54px)` | custom |
| `--space` | — | — | `var(--space-m)` | alias |

**JSON path:** `spacing.mode`, `spacing.default`, `spacing.scale.{mobile,desktop}.{value,ratio}`, `spacing.sizes.{key}.position`, and (custom mode) `spacing.custom.{key}.{mobile,desktop}`

The `spec` column classifies base-spec tokens vs custom tokens riding the same ramp (ADR 0023). Both emit identically — the classification is guaranteed-key bookkeeping, asserted by `styles/verify.php`.

### Text Sizes (`--text-{key}` + bare `--text`)

Open scale family, same shape as spacing but kept to one decimal place (finer type steps). Factory anchors: mobile 15/1.2, desktop 16/1.2.

| Variable | Position | Mobile → Desktop | Spec? |
|----------|----------|------------------|-------|
| `--text-xs` | -2 | 10.4 → 11.1px | custom |
| `--text-s` | -1 | 12.5 → 13.3px | spec |
| `--text-m` | 0 (anchor) | 15 → 16px | spec |
| `--text-l` | +1 | 18 → 19.2px | spec |
| `--text-xl` | +2 | 21.6 → 23px | custom |
| `--text` | — | `var(--text-m)` | alias |

**JSON path:** `typography.text.mode`, `typography.text.default`, `typography.text.scale.{mobile,desktop}.{value,ratio}`, `typography.text.sizes.{key}`

Text carries no derived line-height/letter-spacing — that model is heading-only for now (ADR 0022 scopes the `typography.text` axis out).

### Headings (`--h{n}` + derived typography)

Symmetric open set: keys `h1`–`h6` emit `--h1`–`--h6` (ADR 0027 amends 0024 — `position` orders the scale math, the key names the token). Anchor is `h6` (position 0); h1 is largest. No bare alias — h1–h6 are guaranteed present. Factory anchors: mobile 15/1.5, desktop 16/1.618.

| Variable | Position | Mobile → Desktop |
|----------|----------|------------------|
| `--h6` | 0 (anchor) | 15 → 16px |
| `--h5` | 1 | 23 → 26px |
| `--h4` | 2 | 34 → 42px |
| `--h3` | 3 | 51 → 68px |
| `--h2` | 4 | 76 → 110px |
| `--h1` | 5 | 114 → 177px |

Each level also emits **derived typography** (ADR 0022), driven by the `style` knobs `{ leading, letterSpacingSlope, letterSpacingConstant, weight }`:

```css
--h1-line-height: calc(1em + 8px);          /* text's own size + fixed leading */
--h1-letter-spacing: calc(-0.022em + 0.35px);
--h1-weight: 600;                           /* one authored weight for all levels */
```

Because line-height is affine in font size, small headings run loose and display sizes tight automatically — and it stays correct when the scale is retuned. The `em` term tracks the fluid clamp for free.

**JSON path:** `typography.headings.mode`, `.default`, `.scale.{mobile,desktop}.{value,ratio}`, `.sizes.h{n}.position`, `.style.{leading,letterSpacingSlope,letterSpacingConstant,weight}`, and (custom mode) `.custom.h{n}.{mobile,desktop}` + `.customStyle.h{n}.{lineHeight,letterSpacing,weight}`

### Radius (`--radius-{key}` + bare `--radius`)

Open pick-one family. Every key in `sizes` with a `value` emits `--radius-{key}`; the `default` step backs the bare `--radius` alias (ADR 0017).

| Variable | Default |
|----------|---------|
| `--radius-xs` | 2px |
| `--radius-s` | 4px |
| `--radius-m` | 8px |
| `--radius-l` | 16px |
| `--radius-xl` | 24px |
| `--radius-full` | 9999px |
| `--radius` | `var(--radius-m)` (alias) |

**JSON path:** `radius.default`, `radius.sizes.{key}.value`

### Shadows (`--shadow-{key}` + bare `--shadow`)

Composite pick-one family, built from x, y, blur, spread, and opacity. The seeded `default` is `none`, so the bare alias emits the literal `none` (a step name would round-trip through a `var()`; `none` cannot).

| Variable | Default |
|----------|---------|
| `--shadow-xs` | `0px 1px 1px 0px rgba(0,0,0,0.05)` |
| `--shadow-s` | `0px 1px 2px 0px rgba(0,0,0,0.05)` |
| `--shadow-m` | `0px 4px 6px -1px rgba(0,0,0,0.1)` |
| `--shadow-l` | `0px 10px 15px -3px rgba(0,0,0,0.1)` |
| `--shadow-xl` | `0px 20px 25px -5px rgba(0,0,0,0.15)` |
| `--shadow` | `none` (alias) |

**JSON path:** `shadows.default`, `shadows.sizes.{key}.{x,y,blur,spread,opacity}`

### Borders (`--border-{key}` + bare `--border`)

Open pick-one family; the `default` step backs the bare `--border` alias.

| Variable | Default |
|----------|---------|
| `--border-s` | 1px |
| `--border-m` | 2px |
| `--border-l` | 4px |
| `--border` | `var(--border-m)` (alias) |

**JSON path:** `borders.default`, `borders.sizes.{key}.value`

### Colors (`--{name}`)

> Color emission is unchanged in M1 — its shape change (ramp / palette break / state tier) lands in M3. The one M1 touchpoint is the palette bridge below.

Base colors from `color.sections.*.colors.*`. Each enabled color generates a base variable plus hue shade variants.

**Base:** `--primary`, `--neutral`, `--info`, `--success`, `--warning`, `--error`

**Hue shades** (appended to each base color):

| Shade | Lightness | Example |
|-------|-----------|---------|
| `ultra-light` | 90% | `--neutral-ultra-light` |
| `light` | 80% | `--neutral-light` |
| `semi-light` | 65% | `--neutral-semi-light` |
| `semi-dark` | 35% | `--neutral-semi-dark` |
| `dark` | 20% | `--neutral-dark` |
| `ultra-dark` | 10% | `--neutral-ultra-dark` |

The generator takes the base color's hue and saturation, then replaces its lightness with the shade's target percentage.

**JSON path:** `color.sections.{section}.colors.{name}`, `color.hues.{shade}.value`

### Palette bridge (`--palette-surface`)

M1 emits one forward-looking palette token — `--palette-surface: var(--colorway-base)` — the surface slot the full palette shape (M3) will build out. It is the single guaranteed color key in the base spec.

### Colorways (`--colorway-*`)

> Renamed/remapped to the palette break in M3; unchanged in M1.

Colorways are named color schemes applied via `data-colorway` attributes. Each colorway defines five tokens:

| Token | Role |
|---|---|
| `--colorway-base` | Surface/background color |
| `--colorway-hard-contrast` | Headings, strong text |
| `--colorway-contrast` | Body text |
| `--colorway-soft-contrast` | Borders, strokes, dividers (not text) |
| `--colorway-accent` | Decorative highlights: links, icons, eyebrows |

Each generates a scoped CSS block:

```css
[data-colorway="primary"] {
    --colorway-base: var(--primary);
    --colorway-hard-contrast: #ffffff;
    --colorway-contrast: var(--primary-light);
    --colorway-soft-contrast: var(--primary-semi-light);
    --colorway-accent: var(--primary-dark);
}
```

Components pick the token that matches their semantic role: surface components use `base`/`hard-contrast`, body text uses `contrast`, structural elements (borders, dividers) use `soft-contrast`, and decorative elements (links, icons) use `accent`.

**JSON path:** `color.colorways.{name}.{base,hard-contrast,contrast,soft-contrast,accent}`

### Font Weights

| Variable | Default |
|----------|---------|
| `--font-weight-medium` | 500 |

## Scale Calculation

Spacing, text, and headings compute each step *per device* with the anchor-relative formula:

```
deviceValue = scale.{device}.value * scale.{device}.ratio^(position − anchorPosition)
```

where `anchorPosition` comes from the `default` (anchor) step. The mobile and desktop results are then folded into a `clamp()` whose middle term is the line through `(viewport.mobile, mobileValue)` and `(viewport.desktop, desktopValue)`, computed in PHP so the output is deterministic and server-side verifiable:

```
clamp(min, calc(intercept·px + slope·vw), max)
```

Equal device values emit a plain static px value instead of a degenerate clamp. Changing an anchor shifts that device's steps uniformly; changing a `ratio` adjusts the contrast between steps; widening the `viewport` range flattens the slope.

### Modes (ADR 0018)

Each scale family is either `mode: scale` (systematic, two anchors) or `mode: custom` (hand-authored per-size `{mobile, desktop}` pairs). The two stores coexist in the data; `mode` points at the one the generator reads, and the inactive store is invisible — never emitted, never a fallback. Switching `scale → custom` seeds any missing custom key from the current scale computation (an editor-side contract), so the switch is nondestructive. **An incomplete custom store while `mode: custom` is invalid data — the generator errors rather than backfilling from scale math**, protecting the always-full-emission guarantee. The verify layer asserts both this and viewport distinctness.

## How to Customize

1. Copy `defaults.json` to a new file (e.g., `my-tokens.json`)
2. Edit values — set per-device anchors under `scale.{mobile,desktop}`, change a `ratio`, tune the `viewport` range, add or drop steps (presence is membership), re-point `default`, adjust heading `style` knobs, add colors
3. Run `php styles/generate.php my-tokens.json --output dist/tokens.css`
4. Optionally run `php styles/verify.php` to check the base-spec guarantee, the fallback contract, store completeness, and viewport distinctness

To hand-author a size that opts out of the scale, set the family's `mode` to `custom` and edit its `custom.{key}.{mobile,desktop}` pairs. To drop a step, remove it from `sizes` — it stops emitting, and any component reference degrades to the family's bare alias.

## Component CSS Usage

Components reference size tokens through **chained fallbacks** to the family alias, so an incomplete spec degrades to the family default instead of a broken `var()`:

```css
.my-component {
    padding: var(--space-m, var(--space));
    font-size: var(--text-s, var(--text));
    border-radius: var(--radius-m, var(--radius));
    border: var(--border-s, var(--border)) solid var(--colorway-soft-contrast);
    box-shadow: var(--shadow-m, var(--shadow));
    background-color: var(--colorway-base);
    color: var(--colorway-hard-contrast);
}
```

If a spec omits `--space-m`, the reference resolves to `--space` (the anchor); if it omits the alias too, `styles/verify.php`'s fallback-presence guard flags it before it ships.
