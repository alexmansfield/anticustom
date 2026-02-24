# Styles Tool Guide

## Purpose

The styles tool defines design tokens in JSON (`defaults.json`) and generates a CSS variables file from them via `generate.php`. Components reference these CSS variables; the token system ensures consistent spacing, typography, colors, and effects across all components.

## Quick Start

```bash
# Generate CSS to stdout
php styles/generate.php

# Generate to a file
php styles/generate.php --output dist/tokens.css

# Generate from custom tokens
php styles/generate.php path/to/custom-tokens.json --output dist/tokens.css
```

## Token Categories

### Spacing (`--space-{size}`)

Scale-based: `baseSize * scale^position`

| Variable | Position | Default |
|----------|----------|---------|
| `--space-xxs` | -3 | 4px |
| `--space-xs` | -2 | 7px |
| `--space-s` | -1 | 11px |
| `--space-m` | 0 (base) | 16px |
| `--space-l` | +1 | 24px |
| `--space-xl` | +2 | 36px |
| `--space-xxl` | +3 | 54px |

**JSON path:** `spacing.baseSize`, `spacing.scale`, `spacing.sizes.{size}.value`

When a size has `enabled: true` with a `value`, the override is used instead of the calculated value.

### Text Sizes (`--text-{size}`)

Scale-based: `baseSize * scale^position`

| Variable | Position | Default |
|----------|----------|---------|
| `--text-xs` | -2 | 12.6px |
| `--text-s` | -1 | 14.2px |
| `--text-m` | 0 (base) | 16px |
| `--text-l` | +1 | 18px |
| `--text-xl` | +2 | 20.3px |

Also generates `--text-{size}-line-height` and `--text-{size}-weight` when overridden.

**JSON path:** `typography.text.baseSize`, `typography.text.scale`, `typography.text.sizes.{size}`

### Headings (`--heading-{level}`)

Scale-based with inverted positions (h1 = largest):

| Variable | Position | Default |
|----------|----------|---------|
| `--heading-6` | 0 | 16px |
| `--heading-5` | 1 | 26px |
| `--heading-4` | 2 | 42px |
| `--heading-3` | 3 | 68px |
| `--heading-2` | 4 | 109px |
| `--heading-1` | 5 | 177px |

Also generates `--heading-{n}-line-height`, `--heading-{n}-letter-spacing`, `--heading-{n}-weight`.

**JSON path:** `typography.headings.baseSize`, `typography.headings.scale`, `typography.headings.sizes.h{n}`

### Radius (`--radius-{size}`)

Direct values from JSON:

| Variable | Default |
|----------|---------|
| `--radius-xs` | 2px |
| `--radius-s` | 4px |
| `--radius-m` | 8px |
| `--radius-l` | 16px |
| `--radius-xl` | 24px |
| `--radius-full` | 9999px |

**JSON path:** `radius.sizes.{size}.value`

### Shadows (`--shadow-{size}`)

Composite values built from x, y, blur, spread, and opacity:

| Variable | Default |
|----------|---------|
| `--shadow-xs` | `0 1px 1px 0 rgba(0,0,0,0.05)` |
| `--shadow-s` | `0 1px 2px 0 rgba(0,0,0,0.05)` |
| `--shadow-m` | `0 4px 6px -1px rgba(0,0,0,0.1)` |
| `--shadow-l` | `0 10px 15px -3px rgba(0,0,0,0.1)` |
| `--shadow-xl` | `0 20px 25px -5px rgba(0,0,0,0.15)` |

**JSON path:** `shadows.{size}.{x,y,blur,spread,opacity}`

### Borders (`--border-{size}`)

| Variable | Default |
|----------|---------|
| `--border-s` | 1px |
| `--border-m` | 2px |
| `--border-l` | 4px |

**JSON path:** `borders.sizes.{size}.value`

### Colors (`--{name}`)

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

### Colorways (`--colorway-*`)

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

Spacing, text, and headings all use the formula:

```
value = baseSize * scale^position
```

This means changing `baseSize` shifts all sizes uniformly, while changing `scale` adjusts the contrast between sizes. A scale of 1.5 means each step is 1.5x the previous.

## How to Customize

1. Copy `defaults.json` to a new file (e.g., `my-tokens.json`)
2. Edit values — change `baseSize`, `scale`, enable overrides, add colors
3. Run `php styles/generate.php my-tokens.json --output dist/tokens.css`

To override a calculated value, set `enabled: true` and provide a `value` on the size entry.

## Component CSS Usage

Components reference tokens through CSS custom properties with fallbacks:

```css
.my-component {
    padding: var(--space-m, 1rem);
    font-size: var(--text-s, 0.875rem);
    border-radius: var(--radius-m, 0.5rem);
    background-color: var(--colorway-base);
    color: var(--colorway-hard-contrast);
}
```

Fallback values ensure components render even without the generated CSS loaded.
