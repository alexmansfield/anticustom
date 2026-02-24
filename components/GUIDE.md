# Anticustom Component System Guide

## Architecture

Each component is a self-contained directory:

```
components/{name}/
├── {name}.schema.json      # Component definition (fields, tabs, tokens)
├── styles/
│   ├── _base.css           # Structural layout (position, display, sizing)
│   ├── plato.css           # Named style: clean, shadowed, professional
│   └── aristotle.css       # Named style: flat, bordered, no shadows (optional)
└── templates/
    └── php.php             # PHP rendering template
```

**Key principle:** `_base.css` is structure-only (works without tokens). Named style files (e.g. `plato.css`, `aristotle.css`) map design tokens to visual properties. Components can have one or many style files — the explorer discovers them automatically via `scan_styles()`.

## Rendering

### Render a component

```php
require_once 'components/render.php';
anti_components_dir(__DIR__ . '/components');  // set once

anti_component('button', ['text' => 'Click me', 'url' => '/action']);
```

`anti_component()` loads the schema, merges default props, finds the template, and echoes HTML.

### Render multiple components

```php
render_components([
    ['type' => 'badge', 'props' => ['text' => 'New', 'variant' => 'success']],
    ['type' => 'button', 'props' => ['text' => 'Action', 'url' => '#']],
]);
```

### Get a schema

```php
$schema = anti_get_schema('hero');  // cached, returns [] if not found
```

## Schema Structure

```json
{
  "name": "badge",
  "label": "Badge",
  "version": "1.0.0",
  "category": "data-display",
  "tabs": [
    { "id": "content", "label": "Content" }
  ],
  "fields": [
    {
      "name": "text",
      "type": "text",
      "label": "Text",
      "default": "",
      "required": true,
      "tab": "content"
    }
  ],
  "tokens_used": ["space-xs", "radius-full"],
  "children": { ... }
}
```

### Fields

Each field defines a component prop:
- `name` — Prop key in templates
- `type` — Data type (text, select, boolean, array, image, url, textarea, colorway)
- `default` — Default value when prop is not provided
- `required` — Whether the prop must be provided
- `tab` — Which editor tab this field appears in
- `options` — For select fields: `[{value, label, description}]`
- `runtime` — True for props that come from application data (not editor-configured)

### Children (composition)

Parent components can define child slots:

```json
"children": {
  "allowed": ["intro", "button"],
  "slots": [
    {
      "name": "intro",
      "type": "intro",
      "fixed": true,
      "defaults": { "title": "Welcome", "size": "l" }
    }
  ]
}
```

Use `resolve_child_props($schema, $slotIndex, $childProps)` to merge slot defaults with provided props.

## Template Conventions

Templates receive a `$props` array and output HTML directly:

```php
<?php
// 1. Extract props with defaults
$text    = $props['text'] ?? '';
$variant = $props['variant'] ?? 'neutral';

// 2. Guard clause for required props
if (empty($text)) { return; }

// 3. Build CSS classes
$classes = anti_classes([
    'anti-badge'                => true,
    "anti-badge--{$variant}"    => true,
]);
?>
<span class="<?php echo attr_escape($classes); ?>"><?php echo html_escape($text); ?></span>
```

### Available helpers

| Function | Purpose |
|----------|---------|
| `anti_classes(['class' => bool])` | Build conditional class string |
| `anti_attrs(['attr' => value])` | Build HTML attributes (skips null/false/empty) |
| `attr_escape($val)` | Escape for HTML attributes |
| `html_escape($val)` | Escape for HTML content |
| `url_escape($val)` | Escape for URL contexts |
| `anti_component($type, $props)` | Render a child component |
| `anti_get_schema($name)` | Load a component schema (cached) |
| `resolve_child_props($schema, $i, $props)` | Merge slot defaults with child props |
| `anti_interpolate($template, $data)` | Replace `{field}` placeholders |
| `anti_interpolate_props($props, $data)` | Recursively interpolate all string values |

## Composition Pattern

Parent components delegate rendering to children rather than handling every format themselves.

**Example: Hero delegates to intro + buttons**

```php
// In hero template:
$children = $props['children'] ?? [];
$hero_schema = anti_get_schema('hero');

foreach ($children as $index => $child) {
    $resolved = resolve_child_props($hero_schema, $index, $child['props'] ?? []);
    anti_component($child['type'], $resolved);
}
```

**Example: Table delegates cell rendering to badge**

Column definition:
```json
{
  "label": "Status",
  "component": {
    "name": "badge",
    "props": { "text": "{status}", "variant": "{severity}" }
  }
}
```

The table interpolates `{status}` and `{severity}` from each row's data, then calls `anti_component('badge', $resolved)`.

## Interpolation Syntax

Use `{field}` in child component props to reference parent data:

- `{status}` — Direct field access
- `{user.name}` — Nested property access
- `/edit/{id}` — Mixed with static text

```php
$resolved = anti_interpolate_props(
    ['text' => '{status}', 'url' => '/users/{id}'],
    ['id' => 42, 'status' => 'Active']
);
// Result: ['text' => 'Active', 'url' => '/users/42']
```

## Styling Approach

### `_base.css` — Structure

Handles layout, positioning, and responsive behavior. Uses CSS custom properties with fallbacks as hooks for the theme layer:

```css
.anti-badge--s {
    padding: var(--anti-badge-padding-s, 0.125rem 0.5rem);
    font-size: var(--anti-badge-font-s, 0.75rem);
}
```

### `{style}.css` — Aesthetics (named styles)

Each named style file (e.g. `plato.css`, `aristotle.css`) maps design tokens to visual properties. This is the "theme" layer:

```css
/* plato.css */
.anti-badge {
    --anti-badge-padding-s: var(--space-xs, 0.25rem) var(--space-s, 0.5rem);
    --anti-badge-font-s: var(--text-xs, 0.75rem);
    border-radius: var(--radius-full, 9999px);
}
```

Components can have multiple style files. The explorer auto-discovers them and presents a style switcher in the nav bar. A component without a given style file simply shows base-only styling.

### Colorway system

Components use colorway CSS variables instead of hard-coded colors:
- `--colorway-base` — Surface/background color
- `--colorway-hard-contrast` — Primary text, max contrast against base
- `--colorway-soft-contrast` — Secondary text, subheadings, metadata
- `--colorway-accent` — Decorative highlights: links, icons, eyebrows

Surface components use `base`/`hard-contrast`. Decorative elements (links, icons, eyebrows) use `accent`. Buttons invert — using `hard-contrast` as their prominent fill color.

Set via `data-colorway` attribute: `<div data-colorway="primary">`.

## Creating a New Component

1. **Create directory:** `components/{name}/styles/`, `components/{name}/templates/`
2. **Write schema:** `{name}/{name}.schema.json` with fields, tabs, tokens_used
3. **Write `_base.css`:** Structure only, no colors. Use CSS custom properties for values the theme layer should control.
4. **Write `plato.css`:** Map design tokens to CSS custom properties. Add color, typography, and visual refinements. Optionally create additional style files (e.g. `aristotle.css`) for alternative aesthetics.
5. **Write `templates/{name}.php`:** Extract props, build classes with `anti_classes()`, escape all output.
6. **Verify:** Add test case to `verify.php` and run `php components/verify.php`.

### Checklist

- [ ] Schema has `name`, `label`, `version`, `category`, `fields`, `tokens_used`
- [ ] Template extracts all props with defaults matching schema
- [ ] All user content uses `html_escape()` or `attr_escape()`
- [ ] CSS class prefix is `anti-{name}` (with `--` for modifiers)
- [ ] `_base.css` works standalone (has fallback values)
- [ ] Style files (e.g. `plato.css`) reference only tokens listed in `tokens_used`
- [ ] Component renders in `verify.php` without errors
