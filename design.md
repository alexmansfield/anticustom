# Design Principles

## Overflow Hidden

**Never use `overflow: hidden` without explicit permission.** If you think it's necessary, ask first.

`overflow: hidden` clips content to container bounds, which breaks:
- Dropdown menus
- Tooltips and popovers
- Any element that needs to extend beyond its parent

**Alternatives:**
- Use `border-radius` on child elements directly (e.g., first/last table cells)
- Use `overflow: visible` (default) and handle edge cases individually
- If clipping is truly needed, discuss the trade-offs first

## No Tailwind CSS

**Do not use Tailwind CSS.** This project uses semantic CSS with the anticustom component system.

Tailwind's utility classes create:
- Verbose, hard-to-read markup
- Inconsistent styling across components
- Tight coupling between structure and presentation

**Instead:**
- Use the component system's semantic props (`variant`, `size`, `colorway`)
- Use the `class` prop escape hatch for custom styling when needed
- Write CSS in component style files, not inline utilities

## Hover States

**Only apply hover states to interactive elements.** If an element isn't clickable, it shouldn't have a hover effect.

Hover states signal affordance—they tell users "you can interact with this." Non-clickable elements with hover states create confusion and false expectations.

**Good:**
```css
/* Clickable rows get hover state */
.table-clickable tbody tr:hover td { background: #f9fafb; }
```

**Bad:**
```css
/* All rows hover, even non-clickable ones */
.table tbody tr:hover td { background: #f9fafb; }
```
