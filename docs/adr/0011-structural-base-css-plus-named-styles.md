---
status: accepted
thread: 01kvme6fqvmva8r8azmdfdf4ck
---

# CSS is split into structural `_base.css` plus swappable named styles

*Reconstructed from existing code — documents a decision already embodied in the codebase rather than one taken prospectively.*

Each component separates a standalone structural `_base.css` (layout/responsive, with `var(--x, fallback)` hooks) from one or more named style files (`plato.css`, `aristotle.css`) that map design tokens onto those hooks. The explorer auto-discovers the named styles as a switcher; style files reference only `tokens_used`.

## Considered Options
- **Single combined stylesheet** — simpler, but no swappable themes and no base-only fallback.
- **Global theme layer** — less duplication, but no per-component alternative aesthetics.

## Consequences
A component can carry multiple visual identities and degrade to base-only. The cost is per-style duplication and the discipline to keep `_base.css` color-free and fully fallback-guarded.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvme4qx32wvvg0h1a5rmwy4b)*
