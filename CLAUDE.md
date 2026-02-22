# Anticustom Project Guidelines

## Overview

Anticustom is a monorepo containing three tools plus an explorer:

- **styles/** — Design token system: schema, defaults, and CSS variable generator
- **components/** — Component library: schemas, CSS styles, PHP templates, and shared helpers
- **forms/** — Form builder (placeholder, not yet implemented)
- **explorer/** — Component preview tool (placeholder, not yet implemented)

## Design Principles

See [design.md](design.md) for universal design principles that apply across all projects.

## Repository Structure

```
anticustom/
├── styles/
│   ├── tokens.schema.json   # Token editor schema (panels, icons, presets)
│   ├── defaults.json         # Default token values
│   └── generate.php          # CSS variable generator
├── components/
│   ├── render.php            # Shared helper functions for all templates
│   ├── {name}/
│   │   ├── {name}.schema.json
│   │   ├── styles/_base.css
│   │   ├── styles/default.css
│   │   └── templates/php.php
├── forms/                    # Placeholder
└── explorer/                 # Placeholder
```

## Component System

### Schema Structure
Components use JSON schemas defining:
- `fields` — Props the component accepts (name, type, default, options)
- `tabs` — UI organization for editors
- `tokens_used` — Design tokens referenced by the component's CSS
- `children` — Child component definitions for composition

### Interpolation Syntax
Use `{field}` for runtime data interpolation in component props:
- `{status}` — Simple field access
- `{user.name}` — Nested property access
- `/edit/{id}` — Mixed with static text

### Hybrid Styling Approach
Components support both:
- **Semantic props** (`variant: "success"`) — For common, well-defined styles
- **Class prop** (`class: "my-custom"`) — Escape hatch for custom styling

### Composition Pattern
Complex components delegate rendering to child components rather than handling every format:
- Plain text: Use `key` property
- Special rendering: Use `component` property to delegate to child components
- Example: Tables don't know how to render dates or badges — they delegate to badge/date components

### Template Helpers
All templates rely on `components/render.php` which provides:
- `anti_classes()` — Build conditional class strings
- `anti_attrs()` — Build HTML attribute strings
- `attr_escape()`, `html_escape()`, `url_escape()` — Output escaping
- `scan_components()`, `scan_styles()` — Component discovery
- `render_component()`, `get_default_props()` — Rendering

## Notes

- `forms/` and `explorer/` are placeholders for future phases
- Component schemas do NOT include `ai_metadata` or `permission` fields (those live in antisloth)
- `styles/generate.php` is a basic CSS variable generator; token naming will be refined in Phase 2
