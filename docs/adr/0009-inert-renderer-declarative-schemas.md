---
status: accepted
thread: 01kvme6fqr4qmansve3c1d9m19
---

# The renderer is inert; schemas are declarative contracts

*Reconstructed from existing code — documents a decision already embodied in the codebase rather than one taken prospectively.*

`anti_component()` merges field defaults and injects the `interface` block, and does nothing else: it never enforces `required`, `validation`, or `tokens_used`, and it fails soft (missing template → HTML comment; unresolved `{field}` → left verbatim). Schema contracts are interpreted by other layers (editor, validators, failure-state checks), not the render path.

## Considered Options
- **Enforce validation/required at render time** — a runtime net, but couples rendering to validation and duplicates the editor/validator layer.
- **Fail hard on missing template / unresolved field** — louder errors, but brittle on the live-preview path where partial input is normal.

## Consequences
Rendering stays a thin, predictable transform, and new contract consumers (e.g. the failure-states layer) attach without touching it. The cost is no runtime safety net — nothing stops a render of invalid props.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvme4qx32wvvg0h1a5rmwy4b)*
