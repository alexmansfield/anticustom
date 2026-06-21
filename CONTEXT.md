# Anticustom

Anticustom is a token + component system where every design module is a testable contract: it declares its requirements and the ways it can break, so novice editors are warned and AI agents provably cannot ship a broken layout.

## Language

**Failure state**:
A named, machine-checkable predicate a component can fail at edit or publish time given particular content, viewport, or data (e.g. `no-upscale`, `min-contrast`). Declared per component via the `failure_states` array (mirroring `tokens_used`) and defined once in the shared failure-states registry. Distinct from an **input invariant**: a failure state may be a render- or layout-level emergent property that no single field constrains.
_Avoid_: test, rule, assertion, antipattern

**Invariant layers**:
The cost-ordered spectrum a failure state is evaluated at — **input** (cheap, deterministic, over the JSON props), **render** (cheap-ish, over the HTML string), and **layout** (expensive, viewport-dependent, over real browser geometry). Checks run cheapest-first and fail fast.
_Avoid_: validation level, check type

**Advisory** vs **Enforced**:
The two severity tiers of a failure state that must never blur. An **advisory** finding (the `warning` threshold) is dismissible and tuned for human editors; it informs. An **enforced** finding (the `gated` threshold) is blocking, stricter, and tuned for AI agents and the publish gate; it prevents shipping.
_Avoid_: soft/hard error, warning/blocker (as the canonical names)
