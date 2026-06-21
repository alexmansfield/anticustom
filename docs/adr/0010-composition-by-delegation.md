---
status: accepted
thread: 01kvme6fqs8b1nacq57jgz6jyv
---

# Components compose by delegation, not monolithic rendering

*Reconstructed from existing code — documents a decision already embodied in the codebase rather than one taken prospectively.*

Parents render children via `anti_component()`/`render_components()`/`resolve_child_props()`, and data carries inline `{name, props}` component references resolved per-row through a `{field}` interpolation language before rendering. A container (e.g. `table`) knows its own structure and delegates special cell rendering to the owning component (e.g. `badge`).

## Considered Options
- **Monolithic templates with per-format branching** — simpler call path, but every new format edits every container.
- **A typed slot/transformer API** — more validatable than interpolation strings, but heavier and less JSON-authorable.

## Consequences
Containers stay format-agnostic and formats are reusable. The cost is indirection and a bespoke `{field}` mini-language (which silently leaves unresolved placeholders in output).

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvme4qx32wvvg0h1a5rmwy4b)*
