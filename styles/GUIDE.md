# Styles Tool Guide

## Purpose

The styles tool defines design tokens in JSON (`defaults.json`) and generates a CSS variables file from them via `generate.php`. Components reference these CSS variables; the token system ensures consistent spacing, typography, colors, and effects across all components.

The size families follow the **palette-model emission model** (ADRs 0015–0028):

- **Open ordered sets** — a family lists its steps in `sizes`; *presence is membership*. There is no `enabled` flag. A step a spec omits simply does not emit.
- **Key-identity naming** — the emitted variable is exactly `--{key}`. The spec author owns the namespace; the generator adds no prefix of its own.
- **Anchor-as-origin** — a scale family's `default` step is its anchor. That step's `value` *is* the scale origin; every other step is `anchorValue * ratio^(position − anchorPosition)`. There is no separate `baseSize`.
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

## Token Categories

### Spacing (`--space-{key}` + bare `--space`)

Open scale family. Every key in `sizes` emits `--space-{key}`; the `default` step is the anchor (origin), and its `value` seeds the scale. Other steps: `anchorValue * ratio^(position − anchorPosition)`, rounded to an integer.

| Variable | Position | Value | Spec? |
|----------|----------|-------|-------|
| `--space-xxs` | -3 | 5px | custom |
| `--space-xs` | -2 | 7px | spec |
| `--space-s` | -1 | 11px | spec |
| `--space-m` | 0 (anchor) | 16px | spec |
| `--space-l` | +1 | 24px | spec |
| `--space-xl` | +2 | 36px | spec |
| `--space-xxl` | +3 | 54px | custom |
| `--space` | — | `var(--space-m)` | alias |

**JSON path:** `spacing.default`, `spacing.ratio`, `spacing.sizes.{key}.position`, `spacing.sizes.{default}.value`

The `spec` column classifies base-spec tokens vs custom tokens riding the same ramp (ADR 0023). Both emit identically — the classification is guaranteed-key bookkeeping, asserted by `styles/verify.php`.

### Text Sizes (`--text-{key}` + bare `--text`)

Open scale family, same shape as spacing but kept to one decimal place (finer type steps).

| Variable | Position | Value | Spec? |
|----------|----------|-------|-------|
| `--text-xs` | -2 | 12.6px | custom |
| `--text-s` | -1 | 14.2px | spec |
| `--text-m` | 0 (anchor) | 16px | spec |
| `--text-l` | +1 | 18px | spec |
| `--text-xl` | +2 | 20.3px | custom |
| `--text` | — | `var(--text-m)` | alias |

**JSON path:** `typography.text.default`, `typography.text.ratio`, `typography.text.sizes.{key}`

Per-step `lineHeight` / `letterSpacing` / `weight` are carried in `defaults.json` as the (inactive) custom-store seed but are **not emitted** in M1 — derived typography lands in M2 (ADR 0022).

### Headings (`--h{n}`)

Symmetric open set: keys `h1`–`h6` emit `--h1`–`--h6` (ADR 0027 amends 0024 — headings drop rank-emission; `position` orders the scale math, the key names the token). Anchor is `h6` (position 0); h1 is largest. No bare alias — h1–h6 are guaranteed present.

| Variable | Position | Value |
|----------|----------|-------|
| `--h6` | 0 (anchor) | 16px |
| `--h5` | 1 | 26px |
| `--h4` | 2 | 42px |
| `--h3` | 3 | 68px |
| `--h2` | 4 | 110px |
| `--h1` | 5 | 177px |

**JSON path:** `typography.headings.default`, `typography.headings.ratio`, `typography.headings.sizes.h{n}.position`

Per-level line-height/letter-spacing/weight are migrated into `defaults.json` but dormant until M2 (ADR 0022 derived heading typography).

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

Spacing, text, and headings use the anchor-relative formula:

```
value = anchorValue * ratio^(position − anchorPosition)
```

where `anchorValue` / `anchorPosition` come from the `default` (anchor) step. The anchor step itself resolves to `anchorValue`. Changing the anchor value shifts all steps uniformly; changing `ratio` adjusts the contrast between steps. A ratio of 1.5 means each step is 1.5× the previous.

## How to Customize

1. Copy `defaults.json` to a new file (e.g., `my-tokens.json`)
2. Edit values — set the anchor step's `value`, change `ratio`, add or drop steps (presence is membership), re-point `default`, add colors
3. Run `php styles/generate.php my-tokens.json --output dist/tokens.css`
4. Optionally run `php styles/verify.php` to check the base-spec guarantee and fallback contract still hold

To drop a step, remove it from `sizes` — it stops emitting, and any component reference degrades to the family's bare alias. (Per-step *custom values* that opt out of the scale are an M2 feature, `mode: scale | custom`.)

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
