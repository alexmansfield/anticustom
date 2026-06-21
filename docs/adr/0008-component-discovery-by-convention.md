---
status: accepted
thread: 01kvme6fqqxn6fjj1pfvggfr6d
---

# Components are discovered by filesystem convention, not a registry

*Reconstructed from existing code — documents a decision already embodied in the codebase rather than one taken prospectively.*

Components and their styles are auto-discovered from the filesystem: `scan_components()` registers any `{name}/{name}.schema.json` directory, and `scan_styles()` exposes every non-`_`-prefixed CSS file as a selectable style. There is no central manifest.

## Considered Options
- **Central registry/manifest file** — one place to validate and control, but drifts from the filesystem and is edited on every addition.
- **Code registration per component** — explicit but adds boilerplate.

## Consequences
Adding a component is dropping a conforming directory in place. The cost is a hard naming convention — directory name, schema name, and template filename must all match — and no central point to validate the set.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvme4qx32wvvg0h1a5rmwy4b)*
